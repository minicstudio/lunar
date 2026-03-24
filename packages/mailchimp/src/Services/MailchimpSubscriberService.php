<?php

namespace Lunar\Mailchimp\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Requests\CreateMergeFieldRequest;
use Lunar\Mailchimp\Requests\DeleteMergeFieldRequest;
use Lunar\Mailchimp\Requests\ListMergeFieldsRequest;
use Lunar\Mailchimp\Requests\SyncSubscriberRequest;
use Lunar\Mailchimp\Requests\TrackEventRequest;
use Lunar\Mailchimp\Requests\UpdateMergeFieldRequest;

class MailchimpSubscriberService
{
    public function __construct(protected MailchimpService $mailchimp) {}

    /**
     * Subscribe an email to the Mailchimp list with double opt-in.
     * Handles resubscription: if the member previously unsubscribed,
     * sets status to 'pending' to trigger a re-confirmation email.
     *
     * @throws FailedMailchimpSyncException
     */
    public function subscribe(string $email): array
    {
        $subscriberHash = md5(strtolower($email));

        $data = [
            'email_address' => $email,
            'status_if_new' => 'pending',
        ];

        $response = $this->mailchimp->getConnector()->send(
            new SyncSubscriberRequest($this->mailchimp->getListId(), $subscriberHash, $data)
        );

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to subscribe: {$response->body()}");
        }

        $member = $response->json();

        // If the member previously unsubscribed, set status to 'pending' to trigger re-confirmation
        if (in_array($member['status'] ?? '', ['unsubscribed', 'cleaned'])) {
            $response = $this->mailchimp->getConnector()->send(
                new SyncSubscriberRequest($this->mailchimp->getListId(), $subscriberHash, [
                    'email_address' => $email,
                    'status' => 'pending',
                ])
            );

            if (! $response->successful()) {
                throw new FailedMailchimpSyncException("Failed to resubscribe: {$response->body()}");
            }

            return $response->json();
        }

