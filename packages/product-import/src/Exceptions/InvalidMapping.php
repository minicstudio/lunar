<?php

namespace Lunar\ProductImport\Exceptions;

class InvalidMapping extends ImportActionRefused
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? '');
    }
}
