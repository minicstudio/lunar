<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Lunar\Base\MediaFileName;
use Lunar\Base\MediaFileNamer;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Collection as ProductCollection;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Review\Models\Review;
use Lunar\Tests\Core\TestCase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(TestCase::class);
uses(RefreshDatabase::class);

function makeMediaForFileName(int $id, string $fileName, ?string $name = null): Media
{
    $media = new Media([
        'file_name' => $fileName,
        'custom_properties' => $name === null ? [] : ['name' => $name],
    ]);

    $media->id = $id;

    return $media;
}

it('slugifies accented and special characters', function (string $name, string $expected) {
    expect(MediaFileName::slug($name))->toBe($expected);
})->with([
    ['Kép Fájl ŐŰ', 'kep-fajl-ou'],
    ['Mărțișor ȘȚ ăîâ', 'martisor-st-aia'],
    ['Größe & Farbe 100%', 'grosse-farbe-100'],
    ['IMG_2024 (1)', 'img-2024-1'],
]);

it('falls back to a default name when nothing survives slugification', function (string $name) {
    expect(MediaFileName::slug($name))->toBe(MediaFileName::FALLBACK_NAME);
})->with(['日本語', '😀', '---', '']);

it('limits the slug length without a trailing hyphen', function () {
    expect(MediaFileName::slug(str_repeat('a', 150)))->toBe(str_repeat('a', MediaFileName::MAX_LENGTH))
        ->and(MediaFileName::slug(str_repeat('a', 99).' bc'))->toBe(str_repeat('a', 99));
});

it('builds the file name from the preferred name', function () {
    expect(MediaFileName::make('IMG_1234.JPG', 'Piros nyári ruha'))->toBe('piros-nyari-ruha.jpg');
});

it('builds the file name from the original file name without a preferred name', function (?string $preferredName) {
    expect(MediaFileName::make('Kép Fájl.PNG', $preferredName))->toBe('kep-fajl.png');
})->with([null, '', '   ']);

it('builds a random file name that keeps only the extension', function () {
    $fileName = MediaFileName::random('Vásárló fotója.JPG');

    expect($fileName)->toMatch('/^[0-9a-z]{26}\.jpg$/')
        ->and(MediaFileName::random('Vásárló fotója.JPG'))->not->toBe($fileName);
});

it('builds the file name of a media item from its name custom property', function () {
    expect(MediaFileName::forMedia(makeMediaForFileName(1, 'IMG_1234.webp', 'Piros ruha')))->toBe('piros-ruha.webp')
        ->and(MediaFileName::forMedia(makeMediaForFileName(2, 'Kép.webp')))->toBe('kep.webp');
});

it('appends a numeric suffix until the file name is free', function () {
    expect(MediaFileName::unique('kep.webp', []))->toBe('kep.webp')
        ->and(MediaFileName::unique('kep.webp', ['KEP.webp', 'kep-2.webp']))->toBe('kep-3.webp');
});

it('slugifies the requested file name before checking availability', function () {
    expect(MediaFileName::unique('Alma PÁrtos.JPG', []))->toBe('alma-partos.jpg');
});

it('keeps collision suffixes within the slug length limit', function () {
    $baseName = str_repeat('a', MediaFileName::MAX_LENGTH);

    expect(MediaFileName::unique($baseName.'.jpg', [
        $baseName.'.jpg',
        strtoupper($baseName).'-2.JPG',
        strtoupper($baseName).' 3.JPG',
        str_repeat('a', 98).'-2.jpg',
    ]))->toBe(str_repeat('a', 98).'-3.jpg');
});

it('treats names with the same slug as taken', function (string $fileName, array $takenFileNames, string $expected) {
    expect(MediaFileName::unique($fileName, $takenFileNames))->toBe($expected);
})->with([
    ['alma-asta.jpg', ['ALMA ASTA.JPG'], 'alma-asta-2.jpg'],
    ['ALMA ASTA.JPG', ['alma-asta.jpg', 'Alma Ásta 2.JPG'], 'alma-asta-3.jpg'],
    ['kep-fajl.webp', ['Kép Fájl.WEBP'], 'kep-fajl-2.webp'],
    ['alma-asta', ['ALMA ASTA'], 'alma-asta-2'],
    ['alma-asta.jpg', ['ALMA ASTA.PNG'], 'alma-asta.jpg'],
]);

