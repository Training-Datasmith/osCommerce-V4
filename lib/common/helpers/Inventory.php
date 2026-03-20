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

defined('ALLOW_ANY_QUERY_CACHE') || define('ALLOW_ANY_QUERY_CACHE', 'True');
class Inventory
{
    public static function disabled_on_product($product_id)
    {
        $container = \Yii::$container->get('products');
        $products_arr = $container->load_products(['products_id' => (int) $product_id])->get_product((int) $product_id);
        return !!($products_arr['without_inventory'] ?? null);
    }
    public static function normalize_inventory_id($unified_product_id, &$vids = [], &$virtual_vids = [], $exclude_virtual = true)
    {
        if (preg_match('/^(\d+)(\{.*)$/', $unified_product_id, $match)) {
            $unified_product_id = \common\helpers\Product::normalize_prid((int) $match[1]) . $match[2];
            if (static::disabled_on_product($unified_product_id)) {
                $unified_product_id = static::get_prid($unified_product_id);
            }
            $unified_product_id = self::normalize_id($unified_product_id, $vids, $exclude_virtual, $virtual_vids);
        } else {
            $unified_product_id = \common\helpers\Product::normalize_prid((int) $unified_product_id);
        }
        return $unified_product_id;
    }
    public static function normalize_inventory_price_id($unified_product_id, &$vids = [], &$virtual_vids = [], $exclude_virtual = true)
    {
        if (preg_match('/^(\d+)(\{.*)$/', $unified_product_id, $match)) {
            $unified_product_id = \common\helpers\Product::normalize_price_prid((int) $match[1]) . $match[2];
            $unified_product_id = self::normalize_id($unified_product_id, $vids, $exclude_virtual, $virtual_vids);
        } else {
            $unified_product_id = \common\helpers\Product::normalize_price_prid((int) $unified_product_id);
        }
        return $unified_product_id;
    }
    public static function normalize_id($uprid, &$vids = [], $excl_virtual_options = false, &$virtual_vids = [])
    {
        /*Amount Product*/
        if (strpos($uprid, '_') !== false) {
            return $uprid;
        }
        $uprid = \Yii::$app->get('PropsHelper')::normalize_id($uprid);
        /* PC configurator addon begin */
        list($uprid, ) = explode('{tpl}', $uprid);
        list($uprid, ) = explode('{sub}', $uprid);
        /* PC configurator addon end */
        if ($ext = \common\helpers\Extensions::is_allowed('SupplierPurchase')) {
            $uprid = $ext::get_uprid($uprid);
        }
        if (preg_match("/^\\d+\$/", $uprid)) {
            return $uprid;
        } else {
            $product_id = self::get_prid($uprid);
            preg_match_all('/\{([\d\-]+)/', $uprid, $arr);
            $oids = $arr[1];
            preg_match_all('/\}(\d+)/', $uprid, $arr);
            $vids = [];
            $virtual_vids = [];
            for ($i = 0; $i < count($arr[1]); $i++) {
                if ($excl_virtual_options && \common\helpers\Attributes::is_virtual_option($oids[$i])) {
                    $virtual_vids[$oids[$i]] = $arr[1][$i];
                    continue;
                }
                $vids[$oids[$i]] = $arr[1][$i];
            }
            ksort($vids);
            return self::get_uprid($product_id, $vids);
        }
    }
    public static function normalize_id_excl_virtual($uprid, &$vids = [], &$virtual_vids = [])
    {
        return self::normalize_id($uprid, $vids, true, $virtual_vids);
    }
    public static function get_prid($uprid)
    {
        $pieces = explode('{', $uprid, 2);
        if (is_numeric($pieces[0])) {
            return intval($pieces[0]);
        } else {
            return false;
        }
    }
    public static function is_prid($uprid)
    {
        return strpos($uprid, '{') === false;
    }
    public static function get_uprid($prid, $params)
    {
        $uprid = $prid;
        if (is_array($params) && !strstr($prid, '{')) {
            //{{ ordered uprid from the box
            if (\yii\helpers\Array_Helper::is_indexed($params)) {
                ksort($params);
            } else {
                $_num_params = [];
                $_str_params = [];
                foreach ($params as $_k => $_v) {
                    if (is_numeric($_k)) {
                        $_num_params[$_k] = $_v;
                    } else {
                        $_str_params[$_k] = $_v;
                    }
                }
                ksort($_num_params);
                $params = $_num_params + $_str_params;
            }
            //}}
            foreach ($params as $option => $value) {
                $uprid = $uprid . '{' . $option . '}' . $value;
            }
        }
        return $uprid;
    }
    public static function is_valid_variant($uprid)
    {
        if (static::disabled_on_product($uprid)) {
            return true;
        }
        $inventory_uprid = static::normalize_inventory_id($uprid);
        if (strpos($inventory_uprid, '{') !== false) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
                if ($ext::is_restricted($inventory_uprid)) {
                    return false;
                }
            }
            $data = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS check_exist ' . 'FROM ' . TABLE_INVENTORY . ' ' . "where prid='" . (int) $inventory_uprid . "' " . "  AND products_id = '" . tep_db_input($inventory_uprid) . "' " . '  AND IFNULL(non_existent,0)=0'));
            return $data['check_exist'] > 0;
        }
        return true;
    }
    public static function get_inventory_id_by_uprid($uprid)
    {
        $data = tep_db_fetch_array(tep_db_query('select inventory_id from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($uprid) . "'"));
        return $data['inventory_id'];
    }
    public static function product_has_inventory($prid)
    {
        $data = tep_db_fetch_array(tep_db_query('select count(inventory_id) as total from ' . TABLE_INVENTORY . " where prid = '" . (int) $prid . "'"));
        return $data['total'];
    }
    public static function get_first_invetory($prid)
    {
        $data = tep_db_fetch_array(tep_db_query('select products_id from ' . TABLE_INVENTORY . " where prid = '" . (int) $prid . "' limit 1"));
        return $data['products_id'];
    }
    public static function get_inventory_price_prefix_by_uprid($uprid)
    {
        $data = tep_db_fetch_array(tep_db_query('select price_prefix from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($uprid) . "'"));
        return $data['price_prefix'] ?? null;
    }
    public static function get_inventory_price_by_uprid($uprid, $qty = 1, $inventory_price = 0, $curr_id = 0, $group_id = 0)
    {
        return \common\models\Product\Price::get_instance($uprid)->get_inventory_price(['qty' => $qty, 'curr_id' => $curr_id, 'group_id' => $group_id]);
    }
    public static function get_inventory_full_price_by_uprid($uprid, $qty = 1, $inventory_full_price = 0, $curr_id = 0, $group_id = 0)
    {
        return \common\models\Product\Price::get_instance($uprid)->get_inventory_price(['qty' => $qty, 'curr_id' => $curr_id, 'group_id' => $group_id]);
    }
    public static function get_inventory_weight_by_uprid($uprid)
    {
        $uprid = \common\helpers\Inventory::normalize_id_excl_virtual($uprid, $vids, $virtual_vids);
        $data = tep_db_fetch_array(tep_db_query('select inventory_weight from ' . TABLE_INVENTORY . " where products_id like '" . tep_db_input($uprid) . "' order by inventory_weight asc limit 1"));
        if (!isset($data['inventory_weight'])) {
            $data['inventory_weight'] = 0;
        }
        if (is_array($virtual_vids)) {
            $attributes_weight_percents = [];
            $attributes_weight_fixed = 0;
            foreach ($virtual_vids as $option => $value) {
                $option_arr = explode('-', $option);
                $attribute_weight_query = tep_db_query('select products_attributes_id, options_values_price, price_prefix, products_attributes_weight, products_attributes_weight_prefix from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) ($option_arr[1] > 0 ? $option_arr[1] : self::get_prid($uprid)) . "' and options_id = '" . (int) $option_arr[0] . "' and options_values_id = '" . (int) $value . "'");
                $attribute_weight = tep_db_fetch_array($attribute_weight_query);
                if (tep_not_null($attribute_weight['products_attributes_weight'])) {
                    if ($attribute_weight['products_attributes_weight_prefix'] == '-%') {
                        $attributes_weight_percents[] = 1 - $attribute_weight['products_attributes_weight'] / 100;
                    } elseif ($attribute_weight['products_attributes_weight_prefix'] == '+%') {
                        $attributes_weight_percents[] = 1 + $attribute_weight['products_attributes_weight'] / 100;
                    } else {
                        $attributes_weight_fixed += ($attribute_weight['products_attributes_weight_prefix'] == '-' ? -1 : 1) * $attribute_weight['products_attributes_weight'];
                    }
                }
            }
            $data['inventory_weight'] += $attributes_weight_fixed;
            foreach ($attributes_weight_percents as $attributes_weight_percent) {
                $data['inventory_weight'] *= $attributes_weight_percent;
            }
        }
        return $data['inventory_weight'];
    }
    public static function get_inventory_price($inventory_id, $currency_id = 0, $group_id = 0, $default = '')
    {
        if (USE_MARKET_PRICES != 'True') {
            $currency_id = 0;
        }
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            $group_id = 0;
        }
        if ($currency_id == 0 && $group_id == 0) {
            $data_query = tep_db_query('select inventory_price from ' . TABLE_INVENTORY . " where  inventory_id  = '" . $inventory_id . "'");
        } else {
            $data_query = tep_db_query('select inventory_group_price as inventory_price from ' . TABLE_INVENTORY_PRICES . " where inventory_id = '" . $inventory_id . "' and currencies_id = '" . $currency_id . "' and groups_id = '" . $group_id . "'");
        }
        $data = tep_db_fetch_array($data_query);
        if ($data['inventory_price'] == '' && $default != '') {
            $data['inventory_price'] = $default;
        }
        return $data['inventory_price'];
    }
    public static function get_inventory_full_price($inventory_id, $currency_id = 0, $group_id = 0, $default = '')
    {
        if (USE_MARKET_PRICES != 'True') {
            $currency_id = 0;
        }
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            $group_id = 0;
        }
        if ($currency_id == 0 && $group_id == 0) {
            $data_query = tep_db_query('select inventory_full_price from ' . TABLE_INVENTORY . " where  inventory_id  = '" . $inventory_id . "'");
        } else {
            $data_query = tep_db_query('select inventory_full_price from ' . TABLE_INVENTORY_PRICES . " where inventory_id = '" . $inventory_id . "' and currencies_id = '" . $currency_id . "' and groups_id = '" . $group_id . "'");
        }
        $data = tep_db_fetch_array($data_query);
        if ($data['inventory_full_price'] == '' && $default != '') {
            $data['inventory_full_price'] = $default;
        }
        return $data['inventory_full_price'];
    }
    public static function get_inventory_discount_price($inventory_id, $currency_id = 0, $group_id = 0, $default = '')
    {
        if (USE_MARKET_PRICES != 'True') {
            $currency_id = 0;
        }
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            $group_id = 0;
        }
        if ($currency_id == 0 && $group_id == 0) {
            $data_query = tep_db_query('select inventory_discount_price from ' . TABLE_INVENTORY . " where  inventory_id  = '" . $inventory_id . "'");
        } else {
            $data_query = tep_db_query('select inventory_group_discount_price as inventory_discount_price from ' . TABLE_INVENTORY_PRICES . " where inventory_id = '" . $inventory_id . "' and currencies_id = '" . $currency_id . "' and groups_id = '" . $group_id . "'");
        }
        $data = tep_db_fetch_array($data_query);
        if ($data['inventory_discount_price'] == '' && $default != '') {
            $data['inventory_discount_price'] = $default;
        }
        return $data['inventory_discount_price'];
    }
    public static function get_inventory_discount_full_price($inventory_id, $currency_id = 0, $group_id = 0, $default = '')
    {
        if (USE_MARKET_PRICES != 'True') {
            $currency_id = 0;
        }
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            $group_id = 0;
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('AttributesQuantity', 'allowed')) {
            return $ext::get_inventory_discount($inventory_id, $currency_id, $group_id, $default);
        }
        return $default;
    }
    public static function get_inventory_uprid($ar, $idx)
    {
        $next = false;
        foreach ($ar as $key => $value) {
            if ($next) {
                $next = $key;
                break;
            }
            if ($key == $idx) {
                $next = true;
            }
        }
        if ($next !== false && $next !== true) {
            $sub = self::get_inventory_uprid($ar, $next);
        }
        //}
        $ret = [];
        if (isset($ar[$idx]) && is_array($ar[$idx])) {
            for ($i = 0, $n = sizeof($ar[$idx]); $i < $n; $i++) {
                if (is_array($sub ?? null)) {
                    for ($j = 0, $m = sizeof($sub); $j < $m; $j++) {
                        $ret[] = '{' . $idx . '}' . $ar[$idx][$i] . $sub[$j];
                    }
                } else {
                    $ret[] = '{' . $idx . '}' . $ar[$idx][$i];
                }
            }
        }
        return $ret;
    }
    public static function get_details($products_id, $current_uprid, $params = [])
    {
        global $languages_id;
        $currencies = \Yii::$container->get('currencies');
        if (!(($params['qty'] ?? 0) > 0)) {
            $params['qty'] = 1;
        }
        if (strpos($current_uprid, '{') === false) {
            $current_uprid = $products_id;
        }
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        $products_id = \common\helpers\Inventory::get_prid($products_id);
        $inventory_array = [];
        $product_price = null;
        $product_query = tep_db_query('select products_id, products_price, products_tax_class_id, products_quantity, products_price_full, stock_indication_id from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'");
        if ($product = tep_db_fetch_array($product_query)) {
            $products_attributes_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_OPTIONS . ' popt, ' . TABLE_PRODUCTS_ATTRIBUTES . " patrib where patrib.products_id = '" . (int) $product['products_id'] . "' and patrib.options_id = popt.products_options_id and popt.language_id = '" . (int) $languages_id . "'");
            $products_attributes = tep_db_fetch_array($products_attributes_query);
            if ($products_attributes['total'] > 0) {
                $inventory_query = tep_db_query('select * from ' . TABLE_INVENTORY . " i where non_existent = '0' " . \common\helpers\Inventory::get_sql_inventory_restrictions(['i', 'ip']) . " and prid = '" . (int) $product['products_id'] . "' order by " . ($product['products_price_full'] ? ' inventory_full_price' : ' inventory_price'));
                while ($inventory = tep_db_fetch_array($inventory_query)) {
                    $price_instance = \common\models\Product\Price::get_instance($inventory['products_id']);
                    //                    $inventory_price = $priceInstance->getInventoryPrice(['qty' => $params['qty'], 'id' => $params['id']]);
                    $inventory_price = $price_instance->get_inventory_price($params);
                    //$inventory['inventory_price'] = \common\helpers\Inventory::get_inventory_price_by_uprid($inventory['products_id'], $params['qty'], $inventory['inventory_price']);
                    //$inventory['inventory_full_price'] = \common\helpers\Inventory::get_inventory_full_price_by_uprid($inventory['products_id'], $params['qty'], $inventory['inventory_full_price']);
                    if ($price_instance->calculate_full_price && $inventory_price == -1) {
                        continue;
                        // Disabled for specific group
                    } elseif ($inventory_price == -1) {
                        continue;
                        // Disabled for specific group
                    }
                    $inventory['actual_price'] = $inventory_price;
                    $inventory_special_price = $price_instance->get_inventory_special_price(['qty' => $params['qty']]);
                    if ($inventory_special_price !== false) {
                        $inventory['actual_price'] = $inventory_special_price;
                    }
                    /*
                                       if ($product['products_price_full']) {
                                           $inventory['actual_price'] = $inventory['inventory_full_price'];
                                           if ($special_price !== false) {
                                               // if special - subtract difference
                                               $inventory['actual_price'] -= $product_price_old - $special_price;
                                           }
                                       } else {
                                           if (\common\helpers\Inventory::get_inventory_price_prefix_by_uprid($products_id) == '-') {
                                               $inventory['actual_price'] = $product_price - $inventory['inventory_price'];
                                               if ($special_price !== false) {
                                                   $inventory['actual_price'] = $special_price - $inventory['inventory_price'];
                                               }
                                           } else {
                                               $inventory['actual_price'] = $product_price + $inventory['inventory_price'];
                                               if ($special_price !== false) {
                                                   $inventory['actual_price'] = $special_price + $inventory['inventory_price'];
                                               }
                                           }
                                       }
                    */
                    $inventory['attributes_names'] = $inventory['attributes_names_short'] = '';
                    if (strpos($inventory['products_id'], '{') !== false) {
                        $ar = preg_split('/[\{\}]/', $inventory['products_id']);
                        for ($i = 1; $i < sizeof($ar); $i = $i + 2) {
                            $option = tep_db_fetch_array(tep_db_query('select products_options_name as name from ' . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . (int) $ar[$i] . "' and language_id  = '" . (int) $languages_id . "'"));
                            $options_values = tep_db_fetch_array(tep_db_query('select pov.products_options_values_name as name from ' . TABLE_PRODUCTS_OPTIONS_VALUES . " pov where products_options_values_id  = '" . (int) $ar[$i + 1] . "' and language_id  = '" . (int) $languages_id . "'"));
                            if ($inventory['attributes_names'] == '') {
                                $inventory['attributes_names'] .= $option['name'] . ': ' . $options_values['name'];
                            } else {
                                $inventory['attributes_names'] .= '; ' . $option['name'] . ': ' . $options_values['name'];
                            }
                            if ($inventory['attributes_names_short'] == '') {
                                $inventory['attributes_names_short'] .= $options_values['name'];
                            } else {
                                $inventory['attributes_names_short'] .= '; ' . $options_values['name'];
                            }
                        }
                    }
                    $inventory['stock_indicator'] = \common\classes\Stock_Indication::product_info(['products_id' => $inventory['products_id'], 'products_quantity' => $inventory['products_quantity'], 'stock_indication_id' => isset($inventory['stock_indication_id']) ? $inventory['stock_indication_id'] : null]);
                    if (!($inventory['products_quantity'] > 0)) {
                        $inventory['attributes_names'] .= ' - ' . strip_tags($inventory['stock_indicator']['stock_indicator_text_short']);
                        $inventory['attributes_names_short'] .= ' - ' . strip_tags($inventory['stock_indicator']['stock_indicator_text_short']);
                    }
                    if ($inventory['products_id'] == $current_uprid) {
                        $product_price = $inventory['actual_price'];
                        $special_price = $inventory_special_price;
                        $inventory['selected'] = true;
                    } else {
                        $inventory['selected'] = false;
                    }
                    $inventory['simple_actual_price'] = $inventory['actual_price'];
                    $inventory['actual_price'] = $currencies->display_price($inventory['actual_price'], \common\helpers\Tax::get_tax_rate($product['products_tax_class_id']));
                    $inventory_array[$inventory['products_id']] = $inventory;
                }
                if ($inventory_array) {
                    $is_selected = false;
                    foreach ($inventory_array as $id => $values) {
                        $is_selected = $is_selected || $values['selected'];
                    }
                    if (!$is_selected) {
                        $s_inv = \yii\helpers\Array_Helper::get_column($inventory_array, 'simple_actual_price');
                        $min = min($s_inv);
                        $ids = array_search($min, $s_inv);
                        if ($ids) {
                            $inventory_array[$ids]['selected'] = true;
                        }
                    }
                }
            } else {
                $price_instance = \common\models\Product\Price::get_instance($product['products_id']);
                $product_price = $price_instance->get_product_price(['qty' => $params['qty']]);
                $special_price = $price_instance->get_product_special_price(['qty' => $params['qty']]);
                //$product_price = \common\helpers\Product::get_products_price($product['products_id'], 1, $product['products_price']);
                //$special_price = \common\helpers\Product::get_products_special_price($product['products_id'], 1);
                //$product_price_old = $product_price;
            }
            if (is_null($product_price)) {
                $price_instance = \common\models\Product\Price::get_instance($product['products_id']);
                $product_price = $price_instance->get_product_price(['qty' => $params['qty']]);
                $special_price = $price_instance->get_product_special_price(['qty' => $params['qty']]);
            }
            //var_dump($current_uprid);
            //echo'<pre>';print_r($inventory_array);
            /*
                                    $check_inventory = tep_db_fetch_array(tep_db_query("select inventory_id, min(if(price_prefix = '-', -inventory_price, inventory_price)) as inventory_price, min(inventory_full_price) as inventory_full_price from " . TABLE_INVENTORY . " i where products_id like '" . tep_db_input($current_uprid) . "' and non_existent = '0' " . \common\helpers\Inventory::get_sql_inventory_restrictions(array('i', 'ip'))  . " limit 1"));
                                    if ($check_inventory['inventory_id']) {
                                        $check_inventory['inventory_price'] = \common\helpers\Inventory::get_inventory_price_by_uprid($current_uprid, $params['qty'], $check_inventory['inventory_price']);
                                        $check_inventory['inventory_full_price'] = \common\helpers\Inventory::get_inventory_full_price_by_uprid($current_uprid, $params['qty'], $check_inventory['inventory_full_price']);
                                        if ($product['products_price_full'] && $check_inventory['inventory_full_price'] != -1) {
                                            $product_price = $check_inventory['inventory_full_price'];
                                            if ($special_price !== false) {
                                                // if special - add difference
                                                $special_price += $product_price - $product_price_old;
                                            }
                                        } elseif ($check_inventory['inventory_price'] != -1) {
                                            $product_price += $check_inventory['inventory_price'];
                                            if ($special_price !== false) {
                                                $special_price += $check_inventory['inventory_price'];
                                            }
                                        }
                                    }*/
        }
        //$product_query = tep_db_query("select products_id, products_price, products_tax_class_id, stock_indication_id, products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");
        $_backup_products_quantity = 0;
        $_backup_stock_indication_id = 0;
        if ($product) {
            $_backup_products_quantity = $product['products_quantity'];
            $_backup_stock_indication_id = $product['stock_indication_id'];
        }
        $check_inventory = tep_db_fetch_array(tep_db_query('select inventory_id, products_quantity, stock_indication_id ' . 'from ' . TABLE_INVENTORY . ' ' . "where products_id like '" . tep_db_input($current_uprid) . "' " . 'limit 1'));
        $get_dynamic_prop_r = tep_db_query('SELECT ' . '  IF(LENGTH(i.products_model)>0,i.products_model, p.products_model) AS products_model, ' . '  IF(LENGTH(i.products_upc)>0,i.products_upc, p.products_upc) AS products_upc, ' . '  IF(LENGTH(i.products_ean)>0,i.products_ean, p.products_ean) AS products_ean, ' . '  IF(LENGTH(i.products_asin)>0,i.products_asin, p.products_asin) AS products_asin, ' . '  IF(LENGTH(i.products_isbn)>0,i.products_isbn, p.products_isbn) AS products_isbn ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . ' LEFT JOIN ' . TABLE_INVENTORY . " i ON i.prid=p.products_id AND i.products_id='" . tep_db_input($current_uprid) . "' " . "WHERE p.products_id='" . intval($products_id) . "' ");
        $dynamic_prop = [];
        if (tep_db_num_rows($get_dynamic_prop_r) > 0) {
            $dynamic_prop = tep_db_fetch_array($get_dynamic_prop_r);
        }
        if (isset($inventory_array[$current_uprid])) {
            $stock_indicator = $inventory_array[$current_uprid]['stock_indicator'];
        } else {
            $stock_indicator = \common\classes\Stock_Indication::product_info(['products_id' => $current_uprid, 'products_quantity' => $check_inventory['inventory_id'] ? $check_inventory['products_quantity'] : $_backup_products_quantity, 'stock_indication_id' => isset($check_inventory['stock_indication_id']) ? $check_inventory['stock_indication_id'] : $_backup_stock_indication_id]);
        }
        $stock_indicator_public = $stock_indicator['flags'];
        $stock_indicator_public['quantity_max'] = \common\helpers\Product::filter_product_order_quantity($current_uprid, $stock_indicator['max_qty'], true);
        $stock_indicator_public['stock_code'] = $stock_indicator['stock_code'];
        $stock_indicator_public['text_stock_code'] = $stock_indicator['text_stock_code'];
        $stock_indicator_public['stock_indicator_text'] = $stock_indicator['stock_indicator_text'];
        $stock_indicator_public['allow_out_of_stock_add_to_cart'] = $stock_indicator['allow_out_of_stock_add_to_cart'];
        if ($stock_indicator_public['request_for_quote']) {
            $special_price = false;
        }
        $return_data = ['product_valid' => strpos($current_uprid, '{') !== false ? '1' : '0', 'product_price' => $currencies->display_price($product_price, \common\helpers\Tax::get_tax_rate($product['products_tax_class_id']), 1, $special_price === false ? true : ''), 'product_unit_price' => $currencies->display_price_clear($product_price, 0), 'tax' => \common\helpers\Tax::get_tax_rate($product['products_tax_class_id']), 'special_price' => $special_price !== false ? $currencies->display_price($special_price, \common\helpers\Tax::get_tax_rate($product['products_tax_class_id']), 1, true) : '', 'special_unit_price' => $special_price !== false ? $currencies->display_price_clear($special_price, 0) : '', 'product_qty' => $check_inventory['inventory_id'] ? $check_inventory['products_quantity'] : ($product['products_quantity'] ? $product['products_quantity'] : '0'), 'product_in_cart' => \frontend\design\Info::check_product_in_cart($current_uprid), 'current_uprid' => $current_uprid, 'inventory_array' => $inventory_array, 'stock_indicator' => $stock_indicator_public, 'dynamic_prop' => $dynamic_prop];
        return $return_data;
    }
    public static function get_sql_inventory_restrictions($table_prefixes = ['i', 'ip'])
    {
        // " . \common\helpers\Product::get_sql_product_restrictions(array('p'=>'')) . "
        $def = ['i', 'ip'];
        if (!is_array($table_prefixes)) {
            $table_prefixes['i'] = trim($table_prefixes) != '' ? rtrim($table_prefixes, '.') . '.' : '';
        } else {
            foreach ($table_prefixes as $k => $v) {
                if (is_integer($k)) {
                    $k = $def[$k];
                }
                $table_prefixes[$k] = trim($v) != '' ? rtrim($v, '.') . '.' : '';
            }
        }
        foreach ($def as $k) {
            if (!isset($table_prefixes[$k])) {
                $table_prefixes[$k] = $k . '.';
            }
        }
        $where_str = '';
        $tmp = \common\classes\Stock_Indication::get_hidden_ids();
        if (count($tmp) > 0) {
            $where_str .= ' and ' . $table_prefixes['i'] . "stock_indication_id not in ('" . implode("','", $tmp) . "')";
        }
        return $where_str;
    }
    public static function get_inventory_name_by_uprid($uprid)
    {
        $data = tep_db_fetch_array(tep_db_query('select products_name from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($uprid) . "'"));
        return $data['products_name'];
    }
    public static function update_name_by_uprid($uprid = [], $language_id = '')
    {
        if (!empty($uprid) && !is_array($uprid)) {
            $uprid = [$uprid];
        }
        $q = \common\models\Inventory::find();
        //->asArray();
        if (!empty($uprid)) {
            $q->and_where(['products_id' => $uprid]);
        }
        foreach ($q->all() as $inv) {
            $name = Product::get_products_name($inv->prid, $language_id);
            $name .= ' ' . implode(' ', self::get_inventory_attribute_name_list($inv->products_id, $language_id));
            if ($name != $inv->products_name) {
                $inv->products_name = $name;
                try {
                    $inv->save(false);
                } catch (\Exception $ex) {
                    \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG');
                }
            }
        }
        return true;
    }
    public static function create_empty_inventory($uprid)
    {
        $inventory = \common\models\Inventory::find_one(['products_id' => $uprid]);
        if (!$inventory) {
            $inventory = new \common\models\Inventory();
            $inventory->set_attributes(['products_id' => $uprid, 'prid' => (int) $uprid, 'products_quantity' => 0], false);
            return $inventory->save(false);
        }
        return true;
    }
    /**
     * Return array of attribute name - attribute value pairs for given Inventory Id
     * @global type $languages_id current Language Id
     * @param type $inventoryId Inventory Id
     * @return array array of strings of [attribute name]: [attribute value]
     */
    public static function get_inventory_attribute_name_list($inventory_id = 0, $lang_id = '')
    {
        global $languages_id;
        if (empty($lang_id) || (int) $lang_id == 0) {
            $lang_id = $languages_id;
        }
        $return = [];
        $inventory_id = self::normalize_inventory_id($inventory_id);
        if (strpos($inventory_id, '{') !== false) {
            $attribute_array = preg_split('/[\{\}]/', $inventory_id);
            for ($i = 1; $i < count($attribute_array); $i = $i + 2) {
                $po_record = \common\models\Products_Options::find()->as_array(true)->select('products_options_name')->where(['products_options_id' => (int) $attribute_array[$i]])->and_where(['language_id' => (int) $lang_id])->cache(defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? Product::PRODUCT_RECORD_CACHE : -1)->column();
                $pov_record = \common\models\Products_Options_Values::find()->as_array(true)->select('products_options_values_name')->where(['products_options_values_id' => (int) $attribute_array[$i + 1]])->and_where(['language_id' => (int) $lang_id])->cache(defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? Product::PRODUCT_RECORD_CACHE : -1)->column();
                $return[] = ((is_array($po_record) and isset($po_record[0])) ? ucfirst($po_record[0]) : 'N/A') . ': ' . ((is_array($pov_record) and isset($pov_record[0])) ? ucfirst($pov_record[0]) : 'N/A');
            }
        }
        return $return;
    }
    /**
     * Returning Product Inventory Id if isInventory check is passed, Product Id otherwise
     * @param mixed $inventoryId Product Inventory Id
     * @return string Product Id or Product Inventory Id
     */
    public static function get_inventory_id($inventory_id = 0)
    {
        $inventory_id = self::normalize_inventory_id($inventory_id);
        if (self::is_inventory($inventory_id) != true) {
            $inventory_id = (int) $inventory_id;
        }
        return trim($inventory_id);
    }
    /**
     * Checking does $inventoryId is belongs to existing Inventory Record
     * Behaviour: depending on \common\helpers\Acl::checkExtensionAllowed('Inventory', 'allowed') configuration value
     * @param mixed $inventoryId Product Inventory Id
     * @return boolean true if Inventory Record found, false otherwise
     */
    public static function is_inventory($inventory_id = 0)
    {
        $return = false;
        if (\common\helpers\Extensions::is_allowed('Inventory')) {
            $inventory_record = self::get_record($inventory_id);
            if ($inventory_record instanceof \common\models\Inventory) {
                $product_attribute_array = \common\models\Products_Attributes::find()->where(['products_id' => (int) $inventory_id])->cache(defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? Product::PRODUCT_RECORD_CACHE : -1)->exists();
                //if (count($productAttributeArray) > 0) {
                if (!empty($product_attribute_array)) {
                    $return = true;
                }
                unset($product_attribute_array);
            }
            unset($inventory_record);
            unset($inventory_id);
        }
        return $return;
    }
    /**
     * Get Inventory record
     * @param mixed $inventoryId Inventory Id or instance of Inventory model
     * @return mixed instance of Inventory model or null
     */
    public static function get_record($inventory_id = 0)
    {
        return $inventory_id instanceof \common\models\Inventory ? $inventory_id : \common\models\Inventory::find_one(['products_id' => trim(self::normalize_inventory_id($inventory_id))]);
    }
    public static function force_copy(int $from_product_id, int $to_product_id)
    {
        tep_db_query('delete from inventory_prices where inventory_id in (select inventory_id from inventory where prid=' . (int) $to_product_id . ')');
        tep_db_query('delete from inventory where prid=' . (int) $to_product_id . '');
        $m = new \common\models\Inventory();
        $fields = $m->get_attributes();
        foreach (['inventory_id', 'products_name', 'products_quantity', 'allocated_stock_quantity', 'temporary_stock_quantity', 'warehouse_stock_quantity', 'suppliers_stock_quantity', 'ordered_stock_quantity'] as $skip_field) {
            unset($fields[$skip_field]);
        }
        $fields = array_keys($fields);
        $f_len = strlen((string) $from_product_id) + 1;
        $raw = 'insert into inventory (' . implode(', ', $fields) . ') select * from (' . 'select ' . str_replace(['i1.prid', 'i1.products_id'], [$to_product_id, 'concat(' . $to_product_id . ', substr(i1.products_id, ' . $f_len . '))'], 'i1.' . implode(', i1.', $fields)) . ' from inventory i1  ' . ' where i1.prid=' . $from_product_id . ' ' . ') a';
        \Yii::$app->get_db()->create_command($raw)->execute();
        $m = new \common\models\Inventory_Prices();
        $fields = $m->get_attributes();
        $fields = array_keys($fields);
        $f_len = strlen((string) $from_product_id) + 1;
        $t_len = strlen((string) $to_product_id) + 1;
        $raw = 'insert into inventory_prices (' . implode(', ', $fields) . ') select * from (' . 'select ' . str_replace(['ip.inventory_id', 'ip.prid', 'ip.products_id'], ['i2.inventory_id', $to_product_id, 'i2.products_id'], 'ip.' . implode(', ip.', $fields)) . ' from inventory_prices ip inner join inventory i1 ' . ' on ip.inventory_id=i1.inventory_id inner join inventory i2 ' . ' on substr(i1.products_id, ' . $f_len . ')=substr(i2.products_id, ' . $t_len . ') and i2.prid=' . $to_product_id . ' ' . ' where i1.prid=' . $from_product_id . ' ' . ') a';
        \Yii::$app->get_db()->create_command($raw)->execute();
        /** @var \common\extensions\UserGroupsRestrictions\models\GroupsInventory $modelClass */
        $model_class = \common\helpers\Extensions::get_model('UserGroupsRestrictions', 'GroupsInventory');
        if (!empty($model_class)) {
            $model_class::delete_all(['prid' => $to_product_id]);
            $source_collection = $model_class::find_all(['prid' => $from_product_id]);
            foreach ($source_collection as $origin_model) {
                $__data = $origin_model->get_attributes();
                $__data['products_id'] = preg_replace('/^' . preg_quote((int) $from_product_id) . "(\\{.+)\$/", (int) $to_product_id . '$1', $__data['products_id']);
                $__data['prid'] = (int) $to_product_id;
                $copy_model = new $model_class();
                $copy_model->set_attributes($__data, false);
                $copy_model->load_default_values(true);
                $copy_model->save(false);
            }
        }
    }
}