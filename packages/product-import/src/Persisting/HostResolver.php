<?php

namespace Lunar\ProductImport\Persisting;

/**
 * Resolves a host name to its IPv4 and IPv6 addresses, so image hosts can be
 * checked before anything is fetched from them.
 */
class HostResolver
{
    /**
     * The host's addresses; empty when it does not resolve.
     *
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        $addresses = @gethostbynamel($host) ?: [];
        $records = @dns_get_record($host, DNS_AAAA) ?: [];

        foreach ($records as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
