<?php

uses(\Lunar\Tests\Review\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Review\Filament\Resources\OrderResource\RelationManagers\ProductVariantReviewRelationManager;
use Lunar\Review\Models\Review;

beforeEach(function () {
    $this->asStaff(admin: true);
});

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
        'type' => \Lunar\FieldTypes\Dropdown::class,
        'handle' => 'rating',
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
        ->toBe($comment);

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
        'type' => \Lunar\FieldTypes\Dropdown::class,
        'handle' => 'rating',
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
        ->toBe($comment);
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
