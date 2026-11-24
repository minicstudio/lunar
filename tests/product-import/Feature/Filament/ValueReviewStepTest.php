<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Filament\Livewire\ValueReviewStep;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $group = CollectionGroup::factory()->create();
    $barbati = Collection::factory()->create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new Text('Barbati')])]);
    $this->geaca = Collection::factory()->make(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new Text('Geacă')])]);
    $barbati->appendNode($this->geaca);

    $this->import = ImportBuilder::import(ImportStatus::Staging, ['options' => (new ImportOptions(collectionGroupId: $group->id))->toArray()]);
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Categorie'],
        ['GC-1', 'Geacă iarnă', '399', 'Barbati > Geaci'],
        ['AC-1', 'Curea', '99', 'Accesorii'],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'en'], 2 => 'variant.price', 3 => 'product.category'], ProductType::factory()->create()->id);
    ImportBuilder::stage($this->import);
    $this->import->update(['status' => ImportStatus::ReviewingValues]);
});

test('one open decision disables continuing and deciding it enables it', function () {
    $similar = ProductImportValue::query()->where('normalized_key', 'barbati > geaci')->sole();

    $component = Livewire::test(ValueReviewStep::class, ['import' => $this->import])
        ->assertSee('1 open decision')
        ->assertSee('Needs a decision')
        ->assertSee('Barbati > Geacă')
        ->assertActionDisabled('continue');

    $component->callTableAction('useExisting', $similar, data: ['record' => $this->geaca->id])
        ->assertHasNoTableActionErrors()
        ->assertActionEnabled('continue')
        ->assertSee('Use "Barbati > Geacă"');

    expect($similar->fresh()->action)->toBe(ValueAction::UseExisting);

    $component->callAction('continue');

    expect($this->import->fresh()->status)->toBe(ImportStatus::PreviewReady);
});

test('the review shows the counts and the types', function () {
    Livewire::test(ValueReviewStep::class, ['import' => $this->import])
        ->assertSee('Similar')
        ->assertSee('Products without a category')
        ->assertSee('Categories')
        ->assertSee('Brands')
        ->assertSee('Accesorii')
        ->call('setType', 'brand')
        ->assertDontSee('Accesorii');
});

test('skipping a value is recorded', function () {
    $accesorii = ProductImportValue::query()->where('normalized_key', 'accesorii')->sole();

    Livewire::test(ValueReviewStep::class, ['import' => $this->import])
        ->callTableAction('skip', $accesorii)
        ->assertHasNoTableActionErrors();

    expect($accesorii->fresh()->action)->toBe(ValueAction::Skip);
});

test('the review options hold only the collection group, no separators', function () {
    Livewire::test(ValueReviewStep::class, ['import' => $this->import])
        ->mountAction('reviewOptions')
        ->assertSchemaComponentExists('collection_group_id', 'mountedActionSchema0')
        ->assertSchemaComponentDoesNotExist('path_separator', 'mountedActionSchema0')
        ->assertSchemaComponentDoesNotExist('list_separator', 'mountedActionSchema0');
});
