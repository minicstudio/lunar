<?php

namespace Lunar\Admin\Filament;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Lunar\Admin\Filament\Pages\AiAssistantChatPage;
use Lunar\Admin\Filament\Pages\AiAssistantSettingsPage;
use Minic\LaravelAiAssistant\Filament\AiAssistantPlugin as BaseAiAssistantPlugin;
use Minic\LaravelAiAssistant\Models\AiAssistantSettings;

/**
 * Lunar-specific AI Assistant plugin.
 *
 * Extends the package plugin to:
 *  - register Lunar's Filament 3 / Livewire 3 gated pages by default, and
 *  - gate the floating chat bubble behind the `ai:chat` Lunar staff permission
 *    (the package's own hook only checks authentication + the bubble toggle).
 */
class AiAssistantPlugin extends BaseAiAssistantPlugin
{
    /**
     * @var class-string
     */
    protected string $settingsPage = AiAssistantSettingsPage::class;

    /**
     * @var class-string
     */
    protected string $chatPage = AiAssistantChatPage::class;

    /**
     * Registers the gated pages and a permission-aware BODY_START hook for the chat bubble.
     *
     * @param  Panel  $panel  The Filament panel to register with.
     */
    public function register(Panel $panel): void
    {
        $pages = [$this->settingsPage];

        try {
            $fullpageEnabled = AiAssistantSettings::instance()->fullpage_chat_enabled;
        } catch (\Throwable) {
            // DB not available yet (register phase, console, before migrations) — keep the default.
            $fullpageEnabled = true;
        }

        if ($fullpageEnabled) {
            $pages[] = $this->chatPage;
        }

        $panel->pages($pages);

        $panelId = $panel->getId();

        $panel->renderHook(
            PanelsRenderHook::BODY_START,
            function () use ($panelId): string {
                try {
                    $user = Filament::auth()->user();

                    $show = Filament::getCurrentPanel()?->getId() === $panelId
                        && $user !== null
                        && Gate::forUser($user)->allows('ai:chat')
                        && AiAssistantSettings::instance()->bubble_chat_enabled;

                    return $show
                        ? Blade::render("@livewire('ai-assistant-chat-window', [], key('ai-assistant-chat'))")
                        : '';
                } catch (\Throwable) {
                    return '';
                }
            },
        );
    }
}
