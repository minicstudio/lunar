<?php

use Lunar\Analytics\Traffic\Support\TrafficSourceLabel;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);

test('it names the traffic source in words', function (mixed $trafficSource, string $label, string $color, ?string $campaign) {
    $name = TrafficSourceLabel::for($trafficSource);

    expect($name->label())->toBe($label)
        ->and($name->color())->toBe($color)
        ->and($name->campaign())->toBe($campaign);
})->with([
    'google organic' => [['visit_id' => 1, 'source' => 'google', 'medium' => 'organic', 'campaign' => null], 'Google · Organic search', 'success', null],
    'google ad' => [['visit_id' => 1, 'source' => 'google', 'medium' => 'cpc', 'campaign' => null], 'Google · Paid ad', 'warning', null],
    'facebook' => [['visit_id' => 1, 'source' => 'facebook', 'medium' => 'social', 'campaign' => null], 'Facebook · Social', 'info', null],
    'tiktok' => [['visit_id' => 1, 'source' => 'tiktok', 'medium' => 'social', 'campaign' => null], 'TikTok · Social', 'info', null],
    'newsletter' => [['visit_id' => 1, 'source' => 'mailchimp', 'medium' => 'email', 'campaign' => 'Black-Friday'], 'Mailchimp · Email', 'primary', 'Black-Friday'],
    'price comparison host' => [['visit_id' => 1, 'source' => 'arukereso.hu', 'medium' => 'referral', 'campaign' => null], 'arukereso.hu · Referral', 'gray', null],
    'direct' => [['visit_id' => 1, 'source' => 'direct', 'medium' => 'none', 'campaign' => null], 'Direct', 'gray', null],
    'other source and medium' => [['visit_id' => 1, 'source' => 'fb_ads', 'medium' => 'paid_social', 'campaign' => null], 'Fb Ads · Paid Social', 'gray', null],
    'tag without medium' => [['visit_id' => 1, 'source' => 'partner', 'medium' => '', 'campaign' => null], 'Partner', 'gray', null],
    'no traffic source' => [null, 'Unknown', 'gray', null],
    'traffic source without a source' => [['visit_id' => 1, 'medium' => 'email', 'campaign' => 'Black-Friday'], 'Unknown', 'gray', null],
]);

test('it gives the source and medium names', function () {
    $name = TrafficSourceLabel::for(['visit_id' => 1, 'source' => 'duckduckgo', 'medium' => 'organic', 'campaign' => null]);

    expect($name->sourceName())->toBe('DuckDuckGo')
        ->and($name->mediumName())->toBe('Organic search')
        ->and(TrafficSourceLabel::for(null)->sourceName())->toBe('Unknown')
        ->and(TrafficSourceLabel::for(null)->mediumName())->toBeNull()
        ->and(TrafficSourceLabel::nameOfSource('youtube'))->toBe('YouTube')
        ->and(TrafficSourceLabel::nameOfSource('direct'))->toBe('Direct')
        ->and(TrafficSourceLabel::nameOfMedium('cpc'))->toBe('Paid ad')
        ->and(TrafficSourceLabel::nameOfMedium('none'))->toBeNull()
        ->and(TrafficSourceLabel::nameOfMedium(null))->toBeNull();
});

test('it translates the source and medium names', function (string $locale, array $names) {
    app()->setLocale($locale);

    expect([
        TrafficSourceLabel::nameOfSource('direct'),
        TrafficSourceLabel::nameOfMedium('organic'),
        TrafficSourceLabel::nameOfMedium('cpc'),
        TrafficSourceLabel::nameOfMedium('social'),
        TrafficSourceLabel::nameOfMedium('referral'),
        TrafficSourceLabel::nameOfMedium('email'),
        TrafficSourceLabel::for(null)->label(),
    ])->toBe($names)
        ->and(TrafficSourceLabel::for(['source' => 'google', 'medium' => 'cpc'])->label())->toBe('Google · '.$names[2]);
})->with([
    'hu' => ['hu', ['Közvetlen', 'Organikus keresés', 'Fizetett hirdetés', 'Közösségi média', 'Hivatkozó oldal', 'E-mail', 'Ismeretlen']],
    'ro' => ['ro', ['Direct', 'Căutare organică', 'Reclamă plătită', 'Rețele sociale', 'Recomandare', 'Email', 'Necunoscut']],
]);
