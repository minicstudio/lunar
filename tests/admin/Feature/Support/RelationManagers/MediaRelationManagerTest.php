<?php

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\BrandResource\Pages\ManageBrandMedia;
use Lunar\Admin\Filament\Resources\CollectionResource\Pages\ManageCollectionMedia;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductMedia;
use Lunar\Admin\Support\Extending\RelationManagerExtension;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Admin\Support\RelationManagers\MediaRelationManager;
use Lunar\Base\MediaFileName;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Collection as ProductCollection;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('support.relation-managers');

it('can render relation manager', function ($model, $page) {
    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $model = $model::factory()->create();

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $model,
        'pageClass' => $page,
    ])->assertSuccessful();
})->with([
    [Product::class, ManageProductMedia::class],
    [Brand::class, ManageBrandMedia::class],
]);

it('persists custom properties added by an extension when creating media', function () {
    $class = new class extends RelationManagerExtension
    {
        public function extendForm(Schema $schema): Schema
        {
            $schema->components([
                ...$schema->getComponents(true),
                TextInput::make('custom_properties.credits'),
            ]);

            return $schema;
        }
    };

    LunarPanel::extensions([
        MediaRelationManager::class => $class::class,
    ]);

    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $brand = Brand::factory()->create(['name' => 'Őszi márka']);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $brand,
        'pageClass' => ManageBrandMedia::class,
    ])->callTableAction(CreateAction::class, data: [
        'custom_properties.name' => 'Test image',
        'media' => UploadedFile::fake()->image('foobar.jpg'),
    ])->assertHasNoTableActionErrors();

    $media = $brand->fresh()->getFirstMedia('default');

    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty('name'))->toBe('Test image');
});

it('names the uploaded file after the name field or the owner name', function (?string $name, string $expectedFileName) {
    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $brand = Brand::factory()->create(['name' => 'Őszi márka']);

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $brand,
        'pageClass' => ManageBrandMedia::class,
    ])->callTableAction(CreateAction::class, data: [
        'custom_properties.name' => $name,
        'media' => UploadedFile::fake()->image('Kép Fájl.jpg'),
    ])->assertHasNoTableActionErrors();

    expect($brand->fresh()->getFirstMedia('default')->file_name)->toBe($expectedFileName)
        ->and($brand->fresh()->getFirstMedia('default')->getCustomProperty('name'))->toBe(filled($name) ? $name : 'Őszi márka');
})->with([
    'name field' => ['Piros nyári ruha', 'piros-nyari-ruha.webp'],
    'owner name' => [null, 'oszi-marka.webp'],
    'blank name' => ['', 'oszi-marka.webp'],
]);

it('does not reuse a file name already used by the owner', function () {
    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $brand = Brand::factory()->create(['name' => 'Őszi márka']);

    foreach (['first.png', 'second.jpg'] as $uploadedFileName) {
        Livewire::test(MediaRelationManager::class, [
            'ownerRecord' => $brand,
            'pageClass' => ManageBrandMedia::class,
        ])->callTableAction(CreateAction::class, data: [
            'custom_properties.name' => 'Piros ruha',
            'media' => UploadedFile::fake()->image($uploadedFileName),
        ])->assertHasNoTableActionErrors();
    }

    expect($brand->fresh()->getMedia('default')->pluck('file_name')->all())
        ->toBe(['piros-ruha.webp', 'piros-ruha-2.webp']);
});

it('preserves existing custom properties not present on the edit form', function () {
    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $brand = Brand::factory()->create(['name' => 'Őszi márka']);

    $media = $brand
        ->addMedia(UploadedFile::fake()->image('foobar.jpg'))
        ->preservingOriginal()
        ->withCustomProperties([
            'name' => 'Original name',
            'sha1' => 'abc123',
        ])
        ->toMediaCollection('default');

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $brand,
        'pageClass' => ManageBrandMedia::class,
    ])->mountTableAction(EditAction::class, $media)
        ->setTableActionData([
            'custom_properties' => [
                'name' => 'Updated name',
                'primary' => false,
            ],
        ])->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $media->refresh();

    expect($media->getCustomProperty('name'))->toBe('Updated name')
        ->and($media->getCustomProperty('sha1'))->toBe('abc123');
});

it('uses the owner name and unique filenames for unnamed catalog uploads', function (string $modelClass, string $pageClass, string $name) {
    $this->asStaff();
    Language::factory()->create(['default' => true]);
    $attributes = $modelClass === Brand::class
        ? ['name' => $name]
        : ['attribute_data' => collect(['name' => new TranslatedText(['en' => $name])])];
    $owner = $modelClass::factory()->create($attributes);

    foreach (['first.jpg', 'second.jpg'] as $fileName) {
        Livewire::test(MediaRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => $pageClass,
        ])->callTableAction(CreateAction::class, data: [
            'custom_properties.name' => null,
            'media' => UploadedFile::fake()->image($fileName),
        ])->assertHasNoTableActionErrors();
    }

    $media = $owner->fresh()->getMedia('default');
    $slug = MediaFileName::slug($name);
    expect($media->pluck('file_name')->all())->toBe([$slug.'.webp', substr($slug, 0, 98).'-2.webp'])
        ->and($media->pluck('name')->all())->toBe([$name, $name])
        ->and($media->map(fn ($image) => $image->getCustomProperty('name'))->all())->toBe([$name, $name]);
})->with([
    [Product::class, ManageProductMedia::class, 'Nyári ruha'],
    [ProductCollection::class, ManageCollectionMedia::class, 'Nyári kollekció'],
    [Brand::class, ManageBrandMedia::class, 'Őszi márka'],
    [Brand::class, ManageBrandMedia::class, str_repeat('a', 150)],
]);
