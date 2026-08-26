<?php

namespace Lunar\Klaviyo\Services;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Requests\SubscribeProfilesRequest;
use Lunar\Klaviyo\Requests\UpsertProfileRequest;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Customer;

class KlaviyoProfileService
{
    public function __construct(protected KlaviyoService $klaviyo) {}

    /**
     * Subscribe or upsert a profile based on subscription mode.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function subscribe(
        string $email,
        MarketingSubscriptionMode $subscriptionMode,
        array $context = [],
        ?Customer $customer = null,
    ): array {
        $attributes = $this->buildProfileAttributes($email, $customer, $context);

        $this->upsertProfile($email, $attributes);

        return $this->subscribeProfiles($email, $subscriptionMode, $context);
    }

    /**
     * Create or update a Klaviyo profile identified by email.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function upsertProfile(string $email, array $attributes = []): array
    {
        $profileAttributes = array_merge(['email' => $email], $attributes);

        $payload = [
            'data' => [
                'type' => 'profile',
                'attributes' => $profileAttributes,
            ],
        ];

        $response = $this->klaviyo->getConnector()->send(new UpsertProfileRequest($payload));

        if (! $response->successful()) {
            KlaviyoLogger::error('Upsert profile API failed', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new FailedKlaviyoSyncException("Failed to upsert Klaviyo profile: {$response->body()}");
        }

        KlaviyoLogger::debug('Upsert profile API succeeded', [
            'email' => $email,
            'status' => $response->status(),
            'attribute_keys' => array_keys($profileAttributes),
        ]);

        return $response->json() ?? [];
    }

    /**
     * Track a behavioral event with a stable unique_id.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    public function trackEvent(
        string $email,
        string $eventName,
        array $properties = [],
        string $eventId = '',
        ?float $value = null,
        ?string $valueCurrency = null,
    ): array {
        $attributes = [
            'properties' => $properties,
            'metric' => [
                'data' => [
                    'type' => 'metric',
                    'attributes' => [
                        'name' => $eventName,
                    ],
                ],
            ],
            'profile' => [
                'data' => [
                    'type' => 'profile',
                    'attributes' => [
                        'email' => $email,
                    ],
                ],
            ],
        ];

        if ($eventId !== '') {
            $attributes['unique_id'] = $eventId;
        }

        if ($value !== null) {
            $attributes['value'] = $value;
        }

        if ($valueCurrency !== null) {
            $attributes['value_currency'] = $valueCurrency;
        }

        $payload = [
            'data' => [
                'type' => 'event',
                'attributes' => $attributes,
            ],
        ];

        $response = $this->klaviyo->getConnector()->send(new CreateEventRequest($payload));

        if (! $response->successful()) {
            KlaviyoLogger::error('Track event API failed', [
                'email' => $email,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new FailedKlaviyoSyncException(
                "Failed to track Klaviyo event '{$eventName}': {$response->body()}"
            );
        }

        KlaviyoLogger::debug('Track event API succeeded', [
            'email' => $email,
            'event_name' => $eventName,
            'event_id' => $eventId,
            'status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    /**
     * Map neutral properties onto Klaviyo profile attribute keys.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function mapProfileAttributes(array $properties): array
    {
        $map = config('lunar.klaviyo.profile_attributes', []);
        $attributes = [];
        $customProperties = [];

        foreach ($properties as $key => $value) {
            $mappedKey = $map[$key] ?? null;

            if ($mappedKey === null) {
                continue;
            }

            if (in_array($mappedKey, ['first_name', 'last_name', 'phone_number', 'organization'], true)) {
                $attributes[$mappedKey] = $value;

                continue;
            }

            $customProperties[$mappedKey] = $value;
        }

        if ($customProperties !== []) {
            $attributes['properties'] = $customProperties;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildProfileAttributes(string $email, ?Customer $customer, array $context): array
    {
        $attributes = [];
        $user = null;

        if ($customer) {
            $user = $customer->users()?->first();

            if ($user) {
                $attributes['first_name'] = $user->first_name ?? '';
                $attributes['last_name'] = $user->last_name ?? '';
            }
        }

        $locale = $this->resolveLocale($context, $user);

        if ($locale !== null) {
            $attributes = array_merge($attributes, $this->mapProfileAttributes(['language' => $locale]));
        }

        return $attributes;
    }

    /**
     * Prefer host-provided context, then linked user locale, then current app locale.
     *
     * @param  array<string, mixed>  $context
     */
    protected function resolveLocale(array $context, mixed $user = null): ?string
    {
        $locale = $context['locale'] ?? null;

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        $userLocale = is_object($user) ? ($user->locale ?? null) : null;

        if (is_string($userLocale) && $userLocale !== '') {
            return $userLocale;
        }

        $appLocale = app()->getLocale();

        return is_string($appLocale) && $appLocale !== '' ? $appLocale : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     */
    protected function subscribeProfiles(
        string $email,
        MarketingSubscriptionMode $subscriptionMode,
        array $context = [],
    ): array {
        $listId = $this->klaviyo->getListId();

        if (! $listId) {
            throw new FailedKlaviyoSyncException(
                'Missing Klaviyo list_id. Set KLAVIYO_LIST_ID to subscribe profiles to a list.'
            );
        }

        $profileAttributes = [
            'email' => $email,
            'subscriptions' => [
                'email' => [
                    'marketing' => [
                        'consent' => 'SUBSCRIBED',
                    ],
                ],
            ],
        ];

        $jobAttributes = [
            'profiles' => [
                'data' => [
                    [
                        'type' => 'profile',
                        'attributes' => $profileAttributes,
                    ],
                ],
            ],
        ];

        // ExplicitOptIn: respect list double opt-in (confirmation email). Never historical_import.
        // CustomerRegistration: immediate consented subscribe (Mailchimp status_if_new=subscribed parity).
        if ($subscriptionMode === MarketingSubscriptionMode::CustomerRegistration) {
            $consentedAt = $this->resolvePastConsentedAt($context);

            $jobAttributes['historical_import'] = true;
            $jobAttributes['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing']['consented_at'] = $consentedAt;
        }

        $payload = [
            'data' => [
                'type' => 'profile-subscription-bulk-create-job',
                'attributes' => $jobAttributes,
                'relationships' => [
                    'list' => [
                        'data' => [
                            'type' => 'list',
                            'id' => $listId,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->klaviyo->getConnector()->send(new SubscribeProfilesRequest($payload));

        if (! $response->successful()) {
            KlaviyoLogger::error('Subscribe profiles API failed', [
                'email' => $email,
                'subscription_mode' => $subscriptionMode->value,
                'list_id' => $listId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new FailedKlaviyoSyncException("Failed to subscribe Klaviyo profile: {$response->body()}");
        }

        KlaviyoLogger::debug('Subscribe profiles API succeeded', [
            'email' => $email,
            'subscription_mode' => $subscriptionMode->value,
            'list_id' => $listId,
            'status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    /**
     * Klaviyo requires historical_import consented_at to be clearly in the past.
     * Near-now stamps (e.g. subSecond) are rejected due to clock skew — keep a buffer.
     */
    private const HISTORICAL_CONSENTED_AT_MIN_AGE_MINUTES = 5;

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolvePastConsentedAt(array $context): string
    {
        $raw = $context['consented_at'] ?? null;

        if ($raw instanceof DateTimeInterface) {
            $timestamp = Carbon::instance($raw);
        } elseif (is_string($raw) && $raw !== '') {
            $timestamp = Carbon::parse($raw);
        } else {
            $timestamp = now()->subMinutes(self::HISTORICAL_CONSENTED_AT_MIN_AGE_MINUTES);
        }

        $latestAllowed = now()->subMinutes(self::HISTORICAL_CONSENTED_AT_MIN_AGE_MINUTES);

        if ($timestamp->greaterThan($latestAllowed)) {
            $timestamp = $latestAllowed;
        }

        return $timestamp->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
