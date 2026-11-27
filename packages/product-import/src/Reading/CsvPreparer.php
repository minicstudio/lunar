<?php

namespace Lunar\ProductImport\Reading;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded CSV file into a UTF-8 copy without a byte order mark and
 * detects its delimiter, so the spreadsheet reader sees one clean format.
 */
class CsvPreparer
{
    /**
     * The delimiters that are recognized, in order of preference on a tie.
     *
     * @var list<string>
     */
    public const DELIMITERS = [';', ',', "\t"];

    /**
     * The encodings staff can choose for a CSV file. UTF-8 is the default.
     *
     * @var list<string>
     */
    public const ENCODINGS = ['UTF-8', 'Windows-1250', 'Windows-1252', 'ISO-8859-2', 'ISO-8859-16'];

    /**
     * How many non-empty lines are looked at to detect the delimiter.
     */
    protected const SAMPLE_LINES = 10;

    /**
     * Write the UTF-8 copy and detect its delimiter. Input that is not valid
     * UTF-8 is converted from the given encoding (one of `ENCODINGS`).
     */
    public function prepare(string $path, ?string $encoding = null): PreparedCsv
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("The CSV file [{$path}] cannot be read.");
        }

        [$content, $converted] = $this->toUtf8($content, $encoding);

        $target = tempnam(sys_get_temp_dir(), 'product-import-csv-');

        if ($target === false || file_put_contents($target, $content) === false) {
            throw new RuntimeException('A temporary copy of the CSV file cannot be written.');
        }

        return new PreparedCsv($target, $this->detectDelimiter($content), $converted);
    }

    /**
     * Strip a UTF-8 byte order mark, keep valid UTF-8 as is, and convert
     * other input from the given encoding.
     *
     * @return array{0: string, 1: bool}
     *
     * @throws RuntimeException when the input is not UTF-8 and no other encoding is given.
     */
    public function toUtf8(string $content, ?string $encoding = null): array
    {
        $content = $this->withoutBom($content);

        if (mb_check_encoding($content, 'UTF-8')) {
            return [$content, false];
        }

        $encoding = static::canonicalEncoding($encoding);

        if ($encoding === null || $encoding === 'UTF-8') {
            throw new RuntimeException('The CSV file is not valid UTF-8 and no encoding was chosen for it.');
        }

        $converted = @iconv($encoding, 'UTF-8//IGNORE', $content);

        if ($converted === false) {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return [$converted, true];
    }

    /**
     * Whether the content, without a byte order mark, is valid UTF-8.
     */
    public function isUtf8(string $content): bool
    {
        return mb_check_encoding($this->withoutBom($content), 'UTF-8');
    }

    /**
     * The spelling from `ENCODINGS` of the given encoding, compared without
     * case, or null when it is empty or unknown.
     */
    public static function canonicalEncoding(?string $encoding): ?string
    {
        if ($encoding === null || Str::trim($encoding) === '') {
            return null;
        }

        foreach (self::ENCODINGS as $known) {
            if (strcasecmp($known, Str::trim($encoding)) === 0) {
                return $known;
            }
        }

        return null;
    }

    /**
     * Strip a UTF-8 byte order mark.
     */
    protected function withoutBom(string $content): string
    {
        return Str::chopStart($content, "\xEF\xBB\xBF");
    }

    /**
     * Pick the delimiter that splits the first lines into the most consistent
     * number of fields, respecting quoted values.
     */
    public function detectDelimiter(string $content): string
    {
        $lines = array_slice(
            array_values(array_filter(
                Str::of($content)->split('/\r\n|\n|\r/')->all(),
                fn (string $line): bool => Str::trim($line) !== '',
            )),
            0,
            self::SAMPLE_LINES,
        );

        $best = ',';
        $bestScore = -1.0;

        foreach (self::DELIMITERS as $delimiter) {
            $counts = array_map(
                fn (string $line): int => count(str_getcsv($line, $delimiter, '"', '\\')),
                $lines,
            );

            if ($counts === []) {
                continue;
            }

            $frequencies = array_count_values($counts);
            arsort($frequencies);
            $mode = (int) array_key_first($frequencies);

            if ($mode < 2) {
                continue;
            }

            $score = ($frequencies[$mode] / count($counts)) * 1000 + $mode;

            if ($score > $bestScore) {
                $best = $delimiter;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
