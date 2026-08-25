<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Http\UploadedFile;
use Lunar\Base\MediaWebpConverter;
use Lunar\Models\Product;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('converts non-webp upload contents to webp', function () {
    $file = UploadedFile::fake()->image('avatar.png');

    $result = MediaWebpConverter::convertUpload($file->get(), $file->getClientOriginalName());

    expect($result['converted'])->toBeTrue()
        ->and($result['file_name'])->toBe('avatar.webp')
        ->and($result['contents'])->not->toBeEmpty();
});

it('leaves webp uploads unchanged', function () {
    $file = UploadedFile::fake()->image('avatar.webp');

    $result = MediaWebpConverter::convertUpload($file->get(), $file->getClientOriginalName());

    expect($result['converted'])->toBeFalse()
        ->and($result['file_name'])->toBe('avatar.webp');
});

it('converts an existing media original to webp', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $product = Product::factory()->create();
    $media = $product->addMedia($file)->toMediaCollection(config('lunar.media.collection'));

    expect($media->file_name)->toEndWith('.jpg');

    expect(MediaWebpConverter::convertMediaOriginal($media->fresh()))->toBeTrue();

    $media->refresh();

    expect($media->file_name)->toEndWith('.webp')
        ->and($media->mime_type)->toBe('image/webp')
        ->and($media->getPath())->toEndWith('.webp');
});

it('throws when the original media file is missing', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $product = Product::factory()->create();
    $media = $product->addMedia($file)->toMediaCollection(config('lunar.media.collection'));

    Illuminate\Support\Facades\Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());

    MediaWebpConverter::convertMediaOriginal($media->fresh());
})->throws(RuntimeException::class);
