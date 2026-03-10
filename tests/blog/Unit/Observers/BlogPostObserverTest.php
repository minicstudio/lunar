<?php

uses(\Lunar\Tests\Blog\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Storage;
use Lunar\Blog\Models\BlogPost;
use Lunar\FieldTypes\File;

beforeEach(function () {
    $this->createLanguages();
});

test('deletes the old thumbnail on update when changed', function () {
    $post = BlogPost::factory()->create([
        'attribute_data' => collect([
            'thumbnail' => new File(['blog/old.jpg']),
        ]),
    ]);

    $disk = $post->thumbnailDisk();
    Storage::fake($disk);

    Storage::disk($disk)->put('blog/old.jpg', 'dummy');
    Storage::disk($disk)->assertExists('blog/old.jpg');

    $post->attribute_data = collect([
        'thumbnail' => new File(['blog/new.jpg']),
    ]);
    $post->save();

    Storage::disk($disk)->assertMissing('blog/old.jpg');
});

test('does not delete the thumbnail when it is unchanged on update', function () {
    $post = BlogPost::factory()->create([
        'attribute_data' => collect([
            'thumbnail' => new File(['blog/same.jpg']),
        ]),
    ]);

    $disk = $post->thumbnailDisk();
    Storage::fake($disk);

    Storage::disk($disk)->put('blog/same.jpg', 'dummy');
    Storage::disk($disk)->assertExists('blog/same.jpg');

    $post->attribute_data = collect([
        'thumbnail' => new File(['blog/same.jpg']),
    ]);
    $post->save();

    Storage::disk($disk)->assertExists('blog/same.jpg');
});

test('does nothing on update when original thumbnail is missing', function () {
    $post = BlogPost::factory()->create();

    $disk = $post->thumbnailDisk();
    Storage::fake($disk);

    Storage::disk($disk)->put('blog/unrelated.jpg', 'dummy');
    Storage::disk($disk)->assertExists('blog/unrelated.jpg');

    $post->attribute_data = collect([
        'thumbnail' => new File(['blog/newonly.jpg']),
    ]);
    $post->save();

    Storage::disk($disk)->assertExists('blog/unrelated.jpg');
});

test('deletes the thumbnail file when the post is deleting', function () {
    $post = BlogPost::factory()->create([
        'attribute_data' => collect([
            'thumbnail' => new File(['blog/to-delete.jpg']),
        ]),
    ]);

    $disk = $post->thumbnailDisk();
    Storage::fake($disk);

    Storage::disk($disk)->put('blog/to-delete.jpg', 'dummy');
    Storage::disk($disk)->assertExists('blog/to-delete.jpg');

    $post->delete();

    Storage::disk($disk)->assertMissing('blog/to-delete.jpg');
});

test('does nothing on deleting when there is no thumbnail', function () {
    $post = BlogPost::factory()->create();

    $disk = $post->thumbnailDisk();
    Storage::fake($disk);

    Storage::disk($disk)->put('blog/unrelated-stays.jpg', 'dummy');
    Storage::disk($disk)->assertExists('blog/unrelated-stays.jpg');

    $post->delete();

    Storage::disk($disk)->assertExists('blog/unrelated-stays.jpg');
});
