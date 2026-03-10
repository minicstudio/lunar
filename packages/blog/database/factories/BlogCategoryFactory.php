<?php

namespace Lunar\Blog\Database\Factories;

use Lunar\Blog\Models\BlogCategory;
use Lunar\Database\Factories\BaseFactory;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Language;

class BlogCategoryFactory extends BaseFactory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        $defaultLanguage = Language::where('default', true)->first();

        $nonDefaultLanguage = Language::where('default', false)->first();

        $languages = collect([$defaultLanguage, $nonDefaultLanguage]);

        $translatedTitles = $languages->mapWithKeys(function ($language) {
            return [
                $language->code => $this->faker->name,
            ];
        });

        return [
            'status' => 'published',
            'attribute_data' => collect([
                'name' => new TranslatedText($translatedTitles),
            ]),
        ];
    }
}
