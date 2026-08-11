<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductMedia;
use Lunar\Admin\Support\RelationManagers\MediaRelationManager;
use Lunar\Models\Language;
use Lunar\Models\Product;

uses(\Lunar\Tests\Admin\Feature\Filament\TestCase::class)
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
    [\Lunar\Models\Brand::class, \Lunar\Admin\Filament\Resources\BrandResource\Pages\ManageBrandMedia::class],
]);

it('notifies when uploading a non-webp image and saves media without a warning modal', function () {
    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $product = Product::factory()->create();

    $component = Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => ManageProductMedia::class,
        'mediaCollection' => config('lunar.media.collection'),
    ]);

    expect(method_exists($component->instance(), 'confirmNonWebpUpload'))->toBeFalse();

    $component
        ->callTableAction(\Filament\Tables\Actions\CreateAction::class, data: [
            'custom_properties' => [
                'name' => 'Product photo',
                'primary' => true,
            ],
            'media' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified(__('lunarpanel::relationmanagers.medias.notifications.webp_conversion.title'));

    expect($product->refresh()->getMedia(config('lunar.media.collection')))->toHaveCount(1);

    $media = $product->getMedia(config('lunar.media.collection'))->first();

    expect($media->file_name)->toEndWith('.webp')
        ->and($media->mime_type)->toBe('image/webp');
});

it('does not notify when uploading a webp image', function () {
    $this->asStaff();

    Language::factory()->create([
        'default' => true,
    ]);

    $product = Product::factory()->create();

    Livewire::test(MediaRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => ManageProductMedia::class,
        'mediaCollection' => config('lunar.media.collection'),
    ])
        ->callTableAction(\Filament\Tables\Actions\CreateAction::class, data: [
            'custom_properties' => [
                'name' => 'Product photo',
                'primary' => true,
            ],
            'media' => UploadedFile::fake()->image('photo.webp'),
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotNotified(__('lunarpanel::relationmanagers.medias.notifications.webp_conversion.title'));

    expect($product->refresh()->getMedia(config('lunar.media.collection')))->toHaveCount(1);
});
