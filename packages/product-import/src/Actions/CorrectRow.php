<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Currency;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\RowNormalizer;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Taxonomy\ValueCollector;
use Lunar\ProductImport\Validation\AffectedGroups;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Stores staff corrections of a row's normalized values in `overrides`,
 * apart from the raw cells, and validates the row's group again. A correction
 * of a discount collects the import's discounts again. A failed row of a
 * finished import can be corrected for a retry.
 */
class CorrectRow
{
    use GuardsImportStatus;

    public function __construct(
        protected RowNormalizer $normalizer,
        protected RowStructurer $structurer,
        protected ImportValidator $validator,
        protected ValueCollector $collector,
    ) {}

    /**
     * @param  array<string, ?string>  $fields  Field path => entered text. An empty text removes the correction.
     *
     * @throws ValidationException
     */
    public function handle(ProductImportRow $row, array $fields): ProductImportRow
    {
        $import = $row->import;
        $afterImport = $row->status === RowStatus::Failed;

        $this->ensureStatus($import, $afterImport
            ? [ImportStatus::CompletedWithErrors, ImportStatus::Failed]
            : [ImportStatus::PreviewReady]);

        if ($import->isPruned()) {
            throw ValidationException::withMessages(['row' => __('lunarpanel.product-import::errors.pruned')]);
        }

        $overrides = $row->overrides ?? [];
        $errors = [];
        $mapping = $row->sheet->sheetMapping();
        $factor = (int) (Currency::getDefault()?->factor ?? 100);

        foreach ($fields as $field => $input) {
            $text = Str::trim((string) $input);

            if ($text === '') {
                unset($overrides[$field]);

                continue;
            }

            $value = $this->parse($mapping, $field, $text, $factor);

            if ($value === null) {
                $errors[$field] = __('lunarpanel.product-import::errors.invalid_correction', [
                    'field' => $this->validator->fieldLabel($field),
                    'value' => $text,
                ]);

                continue;
            }

            $overrides[$field] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $previousMatch = $row->match_value;

        $row->forceFill(['overrides' => $overrides === [] ? null : $overrides])->save();

        $this->structurer->structureGroups($import, [(string) $row->group_key]);

        if (! $afterImport) {
            $this->validator->validateGroups($import, AffectedGroups::for($import, [$row], [$previousMatch]));

            if (array_intersect(array_keys($fields), array_map(fn (TargetField $field): string => $field->value, RowStructurer::DISCOUNT)) !== []) {
                $this->collector->collectDiscounts($import);
            }
        }

        return $row->refresh();
    }

    /**
     * Read an entered value the way staging reads its column's cells, in the
     * column's format and with its separators, or null when it is invalid.
     */
    protected function parse(SheetMapping $mapping, string $field, string $text, int $factor): mixed
    {
        $normalized = $this->normalizer->normalizeField($mapping, $field, $text, $factor);

        if ($normalized === null) {
            return $text;
        }

        if ($normalized->invalid !== []) {
            return null;
        }

        $value = $normalized->get($field);

        if ($value === []) {
            return null;
        }
        $target = TargetField::tryFrom((string) $mapping->columnForField($field)?->target);

        if ((is_int($value) || is_float($value)) && $value < 0 && ! $target?->allowsNegative()) {
            return null;
        }

        return $value;
    }
}
