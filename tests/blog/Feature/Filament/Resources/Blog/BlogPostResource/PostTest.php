<?php

uses(\Lunar\Tests\Blog\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Lunar\Admin\Support\Forms\Components\TranslatedRichEditor;
use Lunar\Blog\Filament\Resources\BlogPostResource;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\EditBlogPost;
use Lunar\Blog\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use Lunar\Blog\Models\BlogPost;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
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

test('can attach files to a translated rich text content attribute', function () {
    $attributeGroup = AttributeGroup::factory()->create([
        'attributable_type' => 'blog_post',
        'name' => [
            'en' => 'Details',
        ],
        'handle' => 'details',
        'position' => 1,
    ]);

    Attribute::factory()->create([
        'attribute_type' => 'blog_post',
        'attribute_group_id' => $attributeGroup->id,
        'type' => TranslatedText::class,
        'handle' => 'content',
        'name' => [
            'en' => 'Content',
        ],
        'description' => [
            'en' => 'Description',
        ],
        'configuration' => [
            'richtext' => true,
        ],
    ]);

    $post = BlogPost::factory()->create();

    $defaultLanguage = Language::where('default', true)->first();

    $attachFilesAction = TestAction::make('attachFiles')->schemaComponent("attributeData.content.{$defaultLanguage->code}", schema: 'form');

    $this->asStaff(admin: true);

    Livewire::test(EditBlogPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSchemaComponentExists(
            "attributeData.content.{$defaultLanguage->code}",
            'form',
            fn ($component): bool => $component instanceof TranslatedRichEditor,
        )
        ->mountAction($attachFilesAction)
        ->assertActionMounted($attachFilesAction);
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
