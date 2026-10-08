<?php

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\FieldTypes\Dropdown;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Review\Filament\Resources\OrderResource\RelationManagers\ChannelReviewRelationManager;
use Lunar\Review\Filament\Resources\OrderResource\RelationManagers\ProductVariantReviewRelationManager;
use Lunar\Review\Models\Review;
use Lunar\Tests\Review\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->asStaff(admin: true);
});

test('admin review uploads use the same media names as storefront uploads', function (bool $channelReview, bool $editing) {
    $this->createLanguages();
    $this->createCurrencies();
    app()->setLocale('hu');
    Storage::fake('public');
    config(['lunar.review.upload_disk' => 'public']);

    $order = Order::factory()->create();

    if ($channelReview) {
        $subject = Channel::factory()->create(['name' => 'Webáruház']);
        $order->update(['channel_id' => $subject->id]);
        $relationManager = ChannelReviewRelationManager::class;
        $expectedName = 'Webáruház';
        $expectedSlug = 'webaruhaz';
    } else {
        $product = Product::factory()->create([
            'attribute_data' => collect(['name' => new TranslatedText([
                'en' => 'Summer dress',
                'hu' => 'Nyári ruha',
            ])]),
        ]);
        $subject = ProductVariant::factory()->for($product)->create();
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_id' => $subject->id,
            'purchasable_type' => $subject->getMorphClass(),
        ]);
        $relationManager = ProductVariantReviewRelationManager::class;
        $expectedName = 'Summer dress';
        $expectedSlug = 'summer-dress';
    }

    $attributeGroup = AttributeGroup::factory()->create(['attributable_type' => 'review']);
    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'handle' => 'title',
        'type' => TranslatedText::class,
        'configuration' => ['richtext' => false],
    ]);

    $review = $editing ? Review::factory()->create([
        'order_id' => $order->id,
        'reviewable_type' => $subject->getMorphClass(),
        'reviewable_id' => $subject->id,
    ]) : null;
    $existingMedia = $review?->addMedia(UploadedFile::fake()->image('existing.jpg'))
        ->toMediaCollection('reviews');
    $uploads = [
        UploadedFile::fake()->image('customer-private-name.jpg'),
        UploadedFile::fake()->image('customer-private-name.jpg'),
    ];

    if ($existingMedia) {
        $uploads[$existingMedia->uuid] = $existingMedia->uuid;
    }

    Livewire::test($relationManager, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])->callTableAction($editing ? EditAction::class : CreateAction::class, record: $review, data: [
        'reviewable_id' => $subject->id,
        'attribute_data.title.en' => 'Great choice',
        'attribute_data.title.hu' => 'Remek választás',
        'review' => $uploads,
    ])->assertHasNoTableActionErrors();

    $review ??= Review::query()->where('order_id', $order->id)->firstOrFail();
    $mediaItems = $review->fresh()->getMedia('reviews');

    expect($mediaItems)->toHaveCount($editing ? 3 : 2);

    $newMediaItems = $mediaItems->reject(fn ($media) => $media->id === $existingMedia?->id);
    expect($newMediaItems->pluck('uuid')->unique())->toHaveCount(2)
        ->and($newMediaItems->pluck('file_name')->unique())->toHaveCount(2);

    foreach ($newMediaItems as $media) {
        expect($media->file_name)->toBe($expectedSlug.'-'.$media->uuid.'.jpg')
            ->and($media->name)->toBe($expectedName.' - Great choice')
            ->and($media->getCustomProperty('name'))->toBe($expectedName.' - Great choice')
            ->and($media->hasGeneratedConversion('small'))->toBeTrue()
            ->and($media->hasGeneratedConversion('full'))->toBeTrue();

        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    if ($existingMedia) {
        expect($existingMedia->fresh()->file_name)->toBe('existing.jpg');
        Storage::disk('public')->assertExists($existingMedia->getPathRelativeToRoot());
    }
})->with([false, true])->with([false, true]);

test('can display reviews for a specific order', function () {
    $this->createLanguages();
    $this->createCurrencies();

    $order = Order::factory()->create();

    $product = Product::factory()->create();
    $purchasable = ProductVariant::factory()->for($product)->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_id' => $purchasable->id,
        'purchasable_type' => $purchasable->getMorphClass(),
    ]);

    $reviews = Review::factory(3)->create([
        'reviewable_type' => ProductVariant::morphName(),
        'reviewable_id' => $purchasable->id,
        'order_id' => $order->id,
    ]);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->assertCountTableRecords(3)
        ->assertCanSeeTableRecords($reviews);
});

test('can create review through product review relation manager', function () {
    $this->createLanguages();
    $this->createCurrencies();

    Country::factory()->create();

    $customer = Customer::factory()->create();
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    $customer->customerGroups()->attach($customerGroup->id);

    $order = Order::factory()->for($customer)->create();

    $product = Product::factory()->create();
    $purchasable = ProductVariant::factory()->for($product)->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_id' => $purchasable->id,
        'purchasable_type' => $purchasable->getMorphClass(),
    ]);

    $name = 'John Doe';
    $rating = '5';
    $comment = 'Excellent product!';

    $attributeGroup = AttributeGroup::create([
        'attributable_type' => 'review',
        'name' => collect([
            'en' => 'Review Details',
        ]),
        'handle' => 'review_details',
        'position' => '1',
    ]);

    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'position' => '2',
        'name' => [
            'en' => 'Full Name',
        ],
        'handle' => 'full_name',
        'required' => true,
    ]);

    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'position' => '3',
        'name' => [
            'en' => 'Rating',
        ],
        'type' => Dropdown::class,
        'handle' => 'rating',
        'configuration' => [
            'lookups' => [
                ['label' => '1', 'value' => '1'],
                ['label' => '2', 'value' => '2'],
                ['label' => '3', 'value' => '3'],
                ['label' => '4', 'value' => '4'],
                ['label' => '5', 'value' => '5'],
            ],
        ],
        'required' => true,
    ]);

    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'position' => '4',
        'name' => [
            'en' => 'Comment',
        ],
        'configuration' => [
            'richtext' => false,
            'disable_richtext_toolbar' => true,
        ],
        'type' => TranslatedText::class,
        'handle' => 'comment',
        'required' => true,
    ]);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->callTableAction(CreateAction::class, data: [
            'reviewable_id' => $purchasable->id,
            'attribute_data.full_name' => $name,
            'attribute_data.rating' => $rating,
            'attribute_data.comment.en' => $comment,
        ])
        ->assertHasNoErrors();

    $review = Review::latest()->first();

    expect($review->translateAttribute('full_name'))
        ->toBe($name)
        ->and($review->translateAttribute('rating'))
        ->toBe($rating)
        ->and($review->translateAttribute('comment', 'en'))
        ->toBe("<p>{$comment}</p>");

    expect($review->reviewable_id)->toBe($product->id);
    expect($review->order_id)->toBe($order->id);
});

