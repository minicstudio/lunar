<?php

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Feedback\Filament\Extensions\ManageOrderExtension;
use Lunar\Feedback\Filament\Extensions\OrderResourceExtension;
use Lunar\Feedback\Filament\Resources\FeedbackResource;
use Lunar\Feedback\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use Lunar\Feedback\Models\Feedback;
use Lunar\Models\Order;
use Lunar\Tests\Feedback\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
});

test('order extensions are registered when feedback is enabled', function () {
    $extensions = LunarPanel::getExtensions();

    expect(collect($extensions[OrderResource::class] ?? [])->map(fn ($extension) => $extension::class)->all())
        ->toContain(OrderResourceExtension::class)
        ->and(collect($extensions[ManageOrder::class] ?? [])->map(fn ($extension) => $extension::class)->all())
        ->toContain(ManageOrderExtension::class);
});

test('order resource extension inserts the feedback score after the customer type column', function () {
    $livewire = Mockery::mock(HasTable::class)->shouldIgnoreMissing();

    $table = Table::make($livewire)
        ->columns([
            TextColumn::make('reference'),
            TextColumn::make('new_customer'),
            TextColumn::make('tags.value'),
        ]);

    $extended = (new OrderResourceExtension)->extendTable($table);

    expect(array_keys($extended->getColumns()))->toBe([
        'reference',
        'new_customer',
        'shoppingExperienceFeedback.score',
        'tags.value',
    ])->and($extended->getColumns()['shoppingExperienceFeedback.score']->getLabel())
        ->toBe(__('lunarpanel.feedback::plugin.order.score'));
});

test('manage order extension appends score and comment entries', function () {
    $section = Section::make()->schema([
        TextEntry::make('shipping_method'),
    ]);

    $extended = (new ManageOrderExtension)->extendOrderSummaryInfolist($section);

    $names = collect($extended->getDefaultChildComponents())
        ->map(fn ($component) => $component->getName())
        ->all();

    expect($names)->toBe(['shipping_method', 'feedback_score', 'feedback_comment']);
});

test('feedback resource is read-only', function () {
    $feedback = Feedback::query()->create([
        'order_id' => null,
        'type' => 'checkout.shopping_experience',
        'score' => 4,
        'locale' => 'en',
    ]);

    expect(FeedbackResource::canCreate())->toBeFalse()
        ->and(FeedbackResource::canEdit($feedback))->toBeFalse()
        ->and(FeedbackResource::canDelete($feedback))->toBeFalse()
        ->and(FeedbackResource::canDeleteAny())->toBeFalse()
        ->and(array_keys(FeedbackResource::getPages()))->toBe(['index']);
});

test('feedback list shows rows and filters by type and score', function () {
    $this->asStaff();

    $order = Order::factory()->create();

    $checkout = Feedback::query()->create([
        'order_id' => $order->id,
        'type' => 'checkout.shopping_experience',
        'score' => 2,
        'comment' => 'Slow delivery',
        'locale' => 'en',
    ]);

    $delivery = Feedback::query()->create([
        'order_id' => $order->id,
        'type' => 'order.delivery_experience',
        'score' => 5,
        'locale' => 'hu',
    ]);

    Livewire::test(ListFeedback::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$checkout, $delivery])
        ->filterTable('type', 'order.delivery_experience')
        ->assertCanSeeTableRecords([$delivery])
        ->assertCanNotSeeTableRecords([$checkout])
        ->resetTableFilters()
        ->filterTable('score', 2)
        ->assertCanSeeTableRecords([$checkout])
        ->assertCanNotSeeTableRecords([$delivery]);
});
