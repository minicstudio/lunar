<?php

uses(\Lunar\Tests\Review\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Livewire\Livewire;
use Lunar\Review\Filament\Resources\ReviewResource;
use Lunar\Review\Filament\Resources\ReviewResource\Pages\ListReview;
use Lunar\Review\Models\Review;

test('can render review index page', function () {
    $this->asStaff(admin: true)
        ->get(ReviewResource::getUrl('index'))
        ->assertSuccessful();
});

test('can list reviews', function () {
    $this->createLanguages();
    $this->createCurrencies();

    $this->asStaff(admin: true);

    $reviews = Review::factory(5)->create();

    Livewire::test(ListReview::class)
        ->assertCountTableRecords(5)
        ->assertCanSeeTableRecords($reviews);
});
