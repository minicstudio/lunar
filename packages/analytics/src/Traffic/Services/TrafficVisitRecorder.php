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
use Throwable;

/**
 * Records storefront visits and keeps the current visit in the Laravel session.
 *
 * A visit starts on the first page view of a session, or on a later page view
 * whose campaign parameters give a different source, medium or campaign. A
 * visitor whose active cart already has a traffic source starts no visit; the
 * session continues that cart's visit instead.
 *
 * A browser prefetch records nothing. A visit it would start in a session
 * without one is kept pending in the session and recorded once the page is
 * opened: at the session's next request that is not a prefetch, when that is
 * a page view without a source of its own (`record()`) or when it asks for
 * the current visit, as the cart observer does (`current()`). Writing a
 * current visit drops the pending one.
 *
 * A visit counts once a browser confirms it. The first page view of a
 * session stores its visit unconfirmed. The visit is confirmed when the
 * session comes back: at its next storefront page view (`record()`), when a
 * product is added to a cart (`current()`), or when the page's presence
 * script reaches the presence route (`confirm()`). Crawlers that keep no
 * cookies and run no scripts never confirm theirs. A visit started by a
 * session that came back is stored confirmed.
 *
 * @phpstan-type TrafficVisitPayload array{visit_id: ?int, source: string, medium: ?string, campaign: ?string}
 * @phpstan-type TrafficPendingVisitPayload array{source: string, medium: ?string, campaign: ?string, referrer_host: ?string, landing_path: string}
 */
class TrafficVisitRecorder
{
    /**
     * Session key holding the current visit.
     */
    public const SESSION_KEY = 'analytics_traffic_visit';

    /**
     * Session key holding the visit a prefetch would have started, until the page is opened.
     */
    public const PENDING_SESSION_KEY = 'analytics_traffic_pending_visit';

    /**
     * Session key holding the id of the current visit once it is confirmed.
     */
    public const CONFIRMED_SESSION_KEY = 'analytics_traffic_confirmed_visit';

    public function __construct(protected ClassifiesTrafficSource $classifier) {}

