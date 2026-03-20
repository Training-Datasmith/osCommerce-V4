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
namespace common\extensions\Stock_Control;

class Setup extends \common\classes\modules\Setup_Extensions
{
    public static function get_description()
    {
        return 'Provides the ability to limit the amount of product available for sale on a platform or warehouse';
    }
    public static function get_translation_array()
    {
        return ['extensions/stock-control' => ['TEXT_STOCK_SPLIT' => 'Stock splitting', 'TEXT_STOCK_OVERALL' => 'Overall stock', 'TEXT_STOCK_SPLIT_PLATFORMS' => 'Split stock between platforms', 'TEXT_STOCK_PLATFORMS_TO_WAREHOUSE' => 'Assign platform to warehouse']];
    }
    public static function get_admin_hooks()
    {
        return [['page_name' => 'categories/productedit-beforesave']];
    }
    public static function get_required_modules()
    {
        return ['osCommerce' => ['version' => '999999', 'version_applicable' => 'greater-equal']];
    }
    public static function get_version_history()
    {
        return ['1.0.1' => 'Added hook', '1.0.0' => ['whats_new' => 'Basic version']];
    }
    public static function get_drop_databases_array()
    {
        return ['platform_stock_control', 'warehouse_stock_control', 'platform_inventory_control', 'warehouse_inventory_control'];
    }
    /**
     * @param $platformId
     * @param \common\classes\Migration $migration
     * @return void
     */
    public static function install($platform_id, $migration)
    {
        $migration->create_table_if_not_exists('platform_stock_control', ['products_id' => $migration->integer(10)->not_null(), 'platform_id' => $migration->integer(10)->not_null(), 'current_quantity' => $migration->integer(10)->default_value(0)->not_null(), 'manual_quantity' => $migration->integer(10)->default_value(0)->not_null()], [
            // primary key
            'pk' => ['products_id', 'platform_id'],
        ], null);
        $migration->create_table_if_not_exists('platform_inventory_control', ['products_id' => $migration->string(160)->not_null(), 'platform_id' => $migration->integer(10)->not_null(), 'current_quantity' => $migration->integer(10)->default_value(0)->not_null(), 'manual_quantity' => $migration->integer(10)->default_value(0)->not_null()], [
            // primary key
            'pk' => ['products_id', 'platform_id'],
        ], null);
        $migration->create_table_if_not_exists('warehouse_stock_control', ['products_id' => $migration->integer(10)->not_null(), 'platform_id' => $migration->integer(10)->not_null(), 'warehouse_id' => $migration->integer(10)->default_value(0)->not_null()], [
            // primary key
            'pk' => ['products_id', 'platform_id'],
        ], null);
        $migration->create_table_if_not_exists('warehouse_inventory_control', ['products_id' => $migration->string(160)->not_null(), 'platform_id' => $migration->integer(10)->not_null(), 'warehouse_id' => $migration->integer(10)->default_value(0)->not_null()], [
            // primary key
            'pk' => ['products_id', 'platform_id'],
        ], null);
    }
}