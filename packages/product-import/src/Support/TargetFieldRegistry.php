<?php

namespace Lunar\ProductImport\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Number;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\Toggle;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Enums\TargetField;

/**
 * The targets a column may map to for a product type: the fixed product and
 * variant fields, the type's text, translated text, number, and toggle
 * attributes, plus the store's languages and product options. Attribute
 * targets are labelled with their attribute group ("Search Engine
 * Optimization › Title"), so an attribute named like a fixed target or like
 * an attribute of another group can be told apart.
 */
class TargetFieldRegistry
{
    /**
     * The attribute field types that can be imported, and whether they take a language.
     *
     * @var array<class-string, bool>
     */
    public const ATTRIBUTE_TYPES = [
        Text::class => false,
        TranslatedText::class => true,
        Number::class => false,
        Toggle::class => false,
    ];

    /**
     * Attribute handles covered by fixed targets.
     *
     * @var list<string>
     */
    protected const CORE_ATTRIBUTE_HANDLES = ['name', 'description'];

    /**
     * Targets per product type, built once per request.
     *
     * @var array<int, list<TargetDefinition>>
     */
    protected array $targets = [];

    /**
     * Every target for the product type, fixed targets first.
     *
     * @return list<TargetDefinition>
     */
    public function targets(?int $productTypeId): array
    {
        return $this->targets[$productTypeId ?? 0] ??= [...$this->coreTargets(), ...$this->attributeTargets($productTypeId)];
    }

    /**
     * The target codes the AI may answer with, including `ignore`.
     *
     * @return list<string>
     */
    public function targetCodes(?int $productTypeId): array
    {
        return array_map(fn (TargetDefinition $target): string => $target->target, $this->targets($productTypeId));
    }

    /**
     * The definition of a target, or null when the product type does not
     * offer it.
     */
    public function find(string $target, ?int $productTypeId): ?TargetDefinition
    {
        foreach ($this->targets($productTypeId) as $definition) {
            if ($definition->target === $target) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Whether the product type offers the target.
     */
    public function exists(string $target, ?int $productTypeId): bool
    {
        return $this->find($target, $productTypeId) !== null;
    }

    /**
     * Whether the target takes a store language.
     */
    public function takesLocale(string $target, ?int $productTypeId): bool
    {
        return $this->find($target, $productTypeId)?->takesLocale ?? false;
    }

    /**
     * The format a column mapped to the target needs: `number`, `date`, or
     * null.
     */
    public function format(string $target, ?int $productTypeId): ?string
    {
        return $this->find($target, $productTypeId)?->format;
    }

    /**
     * The label of a target, or the target code when it is unknown.
     */
    public function label(string $target, ?int $productTypeId = null): string
    {
        return $this->find($target, $productTypeId)?->label
            ?? (TargetField::tryFrom($target) ? $this->coreLabel(TargetField::from($target)) : $target);
    }

    /**
     * The store's language codes, the default language first.
     *
     * @return list<string>
     */
    public function languageCodes(): array
    {
        return Language::query()
            ->orderByDesc('default')
            ->orderBy('id')
            ->pluck('code')
            ->all();
    }

    /**
     * The default language code.
     */
    public function defaultLanguage(): string
    {
        return Language::getDefault()?->code ?? (string) config('app.locale', 'en');
    }

    /**
     * The existing product options, id => translated name.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        return ProductOption::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProductOption $option): array => [$option->id => (string) $option->translate('name')])
            ->all();
    }

    /**
     * The import-ready attributes of a product type.
     *
     * @return Collection<int, Attribute>
     */
    public function attributes(?int $productTypeId): Collection
    {
        if ($productTypeId === null) {
            return collect();
        }

        $productType = ProductType::query()->find($productTypeId);

        if (! $productType) {
            return collect();
        }

        return $productType->productAttributes()
            ->with('attributeGroup')
            ->whereIn('type', array_keys(self::ATTRIBUTE_TYPES))
            ->whereNotIn('handle', self::CORE_ATTRIBUTE_HANDLES)
            ->orderBy('position')
            ->get();
    }

    /**
     * The text and translated text attributes of a product type, which
     * properties from cells may fill.
     *
     * @return Collection<int, Attribute>
     */
    public function textAttributes(?int $productTypeId): Collection
    {
        return $this->attributes($productTypeId)
            ->filter(fn (Attribute $attribute): bool => in_array($attribute->type, [Text::class, TranslatedText::class], true))
            ->values();
    }

    /**
     * Forget what was built, after the catalog changed.
     */
    public function flush(): void
    {
        $this->targets = [];
    }

    /**
     * The fixed targets.
     *
     * @return list<TargetDefinition>
     */
    protected function coreTargets(): array
    {
        return array_map(
            fn (TargetField $field): TargetDefinition => new TargetDefinition(
                $field->value,
                $this->coreLabel($field),
                $field->takesLocale(),
                format: $field->format(),
            ),
            TargetField::cases(),
        );
    }

    /**
     * The attribute targets of a product type.
     *
     * @return list<TargetDefinition>
     */
    protected function attributeTargets(?int $productTypeId): array
    {
        return $this->attributes($productTypeId)
            ->map(fn (Attribute $attribute): TargetDefinition => new TargetDefinition(
                TargetField::ATTRIBUTE_PREFIX.$attribute->handle,
                $this->attributeLabel($attribute),
                self::ATTRIBUTE_TYPES[$attribute->type] ?? false,
                TargetDefinition::KIND_ATTRIBUTE,
                $attribute->type === Number::class ? TargetDefinition::FORMAT_NUMBER : null,
            ))
            ->values()
            ->all();
    }

    /**
     * An attribute's name after its attribute group's name, such as "Search
     * Engine Optimization › Title", or its name alone without a group.
     */
    protected function attributeLabel(Attribute $attribute): string
    {
        $name = (string) ($attribute->translate('name') ?: $attribute->handle);
        $group = (string) ($attribute->attributeGroup?->translate('name') ?: '');

        return $group === '' ? $name : $group.' › '.$name;
    }

    /**
     * The translated label of a fixed target. The price label names the
     * default currency and whether prices are stored with tax.
     */
    protected function coreLabel(TargetField $field): string
    {
        $key = 'lunarpanel.product-import::targets.'.Str::replace('.', '_', $field->value);

        return match ($field) {
            TargetField::VariantPrice => __(
                config('lunar.pricing.stored_inclusive_of_tax', false) ? $key.'_inclusive' : $key.'_exclusive',
                ['currency' => Currency::getDefault()?->code ?? ''],
            ),
            default => __($key),
        };
    }
}
