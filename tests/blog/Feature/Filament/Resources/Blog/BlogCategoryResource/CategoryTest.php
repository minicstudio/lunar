<?php

uses(\Lunar\Tests\Blog\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Livewire\Livewire;
use Lunar\Blog\Filament\Resources\BlogCategoryResource;
use Lunar\Blog\Filament\Resources\BlogCategoryResource\Pages\ListBlogCategories;
use Lunar\Blog\Models\BlogCategory;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Language;

beforeEach(function () {
    $this->createLanguages();
});

test('can render index page', function () {
    $this->asStaff(admin: true)
        ->get(BlogCategoryResource::getUrl('index'))
        ->assertSuccessful();
});

test('can render availability sub page', function () {
    $category = BlogCategory::factory()->create();

    $this->asStaff(admin: true)
        ->get(BlogCategoryResource::getUrl('availability', [
            'record' => $category->id,
        ]))
        ->assertSuccessful();
});

test('can create blog category', function () {
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

    $defaultLanguage = Language::where('default', true)->first();

    $nonDefaultLanguage = Language::where('default', false)->first();

    $this->asStaff();

    Livewire::test(ListBlogCategories::class)
        ->callAction('create', data: [
            'name' => [
                $defaultLanguage->code => 'Example Example',
                $nonDefaultLanguage->code => 'Example',
            ],
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas((new BlogCategory)->getTable(), [
        'status' => 'draft',
        'attribute_data' => json_encode([
            'name' => [
                'field_type' => TranslatedText::class,
                'value' => [
                    $defaultLanguage->code => 'Example Example',
                    $nonDefaultLanguage->code => 'Example',
                ],
            ],
        ]),
    ]);
});
