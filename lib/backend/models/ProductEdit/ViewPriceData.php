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
namespace backend\models\Product_Edit;

class View_Price_Data
{
    /**
     * @var \objectInfo
     */
    protected $product_info_ref;
    public function __construct($product_info)
    {
        if (is_object($product_info) && $product_info instanceof \common\models\Products) {
            $product_info = new \Object_Info($product_info->get_attributes());
        }
        $this->product_info_ref = $product_info;
    }
    public function populate_view($view)
    {
        $currencies = \Yii::$container->get('currencies');
        $p_info = $this->product_info_ref;
        $price_tabs_data = $pack_unit_price_tabs_data = $packaging_price_tabs_data = $gift_wrap_data = [];
        $_tax = \common\helpers\Tax::get_tax_rate_value($p_info->products_tax_class_id ?? null) / 100;
        $_round_to = $currencies->get_decimal_places(DEFAULT_CURRENCY);
        $gift_wrap = tep_db_query('select gw_id as gift_wrap_id, groups_id, currencies_id, gift_wrap_price, round(gift_wrap_price +round(gift_wrap_price *' . (float) $_tax . ', 6), ' . (int) $_round_to . ') as gift_wrap_price_gross from ' . TABLE_GIFT_WRAP_PRODUCTS . " where products_id ='" . (int) $p_info->products_id . "'");
        if (tep_db_num_rows($gift_wrap) > 0) {
            while ($data = tep_db_fetch_array($gift_wrap)) {
                if (USE_MARKET_PRICES == 'True' && \common\helpers\Extensions::is_customer_groups_allowed()) {
                    $idx = '[' . $data['currencies_id'] . '][' . $data['groups_id'] . ']';
                } elseif (USE_MARKET_PRICES == 'True') {
                    $idx = '[' . $data['currencies_id'] . ']';
                } elseif (\common\helpers\Extensions::is_customer_groups_allowed()) {
                    $idx = '[' . $data['groups_id'] . ']';
                } else {
                    $idx = '';
                }
                eval('$gift_wrap_data' . $idx . ' = $data;');
            }
        }
        if (isset($view->default_sale_id)) {
            $_def_sale = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_SPECIALS . " where products_id = '" . (int) $p_info->products_id . "' and specials_id='" . (int) $view->default_sale_id . "'"));
        } else {
            $_def_sale = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_SPECIALS . " where products_id = '" . (int) $p_info->products_id . "' /* show expired also and (status>0 or start_date>=now() or (status=0 and specials_disabled=1 and expires_date>now()) )*/ order by status desc, start_date<now(), start_date, specials_disabled  desc  limit 1"));
            //vl2check active, scheduled, disabled not expired
        }
        if (is_array($_def_sale)) {
            $_def_sale['_status'] = $_def_sale['status'];
            $_def_sale['status'] = 1;
            $p_info->specials_id = $_def_sale['specials_id'];
        } else {
            $_def_sale['status'] = 0;
        }
        $view->qty_discounts = [];
        $view->qty_discounts_pack_unit = [];
        $view->qty_discounts_packaging = [];
        ///pseudo group "Main" or simple nothing
        //if (\common\helpers\Extensions::isCustomerGroupsAllowed() && !$view->useMarketPrices) {
        if (!$view->use_market_prices) {
            $qty_discounts = [];
            $tmp = \common\helpers\Product::parse_qty_discount_array($p_info->products_price_discount);
            if (count($tmp) > 0) {
                foreach ($tmp as $key => $value) {
                    $qty_discounts[$key]['price'] = $value;
                    $qty_discounts[$key]['price_gross'] = round($value + round($value * (float) $_tax, 6), $_round_to);
                }
            }
            $delivery_option = 0;
            if ($ext = \common\helpers\Acl::check_extension_allowed('DeliveryOptions', 'allowed')) {
                $delivery_option = $ext::get_product_flag($p_info->products_id, 0);
            }
            $tmp = [
                'groups_id' => 0,
                'currencies_id' => $view->default_currency ?? \common\helpers\Currencies::get_default_currency_id(),
                'products_group_price' => $p_info->products_price,
                'products_group_price_gross' => round($p_info->products_price + round($p_info->products_price * $_tax, 6), $_round_to),
                'products_group_special_price' => isset($_def_sale['specials_new_products_price']) && $_def_sale['status'] ? $_def_sale['specials_new_products_price'] : 0,
                // ok in case of no groups & market prices else - 0
                'products_group_special_price_gross' => isset($_def_sale['specials_new_products_price']) && $_def_sale['status'] ? round($_def_sale['specials_new_products_price'] + round($_def_sale['specials_new_products_price'] * $_tax, 6), $_round_to) : 0,
                'expires_date' => !empty($_def_sale['expires_date']) ? $_def_sale['expires_date'] : '',
                'start_date' => !empty($_def_sale['start_date']) ? $_def_sale['start_date'] : '',
                'supplier_price_manual' => $p_info->supplier_price_manual,
                'bonus_points_price' => $p_info->bonus_points_price,
                'bonus_points_cost' => $p_info->bonus_points_cost,
                'shipping_surcharge_price' => $p_info->shipping_surcharge_price,
                'shipping_surcharge_price_gross' => round($p_info->shipping_surcharge_price + round($p_info->shipping_surcharge_price * $_tax, 6), $_round_to),
                'gift_wrap_id' => !\common\helpers\Extensions::is_customer_groups_allowed() ? isset($gift_wrap_data['gift_wrap_id']) ? $gift_wrap_data['gift_wrap_id'] : 0 : (isset($gift_wrap_data[0]['gift_wrap_id']) ? $gift_wrap_data[0]['gift_wrap_id'] : 0),
                'delivery_option' => $delivery_option,
                'gift_wrap_price' => !\common\helpers\Extensions::is_customer_groups_allowed() ? isset($gift_wrap_data['gift_wrap_price']) ? $gift_wrap_data['gift_wrap_price'] : 0 : (isset($gift_wrap_data[0]['gift_wrap_price']) ? $gift_wrap_data[0]['gift_wrap_price'] : 0),
                'gift_wrap_price_gross' => !\common\helpers\Extensions::is_customer_groups_allowed() ? isset($gift_wrap_data['gift_wrap_price_gross']) ? $gift_wrap_data['gift_wrap_price_gross'] : 0 : (isset($gift_wrap_data[0]['gift_wrap_price_gross']) ? $gift_wrap_data[0]['gift_wrap_price_gross'] : 0),
                'tax_rate' => (float) $_tax,
                'round_to' => (int) $_round_to,
                'qty_discounts' => $qty_discounts,
            ];
            if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
                $price_tabs_data = $tmp;
            } else {
                $price_tabs_data[0] = $tmp;
            }
            $qty_discounts = [];
            $tmp = \common\helpers\Product::parse_qty_discount_array($p_info->products_price_discount_pack_unit);
            if (count($tmp) > 0) {
                foreach ($tmp as $key => $value) {
                    $qty_discounts[$key]['price'] = $value;
                    $qty_discounts[$key]['price_gross'] = round($value + round($value * (float) $_tax, 6), $_round_to);
                }
            }
            $tmp = ['groups_id' => 0, 'currencies_id' => $view->default_currency ?? \common\helpers\Currencies::get_default_currency_id(), 'tax_rate' => (float) $_tax, 'round_to' => (int) $_round_to, 'products_group_price_pack_unit' => $p_info->products_price_pack_unit, 'products_group_price_gross_pack_unit' => round($p_info->products_price_pack_unit + round($p_info->products_price_pack_unit * $_tax, 6), $_round_to), 'qty_discounts' => $qty_discounts];
            if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
                $pack_unit_price_tabs_data = $tmp;
            } else {
                $pack_unit_price_tabs_data[0] = $tmp;
            }
            $qty_discounts = [];
            $tmp = \common\helpers\Product::parse_qty_discount_array($p_info->products_price_discount_packaging);
            if (count($tmp) > 0) {
                foreach ($tmp as $key => $value) {
                    $qty_discounts[$key]['price'] = $value;
                    $qty_discounts[$key]['price_gross'] = round($value + round($value * (float) $_tax, 6), $_round_to);
                }
            }
            $tmp = ['groups_id' => 0, 'currencies_id' => $view->default_currency ?? \common\helpers\Currencies::get_default_currency_id(), 'tax_rate' => (float) $_tax, 'round_to' => (int) $_round_to, 'products_group_price_packaging' => $p_info->products_price_packaging, 'products_group_price_gross_packaging' => round($p_info->products_price_packaging + round($p_info->products_price_packaging * $_tax, 6), $_round_to), 'qty_discounts' => $qty_discounts];
            if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
                $packaging_price_tabs_data = $tmp;
            } else {
                $packaging_price_tabs_data[0] = $tmp;
            }
        }
        if ($view->use_market_prices || \common\helpers\Extensions::is_customer_groups_allowed()) {
            $products_price_query = tep_db_query('select pp.groups_id, pp.currencies_id, pp.products_sets_discount, pp.supplier_price_manual, pp.products_group_price, round(pp.products_group_price +round(pp.products_group_price *' . (float) $_tax . ', 6), ' . (int) $_round_to . ') as products_group_price_gross, pp.bonus_points_price,	pp.bonus_points_cost, pp.products_group_discount_price, pp.products_group_price_pack_unit, round(pp.products_group_price_pack_unit +round(pp.products_group_price_pack_unit *' . (float) $_tax . ', 6), ' . (int) $_round_to . ') as products_group_price_gross_pack_unit, pp.products_group_discount_price_pack_unit, pp.products_group_price_packaging, round(pp.products_group_price_packaging +round(pp.products_group_price_packaging *' . (float) $_tax . ', 6), ' . (int) $_round_to . ') as products_group_price_gross_packaging, pp.products_group_discount_price_packaging, sp.specials_new_products_price as products_group_special_price, round(sp.specials_new_products_price +round(sp.specials_new_products_price *' . (float) $_tax . ', 6), ' . (int) $_round_to . ') as products_group_special_price_gross, s.expires_date, s.start_date, ifnull(s.status, 0) as sales_status, ifnull(s.specials_disabled, -1) as specials_disabled, ifnull(s.specials_enabled, -1) as specials_enabled, (ifnull(s.start_date, now())>now()) as specials_scheduled, max_per_order, total_qty, pp.shipping_surcharge_price, round(pp.shipping_surcharge_price +round(pp.shipping_surcharge_price *' . (float) $_tax . ', 6), ' . (int) $_round_to . ') as shipping_surcharge_price_gross from ' . TABLE_PRODUCTS_PRICES . ' pp left join ' . TABLE_SPECIALS . " s on pp.products_id=s.products_id and s.specials_id='" . (isset($p_info->specials_id) ? (int) $p_info->specials_id : 0) . "' left join " . TABLE_SPECIALS_PRICES . " sp on pp.groups_id=sp.groups_id and pp.currencies_id=sp.currencies_id and s.specials_id=sp.specials_id where pp.products_id = '" . (int) $p_info->products_id . "' order by pp.currencies_id, pp.groups_id");
            $_tmp_keys = ['price_tabs_data' => ['delivery_option', 'qty_discounts', 'products_sets_discount', 'supplier_price_manual', 'products_group_price', 'products_group_price_gross', 'products_group_special_price', 'products_group_special_price_gross', 'bonus_points_price', 'bonus_points_cost', 'expires_date', 'start_date', 'sales_status', 'specials_disabled', 'specials_enabled', 'max_per_order', 'total_qty', 'specials_scheduled', 'gift_wrap_id', 'gift_wrap_price', 'gift_wrap_price_gross', 'shipping_surcharge_price', 'shipping_surcharge_price_gross', 'tax_rate', 'round_to', 'base_price', 'base_price_gross', 'base_specials_price', 'base_specials_price_gross'], 'pack_unit_price_tabs_data' => ['qty_discounts', 'products_group_price_pack_unit', 'products_group_price_gross_pack_unit', 'tax_rate', 'round_to', 'base_price', 'base_price_gross', 'base_specials_price', 'base_specials_price_gross'], 'packaging_price_tabs_data' => ['qty_discounts', 'products_group_price_packaging', 'products_group_price_gross_packaging', 'tax_rate', 'round_to', 'base_price', 'base_price_gross', 'base_specials_price', 'base_specials_price_gross']];
            $_base_price = $p_info->products_price;
            $_base_price_gross = round($p_info->products_price + round($p_info->products_price * $_tax, 6), $_round_to);
            $_base_special_price = $_def_sale['status'] == 1 ? $_def_sale['specials_new_products_price'] : 0;
            $_base_special_price_gross = $_def_sale['status'] == 1 ? round($_def_sale['specials_new_products_price'] + round($_def_sale['specials_new_products_price'] * $_tax, 6), $_round_to) : 0;
            while ($products_price_data = tep_db_fetch_array($products_price_query)) {
                //$products_price_data['tax_rate'] = (double)$_tax;
                //$products_price_data['round_to'] = (int)$_roundTo;
                $products_price_data['round_to'] = (int) $currencies->get_decimal_places_by_id($products_price_data['currencies_id']);
                if ($view->use_market_prices && $products_price_data['groups_id'] == 0) {
                    $_base_price = $products_price_data['products_group_price'];
                    $_base_price_gross = $products_price_data['products_group_price_gross'];
                    $_base_special_price = $products_price_data['products_group_special_price'];
                    $_base_special_price_gross = $products_price_data['products_group_special_price_gross'];
                }
                //else {
                $products_price_data['base_price'] = $_base_price;
                $products_price_data['base_price_gross'] = $_base_price_gross;
                $products_price_data['base_specials_price'] = $_base_special_price;
                $products_price_data['base_specials_price_gross'] = $_base_special_price_gross;
                if (is_null($products_price_data['products_group_special_price'])) {
                    // group 0 could be saved in products_prices ???
                    $products_price_data['products_group_special_price'] = $_base_special_price;
                    $products_price_data['products_group_special_price_gross'] = $_base_special_price_gross;
                }
                //}
                $delivery_option = 0;
                if ($ext = \common\helpers\Acl::check_extension_allowed('DeliveryOptions', 'allowed')) {
                    $delivery_option = $ext::get_product_flag($p_info->products_id, $products_price_data['groups_id']);
                }
                $products_price_data['delivery_option'] = $delivery_option;
                if (USE_MARKET_PRICES == 'True' && \common\helpers\Extensions::is_customer_groups_allowed()) {
                    foreach ($_tmp_keys as $k => $v) {
                        $_tmp_keys[$k] = array_merge($v, ['groups_id', 'currencies_id']);
                    }
                    $idx = '[' . $products_price_data['currencies_id'] . '][' . $products_price_data['groups_id'] . ']';
                } elseif (USE_MARKET_PRICES == 'True') {
                    foreach ($_tmp_keys as $k => $v) {
                        $_tmp_keys[$k] = array_merge($v, ['currencies_id']);
                    }
                    $idx = '[' . $products_price_data['currencies_id'] . ']';
                } else {
                    foreach ($_tmp_keys as $k => $v) {
                        $_tmp_keys[$k] = array_merge($v, ['groups_id']);
                    }
                    $idx = '[' . $products_price_data['groups_id'] . ']';
                }
                $tmp_qty_discounts = false;
                foreach ($_tmp_keys as $k => $v) {
                    // $k - 3 tabs $v - required fields
                    if ($k == 'price_tabs_data') {
                        @eval('$products_price_data[\'gift_wrap_id\'] = $gift_wrap_data' . $idx . '[\'gift_wrap_id\'];');
                        @eval('$products_price_data[\'gift_wrap_price\'] = $gift_wrap_data' . $idx . '[\'gift_wrap_price\'];');
                        @eval('$products_price_data[\'gift_wrap_price_gross\'] = $gift_wrap_data' . $idx . '[\'gift_wrap_price_gross\'];');
                        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                            $tmp_qty_discounts = \common\helpers\Product::parse_qty_discount_array($products_price_data['products_group_discount_price']);
                        }
                    }
                    if ($k == 'pack_unit_price_tabs_data' && $ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                        $tmp_qty_discounts = \common\helpers\Product::parse_qty_discount_array($products_price_data['products_group_discount_price_pack_unit']);
                    }
                    if ($k == 'packaging_price_tabs_data' && $ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                        $tmp_qty_discounts = \common\helpers\Product::parse_qty_discount_array($products_price_data['products_group_discount_price_packaging']);
                    }
                    if (is_array($tmp_qty_discounts)) {
                        foreach ($tmp_qty_discounts as $key => $value) {
                            $products_price_data['qty_discounts'][$key]['price'] = $value;
                            $products_price_data['qty_discounts'][$key]['price_gross'] = round($value + round($value * (float) $_tax, 6), $products_price_data['round_to']);
                        }
                    }
                    $tmp = array_intersect_key($products_price_data, array_flip($v));
                    eval('$' . $k . $idx . ' = $tmp;');
                    unset($products_price_data['qty_discounts']);
                }
            }
        }
        $view->price_tabs_data = $price_tabs_data;
        //echo '<pre>';print_r($price_tabs_data);die;
        $view->pack_unit_price_tabs_data = $pack_unit_price_tabs_data;
        $view->packaging_price_tabs_data = $packaging_price_tabs_data;
    }
}