it('lists the file names used by an owner', function () {
    $product = Product::factory()->create();

    $first = $product->addMedia(UploadedFile::fake()->image('first.jpg'))
        ->toMediaCollection(config('lunar.media.collection'));

    $product->addMedia(UploadedFile::fake()->image('second.jpg'))
        ->toMediaCollection(config('lunar.media.collection'));

    expect(MediaFileName::usedBy($product->getMorphClass(), $product->getKey())->all())->toBe(['first.jpg', 'second.jpg'])
        ->and(MediaFileName::usedBy($product->getMorphClass(), $product->getKey(), $first->getKey())->all())->toBe(['second.jpg']);
});

it('plans renames only for media whose file name changes', function () {
    $renames = MediaFileName::planRenames([
        makeMediaForFileName(1, 'Kép Fájl.webp'),
        makeMediaForFileName(2, 'already-clean.webp'),
        makeMediaForFileName(3, 'IMG_1234.JPG', 'Piros ruha'),
    ]);

    expect($renames)->toBe([
        1 => 'kep-fajl.webp',
        3 => 'piros-ruha.jpg',
    ]);
});

it('plans unique file names within a directory', function () {
    $renames = MediaFileName::planRenames([
        makeMediaForFileName(1, 'Kép.webp'),
        makeMediaForFileName(2, 'kep.webp'),
        makeMediaForFileName(3, 'KÉP.webp'),
    ]);

    expect($renames)->toBe([
        1 => 'kep-2.webp',
        3 => 'kep-3.webp',
    ]);
});

it('does not plan a file name that already exists in the directory', function () {
    $renames = MediaFileName::planRenames(
        [makeMediaForFileName(1, 'Kép.webp')],
        ['Kép.webp', 'kep.webp'],
    );

    expect($renames)->toBe([1 => 'kep-2.webp']);
});

it('plans nothing once the file names are in place', function () {
    $mediaItems = [
        makeMediaForFileName(1, 'Kép.webp'),
        makeMediaForFileName(2, 'kep.webp'),
        makeMediaForFileName(3, 'IMG_1.webp', 'Kép'),
    ];

    foreach (MediaFileName::planRenames($mediaItems) as $id => $fileName) {
        collect($mediaItems)->firstWhere('id', $id)->file_name = $fileName;
    }

    expect(MediaFileName::planRenames($mediaItems))->toBe([]);
});

it('slugifies the original file name through the file namer', function () {
    expect((new MediaFileNamer)->originalFileName('Kép Fájl.JPG'))->toBe('kep-fajl');

    config(['media-library.file_namer' => MediaFileNamer::class]);

    $media = Product::factory()->create()
        ->addMedia(UploadedFile::fake()->image('Kép Fájl.jpg'))
        ->toMediaCollection(config('lunar.media.collection'));

    expect($media->file_name)->toBe('kep-fajl.jpg');
});

it('uses catalog owner names for unnamed media', function (string $modelClass) {
    $owner = new $modelClass;

    if ($owner instanceof Brand) {
        $owner->name = 'Őszi kollekció';
    } else {
        $owner->attribute_data = collect(['name' => new TranslatedText(['en' => 'Őszi kollekció'])]);
    }

    $media = makeMediaForFileName(1, 'IMG_1234.JPG');
    $media->setRelation('model', $owner);

    expect(MediaFileName::forMedia($media))->toBe('oszi-kollekcio.jpg')
        ->and(MediaFileName::altText($owner))->toBe('Őszi kollekció')
        ->and(MediaFileName::altText($owner, 'Egyedi kép'))->toBe('Egyedi kép');

    $media->setCustomProperty('name', 'Egyedi kép');

    expect(MediaFileName::forMedia($media))->toBe('egyedi-kep.jpg');
})->with([Product::class, ProductCollection::class, Brand::class]);

it('uses the default language for catalog media regardless of the active locale', function (string $modelClass) {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    app()->setLocale('en');

    $owner = new $modelClass([
        'attribute_data' => collect(['name' => new TranslatedText([
            'en' => 'Summer dress',
            'ro' => 'Rochie de vară',
        ])]),
    ]);
    $media = makeMediaForFileName(1, 'IMG_1234.JPG');
    $media->setRelation('model', $owner);

    expect(MediaFileName::altText($owner))->toBe('Rochie de vară')
        ->and(MediaFileName::forMedia($media))->toBe('rochie-de-vara.jpg')
        ->and(MediaFileName::altText($owner, 'Custom image'))->toBe('Custom image')
        ->and(MediaFileName::forOwner('image.jpg', $owner, 'Custom image'))->toBe('custom-image.jpg');
})->with([[Product::class], [ProductCollection::class]]);

