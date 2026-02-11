<?php

namespace Lunar\Admin\Support\FieldTypes;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Toggle;
use Lunar\Admin\Support\Forms\Components\TranslatedText as TranslatedTextComponent;
use Lunar\Admin\Support\Synthesizers\TranslatedTextSynth;
use Lunar\Models\Attribute;

class TranslatedText extends BaseFieldType
{
    protected static string $synthesizer = TranslatedTextSynth::class;

    public static function getConfigurationFields(): array
    {
        $array = TextField::getConfigurationFields();

        $array[] = Toggle::make('disable_richtext_toolbar')->label(
            __('lunarpanel::fieldtypes.text.form.disable_richtext_toolbar.label')
        );

        return $array;
    }

    public static function getFilamentComponent(Attribute $attribute): Component
    {
        $disableToolbar = (bool) $attribute->configuration->get('disable_richtext_toolbar');
        $richtext = (bool) $attribute->configuration->get('richtext');
        
        return TranslatedTextComponent::make($attribute->handle)
            ->optionRichtext(($disableToolbar === true || $richtext === true))
            ->richtextDisableAllToolbarButtons($disableToolbar)
            ->when(filled($attribute->validation_rules), fn (TranslatedTextComponent $component) => $component->rules($attribute->validation_rules))
            ->required((bool) $attribute->required)
            ->helperText($attribute->translate('description'));
    }
}
