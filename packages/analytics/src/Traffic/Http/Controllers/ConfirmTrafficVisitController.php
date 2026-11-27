<?php

namespace Lunar\Analytics\Traffic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Lunar\Analytics\Traffic\Services\TrafficVisitRecorder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ConfirmTrafficVisitController
{
    public function __invoke(Request $request, SessionManager $sessions, TrafficVisitRecorder $recorder): Response
    {
        try {
            $session = $sessions->driver();
            $session->setId($request->cookies->get($session->getName()));
            $session->start();
            $request->setLaravelSession($session);

            $recorder->confirm();
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->noContent();
    }
}