it('keeps translation fallback when the default language name is missing or empty', function (string $modelClass, array $translations) {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    app()->setLocale('en');

    $owner = new $modelClass([
        'attribute_data' => collect(['name' => new TranslatedText($translations)]),
    ]);

    expect(MediaFileName::altText($owner))->toBe('Nyári ruha')
        ->and(MediaFileName::forOwner('image.jpg', $owner))->toBe('nyari-ruha.jpg');
})->with([[Product::class], [ProductCollection::class]])->with([
    'missing' => [['hu' => 'Nyári ruha', 'en' => 'Summer dress']],
    'empty' => [['ro' => '', 'hu' => 'Nyári ruha', 'en' => 'Summer dress']],
]);

it('uses the default language for review image subjects and titles', function (bool $channelReview) {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    app()->setLocale('en');

    $review = new Review(['attribute_data' => collect(['title' => new TranslatedText([
        'en' => 'Great choice',
        'ro' => 'Alegere excelentă',
    ])])]);

    if ($channelReview) {
        $review->setRelation('reviewable', new Channel(['name' => 'Magazin']));
        $expectedName = 'Magazin';
    } else {
        $variant = new ProductVariant;
        $variant->setRelation('product', new Product(['attribute_data' => collect(['name' => new TranslatedText([
            'en' => 'Summer dress',
            'ro' => 'Rochie de vară',
        ])])]));
        $review->setRelation('reviewable', $variant);
        $expectedName = 'Rochie de vară';
    }

    $media = makeMediaForFileName(42, 'image.jpg');
    $media->uuid = '12345678-1234-4234-8234-123456789012';
    $media->setRelation('model', $review);

    expect(MediaFileName::altText($review))->toBe($expectedName.' - Alegere excelentă')
        ->and(MediaFileName::forMedia($media))->toBe(MediaFileName::slug($expectedName).'-'.$media->uuid.'.jpg');
})->with([false, true]);

it('preserves collision suffixes when planning repeated renames', function (string $name, int $suffix) {
    $owner = new Brand(['name' => $name]);
    $fileName = MediaFileName::unique(MediaFileName::make('image.jpg', $name), collect(range(1, $suffix - 1))->map(
        fn (int $number) => $number === 1
            ? MediaFileName::slug($name).'.jpg'
            : substr(MediaFileName::slug($name), 0, MediaFileName::MAX_LENGTH - strlen((string) $number) - 1).'-'.$number.'.jpg'
    ));
    $media = makeMediaForFileName(1, $fileName);
    $media->setRelation('model', $owner);

    expect(MediaFileName::forMedia($media))->toBe($fileName)
        ->and(MediaFileName::planRenames([$media]))->toBe([])
        ->and((new MediaFileNamer)->originalFileName($fileName))->toBe(pathinfo($fileName, PATHINFO_FILENAME));
})->with([['Nyári ruha', 2], ['Nyári ruha', 10], [str_repeat('a', 150), 2], [str_repeat('a', 150), 10]]);

it('names review images with the subject and a stable unique identifier', function (bool $channelReview) {
    $review = new Review(['attribute_data' => collect(['title' => new TranslatedText(['en' => 'Remek választás'])])]);

    if ($channelReview) {
        $review->setRelation('reviewable', new Channel(['name' => 'Webáruház']));
        $expectedName = 'Webáruház';
    } else {
        $variant = new ProductVariant;
        $variant->setRelation('product', new Product(['attribute_data' => collect(['name' => new TranslatedText(['en' => 'Nyári ruha'])])]));
        $review->setRelation('reviewable', $variant);
        $expectedName = 'Nyári ruha';
    }

    $media = makeMediaForFileName(42, 'customer-private-name.JPG', 'Review image');
    $media->uuid = '12345678-1234-4234-8234-123456789012';
    $media->setRelation('model', $review);

    expect(MediaFileName::forMedia($media))->toBe(MediaFileName::slug($expectedName).'-'.$media->uuid.'.jpg')
        ->and(MediaFileName::altText($review, 'Review image'))->toBe($expectedName.' - Remek választás');

    $media->file_name = MediaFileName::forMedia($media);
    expect(MediaFileName::planRenames([$media]))->toBe([]);
})->with([false, true]);
