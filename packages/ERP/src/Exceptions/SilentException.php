<?php

namespace Lunar\ERP\Exceptions;

use Exception;

class SilentException extends Exception
{
    // This exception is used to silently fail certain operations without displaying errors to the user,
    // but sending error reports to monitoring services like Nightwatch
}
