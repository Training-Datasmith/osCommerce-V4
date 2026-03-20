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

class View_Stock_Info
{
    /**
     * @var \objectInfo
     */
    protected $product_info_ref;
    public function __construct($product_info)
    {
        $this->product_info_ref = $product_info;
        $this->wrap($this->product_info_ref);
    }
    protected function wrap($p_info)
    {
        $products_id = $p_info->products_id;
        if ($p_info->parent_products_id && $p_info->products_id_stock) {
            $products_id = $p_info->products_id_stock;
            $p_data_info = new \Object_Info(\common\models\Products::find_one($products_id)->get_attributes());
        } else {
            $p_data_info = $p_info;
        }
        $allocated_temporary = \common\helpers\Product::get_allocated_temporary($products_id, true);
        $p_info->products_quantity = $p_data_info->products_quantity;
        $p_info->allocated_quantity = $p_data_info->allocated_stock_quantity - $allocated_temporary;
        $p_info->allocated_temporary_quantity = $allocated_temporary;
        $p_info->temporary_quantity = $p_data_info->temporary_stock_quantity;
        $p_info->warehouse_quantity = $p_data_info->warehouse_stock_quantity;
        //$pInfo->ordered_quantity = $pDataInfo->ordered_stock_quantity;
        $p_info->ordered_quantity = \common\helpers\Product::get_stock_ordered($products_id);
        $p_info->suppliers_quantity = $p_data_info->suppliers_stock_quantity;
        $p_info->deficit_quantity = \common\helpers\Product::get_stock_deficit($products_id);
        if ((int) $p_data_info->stock_reorder_level < 0) {
            $p_info->stock_reorder_level = (int) STOCK_REORDER_LEVEL;
        } else {
            $p_info->stock_reorder_level_on = true;
        }
        if ((int) $p_data_info->stock_reorder_quantity < 0) {
            $p_info->stock_reorder_quantity = (int) STOCK_REORDER_QUANTITY;
        } else {
            $p_info->stock_reorder_quantity_on = true;
        }
        if ((int) $p_data_info->stock_limit < 0) {
            $p_info->stock_limit = (int) ADDITIONAL_STOCK_LIMIT;
        } else {
            $p_info->stock_limit_on = true;
        }
        $p_info->platform_stock_list = [];
        $p_info->platform_warehouse_list = [];
        if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
            $ext_scl::update_product_view_stock_info($p_info);
        }
    }
}