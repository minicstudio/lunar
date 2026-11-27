<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\CorrectImageUrl;
use Lunar\ProductImport\Actions\RetryFailedImages;
use Lunar\ProductImport\Actions\StartImport;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\HostResolver;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();

    Storage::fake('public');
    Config::set('media-library.disk_name', 'public');

    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function addresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    Http::fake([
        'https://img.example.com/missing.png' => Http::response('', 404),
        'https://img.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    // "Rochie vară" has the image that answers 404.
    $this->import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Imagini'],
        ['TB-1', 'Tricou', '49', 'https://img.example.com/tricou.png'],
        ['RV-1', 'Rochie vară', '199', 'https://img.example.com/missing.png'],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.images'], $this->productType->id);
    ImportBuilder::stage($this->import);
    $this->import->update(['status' => ImportStatus::PreviewReady]);

    app(StartImport::class)->handle($this->import);

    $this->import->refresh();
    $this->failed = ProductImportImage::query()->where('status', ImageStatus::Failed)->sole();
});

function dressProduct(): Product
{
    return ProductVariant::query()->where('sku', 'RV-1')->sole()->product;
}

test('a 404 image corrected to a working url is downloaded on retry and the completed import stays completed', function () {
    expect($this->import->status)->toBe(ImportStatus::Completed)
        ->and($this->import->stats['images_failed'])->toBe(1)
        ->and(dressProduct()->getMedia('images'))->toHaveCount(0);

    app(CorrectImageUrl::class)->handle($this->failed, 'https://img.example.com/rochie.png');
    app(RetryFailedImages::class)->handle($this->import);

    $import = $this->import->fresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->stats['images_failed'])->toBe(0)
        ->and($import->stats['images_downloaded'])->toBe(2)
        ->and($import->finished_at)->not->toBeNull()
        ->and($this->failed->fresh()->status)->toBe(ImageStatus::Downloaded)
        ->and(dressProduct()->getMedia('images'))->toHaveCount(1)
        ->and(dressProduct()->getFirstMedia('images')->getCustomProperty('source_url'))->toBe('https://img.example.com/rochie.png');
});

test('without a correction the retry tries every failed image again and writes no product, variant, or row', function () {
    $before = [
        'products' => Product::query()->orderBy('id')->pluck('updated_at')->map->toDateTimeString()->all(),
        'variants' => ProductVariant::query()->orderBy('id')->pluck('updated_at')->map->toDateTimeString()->all(),
        'rows' => ProductImportRow::query()->orderBy('id')->get(['status', 'updated_at', 'messages'])->toArray(),
    ];

    $this->travel(5)->minutes();

    app(RetryFailedImages::class)->handle($this->import);

    $import = $this->import->fresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->stats['images_failed'])->toBe(1)
        ->and($this->failed->fresh()->status)->toBe(ImageStatus::Failed)
        ->and(Http::recorded(fn (Request $request) => $request->url() === 'https://img.example.com/missing.png'))->toHaveCount(2)
        ->and(Http::recorded(fn (Request $request) => $request->url() === 'https://img.example.com/tricou.png'))->toHaveCount(1)
        ->and(Product::query()->orderBy('id')->pluck('updated_at')->map->toDateTimeString()->all())->toBe($before['products'])
        ->and(ProductVariant::query()->orderBy('id')->pluck('updated_at')->map->toDateTimeString()->all())->toBe($before['variants'])
        ->and(ProductImportRow::query()->orderBy('id')->get(['status', 'updated_at', 'messages'])->toArray())->toBe($before['rows']);
});

test('a correction that is not an http or https url is refused and the raw cell is unchanged', function () {
    expect(fn () => app(CorrectImageUrl::class)->handle($this->failed, 'ftp://cdn.example/1.jpg'))
        ->toThrow(ValidationException::class);

    expect($this->failed->fresh()->url)->toBe('https://img.example.com/missing.png')
        ->and($this->failed->row->raw[3])->toBe('https://img.example.com/missing.png');

    app(CorrectImageUrl::class)->handle($this->failed, 'https://img.example.com/rochie.png');

    expect($this->failed->fresh()->url)->toBe('https://img.example.com/rochie.png')
        ->and($this->failed->row->fresh()->raw[3])->toBe('https://img.example.com/missing.png');
});

test('a pruned import refuses the correction and the retry', function () {
    $this->import->update(['pruned_at' => now()]);

    expect(fn () => app(CorrectImageUrl::class)->handle($this->failed, 'https://img.example.com/rochie.png'))
        ->toThrow(ImportActionRefused::class, 'The row data of this import was removed.')
        ->and(fn () => app(RetryFailedImages::class)->handle($this->import))
        ->toThrow(ImportActionRefused::class, 'The row data of this import was removed.');

    expect($this->import->fresh()->status)->toBe(ImportStatus::Completed);
});

test('an import without failed images refuses the retry', function () {
    $this->failed->update(['status' => ImageStatus::Downloaded]);

    expect(fn () => app(RetryFailedImages::class)->handle($this->import))
        ->toThrow(ImportActionRefused::class, 'There are no failed images to retry.');

    expect(app(RetryFailedImages::class)->canRetry($this->import))->toBeFalse();
});

test('the retry is refused while another import is importing', function () {
    ProductImport::factory()->create(['status' => ImportStatus::Importing]);

    expect(fn () => app(RetryFailedImages::class)->handle($this->import))
        ->toThrow(ImportActionRefused::class, 'Another import is running. Try again when it has finished.');

    expect($this->import->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($this->failed->fresh()->status)->toBe(ImageStatus::Failed);
});
