<?php

namespace Lunar\ERP\Contracts;

interface SupportsLocalities
{
    /**
     * Get localities from the ERP system.
     */
    public function getLocalities(): array;
}