    /**
     * Whether traffic recording is switched on (`lunar.analytics.traffic.enabled`, off by default).
     */
    public function enabled(): bool
    {
        return (bool) config('lunar.analytics.traffic.enabled', false);
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
     * Whether the browser sent the request as a prefetch or prerender (`Sec-Purpose` or `Purpose`).
     */
    public function isPrefetch(Request $request): bool
    {
        $purpose = $request->headers->get('Sec-Purpose', '').' '.$request->headers->get('Purpose', '');

        return str_contains(strtolower($purpose), 'prefetch');
    }

    /**
     * Record a visit for the request when it starts one.
     *
     * Does nothing when the session already has a visit and the request has
     * no campaign parameters, or they give the current visit's source,
     * medium and campaign. When the visitor's cart already has a traffic
     * source, nothing is recorded and a session without a visit continues
     * the cart's visit.
     *
     * A prefetch stores the visit it would start as the session's pending
     * visit instead. A later request without a source of its own (the
     * shop's own referrer and no campaign parameters) records the pending
     * visit, with its landing path and referrer host; a request with a
     * source of its own records that source and drops the pending visit.
     *
     * A request that is not a prefetch, in a session holding a visit or a
     * pending visit, shows the session came back: it confirms the current
     * visit, and a visit it starts is stored confirmed. The first page view
     * of a session stores its visit unconfirmed.
     */
    public function record(Request $request): void
    {
        if (! $this->enabled() || ! $this->shouldTrack() || ! $request->hasSession()) {
            return;
        }

        $session = $request->session();
        $current = $this->normalisePayload($session->get(self::SESSION_KEY));
        $isPrefetch = $this->isPrefetch($request);

        if ($isPrefetch && $current !== null) {
            return;
        }

        $pending = $current === null && ! $isPrefetch ? $this->pendingVisit($session) : null;
        $cameBack = ! $isPrefetch && ($current !== null || $pending !== null);

        if (! $isPrefetch && $current !== null) {
            $this->confirmVisit($session, $current);
        }

        $source = $this->classifier->classify($request);
        $landingPath = mb_substr('/'.ltrim($request->path(), '/'), 0, 255);

        if (! $source) {
            if ($pending === null) {
                return;
            }

            $source = $this->pendingSource($pending);
            $landingPath = $pending['landing_path'];
        } elseif ($current !== null && (! $this->classifier->hasCampaignParameters($request) || $this->isSameSource($current, $source))) {
            return;
        }

        $this->start($request, $session, $current, $source, $landingPath, $isPrefetch, confirmed: $cameBack);
    }

    /**
     * The session's current visit.
     *
     * Null when recording is disabled, the request may not be tracked, no
     * session is available or the session has no visit. On a request that
     * is not a prefetch, the session came back: a session without a visit
     * first records its pending visit confirmed, applying the cart rule of
     * `record()`, and a session with a visit confirms it.
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

        $session = $request->session();
        $current = $this->normalisePayload($session->get(self::SESSION_KEY));

        if ($this->isPrefetch($request)) {
            return $current;
        }

        if ($current !== null) {
            $this->confirmVisit($session, $current);

            return $current;
        }

        if ($pending = $this->pendingVisit($session)) {
            $this->start($request, $session, null, $this->pendingSource($pending), $pending['landing_path'], isPrefetch: false, confirmed: true);

            $current = $this->normalisePayload($session->get(self::SESSION_KEY));
        }

        return $current;
    }

    /**
     * Confirm the session's current visit, as the presence route does when
     * the page's script runs in a browser.
     *
     * Does nothing when recording is disabled, the request may not be
     * tracked or is a prefetch, or the session has no current visit. A
     * prefetch's pending visit is left to the session's next request.
     */
    public function confirm(): void
    {
        if (! $this->enabled() || ! $this->shouldTrack()) {
            return;
        }

        $request = request();

        if (! $request->hasSession() || $this->isPrefetch($request)) {
            return;
        }

        $session = $request->session();

        if ($current = $this->normalisePayload($session->get(self::SESSION_KEY))) {
            $this->confirmVisit($session, $current);
        }
    }

    /**
     * Whether the page should carry the presence script: recording is on,
     * the request may be tracked, and the session has no visit yet, so this
     * page view may start an unconfirmed one. A later page view confirms
     * the visit by itself.
     */
    public function needsConfirmation(): bool
    {
        if (! $this->enabled() || ! $this->shouldTrack()) {
            return false;
        }

        $request = request();

        return $request->hasSession()
            && $this->normalisePayload($request->session()->get(self::SESSION_KEY)) === null;
    }

    /**
     * Start a visit with the given source, unless the visitor's cart has a traffic source.
     *
     * With a sourced cart, a session without a visit continues the cart's
     * visit. Otherwise a prefetch stores the pending visit, and any other
     * request inserts the visit and makes it current, confirmed or not.
     *
     * @param  TrafficVisitPayload|null  $current  The session's current visit.
     * @param  bool  $confirmed  Whether the session came back, so a browser holds it.
     */
    protected function start(Request $request, Session $session, ?array $current, TrafficSource $source, string $landingPath, bool $isPrefetch, bool $confirmed): void
    {
        if ($cartSource = $this->visitorCartSource($request, $session)) {
            if ($current === null) {
                $this->putCurrent($session, $cartSource);
            }

            return;
        }

        if ($isPrefetch) {
            $session->put(self::PENDING_SESSION_KEY, [
                'source' => $source->source,
                'medium' => $source->medium,
                'campaign' => $source->campaign,
                'referrer_host' => $source->referrerHost,
                'landing_path' => $landingPath,
            ]);

            return;
        }

        $visit = TrafficVisit::query()->create([
            'source' => $source->source,
            'medium' => $source->medium,
            'campaign' => $source->campaign,
            'referrer_host' => $source->referrerHost,
            'landing_path' => $landingPath,
            'confirmed_at' => $confirmed ? now() : null,
        ]);

        if ($confirmed) {
            $session->put(self::CONFIRMED_SESSION_KEY, (int) $visit->getKey());
        }

        $this->putCurrent($session, [
            'visit_id' => (int) $visit->getKey(),
            'source' => $visit->source,
            'medium' => $visit->medium,
            'campaign' => $visit->campaign,
        ]);
    }

    /**
     * Confirm the session's current visit, once per visit and session.
     *
     * The update only touches an unconfirmed visit, so a repeated or
     * concurrent confirmation changes nothing. A failed update is reported
     * and retried at the session's next request, so it never interrupts the
     * page or the add to cart that confirms.
     *
     * @param  TrafficVisitPayload  $current
     */
    protected function confirmVisit(Session $session, array $current): void
    {
        $visitId = $current['visit_id'];

        if ($visitId === null || $session->get(self::CONFIRMED_SESSION_KEY) === $visitId) {
            return;
        }

        try {
            TrafficVisit::query()
                ->whereKey($visitId)
                ->whereNull('confirmed_at')
                ->update(['confirmed_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $session->put(self::CONFIRMED_SESSION_KEY, $visitId);
    }

    /**
     * Make a visit the session's current visit and drop the pending one.
     *
     * @param  TrafficVisitPayload  $payload
     */
    protected function putCurrent(Session $session, array $payload): void
    {
        $session->put(self::SESSION_KEY, $payload);
        $session->forget(self::PENDING_SESSION_KEY);
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
     * The session's pending visit, if it holds a valid one.
     *
     * @return TrafficPendingVisitPayload|null
     */
    protected function pendingVisit(Session $session): ?array
    {
        $pending = $session->get(self::PENDING_SESSION_KEY);

        if (! is_array($pending)
            || ! is_string($pending['source'] ?? null) || $pending['source'] === ''
            || ! is_string($pending['landing_path'] ?? null) || $pending['landing_path'] === '') {
            return null;
        }

        return [
            'source' => $pending['source'],
            'medium' => is_string($pending['medium'] ?? null) ? $pending['medium'] : null,
            'campaign' => is_string($pending['campaign'] ?? null) ? $pending['campaign'] : null,
            'referrer_host' => is_string($pending['referrer_host'] ?? null) ? $pending['referrer_host'] : null,
            'landing_path' => $pending['landing_path'],
        ];
    }

    /**
     * The classified source a pending visit was stored with.
     *
     * @param  TrafficPendingVisitPayload  $pending
     */
    protected function pendingSource(array $pending): TrafficSource
    {
        return new TrafficSource(
            source: $pending['source'],
            medium: $pending['medium'],
            campaign: $pending['campaign'],
            referrerHost: $pending['referrer_host'],
        );
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
