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

use common\classes\Images;
use common\helpers\Order_Product;
use Yii;
use yii\base\Widget;
class Product extends Widget
{
    public $product;
    public $manager;
    public $ops_array;
    public $handlers_array = [];
    public $iter;
    public $order;
    public $currency;
    public $currency_value;
    public $warehouse_list;
    public $location_block_list;
    public $warehouses_allocated_array = [];
    public $suppliers_allocated_array = [];
    public function init()
    {
        parent::init();
        if (!$this->currency) {
            $this->currency = $this->order->info['currency'];
        }
        if (!$this->currency_value) {
            $this->currency_value = $this->order->info['currency_value'];
        }
    }
    public function run()
    {
        global $languages_id;
        $is_temporary = false;
        foreach (\common\helpers\Order_Product::get_allocated_array($this->product['orders_products_id'], true) as $opa_record) {
            if ((int) $opa_record['is_temporary'] > 0 and (int) $opa_record['allocate_received'] > (int) $opa_record['allocate_dispatched']) {
                $is_temporary = true;
                break;
            }
        }
        unset($opa_record);
        $opsm_array = [];
        if (isset($this->ops_array[$this->product['status']])) {
            $opsm_array = $this->ops_array[$this->product['status']]->get_matrix_array();
        }
        /**
         * @var $ext \common\extensions\Handlers\Handlers
         */
        if (($ext = \common\helpers\Acl::check_extension_allowed('Handlers', 'allowed')) && !$ext::check_access((int) $this->product['id'], $this->handlers_array)) {
            return;
        }
        $row_class = '';
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            $found_in_allocations = false;
            foreach (\common\helpers\Order_Product::get_allocated_array($this->product['orders_products_id']) as $order_product_allocate_record) {
                if (in_array($order_product_allocate_record['warehouse_id'], $this->warehouses_allocated_array)) {
                    $found_in_allocations = true;
                    break;
                }
            }
            unset($order_product_allocate_record);
            if (!$found_in_allocations) {
                $row_class = 'dis_module';
            }
        }
        if (empty($row_class) && !\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
            $found_in_allocations = false;
            foreach (\common\helpers\Order_Product::get_allocated_array($this->product['orders_products_id']) as $order_product_allocate_record) {
                if (in_array($order_product_allocate_record['suppliers_id'], $this->suppliers_allocated_array)) {
                    $found_in_allocations = true;
                    break;
                }
            }
            unset($order_product_allocate_record);
            if (!$found_in_allocations) {
                $row_class = 'dis_module';
            }
        }
        $location = '';
        foreach (\common\helpers\Order_Product::get_allocated_array($this->product['orders_products_id']) as $order_product_allocate_record) {
            $location_name = trim(\common\helpers\Warehouses::get_location_path($order_product_allocate_record['location_id'], $order_product_allocate_record['warehouse_id'], $this->location_block_list));
            if ($order_product_allocate_record['layers_id']) {
                $location_name .= ', ' . \common\helpers\Translation::get_translation_value('TEXT_EXPIRY_DATE', 'admin/categories') . ' ' . \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($order_product_allocate_record['layers_id']));
            }
            if ($order_product_allocate_record['batch_id']) {
                $location_name .= ', ' . TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME . ' ' . \common\helpers\Warehouses::get_batch_name_by_batch_id($order_product_allocate_record['batch_id']);
            }
            $location .= '<div>' . (isset($this->warehouse_list[$order_product_allocate_record['warehouse_id']]) ? $this->warehouse_list[$order_product_allocate_record['warehouse_id']] : 'N/A') . ', ' . ($location_name != '' ? $location_name : 'N/A') . ': ' . '<b>' . $order_product_allocate_record['allocate_received'] . '</b>' . '</div>';
            unset($location_name);
        }
        unset($order_product_allocate_record);
        $gv_state_label = '';
        if ($this->product['gv_state'] != 'none') {
            $_inner_gv_state_label = defined('TEXT_ORDERED_GV_STATE_' . strtoupper($this->product['gv_state'])) ? constant('TEXT_ORDERED_GV_STATE_' . strtoupper($this->product['gv_state'])) : $this->product['gv_state'];
            if ($this->product['gv_state'] == 'pending' || $this->product['gv_state'] == 'canceled') {
                $_inner_gv_state_label = '<a class="js_gv_state_popup" href="' . Yii::$app->url_manager->create_url(['orders/gv-change-state', 'opID' => $this->product['orders_products_id']]) . '">' . $_inner_gv_state_label . '</a>';
            }
            $gv_state_label = '<span class="ordered_gv_state ordered_gv_state-' . $this->product['gv_state'] . '">' . $_inner_gv_state_label . '</span>';
        }
        $asset = null;
        if ($this->product['promo_id'] && \common\helpers\Acl::check_extension_allowed('Promotions')) {
            $asset = \common\extensions\Promotions\models\Promotion_Service::get_asset($this->product['promo_id'], $this->product['id']);
        }
        $ops_array = [];
        foreach (\common\models\Orders_Products_Status::find_all(['language_id' => (int) $languages_id]) as $ops_record) {
            $ops_array[$ops_record->orders_products_status_id] = $ops_record;
        }
        unset($ops_record);
        $suppliers_prices_array = [];
        foreach (\common\models\Orders_Products_Allocate::find_all(['orders_products_id' => (int) $this->product['orders_products_id']]) as $opa_record) {
            if ($opa_record->suppliers_price > 0) {
                if (!isset($suppliers_prices_array[$opa_record->suppliers_id])) {
                    $suppliers_prices_array[$opa_record->suppliers_id] = $opa_record;
                } else {
                    $suppliers_prices_array[$opa_record->suppliers_id]->allocate_received += $opa_record->allocate_received;
                }
            }
        }
        return $this->render('product', ['rowClass' => $row_class, 'manager' => $this->manager, 'order' => $this->order, 'opsmArray' => $opsm_array, 'product' => $this->product, 'image' => Images::get_image($this->product['id'], 'Small'), 'image_url' => Images::get_image_url($this->product['id'], 'Large'), 'iter' => $this->iter, 'currency' => $this->currency, 'currency_value' => $this->currency_value, 'location' => $location, 'gv_state_label' => $gv_state_label, 'asset' => $asset, 'color' => isset($this->ops_array[$this->product['status']]) ? $this->ops_array[$this->product['status']]->get_colour() : '#000000', 'status' => isset($this->ops_array[$this->product['status']]) ? $this->ops_array[$this->product['status']]->orders_products_status_name : '', 'isTemporary' => $is_temporary, 'headers' => ['cancel' => $ops_array[Order_Product::OPS_CANCELLED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_CANCELLED, 'ordered' => $ops_array[Order_Product::OPS_STOCK_ORDERED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_STOCK_ORDERED, 'deficit' => $ops_array[Order_Product::OPS_STOCK_DEFICIT]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_STOCK_DEFICIT, 'received' => $ops_array[Order_Product::OPS_RECEIVED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_RECEIVED, 'dispatched' => $ops_array[Order_Product::OPS_DISPATCHED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_DISPATCHED, 'delivered' => $ops_array[Order_Product::OPS_DELIVERED]->orders_products_status_name_long ?? TEXT_STATUS_LONG_OPS_DELIVERED], 'suppliersPricesArray' => $suppliers_prices_array]);
    }
}