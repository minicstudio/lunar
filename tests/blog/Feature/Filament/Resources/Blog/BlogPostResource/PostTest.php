<?php

uses(\Lunar\Tests\Blog\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Livewire\Livewire;
use Lunar\Blog\Filament\Resources\BlogPostResource;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use Lunar\Blog\Models\BlogPost;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Language;

beforeEach(function () {
    $this->createLanguages();
});

test('can render index page', function () {
    $this->asStaff(admin: true)
        ->get(BlogPostResource::getUrl('index'))
        ->assertSuccessful();
});

test('can render availability sub page', function () {
    $category = BlogPost::factory()->create();

    $this->asStaff(admin: true)
        ->get(BlogPostResource::getUrl('availability', [
            'record' => $category->id,
        ]))
        ->assertSuccessful();
});

test('can render urls sub page', function () {
    $category = BlogPost::factory()->create();

    $this->asStaff(admin: true)
        ->get(BlogPostResource::getUrl('urls', [
            'record' => $category->id,
        ]))
        ->assertSuccessful();
});

test('can create blog post', function () {
    Attribute::factory()->create([
        'attribute_type' => 'blog_category',
        'type' => TranslatedText::class,
        'handle' => 'name',
        'name' => [
            'en' => 'Name',
        ],
        'description' => [
            'en' => 'Description',
        ],
    ]);

    Attribute::factory()->create([
        'attribute_type' => 'blog_post',
        'type' => TranslatedText::class,
        'handle' => 'title',
        'name' => [
            'en' => 'Title',
        ],
        'description' => [
            'en' => 'Description',
        ],
    ]);

    $defaultLanguage = Language::where('default', true)->first();

    $nonDefaultLanguage = Language::where('default', false)->first();

    $this->asStaff();

    Livewire::test(ListBlogPosts::class)
        ->callAction('create', data: [
            'title' => [
                $defaultLanguage->code => 'Example Example',
                $nonDefaultLanguage->code => 'Example',
            ],
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas((new BlogPost)->getTable(), [
        'status' => 'draft',
        'attribute_data' => json_encode([
            'title' => [
                'field_type' => TranslatedText::class,
                'value' => [
                    $defaultLanguage->code => 'Example Example',
                    $nonDefaultLanguage->code => 'Example',
                ],
            ],
        ]),
    ]);
});