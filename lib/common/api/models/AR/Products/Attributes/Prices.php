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
namespace common\api\models\AR\Products\Attributes;

use common\api\models\AR\Ep_Map;
class Prices extends Ep_Map
{
    protected $hide_fields = ['products_attributes_id', 'groups_id', 'currencies_id'];
    public static function table_name()
    {
        return TABLE_PRODUCTS_ATTRIBUTES_PRICES;
    }
    public static function primary_key()
    {
        return ['products_attributes_id', 'groups_id', 'currencies_id'];
    }
    public static function get_all_key_codes()
    {
        $key_codes = [];
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True') {
            foreach (\common\helpers\Currencies::get_currencies() as $currency) {
                $key_code = $currency['code'] . '_0';
                $key_codes[$key_code] = ['products_attributes_id' => null, 'groups_id' => 0, 'currencies_id' => $currency['currencies_id']];
                if (\common\helpers\Extensions::is_customer_groups_allowed()) {
                    foreach (\common\helpers\Group::get_customer_groups() as $group_info) {
                        $key_code = $currency['code'] . '_' . $group_info['groups_id'];
                        $key_codes[$key_code] = ['products_attributes_id' => null, 'groups_id' => $group_info['groups_id'], 'currencies_id' => $currency['currencies_id']];
                    }
                }
            }
        } else if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            foreach (\common\helpers\Group::get_customer_groups() as $group_info) {
                $key_code = \common\helpers\Currencies::system_currency_code() . '_' . $group_info['groups_id'];
                $key_codes[$key_code] = ['products_attributes_id' => null, 'groups_id' => $group_info['groups_id'], 'currencies_id' => 0];
            }
        }
        return $key_codes;
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_attributes_id = $parent_object->products_attributes_id;
        parent::parent_ep_map($parent_object);
    }
}