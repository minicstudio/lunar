<?php

namespace Lunar\Klaviyo\Services;

use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Exceptions\SilentException;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Exceptions\MissingKlaviyoConfigurationException;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Requests\GetProfilesRequest;
use Lunar\Klaviyo\Requests\SubscribeProfilesRequest;
use Lunar\Klaviyo\Requests\UpsertProfileRequest;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Customer;

class KlaviyoProfileService
{
    /**
     * Suppression reasons that must not be cleared by automatic Bulk Subscribe.
     *
     * @var list<string>
     */
    private const BLOCKING_SUPPRESSION_REASONS = [
        'UNSUBSCRIBE',
        'SPAM_REPORT',
        'USER_SUPPRESSED',
    ];

    public function __construct(protected KlaviyoService $klaviyo) {}

    /**
     * Subscribe or upsert a profile based on subscription mode.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws FailedKlaviyoSyncException
     * @throws MissingKlaviyoConfigurationException
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
     * @throws MissingKlaviyoConfigurationException
     */
    protected function subscribeProfiles(
        string $email,
        MarketingSubscriptionMode $subscriptionMode,
        array $context = [],
    ): array {
        $listId = $this->resolveListIdForMode($subscriptionMode);

        if ($subscriptionMode === MarketingSubscriptionMode::CustomerRegistration
            && ! $this->mayAutomaticallySubscribe($email)) {
            KlaviyoLogger::warning('Automatic subscribe skipped — profile suppressed or previously opted out', [
                'email' => $email,
                'subscription_mode' => $subscriptionMode->value,
                'list_id' => $listId,
            ]);

            report(new SilentException(
                "Klaviyo automatic subscribe skipped for {$email}: profile is suppressed or previously opted out."
            ));

            return ['skipped' => true, 'reason' => 'suppressed_or_unsubscribed'];
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

        $customSource = $context['custom_source'] ?? $context['source'] ?? null;

        if (is_string($customSource) && $customSource !== '') {
            $jobAttributes['custom_source'] = $customSource;
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
     * @throws MissingKlaviyoConfigurationException
     */
    protected function resolveListIdForMode(MarketingSubscriptionMode $subscriptionMode): string
    {
        if ($subscriptionMode === MarketingSubscriptionMode::CustomerRegistration) {
            $listId = $this->klaviyo->getAutomaticListId();

            if (! $listId) {
                throw new MissingKlaviyoConfigurationException(
                    'Missing Klaviyo automatic_list_id. Set KLAVIYO_AUTOMATIC_LIST_ID to a single opt-in list for CustomerRegistration subscribe.'
                );
            }

            return $listId;
        }

        $listId = $this->klaviyo->getListId();

        if (! $listId) {
            throw new MissingKlaviyoConfigurationException(
                'Missing Klaviyo list_id. Set KLAVIYO_LIST_ID to a double opt-in list for ExplicitOptIn subscribe.'
            );
        }

        return $listId;
    }

    /**
     * Automatic paths must not Bulk Subscribe when the profile is already
     * unsubscribed, spam-suppressed, or user-suppressed (Bulk Subscribe removes those).
     * New / never-subscribed profiles are eligible.
     */
    protected function mayAutomaticallySubscribe(string $email): bool
    {
        $response = $this->klaviyo->getConnector()->send(new GetProfilesRequest([
            'filter' => 'equals(email,"'.$email.'")',
            'additional-fields' => [
                'profile' => 'subscriptions',
            ],
        ]));

        if (! $response->successful()) {
            KlaviyoLogger::warning('Could not verify profile eligibility for automatic subscribe — skipping', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $profiles = $response->json('data') ?? [];

        if ($profiles === []) {
            return true;
        }

        $marketing = $profiles[0]['attributes']['subscriptions']['email']['marketing'] ?? null;

        if (! is_array($marketing)) {
            return true;
        }

        $consent = $marketing['consent'] ?? null;

        if (is_string($consent) && strtoupper($consent) === 'UNSUBSCRIBED') {
            return false;
        }

        $suppressions = $marketing['suppression'] ?? [];

        if (! is_array($suppressions)) {
            return true;
        }

        foreach ($suppressions as $suppression) {
            $reason = strtoupper((string) ($suppression['reason'] ?? ''));

            if (in_array($reason, self::BLOCKING_SUPPRESSION_REASONS, true)) {
                return false;
            }
        }

        return true;
    }
}
