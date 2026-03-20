<?php

declare(strict_types=1);

namespace PayPalCheckoutSdk\Core;

use PayPalHttp\Injector;

class GzipInjector implements Injector
{
    public function inject($httpRequest)
    {
        $httpRequest->headers['Accept-Encoding'] = 'gzip';
    }
}
