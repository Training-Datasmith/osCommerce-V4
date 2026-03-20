<?php

declare(strict_types=1);
/**
 * NOT part of Lib
 * @Holbi Group Ltd
 */

namespace PayPalCheckoutSdk\Shipping;

use PayPalHttp\HttpRequest;

class TrackersBatchRequest extends HttpRequest
{
    public function __construct()
    {
        parent::__construct('/v1/shipping/trackers-batch?', 'POST');
        $this->headers['Content-Type'] = 'application/json';
    }
}