test('can save edited product review data', function () {
    $this->createLanguages();
    $this->createCurrencies();

    Country::factory()->create();

    $customer = Customer::factory()->create();
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    $customer->customerGroups()->attach($customerGroup->id);

    $order = Order::factory()->for($customer)->create();

    $product = Product::factory()->create();
    $purchasable = ProductVariant::factory()->for($product)->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_id' => $purchasable->id,
        'purchasable_type' => $purchasable->getMorphClass(),
    ]);

    $review = Review::factory()->create([
        'order_id' => $order->id,
        'reviewable_type' => ProductVariant::morphName(),
        'reviewable_id' => $purchasable->id,
    ]);

    $rating = '4';
    $comment = 'Updated product review comment';

    $attributeGroup = AttributeGroup::create([
        'attributable_type' => 'review',
        'name' => collect([
            'en' => 'Review Details',
        ]),
        'handle' => 'review_details',
        'position' => '1',
    ]);

    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'position' => '2',
        'name' => [
            'en' => 'Full Name',
        ],
        'handle' => 'full_name',
        'required' => true,
    ]);

    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'position' => '3',
        'name' => [
            'en' => 'Rating',
        ],
        'type' => Dropdown::class,
        'handle' => 'rating',
        'configuration' => [
            'lookups' => [
                ['label' => '1', 'value' => '1'],
                ['label' => '2', 'value' => '2'],
                ['label' => '3', 'value' => '3'],
                ['label' => '4', 'value' => '4'],
                ['label' => '5', 'value' => '5'],
            ],
        ],
        'required' => true,
    ]);

    Attribute::factory()->create([
        'attribute_group_id' => $attributeGroup->id,
        'attribute_type' => 'review',
        'position' => '4',
        'name' => [
            'en' => 'Comment',
        ],
        'configuration' => [
            'richtext' => false,
            'disable_richtext_toolbar' => true,
        ],
        'type' => TranslatedText::class,
        'handle' => 'comment',
        'required' => true,
    ]);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->callTableAction(
            EditAction::class,
            record: $review,
            data: [
                'attribute_data.rating' => $rating,
                'attribute_data.comment.en' => $comment,
            ]
        )
        ->assertHasNoTableActionErrors();

    expect($review->refresh()->translateAttribute('rating'))
        ->toBe($rating)
        ->and($review->translateAttribute('comment', 'en'))
        ->toBe("<p>{$comment}</p>");
});

test('can delete a review for a specific order', function () {
    $this->createLanguages();
    $this->createCurrencies();

    $order = Order::factory()->create();

    $product = Product::factory()->create();
    $purchasable = ProductVariant::factory()->for($product)->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_id' => $purchasable->id,
        'purchasable_type' => $purchasable->getMorphClass(),
    ]);

    $reviews = Review::factory(3)->create([
        'reviewable_type' => ProductVariant::morphName(),
        'reviewable_id' => $purchasable->id,
        'order_id' => $order->id,
    ]);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->assertCountTableRecords(3)
        ->assertCanSeeTableRecords($reviews);

    $reviewToDelete = $reviews->first();

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->callTableAction(DeleteAction::class, record: $reviewToDelete)
        ->assertHasNoTableActionErrors();

    $reviews->fresh();

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->assertCountTableRecords(2)
        ->assertCanSeeTableRecords($reviews->except($reviewToDelete->id));
});

test('can approve a review for a specific order', function () {
    $this->createLanguages();
    $this->createCurrencies();

    $order = Order::factory()->create();

    $product = Product::factory()->create();
    $purchasable = ProductVariant::factory()->for($product)->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_id' => $purchasable->id,
        'purchasable_type' => $purchasable->getMorphClass(),
    ]);

    $reviews = Review::factory(3)->create([
        'reviewable_type' => ProductVariant::morphName(),
        'reviewable_id' => $purchasable->id,
        'order_id' => $order->id,
        'approved_at' => null,
    ]);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->assertCountTableRecords(3)
        ->assertCanSeeTableRecords($reviews);

    $reviewToApprove = $reviews->first();

    $this->assertNull($reviewToApprove->approved_at);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->callTableAction(
            EditAction::class,
            record: $reviewToApprove,
            data: [
                'approved_at' => true,
            ]
        )
        ->assertHasNoTableActionErrors();

    $reviewToApprove->refresh();

    $this->assertNotNull($reviewToApprove->approved_at);

    Livewire::test(ProductVariantReviewRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ManageOrder::class,
    ])
        ->assertCountTableRecords(3)
        ->assertCanSeeTableRecords($reviews->except($reviewToApprove->id));
});
