<?php

namespace Lunar\Addons\Shipping\Providers\Sameday;

use Carbon\Carbon;
use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\TokenAwareShippingApiClient;
use Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException;
use Lunar\Addons\Shipping\Exceptions\FailedToDownloadAWBPDFException;
use Lunar\Addons\Shipping\Exceptions\FailedToGetLocationsException;
use Lunar\Addons\Shipping\Exceptions\FailedToGetLockersException;
use Lunar\Addons\Shipping\Exceptions\InvalidShippingResponseException;
use Lunar\Addons\Shipping\Models\ShippingProviderCredentials;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\AuthenticateRequest;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\DownloadAWBPDF;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\GenerateAWBRequest;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\GetCities;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\GetCounties;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\GetLockerLocations;
use Saloon\Http\Connector;
use Saloon\Http\Response;

class SamedayApiClient extends Connector implements TokenAwareShippingApiClient
{
    protected string $provider = 'sameday';

    /**
     * The base URL for the API endpoint.
     */
    public function resolveBaseUrl(): string
    {
        return config('lunar.shipping.sameday.base_url');
    }

    /**
     * Get valid token for authenticated requests.
     */
    public function getToken(): string
    {
        $token = ShippingProviderCredentials::validTokenFor($this->provider);

        return $token ?? $this->refreshToken();
    }

    /**
     * Authenticate with Sameday and store a new token.
     *
     * @throws InvalidShippingResponseException
     */
    public function refreshToken(): string
    {
        $response = $this->send(new AuthenticateRequest);
        $data = $response->json();

        if (! isset($data['token'], $data['expire_at'])) {
            throw new InvalidShippingResponseException('Invalid response from Sameday authentication.');
        }

        $token = $data['token'];
        $expiresAt = Carbon::parse($data['expire_at']);

        ShippingProviderCredentials::updateOrCreate(
            ['provider' => $this->provider],
            ['token' => $token, 'expires_at' => $expiresAt]
        );

        return $token;
    }

    /**
     * Send request with automatic token refresh on 401 errors.
     */
    protected function sendWithRetry(callable $requestCallback): Response
    {
        $response = $requestCallback();

        // If we get a 401, log it and try once more with a fresh token
        if ($response->status() === 401) {
            report(new \RuntimeException('Sameday API: Invalid credentials (401), refreshing token and retrying'));

            // Invalidate current token and let the request fetch a fresh one
            app(SamedayTokenProvider::class)->invalidateToken();
            $response = $requestCallback();

            if ($response->status() === 401) {
                report(new \RuntimeException('Sameday API: Still getting 401 after token refresh'));
            }
        }

        return $response;
    }

    /**
     * Generate an AWB using the Sameday API.
     */
    public function generateAWB(?AWBRequestBodyInterface $payload): array
    {
        $response = $this->sendWithRetry(function () use ($payload) {
            $request = new GenerateAWBRequest($payload->toArray());

            return $this->send($request);
        });

        if (! $response->successful()) {
            throw new FailedAWBGenerationException(__('lunar::exceptions.order.awb_generation_failed') . $response->body());
        }

        return $response->json();
    }

    /**
     * Download the AWB PDF.
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        $response = $this->sendWithRetry(function () use ($awbNumber) {
            $request = new DownloadAWBPDF($awbNumber);

            return $this->send($request);
        });

        if (! $response->successful()) {
            throw new FailedToDownloadAWBPDFException(__('lunar::exceptions.order.failed_to_download_awb_pdf'), $response->body());
        }

        return $response;
    }

    /**
     * Get counties for locker locations.
     */
    public function getCounties()
    {
        $response = $this->sendWithRetry(function () {
            $request = new GetCounties;

            return $this->send($request);
        });

        if (! $response->successful()) {
            throw new FailedToGetLocationsException('Failed to retrieve counties: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get cities for Locker locations.
     */
    public function getCities(?int $countyId = null, int $page = 1)
    {
        $response = $this->sendWithRetry(function () use ($countyId, $page) {
            $queryParams = [
                'page' => $page,
                'countPerPage' => 500,
            ];
            if ($countyId) {
                $queryParams['county'] = $countyId;
            }

            $request = new GetCities($queryParams);

            return $this->send($request);
        });

        if (! $response->successful()) {
            throw new FailedToGetLocationsException('Failed to retrieve cities: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get Locker locations.
     */
    public function getLockerLocationsPaginated(int $page = 1): array
    {
        $response = $this->sendWithRetry(function () use ($page) {
            $request = new GetLockerLocations([
                'page' => $page,
                'countPerPage' => 500,
            ]);

            return $this->send($request);
        });

        if (! $response->successful()) {
            throw new FailedToGetLockersException('Failed to retrieve locker locations: '.$response->body());
        }

        return $response->json();
    }
}
