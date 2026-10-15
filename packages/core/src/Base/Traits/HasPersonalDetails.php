<?php

namespace Lunar\Base\Traits;

trait HasPersonalDetails
{
    /**
     * Return the full name without title, which has a different meaning in this project.
     */
    public function getFullNameAttribute(): string
    {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                "{$this->first_name} {$this->last_name}"
            )
        );
    }
}
