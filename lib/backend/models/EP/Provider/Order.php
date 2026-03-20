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
namespace backend\models\EP\Provider;

use backend\models\EP;
use backend\models\EP\Messages;
use common\classes\payment;
use common\classes\shipping;
use common\classes\shopping_cart;
use common\helpers\Html;
use common\models\Orders_History;
use common\models\Orders_Status_History;
use common\models\Orders_Transactions;
use yii\db\Column_Schema;
use yii\db\Expression;
use yii\helpers\Array_Helper;
class Order extends Provider_Abstract implements Export_Interface, Import_Interface
{
    protected $fields = [];
    protected $export_query;
    protected $export_rows = [];
    protected $skip_external_on_import = [];
    protected $collect_order = [];
    protected $last_process_product_error = '';
    protected $processed_count = 0;
    protected $site_modules = [];
    protected $import_defaults = ['check_valid_product' => 'check_valid', 'import_mode' => 'create_recalculate', 'recalculate' => 'no'];
    /**
     * @var EP\Tools
     */
    protected $e_ptools;
    public function init()
    {
        parent::init();
        $this->init_fields();
        $this->e_ptools = new EP\Tools();
    }
    public function exchange_xml()
    {
        return [['header' => ['type' => 'orders'], 'rowsTag' => 'Orders', 'rowTag' => 'Order']];
    }
    protected function init_fields()
    {
        $this->fields['intId'] = ['name' => 'info.orders_id', 'value' => 'OrderID'];
        $this->fields['extId'] = ['name' => 'info.external_orders_id', 'value' => 'External Order ID'];
        $this->fields[] = ['name' => 'customer.email_address', 'value' => 'Customer Email'];
        $this->fields[] = ['name' => 'customer.newsletter', 'value' => 'Subscribed to Newsletters'];
        $this->fields[] = ['name' => 'customer.telephone', 'value' => 'Customer Phone'];
        $this->fields[] = ['name' => 'customer.name', 'value' => 'Customer Name'];
        $this->fields[] = ['name' => 'customer.firstname', 'value' => 'Customer First Name'];
        $this->fields[] = ['name' => 'customer.lastname', 'value' => 'Customer Last Name'];
        $this->fields[] = ['name' => 'customer.company', 'value' => 'Customer Company'];
        $this->fields[] = ['name' => 'customer.street_address', 'value' => 'Customer Street Address'];
        $this->fields[] = ['name' => 'customer.suburb', 'value' => 'Customer Suburb'];
        $this->fields[] = ['name' => 'customer.city', 'value' => 'Customer City'];
        $this->fields[] = ['name' => 'customer.postcode', 'value' => 'Customer Postcode'];
        $this->fields[] = ['name' => 'customer.state', 'value' => 'Customer State'];
        $this->fields[] = ['name' => 'customer.country.iso_code_2', 'value' => 'Customer Country'];
        $this->fields[] = ['name' => 'billing.name', 'value' => 'Billing Name'];
        $this->fields[] = ['name' => 'billing.firstname', 'value' => 'Billing First Name'];
        $this->fields[] = ['name' => 'billing.lastname', 'value' => 'Billing Last Name'];
        $this->fields[] = ['name' => 'billing.company', 'value' => 'Billing Company'];
        $this->fields[] = ['name' => 'billing.street_address', 'value' => 'Billing Street Address'];
        $this->fields[] = ['name' => 'billing.suburb', 'value' => 'Billing Suburb'];
        $this->fields[] = ['name' => 'billing.city', 'value' => 'Billing City'];
        $this->fields[] = ['name' => 'billing.postcode', 'value' => 'Billing Postcode'];
        $this->fields[] = ['name' => 'billing.state', 'value' => 'Billing State'];
        $this->fields[] = ['name' => 'billing.country.iso_code_2', 'value' => 'Billing Country'];
        $this->fields[] = ['name' => 'delivery.name', 'value' => 'Shipping Name'];
        $this->fields[] = ['name' => 'delivery.firstname', 'value' => 'Shipping First Name'];
        $this->fields[] = ['name' => 'delivery.lastname', 'value' => 'Shipping Last Name'];
        $this->fields[] = ['name' => 'delivery.company', 'value' => 'Shipping Company'];
        $this->fields[] = ['name' => 'delivery.street_address', 'value' => 'Shipping Street Address'];
        $this->fields[] = ['name' => 'delivery.suburb', 'value' => 'Shipping Suburb'];
        $this->fields[] = ['name' => 'delivery.city', 'value' => 'Shipping City'];
        $this->fields[] = ['name' => 'delivery.postcode', 'value' => 'Shipping Postcode'];
        $this->fields[] = ['name' => 'delivery.state', 'value' => 'Shipping State'];
        $this->fields[] = ['name' => 'delivery.country.iso_code_2', 'value' => 'Shipping Country'];
        $this->fields[] = ['name' => 'products.*.model', 'value' => 'Product Model'];
        $this->fields[] = ['name' => 'products.*.name', 'value' => 'Product Name'];
        $this->fields[] = ['name' => 'products.*.units', 'value' => 'Product Unit Qty'];
        $this->fields[] = ['name' => 'products.*.packagings', 'value' => 'Product Pack Qty'];
        $this->fields[] = ['name' => 'products.*.packs', 'value' => 'Product Carton Qty'];
        $this->fields[] = ['name' => 'products.*.tax', 'value' => 'Product Tax Rate'];
        $this->fields[] = ['name' => 'products.*.units_price', 'value' => 'Product Unit Price'];
        $this->fields[] = ['name' => 'products.*.packagings_price', 'value' => 'Product Pack Price'];
        $this->fields[] = ['name' => 'products.*.packs_price', 'value' => 'Product Carton Price'];
        $this->fields[] = ['name' => 'products.*.weight', 'value' => 'Product Weight'];
        // {{ totals
        foreach ($this->get_order_total_modules() as $total_code => $total_title) {
            $this->fields[] = ['name' => 'totals.' . $total_code . '.title', 'value' => 'Total: ' . $total_title . ' (Title)'];
            $this->fields[] = ['name' => 'totals.' . $total_code . '.value_exc_vat', 'value' => 'Total: ' . $total_title . ' (value exl vat)'];
            $this->fields[] = ['name' => 'totals.' . $total_code . '.value_inc_tax', 'value' => 'Total: ' . $total_title . ' (value inc vat)'];
        }
        // }} totals
        $this->fields[] = ['name' => 'info.payment_class', 'value' => 'Payment class'];
        $this->fields[] = ['name' => 'info.payment_method', 'value' => 'Payment Method'];
        $this->fields[] = ['name' => 'transaction.transaction_id', 'value' => 'Payment Transaction ID'];
        $this->fields[] = ['name' => 'transaction.transaction_amount', 'value' => 'Payment Transaction Amount'];
        $this->fields[] = ['name' => 'transaction.date_created', 'value' => 'Payment Date'];
        $this->fields[] = ['name' => 'info.shipping_class', 'value' => 'Shipping class'];
        $this->fields[] = ['name' => 'info.shipping_method', 'value' => 'Shipping Method'];
        $this->fields[] = ['name' => 'info.shipping_weight', 'value' => 'Order Shipping Weight'];
        $this->fields[] = ['name' => 'info.order_status', 'value' => 'Order Status'];
        $this->fields[] = ['name' => 'info.currency', 'value' => 'Currency Code'];
        $this->fields[] = ['name' => 'info.currency_value', 'value' => 'Currency Rate'];
        $this->fields[] = ['name' => 'info.language', 'value' => 'Language'];
        $this->fields[] = ['name' => 'info.platform_id', 'value' => 'Sale Channel'];
        $this->fields[] = ['name' => 'info.date_purchased', 'value' => 'Date Purchased'];
        $this->fields[] = ['name' => 'info.comments', 'value' => 'Order Comments'];
    }
    protected function get_order_total_modules()
    {
        if (count($this->site_modules) == 0) {
            $MODULE_ORDER_TOTAL_INSTALLED = [];
            foreach (\common\classes\platform::get_list() as $platform) {
                $platform_config = \Yii::$app->get('platform')->get_config($platform['id']);
                $MODULE_ORDER_TOTAL_INSTALLED = array_merge($MODULE_ORDER_TOTAL_INSTALLED, explode(';', $platform_config->const_value('MODULE_ORDER_TOTAL_INSTALLED')));
            }
            $MODULE_ORDER_TOTAL_INSTALLED = array_unique($MODULE_ORDER_TOTAL_INSTALLED);
            $modules = [];
            $manager = new \common\services\Order_Manager(\Yii::$app->get('storage'));
            $builder = new \common\classes\modules\Module_Builder($manager);
            foreach ($MODULE_ORDER_TOTAL_INSTALLED as $MODULE_ORDER_TOTAL_) {
                $MODULE_ORDER_TOTAL_CODE = preg_replace('/\.php$/', '', $MODULE_ORDER_TOTAL_);
                $title_const = 'MODULE_ORDER_TOTAL_' . strtoupper(preg_replace('/^ot_/', '', $MODULE_ORDER_TOTAL_CODE)) . '_TITLE';
                $MODULE_ORDER_TOTAL_TITLE = \common\helpers\Translation::get_translation_value($title_const, 'ordertotal');
                if (empty($MODULE_ORDER_TOTAL_TITLE)) {
                    \common\helpers\Translation::init('ordertotal');
                    if (\frontend\design\Info::is_totally_admin()) {
                        if (!is_file(DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/' . $MODULE_ORDER_TOTAL_)) {
                            continue;
                        }
                        include_once DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/' . $MODULE_ORDER_TOTAL_;
                    } else {
                        if (!is_file(DIR_WS_MODULES . 'order_total/' . $MODULE_ORDER_TOTAL_)) {
                            continue;
                        }
                        include_once DIR_WS_MODULES . 'order_total/' . $MODULE_ORDER_TOTAL_;
                    }
                    $class = $MODULE_ORDER_TOTAL_CODE;
                    try {
                        $module_instance = $builder(['class' => $class]);
                        if ($module_instance && $module_instance->title) {
                            $MODULE_ORDER_TOTAL_TITLE = $module_instance->title;
                        }
                    } catch (\Exception $ex) {
                    }
                }
                $modules[$MODULE_ORDER_TOTAL_CODE] = $MODULE_ORDER_TOTAL_TITLE;
            }
            $this->site_modules = $modules;
        }
        return $this->site_modules;
    }
    public function prepare_export($use_columns, $filter)
    {
        $this->build_sources($use_columns);
        $filter_sql = '';
        if (is_array($filter)) {
            $order_filter = isset($filter['order']) && is_array($filter['order']) ? $filter['order'] : [];
            if (isset($order_filter['date_type_range']) && $order_filter['date_type_range'] == 'exact') {
                if (!empty($order_filter['date_from'])) {
                    $filter_sql .= " AND o.date_purchased >= '" . tep_db_input(substr($order_filter['date_from'], 0, 10)) . " 00:00:00' ";
                }
                if (!empty($order_filter['date_to'])) {
                    $filter_sql .= " AND o.date_purchased <= '" . tep_db_input(substr($order_filter['date_to'], 0, 10)) . " 23:59:59' ";
                }
            } elseif (isset($order_filter['date_type_range']) && $order_filter['date_type_range'] == 'year/month') {
                $year = $order_filter['year'];
                $filter_sql .= " AND YEAR(o.date_purchased)='" . tep_db_input($year) . "' ";
                $month = $order_filter['month'];
                if (!empty($month)) {
                    $filter_sql .= " AND DATE_FORMAT(o.date_purchased,'%Y%m')='" . tep_db_input($year . sprintf('%02s', (int) $month)) . "' ";
                }
            } elseif (isset($order_filter['date_type_range']) && $order_filter['date_type_range'] == 'presel') {
                switch ($order_filter['interval']) {
                    case 'week':
                        $filter_sql .= " AND o.date_purchased >= '" . date('Y-m-d', strtotime('monday this week')) . "' ";
                        break;
                    case 'month':
                        $filter_sql .= " AND o.date_purchased >= '" . date('Y-m-d', strtotime('first day of this month')) . "' ";
                        break;
                    case 'year':
                        $filter_sql .= " AND o.date_purchased >= '" . date('Y') . '-01-01' . "' ";
                        break;
                    case '1':
                        $filter_sql .= " AND o.date_purchased >= '" . date('Y-m-d') . "' ";
                        break;
                    case '3':
                    case '7':
                    case '14':
                    case '30':
                        $filter_sql .= " AND o.date_purchased >= '" . date('Y-m-d', strtotime('-' . $filters['interval'] . ' days')) . "' ";
                        break;
                }
            }
        }
        $main_sql = 'SELECT o.orders_id ' . 'FROM ' . TABLE_ORDERS . ' o ' . "WHERE 1 {$filter_sql} ";
        $this->export_query = tep_db_query($main_sql);
        $this->export_rows = false;
        return $this->export_query;
    }
    public function import_options()
    {
        $check_valid_product = isset($this->import_config['check_valid_product']) ? $this->import_config['check_valid_product'] : 'check_valid';
        $import_mode = isset($this->import_config['import_mode']) ? $this->import_config['import_mode'] : 'create_recalculate';
        $recalculate = isset($this->import_config['recalculate']) ? $this->import_config['recalculate'] : 'no';
        return '
<div class="widget box box-no-shadow">
    <div class="widget-header"><h4>Import options</h4></div>
    <div class="widget-content">
        <div class="row">
            <div class="col-md-6"><label>Import mode</label></div>
            <div class="col-md-6">' . Html::drop_down_list('import_config[import_mode]', $import_mode, ['update' => 'Update existing', 'create' => 'Create not existing', 'create_update' => 'Create not existing and update existing'], ['class' => 'form-control']) . '</div>
        </div>
        <div class="row">
            <div class="col-md-6"><label>Recalculate</label></div>
            <div class="col-md-6">' . Html::drop_down_list('import_config[recalculate]', $recalculate, ['no' => 'Import as is', 'yes' => 'Recalculate'], ['class' => 'form-control', 'options' => ['yes' => ['disabled' => 'disabled']]]) . '</div>
        </div>
        <div class="row form-group">
            <div class="col-md-6"><label>Check product exist?</label></div>
            <div class="col-md-6">' . Html::drop_down_list('import_config[check_valid_product]', $check_valid_product, ['check_valid' => 'Yes, product need exist in catalog', 'add_dummy' => 'No, just insert'], ['class' => 'form-control']) . '</div>
        </div>
    </div>
</div>
        ';
    }
    public function export_row()
    {
        if (is_array($this->export_rows) && current($this->export_rows)) {
            $this->data = current($this->export_rows);
            next($this->export_rows);
            return $this->data;
        }
        $order_id = tep_db_fetch_array($this->export_query);
        if (!is_array($order_id)) {
            return false;
        }
        $this->export_rows = false;
        $data_sources = $this->data_sources;
        $export_columns = $this->export_columns;
        try {
            $order = new \common\classes\Order((int) $order_id['orders_id']);
        } catch (\Exception $ex) {
            return [':empty' => 'data'];
        }
        $order = ['customer' => $order->customer, 'delivery' => $order->delivery, 'billing' => $order->billing, 'products' => $order->products, 'info' => $order->info, 'totals' => $order->totals, 'transaction' => \common\models\Orders_Transactions::find()->where(['orders_id' => $order->order_id])->order_by(['orders_transactions_id' => SORT_DESC])->as_array()->one()];
        if ($this->format == 'XML') {
            return $order;
        }
        if (isset($order['totals']) && is_array($order['totals'])) {
            $order_totals = $order['totals'];
            $order['totals'] = [];
            foreach ($order_totals as $total) {
                $order['totals'][$total['code']] = $total;
            }
        }
        $order_wo_product = $order;
        unset($order_wo_product['products']);
        $order_wo_product = EP\Array_Transform::convert_multi_dimensional_to_flat($order_wo_product);
        $order_wo_product['info.language'] = \common\classes\language::get_code($order_wo_product['info.language']);
        $order_wo_product['info.platform_id'] = $this->e_ptools->get_platform_name($order_wo_product['info.platform_id']);
        //$order_wo_product['info.order_status'] = \common\helpers\Order::get_status_name($order_wo_product['info.order_status']);
        $order_wo_product['info.order_status'] = $order_wo_product['info.orders_status_name'];
        $order_rows = [];
        if (count($order['products']) > 0 && preg_grep('/^products\.\*/', array_keys($export_columns))) {
            foreach ($order['products'] as $product) {
                if ($product['packs'] || $product['packagings'] || $product['units']) {
                } else {
                    $product['units_price'] = $product['final_price'];
                    $product['units'] = $product['qty'];
                }
                $append_row = $order_wo_product;
                $flat_product = EP\Array_Transform::convert_multi_dimensional_to_flat($product);
                foreach ($flat_product as $key => $val) {
                    $append_row['products.*.' . $key] = $val;
                }
                $order_rows[] = $append_row;
            }
        } else {
            $order_rows[] = $order_wo_product;
        }
        $this->export_rows = $order_rows;
        reset($this->export_rows);
        if (is_array($this->export_rows) && current($this->export_rows)) {
            $this->data = current($this->export_rows);
            next($this->export_rows);
            return $this->data;
        }
        return false;
    }
    public function import_row($data, Messages $message)
    {
        $allow_insert_not_existing_products = false;
        if (is_array($this->import_config) && isset($this->import_config['check_valid_product']) && $this->import_config['check_valid_product'] == 'add_dummy') {
            $allow_insert_not_existing_products = true;
        }
        if ($this->format == 'CSV') {
            // remove empty
            $data = array_filter($data, function ($v, $k) {
                return strlen($v) > 0 || strpos($k, 'iso_code_2') !== false;
            }, ARRAY_FILTER_USE_BOTH);
            $data = EP\Array_Transform::convert_flat_to_multi_dimensional($data);
        } else {
            $data['products'] = Array_Helper::is_indexed($data['products']['product']) ? $data['products']['product'] : [$data['products']['product']];
        }
        //$data['info']['platform_id'] = \common\classes\platform::defaultId();
        if ($data['info'] && $data['info']['language']) {
            $data['info']['language_id'] = \common\classes\language::get_id($data['info']['language']);
        }
        $ext_id_present = isset($data['info']['external_orders_id']) && !empty($data['info']['external_orders_id']);
        $order_id_present = isset($data['info']['orders_id']) && !empty($data['info']['orders_id']);
        if (!$order_id_present && !$ext_id_present) {
            if ($this->format == 'CSV' && count($this->collect_order) > 0) {
                $this->persist_collected_order($message);
            }
            $message->info('Missing or empty ' . $this->fields['intId']['value'] . ', ' . $this->fields['extId']['value']);
            $this->collect_order = [];
            return false;
        }
        if ($order_id_present) {
            $group_by = 'INT^' . $data['info']['orders_id'];
            $order_key = 'orders_id';
            $key_name = $this->fields['intId']['value'];
        } else {
            $group_by = 'EXT^' . $data['info']['external_orders_id'];
            $order_key = 'external_orders_id';
            $key_name = $this->fields['extId']['value'];
        }
        /*
        if ( $extIdPresent ) {
            $group_by = 'EXT^' . $data['info']['external_orders_id'];
            $orderKey = 'external_orders_id';
            $keyName = $this->fields['extId']['value'];
        }else{
            $group_by = 'INT^' . $data['info']['orders_id'];
            $orderKey = 'orders_id';
            $keyName = $this->fields['intId']['value'];
        }
        */
        $data['info']['.import_key'] = $order_key;
        // {{ required check
        if (isset($skip_external_on_import[$group_by])) {
            //$message->info("".$this->fields['extId']['value']." =".$data['info.external_orders_id']." skipped ");
            $this->collect_order = [];
            return false;
        }
        /*
        if ( !isset($data['customer']['email_address']) || empty($data['customer']['email_address']) ) {
            $skip_external_on_import[$group_by] = $data['info'][$orderKey];
        
            $message->info("".$keyName." =".$data['info'][$orderKey]." skipped: Empty customer email");
            $this->collectOrder = [];
            return false;
        }
        */
        $skip_by_product_check = false;
        if (isset($data['products'])) {
            foreach ($data['products'] as $_product_idx => $product_data) {
                if (empty($product_data['model']) && !$allow_insert_not_existing_products) {
                    $skip_by_product_check = true;
                    break;
                } else {
                    $product = $this->process_import_product_data($product_data, $allow_insert_not_existing_products);
                    if ($product === false) {
                        $skip_external_on_import[$data['info'][$order_key]] = $data['info'][$order_key];
                        $message->info('' . $key_name . ' =' . $data['info'][$order_key] . ' skipped: ' . $this->last_process_product_error);
                        $this->collect_order = [];
                        return false;
                    } else {
                        $data['products'][$_product_idx] = $product;
                    }
                }
            }
        } else {
            $skip_by_product_check = true;
        }
        if ($skip_by_product_check) {
            $message->info('' . $key_name . ' =' . $data['info'][$order_key] . ' skipped: Empty product model');
            $this->collect_order = [];
            return false;
        }
        // }} required check
        if ($this->format == 'XML') {
            $this->collect_order = $data;
            $this->persist_collected_order($message);
            return true;
        } else {
            // CSV - per line
            if (count($this->collect_order) > 0 && $this->collect_order['info'][$order_key] != $data['info'][$order_key]) {
                $this->persist_collected_order($message);
            }
            $product = $data['products']['*'];
            if (count($this->collect_order) == 0) {
                unset($data['products']['*']);
                $this->collect_order = $data;
            } else {
                unset($data['products']['*']);
                // "merge" non empty - common data from any line
                // 2 level array
                foreach ($data as $L1Key => $L1Val) {
                    if (is_array($L1Val)) {
                        foreach ($L1Val as $L2Key => $L2Val) {
                            if (is_array($L2Val)) {
                                if ($L2Key == '*') {
                                    $this->collect_order[$L1Key][] = $L2Val;
                                    continue;
                                }
                                foreach ($L2Val as $L3Key => $L3Val) {
                                    if ($L3Key == '*') {
                                        $this->collect_order[$L1Key][$L2Key][] = $L3Val;
                                        continue;
                                    }
                                    $this->collect_order[$L1Key][$L2Key][$L3Key] = $L3Val;
                                }
                            } else {
                                $this->collect_order[$L1Key][$L2Key] = $L2Val;
                            }
                        }
                    } else {
                        $this->collect_order[$L1Key] = $L1Val;
                    }
                }
            }
            $this->collect_order['products'][] = $product;
            return true;
        }
    }
    protected function process_import_product_data($product, $allow_insert_not_existing_products = false)
    {
        $this->last_process_product_error = '';
        if (!isset($product['qty'])) {
            $product['qty'] = 1;
        }
        $product['qty'] = (int) $product['qty'];
        if ($allow_insert_not_existing_products && empty($product['model'])) {
            $get_product_r = tep_db_query('SELECT products_id FROM ' . TABLE_PRODUCTS . ' WHERE 1=0');
        } else {
            $get_product_r = tep_db_query('SELECT IFNULL(i.products_id, p.products_id) AS uprid, p.products_id, ' . ' IFNULL(i.products_id, p.products_id) AS id, ' . ' p.products_tax_class_id, ' . ' p.pack_unit, p.packaging ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . ' LEFT JOIN ' . TABLE_INVENTORY . ' i ON i.prid=p.products_id ' . 'WHERE 1 ' . '  AND (' . "    IF(LENGTH(i.products_model)>0,i.products_model,p.products_model)='" . tep_db_input($product['model']) . "' " . '    OR ' . "    p.products_model='" . tep_db_input($product['model']) . "' " . '  ) ' . "ORDER BY IF(p.products_model='" . tep_db_input($product['model']) . "',1,0) " . 'LIMIT 1 ');
        }
        if ($allow_insert_not_existing_products == false && tep_db_num_rows($get_product_r) == 0) {
            $this->last_process_product_error = "can't find product model \"" . $product['model'] . '"';
            return false;
        }
        /*if ($allowInsertNotExistingProducts && empty($product['name'])) {
              $this->lastProcessProductError = "can't insert product without name";
              return false;
          }*/
        if (tep_db_num_rows($get_product_r) > 0) {
            $shop_product = tep_db_fetch_array($get_product_r);
        } else {
            $shop_product = ['uprid' => '', 'products_id' => 0, 'id' => 0];
        }
        $product['id'] = $shop_product['id'];
        $product['uprid'] = $shop_product['uprid'];
        $product['products_id'] = $shop_product['products_id'];
        if (empty($product['name']) && $shop_product['id'] > 0) {
            $product['name'] = \common\helpers\Product::get_products_name($shop_product['id']);
        }
        \common\helpers\Php8::null_props($product, ['packs', 'packagings', 'units']);
        if ($product['packs'] || $product['packagings'] || $product['units']) {
            if (intval($shop_product['pack_unit']) == 0 && (int) $product['packs'] != 0) {
                $this->last_process_product_error = 'product model "' . $product['model'] . '" Carton qty not enabled';
                return false;
            }
            if (intval($shop_product['packaging']) == 0 && (int) $product['packagings'] != 0) {
                $this->last_process_product_error = 'product model "' . $product['model'] . '" Pack qty not enabled';
                return false;
            }
            $product['qty'] = $product['units'] + $product['packs'] * $shop_product['pack_unit'] + $product['packagings'] * ($shop_product['pack_unit'] * $shop_product['packaging']);
            if ($product['units_price'] && empty($product['packs']) && empty($product['packagings'])) {
                $product['final_price'] = $product['units_price'];
            } else {
                $item_amount = $product['units_price'] * $product['units'] + $product['packs'] * $product['packs_price'] + $product['packagings'] * $product['packagings_price'];
                $product['final_price'] = $item_amount / $product['qty'];
            }
            $product['price'] = $product['final_price'];
        } else {
            $product['qty'] = max(1, isset($product['units']) ? intval($product['units']) : 1);
            $product['final_price'] = $product['units_price'];
            $product['price'] = $product['units_price'];
        }
        $product['final_price'] = number_format(floatval($product['final_price']), 5, '.', '');
        $product['price'] = number_format(floatval($product['price']), 5, '.', '');
        if (isset($product['name'])) {
            $product['products_name'] = $product['name'];
        }
        if (isset($product['model'])) {
            $product['products_model'] = $product['model'];
        }
        if (isset($product['price'])) {
            $product['products_price'] = $product['price'];
        }
        if (isset($product['qty'])) {
            $product['products_quantity'] = $product['qty'];
        }
        if (isset($product['tax'])) {
            $product['products_tax'] = $product['tax'];
        }
        if (isset($product['weight'])) {
            $product['products_weight'] = $product['weight'];
        }
        return $product;
    }
    protected function assign_customer(\common\models\Orders $order)
    {
        /**
         * @var $platform_config \common\classes\platform_config
         */
        $platform_config = \Yii::$app->get('platform')->get_config($order->platform_id);
        $customer = null;
        if ($order->customers_email_address) {
            $customer = \common\models\Customers::find_by_email($order->customers_email_address);
            if (!$customer) {
                $customer = \common\models\Customers::find_by_multi_email($order->customers_email_address);
            }
        } elseif ($order->customers_firstname && $order->customers_lastname) {
            $customer = \common\models\Customers::find()->where(['customers_firstname' => $order->customers_firstname, 'customers_lastname' => $order->customers_lastname])->one();
        }
        if (!$customer) {
            $customer = new \common\models\Customers();
            $customer->load_default_values();
        }
        if ($customer->is_new_record) {
            $customer_data = $order->get_attributes(['customers_firstname', 'customers_lastname', 'customers_telephone', 'customers_company', 'customers_company_vat']);
            $customer_data = array_filter($customer_data, 'strlen');
            $customer->set_attributes($customer_data, false);
            $customer->dob_flag = 1;
            $customer->customers_email_address = $order->customers_email_address;
            $customer->platform_id = $platform_config->get_id();
            $customer->customers_password = \common\helpers\Password::encrypt_password(\common\helpers\Password::create_random_value(12), 'frontend');
            $customer->groups_id = $platform_config->const_value('DEFAULT_USER_LOGIN_GROUP', 0);
            $customer->save(false);
            $customer->refresh();
        }
        $order->customers_id = $customer->customers_id;
        $customer_info = \common\models\Customers_Info::find_one($customer->customers_id);
        if (!$customer_info) {
            $customer_info = new \common\models\Customers_Info(['customers_info_id' => $customer->customers_id, 'customers_info_number_of_logons' => 0, 'customers_info_date_account_created' => new Expression('NOW()')]);
            $customer_info->load_default_values();
            $customer_info->save(false);
            $customer_info->refresh();
        }
        if (!$customer->customers_default_address_id) {
            $platform_address = $platform_config->get_platform_address();
            $default_address_book = new \common\models\Address_Book(['customers_id' => $customer->customers_id, 'entry_country_id' => $platform_address['country_id']]);
            $default_address_book->load_default_values();
            $order_customer = $order->get_attributes(['customers_company', 'customers_company_vat', 'customers_firstname', 'customers_lastname', 'customers_street_address', 'customers_suburb', 'customers_postcode', 'customers_city', 'customers_state', 'customers_country']);
            if ($order_customer['customers_country']) {
                $order_customer['customers_country_id'] = EP\Tools::get_instance()->get_country_id($order_customer['customers_country']);
            }
            if (!$order_customer['customers_country_id']) {
                $order_customer['customers_country_id'] = $platform_address['country_id'];
            }
            if ($order_customer['customers_country_id'] && $order_customer['customers_state']) {
                $order_customer['customers_zone_id'] = EP\Tools::get_instance()->get_country_zone_id($order_customer['customers_state'], $order_customer['customers_country_id']);
            }
            foreach ($order_customer as $_key => $_val) {
                $ab_property_name = 'entry_' . str_replace('customers_', '', $_key);
                if ($default_address_book->can_set_property($ab_property_name)) {
                    $default_address_book->set_attribute($ab_property_name, $_val);
                }
            }
            foreach ($default_address_book->get_table_schema()->columns as $column) {
                /**
                 * @var $column ColumnSchema
                 */
                if ($default_address_book->get_attribute($column->name) === null && !$column->allow_null) {
                    $default_address_book->set_attribute($column->name, '');
                }
            }
            $default_address_book->save(false);
            $customer->customers_default_address_id = $default_address_book->address_book_id;
            $customer->save(false);
        }
        return $order->customers_id;
    }
    protected function persist_collected_order(Messages $message)
    {
        /**
        'update'=>'Update existing',
        'create'=>'Create not existing',
        'create_update'=>'Create not existing and update existing',
        */
        $import_mode = $this->import_defaults['import_mode'];
        /**
                'no' => 'Import as is',
                'yes' => 'Recalculate',
        */
        $recalculate = $this->import_defaults['recalculate'];
        if (is_array($this->import_config)) {
            if (!empty($this->import_config['import_mode'])) {
                $import_mode = $this->import_config['import_mode'];
            }
            if (!empty($this->import_config['recalculate'])) {
                $recalculate = $this->import_config['recalculate'];
            }
        }
        $import_key = $this->collect_order['info']['.import_key'];
        $import_key_value = $this->collect_order['info'][$import_key];
        $order_model = \common\models\Orders::find()->where([$import_key => $import_key_value])->limit(1)->one();
        if ($order_model) {
            if (strpos($import_mode, 'update') === false) {
                $message->info('' . $import_key . ' =' . $import_key_value . ' skipped: already exists');
                return false;
            }
        } else if (strpos($import_mode, 'create') === false) {
            $message->info('' . $import_key . ' =' . $import_key_value . ' skipped: not exists');
            return false;
        }
        $this->collect_order['info']['external_orders_id'] = $this->collect_order['info']['external_orders_id'] ?? null;
        $new_order_added = false;
        if (!$order_model) {
            $new_order_added = true;
            $order_model = new \common\models\Orders();
            $order_model->load_default_values();
            if ($this->collect_order['info']['orders_id']) {
                $order_model->orders_id = (int) $this->collect_order['info']['orders_id'];
            }
            if ($this->collect_order['info']['external_orders_id']) {
                $order_model->external_orders_id = strval($this->collect_order['info']['external_orders_id']);
            }
        } else if ($import_key == 'orders_id') {
            if ($this->collect_order['info']['external_orders_id']) {
                $order_model->external_orders_id = strval($this->collect_order['info']['external_orders_id']);
            }
        }
        if (!empty($this->collect_order['info']['platform_id'])) {
            $this->collect_order['info']['platform_id'] = $this->e_ptools->get_platform_id($this->collect_order['info']['platform_id']);
            if (empty($this->collect_order['info']['platform_id'])) {
                $this->collect_order['info']['platform_id'] = \common\classes\platform::default_id();
            }
            $order_model->platform_id = $this->collect_order['info']['platform_id'];
        }
        if (empty($order_model->platform_id)) {
            $order_model->platform_id = \common\classes\platform::default_id();
        }
        /**
         * @var $platform_config \common\classes\platform_config
         */
        $platform_config = \Yii::$app->get('platform')->get_config($order_model->platform_id);
        if (!empty($this->collect_order['info']['language'])) {
            if ($language_id_array = \common\helpers\Language::get_language_id($this->collect_order['info']['language'])) {
                $this->collect_order['info']['language_id'] = (int) $language_id_array['languages_id'];
            }
        }
        if (!empty($this->collect_order['info']['language_id'])) {
            $order_model->language_id = $this->collect_order['info']['language_id'];
        }
        if (empty($order_model->language_id) && $order_model->is_new_record) {
            if ($language_id_array = \common\helpers\Language::get_language_id($platform_config->get_default_language())) {
                $order_model->language_id = (int) $language_id_array['languages_id'];
            } elseif ($language_id_array = \common\helpers\Language::get_language_id(\Yii::$app->get('platform')->get_config(\common\classes\platform::default_id())->get_default_language())) {
                $order_model->language_id = (int) $language_id_array['languages_id'];
            }
        }
        // {{ get missing customer address from billing address
        if ($order_model->is_new_record) {
            $address_columns = ['name', 'firstname', 'lastname', 'company', 'street_address', 'suburb', 'city', 'postcode', 'state', 'country'];
            if (!isset($this->collect_order['customer']) || count(array_intersect_key($this->collect_order['customer'], array_flip($address_columns))) == 0) {
                if (!isset($this->collect_order['customer'])) {
                    $this->collect_order['customer'] = [];
                }
                if (isset($this->collect_order['billing']) && is_array($this->collect_order['billing'])) {
                    $this->collect_order['customer'] = array_merge($this->collect_order['billing'], $this->collect_order['customer']);
                }
            }
        }
        // }} get missing customer address from billing address
        foreach (['customer', 'billing', 'delivery'] as $copy_key) {
            if (!isset($this->collect_order[$copy_key])) {
                continue;
            }
            $target_prefix_key = $copy_key == 'customer' ? 'customers' : $copy_key;
            if (!isset($this->collect_order[$copy_key]['firstname']) && !isset($this->collect_order[$copy_key]['lastname'])) {
                if (isset($this->collect_order[$copy_key]['name'])) {
                    list($f_n, $l_n) = explode(' ', trim($this->collect_order[$copy_key]['name']), 2);
                    $this->collect_order[$copy_key]['firstname'] = $f_n;
                    $this->collect_order[$copy_key]['lastname'] = $l_n;
                }
            }
            $platform_address = \Yii::$app->get('platform')->get_config(\common\classes\platform::default_id())->get_platform_address();
            $import_country_id = null;
            if (isset($this->collect_order[$copy_key]['country']['iso_code_2'])) {
                $import_country_id = $this->e_ptools->get_country_id($this->collect_order[$copy_key]['country']['iso_code_2']);
                if (empty($import_country_id)) {
                    $message->info('' . $import_key . ' =' . $import_key_value . " Country '" . $this->collect_order[$copy_key]['country']['iso_code_2'] . "' not found set to default");
                    $import_country_id = $platform_address['country_id'];
                }
                $import_country_info = $this->e_ptools->get_country_info($import_country_id);
                if (strlen($order_model->get_attribute($target_prefix_key . '_country')) == 0 || $this->e_ptools->get_country_id($order_model->get_attribute($target_prefix_key . '_country')) != $import_country_id) {
                    $order_model->set_attribute($target_prefix_key . '_country', $import_country_info['countries_name']);
                    $order_model->set_attribute($target_prefix_key . '_address_format_id', $import_country_info['address_format_id']);
                }
            }
            foreach ($this->collect_order[$copy_key] as $address_key => $address_value) {
                if (is_array($address_value)) {
                    continue;
                }
                $target_key = $target_prefix_key . '_' . $address_key;
                if ($order_model->can_set_property($target_key)) {
                    $order_model->{$target_key} = $address_value;
                }
            }
            if ($order_model->is_attribute_changed($target_prefix_key . '_firstname') || $order_model->is_attribute_changed($target_prefix_key . '_lastname')) {
                $order_model->{$target_prefix_key . '_name'} = $order_model->{$target_prefix_key . '_firstname'} . ' ' . $order_model->{$target_prefix_key . '_lastname'};
            }
        }
        $this->assign_customer($order_model);
        $currencies = \Yii::$container->get('currencies');
        /**
         * @var $currencies \common\classes\Currencies
         */
        if (!empty($this->collect_order['info']['currency']) && $currencies->is_set(strtoupper($this->collect_order['info']['currency']))) {
            $currency = strtoupper($this->collect_order['info']['currency']);
            $order_model->currency = $currency;
        }
        if (empty($order_model->currency)) {
            $order_model->currency = $platform_config->get_default_currency();
            if (empty($order_model->currency)) {
                $order_model->currency = \Yii::$app->get('platform')->get_config(\common\classes\platform::default_id())->get_default_currency();
            }
        }
        $currency = $order_model->currency;
        \Yii::$app->settings->set('currency', $currency);
        \Yii::$app->settings->set('currency_id', $currencies->currencies[$currency]['id']);
        if (!empty($this->collect_order['info']['currency_value'])) {
            $order_model->currency_value = $this->collect_order['info']['currency_value'];
        }
        if (empty($order_model->currency_value)) {
            $order_model->currency_value = $currencies->get_value($order_model->currency);
        }
        if (isset($this->collect_order['info']) && is_array($this->collect_order['info'])) {
            foreach ($this->collect_order['info'] as $_key => $_val) {
                if (in_array($_key, ['date_purchased', 'comments', 'order_status', 'language', 'platform_id', 'currency', 'currency_value'])) {
                    continue;
                }
                if ($order_model->can_set_property($_key)) {
                    $order_model->set_attribute($_key, $_val);
                }
            }
        }
        if (isset($this->collect_order['info']['date_purchased']) && strlen($this->collect_order['info']['date_purchased']) > 10) {
            $order_model->date_purchased = $this->e_ptools->parse_date($this->collect_order['info']['date_purchased']);
        }
        if (!empty($this->collect_order['info']['order_status'])) {
            static $cache_status = [];
            if (!isset($cache_status[$this->collect_order['info']['order_status']])) {
                $cache_status[$this->collect_order['info']['order_status']] = $this->e_ptools->lookup_order_status_id($this->collect_order['info']['order_status'], \common\helpers\Order::get_status_type_id());
            }
            if (empty($cache_status[$this->collect_order['info']['order_status']])) {
                $message->info('Order status "' . $this->collect_order['info']['order_status'] . '" not found');
            }
            if (empty($order_model->orders_status)) {
                $order_model->orders_status = intval($cache_status[$this->collect_order['info']['order_status']]);
            }
        }
        if (empty($order_model->orders_status)) {
            $order_model->orders_status = intval($platform_config->const_value('DEFAULT_ORDERS_STATUS_ID'));
        }
        $is_orders_status_changed = $order_model->is_attribute_changed('orders_status');
        foreach ($order_model->get_table_schema()->columns as $column) {
            /**
             * @var $column ColumnSchema
             */
            if (!$column->allow_null && $order_model->get_attribute($column->name) === null) {
                $order_model->set_attribute($column->name, '');
            }
        }
        if (!$order_model->save(false)) {
            foreach ($order_model->get_errors() as $attrib => $attr_errors) {
                $message->info('[!] ' . $import_key . ' =' . $import_key_value . " skipped: Save Error {$attrib} " . implode(', ', $attr_errors));
            }
            return false;
        }
        $order_model->refresh();
        // {{ products
        $db_product_collection = $order_model->get_orders_products()->all();
        foreach ($db_product_collection as $ordered_product) {
            $matched_product = false;
            foreach ($this->collect_order['products'] as $_idx => $imported_product) {
                if ($imported_product['matched']) {
                    continue;
                }
                if ($imported_product['products_id'] && strval($imported_product['products_id']) == strval($ordered_product->products_id) && strval($imported_product['uprid']) == strval($ordered_product->products_id)) {
                    $this->collect_order['products'][$_idx]['matched'] = $ordered_product->orders_products_id;
                    $imported_product['matched'] = $ordered_product->orders_products_id;
                    $matched_product = $imported_product;
                    break;
                }
            }
            if ($matched_product === false) {
                foreach ($this->collect_order['products'] as $_idx => $imported_product) {
                    if ($imported_product['matched']) {
                        continue;
                    }
                    if (strval($imported_product['model']) == strval($ordered_product->products_model)) {
                        $this->collect_order['products'][$_idx]['matched'] = $ordered_product->orders_products_id;
                        $imported_product['matched'] = $ordered_product->orders_products_id;
                        $matched_product = $imported_product;
                        break;
                    }
                }
                if ($matched_product === false) {
                    foreach ($this->collect_order['products'] as $_idx => $imported_product) {
                        if ($imported_product['matched']) {
                            continue;
                        }
                        if (strval($imported_product['name']) == strval($ordered_product->products_name)) {
                            $this->collect_order['products'][$_idx]['matched'] = $ordered_product->orders_products_id;
                            $imported_product['matched'] = $ordered_product->orders_products_id;
                            $matched_product = $imported_product;
                            break;
                        }
                    }
                }
            }
            if (is_array($matched_product)) {
                $ordered_product->set_attributes($matched_product, false);
                $ordered_product->save(false);
            } else {
                $ordered_product->delete();
            }
        }
        foreach ($this->collect_order['products'] as $_iidx => $imported_product) {
            if ($imported_product['matched'] ?? null) {
                continue;
            }
            $ordered_product = new \common\models\Orders_Products(['orders_id' => $order_model->orders_id, 'products_quantity' => 1, 'products_id' => $imported_product['products_id'], 'uprid' => $imported_product['uprid'], 'template_uprid' => $imported_product['uprid']]);
            $ordered_product->load_default_values();
            $ordered_product->set_attributes($imported_product, false);
            $ordered_product->save(false);
        }
        //$this->collectOrder['products'];
        // }} products
        if (!is_array($this->collect_order['totals'])) {
            $this->collect_order['totals'] = [];
        }
        $import_totals = $this->collect_order['totals'];
        $total_paid_amount = 0;
        foreach ($import_totals as $__idx => $_total_info) {
            if (strlen($_total_info['value_exc_vat'] ?? null) > 0) {
                $import_totals[$__idx]['value_exc_vat'] = number_format($_total_info['value_exc_vat'], 6, '.', '');
            }
            if (strlen($_total_info['value_inc_tax'] ?? null) > 0) {
                $import_totals[$__idx]['value_inc_tax'] = number_format($_total_info['value_inc_tax'], 6, '.', '');
            }
            if (strlen($_total_info['value'] ?? null) > 0) {
                $import_totals[$__idx]['value'] = number_format($_total_info['value'], 6, '.', '');
            } else if ($platform_config->const_value('DISPLAY_PRICE_WITH_TAX', 'true') == 'true') {
                $import_totals[$__idx]['value'] = $import_totals[$__idx]['value_inc_tax'];
            } else {
                $import_totals[$__idx]['value'] = $import_totals[$__idx]['value_exc_vat'];
            }
            if ($__idx == 'ot_paid') {
                $total_paid_amount = $_total_info['value_inc_tax'];
            }
        }
        $total_modules = $this->get_order_total_modules();
        $order_keys = array_flip(array_keys($total_modules));
        $db_totals_collection = $order_model->get_orders_totals()->all();
        foreach ($db_totals_collection as $db_total) {
            /**
             * @var $dbTotal \common\models\OrdersTotal
             */
            if (empty($db_total->sort_order)) {
                $db_total->sort_order = $order_keys[$db_total->class];
            }
            if (isset($import_totals[$db_total->class])) {
                $db_total->set_attributes($import_totals[$db_total->class], false);
                foreach ($db_total->get_dirty_attributes(['value_exc_vat', 'value_inc_tax', 'value']) as $changed_key => $changed_value) {
                    if ($changed_key == 'value_exc_vat') {
                        $db_total->text_exc_tax = $currencies->format($changed_value);
                    } elseif ($changed_key == 'value_inc_tax') {
                        $db_total->text_inc_tax = $currencies->format($changed_value);
                    } elseif ($changed_key == 'value') {
                        $db_total->text = $currencies->format($changed_value);
                    }
                }
                if (empty($db_total->title) && isset($total_modules[$db_total->class])) {
                    $db_total->title = $total_modules[$db_total->class] . ':';
                }
                $db_total->save(false);
                unset($import_totals[$db_total->class]);
            } else {
                $db_total->delete();
            }
        }
        // add missing
        foreach ($import_totals as $__idx => $_total_info) {
            $missing_total = new \common\models\Orders_Total(['orders_id' => $order_model->orders_id, 'class' => $__idx, 'currency' => $order_model->currency, 'currency_value' => $order_model->currency_value, 'sort_order' => (isset($order_keys[$__idx]) ? $order_keys[$__idx] : 0) + 300]);
            if (!isset($_total_info['value_exc_vat']) && isset($_total_info['value_inc_tax'])) {
                $_total_info['value_exc_vat'] = $_total_info['value_inc_tax'];
            } elseif (isset($_total_info['value_exc_vat']) && !isset($_total_info['value_inc_tax'])) {
                $_total_info['value_inc_tax'] = $_total_info['value_exc_vat'];
            }
            if (!isset($_total_info['value'])) {
                if ($platform_config->const_value('DISPLAY_PRICE_WITH_TAX', 'true') == 'true') {
                    $_total_info['value'] = $_total_info['value_inc_tax'];
                } else {
                    $_total_info['value'] = $_total_info['value_exc_vat'];
                }
            }
            $missing_total->set_attributes($_total_info, false);
            $missing_total->text_exc_tax = $currencies->format($missing_total->value_exc_vat);
            $missing_total->text_inc_tax = $currencies->format($missing_total->value_inc_tax);
            $missing_total->text = $currencies->format($missing_total->value);
            if (empty($missing_total->title) && isset($total_modules[$missing_total->class])) {
                $missing_total->title = $total_modules[$missing_total->class] . ':';
            }
            $missing_total->save(false);
        }
        if ($is_orders_status_changed) {
            $status_history = new Orders_Status_History();
            $status_history->detach_behavior('date_added');
            $status_history->load_default_values();
            $status_history->orders_id = $order_model->orders_id;
            $status_history->orders_status_id = $order_model->orders_status;
            $status_history->customer_notified = 0;
            $status_history->comments = empty($this->collect_order['info']['comments']) ? '' : $this->collect_order['info']['comments'];
            $status_history->admin_id = (int) ($_SESSION['login_id'] ?? null);
            $status_history->save(false);
        }
        if ($new_order_added) {
            $orders_history = new Orders_History(['orders_id' => $order_model->orders_id, 'comments' => 'Order imported', 'admin_id' => (int) ($_SESSION['login_id'] ?? null), 'date_added' => new Expression('NOW()')]);
            $orders_history->load_default_values();
            $orders_history->save(false);
        }
        if (isset($this->collect_order['transaction']) && is_array($this->collect_order['transaction'])) {
            if (!empty($this->collect_order['transaction']['transaction_id'])) {
                if (Orders_Transactions::find()->where(['orders_id' => $order_model->orders_id])->and_where(['transaction_id' => $this->collect_order['transaction']['transaction_id']])->count() == 0) {
                    $paid_amount = 0;
                    if (!isset($this->collect_order['transaction']['transaction_amount']) || strlen($this->collect_order['transaction']['transaction_amount']) == 0) {
                        $paid_amount = $total_paid_amount;
                    }
                    $transaction_date = 0;
                    if (!empty($this->collect_order['transaction']['date_created'])) {
                        $transaction_date = EP\Tools::get_instance()->parse_date($this->collect_order['transaction']['date_created']);
                    }
                    if ($transaction_date < 1971) {
                        $transaction_date = $order_model->date_purchased;
                    }
                    $transaction = new Orders_Transactions(['orders_id' => $order_model->orders_id, 'payment_class' => $order_model->payment_class, 'transaction_id' => $this->collect_order['transaction']['transaction_id'], 'transaction_amount' => floatval($paid_amount), 'transaction_status' => '', 'transaction_currency' => $order_model->currency, 'splinters_suborder_id' => null, 'comments' => 'Imported payment transaction', 'admin_id' => (int) $_SESSION['login_id'], 'date_created' => $transaction_date]);
                    $transaction->load_default_values();
                    $transaction->save(false);
                }
            }
        }
        if (!isset($this->collect_order['info']['shipping_weight']) || strlen($this->collect_order['info']['shipping_weight']) == 0) {
            \Yii::$app->get_db()->create_command('UPDATE orders ' . 'SET shipping_weight = (' . " SELECT SUM(products_quantity*products_weight) FROM orders_products WHERE orders_id='" . intval($order_model->orders_id) . "'" . ') ' . "WHERE orders_id='" . intval($order_model->orders_id) . "'")->execute();
        }
        if ($recalculate != 'yes') {
            $this->collect_order = [];
            $this->processed_count++;
            return true;
        }
        $manager = \common\services\Order_Manager::load_manager();
        if ($order_model) {
            $order = $manager->get_order_instance_with_id('\common\classes\Order', $order_model->orders_id);
        } else {
            $order = $manager->create_order_instance('\common\classes\Order');
        }
        echo '<pre>';
        var_dump($order);
        echo '</pre>';
        return true;
        die;
        echo '<pre>';
        var_dump($this->collect_order);
        echo '</pre>';
        die;
        $this->collect_order['info']['platform_id'] = $this->e_ptools->get_platform_name($this->collect_order['info']['platform_id']);
        $order_model = false;
        if (count($orders) == 1) {
            $order_model = $orders[0];
        }
        global $cart, $order_total_modules, $order;
        global $payment;
        $payment = '';
        if (!is_object($cart)) {
            $cart = new \common\classes\shopping_cart();
        }
        $cart->reset(true);
        $order_data = $this->collect_order;
        if (isset($order_data['products'])) {
            foreach ($order_data['products'] as $product) {
                $cart->contents[$product['uprid']] = ['qty' => $product['qty']];
            }
        }
        global $languages_id;
        $keep_language = $languages_id;
        $languages_id = \common\classes\language::default_id();
        if ($order_data['info']['language_id']) {
            $languages_id = (int) $order_data['info']['language_id'];
        }
        if ($order_data['info']['platform_id']) {
            \Yii::$app->get('platform')->config((int) $order_data['info']['platform_id']);
        }
        $tools = new EP\Tools();
        // arrange countries
        foreach (['customer', 'billing', 'delivery'] as $ab_key) {
            if (isset($order_data[$ab_key]) && isset($order_data[$ab_key]['country']['iso_code_2'])) {
                $country_id = $tools->get_country_id($order_data[$ab_key]['country']['iso_code_2']);
                $order_data[$ab_key]['country_id'] = $country_id;
                $country_info = \common\helpers\Country::get_country_info_by_id($country_id);
                $order_data[$ab_key]['country'] = ['id' => $country_id, 'title' => $country_info['countries_name'], 'iso_code_2' => $country_info['countries_iso_code_2'], 'iso_code_3' => $country_info['countries_iso_code_3']];
                $order_data[$ab_key]['format_id'] = \common\helpers\Address::get_address_format_id($country_id);
            }
            if (isset($order_data[$ab_key]) && !empty($order_data[$ab_key]['country_id']) && !empty($order_data[$ab_key]['state'])) {
                $order_data[$ab_key]['zone_id'] = \common\helpers\Zones::get_zone_id($order_data[$ab_key]['country_id'], $order_data[$ab_key]['state']);
            }
        }
        $check_customer_exists_r = tep_db_query('SELECT customers_id ' . 'FROM ' . TABLE_CUSTOMERS . ' ' . "WHERE customers_email_address='" . tep_db_query($order_data['customer']['email_address']) . "' " . 'LIMIT 1');
        if (tep_db_num_rows($check_customer_exists_r) > 0) {
            $check_customer_exists = tep_db_fetch_array($check_customer_exists_r);
            $customer_id = $check_customer_exists['customers_id'];
        } else {
            $customer_data = [
                'customers_firstname' => strval($order_data['customer']['firstname']),
                'customers_lastname' => strval($order_data['customer']['lastname']),
                'customers_email_address' => strval($order_data['customer']['email_address']),
                'customers_password' => \common\helpers\Password::encrypt_password(\common\helpers\Password::create_random_value(12), 'frontend'),
                'platform_id' => \common\classes\platform::default_id(),
                'customers_telephone' => strval($order_data['customer']['telephone']),
                //'groups_id' => '',
                'customers_company' => strval($order_data['customer']['company']),
            ];
            tep_db_perform(TABLE_CUSTOMERS, $customer_data);
            $customer_id = tep_db_insert_id();
            tep_db_perform(TABLE_CUSTOMERS_INFO, ['customers_info_id' => $customer_id, 'customers_info_date_account_created' => 'now()']);
            $customer_address_book = ['customers_id' => $customer_id, 'entry_company' => strval($order_data['customer']['company']), 'entry_firstname' => strval($order_data['customer']['firstname']), 'entry_lastname' => strval($order_data['customer']['lastname']), 'entry_street_address' => strval($order_data['customer']['street_address']), 'entry_suburb' => strval($order_data['customer']['suburb']), 'entry_postcode' => strval($order_data['customer']['postcode']), 'entry_city' => strval($order_data['customer']['city']), 'entry_state' => strval($order_data['customer']['state']), 'entry_country_id' => strval($order_data['customer']['country']['id']), 'entry_zone_id' => strval($order_data['customer']['zone_id'])];
            tep_db_perform(TABLE_ADDRESS_BOOK, $customer_address_book);
            $customers_default_address_id = tep_db_insert_id();
            tep_db_perform(TABLE_CUSTOMERS, ['customers_default_address_id' => $customers_default_address_id], 'update', "customers_id='" . (int) $customer_id . "'");
        }
        $order_data['customer']['customer_id'] = $customer_id;
        $store_order = new \common\classes\Order();
        foreach (['customer', 'billing', 'delivery'] as $ab_key) {
            if (!isset($order_data[$ab_key])) {
                continue;
            }
            $order_data[$ab_key]['address_book_id'] = $tools->address_book_find($customer_id, $order_data[$ab_key]);
            foreach (array_keys($store_order->{$ab_key}) as $key) {
                $store_order->{$ab_key}[$key] = isset($order_data[$ab_key][$key]) ? $order_data[$ab_key][$key] : null;
            }
        }
        if (isset($order_data['products'])) {
            foreach ($order_data['products'] as $imported_product) {
                foreach ($store_order->products as $_idx => $order_product) {
                    if ($imported_product['id'] != $order_product['id']) {
                        continue;
                    }
                    foreach (array_keys($order_product) as $copy_key) {
                        if (array_key_exists($copy_key, $imported_product)) {
                            $store_order->products[$_idx][$copy_key] = $imported_product[$copy_key];
                        }
                    }
                }
            }
        }
        if (isset($order_data['info']) && is_array($order_data['info'])) {
            foreach ($order_data['info'] as $__key => $__val) {
                $store_order->info[$__key] = $__val;
            }
        }
        $store_order_id = $store_order->save_order();
        $store_order->save_products(false);
        $message->info('' . $this->fields['extId']['value'] . ' =' . $order_data['info']['external_orders_id'] . " imported. Shop order #{$store_order_id}");
        if (false && isset($order_data['products'])) {
            foreach ($order_data['products'] as $imported_product) {
                $update_set = '';
                $update_set .= "units = '" . (int) $imported_product['units'] . "', ";
                $update_set .= "units_price = '" . tep_db_input($imported_product['units_price']) . "', ";
                $update_set .= "packs = '" . (int) $imported_product['packs'] . "', ";
                $update_set .= "packs_price = '" . tep_db_input($imported_product['packs_price']) . "', ";
                $update_set .= "packagings = '" . (int) $imported_product['packagings'] . "', ";
                $update_set .= "packagings_price = '" . tep_db_input($imported_product['packagings_price']) . "', ";
                $update_set = substr($update_set, 0, -2);
                tep_db_query('UPDATE ' . TABLE_ORDERS_PRODUCTS . " SET {$update_set} WHERE orders_id='" . (int) $store_order_id . "' AND uprid='" . tep_db_input($imported_product['id']) . "'");
            }
        }
        // calculate totals
        //$load_language_id = $storeOrder->info['language_id'];
        $load_language_id = $languages_id;
        \common\helpers\Translation::init('admin/main', $load_language_id);
        \common\helpers\Translation::init('admin/orders', $load_language_id);
        \common\helpers\Translation::init('admin/orders/create', $load_language_id);
        \Yii::$app->get('platform')->config()->constant_up();
        global $cart, $order, $languages_id, $payment, $shipping;
        global $total_weight, $total_count, $order_totals;
        $currencies = \Yii::$container->get('currencies');
        if ($currencies->is_set($store_order->info['currency'])) {
            $currency = $store_order->info['currency'];
            \Yii::$app->settings->set('currency', $currency);
            \Yii::$app->settings->set('currency_id', $currencies->currencies[$currency]['id']);
        }
        $cart = new shopping_cart($store_order_id);
        $order = new \common\classes\Order($store_order_id);
        foreach (array_keys($order->info) as $key) {
            if (isset($order_data['info'][$key])) {
                $order->info[$key] = $order_data['info'][$key];
            }
        }
        $languages_id = $order->info['language_id'];
        $payment = $order->info['payment_class'];
        $payment_modules = new payment();
        // $payment_modules - for selected country (was update_status)
        $payment_selection = $payment_modules->selection();
        if (empty($order->info['payment_class']) && count($payment_selection) > 0) {
            reset($payment_selection);
            $payment_select = current($payment_selection);
            if (isset($payment_select['methods']) && is_array($payment_select['methods']) && count($payment_select['methods']) > 0) {
                $payment_select = array_shift($payment_select['methods']);
            }
            $payment = $payment_select['id'];
            $order->info['payment_class'] = $payment_select['id'];
            $order->info['payment_method'] = $payment_select['module'];
            tep_db_query('UPDATE ' . TABLE_ORDERS . ' ' . "SET payment_class='" . tep_db_input($order->info['payment_class']) . "', payment_method='" . tep_db_input($order->info['payment_method']) . "' " . "WHERE orders_id='" . (int) $store_order_id . "'");
        }
        // weight and count needed for shipping !
        $total_weight = $order->info['shipping_weight'];
        $total_count = array_reduce($order->products, function ($total, $order_product) {
            return $total + $order_product['qty'];
        }, 0);
        $free_shipping = false;
        $quotes = [];
        if ($order->content_type != 'virtual' && $order->content_type != 'virtual_weight') {
            $shipping_modules = new shipping();
            if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') {
                $pass = false;
                switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
                    case 'national':
                        if ($order->delivery['country_id'] == STORE_COUNTRY) {
                            $pass = true;
                        }
                        break;
                    case 'international':
                        if ($order->delivery['country_id'] != STORE_COUNTRY) {
                            $pass = true;
                        }
                        break;
                    case 'both':
                        $pass = true;
                        break;
                }
                $free_shipping = false;
                if ($pass == true && $order->info['subtotal_inc_tax'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) {
                    $free_shipping = true;
                }
            } else {
                $free_shipping = false;
            }
            // get all available shipping quotes
            list($shipping_module, $shipping_method) = explode('_', $order->info['shipping_class']);
            $quotes = $shipping_modules->quote($shipping_method, $shipping_module);
            foreach ($quotes as $quote_info) {
                if (!is_array($quote_info['methods'])) {
                    continue;
                }
                foreach ($quote_info['methods'] as $quote_method) {
                    $shipping = ['id' => $quote_method['code'], 'title' => $quote_info['module'] . (empty($quote_method['title']) ? '' : ' (' . $quote_method['title'] . ')'), 'cost' => $quote_method['cost'], 'cost_inc_tax' => \common\helpers\Tax::add_tax_always($quote_method['cost'], isset($quote_info['tax']) ? $quote_info['tax'] : 0)];
                    break;
                }
            }
        }
        if (is_array($shipping)) {
            $order->change_shipping($shipping);
        }
        $order_total_modules = new \common\classes\order_total();
        $order_totals = $order_total_modules->process();
        unset($order->info['date_purchased']);
        $order->save_details();
        $languages_id = $keep_language;
        $this->collect_order = [];
        $this->processed_count++;
    }
    public function post_process(Messages $message)
    {
        if (count($this->collect_order) > 0) {
            $this->persist_collected_order($message);
        }
        $message->info('Processed ' . $this->processed_count . ' orders');
        $tools = new EP\Tools();
        $tools->done('orders_import');
    }
}