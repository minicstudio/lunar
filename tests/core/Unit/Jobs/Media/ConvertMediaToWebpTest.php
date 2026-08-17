<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Jobs\Media\ConvertMediaToWebp;
use Lunar\Models\Product;
use Spatie\MediaLibrary\Conversions\FileManipulator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));

    config()->set('media-library.queue_connection_name', 'sync');
});

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

it('leaves no conversion file behind using the previous extension', function () {
    $product = Product::factory()->create();
    $media = $product->addMedia(UploadedFile::fake()->image('photo.png'))
        ->toMediaCollection(config('lunar.media.collection'));

    $disk = Storage::disk($media->conversions_disk);
    $conversionDirectory = dirname($media->getPathRelativeToRoot('small'));

    expect($disk->files($conversionDirectory))->toContain("{$conversionDirectory}/photo-small.png");

    (new ConvertMediaToWebp($media))->handle(app(FileManipulator::class));

    $media->refresh();

    expect($disk->files($conversionDirectory))
        ->not->toContain("{$conversionDirectory}/photo-small.png")
        ->each->toEndWith('.webp');
});

it('regenerates every conversion as actual webp data', function () {
    $product = Product::factory()->create();
    $media = $product->addMedia(UploadedFile::fake()->image('photo.png'))
        ->toMediaCollection(config('lunar.media.collection'));

    (new ConvertMediaToWebp($media))->handle(app(FileManipulator::class));

    $media->refresh();

    $disk = Storage::disk($media->conversions_disk);

    foreach (['small', 'medium', 'large', 'zoom'] as $conversion) {
        $contents = $disk->get($media->getPathRelativeToRoot($conversion));

        expect(substr($contents, 0, 4))->toBe('RIFF')
            ->and(substr($contents, 8, 4))->toBe('WEBP');
    }
});
