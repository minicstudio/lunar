<?php

namespace Lunar\Addons\Shipping\Connectors;

use Saloon\Http\Connector;

class NominatimConnector extends Connector
{
    /**
     * The base URL for the Nominatim endpoint.
     */
    public function resolveBaseUrl(): string
    {
        return 'https://nominatim.openstreetmap.org';
    }

    /**
     * The default headers to send with each request.
     */
    public function defaultHeaders(): array
    {
        $contact = collect(config('lunar.shipping.contact_recipients'))->first();

        return [
            'User-Agent' => config('app.name').' ('.$contact.')',
            'Accept-Language' => 'en',
        ];
    }
}
