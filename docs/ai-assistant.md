# AI Assistant

The Lunar admin panel bundles the [`minic/laravel-ai-assistant`](https://github.com/minicstudio/laravel-ai-assistant)
package and registers it with Filament 3 / Livewire 3 compatible, permission-gated pages. It lives in
the `admin` package (`packages/admin`) and is wired into the default Lunar panel automatically — no
per-project registration is required. It adds three things to the admin panel:

- **AI Assistant Settings** page — choose provider/model, persona, chat appearance, and tools.
- **AI Assistant Chat** page — full-page chat.
- **Floating chat bubble** — bottom-right of every admin screen.

## Where it lives

| Concern | Location |
| --- | --- |
| Filament pages | `packages/admin/src/Filament/Pages/AiAssistant{Chat,Settings}Page.php` |
| Panel plugin (registers pages + gated bubble) | `packages/admin/src/Filament/AiAssistantPlugin.php`, added in `LunarPanelManager::defaultPanel()` |
| Permissions state | `packages/admin/database/state/EnsureAiAssistantPermissions.php` (runs on `migrate`) |
| Config (2 Lunar-specific docblocks) | `packages/admin/config/ai-assistant.php` |
| Settings view | `packages/admin/resources/views/filament/ai-assistant/settings.blade.php` |
| Conversation history trait | `Minic\LaravelAiAssistant\Traits\HasAiConversations` on `Lunar\Admin\Models\Staff` |

## Permissions

Created idempotently by `EnsureAiAssistantPermissions` (a Lunar migration state — no seeder), shown
in **Settings → Staff** grouped under `ai`:

| Permission | Grants access to |
| --- | --- |
| `ai:manage-settings` | The AI Assistant **Settings** page |
| `ai:chat` | The AI Assistant **Chat** page and the floating **chat bubble** |

Administrators always pass (Lunar's `Gate::after`). The chat page and bubble additionally depend on
the corresponding toggles in the AI Assistant settings.

## Setup

```bash
# 1. Run composer update
composer update

# 2. Publish the package migrations and assets.
php artisan ai-assistant:install

# 3. (optional) Publish Lunar's config copy to override defaults.
php artisan vendor:publish --tag="lunar.ai-assistant" --force

# 4. The Laravel AI SDK config holds provider API keys.
php artisan vendor:publish --tag=ai-config

# 5. Publish the Laravel AI SDK migrations.
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"

# 6. Run migrations — creates the table AND the ai:manage-settings / ai:chat permissions.
php artisan migrate
```

Set the provider key in `.env` (e.g. `GEMINI_API_KEY=...`, read by `config/ai.php`) and run a queue
worker (`php artisan queue:work`) — chat responses are processed on a queue.
