<?php

namespace Lunar\Admin\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Minic\LaravelAiAssistant\Contracts\ToolInterface;
use Minic\LaravelAiAssistant\Contracts\ToolMetadataInterface;
use Minic\LaravelAiAssistant\Facades\AiAssistant;
use Minic\LaravelAiAssistant\Models\AiAssistantSettings;
use Minic\LaravelAiAssistant\Support\ToolRegistry;

/**
 * Filament 3 / Livewire 3 AI Assistant settings page for the Lunar admin panel.
 *
 * Adapted from the package's Filament 5 page (Schemas API) to the Filament 3 Forms API.
 * The API key field has been intentionally removed: the provider key is supplied via the
 * Laravel AI SDK config (config/ai.php, e.g. GEMINI_API_KEY) rather than this page.
 */
class AiAssistantSettingsPage extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'AI Assistant';

    protected static ?string $navigationLabel = 'AI Assistant Settings';

    protected static ?string $title = 'AI Assistant Settings';

    protected static string $view = 'lunarpanel::filament.ai-assistant.settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected ?AiAssistantSettings $settings = null;

    /**
     * Restricts access to staff holding the `ai:manage-settings` permission
     * (admins always pass via Lunar's Gate::after resolution).
     *
     * @return bool True if the current user can access the settings page, false otherwise.
     */
    public static function canAccess(): bool
    {
        $user = filament()->auth()->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('ai:manage-settings');
    }

    /**
     * Returns the relative route name for this page within the panel.
     *
     * @return string The route name 'ai-assistant-settings'.
     */
    public static function getRelativeRouteName(): string
    {
        return 'ai-assistant-settings';
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

    /**
     * Mounts the page: loads settings and fills the form.
     */
    public function mount(): void
    {
        $this->settings = AiAssistantSettings::instance();
        $this->fillForm();
    }

    /**
     * Fills the form with current settings.
     */
    protected function fillForm(): void
    {
        $this->form->fill($this->settings->attributesToArray());
    }

    /**
     * Defines the form schema (provider, model, persona, chat appearance, tools).
     *
     * @param  Form  $form  The form to configure.
     * @return Form The configured form.
     */
    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model($this->settings)
            ->schema([
                Section::make(__('Provider & Model'))
                    ->schema([
                        Select::make('provider')
                            ->label(__('Provider'))
                            ->options(fn () => $this->getProviderOptions())
                            ->live()
                            ->searchable(),
                        TextInput::make('active_model')
                            ->label(__('Active model'))
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make(__('Persona'))
                    ->schema([
                        Textarea::make('system_prompt')
                            ->label(__('System prompt'))
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Chat appearance'))
                    ->schema([
                        TextInput::make('chat_title')
                            ->label(__('Chat title'))
                            ->maxLength(255),
                        TextInput::make('chat_accent_color')
                            ->label(__('Accent color'))
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make(__('Chat UI'))
                    ->schema([
                        Toggle::make('bubble_chat_enabled')
                            ->label(__('Enable bubble chat'))
                            ->helperText(__('Show the floating chat bubble in the bottom-right corner.')),
                        Toggle::make('fullpage_chat_enabled')
                            ->label(__('Enable full-page chat'))
                            ->helperText(__('Show the Chat page in the navigation menu.')),
                    ])
                    ->columns(2),
                Section::make(__('Tools'))
                    ->description(__('Enable or disable tools. Each tool\'s settings appear in a separate block below when that tool is enabled.'))
                    ->schema($this->getToolsFormSchema()),
            ]);
    }

    /**
     * Returns the provider options for the provider select (openai, anthropic, gemini).
     *
     * @return array<string, string> Provider key => label.
     */
    protected function getProviderOptions(): array
    {
        return [
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini',
        ];
    }

    /**
     * Returns enabled provider tool checkbox options for the given provider (only tools the provider supports).
     * Uses config('ai-assistant.provider_tools') for support matrix and labels.
     *
     * @return array<string, string> Tool key => label.
     */
    protected function getProviderToolOptionsForProvider(?string $provider): array
    {
        $provider = $provider !== null ? strtolower(trim($provider)) : '';
        if ($provider === '') {
            return [];
        }

        return collect(config('ai-assistant.provider_tools', []))
            ->filter(fn (array $entry) => in_array($provider, $entry['providers'] ?? [], true))
            ->mapWithKeys(fn (array $entry, string $tool) => [$tool => __($entry['label'] ?? $tool)])
            ->all();
    }

    /**
     * Filters enabled_provider_tools to only include tools supported by the given provider.
     *
     * @param  array<int, string>  $enabled
     * @return array<int, string>
     */
    protected function filterEnabledProviderToolsForProvider(array $enabled, ?string $provider): array
    {
        $allowed = array_keys($this->getProviderToolOptionsForProvider($provider));

        return array_values(array_intersect($enabled, $allowed));
    }

    /**
     * Builds the tools section of the form (enabled package tools, provider tools, per-tool settings).
     *
     * @return array<int, Component> Form components for the tools section.
     */
    protected function getToolsFormSchema(): array
    {
        $registry = app(ToolRegistry::class);
        $tools = $registry->all();
        $options = collect($tools)->mapWithKeys(fn ($t) => [$t->name() => $t->label()])->all();

        $schema = [
            CheckboxList::make('enabled_tools')
                ->label(__('Enabled package tools'))
                ->options($options)
                ->columns(2)
                ->live(),
            Placeholder::make('tool_dependency_hint')
                ->content(fn ($get) => $this->getToolDependencyHint($get('enabled_tools'), $tools))
                ->visible(fn ($get) => $this->getToolDependencyHint($get('enabled_tools'), $tools) !== ''),
            CheckboxList::make('enabled_provider_tools')
                ->label(__('Enabled provider tools'))
                ->options(fn ($get) => $this->getProviderToolOptionsForProvider($get('provider')))
                ->columns(2),
        ];

        foreach ($tools as $tool) {
            $settingsSchema = $tool->settings();
            if ($settingsSchema === []) {
                continue;
            }
            $toolName = $tool->name();
            $fields = [];
            foreach ($settingsSchema as $key => $def) {
                $statePath = 'tool_settings.'.$tool->name().'.'.$key;
                $fields[] = $this->buildToolSettingField($statePath, $def);
            }
            $schema[] = Section::make($tool->label())
                ->schema($fields)
                ->visible(fn (Get $get): bool => collect(Arr::wrap($get('enabled_tools')))->containsStrict($toolName));
        }

        return $schema;
    }

    /**
     * Returns a hint when an enabled tool has required-tool dependencies that are not enabled.
     *
     * @param  array<int, string>|null  $enabledTools
     * @param  array<string, ToolInterface>  $tools
     */
    protected function getToolDependencyHint(?array $enabledTools, array $tools): string
    {
        $enabled = Arr::wrap($enabledTools ?? []);
        $labels = collect($tools)->mapWithKeys(fn ($t) => [$t->name() => $t->label()])->all();
        $hints = [];

        foreach ($tools as $tool) {
            if (! $tool instanceof ToolMetadataInterface) {
                continue;
            }
            if (! in_array($tool->name(), $enabled, true)) {
                continue;
            }
            $required = $tool->requiredTools();
            $missing = array_diff($required, $enabled);
            if ($missing !== []) {
                $missingLabels = array_map(fn ($name) => $labels[$name] ?? $name, $missing);
                $hints[] = $tool->label().': '.__('Works best when :tools are also enabled.', ['tools' => implode(', ', $missingLabels)]);
            }
        }

        return $hints === [] ? '' : implode(' ', $hints);
    }

    /**
     * Builds a Filament form field for a tool setting definition.
     * Supports integer, tags (array of strings), boolean, and string types.
     *
     * @param  array<string, mixed>  $def  Setting definition from ToolInterface::settings().
     * @return Field The configured form field.
     */
    protected function buildToolSettingField(string $statePath, array $def): Field
    {
        $type = Arr::get($def, 'type', 'string');
        $label = __(Arr::get($def, 'label', $statePath));
        $default = Arr::get($def, 'default');
        $description = Arr::get($def, 'description');

        return match ($type) {
            'integer', 'int' => TextInput::make($statePath)
                ->label($label)
                ->helperText($description)
                ->numeric()
                ->default($default)
                ->minValue(Arr::get($def, 'min'))
                ->maxValue(Arr::get($def, 'max')),
            'tags' => TagsInput::make($statePath)
                ->label($label)
                ->helperText($description)
                ->default($default ?? [])
                ->placeholder(__('Type and press Enter')),
            'boolean', 'bool' => Toggle::make($statePath)
                ->label($label)
                ->helperText($description)
                ->default($default ?? false),
            default => TextInput::make($statePath)
                ->label($label)
                ->helperText($description)
                ->default($default),
        };
    }

    /**
     * Header actions (e.g. Embed chat modal).
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('embedChat')
                ->label(__('Embed chat'))
                ->modalHeading(__('Embed This Bot'))
                ->modalDescription(__('Paste this script tag into any app or website.'))
                ->modalWidth(MaxWidth::SixExtraLarge)
                ->modalContent(fn () => view('laravel-ai-assistant::filament.embed-snippet-modal', [
                    'snippet' => AiAssistant::embedSnippet(),
                ]))
                ->modalSubmitAction(false),
        ];
    }

    /**
     * Called from embed modal after copy to clipboard; sends a success notification.
     */
    public function notifyEmbedCopied(): void
    {
        Notification::make()
            ->success()
            ->title(__('Copied to clipboard'))
            ->send();
    }

    /**
     * Saves the form data to the settings model.
     */
    public function save(): void
    {
        DB::transaction(function (): void {
            $settings = $this->settings ?? AiAssistantSettings::instance();
            $data = $this->form->getState();

            $data['enabled_provider_tools'] = $this->filterEnabledProviderToolsForProvider(
                Arr::wrap($data['enabled_provider_tools'] ?? []),
                $data['provider'] ?? null
            );
            $settings->update($data);
            $this->settings = $settings;
        });

        Notification::make()
            ->success()
            ->title(__('Settings saved'))
            ->send();
    }
}
