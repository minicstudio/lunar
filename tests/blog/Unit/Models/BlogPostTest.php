<?php

uses(\Lunar\Tests\Blog\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\Admin\Models\Staff;
use Lunar\Blog\Models\BlogCategory;
use Lunar\Blog\Models\BlogPost;
use Lunar\FieldTypes\File;
use Lunar\FieldTypes\Text;
use Lunar\Models\Attribute;

beforeEach(function () {
    $this->createLanguages();
});

test('factory creates a blog post', function () {
    $post = BlogPost::factory()->create();

    expect($post)->toBeInstanceOf(BlogPost::class)
        ->and($post->id)->not->toBeNull();
});

test('author relationship returns the Staff model', function () {
    $staff = Staff::factory()->create();

    $post = BlogPost::factory()->create([
        'author_id' => $staff->id,
    ]);

    expect($post->author)->toBeInstanceOf(Staff::class)
        ->and($post->author->id)->toBe($staff->id);
});

test('thumbnailDisk falls back to default filesystem when no attribute config is found', function () {
    $post = BlogPost::factory()->create();

    expect($post->thumbnailDisk())->toBe(config('filesystems.default'));
});

test('thumbnailDisk hits the config-present branch and returns default when disk cannot be resolved', function () {
    Attribute::factory()->create([
        'attribute_type' => BlogPost::morphName(),
        'handle' => 'thumbnail',
        'configuration' => ['foo' => 'bar'],
    ]);

    $post = BlogPost::factory()->create();

    expect($post->thumbnailDisk())->toBe(config('filesystems.default'));
});

test('getThumbnail returns the first thumbnail path when present', function () {
    $post = BlogPost::factory()->create([
        'attribute_data' => collect([
            'thumbnail' => new File(['blog/example.jpg']),
        ]),
    ]);

    expect($post->getThumbnail())->toBe('blog/example.jpg');
});

test('getThumbnail returns null when no thumbnail is set', function () {
    $post = BlogPost::factory()->create();

    expect($post->getThumbnail())->toBeNull();
});

test('blogCategories relationship attaches and retrieves categories', function () {
    $post = BlogPost::factory()->create();
    $categoryFirst = BlogCategory::factory()->create();
    $categorySecond = BlogCategory::factory()->create();

    $post->blogCategories()->attach([$categoryFirst->id, $categorySecond->id]);

    $loaded = $post->fresh()->blogCategories->pluck('id')->all();

    expect($loaded)->toContain($categoryFirst->id, $categorySecond->id);
});

test('getAuthorFullNameAttribute concatenates author first and last names from attributes', function () {
    $post = BlogPost::factory()->create([
        'attribute_data' => collect([
            'author_first_name' => new Text('John'),
            'author_last_name' => new Text('Doe'),
        ]),
    ]);

    expect($post->author_full_name)->toBe('John Doe');
});
