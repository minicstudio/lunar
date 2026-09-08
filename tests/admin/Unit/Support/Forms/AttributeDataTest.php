<?php

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Lunar\Admin\Support\Facades\AttributeData;
use Lunar\Admin\Support\FieldTypes\TextField;
use Lunar\FieldTypes\Dropdown;
use Lunar\FieldTypes\ListField;
use Lunar\FieldTypes\Number;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\FieldTypes\YouTube;
use Lunar\Models\Attribute;
use Lunar\Tests\Admin\Unit\Livewire\TestCase;

uses(TestCase::class)
    ->group('livewire.support');

describe('attribute data test', function () {
    beforeEach(function () {
        $this->asStaff();
    });

    test('correct form components are returned', function ($fieldType, $expectedComponent, $configuration = []) {
        $attribute = Attribute::factory()->create([
            'type' => $fieldType,
            'configuration' => $configuration,
        ]);

        $inputComponent = AttributeData::getFilamentComponent($attribute);

        expect($inputComponent)->toBeInstanceOf($expectedComponent);

    })->with([
        [Text::class, TextInput::class],
        [Text::class, RichEditor::class, ['richtext' => true]],
        [Dropdown::class, Select::class],
        [ListField::class, KeyValue::class],
        [YouTube::class, Lunar\Admin\Support\Forms\Components\YouTube::class],
        [Number::class, TextInput::class],
    ]);

    test('can extend converters', function () {
        $attribute = Attribute::factory()->create([
            'type' => TestFieldType::class,
        ]);

        AttributeData::registerFieldType(TestFieldType::class, TestFieldConverter::class);

        $inputComponent = AttributeData::getFilamentComponent($attribute);
        expect($inputComponent)->toBeInstanceOf(RichEditor::class);
    });

    test('preserves dropdown values when saving unchanged select state', function (?string $value, string $expected) {
        $attribute = Attribute::factory()->create([
            'type' => Dropdown::class,
            'handle' => 'rating',
        ]);

        $component = AttributeData::getFilamentComponent($attribute);
        $state = $value;

        foreach ($component->getStateCasts() as $stateCast) {
            $state = $stateCast->get($state);
        }

        $result = $component->mutateDehydratedState($state);

        expect($result)->toBeInstanceOf(Dropdown::class)
            ->and($result->getValue())->toBe($expected);
    })->with([
        'numeric rating' => ['5', '5'],
        'zero' => ['0', '0'],
        'leading zeros' => ['05', '05'],
        'text option' => ['excellent', 'excellent'],
        'empty selection' => [null, ''],
    ]);

    test('dehydrates translated text array state into field type with values', function () {
        $attribute = Attribute::factory()->create([
            'type' => TranslatedText::class,
            'handle' => 'collection-meta-keywords',
        ]);

        $component = AttributeData::getFilamentComponent($attribute);

        $result = $component->mutateDehydratedState([
            'ro' => 'carte, cadou',
            'hu' => 'konyv, ajandek',
        ]);

        expect($result)->toBeInstanceOf(TranslatedText::class)
            ->and($result->getValue()->get('ro')->getValue())->toBe('carte, cadou')
            ->and($result->getValue()->get('hu')->getValue())->toBe('konyv, ajandek');
    });

    test('dehydrates empty translated text state without error', function () {
        $attribute = Attribute::factory()->create([
            'type' => TranslatedText::class,
            'handle' => 'collection-meta-description',
        ]);

        $component = AttributeData::getFilamentComponent($attribute);

        $result = $component->mutateDehydratedState(null);

        expect($result)->toBeInstanceOf(TranslatedText::class)
            ->and($result->getValue())->toBeEmpty();
    });
});

class TestFieldType extends Text {}

class TestFieldConverter extends TextField
{
    public static function getFilamentComponent(Attribute $attribute): Component
    {
        return RichEditor::make($attribute->handle);
    }
}
