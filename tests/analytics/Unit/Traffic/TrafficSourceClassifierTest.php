<?php

use Illuminate\Http\Request;
use Lunar\Analytics\Traffic\Contracts\ClassifiesTrafficSource;
use Lunar\Analytics\Traffic\DataTransferObjects\TrafficSource;
use Lunar\Analytics\Traffic\Support\TrafficSourceClassifier;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);

/**
 * Build a storefront GET request on shop.test with an optional referrer.
 */
function classifierRequest(string $path, ?string $referrer = null): Request
{
    $server = $referrer === null ? [] : ['HTTP_REFERER' => $referrer];

    return Request::create('https://shop.test'.$path, 'GET', server: $server);
}

function classifyRequest(string $path, ?string $referrer = null): ?TrafficSource
{
    return app(ClassifiesTrafficSource::class)->classify(classifierRequest($path, $referrer));
}

test('the contract resolves to the default classifier', function () {
    expect(app(ClassifiesTrafficSource::class))->toBeInstanceOf(TrafficSourceClassifier::class);
});

test('it classifies the request', function (string $path, ?string $referrer, array $expected) {
    $source = classifyRequest($path, $referrer);

    expect($source)->toBeInstanceOf(TrafficSource::class)
        ->and([$source->source, $source->medium, $source->campaign, $source->referrerHost])->toBe($expected);
})->with([
    'google.com search' => ['/products/phone', 'https://www.google.com/', ['google', 'organic', null, 'google.com']],
    'country google domain' => ['/', 'https://www.google.hu/', ['google', 'organic', null, 'google.hu']],
    'two-label google suffix' => ['/', 'https://www.google.co.uk/search?q=x', ['google', 'organic', null, 'google.co.uk']],
    'facebook link shim' => ['/', 'https://lm.facebook.com/', ['facebook', 'social', null, 'lm.facebook.com']],
    'price comparison referral without www' => ['/', 'https://www.arukereso.hu/mobiltelefon-c3277/', ['arukereso.hu', 'referral', null, 'arukereso.hu']],
    'utm newsletter link lowercases source and medium only' => ['/?utm_source=Mailchimp&utm_medium=Email&utm_campaign=Black-Friday', null, ['mailchimp', 'email', 'Black-Friday', null]],
    'utm tag overrides the facebook referrer' => ['/?utm_source=fb_ads&utm_medium=paid_social', 'https://l.facebook.com/', ['fb_ads', 'paid_social', null, 'l.facebook.com']],
    'utm values are trimmed' => ['/?utm_source=%20Blog%20&utm_medium=%20&utm_campaign=%20nov%20', null, ['blog', null, 'nov', null]],
    'gclid wins over the google referrer' => ['/?gclid=Cj0KCQ', 'https://www.google.com/', ['google', 'cpc', null, 'google.com']],
    'gbraid without referrer' => ['/?gbraid=abc', null, ['google', 'cpc', null, null]],
    'fbclid without referrer' => ['/?fbclid=IwAR0', null, ['facebook', 'social', null, null]],
    'fbclid with an instagram referrer' => ['/?fbclid=PAZX', 'https://l.instagram.com/', ['instagram', 'social', null, 'l.instagram.com']],
    'utm_medium without utm_source is ignored' => ['/?utm_medium=email&utm_campaign=nov', null, ['direct', 'none', null, null]],
    'direct' => ['/', null, ['direct', 'none', null, null]],
    'unparsable referrer counts as none' => ['/', 'http://', ['direct', 'none', null, null]],
    'same-host referrer with utm_source' => ['/?utm_source=banner', 'https://www.shop.test/cart', ['banner', null, null, null]],
    'same-host referrer with fbclid' => ['/?fbclid=x', 'https://shop.test/', ['facebook', 'social', null, null]],
]);

test('a same-host referrer without campaign parameters gives no source', function (string $referrer) {
    expect(classifyRequest('/products', $referrer))->toBeNull();
})->with([
    'same host' => ['https://shop.test/'],
    'www variant' => ['https://www.shop.test/category'],
]);

test('values over 255 characters are cut to 255', function () {
    $campaign = str_repeat('c', 300);
    $source = str_repeat('S', 300);

    $result = classifyRequest('/?utm_source='.$source.'&utm_campaign='.$campaign);

    expect(mb_strlen($result->campaign))->toBe(255)
        ->and($result->source)->toBe(str_repeat('s', 255));
});

test('it detects campaign parameters', function (string $path, bool $expected) {
    expect(app(ClassifiesTrafficSource::class)->hasCampaignParameters(classifierRequest($path)))->toBe($expected);
})->with([
    'utm_source' => ['/?utm_source=x', true],
    'gclid' => ['/?gclid=x', true],
    'fbclid' => ['/?fbclid=x', true],
    'utm_medium only' => ['/?utm_medium=x', false],
    'empty click id' => ['/?gclid=', false],
    'none' => ['/', false],
]);

test('a shop can map its own referrer host', function () {
    config(['lunar.analytics.traffic.referrer_hosts' => [
        ...config('lunar.analytics.traffic.referrer_hosts'),
        'compari.ro' => ['source' => 'compari', 'medium' => 'comparison'],
    ]]);

    $source = classifyRequest('/', 'https://www.compari.ro/');

    expect([$source->source, $source->medium, $source->referrerHost])->toBe(['compari', 'comparison', 'compari.ro']);
});

test('a shop can add a click id that wins over the referrer', function () {
    config(['lunar.analytics.traffic.click_ids.msclkid' => ['source' => 'bing', 'medium' => 'cpc', 'before_referrer' => true]]);

    $source = classifyRequest('/?msclkid=x', 'https://www.bing.com/');

    expect([$source->source, $source->medium])->toBe(['bing', 'cpc'])
        ->and(app(ClassifiesTrafficSource::class)->hasCampaignParameters(classifierRequest('/?msclkid=x')))->toBeTrue();
});

test('a shop can replace the classifier through config', function () {
    $custom = new class implements ClassifiesTrafficSource
    {
        public function classify(Request $request): ?TrafficSource
        {
            return new TrafficSource('custom', 'rule');
        }

        public function hasCampaignParameters(Request $request): bool
        {
            return false;
        }
    };

    app()->instance($custom::class, $custom);
    config(['lunar.analytics.traffic.classifier' => $custom::class]);

    expect(app(ClassifiesTrafficSource::class))->toBe($custom)
        ->and(classifyRequest('/', 'https://www.google.com/')->source)->toBe('custom');
});
