<?php

namespace Lunar\ProductFeed;

use Lunar\ProductFeed\Encoders\ProductFeedEncoder;

class ProductFeedEncoderResolver
{
    /**
     * @param  array<string, ProductFeedEncoder>  $encoders
     */
    public function __construct(
        protected array $encoders
    ) {}

    public function resolve(string $format): ?ProductFeedEncoder
    {
        return $this->encoders[$format] ?? null;
    }
}
