<?php

declare(strict_types=1);

namespace PayPal\Core;

/**
 * Class PayPalConstants
 * Placeholder for Paypal Constants
 *
 * @package PayPal\Core
 */
class PayPalConstants
{
    public const SDK_NAME = 'PayPal-PHP-SDK';
    public const SDK_VERSION = '1.14.0';

    /**
     * Approval URL for Payment
     */
    public const APPROVAL_URL = 'approval_url';

    public const REST_SANDBOX_ENDPOINT = 'https://api.sandbox.paypal.com/';
    public const OPENID_REDIRECT_SANDBOX_URL = 'https://www.sandbox.paypal.com';

    public const REST_LIVE_ENDPOINT = 'https://api.paypal.com/';
    public const OPENID_REDIRECT_LIVE_URL = 'https://www.paypal.com';
}
