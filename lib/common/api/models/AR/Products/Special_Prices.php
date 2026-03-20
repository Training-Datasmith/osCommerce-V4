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
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
class Special_Prices extends Ep_Map
{
    protected $hide_fields = ['specials_id', 'groups_id', 'currencies_id'];
    /**
     * @var Special
     */
    protected $parent_object;
    public static function table_name()
    {
        return 'specials_prices';
    }
    public static function primary_key()
    {
        return ['specials_id', 'currencies_id', 'groups_id'];
    }
    public static function get_all_key_codes()
    {
        $key_codes = [];
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True') {
            foreach (\common\helpers\Currencies::get_currencies() as $currency) {
                $key_code = $currency['code'] . '_0';
                $key_codes[$key_code] = ['specials_id' => null, 'groups_id' => 0, 'currencies_id' => $currency['currencies_id']];
                if (\common\helpers\Extensions::is_customer_groups_allowed()) {
                    foreach (\common\helpers\Group::get_customer_groups() as $group_info) {
                        $key_code = $currency['code'] . '_' . $group_info['groups_id'];
                        $key_codes[$key_code] = ['specials_id' => null, 'groups_id' => $group_info['groups_id'], 'currencies_id' => $currency['currencies_id']];
                    }
                }
            }
        } else if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            $key_codes[\common\helpers\Currencies::system_currency_code() . '_0'] = ['specials_id' => null, 'groups_id' => 0, 'currencies_id' => 0];
            foreach (\common\helpers\Group::get_customer_groups() as $group_info) {
                $key_code = \common\helpers\Currencies::system_currency_code() . '_' . $group_info['groups_id'];
                $key_codes[$key_code] = ['specials_id' => null, 'groups_id' => $group_info['groups_id'], 'currencies_id' => 0];
            }
        }
        return $key_codes;
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->specials_id = $parent_object->specials_id;
        $this->parent_object = $parent_object;
    }
    public function before_save($insert)
    {
        if ($insert) {
            if (is_null($this->specials_new_products_price)) {
                $this->specials_new_products_price = -2;
                if ($this->groups_id == 0 && $this->currencies_id == 0 && is_object($this->parent_object)) {
                    $this->specials_new_products_price = $this->parent_object->specials_new_products_price;
                }
            }
        } else if ($this->groups_id == 0 && $this->currencies_id == 0 && is_object($this->parent_object)) {
            $this->specials_new_products_price = $this->parent_object->specials_new_products_price;
        }
        return parent::before_save($insert);
    }
}