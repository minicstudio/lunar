<?php

namespace Lunar\Admin\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Minic\LaravelAiAssistant\Models\AiAssistantSettings;
use Minic\LaravelAiAssistant\Traits\HasLivewireChatBehavior;

/**
 * Filament 3 / Livewire 3 AI Assistant full-page chat for the Lunar admin panel.
 *
 * Behavior (and blade view) is provided by the package's HasLivewireChatBehavior trait and
 * its Filament 3-compatible chat blade. Access requires the `ai:chat` Lunar staff permission
 * and the full-page chat to be enabled in the AI Assistant settings.
 */
class AiAssistantChatPage extends Page
{
    use HasLivewireChatBehavior;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'AI Assistant';

    protected static ?string $navigationLabel = 'Chat';

    protected static ?string $title = 'AI Assistant Chat';

    protected static string $view = 'laravel-ai-assistant::filament.pages.ai-assistant-chat';

    /**
     * Grants access to staff holding the `ai:chat` permission (admins always pass via Lunar's
     * Gate::after resolution), provided the full-page chat is enabled in the AI Assistant settings.
     *
     * @return bool True if the current user can access the chat page, false otherwise.
     */
    public static function canAccess(): bool
    {
        try {
            $fullpageEnabled = AiAssistantSettings::instance()->fullpage_chat_enabled;
        } catch (\Throwable) {
            // Settings table not available yet (e.g. before migrations) — deny access.
            return false;
        }

        if (! $fullpageEnabled) {
            return false;
        }

        $user = filament()->auth()->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('ai:chat');
    }

    /**
     * Returns the relative route name for this page within the panel.
     *
     * @return string The route name 'ai-assistant-chat'.
     */
    public static function getRelativeRouteName(): string
    {
        return 'ai-assistant-chat';
    }

    /**
     * Returns the URL slug for this page.
     *
     * @return string The slug (same as relative route name).
     */
    public static function getSlug(): string
    {
        return static::getRelativeRouteName();
    }
}
