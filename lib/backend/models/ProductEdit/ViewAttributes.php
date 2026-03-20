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

class View_Attributes
{
    /**
     * @var \objectInfo
     */
    protected $product_info_ref;
    protected $is_parent_product = false;
    public function __construct($product_info, $is_parent_product = false)
    {
        $this->product_info_ref = $product_info;
        $this->is_parent_product = $is_parent_product;
    }
    public function populate_view($view)
    {
        $currencies = \Yii::$container->get('currencies');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $p_info = $this->product_info_ref;
        $selected_attributes = [];
        if (!empty($p_info->options_templates_id)) {
            $query = tep_db_query("select '' AS products_attributes_id, po.products_options_id, po.products_options_name, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.products_attributes_discount_price, pa.products_options_sort_order, pa.product_attributes_one_time, pa.products_attributes_weight, pa.products_attributes_weight_prefix, pa.products_attributes_units, pa.products_attributes_units_price, " . ' pa.options_templates_attributes_id, ' . ' pa.default_option_value, ' . ' pa.products_attributes_filename, pa.products_attributes_maxdays, pa.products_attributes_maxcount /*,count(distinct po.products_options_id) as options_count*/ ' . 'from ' . TABLE_OPTIONS_TEMPLATES_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS_OPTIONS . ' po, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov ' . "where pa.options_templates_id = '" . $p_info->options_templates_id . "' and pa.options_id = po.products_options_id and po.language_id = '" . $languages_id . "' and pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . $languages_id . "' " . 'order by po.products_options_sort_order, po.products_options_name, pa.products_options_sort_order, pov.products_options_values_sort_order, pov.products_options_values_name');
            $tmp = tep_db_fetch_array(tep_db_query('SELECT count(DISTINCT options_id) AS total FROM ' . TABLE_OPTIONS_TEMPLATES_ATTRIBUTES . " pa WHERE pa.options_templates_id = '" . $p_info->options_templates_id . "'"));
            $options_count = $tmp['total'];
        } elseif ($p_info->products_id) {
            $query = tep_db_query('select pa.products_attributes_id, po.products_options_id, po.products_options_name, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.products_attributes_discount_price, pa.products_options_sort_order, pa.product_attributes_one_time, pa.products_attributes_weight, pa.products_attributes_weight_prefix, pa.products_attributes_units, pa.products_attributes_units_price, ' . ' pa.default_option_value, ' . ' pa.products_attributes_filename, pa.products_attributes_maxdays, pa.products_attributes_maxcount /*,count(distinct po.products_options_id) as options_count*/ ' . 'from ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS_OPTIONS . ' po, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov ' . "where pa.products_id = '" . $p_info->products_id . "' and pa.options_id = po.products_options_id and po.language_id = '" . $languages_id . "' and pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . $languages_id . "' " . 'order by po.products_options_sort_order, po.products_options_name, pa.products_options_sort_order, pov.products_options_values_sort_order, pov.products_options_values_name');
            $tmp = tep_db_fetch_array(tep_db_query('SELECT count(DISTINCT options_id) AS total FROM ' . TABLE_PRODUCTS_ATTRIBUTES . " pa WHERE pa.products_id = '" . $p_info->products_id . "'"));
            $options_count = $tmp['total'];
        } else {
            $products_attributes_id = \Yii::$app->request->post('products_attributes_id', []);
            if (!is_array($products_attributes_id)) {
                $products_attributes_id = [];
            }
            $options_count = count($products_attributes_id);
            $query_selected = [];
            foreach ($products_attributes_id as $opt_id => $opt_data) {
                $query_selected += array_map(function ($val_id) use ($opt_id) {
                    return $opt_id . '-' . $val_id;
                }, array_keys($opt_data));
            }
            $query = tep_db_query("select '' AS products_attributes_id, po.products_options_id, po.products_options_name, pov.products_options_values_id, pov.products_options_values_name, '0.00' AS options_values_price, '+' AS price_prefix, '' AS products_attributes_discount_price, '' AS products_options_sort_order, '' AS product_attributes_one_time, '' AS products_attributes_weight, '' AS products_attributes_weight_prefix, '' AS products_attributes_units, '' AS products_attributes_units_price, " . ' 0 AS default_option_value, ' . " '' AS products_attributes_filename, '' AS products_attributes_maxdays, '' AS products_attributes_maxcount /*,count(distinct po.products_options_id) as options_count*/ " . 'from ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . ' o2v, ' . TABLE_PRODUCTS_OPTIONS . ' po, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov ' . "where po.language_id = '" . $languages_id . "' and pov.language_id = '" . $languages_id . "' " . 'AND o2v.products_options_id=po.products_options_id AND o2v.products_options_values_id=pov.products_options_values_id ' . (count($query_selected) > 0 ? "and CONCAT(po.products_options_id, '-', pov.products_options_values_id) IN ('" . implode("','", $query_selected) . "') " : ' and 1=0 ') . 'order by po.products_options_sort_order, po.products_options_name, pov.products_options_values_sort_order, pov.products_options_values_name');
        }
        $_tax = \common\helpers\Tax::get_tax_rate_value($p_info->products_tax_class_id);
        while ($data = tep_db_fetch_array($query)) {
            $without_inventory = !!$p_info->without_inventory;
            $is_virtual_option = \common\helpers\Attributes::is_virtual_option($data['products_options_id']);
            if (!empty($p_info->options_templates_id)) {
                $price0 = \common\helpers\Attributes::get_template_attributes_price($data['options_templates_attributes_id'], $view->default_currency, 0);
                $fullprice_tmp = $price0;
            } elseif (empty($p_info->products_id)) {
                $price0 = 0;
                $fullprice_tmp = 0;
            } elseif (!$without_inventory && \common\helpers\Extensions::is_allowed('Inventory') && $options_count == 1 && !$is_virtual_option) {
                // else price column is not shown in assigned attributes list
                // with inventory the attributes price is not saved!!! (can't be split)
                $price0 = \common\helpers\Inventory::get_inventory_price_by_uprid($p_info->products_id . '{' . $data['products_options_id'] . '}' . $data['products_options_values_id'], 1, 0, $view->default_currency);
                $fullprice_tmp = $price0 + \common\helpers\Product::get_products_price_for_edit($p_info->products_id);
                $data['price_prefix'] = \common\helpers\Inventory::get_inventory_price_prefix_by_uprid($p_info->products_id . '{' . $data['products_options_id'] . '}' . $data['products_options_values_id'], 1, 0, $view->default_currency);
            } else {
                $price0 = \common\helpers\Attributes::get_attributes_price($data['products_attributes_id'], $view->default_currency, 0);
                if ($is_virtual_option) {
                    $fullprice_tmp = $price0;
                } else {
                    $fullprice_tmp = $price0 + \common\helpers\Product::get_products_price_for_edit($p_info->products_id);
                }
            }
            if (strpos($data['price_prefix'], '%') !== false && $p_info->products_price_full != 1) {
                $gross_price_formatted = $net_price_formatted = \common\helpers\Output::percent($price0, '');
            } else {
                $net_price_formatted = $currencies->display_price($p_info->products_price_full == 1 ? $fullprice_tmp : $price0, 0, 1, false);
                $gross_price_formatted = $currencies->display_price($p_info->products_price_full == 1 ? $fullprice_tmp : $price0, (float) $_tax, 1, false);
            }
            $products_file_url = '';
            if (file_exists(DIR_FS_DOWNLOAD . $data['products_attributes_filename'])) {
                $products_file_url = tep_href_link(FILENAME_DOWNLOAD, 'filename=' . $data['products_attributes_filename']);
            }
            if (!isset($selected_attributes[$data['products_options_id']])) {
                $products_options_values = [];
                $products_options_values[] = ['products_attributes_id' => $data['products_attributes_id'], 'products_options_values_id' => $data['products_options_values_id'], 'products_options_values_name' => $data['products_options_values_name'], 'products_attributes_weight_prefix' => $data['products_attributes_weight_prefix'], 'products_attributes_weight' => $data['products_attributes_weight'], 'default_option_value' => $data['default_option_value'], 'products_file' => $data['products_attributes_filename'], 'products_file_url' => $products_file_url, 'products_attributes_maxdays' => $data['products_attributes_maxdays'], 'products_attributes_maxcount' => $data['products_attributes_maxcount'], 'price_prefix' => $data['price_prefix'], 'prices' => !empty($p_info->options_templates_id) ? \common\helpers\Attributes::get_template_attributes_prices($data['options_templates_attributes_id'], (float) $_tax) : \common\helpers\Attributes::get_attributes_prices($data['products_attributes_id'], (float) $_tax), 'net_price_formatted' => $net_price_formatted, 'gross_price_formatted' => $gross_price_formatted];
                $is_virtual_option = \common\helpers\Attributes::is_virtual_option($data['products_options_id']);
                $selected_attributes[$data['products_options_id']] = ['is_virtual_option' => $is_virtual_option, 'disable_action' => !$is_virtual_option && $this->is_parent_product, 'products_options_id' => $data['products_options_id'], 'products_options_name' => $data['products_options_name'], 'values' => $products_options_values, 'is_ordered_values' => !empty($data['products_options_sort_order']), 'ordered_value_ids' => ',' . $data['products_options_values_id']];
            } else {
                $selected_attributes[$data['products_options_id']]['values'][] = ['products_attributes_id' => $data['products_attributes_id'], 'products_options_values_id' => $data['products_options_values_id'], 'products_options_values_name' => $data['products_options_values_name'], 'products_attributes_weight_prefix' => $data['products_attributes_weight_prefix'], 'products_attributes_weight' => $data['products_attributes_weight'], 'default_option_value' => $data['default_option_value'], 'products_file' => $data['products_attributes_filename'], 'products_file_url' => $products_file_url, 'products_attributes_maxdays' => $data['products_attributes_maxdays'], 'products_attributes_maxcount' => $data['products_attributes_maxcount'], 'price_prefix' => $data['price_prefix'], 'prices' => !empty($p_info->options_templates_id) ? \common\helpers\Attributes::get_template_attributes_prices($data['options_templates_attributes_id'], (float) $_tax) : \common\helpers\Attributes::get_attributes_prices($data['products_attributes_id'], (float) $_tax), 'net_price_formatted' => $net_price_formatted, 'gross_price_formatted' => $gross_price_formatted];
                $selected_attributes[$data['products_options_id']]['ordered_value_ids'] .= ',' . $data['products_options_values_id'];
                $selected_attributes[$data['products_options_id']]['is_ordered_values'] = $selected_attributes[$data['products_options_id']]['is_ordered_values'] || !empty($data['products_options_sort_order']);
            }
        }
        foreach ($selected_attributes as $__opt_id => $__opt_data) {
            if ($__opt_data['is_ordered_values']) {
                $selected_attributes[$__opt_id]['ordered_value_ids'] .= ',';
            } else {
                $selected_attributes[$__opt_id]['ordered_value_ids'] = '';
            }
        }
        $view->selected_attributes = $selected_attributes;
    }
}