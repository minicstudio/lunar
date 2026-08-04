<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Http\UploadedFile;
use Lunar\Jobs\Media\ConvertMediaToWebp;
use Lunar\Models\Product;
use Spatie\MediaLibrary\Conversions\FileManipulator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('converts media original and derived conversions to webp', function () {
    $product = Product::factory()->create();
    $media = $product->addMedia(UploadedFile::fake()->image('photo.png'))
        ->toMediaCollection(config('lunar.media.collection'));

    expect($media->file_name)->toEndWith('.png');

    (new ConvertMediaToWebp($media))->handle(app(FileManipulator::class));

    $media->refresh();

    expect($media->file_name)->toEndWith('.webp')
        ->and($media->mime_type)->toBe('image/webp')
        ->and($media->hasGeneratedConversion('small'))->toBeTrue()
        ->and($media->getPath('small'))->toEndWith('.webp');
});
