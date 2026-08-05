<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Lunar\Console\Commands\ConvertMediaToWebp;
use Lunar\Jobs\Media\ConvertMediaToWebp as ConvertMediaToWebpJob;
use Lunar\Models\Product;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('registers the convert webp command', function () {
    expect(collect(Artisan::all())->keys())->toContain('lunar:media:convert-webp');
});

it('dispatches a job per media item', function () {
    Bus::fake([ConvertMediaToWebpJob::class]);

    $product = Product::factory()->create();
    $media = $product->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->toMediaCollection(config('lunar.media.collection'));

    $this->artisan(ConvertMediaToWebp::class, [
        '--ids' => [$media->id],
    ])->assertSuccessful();

    Bus::assertDispatched(ConvertMediaToWebpJob::class, function (ConvertMediaToWebpJob $job) use ($media) {
        return $job->media->is($media)
            && $job->onlyMissing === false
            && $job->onlyConversions === ['small', 'medium', 'large', 'zoom'];
    });
});

it('does not dispatch jobs for media already converted to webp', function () {
    Bus::fake([ConvertMediaToWebpJob::class]);

    $product = Product::factory()->create();
    $media = $product->addMedia(UploadedFile::fake()->image('avatar.webp'))
        ->toMediaCollection(config('lunar.media.collection'));

    expect($media->mime_type)->toBe('image/webp');

    $this->artisan(ConvertMediaToWebp::class, [
        '--ids' => [$media->id],
    ])->assertSuccessful();

    Bus::assertNotDispatched(ConvertMediaToWebpJob::class);
});

it('does not dispatch jobs when mime_type is webp even if file_name is not', function () {
    Bus::fake([ConvertMediaToWebpJob::class]);

    $product = Product::factory()->create();
    $media = $product->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->toMediaCollection(config('lunar.media.collection'));

    $media->update([
        'mime_type' => 'image/webp',
    ]);

    $this->artisan(ConvertMediaToWebp::class, [
        '--ids' => [$media->id],
    ])->assertSuccessful();

    Bus::assertNotDispatched(ConvertMediaToWebpJob::class);
});