        return $member;
    }

    /**
     * Sync a user as subscriber to Mailchimp list.
     *
     * @throws FailedMailchimpSyncException
     */
    public function syncSubscriber(Authenticatable $user, array $mergeFields = []): array
    {
        return $this->syncSubscriberByEmail(
            $user->email,
            $user->first_name ?? '',
            $user->last_name ?? '',
            $mergeFields,
        );
    }

    /**
     * Sync a subscriber by email to Mailchimp list with merge fields.
     *
     * @throws FailedMailchimpSyncException
     */
    public function syncSubscriberByEmail(
        string $email,
        string $firstName,
        string $lastName,
        array $mergeFields = [],
    ): array {
        $subscriberHash = md5(strtolower($email));

        $cleanedMergeFields = collect($mergeFields)
            ->filter(fn ($value, $key) => ! empty($key))
            ->all();

        $data = [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'merge_fields' => array_merge(
                [
                    config('lunar-frontend.mailchimp.merge_fields.first_name') => $firstName,
                    config('lunar-frontend.mailchimp.merge_fields.last_name') => $lastName,
                ],
                $cleanedMergeFields
            ),
        ];

        $response = $this->mailchimp->getConnector()->send(
            new SyncSubscriberRequest($this->mailchimp->getListId(), $subscriberHash, $data)
        );

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to sync subscriber: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Track a custom event for a subscriber.
     *
     * @throws FailedMailchimpSyncException
     */
    public function trackEvent(string $email, string $eventName, array $properties = []): array
    {
        $subscriberHash = md5(strtolower($email));

        $data = [
            'name' => $eventName,
            'properties' => $properties,
            'occurred_at' => now()->toIso8601String(),
        ];

        $response = $this->mailchimp->getConnector()->send(
            new TrackEventRequest($this->mailchimp->getListId(), $subscriberHash, $data)
        );

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to track event '{$eventName}': {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Create or update merge fields in the Mailchimp audience.
     * Skips default Mailchimp fields (FNAME, LNAME, ADDRESS, PHONE) as they exist by default.
     *
     * @throws FailedMailchimpSyncException
     */
    public function setupMergeFields(): array
    {
        $results = [];
        $existingFields = $this->getAllMergeFields();

        $mergeFieldsConfig = [
            'preferred_category' => [
                'name' => 'Preferred Category',
                'type' => 'text',
                'required' => false,
                'public' => false,
            ],
            'preferred_subcategory' => [
                'name' => 'Preferred Subcategory',
                'type' => 'text',
                'required' => false,
                'public' => false,
            ],
        ];

        foreach ($mergeFieldsConfig as $configKey => $fieldConfig) {
            $tag = config("lunar-frontend.mailchimp.merge_fields.{$configKey}");

            if (! $tag) {
                continue;
            }

            try {
                $result = $this->createOrUpdateMergeField($tag, $fieldConfig, $existingFields);
                $results[$tag] = ['success' => true, 'data' => $result];
            } catch (FailedMailchimpSyncException $e) {
                $results[$tag] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $optionFields = config('lunar-frontend.mailchimp.option_fields', []);
        foreach ($optionFields as $tag => $fieldConfig) {
            if (empty($tag) || empty($fieldConfig['name']) || empty($fieldConfig['handle'])) {
                continue;
            }

            try {
                $result = $this->createOrUpdateMergeField($tag, [
                    'name' => $fieldConfig['name'],
                    'type' => $fieldConfig['type'] ?? 'text',
                    'required' => $fieldConfig['required'] ?? false,
                    'public' => $fieldConfig['public'] ?? true,
                ], $existingFields);
                $results[$tag] = ['success' => true, 'data' => $result];
            } catch (FailedMailchimpSyncException $e) {
                $results[$tag] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Delete merge fields from the Mailchimp audience.
     * Fetches the full list once and looks up each tag to avoid N+1 API calls.
     */
    public function deleteMergeFields(array $fieldsToDelete): array
    {
        $results = [];
        $existingFields = $this->getAllMergeFields();

        foreach ($fieldsToDelete as $tag => $name) {
            try {
                $mergeField = collect($existingFields)->firstWhere('tag', $tag);

                if (! $mergeField) {
                    $results[$tag] = [
                        'success' => true,
                        'data' => ['deleted' => false, 'reason' => 'Field does not exist']
                    ];
                    continue;
                }

                $response = $this->mailchimp->getConnector()->send(
                    new DeleteMergeFieldRequest($this->mailchimp->getListId(), $mergeField['merge_id'])
                );

                if (! $response->successful()) {
                    throw new FailedMailchimpSyncException("Failed to delete merge field {$tag}: {$response->body()}");
                }

                $results[$tag] = ['success' => true, 'data' => ['deleted' => true]];
            } catch (FailedMailchimpSyncException $e) {
                $results[$tag] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Create or update a merge field using a pre-fetched list of existing fields.
     *
     * @throws FailedMailchimpSyncException
     */
    protected function createOrUpdateMergeField(string $tag, array $config, array $existingFields): array
    {
        $existing = collect($existingFields)->firstWhere('tag', $tag);

        if ($existing) {
            return $this->updateMergeField($tag, $existing, $config);
        }

        return $this->createMergeField($tag, $config);
    }

    /**
     * @throws FailedMailchimpSyncException
     */
    protected function updateMergeField(string $tag, array $existing, array $config): array
    {
        $response = $this->mailchimp->getConnector()->send(
            new UpdateMergeFieldRequest($this->mailchimp->getListId(), $existing['merge_id'], $config)
        );

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to update merge field {$tag}: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * @throws FailedMailchimpSyncException
     */
    protected function createMergeField(string $tag, array $config): array
    {
        $response = $this->mailchimp->getConnector()->send(
            new CreateMergeFieldRequest(
                $this->mailchimp->getListId(),
                array_merge($config, ['tag' => $tag])
            )
        );

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to create merge field {$tag}: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Fetch all merge fields from the Mailchimp audience in a single API call.
     *
     * @throws FailedMailchimpSyncException
     */
    protected function getAllMergeFields(): array
    {
        $response = $this->mailchimp->getConnector()->send(
            new ListMergeFieldsRequest($this->mailchimp->getListId())
        );

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to list merge fields: {$response->body()}");
        }

        return $response->json('merge_fields', []);
    }
}
