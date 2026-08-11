<?php

namespace Lunar\Content\Observers;

use Illuminate\Support\Facades\Cache;
use Lunar\Content\Models\ContentBlock;

class ContentBlockObserver
{
    /**
     * Clear cached storefront content when a block changes.
     */
    public function saved(ContentBlock $block): void
    {
        $this->clearTypeCache($block);
    }

    /**
     * Clear cached storefront content when a block is deleted.
     */
    public function deleted(ContentBlock $block): void
    {
        $this->clearTypeCache($block);
    }

    protected function clearTypeCache(ContentBlock $block): void
    {
        match ($block->type) {
            'announcement' => Cache::forget('content.announcements'),
            'popup' => Cache::forget('content.popups'),
            'hero' => Cache::forget('content.heroes'),
            'menu_item' => Cache::forget('content.menu_items'),
            default => null,
        };
    }
}
