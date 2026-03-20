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
namespace backend\models\EP\Provider\Magento;

use backend\models\EP\Messages;
use backend\models\EP\Provider\Datasource_Interface;
use backend\models\EP\Provider\Magento\helpers\Soap_Client;
use backend\models\EP\Provider\Magento\maps\Order_Customer_Map;
use backend\models\EP\Tools;
class Import_Orders implements Datasource_Interface
{
    protected $total_count = 0;
    protected $row_count = 0;
    protected $orders_list;
    protected $config = [];
    protected $after_process_filename = '';
    protected $after_process_file = false;
    protected $client;
    public function __construct($config)
    {
        if (substr($config['client']['location'], -1) == '/') {
            $config['client']['location'] = substr($config['client']['location'], 0, -1);
        }
        $this->config = $config;
        $this->init_db();
        $pltform_config = new \common\classes\platform_config(\common\classes\platform::default_id());
        $pltform_config->constant_up();
        $p_address = $pltform_config->get_platform_address();
        $this->config['default_platform'] = \common\helpers\Country::get_countries($p_address['country_id'], true);
        $manager = \common\services\Order_Manager::load_manager();
        $order_total_modules = $manager->get_total_collection();
        $payment_modules = $manager->get_payment_collection();
        if (is_array($this->config['predefined_status'])) {
            $this->config['predefined_status'] = array_flip($this->config['predefined_status']);
            foreach ($this->config['predefined_status'] as $key => $status) {
                $ex = explode('_', $status);
                if (isset($ex[1])) {
                    $this->config['predefined_status'][$key] = $ex[1];
                }
            }
        }
    }
    public function allow_run_in_popup()
    {
        return true;
    }
    public function init_db()
    {
        tep_db_query('CREATE TABLE IF NOT EXISTS ep_holbi_soap_link_orders(
   ep_directory_id INT(11) NOT NULL,
   remote_order_id INT(11) NOT NULL,
   local_order_id INT(11) NOT NULL,
   KEY(ep_directory_id, remote_order_id),
   UNIQUE KEY(local_order_id)
);');
    }
    public function get_progress()
    {
        if ($this->total_count > 0) {
            $percent_done = min(100, $this->row_count / $this->total_count * 100);
        } else {
            $percent_done = 100;
        }
        return number_format($percent_done, 1, '.', '');
    }
    public function prepare_process(Messages $message)
    {
        $mg = new Soap_Client($this->config['client']);
        $this->client = $mg->get_client();
        $this->session = $mg->login_client();
        $this->config['assign_platform'] = \common\classes\platform::default_id();
        $this->get_orders_list();
        $this->total_count = count($this->orders_list);
        $this->after_process_filename = tempnam($this->config['workingDirectory'], 'after_process');
        $this->after_process_file = fopen($this->after_process_filename, 'w+');
    }
    public function get_orders_list()
    {
        try {
            $this->orders_list = $this->client->call($this->session, 'sales_order.list');
            if (is_array($this->orders_list)) {
                if (isset($this->config['trunkate_orders'])) {
                    \common\helpers\Order::trunk_orders();
                    tep_db_query("delete from ep_holbi_soap_link_orders where ep_directory_id = '" . (int) $this->config['directoryId'] . "'");
                }
            }
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customers list error');
        }
    }
    public function get_order_info($id)
    {
        try {
            $result = $this->client->call($this->session, 'sales_order.info', $id);
        } catch (\Exception $ex) {
            throw new \Exception('Fetch order ' . $id . ' info error');
        }
        return $result;
    }
    public function get_customer_address_info($id)
    {
        try {
            $result = $this->client->call($this->session, 'customer_address.info', $id);
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customer address info error');
        }
        return $result;
    }
    public function get_customer_addresses($id)
    {
        try {
            $result = $this->client->call($this->session, 'customer_address.list', $id);
            if ($result) {
                if (is_array($result)) {
                    foreach ($result as $key => $address) {
                        $result[$key] = array_merge($result[$key], $this->get_customer_address_info($address['customer_address_id']));
                    }
                }
            }
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customer ' . $id . ' info error');
        }
        return $result;
    }
    public function process_row(Messages $message)
    {
        $remote_order = current($this->orders_list);
        if (!$remote_order) {
            return false;
        }
        try {
            $this->process_remote_order($remote_order['increment_id']);
        } catch (\Exception $ex) {
            throw new \Exception('Processing order error (increment ID: ' . $remote_order['increment_id'] . ')');
        }
        $this->row_count++;
        next($this->orders_list);
        return true;
    }
    public function post_process(Messages $message)
    {
        return;
    }
    protected function process_remote_order($remote_order_id)
    {
        global $cart, $sendto, $billto;
        $currencies = \Yii::$container->get('currencies');
        static $timing = ['soap' => 0, 'local' => 0];
        $t1 = microtime(true);
        $local_id = $this->lookup_local_id($remote_order_id);
        if (!$local_id) {
            $remote_order = $this->get_order_info($remote_order_id);
            $t2 = microtime(true);
            $timing['soap'] += $t2 - $t1;
            if ($remote_order) {
                if (!is_object($cart)) {
                    $cart = new \common\classes\shopping_cart();
                }
                $cart->reset(true);
                $local_order = new \common\classes\Order();
                $session = new \yii\web\Session();
                $local_customer = \common\api\models\AR\Customer::find()->where(['customers_email_address' => $remote_order['customer_email']])->one();
                $import_array = $this->map_customer($remote_order);
                $import_array['customers_status'] = 1;
                if (!$local_customer) {
                    $local_customer = new \common\api\models\AR\Customer();
                    $import_array['customers_status'] = 0;
                }
                $import_array['platform_id'] = $this->config['assign_platform'];
                $sendto = $billto = null;
                try {
                    $local_customer->import_array($import_array);
                    $addresses = $local_customer->init_collection_by_lookup_key_addresses([]);
                } catch (\Exception $ex) {
                    //                    mail('akoshelev@holbi.co.uk','importOrder', print_r($remoteOrder, 1));
                    //                    mail('akoshelev@holbi.co.uk','importCustomer', print_r($importArray, 1));
                    echo $ex->get_message();
                }
                if (is_array($addresses) && count($addresses)) {
                    $addresses[0]->is_default = 1;
                }
                if ($local_customer->validate() && $local_customer->is_new_record) {
                    try {
                        $local_customer->save(false);
                        $addresses = $local_customer->init_collection_by_lookup_key_addresses([]);
                    } catch (\Exception $ex) {
                        mail('akoshelev@holbi.co.uk', 'saveError', print_r($ex, 1));
                    }
                } elseif ($local_customer->validate() && !$local_customer->is_new_record) {
                    // update addresses
                    $addresses = $local_customer->init_collection_by_lookup_key_addresses([]);
                    if (is_array($addresses) && count($addresses)) {
                        foreach ($addresses as $_address) {
                            $_address->pending_removal = false;
                            try {
                                if ($_address->validate()) {
                                    $_address->save(false);
                                }
                            } catch (\Exception $ex) {
                                echo $ex->get_message();
                            }
                        }
                    }
                }
                if (is_array($addresses)) {
                    if (count($addresses) == 1) {
                        $sendto = $billto = $addresses[0]->address_book_id;
                    } elseif (count($addresses) == 2) {
                        $sendto = $addresses[0]->address_book_id;
                        $billto = $addresses[1]->address_book_id;
                    }
                }
                if (empty($sendto) || empty($billto)) {
                    $c_info = \common\helpers\Customer::get_customer_data($local_customer->customers_id);
                    if ($c_info) {
                        if (empty($sendto)) {
                            $sendto = $c_info['customers_default_address_id'];
                        }
                        if (empty($billto)) {
                            $billto = $c_info['customers_default_address_id'];
                        }
                    }
                }
                if (is_null($sendto)) {
                    $sendto = $billto;
                }
                if (is_null($billto)) {
                    $billto = $sendto;
                }
                $session->set('customer_id', $local_customer->customers_id);
                $cart->currency = $remote_order['order_currency_code'];
                $local_order->cart();
                $local_order->info['payment_method'] = $remote_order['payment']['method'];
                $local_order->info['payment_class'] = $remote_order['payment']['method'];
                $local_order->info['shipping_class'] = $remote_order['shipping_method'];
                $local_order->info['shipping_weight'] = $remote_order['weight'];
                $local_order->info['shipping_method'] = $remote_order['shipping_description'];
                $local_order->info['shipping_cost'] = (float) $remote_order['shipping_amount'] + (float) $remote_order['shipping_tax_amount'];
                $local_order->info['shipping_cost_inc_tax'] = (float) $remote_order['shipping_amount'] + (float) $remote_order['shipping_tax_amount'];
                $local_order->info['shipping_cost_exc_tax'] = (float) $remote_order['shipping_amount'];
                $local_order->info['subtotal'] = (float) $remote_order['subtotal_incl_tax'];
                $local_order->info['subtotal_inc_tax'] = (float) $remote_order['subtotal_incl_tax'];
                $local_order->info['subtotal_exc_tax'] = (float) $remote_order['subtotal'];
                $local_order->info['tax'] = (float) $remote_order['tax_amount'];
                $local_order->info['total_inc_tax'] = (float) $remote_order['grand_total'];
                $local_order->info['total_exc_tax'] = (float) $remote_order['grand_total'] - (float) $remote_order['tax_amount'];
                $local_order->info['total'] = (float) $remote_order['grand_total'];
                $local_order->info['total_paid_inc_tax'] = (float) $remote_order['total_paid'];
                $local_order->info['total_paid_exc_tax'] = (float) $remote_order['total_paid'];
                $local_order->info['order_status'] = $this->config['predefined_status'][$remote_order['status']];
                global $payment;
                try {
                    if (!is_object($GLOBALS[$remote_order['payment']['method']]) && !empty($remote_order['payment']['method'])) {
                        eval("class {$remote_order['payment']['method']} { }");
                        $GLOBALS[$remote_order['payment']['method']] = new $remote_order['payment']['method']();
                        $GLOBALS[$remote_order['payment']['method']]->dont_update_stock = true;
                        $payment = $remote_order['payment']['method'];
                        $local_order->info['payment_method'] = 'Payment ' . $remote_order['payment']['method'] . ' from Magento';
                        $local_order->info['payment_class'] = $remote_order['payment']['method'];
                    } elseif (is_object($GLOBALS[$remote_order['payment']['method']])) {
                        $local_order->info['payment_method'] = $GLOBALS[$remote_order['payment']['method']]->title;
                    }
                } catch (\Exception $ex) {
                    throw new \Exception($ex->get_message());
                }
                $tools = new Tools();
                if (is_array($remote_order['items'])) {
                    $products = $remote_order['items'];
                    $index = 0;
                    $add_manual_products = [];
                    for ($i = 0; $i < $remote_order['total_item_count']; $i++) {
                        //echo '<pre>';print_r(unserialize($products[$i]['product_options']));
                        $tl_product = \common\api\models\AR\Products::find()->where(['products_id' => (int) $this->lookup_local_product_id($products[$i]['product_id'])])->one();
                        $attributes = [];
                        if (!is_null($products[$i]['product_options'])) {
                            $unserialize = unserialize($products[$i]['product_options']);
                            if (is_array($unserialize['options']) && count($unserialize['options']) > 0) {
                                for ($j = 0; $j < count($unserialize['options']); $j++) {
                                    if (in_array($unserialize['options'][$j]['option_type'], ['checkbox', 'drop_down'])) {
                                        $option_id = $tools->get_option_by_name($unserialize['options'][$j]['label']);
                                        $option_value_id = $tools->get_option_value_by_name($option_id, $unserialize['options'][$j]['value']);
                                        if ($option_id && $option_value_id) {
                                            $attributes[] = ['products_options' => $unserialize['options'][$j]['label'], 'products_options_values' => $unserialize['options'][$j]['value'], 'option_id' => $option_id, 'value_id' => $option_value_id];
                                        }
                                    }
                                }
                            }
                        }
                        $_product = ['qty' => $products[$i]['qty_ordered'], 'name' => $products[$i]['name'], 'model' => $products[$i]['sku'], 'is_virtual' => isset($products[$i]['is_virtual']) ? intval($products[$i]['is_virtual']) : 0, 'gv_state' => preg_match('/^GIFT/', $products[$i]['sku']) ? 'pending' : 'none', 'tax' => $products[$i]['tax_amount'], 'ga' => 0, 'price' => (float) $products[$i]['price'], 'final_price' => (float) $products[$i]['price'] + (float) $products[$i]['tax_amount'], 'weight' => $products[$i]['weight'], 'id' => \common\helpers\Inventory::normalize_id(\common\helpers\Inventory::get_uprid($tl_product->products_id, $attributes)), 'attributes' => $attributes];
                        if ($tl_product) {
                            $local_order->products[$index] = $_product;
                            $index++;
                        } else {
                            $_product['id'] = 0;
                            $add_manual_products[] = $_product;
                        }
                    }
                }
                $insert_id = $local_order->save_order();
                if ($insert_id) {
                    tep_db_query('update ' . $local_order->table_prefix . TABLE_ORDERS . " set date_purchased = '" . $remote_order['created_at'] . "' where orders_id = '" . (int) $insert_id . "'");
                }
                global $order_totals;
                $order_totals = [];
                if (is_object($GLOBALS['ot_subtotal'])) {
                    $order_totals[] = ['code' => 'ot_subtotal', 'title' => $GLOBALS['ot_subtotal']->title, 'text' => $currencies->format($local_order->info['subtotal'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'value' => $local_order->info['subtotal'], 'sort_order' => 1, 'text_exc_tax' => $currencies->format($local_order->info['subtotal_exc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'text_inc_tax' => $currencies->format($local_order->info['subtotal_inc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $local_order->info['subtotal_exc_tax'], 'value_inc_tax' => $local_order->info['subtotal_inc_tax'], 'difference' => 0];
                }
                if ((float) $remote_order['discount_amount'] != 0 && is_object($GLOBALS['ot_coupon'])) {
                    $remote_order['discount_amount'] = (float) $remote_order['discount_amount'];
                    $text = $currencies->format($remote_order['discount_amount'], true, $local_order->info['currency'], $local_order->info['currency_value']);
                    $order_totals[] = ['code' => 'ot_coupon', 'title' => $GLOBALS['ot_coupon']->title . ' (' . $remote_order['discount_description'] . ')', 'text' => $text, 'value' => $local_order->info['discount_amount'], 'sort_order' => 2, 'text_exc_tax' => $text, 'text_inc_tax' => $text, 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $remote_order['discount_amount'], 'value_inc_tax' => $remote_order['discount_amount'], 'difference' => 0];
                }
                if (is_object($GLOBALS['ot_shipping'])) {
                    $order_totals[] = ['code' => 'ot_shipping', 'title' => $GLOBALS['ot_shipping']->title, 'text' => $currencies->format($local_order->info['shipping_cost'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'value' => $local_order->info['shipping_cost'], 'sort_order' => 3, 'text_exc_tax' => $currencies->format($local_order->info['shipping_cost_exc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'text_inc_tax' => $currencies->format($local_order->info['shipping_cost_inc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $local_order->info['shipping_cost_exc_tax'], 'value_inc_tax' => $local_order->info['shipping_cost_inc_tax'], 'difference' => 0];
                }
                if (is_object($GLOBALS['ot_subtax'])) {
                    $value = $local_order->info['subtotal'] + $local_order->info['shipping_cost'] - $remote_order['discount_amount'];
                    $value_inc = $local_order->info['subtotal_inc_tax'] + $local_order->info['shipping_cost_inc_tax'] - $remote_order['discount_amount'];
                    $value_exc = $local_order->info['subtotal_exc_tax'] + $local_order->info['shipping_cost_exc_tax'] - $remote_order['discount_amount'];
                    $order_totals[] = ['code' => 'ot_subtax', 'title' => $GLOBALS['ot_subtax']->title, 'text' => $currencies->format($value, true, $local_order->info['currency'], $local_order->info['currency_value']), 'value' => $value, 'sort_order' => 4, 'text_exc_tax' => $currencies->format($value_exc, true, $local_order->info['currency'], $local_order->info['currency_value']), 'text_inc_tax' => $currencies->format($value_inc, true, $local_order->info['currency'], $local_order->info['currency_value']), 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $value_exc, 'value_inc_tax' => $value_inc, 'difference' => 0];
                }
                if (is_object($GLOBALS['ot_tax'])) {
                    $text = $currencies->format($local_order->info['tax'], true, $local_order->info['currency'], $local_order->info['currency_value']);
                    $order_totals[] = ['code' => 'ot_tax', 'title' => $GLOBALS['ot_tax']->title, 'text' => $text, 'value' => $local_order->info['tax'], 'sort_order' => 5, 'text_exc_tax' => $text, 'text_inc_tax' => $text, 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $local_order->info['tax'], 'value_inc_tax' => $local_order->info['tax'], 'difference' => 0];
                }
                if (is_object($GLOBALS['ot_total'])) {
                    $order_totals[] = ['code' => 'ot_total', 'title' => $GLOBALS['ot_total']->title, 'text' => $currencies->format($local_order->info['total'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'value' => $local_order->info['total'], 'sort_order' => 6, 'text_exc_tax' => $currencies->format($local_order->info['total_exc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'text_inc_tax' => $currencies->format($local_order->info['total_inc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $local_order->info['total_exc_tax'], 'value_inc_tax' => $local_order->info['total_inc_tax'], 'difference' => 0];
                }
                if (is_object($GLOBALS['ot_paid'])) {
                    $order_totals[] = ['code' => 'ot_paid', 'title' => $GLOBALS['ot_paid']->title, 'text' => $currencies->format($local_order->info['total_paid_inc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'value' => $local_order->info['total_paid_inc_tax'], 'sort_order' => 7, 'text_exc_tax' => $currencies->format($local_order->info['total_paid_exc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'text_inc_tax' => $currencies->format($local_order->info['total_paid_inc_tax'], true, $local_order->info['currency'], $local_order->info['currency_value']), 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $local_order->info['total_paid_exc_tax'], 'value_inc_tax' => $local_order->info['total_paid_inc_tax'], 'difference' => 0];
                }
                if (is_object($GLOBALS['ot_due'])) {
                    $value_inc = $local_order->info['total_inc_tax'] - $local_order->info['total_paid_inc_tax'];
                    $value_exc = $local_order->info['total_exc_tax'] - $local_order->info['total_paid_exc_tax'];
                    $order_totals[] = ['code' => 'ot_due', 'title' => $GLOBALS['ot_due']->title, 'text' => $currencies->format($value_inc, true, $local_order->info['currency'], $local_order->info['currency_value']), 'value' => $value_inc, 'sort_order' => 8, 'text_exc_tax' => $currencies->format($value_exc, true, $local_order->info['currency'], $local_order->info['currency_value']), 'text_inc_tax' => $currencies->format($value_inc, true, $local_order->info['currency'], $local_order->info['currency_value']), 'adjusted' => 0, 'tax_class_id' => 0, 'value_exc_vat' => $value_exc, 'value_inc_tax' => $value_inc, 'difference' => 0];
                }
                $local_order->status = 'migrated';
                $local_order->migrated = $this->config['client']['location'];
                $local_order->save_details();
                if (is_array($remote_order['status_history']) && count($remote_order['status_history'])) {
                    tep_db_query('delete from ' . $local_order->table_prefix . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . $local_order->order_id . "'");
                    $history = $remote_order['status_history'];
                    try {
                        $history = \yii\helpers\Array_Helper::index($history, 'created_at');
                    } catch (\Exception $ex) {
                        $history = null;
                    }
                    if (!is_null($history) && $local_order->order_id) {
                        ksort($history);
                        foreach ($history as $date => $_history) {
                            $sql_array = ['orders_id' => $local_order->order_id, 'orders_status_id' => $this->config['predefined_status'][$_history['status']], 'date_added' => $date, 'customer_notified' => (int) $_history['is_customer_notified'], 'comments' => tep_db_prepare_input($_history['comment'])];
                            tep_db_perform($local_order->table_prefix . TABLE_ORDERS_STATUS_HISTORY, $sql_array);
                        }
                    }
                }
                try {
                    $local_order->save_products(false);
                    if (is_array($add_manual_products) && count($add_manual_products) > 0) {
                        foreach ($add_manual_products as $_product) {
                            $sql_data_array = ['orders_id' => $local_order->order_id, 'products_id' => \common\helpers\Inventory::get_prid($_product['id']), 'products_model' => $_product['model'], 'products_name' => $_product['name'], 'products_price' => $_product['price'], 'final_price' => $_product['final_price'], 'products_tax' => $_product['tax'], 'products_quantity' => $_product['qty'], 'is_giveaway' => $_product['ga'], 'is_virtual' => $_product['is_virtual'], 'gv_state' => $_product['gv_state'], 'uprid' => $_product['id']];
                            tep_db_perform($local_order->table_prefix . TABLE_ORDERS_PRODUCTS, $sql_data_array);
                            $order_products_id = tep_db_insert_id();
                            if (isset($_product['attributes'])) {
                                for ($j = 0, $n2 = sizeof($_product['attributes']); $j < $n2; $j++) {
                                    $sql_data_array = ['orders_id' => $local_order->order_id, 'orders_products_id' => $order_products_id, 'products_options' => $_product['attributes'][$j]['products_options'], 'products_options_values' => $_product['attributes'][$j]['products_options_values'], 'options_values_price' => 0, 'price_prefix' => '+', 'products_options_id' => $_product['attributes'][$j]['option_id'], 'products_options_values_id' => $_product['attributes'][$j]['value_id']];
                                    tep_db_perform($local_order->table_prefix . TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);
                                }
                            }
                        }
                    }
                } catch (\Exception $ex) {
                    echo '<pre>';
                    print_r($ex);
                }
                if ($local_order->order_id) {
                    $this->link_remote_with_local_id($remote_order_id, $local_order->order_id);
                }
                //echo '<pre>';print_r($localOrder);die;
                //$localAdresses = $localCustomer->initCollectionByLookupKey_Addresses(null);
                unset($local_customer);
                unset($local_order);
            }
        }
        //echo'<pre>';print_r($localCustomer);print_r($localOrder);die;
        $t3 = microtime(true);
        $timing['local'] += $t3 - $t2;
        //echo '<pre>';  var_dump($timing);    echo '</pre>';
    }
    protected function lookup_local_product_id($remote_id)
    {
        $get_local_id_r = tep_db_query('SELECT local_products_id ' . 'FROM ep_holbi_soap_link_products ' . "WHERE ep_directory_id='" . (int) $this->config['directoryId'] . "' " . " AND remote_products_id='" . $remote_id . "'");
        if (tep_db_num_rows($get_local_id_r) > 0) {
            $_local_id = tep_db_fetch_array($get_local_id_r);
            tep_db_free_result($get_local_id_r);
            return $_local_id['local_products_id'];
        }
        return false;
    }
    protected function lookup_local_id($remote_id)
    {
        $get_local_id_r = tep_db_query('SELECT local_order_id ' . 'FROM ep_holbi_soap_link_orders ' . "WHERE ep_directory_id='" . (int) $this->config['directoryId'] . "' " . " AND remote_order_id='" . $remote_id . "'");
        if (tep_db_num_rows($get_local_id_r) > 0) {
            $_local_id = tep_db_fetch_array($get_local_id_r);
            tep_db_free_result($get_local_id_r);
            return $_local_id['local_order_id'];
        }
        return false;
    }
    protected function link_remote_with_local_id($remote_id, $local_id)
    {
        tep_db_query('INSERT INTO ep_holbi_soap_link_orders(ep_directory_id, remote_order_id, local_order_id ) ' . ' VALUES ' . " ('" . (int) $this->config['directoryId'] . "', '" . $remote_id . "','" . $local_id . "') " . "ON DUPLICATE KEY UPDATE ep_directory_id='" . (int) $this->config['directoryId'] . "', remote_order_id='" . $remote_id . "'");
        return true;
    }
    protected function map_customer($response_object)
    {
        $order = json_decode(json_encode($response_object), true);
        $t1 = microtime(true);
        $simple = Order_Customer_Map::aplly_mapping($order);
        //$simple['customer_id'] = (int) $order['customer_id'];
        return $simple;
    }
}