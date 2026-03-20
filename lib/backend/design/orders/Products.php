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
namespace backend\design\orders;

use common\helpers\Order_Product;
use yii\base\Widget;
class Products extends Widget
{
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        global $login_id;
        $languages_id = (int) \Yii::$app->settings->get('languages_id');
        $ops_array = [];
        foreach (\common\models\Orders_Products_Status::find_all(['language_id' => (int) $languages_id]) as $ops_record) {
            $ops_array[$ops_record->orders_products_status_id] = $ops_record;
        }
        unset($ops_record);
        $handlers_array = [];
        /**
         * @var $ext \common\extensions\Handlers\Handlers
         */
        if ($ext = \common\helpers\Extensions::is_allowed('Handlers')) {
            $handlers_array = $ext::get_handlers_query((int) $_SESSION['access_levels_id']);
        }
        $warehouses_allocated_array = [];
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            foreach (\common\models\Admin_Warehouses::find()->where(['admin_id' => $login_id])->as_array()->all() as $warehouse) {
                $warehouses_allocated_array[] = $warehouse['warehouse_id'];
            }
            unset($warehouse);
        }
        $suppliers_allocated_array = [];
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
            foreach (\common\models\Admin_Suppliers::find()->where(['admin_id' => $login_id])->as_array()->all() as $supplier) {
                $suppliers_allocated_array[] = $supplier['suppliers_id'];
            }
            unset($supplier);
        }
        $warehouse_list = [];
        foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
            $warehouse_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
        }
        unset($warehouse_record);
        $location_block_list = [];
        foreach (\common\models\Location_Blocks::find()->as_array(true)->all() as $location_block_record) {
            $location_block_list[$location_block_record['block_id']] = $location_block_record['block_name'];
        }
        unset($location_block_record);
        return $this->render('products', [
            'manager' => $this->manager,
            // 'opsRecord' => $opsRecord,
            'order' => $this->order,
            'opsArray' => $ops_array,
            'handlers_array' => $handlers_array,
            'warehouses_allocated_array' => $warehouses_allocated_array,
            'suppliers_allocated_array' => $suppliers_allocated_array,
            'warehouseList' => $warehouse_list,
            'locationBlockList' => $location_block_list,
            'headers' => ['cancel' => $ops_array[Order_Product::OPS_CANCELLED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_CANCELLED, 'ordered' => $ops_array[Order_Product::OPS_STOCK_ORDERED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_STOCK_ORDERED, 'received' => $ops_array[Order_Product::OPS_RECEIVED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_RECEIVED, 'dispatched' => $ops_array[Order_Product::OPS_DISPATCHED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_DISPATCHED, 'delivered' => $ops_array[Order_Product::OPS_DELIVERED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_DELIVERED],
        ]);
    }
}