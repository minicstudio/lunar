<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Lunar\Blog\Generators\UrlGenerator;
use Lunar\Blog\Models\BlogPost;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Language;

beforeEach(function () {
    $this->createLanguages();
    Config::set('lunar.urls.generator', UrlGenerator::class);
});

test('generates urls for each language based on configured attribute', function () {
    $en = Language::where('code', 'en')->first();
    $hu = Language::where('code', 'hu')->first();

    $titles = [
        'en' => 'My First Post',
        'hu' => 'Elso Bejegyzes',
    ];

    $post = BlogPost::factory()->create([
        'attribute_data' => collect([
            'title' => new TranslatedText(collect($titles)),
            'content' => new TranslatedText(collect([
                'en' => 'Content EN',
                'hu' => 'Content HU',
            ])),
        ]),
    ]);

    $post->refresh();

    expect($post->urls)->toHaveCount(2);

    $enUrl = $post->urls->firstWhere('language_id', $en->id);
    $huUrl = $post->urls->firstWhere('language_id', $hu->id);

    expect($enUrl?->slug)->toBe(Str::slug($titles['en']))
        ->and($huUrl?->slug)->toBe(Str::slug($titles['hu']));
});

test('generates unique slugs per language with numeric suffix on duplicates', function () {
    $en = Language::where('code', 'en')->first();
    $hu = Language::where('code', 'hu')->first();

    $titles = [
        'en' => 'Duplicate Title',
        'hu' => 'Ismetlodo Cim',
    ];

    $first = BlogPost::factory()->create([
        'attribute_data' => collect([
            'title' => new TranslatedText(collect($titles)),
            'content' => new TranslatedText(collect([
                'en' => 'A',
                'hu' => 'B',
            ])),
        ]),
    ]);

    $second = BlogPost::factory()->create([
        'attribute_data' => collect([
            'title' => new TranslatedText(collect($titles)),
            'content' => new TranslatedText(collect([
                'en' => 'C',
                'hu' => 'D',
            ])),
        ]),
    ]);

    $first->refresh();
    $second->refresh();

    $enSlug = Str::slug($titles['en']);
    $huSlug = Str::slug($titles['hu']);

    expect($first->urls->firstWhere('language_id', $en->id)?->slug)->toBe($enSlug)
        ->and($first->urls->firstWhere('language_id', $hu->id)?->slug)->toBe($huSlug);

    expect($second->urls->firstWhere('language_id', $en->id)?->slug)->toBe($enSlug.'-2')
        ->and($second->urls->firstWhere('language_id', $hu->id)?->slug)->toBe($huSlug.'-2');
});
