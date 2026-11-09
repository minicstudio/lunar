<?php

namespace Lunar\Analytics\Traffic\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a storefront visit after the response is built.
 *
 * Attach it to storefront page routes inside `StartSession`, so the session
 * is read and written before it is saved. Only GET requests answered with a
 * 200 HTML response count; bots, empty user agents and prefetches do not.
 */
class RecordTrafficVisit
{
    public function __construct(protected TrafficVisitRecorder $recorder) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isRecordable($request, $response)) {
            $this->recorder->record($request);
        }

        return $response;
    }

    /**
     * Whether the request is a real storefront page view that may start a visit.
     */
    protected function isRecordable(Request $request, Response $response): bool
    {
        return $request->getMethod() === Request::METHOD_GET
            && $response->getStatusCode() === Response::HTTP_OK
            && $this->isHtml($response)
            && ! $this->isBot($request)
            && ! $this->isPrefetch($request)
            && $this->recorder->enabled()
            && $this->recorder->shouldTrack();
    }

    /**
     * Whether the response is an HTML page.
     */
    protected function isHtml(Response $response): bool
    {
        return str_starts_with(strtolower((string) $response->headers->get('Content-Type', '')), 'text/html');
    }

    /**
     * Whether the user agent is empty or matches `lunar.analytics.traffic.bot_user_agent_pattern`.
     */
    protected function isBot(Request $request): bool
    {
        $userAgent = trim((string) $request->userAgent());

        if ($userAgent === '') {
            return true;
        }

        $pattern = config('lunar.analytics.traffic.bot_user_agent_pattern');

        return is_string($pattern) && $pattern !== '' && preg_match($pattern, $userAgent) === 1;
    }

    /**
     * Whether the browser sent the request as a prefetch (`Sec-Purpose` or `Purpose`).
     */
    protected function isPrefetch(Request $request): bool
    {
        $purpose = $request->headers->get('Sec-Purpose', '').' '.$request->headers->get('Purpose', '');

        return str_contains(strtolower($purpose), 'prefetch');
    }
}
