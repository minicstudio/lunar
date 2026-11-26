<?php

namespace Lunar\ProductImport\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Currency;
use Lunar\ProductImport\Actions\CorrectRow;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\CellWriter;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * The row correction modal: one field per mapped column, with the original
 * cell next to it. Corrections are stored apart from the raw cells.
 */
class CorrectRowAction
{
    public static function make(string $name = 'correctRow'): Action
    {
        return Action::make($name)
            ->label(__('lunarpanel.product-import::ui.preview.correct'))
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(fn (ProductImportRow $record): string => __('lunarpanel.product-import::ui.preview.correct_heading', [
                'sheet' => $record->sheet->name,
                'row' => $record->row_number,
            ]))
            ->fillForm(fn (ProductImportRow $record): array => ['fields' => static::currentValues($record)])
            ->schema(fn (ProductImportRow $record): array => static::fields($record))
            ->action(function (array $data, ProductImportRow $record, Action $action): void {
                $fields = [];

                foreach ($data['fields'] ?? [] as $key => $value) {
                    $fields[static::decode($key)] = $value;
                }

                try {
                    app(CorrectRow::class)->handle($record, $fields);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();
                    $action->halt();
                } catch (ImportActionRefused $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }
            });
    }

    /**
     * One text field per mapped column of the row's sheet.
     *
     * @return list<TextInput>
     */
    public static function fields(ProductImportRow $row): array
    {
        $validator = app(ImportValidator::class);

        return collect($row->sheet->sheetMapping()->mappedColumns())
            ->reject(fn (ColumnMapping $column): bool => $column->is(TargetField::GroupKey))
            ->map(function (ColumnMapping $column) use ($row, $validator): ?TextInput {
                $field = $column->fieldPath();

                if ($field === null) {
                    return null;
                }

                $raw = $row->raw[$column->index] ?? null;

                return TextInput::make('fields.'.static::encode($field))
                    ->label($column->header.' · '.$validator->fieldLabel($field))
                    ->helperText(filled($raw)
                        ? __('lunarpanel.product-import::ui.preview.original_cell', ['value' => $raw])
                        : __('lunarpanel.product-import::ui.preview.original_cell_empty'));
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The corrections already entered, for the form, written the way their
     * column reads them, so that saving the form again stores the same
     * values.
     *
     * @return array<string, string>
     */
    public static function currentValues(ProductImportRow $row): array
    {
        $values = [];
        $mapping = $row->sheet->sheetMapping();
        $factor = (int) (Currency::getDefault()?->factor ?? 100);
        $writer = app(CellWriter::class);

        foreach ($row->overrides ?? [] as $field => $value) {
            $column = $mapping->columnForField($field);

            $values[static::encode($field)] = match (true) {
                $column !== null => $writer->write($column, $value, $factor),
                is_scalar($value) => (string) $value,
                default => '',
            };
        }

        return $values;
    }

    /**
     * A field path as a form key without dots or colons.
     */
    public static function encode(string $field): string
    {
        return Str::replace(['.', ':'], ['__', '--'], $field);
    }

    /**
     * The field path of a form key.
     */
    public static function decode(string $key): string
    {
        return Str::replace(['__', '--'], ['.', ':'], $key);
    }
}
