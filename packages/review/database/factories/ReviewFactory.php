<?php

namespace Lunar\Review\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\FieldTypes\Dropdown;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\Toggle;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Lunar\Review\Models\Review;

class ReviewFactory extends BaseFactory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $defaultLanguage = Language::where('code', 'en')->first();

        $nonDefaultLanguage = Language::where('default', false)->first();

        $languages = collect([$defaultLanguage, $nonDefaultLanguage]);

        $ratingValue = (string) $this->faker->numberBetween(1, 5);

        $translatedComments = $languages->mapWithKeys(function ($language) {
            return [
                $language->code => $this->faker->sentence,
            ];
        });

        $translatedTitles = $languages->mapWithKeys(function ($language) {
            return [
                $language->code => $this->faker->sentence,
            ];
        });

        $availableTypes = config('lunar.review.available_types');
        $modelClass = array_rand($availableTypes);

        if (! class_exists($modelClass)) {
            $modelClass = ProductVariant::class;
        }

        return [
            'order_id' => Order::factory(),
            'user_id' => null,
            'reviewable_type' => $modelClass::morphName(),
            'reviewable_id' => $modelClass::factory(),
            'attribute_data' => collect([
                'title' => new TranslatedText($translatedTitles),
                'full_name' => new Text($this->faker->name()),
                'anonym' => new Toggle(false),
                'rating' => new Dropdown($ratingValue),
                'comment' => new TranslatedText($translatedComments),
            ]),
            'approved_at' => $this->faker->optional()->dateTime,
        ];
    }
}
