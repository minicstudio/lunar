<?php

use Filament\Facades\Filament;
use Lunar\Admin\Database\State\EnsureAiAssistantPermissions;
use Lunar\Admin\Filament\Pages\AiAssistantChatPage;
use Lunar\Admin\Filament\Pages\AiAssistantSettingsPage;
use Minic\LaravelAiAssistant\Models\AiAssistantSettings;
use Spatie\Permission\Models\Permission;

uses(\Lunar\Tests\Admin\Feature\Filament\AiAssistant\TestCase::class);

beforeEach(function () {
    (new EnsureAiAssistantPermissions)->run();

    Filament::setCurrentPanel(Filament::getPanel('lunar'));
});

it('creates the ai permissions via the state', function () {
    expect(Permission::where('guard_name', 'staff')->whereIn('name', ['ai:manage-settings', 'ai:chat'])->count())
        ->toBe(2);
});

it('lets an admin access both pages', function () {
    $this->actingAs($this->makeAiStaff(admin: true), 'staff');

    expect(AiAssistantSettingsPage::canAccess())->toBeTrue()
        ->and(AiAssistantChatPage::canAccess())->toBeTrue();
});

it('gates the settings page behind ai:manage-settings', function () {
    $this->actingAs($this->makeAiStaff(permissions: ['ai:manage-settings']), 'staff');
    expect(AiAssistantSettingsPage::canAccess())->toBeTrue();

    $this->actingAs($this->makeAiStaff(permissions: ['ai:chat']), 'staff');
    expect(AiAssistantSettingsPage::canAccess())->toBeFalse();
});

it('gates the chat page behind ai:chat', function () {
    $this->actingAs($this->makeAiStaff(permissions: ['ai:chat']), 'staff');
    expect(AiAssistantChatPage::canAccess())->toBeTrue();

    $this->actingAs($this->makeAiStaff(permissions: ['ai:manage-settings']), 'staff');
    expect(AiAssistantChatPage::canAccess())->toBeFalse();
});

it('hides the chat page when full-page chat is disabled even with ai:chat', function () {
    AiAssistantSettings::instance()->update(['fullpage_chat_enabled' => false]);

    $this->actingAs($this->makeAiStaff(permissions: ['ai:chat']), 'staff');

    expect(AiAssistantChatPage::canAccess())->toBeFalse();
});

it('denies a staff member with no ai permissions', function () {
    $this->actingAs($this->makeAiStaff(), 'staff');

    expect(AiAssistantSettingsPage::canAccess())->toBeFalse()
        ->and(AiAssistantChatPage::canAccess())->toBeFalse();
});
