<?php

namespace Lunar\Admin\Filament\Resources\SessionReplayResource;

class CustomerLabel
{
    /**
     * The customer column for one recording.
     *
     * Email when the recording is linked to a storefront user. Anonymous when
     * a signed-in shopper was stored without a user (`customer` = `anonymous`).
     * Guest when nobody was signed in.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function for(?string $email, mixed $userId, ?array $properties): string
    {
        if (is_string($email) && $email !== '') {
            return $email;
        }

        if (filled($userId)) {
            return '#'.$userId;
        }

        if (($properties['customer'] ?? null) === 'anonymous') {
            return __('lunarpanel::session-replay.table.anonymous');
        }

        return __('lunarpanel::session-replay.table.guest');
    }
}
