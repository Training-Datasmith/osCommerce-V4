<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\extensions\Coupons_And_Vauchers\Checkout;

class Checkout extends \yii\base\Widget
{
    public $name;
    public $params;
    public $settings;
    public $id;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (!\common\helpers\Acl::check_extension_allowed('CouponsAndVauchers', 'allowed')) {
            return '';
        }
        $manager = $this->params['manager'];
        return \common\extensions\Coupons_And_Vauchers\Coupons_And_Vauchers::checkout_coupon_voucher($manager->get_credit_modules(), $this->id);
    }
}