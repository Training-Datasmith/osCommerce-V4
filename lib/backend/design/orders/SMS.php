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
namespace backend\design\orders;

use common\helpers\Acl;
use yii\base\Widget;
class SMS extends Widget
{
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (Acl::check_extension_allowed('SMS', 'showOnOrderPage') && $sms = Acl::check_extension_allowed('SMS', 'allowed')) {
            $sms_block = $sms::view_order($this->order);
            if ($sms_block) {
                echo $sms_block;
            }
        }
    }
}