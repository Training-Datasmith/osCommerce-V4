<?php

declare(strict_types=1);

namespace common\modules\orderPayment\lib\PaypalPartner\api;

/**
 * Class PartnerConstants
 * Placeholder for Paypal Constants
 *
 * @package PayPal\Core
 */
class PartnerConstants
{
    public const NAME_TYPE = 'LEGAL';
    public const INDIVIDUAL_TYPE = 'PRIMARY';

    public const BUSINESS_TYPE = 'INDIVIDUAL';
    public const BUSINESS_SUBTYPE = 'ASSO_TYPE_INCORPORATED';

    public const ADDRESS_TYPE_HOME = 'HOME';
    public const ADDRESS_TYPE_WORK = 'WORK';

    public const OPERATION = 'API_INTEGRATION';

    public const PRODUCT_EXPRESS_CHECKOUT = 'EXPRESS_CHECKOUT';
    public const PRODUCT = 'PPCP';

    public const CONSENTS_TYPE = 'SHARE_DATA_CONSENT';

    public const APPROVAL_URL = 'approval_url';

    public const CUSTOMER_TYPE = 'MERCHANT';

    public const REFERRALUSER_TYPE = 'PAYER_ID';
    public const PARTNERIDENTIFIER_TYPE = 'TRACKING_ID';

    public const INTEGRATION_METHOD = 'PAYPAL';
    public const INTEGRATION_TYPE_TP = 'THIRD_PARTY';
    public const INTEGRATION_TYPE_FP = 'FIRST_PARTY';

    public const FEATURE_PAYMENT = 'PAYMENT';
    public const FEATURE_REFUND = 'REFUND';
    public const FEATURE_FEE = 'PARTNER_FEE';
    public const FEATURE_DELAY = 'DELAY_FUNDS_DISBURSEMENT';
    public const FEATURE_INFO = 'ACCESS_MERCHANT_INFORMATION';

}
