<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Support\Str;
use Lunar\FieldTypes\Number;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\Toggle;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;

/**
 * Builds attribute field values the way the admin does: the attribute's
 * field type decides whether a value is translated text, text, a number, or
 * a toggle.
 */
class FieldValues
{
    /**
     * Field type class per attribute type and handle, resolved once.
     *
     * @var array<string, ?string>
     */
    protected array $types = [];

    /**
     * The field type of an attribute, or null when it does not exist.
     */
    public function type(string $attributeType, string $handle): ?string
    {
        $key = $attributeType.'|'.$handle;

        if (! array_key_exists($key, $this->types)) {
            $this->types[$key] = Attribute::query()
                ->where('attribute_type', $attributeType)
                ->where('handle', $handle)
                ->value('type');
        }

        return $this->types[$key];
    }

    /**
     * A name for a new record: translated text in the default language, or text.
     */
    public function name(string $attributeType, string $name, string $locale): Text|TranslatedText
    {
        return ($this->type($attributeType, 'name') ?? TranslatedText::class) === Text::class
            ? new Text($name)
            : new TranslatedText(collect([$locale => new Text($name)]));
    }

    /**
     * A field value merged into the current one. Translated text keeps the
     * other languages.
     *
     * @param  array<string, mixed>  $byLocale  Locale ('' for none) => value.
     */
    public function merge(?string $type, mixed $current, array $byLocale): mixed
    {
        $type ??= TranslatedText::class;
        $values = array_filter($byLocale, fn (mixed $value): bool => $value !== null && $value !== '');

        if ($values === []) {
            return $current;
        }

        if ($type === TranslatedText::class) {
            $existing = $current instanceof TranslatedText ? $current->getValue() : collect();

            foreach ($values as $locale => $value) {
                $existing[$locale === '' ? config('app.locale') : $locale] = new Text((string) $value);
            }

            return new TranslatedText($existing);
        }

        $value = reset($values);

        return match ($type) {
            Number::class => new Number(is_numeric(Str::replace(',', '.', (string) $value)) ? (float) Str::replace(',', '.', (string) $value) : 0),
            Toggle::class => new Toggle(in_array((string) Str::of((string) $value)->trim()->lower(), ['1', 'true', 'yes', 'da', 'igen', 'y', 'x'], true)),
            default => new Text((string) $value),
        };
    }
}
