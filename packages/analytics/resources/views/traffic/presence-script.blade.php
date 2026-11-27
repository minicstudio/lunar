{{--
    Presence script: confirms the session's traffic visit once the page runs
    in a browser, through the package's presence route. Rendered only while
    the session has no visit yet. A prerendered page waits until the visitor
    opens it.

    @include('lunar.analytics::traffic.presence-script', ['nonce' => $cspNonce])
--}}
@if (app(\Lunar\Analytics\Traffic\Services\TrafficVisitRecorder::class)->needsConfirmation())
    <script @if (! empty($nonce)) nonce="{{ $nonce }}" @endif>
        (() => {
            const confirmVisit = () => navigator.sendBeacon(@js(route('lunar.analytics.traffic.presence', absolute: false)));

            document.prerendering
                ? document.addEventListener('prerenderingchange', confirmVisit, { once: true })
                : confirmVisit();
        })();
    </script>
@endif
