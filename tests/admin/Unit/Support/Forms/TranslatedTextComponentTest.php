<?php

uses(\Lunar\Tests\Admin\Unit\Livewire\TestCase::class)
    ->group('livewire.support');

test('translated text does not register locale editors as filament child components', function () {
    \Lunar\Models\Language::factory()->create([
        'code' => 'en',
        'default' => true,
    ]);

    $component = \Lunar\Admin\Support\Forms\Components\TranslatedText::make('description')
        ->optionRichtext(true);

    expect($component->getChildComponents())->toBeEmpty();

    $component->prepareChildComponents();

    expect($component->components)->toHaveCount(1)
        ->and($component->components->first())->toBeInstanceOf(\Lunar\Admin\Support\Forms\Components\TranslatedRichEditor::class);
});
