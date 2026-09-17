<?php

namespace Lunar\Admin\Support\Forms\Components;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lunar\Base\FieldType;
use Lunar\Models\Language;

class TranslatedText extends TextInput
{
    protected string $view = 'lunarpanel::forms.components.translated-text';

    public bool $expanded = false;

    public bool $optionRichtext = false;

    protected ?array $richtextToolbarButtons = null;

    protected array $richtextDisableToolbarButtons = [];

    protected bool $richtextDisableAllToolbarButtons = false;

    protected Closure|null|string $richtextFileAttachmentsDisk = null;

    protected Closure|null|string $richtextFileAttachmentsDirectory = null;

    protected Closure|string $richtextFileAttachmentsVisibility = 'public';

    protected ?Closure $richtextGetUploadedAttachmentUrlUsing = null;

    protected ?Closure $richtextSaveUploadedFileAttachmentsUsing = null;

    protected bool $mergeExtraInputAttributes = false;

    public Language $defaultLanguage;

    public Collection $components;

    public Collection $languages;

    public function setUp(): void
    {
        parent::setUp();

        $this->languages = Language::orderBy('default', 'desc')->get();

        $this->default(static function (TranslatedText $component): array {
            return $component->getLanguageDefaults();
        });

        $this->childComponents([]);
    }

