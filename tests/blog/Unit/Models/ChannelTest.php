<?php

uses(\Lunar\Tests\Blog\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Lunar\Blog\Models\BlogCategory;
use Lunar\Blog\Models\BlogPost;
use Lunar\Models\Channel;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
});

test('blogCategories returns morph to many relationship', function () {
    $channel = Channel::factory()->create(['name' => 'Test Channel']);

    expect($channel->blogCategories())->toBeInstanceOf(MorphToMany::class);
});

test('blogCategories can have multiple categories', function () {
    $channel = Channel::factory()->create(['name' => 'Multi Category Channel']);
    $category1 = BlogCategory::factory()->create();
    $category2 = BlogCategory::factory()->create();
    $category3 = BlogCategory::factory()->create();

    $channel->blogCategories()->attach([$category1->id, $category2->id, $category3->id]);

    $attachedCategoryIds = $channel->fresh()->blogCategories->pluck('id')->unique();

    expect($attachedCategoryIds)
        ->toHaveCount(3)
        ->toContain($category1->id, $category2->id, $category3->id);
});

test('blogPosts returns morph to many relationship', function () {
    $channel = Channel::factory()->create(['name' => 'Test Channel']);

    expect($channel->blogPosts())->toBeInstanceOf(MorphToMany::class);
});

test('blogPosts can have multiple posts', function () {
    $channel = Channel::factory()->create(['name' => 'Multi Post Channel']);
    $post1 = BlogPost::factory()->create();
    $post2 = BlogPost::factory()->create();

    $channel->blogPosts()->attach([$post1->id, $post2->id]);

    $attachedPostIds = $channel->fresh()->blogPosts->pluck('id')->unique();

    expect($attachedPostIds)
        ->toHaveCount(2)
        ->toContain($post1->id, $post2->id);
});

test('name attribute returns correct name for different channels', function () {
    $channel1 = Channel::factory()->create(['name' => 'Channel One']);
    $channel2 = Channel::factory()->create(['name' => 'Channel Two']);

    expect($channel1->name)->toBe('Channel One')
        ->and($channel2->name)->toBe('Channel Two')
        ->and($channel1->name)->not()->toBe($channel2->name);
});
