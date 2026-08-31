<?php

namespace Lunar\Mailchimp\Listeners;

use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Mailchimp\Jobs\SyncSubscriberToMailchimp;

class SyncCustomerOnMarketingProfileUpdated
{
    public function handle(CustomerMarketingProfileUpdated $event): void
    {
        if (! config('lunar.mailchimp.enabled', false)) {
            return;
        }

        $properties = $event->properties;
        $languageOnly = array_keys($properties) === ['language'];
        $connection = config('lunar.mailchimp.queue_connection', 'deferred');

        if ($languageOnly) {
            SyncSubscriberToMailchimp::dispatch(
                user: $event->customer,
                languageOnly: true,
            )->onConnection($connection);

            return;
        }

        $mergeFields = $this->mapPropertiesToMergeFields($properties);

        SyncSubscriberToMailchimp::dispatch(
            user: $event->customer,
            mergeFields: $mergeFields,
        )->onConnection($connection);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function mapPropertiesToMergeFields(array $properties): array
    {
        $config = config('lunar.mailchimp.merge_fields', []);
        $mapped = [];

        foreach ($properties as $key => $value) {
            $tag = $config[$key] ?? null;

            if ($tag) {
                $mapped[$tag] = $value;
            }
        }

        return $mapped;
    }
}