    /**
     * Unlike the default component hydration, the hydration hooks of this component (e.g. `formatStateUsing()`)
     * run before the per-language child fields hydrate, as they normalize the translations the children read from.
     *
     * @param  array<string, mixed> | null  $hydratedDefaultState
     * @param  array<string, true>  $appliedStateCastPaths
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        $this->hydrateDefaultState($hydratedDefaultState);

        if ($hydratedDefaultState === null) {
            $this->loadStateFromRelationships();
        }

        $this->unwrapFieldTypeState();

        if ($shouldCallHydrationHooks) {
            $this->callAfterStateHydrated();
        }

        foreach ($this->getChildSchemas(withHidden: true) as $childSchema) {
            $childSchema->hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
        }
    }

    /**
     * @param  array<string>  $statePaths
     */
    public function hydrateStatePartially(array $statePaths, bool $shouldCallHydrationHooks = true): void
    {
        $statePathToCheck = $this->getStatePath();

        $isStatePathMatching = in_array($statePathToCheck, $statePaths);

        while ((! $isStatePathMatching) && str($statePathToCheck)->contains('.')) {
            $statePathToCheck = (string) str($statePathToCheck)->beforeLast('.');

            $isStatePathMatching = in_array($statePathToCheck, $statePaths);
        }

        if ($isStatePathMatching) {
            $this->loadStateFromRelationships();

            $this->unwrapFieldTypeState();

            if ($shouldCallHydrationHooks) {
                $this->callAfterStateHydrated();
            }
        }

        foreach ($this->getChildSchemas(withHidden: true) as $childSchema) {
            $childSchema->hydrateStatePartially($statePaths, $shouldCallHydrationHooks);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function callBeforeStateDehydrated(array &$state = []): static
    {
        $this->unwrapFieldTypeState($state);

        return parent::callBeforeStateDehydrated($state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function dehydrateState(array &$state, bool $isDehydrated = true): void
    {
        $this->unwrapFieldTypeState($state);

        parent::dehydrateState($state, $isDehydrated);
    }

    /**
     * Field type values (e.g. set through `$set()` or Livewire's field type synthesizers) are unwrapped
     * into a plain translations array, so the per-language child fields can read and write their state.
     *
     * @param  array<string, mixed> | null  $state
     */
    protected function unwrapFieldTypeState(?array &$state = null): void
    {
        $rawState = $this->getRawState();
        $unwrappedRawState = $this->unwrapFieldTypeValue($rawState);

        if ($unwrappedRawState !== $rawState) {
            $this->rawState($unwrappedRawState);
        }

        $statePath = $this->getStatePath();

        if (($state !== null) && Arr::has($state, $statePath)) {
            Arr::set($state, $statePath, $this->unwrapFieldTypeValue(Arr::get($state, $statePath)));
        }
    }

    protected function unwrapFieldTypeValue(mixed $value): mixed
    {
        if ($value instanceof FieldType) {
            $value = $value->getValue();
        }

        if (! (is_array($value) || $value instanceof Arrayable)) {
            return $value;
        }

        return collect($value)
            ->map(fn (mixed $item): mixed => $item instanceof FieldType ? $item->getValue() : $item)
            ->all();
    }

    public function prepareChildComponents()
    {
        $this->components = collect(
            $this->getLanguages()->map(fn ($lang) => $this->getOptionRichtext() ?
                $this->getTranslatedRichEditorComponent($lang->code) :
                $this->getTranslatedTextComponent($lang->code)
            )
        );
    }

    /**
     * @return array<string, Schema>
     */
    public function getDefaultChildSchemas(): array
    {
        $this->prepareChildComponents();

        return $this->getLanguages()
            ->mapWithKeys(fn (Language $language): array => [
                $language->code => $this->makeChildSchema($language->code)->components(
                    $this->components
                        ->filter(fn ($component): bool => $component->getName() == $language->code)
                        ->map(fn ($component) => $this->prepareTranslateLocaleComponent($component, $language->code))
                        ->all()
                ),
            ])
            ->all();
    }

    protected function getTranslatedRichEditorComponent(string $langCode): TranslatedRichEditor
    {
        $component = TranslatedRichEditor::make($langCode)
            ->statePath($langCode)
            ->disableAllToolbarButtons($this->richtextDisableAllToolbarButtons)
            ->fileAttachmentsVisibility($this->richtextFileAttachmentsVisibility)
            ->fileAttachmentsDirectory($this->richtextFileAttachmentsDirectory)
            ->fileAttachmentsDisk($this->richtextFileAttachmentsDisk)
            ->getUploadedAttachmentUrlUsing($this->richtextGetUploadedAttachmentUrlUsing)
            ->saveUploadedFileAttachmentsUsing($this->richtextSaveUploadedFileAttachmentsUsing);

        if (! empty($this->richtextToolbarButtons)) {
            $component->disableToolbarButtons($this->richtextToolbarButtons);
        }

        if ($this->richtextToolbarButtons !== null) {
            $component->toolbarButtons($this->richtextToolbarButtons);
        }

        return $this->prepareTranslatedTextComponent($component);
    }

    public function extraInputAttributes(array|Closure $attributes, bool $merge = false): static
    {
        $this->mergeExtraInputAttributes = $merge;

        if ($merge) {
            $this->extraInputAttributes[] = $attributes;
        } else {
            $this->extraInputAttributes = [$attributes];
        }

        return $this;
    }

    protected function getTranslatedTextComponent(string $langCode): TranslatedTextInput
    {
        $component = TranslatedTextInput::make($langCode)
            ->statePath($langCode)
            ->telRegex($this->telRegex)
            ->step($this->step);

        if ($this->isEmail) {
            $component->email();
        }

        if ($this->isTel) {
            $component->tel();
        }

        if ($this->isUrl) {
            $component->url();
        }

        if ($this->isNumeric) {
            $component->numeric();
        }

        if ($this->step === 1) {
            $component->integer();
        }

        return $this->prepareTranslatedTextComponent($component);
    }

    protected function prepareTranslatedTextComponent(TranslatedTextInput|TranslatedRichEditor $component): TranslatedTextInput|TranslatedRichEditor
    {
        $component
            ->regex($this->regexPattern)
            ->minLength($this->minLength)
            ->maxLength($this->maxLength);

        if (! empty($this->extraInputAttributes)) {
            $component->extraInputAttributes($this->extraInputAttributes, $this->mergeExtraInputAttributes);
        }

        return $component;
    }

    public function prepareTranslateLocaleComponent(Component $component, string $locale)
    {
        $localeComponent = clone $component;

        $localeComponent->name($component->getName());

        $localeComponent->statePath($localeComponent->getName());

        $localeComponent->required(fn (): bool => $this->isRequired() && $locale == $this->getDefaultLanguage()->code);

        $localeComponent->validationAttribute(fn (): string => $this->getValidationAttribute());

        return $localeComponent;
    }

    public function getComponentByLanguage(Language $language): ?Schema
    {
        return $this->getChildSchema($language->code);
    }

    public function optionRichtext(bool $optionRichtext): static
    {
        $this->optionRichtext = $optionRichtext;

        return $this;
    }

    public function getOptionRichtext(): bool
    {
        return $this->optionRichtext;
    }

    public function richtextToolbarButtons(array $buttons): static
    {
        $this->richtextToolbarButtons = $buttons;

        return $this;
    }

    public function richtextDisableToolbarButtons(array $buttons): static
    {
        $this->richtextDisableToolbarButtons = $buttons;

        return $this;
    }

    public function richtextDisableAllToolbarButtons(bool $condition = true): static
    {
        $this->richtextDisableAllToolbarButtons = $condition;

        return $this;
    }

    public function richtextFileAttachmentsDirectory(string|Closure|null $name): static
    {
        $this->richtextFileAttachmentsDirectory = $name;

        return $this;
    }

    public function richtextFileAttachmentsDisk(string|Closure|null $name): static
    {
        $this->richtextFileAttachmentsDisk = $name;

        return $this;
    }

    public function richtextFileAttachmentsVisibility(string|Closure $visibility): static
    {
        $this->richtextFileAttachmentsVisibility = $visibility;

        return $this;
    }

    public function getExpanded(): bool
    {
        return $this->expanded;
    }

    public function getDefaultLanguage(): Language
    {
        return $this->languages->first(fn ($lang) => $lang->default);
    }

    public function getMoreLanguages(): Collection
    {
        return $this->languages->filter(fn ($lang) => ! $lang->default);
    }

    public function getLanguageDefaults(): array
    {
        return $this->getLanguages()->mapWithKeys(fn ($language) => [$language->code => ''])->toArray();
    }

    public function getLanguages(): Collection
    {
        return $this->languages;
    }
}
