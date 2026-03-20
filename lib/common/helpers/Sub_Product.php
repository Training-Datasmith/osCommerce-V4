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

use yii\helpers\Array_Helper;
class Sub_Product
{
    public static function get_children_ids($product_id)
    {
        return Array_Helper::map(\common\models\Products::find()->where(['parent_products_id' => $product_id])->select(['products_id'])->as_array()->all(), 'products_id', 'products_id');
    }
    public static function copy_parent_attributes_to_children($product_id)
    {
        $inv_list = static::copy_inventory_attributes_list();
        $inventory_attributes = array_merge($inv_list['common'], $inv_list['price']);
        // parent
        $parent_model = \common\api\models\AR\Products::find_one($product_id);
        $data = $parent_model->export_array(['attributes' => ['*' => ['options_id', 'options_values_id', 'is_virtual', 'products_options_sort_order']], 'inventory' => ['*' => $inventory_attributes]]);
        $parent_attributes = array_filter($data['attributes'], function ($item) {
            return !$item['is_virtual'];
        });
        $children_ids = static::get_children_ids($product_id);
        foreach ($children_ids as $children_id) {
            if ($child_product = \common\api\models\AR\Products::find_one($children_id)) {
                $child_data = $child_product->export_array(['attributes' => ['*' => ['options_id', 'options_values_id', 'is_virtual', 'products_options_sort_order']]]);
                $child_attributes = array_filter($child_data['attributes'], function ($item) {
                    return !!$item['is_virtual'];
                });
                $apply_attributes = array_merge($parent_attributes, $child_attributes);
                if ($child_product->products_id_price == $child_product->parent_products_id) {
                    // child use parent price
                    $child_product->import_array(['attributes' => $apply_attributes, 'inventory' => $data['inventory'] ?? null]);
                } else {
                    // child with own price
                    $common_inventory = [];
                    foreach ($data['inventory'] as $_inv_idx => $_inventory_row) {
                        $common_inventory[$_inv_idx] = [];
                        foreach ($inv_list['common'] as $_copy_key1 => $_copy_key2) {
                            if (is_numeric($_copy_key1)) {
                                $common_inventory[$_inv_idx][$_copy_key2] = $_inventory_row[$_copy_key2];
                            } else {
                                $common_inventory[$_inv_idx][$_copy_key1] = $_inventory_row[$_copy_key1];
                            }
                        }
                    }
                    $child_product->import_array(['attributes' => $apply_attributes, 'inventory' => $common_inventory]);
                }
                $child_product->save();
            }
        }
    }
    protected static function copy_inventory_attributes_list()
    {
        return ['price' => ['inventory_price', 'inventory_discount_price', 'price_prefix', 'inventory_full_price', 'inventory_discount_full_price', 'inventory_tax_class_id', 'price' => ['*' => ['*']]], 'common' => ['inventory_weight', 'stock_indication_id', 'stock_delivery_terms_id', 'stock_control', 'non_existent', 'attribute_map']];
    }
    public static function copy_attributes_from_parent($child_product_id)
    {
        $inv_list = static::copy_inventory_attributes_list();
        $inventory_attributes = array_merge($inv_list['common'], $inv_list['price']);
        if ($child_product = \common\api\models\AR\Products::find_one($child_product_id)) {
            if ($child_product->parent_products_id > 0 && $parent_model = \common\api\models\AR\Products::find_one($child_product->parent_products_id)) {
                $data = $parent_model->export_array(['attributes' => ['*' => ['options_id', 'options_values_id', 'is_virtual']], 'inventory' => ['*' => $inventory_attributes]]);
                if ($child_product->products_id_price == $child_product->parent_products_id) {
                    // child use parent price
                    $child_product->import_array(['attributes' => $data['attributes'], 'inventory' => $data['inventory']]);
                } else {
                    // child with own price
                    $common_inventory = [];
                    foreach ($data['inventory'] as $_inv_idx => $_inventory_row) {
                        $common_inventory[$_inv_idx] = [];
                        foreach ($inv_list['common'] as $_copy_key1 => $_copy_key2) {
                            if (is_numeric($_copy_key1)) {
                                $common_inventory[$_inv_idx][$_copy_key2] = $_inventory_row[$_copy_key2];
                            } else {
                                $common_inventory[$_inv_idx][$_copy_key1] = $_inventory_row[$_copy_key1];
                            }
                        }
                    }
                    $child_product->import_array(['attributes' => $data['attributes'], 'inventory' => $common_inventory]);
                }
                $child_product->save();
            }
        }
    }
    public static function after_product_save(\common\models\Products $product)
    {
        if (empty($product->parent_products_id) && $product->sub_product_children_count > 0) {
            static::copy_parent_attributes_to_children($product->products_id);
        }
    }
}