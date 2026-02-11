<?php

namespace Lunar\Blog\Database\Seeders;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Language;

class BlogSeeder extends AbstractSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $details = $this->getSeedData('blog/detailsAttributes');
        $authorInformation = $this->getSeedData('blog/authorInformationAttributes');
        $seo = $this->getSeedData('blog/seoAttributes');

        DB::transaction(function () use ($details, $seo, $authorInformation) {
            $this->createAttributeGroupsWithAttributes($details);

            $this->createAttributeGroupsWithAttributes($authorInformation);

            $this->createAttributeGroupsWithAttributes($seo);
        });
    }

    /**
     * Create attribute groups with attributes.
     */
    private function createAttributeGroupsWithAttributes(Collection $groups): void
    {
        $languages = Language::all()->pluck('code');

        foreach ($groups as $group) {
            $attributeGroup = AttributeGroup::create([
                'attributable_type' => $group->attributable_type,
                'name' => $this->generateLocalizedField($languages, $group->name),
                'handle' => $group->handle,
                'position' => $group->position,
            ]);

            foreach ($group->attributes as $attribute) {
                $attribute = Attribute::create([
                    'attribute_group_id' => $attributeGroup->id,
                    'attribute_type' => $attribute->attribute_type,
                    'handle' => $attribute->handle,
                    'section' => $attribute->section,
                    'type' => $attribute->type,
                    'required' => $attribute->required ?? false,
                    'searchable' => $attribute->searchable ?? false,
                    'filterable' => $attribute->filterable ?? false,
                    'system' => $attribute->system ?? false,
                    'position' => $attribute->position,
                    'name' => $this->generateLocalizedField($languages, $attribute->name),
                    'description' => $this->generateLocalizedField($languages, $attribute->description),
                    'configuration' => (array) $attribute->configuration,
                ]);
            }
        }
    }

    /**
     * Generate localized fields dynamically.
     */
    private function generateLocalizedField(Collection $languages, object $fieldData): array
    {
        return $languages->mapWithKeys(function ($language) use ($fieldData) {
            return [
                $language => $fieldData->$language ?? $fieldData->en,
            ];
        })->toArray();
    }
}
