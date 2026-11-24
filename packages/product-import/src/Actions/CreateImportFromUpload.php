<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\Admin\Models\Staff;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\ReadImportFile;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Reading\CsvPreparer;

/**
 * Validates the uploaded files, stores them on the private import disk,
 * creates one import holding all of them, and queues reading them.
 */
class CreateImportFromUpload
{
    public function __construct(
        protected AiAvailability $ai,
        protected CsvPreparer $csvPreparer,
    ) {}

    /**
     * The accepted file extensions.
     *
     * @var list<string>
     */
    public const EXTENSIONS = ['xlsx', 'xls', 'csv'];

    /**
     * @param  UploadedFile|list<UploadedFile>  $files  The files, in upload order.
     * @param  ?string  $encoding  The encoding of every CSV file, one of `CsvPreparer::ENCODINGS`. Null means UTF-8. Ignored for other formats.
     *
     * @throws ValidationException
     */
    public function handle(UploadedFile|array $files, ?Staff $staff = null, ?string $encoding = null, string $field = 'file'): ProductImport
    {
        $files = array_values(Arr::wrap($files));

        $encodings = $this->validate($files, $encoding, $field);
        $disk = (string) config('lunar.product-import.disk', 'local');

        $import = DB::transaction(function () use ($files, $encodings, $disk, $staff): ProductImport {
            $import = ProductImport::query()->create([
                'staff_id' => $staff?->getKey(),
                'status' => ImportStatus::Uploaded,
            ]);

            foreach ($files as $position => $file) {
                $path = $file->storeAs('product-imports', Str::uuid()->toString().'.'.$this->extension($file), ['disk' => $disk]);

                $import->files()->create([
                    'position' => $position,
                    'original_name' => $file->getClientOriginalName(),
                    'disk' => $disk,
                    'path' => $path,
                    'size' => $file->getSize(),
                    'encoding' => $encodings[$position],
                ]);
            }

            return $import;
        });

        ReadImportFile::dispatch($import->id);

        return $import;
    }

    /**
     * Reject the whole upload when a file has another format, is above the
     * size limit, or is a CSV file that is not UTF-8 while no other encoding
     * was chosen, naming that file, and reject an unknown encoding. Returns
     * the encoding to store per file: its listed spelling for a CSV file, and
     * null for other formats.
     *
     * @param  UploadedFile|list<UploadedFile>  $files
     * @return list<?string>
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile|array $files, ?string $encoding = null, string $field = 'file'): array
    {
        $files = array_values(Arr::wrap($files));

        if (! $this->ai->isConfigured()) {
            throw ValidationException::withMessages([
                $field => __('lunarpanel.product-import::errors.ai_not_configured'),
            ]);
        }

        if ($files === []) {
            throw ValidationException::withMessages([
                $field => __('lunarpanel.product-import::errors.no_files'),
            ]);
        }

        $chosen = CsvPreparer::canonicalEncoding($encoding);

        if ($encoding !== null && Str::trim($encoding) !== '' && $chosen === null) {
            throw ValidationException::withMessages([
                'encoding' => __('lunarpanel.product-import::errors.unknown_encoding', [
                    'encodings' => implode(', ', CsvPreparer::ENCODINGS),
                ]),
            ]);
        }

        $maxKilobytes = (int) config('lunar.product-import.max_file_size_kb', 20480);

        return array_map(function (UploadedFile $file) use ($field, $maxKilobytes, $chosen): ?string {
            $name = $file->getClientOriginalName();

            if (! in_array($this->extension($file), self::EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    $field => __('lunarpanel.product-import::errors.unsupported_format', ['file' => $name]),
                ]);
            }

            if ($file->getSize() > $maxKilobytes * 1024) {
                throw ValidationException::withMessages([
                    $field => __('lunarpanel.product-import::errors.file_too_large', [
                        'file' => $name,
                        'size' => Number::format($maxKilobytes / 1024, maxPrecision: 1),
                    ]),
                ]);
            }

            if ($this->extension($file) !== 'csv') {
                return null;
            }

            if (($chosen === null || $chosen === 'UTF-8') && ! $this->csvPreparer->isUtf8((string) $file->get())) {
                throw ValidationException::withMessages([
                    'encoding' => __('lunarpanel.product-import::errors.csv_encoding_required', ['file' => $name]),
                ]);
            }

            return $chosen;
        }, $files);
    }

    /**
     * The lowercase extension of the original file name.
     */
    protected function extension(UploadedFile $file): string
    {
        return Str::lower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
    }
}
