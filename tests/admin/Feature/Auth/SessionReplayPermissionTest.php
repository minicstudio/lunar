<?php

use Lunar\Admin\Auth\Manifest;
use Lunar\Admin\Filament\Resources\SessionReplayResource\CustomerLabel;
use Lunar\Admin\Support\DataTransferObjects\Permission as PanelPermission;
use Lunar\Tests\Admin\TestCase;

uses(TestCase::class);

test('session replays are a base staff permission', function () {
    expect(app(Manifest::class)->getBasePermissions())->toContain('settings:view-session-replays');
});

test('the session replay permission label resolves in the admin locales', function (string $locale, string $label) {
    app()->setLocale($locale);

    $permission = PanelPermission::make('settings:view-session-replays', true);

    expect($permission->transLabel())->toBe($label)
        ->and($permission->transDescription())->not->toBe('settings:view-session-replays');
})->with([
    'en' => ['en', 'View session replays'],
    'hu' => ['hu', 'Munkamenet-felvételek megtekintése'],
    'ro' => ['ro', 'Vizualizare înregistrări de sesiune'],
]);

test('the customer column names a linked shopper, an anonymous signed-in shopper, and a guest', function (string $locale, string $anonymous, string $guest) {
    app()->setLocale($locale);

    expect(CustomerLabel::for('ada@example.com', '15', null))->toBe('ada@example.com')
        ->and(CustomerLabel::for(null, null, ['customer' => 'anonymous']))->toBe($anonymous)
        ->and(CustomerLabel::for(null, null, null))->toBe($guest)
        ->and(CustomerLabel::for(null, '99', null))->toBe('#99');
})->with([
    'en' => ['en', 'Anonymous', 'Guest'],
    'hu' => ['hu', 'Névtelen', 'Vendég'],
    'ro' => ['ro', 'Anonim', 'Vizitator'],
]);
