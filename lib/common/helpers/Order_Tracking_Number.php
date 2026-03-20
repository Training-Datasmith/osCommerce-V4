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
namespace common\helpers;

use common\models\Tracking_Carriers;
use yii\helpers\Array_Helper;
/**
 * @deprecated Use TrackingCarriers extensions instead this
 */
class Order_Tracking_Number
{
    public static function get_carriers_variants()
    {
        return Array_Helper::map(Tracking_Carriers::find()->order_by(['tracking_carriers_name' => SORT_ASC])->as_array()->all(), 'tracking_carriers_id', 'tracking_carriers_name');
    }
    public static function get_carrier_id($name)
    {
        if ($carrier = Tracking_Carriers::find_one(['tracking_carriers_name' => $name])) {
            return $carrier->tracking_carriers_id;
        }
        return 0;
    }
    public static function get_carrier_name($id)
    {
        if ($carrier = Tracking_Carriers::find_one(['tracking_carriers_id' => $id])) {
            return $carrier->tracking_carriers_name;
        }
        return '';
    }
}