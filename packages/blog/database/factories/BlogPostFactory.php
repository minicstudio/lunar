<?php

namespace Lunar\Blog\Database\Factories;

use Lunar\Admin\Models\Staff;
use Lunar\Blog\Models\BlogPost;
use Lunar\Database\Factories\BaseFactory;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Language;

class BlogPostFactory extends BaseFactory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $defaultLanguage = Language::where('code', 'en')->first();

        $nonDefaultLanguage = Language::where('default', false)->first();

        $languages = collect([$defaultLanguage, $nonDefaultLanguage]);

        $translatedTitles = $languages->mapWithKeys(function ($language) {
            return [
                $language->code => $this->faker->name,
            ];
        });

        $translatedContents = $languages->mapWithKeys(function ($language) {
            return [
                $language->code => $this->faker->sentence,
            ];
        });

        $author = Staff::factory()->create();

        return [
            'status' => 'published',
            'author_id' => $author->id,
            'attribute_data' => collect([
                'title' => new TranslatedText($translatedTitles),
                'content' => new TranslatedText($translatedContents),
            ]),
        ];
    }
}
