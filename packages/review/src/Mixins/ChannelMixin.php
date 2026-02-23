<?php

namespace Lunar\Review\Mixins;

use Lunar\Review\Traits\HasReviews;

class ChannelMixin
{
    use HasReviews;

    /**
     * Returns the name of the channel
     */
    public function getName()
    {
        return function (): string {
            /** @var \Lunar\Models\Channel $this */
            return $this->name;
        };
    }
}
