<?php

namespace Lunar\ProductFeed\Http\Controllers;

use Lunar\ProductFeed\ProductFeedCache;
use Lunar\ProductFeed\ProductFeedEncoderResolver;
use Lunar\ProductFeed\Services\ProductFeedService;
use Symfony\Component\HttpFoundation\Response;

class ProductFeedController
{
    public function __construct(
        protected ProductFeedService $feedService,
        protected ProductFeedEncoderResolver $encoderResolver,
        protected ProductFeedCache $feedCache,
    ) {}

    public function show(string $format): Response
    {
        $encoder = $this->encoderResolver->resolve($format);

        if ($encoder === null) {
            abort(404);
        }

        $headers = [
            'Content-Type' => $encoder->contentType(),
        ];

        if ($this->feedCache->isEnabled()) {
            $headers['Cache-Control'] = 'public, max-age='.$this->feedCache->ttl();
        }

        $filename = $encoder->downloadFilename();

        if ($filename !== null) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$filename.'"';
        }

        if ($this->feedCache->isEnabled()) {
            $body = $this->feedCache->remember($format, function () use ($encoder): string {
                return $encoder->encode($this->feedService->buildItems());
            });

            return response($body, 200, $headers);
        }

        return response()->stream(function () use ($encoder): void {
            $out = fopen('php://output', 'wb');
            $encoder->streamEncode($this->feedService->buildItems(), $out);
        }, 200, $headers);
    }
}
