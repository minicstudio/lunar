<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Filament\Livewire\ValueReviewStep;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\Tests\ProductImport\Support\Discounts;
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

test('creating a category that needs a decision is prefilled with its last level', function () {
    $similar = ProductImportValue::query()->where('normalized_key', 'barbati > geaci')->sole();

    expect($similar->create_name)->toBeNull();

    Livewire::test(ValueReviewStep::class, ['import' => $this->import])
        ->mountTableAction('create', $similar)
        ->assertTableActionDataSet(['name' => 'Geaci', 'parent' => $similar->parent_id])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($similar->fresh()->action)->toBe(ValueAction::Create)
        ->and($similar->fresh()->create_name)->toBe('Geaci');
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

test('the discounts tab lists a discount with its variants and lets staff use another eligible discount or create one with other values', function () {
    $this->travelTo(now()->setDate(2026, 11, 24));

    $import = ImportBuilder::import(ImportStatus::Staging);
    Discounts::sheet($import, ProductType::factory()->create()->id, [
        2 => ['OUT-RUHA-S', 'Rochie S', '229,00', '', '30', '', '2026-12-31'],
        3 => ['OUT-RUHA-M', 'Rochie M', '229,00', '', '30', '', '2026-12-31'],
    ]);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::ReviewingValues]);

    $blackWeek = Discounts::percent('Black Week', 40, '2026-11-20 00:00:00', '2026-11-30 23:59:59', [Product::factory()->create()]);
    $thirty = ProductImportValue::query()->where('type', ValueType::Discount)->sole();

    $component = Livewire::test(ValueReviewStep::class, ['import' => $import])
        ->call('setType', 'discount')
        ->assertSee('Discounts')
        ->assertSee('30% · until 31 Dec 2026')
        ->assertCanSeeTableRecords([$thirty])
        ->assertTableColumnStateSet('variant_count', 2, $thirty)
        ->assertTableColumnStateSet('product_count', 2, $thirty)
        ->assertSee('Create "30% · until 31 Dec 2026" (30% · until 31 Dec 2026)')
        ->assertActionEnabled('continue')
        ->mountTableAction('useExisting', $thirty)
        ->assertTableActionDataSet(['record' => null]);

    expect(array_values($component->instance()->getMountedTableActionForm()?->getComponent('record')?->getOptions() ?? []))->toBe(['Black Week (40% · from 20 Nov 2026 until 30 Nov 2026)']);

    $component->setTableActionData(['record' => $blackWeek->id])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertSee('Use "Black Week"');

    expect($thirty->fresh()->target_id)->toBe($blackWeek->id);

    $component->mountTableAction('create', $thirty)
        ->assertTableActionDataSet(['name' => '30% · until 31 Dec 2026', 'kind' => 'percent', 'value' => '30', 'ends_at' => '2026-12-31 23:59:59'])
        ->setTableActionData(['name' => 'Outlet iarnă', 'kind' => 'amount', 'value' => '25,5', 'starts_at' => null, 'ends_at' => '2027-01-15 23:59:59'])
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['value']);

    $component->setTableActionData(['value' => '25.50'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($thirty->fresh()->action)->toBe(ValueAction::Create)
        ->and($thirty->fresh()->create_name)->toBe('Outlet iarnă')
        ->and($thirty->fresh()->create_details)->toBe(['kind' => 'amount', 'value' => 2550, 'starts_at' => null, 'ends_at' => '2027-01-15 23:59:59']);
});
