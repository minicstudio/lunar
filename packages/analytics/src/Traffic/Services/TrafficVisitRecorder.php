<?php

namespace Lunar\Analytics\Traffic\Services;

use ArrayObject;
use Filament\Facades\Filament;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Analytics\Traffic\Contracts\ClassifiesTrafficSource;
use Lunar\Analytics\Traffic\DataTransferObjects\TrafficSource;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Models\Cart;

/**
 * Records storefront visits and keeps the current visit in the Laravel session.
 *
 * A visit starts on the first page view of a session, or on a later page view
 * whose campaign parameters give a different source, medium or campaign. A
 * visitor whose active cart already has a traffic source starts no visit; the
 * session continues that cart's visit instead.
 */
class TrafficVisitRecorder
{
    /**
     * Session key holding the current visit.
     */
    public const SESSION_KEY = 'analytics_traffic_visit';

    public function __construct(protected ClassifiesTrafficSource $classifier) {}

    /**
     * Whether traffic recording is switched on (`lunar.analytics.traffic.enabled`).
     */
    public function enabled(): bool
    {
        return (bool) config('lunar.analytics.traffic.enabled', true);
    }

    /**
     * Whether the current request may record or carry a visit.
     *
     * False while the admin panel serves the request, and while a staff
     * member is logged in on the panel guard in this session.
     */
    public function shouldTrack(): bool
    {
        if (app()->bound('filament') && Filament::isServing()) {
            return false;
        }

        if (app()->bound('lunar-panel') && Auth::guard(LunarPanel::getPanel()->getAuthGuard())->check()) {
            return false;
        }

        return true;
    }

    /**
     * Record a visit for the request when it starts one.
     *
     * Does nothing when the session already has a visit and the request has
     * no campaign parameters, or they give the current visit's source,
     * medium and campaign. When the visitor's cart already has a traffic
     * source, nothing is recorded and a session without a visit continues
     * the cart's visit.
     */
    public function record(Request $request): void
    {
        if (! $this->enabled() || ! $this->shouldTrack() || ! $request->hasSession()) {
            return;
        }

        $source = $this->classifier->classify($request);

        if (! $source) {
            return;
        }

        $session = $request->session();
        $current = $this->normalisePayload($session->get(self::SESSION_KEY));

        if ($current !== null && (! $this->classifier->hasCampaignParameters($request) || $this->isSameSource($current, $source))) {
            return;
        }

        if ($cartSource = $this->visitorCartSource($request, $session)) {
            if ($current === null) {
                $session->put(self::SESSION_KEY, $cartSource);
            }

            return;
        }

        $visit = TrafficVisit::query()->create([
            'source' => $source->source,
            'medium' => $source->medium,
            'campaign' => $source->campaign,
            'referrer_host' => $source->referrerHost,
            'landing_path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
        ]);

        $session->put(self::SESSION_KEY, [
            'visit_id' => (int) $visit->getKey(),
            'source' => $visit->source,
            'medium' => $visit->medium,
            'campaign' => $visit->campaign,
        ]);
    }

    /**
     * The session's current visit.
     *
     * Null when recording is disabled, the request may not be tracked, no
     * session is available or the session has no visit.
     *
     * @return TrafficVisitPayload|null
     */
    public function current(): ?array
    {
        if (! $this->enabled() || ! $this->shouldTrack()) {
            return null;
        }

        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        return $this->normalisePayload($request->session()->get(self::SESSION_KEY));
    }

    /**
     * The traffic source of the visitor's active cart, if it has one.
     *
     * The cart is the session's cart or, without one, the logged-in user's
     * latest unmerged active cart, picked as `CartSessionManager` picks it.
     * The cart is read directly, so the lookup never calculates or creates one.
     *
     * @return TrafficVisitPayload|null
     */
    protected function visitorCartSource(Request $request, Session $session): ?array
    {
        $cartId = $session->get(config('lunar.cart_session.session_key'));

        if ($cartId) {
            $cart = Cart::query()->unmerged()->active()->whereKey($cartId)->first();
        } elseif ($user = Auth::guard()->user()) {
            $cart = Cart::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->unmerged()
                ->active()
                ->latest('id')
                ->first();
        } else {
            $cart = null;
        }

        return $cart ? $this->normalisePayload(data_get($cart->meta, 'traffic_source')) : null;
    }

    /**
     * Whether a classified source has the current visit's source, medium and campaign.
     *
     * @param  TrafficVisitPayload  $current
     */
    protected function isSameSource(array $current, TrafficSource $source): bool
    {
        return $current['source'] === $source->source
            && $current['medium'] === $source->medium
            && $current['campaign'] === $source->campaign;
    }

    /**
     * Normalise a stored visit (session payload or cart meta) to its four keys.
     *
     * @param  mixed  $payload  The stored value.
     * @return TrafficVisitPayload|null
     */
    protected function normalisePayload(mixed $payload): ?array
    {
        if ($payload instanceof ArrayObject) {
            $payload = $payload->getArrayCopy();
        }

        if (! is_array($payload) || ! is_string($payload['source'] ?? null) || $payload['source'] === '') {
            return null;
        }

        $visitId = $payload['visit_id'] ?? null;

        return [
            'visit_id' => is_numeric($visitId) ? (int) $visitId : null,
            'source' => $payload['source'],
            'medium' => is_string($payload['medium'] ?? null) ? $payload['medium'] : null,
            'campaign' => is_string($payload['campaign'] ?? null) ? $payload['campaign'] : null,
        ];
    }
}
