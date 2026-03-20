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

use common\models\Design_Boxes_Settings;
use common\models\Design_Boxes_Settings_Tmp;
use frontend\design\Info;
class Custom_Bundles
{
    public static function get_details($params)
    {
        global $languages_id;
        if (!$params['box_id']) {
            return '';
        }
        if (Info::is_admin()) {
            $design_boxes_settings = Design_Boxes_Settings_Tmp::find();
        } else {
            $design_boxes_settings = Design_Boxes_Settings::find();
        }
        $box_settings = $design_boxes_settings->where(['box_id' => $params['box_id']])->as_array()->all();
        $settings = [];
        foreach ($box_settings as $set) {
            $settings[$set['language_id']][$set['setting_name']] = $set['setting_value'];
        }
        if (!$params['products_id']) {
            return '';
        }
        $xsell_type_id = 0;
        if (isset($settings[0]['xsell_type_id'])) {
            $xsell_type_id = (int) $settings[0]['xsell_type_id'];
        }
        $max = isset($settings[0]['max_products']) ? $settings[0]['max_products'] : 10;
        $currencies = \Yii::$container->get('currencies');
        if (!is_array($params['custom_bundles'] ?? null)) {
            $params['custom_bundles'] = [];
        }
        $bundle_products = [];
        $check_configurator = \common\models\Products::find()->select('products_pctemplates_id')->where('products_id=:products_id', [':products_id' => \common\helpers\Inventory::get_prid($params['products_id'])])->as_array()->one();
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductBundles', 'allowed')) {
            $bundle_products = $ext::get_bundle_products(\common\helpers\Inventory::get_prid($params['products_id']), \common\classes\platform::current_id());
        }
        if (!$check_configurator['products_pctemplates_id'] && !(count($bundle_products) > 0)) {
            if ($xsell_model = \common\helpers\Extensions::get_model('UpSell', 'ProductsXsell')) {
                $c_w = ['exists', $xsell_model::find()->alias('xp')->and_where('p.products_id = xp.xsell_id')->and_where(['xp.products_id' => (int) $params['products_id'], 'xp.xsell_type_id' => $xsell_type_id])->and_where(['not in', 'p.products_id', array_map('intval', $params['custom_bundles'])])];
            } else {
                $c_w = '';
            }
            $q = new \common\components\Products_Query(['limit' => (int) $max, 'customAndWhere' => $c_w, 'orderBy' => ['FIELD (products_id, ' . (int) $params['products_id'] . ') DESC' => '']]);
            $settings['listing_type'] = 'cross-sell-' . $xsell_type_id;
            $settings['options_prefix'] = 'list';
            $all_products = Info::get_list_products_details($q->build_query()->all_ids(), $settings);
            $custom_bundle_set_products = Info::get_list_products_details(array_map('intval', $params['custom_bundles']), $settings);
            $parent_products_id = $params['products_id'];
            $custom_bundle_full_price = 0;
            /*$listing_sql_array = \frontend\design\ListingSql::get_listing_sql_array('catalog/all-products');
            
                        $parent_products_id = $params['products_id'];
                        $products_query = tep_db_query("select p.products_id, p.order_quantity_minimal, p.order_quantity_max, p.order_quantity_step, p.stock_indication_id,  p.is_virtual, p.products_image, if(length(pd1.products_name), pd1.products_name, pd.products_name) as products_name, if(length(pd1.products_description_short), pd1.products_description_short, pd.products_description_short) as products_description_short, p.products_tax_class_id, p.products_price, p.products_quantity, p.products_model from " . $listing_sql_array['from'] . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCTS_DESCRIPTION . " pd on pd.products_id = p.products_id and pd.language_id = '" . (int) $languages_id . "' and pd.platform_id = '".intval(\common\classes\platform::defaultId())."' left join " . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd1.products_id = p.products_id and pd1.language_id = '" . (int) $languages_id . "' and pd1.platform_id = '" . intval(\Yii::$app->get('platform')->config()->getPlatformToDescription()) . "' " . $listing_sql_array['left_join'] . " where p.products_id not in ('" . implode("','", array_map('intval', $params['custom_bundles'])) . "') and p.products_id <> '" . (int) $params['products_id'] . "' " . $listing_sql_array['where'] . " order by p.sort_order, products_name limit 10");
                        $all_products = \frontend\design\Info::getProducts($products_query);
            
                        $custom_bundle_products = array();
                        $custom_bundle_full_price = 0;
                        $custom_bundle_sets_query = tep_db_query("select p.products_id, p.order_quantity_minimal, p.order_quantity_max, p.order_quantity_step, p.stock_indication_id,  p.is_virtual, p.products_image, if(length(pd1.products_name), pd1.products_name, pd.products_name) as products_name, if(length(pd1.products_description_short), pd1.products_description_short, pd.products_description_short) as products_description_short, p.products_tax_class_id, p.products_price, p.products_quantity, p.products_model from " . $listing_sql_array['from'] . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCTS_DESCRIPTION . " pd on pd.products_id = p.products_id and pd.language_id = '" . (int) $languages_id . "' and pd.platform_id = '".intval(\common\classes\platform::defaultId())."' left join " . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd1.products_id = p.products_id and pd1.language_id = '" . (int) $languages_id . "' and pd1.platform_id = '" . intval(\Yii::$app->get('platform')->config()->getPlatformToDescription()) . "' " . $listing_sql_array['left_join'] . " where p.products_id in ('" . implode("','", array_map('intval', array_merge([$parent_products_id], $params['custom_bundles']))) . "') " . $listing_sql_array['where'] . " order by p.sort_order, products_name");
                        $custom_bundle_set_products = \frontend\design\Info::getProducts($custom_bundle_sets_query);*/
            $all_filled_array = [];
            $stock_indicators_ids = [];
            $stock_indicators_array = [];
            foreach ($custom_bundle_set_products as $custom_bundle_sets) {
                $products_id = $custom_bundle_sets['products_id'];
                $params['custom_bundles_qty'][$products_id] = $params['custom_bundles_qty'][$products_id] ?? null;
                if ($products_id == $parent_products_id) {
                    $attributes = $params['id'] ?? null;
                } elseif (is_array($params['custom_bundles_attr'][$products_id])) {
                    $attributes = $params['custom_bundles_attr'][$products_id];
                } else {
                    $attributes = [];
                }
                $uprid = \common\helpers\Inventory::normalize_id(\common\helpers\Inventory::get_uprid($products_id, $attributes));
                unset($params['products_id']);
                $price_instance = \common\models\Product\Price::get_instance($uprid);
                $product_price = $price_instance->get_inventory_price(['qty' => $params['custom_bundles_qty'][$products_id]]);
                $special_price = $price_instance->get_inventory_special_price(['qty' => $params['custom_bundles_qty'][$products_id]]);
                $actual_product_price = $product_price;
                $products_options_name_query = tep_db_query('select distinct p.products_id, p.products_tax_class_id, popt.products_options_id, popt.products_options_name from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_OPTIONS . ' popt, ' . TABLE_PRODUCTS_ATTRIBUTES . " patrib where p.products_id = '" . (int) $products_id . "' and patrib.products_id = p.products_id and patrib.options_id = popt.products_options_id and popt.language_id = '" . (int) $languages_id . "' order by popt.products_options_sort_order, popt.products_options_name");
                while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {
                    if (!isset($attributes[$products_options_name['products_options_id']])) {
                        $check = tep_db_fetch_array(tep_db_query('select max(pov.products_options_values_id) as values_id, count(pov.products_options_values_id) as values_count from ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS_OPTIONS_VALUES . " pov where pa.products_id = '" . (int) $products_id . "' and pa.options_id = '" . (int) $products_options_name['products_options_id'] . "' and pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . (int) $languages_id . "'"));
                        if ($check['values_count'] == 1) {
                            // if only one option value - it should be selected
                            $attributes[$products_options_name['products_options_id']] = $check['values_id'];
                        } else {
                            $attributes[$products_options_name['products_options_id']] = 0;
                        }
                    }
                }
                $all_filled = true;
                /*
                                if (isset($attributes) && is_array($attributes))
                                    foreach ($attributes as $value) {
                                        $all_filled = $all_filled && (bool) $value;
                                    }
                */
                $attributes_array = [];
                tep_db_data_seek($products_options_name_query, 0);
                while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {
                    $products_options_array = [];
                    $products_options_query = tep_db_query('select pa.products_attributes_id, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix ' . 'from ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov ' . "where pa.products_id = '" . (int) $products_id . "' and pa.options_id = '" . (int) $products_options_name['products_options_id'] . "' and pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . (int) $languages_id . "' " . 'order by pa.products_options_sort_order, pov.products_options_values_sort_order, pov.products_options_values_name');
                    while ($products_options = tep_db_fetch_array($products_options_query)) {
                        $comb_arr = $attributes;
                        $comb_arr[$products_options_name['products_options_id']] = $products_options['products_options_values_id'];
                        foreach ($comb_arr as $opt_id => $val_id) {
                            if (!($val_id > 0)) {
                                $comb_arr[$opt_id] = '0000000';
                            }
                        }
                        reset($comb_arr);
                        $mask = str_replace('0000000', '%', \common\helpers\Inventory::normalize_inventory_id(\common\helpers\Inventory::get_uprid($products_id, $comb_arr), $vids, $virtual_vids));
                        $check_inventory = tep_db_fetch_array(tep_db_query('select inventory_id, max(products_quantity) as products_quantity, stock_indication_id, stock_delivery_terms_id, ' . " min(if(price_prefix = '-', -inventory_price, inventory_price)) as inventory_price, min(inventory_full_price) as inventory_full_price " . 'from ' . TABLE_INVENTORY . ' i ' . "where products_id like '" . tep_db_input($mask) . "' and non_existent = '0' " . \common\helpers\Inventory::get_sql_inventory_restrictions(['i', 'ip']) . ' ' . 'order by products_quantity desc ' . 'limit 1'));
                        if (!$check_inventory['inventory_id'] || !$check_inventory['products_id']) {
                            continue;
                        }
                        $price_instance = \common\models\Product\Price::get_instance($mask);
                        $products_price = $price_instance->get_inventory_price($params);
                        if ($price_instance->calculate_full_price && $products_price == -1) {
                            continue;
                            // Disabled for specific group
                        } elseif ($products_price == -1) {
                            continue;
                            // Disabled for specific group
                        }
                        if ($virtual_vids) {
                            $virtual_attribute_price = \common\helpers\Attributes::get_virtual_attribute_price($products_id, $virtual_vids, $params['qty'], $products_price);
                            if ($virtual_attribute_price === false) {
                                continue;
                                // Disabled for specific group
                            } else {
                                $products_price += $virtual_attribute_price;
                            }
                        }
                        $products_options_array[] = ['id' => $products_options['products_options_values_id'], 'text' => $products_options['products_options_values_name'], 'price_diff' => 0];
                        $price_diff = $products_price - $actual_product_price;
                        if ($price_diff != '0') {
                            $products_options_array[sizeof($products_options_array) - 1]['text'] .= ' (' . ($price_diff < 0 ? '-' : '+') . $currencies->display_price(abs($price_diff), \common\helpers\Tax::get_tax_rate($products_options_name['products_tax_class_id']), 1, false) . ') ';
                        }
                        $products_options_array[sizeof($products_options_array) - 1]['price_diff'] = $price_diff;
                        $stock_indicator = \common\classes\Stock_Indication::product_info(['products_id' => $check_inventory['products_id'], 'products_quantity' => $check_inventory['inventory_id'] ? $check_inventory['products_quantity'] : '0', 'stock_indication_id' => isset($check_inventory['stock_indication_id']) ? $check_inventory['stock_indication_id'] : null, 'stock_delivery_terms_id' => isset($check_inventory['stock_delivery_terms_id']) ? $check_inventory['stock_delivery_terms_id'] : null]);
                        if (!($check_inventory['products_quantity'] > 0)) {
                            $products_options_array[sizeof($products_options_array) - 1]['params'] = ' class="outstock" data-max-qty="' . (int) $stock_indicator['max_qty'] . '"';
                            $products_options_array[sizeof($products_options_array) - 1]['text'] .= ' - ' . strip_tags($stock_indicator['stock_indicator_text_short']);
                        } else {
                            $products_options_array[sizeof($products_options_array) - 1]['params'] = ' class="outstock" data-max-qty="' . (int) $stock_indicator['max_qty'] . '"';
                        }
                    }
                    if ($attributes[$products_options_name['products_options_id']] > 0) {
                        $selected_attribute = $attributes[$products_options_name['products_options_id']];
                    } else {
                        $selected_attribute = false;
                    }
                    if (count($products_options_array) > 0) {
                        $all_filled = $all_filled && (bool) $attributes[$products_options_name['products_options_id']];
                        $attributes_array[] = ['title' => htmlspecialchars($products_options_name['products_options_name']), 'name' => 'custom_bundles_attr[' . $products_id . '][' . $products_options_name['products_options_id'] . ']', 'options' => $products_options_array, 'selected' => $selected_attribute];
                    }
                }
                $product_query = tep_db_query('select products_id, products_price, products_tax_class_id, stock_indication_id, stock_delivery_terms_id, products_quantity from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'");
                $_backup_products_quantity = 0;
                $_backup_stock_indication_id = $_backup_stock_delivery_terms_id = 0;
                if ($product = tep_db_fetch_array($product_query)) {
                    $_backup_products_quantity = $product['products_quantity'];
                    $_backup_stock_indication_id = $product['stock_indication_id'];
                    $_backup_stock_delivery_terms_id = $product['stock_delivery_terms_id'];
                }
                $current_uprid = \common\helpers\Inventory::normalize_id(\common\helpers\Inventory::get_uprid($products_id, $attributes));
                $custom_bundle_sets['current_uprid'] = $current_uprid;
                $check_inventory = tep_db_fetch_array(tep_db_query('select inventory_id, products_quantity, stock_indication_id, stock_delivery_terms_id ' . 'from ' . TABLE_INVENTORY . ' ' . "where products_id like '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($current_uprid)) . "' " . 'limit 1'));
                \common\helpers\Php8::null_props($check_inventory, ['inventory_id', 'products_quantity', 'stock_indication_id', 'stock_delivery_terms_id']);
                $stock_indicator = \common\classes\Stock_Indication::product_info(['products_id' => $current_uprid, 'products_quantity' => $check_inventory['inventory_id'] ? $check_inventory['products_quantity'] : $_backup_products_quantity, 'stock_indication_id' => isset($check_inventory['stock_indication_id']) ? $check_inventory['stock_indication_id'] : $_backup_stock_indication_id, 'stock_delivery_terms_id' => isset($check_inventory['stock_delivery_terms_id']) ? $check_inventory['stock_delivery_terms_id'] : $_backup_stock_delivery_terms_id]);
                $stock_indicator_public = $stock_indicator['flags'];
                $stock_indicator_public['quantity_max'] = \common\helpers\Product::filter_product_order_quantity($current_uprid, $stock_indicator['max_qty'], true);
                $stock_indicator_public['stock_code'] = $stock_indicator['stock_code'];
                $stock_indicator_public['text_stock_code'] = $stock_indicator['text_stock_code'];
                $stock_indicator_public['stock_indicator_text'] = $stock_indicator['stock_indicator_text'];
                if ($stock_indicator_public['request_for_quote']) {
                    $special_price = false;
                }
                $custom_bundle_sets['stock_indicator'] = $stock_indicator_public;
                if (($stock_indicator['id'] ?? null) > 0) {
                    $stock_indicators_ids[] = $stock_indicator['id'];
                    $stock_indicators_array[$stock_indicator['id']] = $stock_indicator_public;
                }
                $custom_bundle_sets['all_filled'] = $all_filled;
                $custom_bundle_sets['product_qty'] = $check_inventory['inventory_id'] ? $check_inventory['products_quantity'] : ($product['products_quantity'] ? $product['products_quantity'] : '0');
                $all_filled_array[] = $custom_bundle_sets['all_filled'];
                $custom_bundle_sets['custom_bundles_qty'] = $params['custom_bundles_qty'][$products_id] > 0 ? $params['custom_bundles_qty'][$products_id] : 1;
                if ($special_price !== false) {
                    $custom_bundle_sets['price_old'] = $currencies->display_price($product_price, \common\helpers\Tax::get_tax_rate($custom_bundle_sets['products_tax_class_id']));
                    $custom_bundle_sets['price_special'] = $currencies->display_price($special_price, \common\helpers\Tax::get_tax_rate($custom_bundle_sets['products_tax_class_id']));
                    $custom_bundle_full_price += $currencies->display_price_clear($special_price, \common\helpers\Tax::get_tax_rate($custom_bundle_sets['products_tax_class_id']), $custom_bundle_sets['custom_bundles_qty']);
                } else {
                    $custom_bundle_sets['price'] = $currencies->display_price($product_price, \common\helpers\Tax::get_tax_rate($custom_bundle_sets['products_tax_class_id']));
                    $custom_bundle_full_price += $currencies->display_price_clear($product_price, \common\helpers\Tax::get_tax_rate($custom_bundle_sets['products_tax_class_id']), $custom_bundle_sets['custom_bundles_qty']);
                }
                $custom_bundle_sets['attributes_array'] = $attributes_array;
                if ($products_id != $parent_products_id) {
                    $custom_bundle_products[] = $custom_bundle_sets;
                }
            }
            if (count($stock_indicators_ids) > 0) {
                $stock_indicators_sorted = \common\classes\Stock_Indication::sort_stock_indicators($stock_indicators_ids);
                $custom_bundle_stock_indicator = $stock_indicators_array[$stock_indicators_sorted[count($stock_indicators_sorted) - 1]];
            } else {
                $custom_bundle_stock_indicator = $stock_indicator_public ?? null;
            }
            $return_data = ['product_valid' => count($all_filled_array) > 1 && min($all_filled_array) ? '1' : '0', 'custom_bundle_products' => $custom_bundle_products ?? null, 'all_products' => $all_products, 'stock_indicator' => $custom_bundle_stock_indicator, 'custom_bundle_full_price' => $currencies->format($custom_bundle_full_price, false)];
            return $return_data;
        }
    }
}