<?php

declare(strict_types=1);

namespace PayPalHttp;

use Throwable;

class IOException extends \Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
