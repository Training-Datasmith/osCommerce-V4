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
namespace common\api\Classes;

class Order
{
    public $orders_id;
    public $orders;
    public $orders_products;
    public $orders_history;
    public $orders_status_history;
    public $orders_total;
    public $orders_transactions;
    public $orders_payment;
    public $tracking_number_record_array;
    // settings
    public $delete_status_historybefore_creation = false;
    public function set($order_array = [])
    {
        $this->orders_id = 0;
        $this->orders = [];
        $this->orders_products = [];
        $this->orders_history = [];
        $this->orders_status_history = [];
        $this->orders_total = [];
        $this->orders_transactions = [];
        $this->orders_payment = [];
        $this->tracking_number_record_array = [];
        foreach ($order_array as $key => $value) {
            if (isset($this->{$key})) {
                $this->{$key} = $value;
            }
        }
        unset($order_array);
        unset($value);
        unset($key);
        return true;
    }
    public function get($property_name = null)
    {
        if (is_null($property_name)) {
            $response = [];
            foreach ((new \Reflection_Object($this))->get_properties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $response[$property->name] = $this->{$property->name};
            }
        } elseif (property_exists($this, $property_name)) {
            $response = $this->{$property_name};
        }
        return $response;
    }
    public function message_get()
    {
        return ['Unknown error in Order API!' => 'error'];
    }
    public function load($orders_id)
    {
        $this->orders_id = $orders_id;
        $this->orders = \common\models\Orders::find()->where(['orders_id' => $orders_id])->as_array()->one();
        if (isset($this->orders['orders_id'])) {
            unset($this->orders['orders_id']);
        }
        $this->orders_history = \common\models\Orders_History::find()->where(['orders_id' => $orders_id])->order_by('orders_history_id')->as_array()->all();
        if (is_array($this->orders_history)) {
            foreach ($this->orders_history as $index => $value) {
                /*if (isset($this->orders_history[$index]['orders_history_id'])) {
                      unset($this->orders_history[$index]['orders_history_id']);
                  }*/
                if (isset($this->orders_history[$index]['orders_id'])) {
                    unset($this->orders_history[$index]['orders_id']);
                }
            }
        }
        $this->orders_status_history = \common\models\Orders_Status_History::find()->where(['orders_id' => $orders_id])->order_by('orders_status_history_id')->as_array()->all();
        if (is_array($this->orders_status_history)) {
            foreach ($this->orders_status_history as $index => $value) {
                /*if (isset($this->orders_status_history[$index]['orders_status_history_id'])) {
                      unset($this->orders_status_history[$index]['orders_status_history_id']);
                  }*/
                if (isset($this->orders_status_history[$index]['orders_id'])) {
                    unset($this->orders_status_history[$index]['orders_id']);
                }
            }
        }
        $this->orders_total = \common\models\Orders_Total::find()->where(['orders_id' => $orders_id])->order_by('sort_order')->as_array()->all();
        if (is_array($this->orders_total)) {
            foreach ($this->orders_total as $index => $value) {
                /*if (isset($this->orders_total[$index]['orders_total_id'])) {
                      unset($this->orders_total[$index]['orders_total_id']);
                  }*/
                if (isset($this->orders_total[$index]['orders_id'])) {
                    unset($this->orders_total[$index]['orders_id']);
                }
            }
        }
        $this->orders_transactions = \common\models\Orders_Transactions::find()->where(['orders_id' => $orders_id])->order_by('orders_transactions_id')->as_array()->all();
        if (is_array($this->orders_transactions)) {
            foreach ($this->orders_transactions as $index => $value) {
                if (isset($this->orders_transactions[$index]['orders_id'])) {
                    unset($this->orders_transactions[$index]['orders_id']);
                }
            }
        }
        $this->orders_payment = \common\models\Orders_Payment::find()->where(['orders_payment_order_id' => $orders_id])->order_by('orders_payment_id')->as_array()->all();
        if (is_array($this->orders_payment)) {
            foreach ($this->orders_payment as $index => $value) {
                if (isset($this->orders_payment[$index]['orders_payment_order_id'])) {
                    unset($this->orders_payment[$index]['orders_payment_order_id']);
                }
            }
        }
        $orders_products = \common\models\Orders_Products::find()->where(['orders_id' => $orders_id])->order_by(['sort_order' => SORT_ASC, 'orders_products_id' => SORT_ASC])->as_array()->all();
        if (is_array($orders_products)) {
            foreach ($orders_products as $value) {
                $orders_products_id = $value['orders_products_id'];
                unset($value['orders_id']);
                //unset($value['orders_products_id']);
                $this->orders_products[$orders_products_id] = $value;
            }
        }
        $orders_products_allocate = \common\models\Orders_Products_Allocate::find()->where(['orders_id' => $orders_id])->order_by('orders_products_id')->as_array()->all();
        if (is_array($orders_products_allocate)) {
            foreach ($orders_products_allocate as $value) {
                $orders_products_id = $value['orders_products_id'];
                unset($value['orders_id']);
                unset($value['orders_products_id']);
                if (isset($this->orders_products[$orders_products_id])) {
                    $this->orders_products[$orders_products_id]['orders_products_allocate'][] = $value;
                }
            }
        }
        $orders_products_attributes = \common\models\Orders_Products_Attributes::find()->where(['orders_id' => $orders_id])->order_by('orders_products_id')->as_array()->all();
        if (is_array($orders_products_attributes)) {
            foreach ($orders_products_attributes as $value) {
                $orders_products_id = $value['orders_products_id'];
                unset($value['orders_id']);
                unset($value['orders_products_id']);
                if (isset($this->orders_products[$orders_products_id])) {
                    $this->orders_products[$orders_products_id]['orders_products_attributes'][] = $value;
                }
            }
        }
        $orders_products_download = \common\models\Orders_Products_Download::find()->where(['orders_id' => $orders_id])->order_by('orders_products_id')->as_array()->all();
        if (is_array($orders_products_download)) {
            foreach ($orders_products_download as $value) {
                $orders_products_id = $value['orders_products_id'];
                unset($value['orders_id']);
                unset($value['orders_products_id']);
                if (isset($this->orders_products[$orders_products_id])) {
                    $this->orders_products[$orders_products_id]['orders_products_download'][] = $value;
                }
            }
        }
        $orders_products_status_history = \common\models\Orders_Products_Status_History::find()->where(['orders_id' => $orders_id])->order_by('orders_products_id')->as_array()->all();
        if (is_array($orders_products_status_history)) {
            foreach ($orders_products_status_history as $value) {
                $orders_products_id = $value['orders_products_id'];
                unset($value['orders_id']);
                unset($value['orders_products_id']);
                unset($value['orders_products_history_id']);
                if (isset($this->orders_products[$orders_products_id])) {
                    $this->orders_products[$orders_products_id]['orders_products_status_history'][] = $value;
                }
            }
        }
        $this->tracking_number_record_array = \common\models\Tracking_Numbers::find()->where(['orders_id' => $this->orders_id])->order_by(['tracking_numbers_id' => SORT_ASC])->as_array()->all();
    }
    public function create()
    {
        $this->orders_id = 0;
        $this->save();
    }
    public function save($is_create = false)
    {
        $is_create = (int) $is_create > 0 ? true : false;
        $orders_id = (int) ((int) $this->orders_id > 0 ? $this->orders_id : 0);
        if ($orders_id > 0) {
            $orders = \common\models\Orders::find()->where(['orders_id' => $orders_id])->one();
            if ($is_create == true and !$orders instanceof \common\models\Orders) {
                $orders = new \common\models\Orders();
                $orders->load_default_values();
                $orders->orders_id = $orders_id;
            }
        } else {
            $orders = new \common\models\Orders();
            $orders->load_default_values();
        }
        if (!is_object($orders)) {
            return false;
        }
        if (is_array($this->orders)) {
            $orders->set_attributes($this->orders, false);
        }
        $orders->save();
        //$orders->orders_id
        if (is_array($this->orders_history)) {
            foreach ($this->orders_history as $item) {
                if ($orders_id > 0) {
                    $orders_history = \common\models\Orders_History::find()->where(['orders_history_id' => $item['orders_history_id']])->one();
                    if ($is_create == true) {
                        if (!$orders_history instanceof \common\models\Orders_History) {
                            $item['orders_id'] = $orders->orders_id;
                            $orders_history = new \common\models\Orders_History();
                            $orders_history->set_attributes($item, false);
                            $orders_history = \common\models\Orders_History::find_one($orders_history->to_array());
                            if (!$orders_history instanceof \common\models\Orders_History) {
                                $orders_history = new \common\models\Orders_History();
                                $orders_history->load_default_values();
                            }
                        }
                        $orders_history->orders_id = $orders->orders_id;
                    }
                } else {
                    if (isset($item['orders_history_id'])) {
                        unset($item['orders_history_id']);
                    }
                    $orders_history = new \common\models\Orders_History();
                    $orders_history->load_default_values();
                    $orders_history->orders_id = $orders->orders_id;
                }
                $orders_history->set_attributes($item, false);
                $orders_history->save();
            }
        }
        if (is_array($this->orders_status_history)) {
            $renew_full = $this->delete_status_historybefore_creation && $is_create == true && $orders_id > 0;
            if ($renew_full) {
                \common\models\Orders_Status_History::delete_all(['orders_id' => $orders_id]);
            }
            foreach ($this->orders_status_history as $item) {
                if ($orders_id > 0) {
                    $orders_status_history = \common\models\Orders_Status_History::find()->where(['orders_status_history_id' => $item['orders_status_history_id']])->one();
                    if ($is_create == true) {
                        if (!$orders_status_history instanceof \common\models\Orders_Status_History) {
                            $item['orders_id'] = $orders->orders_id;
                            if ($renew_full) {
                                $orders_status_history = new \common\models\Orders_Status_History();
                                $orders_status_history->load_default_values();
                            } else {
                                $orders_status_history = new \common\models\Orders_Status_History();
                                $orders_status_history->set_attributes($item, false);
                                $orders_status_history = \common\models\Orders_Status_History::find_one($orders_status_history->to_array());
                                if (!$orders_status_history instanceof \common\models\Orders_Status_History) {
                                    $orders_status_history = new \common\models\Orders_Status_History();
                                    $orders_status_history->load_default_values();
                                }
                            }
                        }
                        $orders_status_history->detach_behavior('date_added');
                        if (!isset($item['date_added']) and $orders_status_history->is_new_record or isset($item['date_added']) and $item['date_added'] == '') {
                            $item['date_added'] = date('Y-m-d H:i:s');
                        }
                        $orders_status_history->orders_id = $orders->orders_id;
                    }
                } else {
                    if (isset($item['orders_status_history_id'])) {
                        unset($item['orders_status_history_id']);
                    }
                    $orders_status_history = new \common\models\Orders_Status_History();
                    $orders_status_history->load_default_values();
                    $orders_status_history->orders_id = $orders->orders_id;
                }
                $orders_status_history->set_attributes($item, false);
                $orders_status_history->save();
            }
        }
        if (is_array($this->orders_total)) {
            foreach ($this->orders_total as $item) {
                if ($orders_id > 0) {
                    $orders_total = \common\models\Orders_Total::find()->where(['orders_total_id' => $item['orders_total_id']])->one();
                    if ($is_create == true) {
                        if (!$orders_total instanceof \common\models\Orders_Total) {
                            $orders_total = \common\models\Orders_Total::find()->where(['orders_id' => $orders->orders_id, 'class' => $item['class']])->one();
                            if (!$orders_total instanceof \common\models\Orders_Total) {
                                $orders_total = new \common\models\Orders_Total();
                                $orders_total->load_default_values();
                            }
                        }
                        $orders_total->orders_id = $orders->orders_id;
                    }
                } else {
                    if (isset($item['orders_total_id'])) {
                        unset($item['orders_total_id']);
                    }
                    $orders_total = new \common\models\Orders_Total();
                    $orders_total->load_default_values();
                    $orders_total->orders_id = $orders->orders_id;
                }
                $orders_total->set_attributes($item, false);
                $orders_total->save();
            }
        }
        if (is_array($this->orders_transactions)) {
            foreach ($this->orders_transactions as $item) {
                if ($orders_id > 0) {
                    $orders_transactions = \common\models\Orders_Transactions::find()->where(['orders_transactions_id' => $item['orders_transactions_id']])->one();
                    if ($is_create == true) {
                        if (!$orders_transactions instanceof \common\models\Orders_Transactions) {
                            $orders_transactions = \common\models\Orders_Transactions::find()->where(['orders_id' => $orders->orders_id, 'transaction_id' => $item['transaction_id']])->one();
                            if (!$orders_transactions instanceof \common\models\Orders_Transactions) {
                                $orders_transactions = new \common\models\Orders_Transactions();
                                $orders_transactions->load_default_values();
                            }
                        }
                        $orders_transactions->orders_id = $orders->orders_id;
                    }
                } else {
                    if (isset($item['orders_transactions_id'])) {
                        unset($item['orders_transactions_id']);
                    }
                    $orders_transactions = new \common\models\Orders_Transactions();
                    $orders_transactions->load_default_values();
                    $orders_transactions->orders_id = $orders->orders_id;
                }
                $orders_transactions->set_attributes($item, false);
                $orders_transactions->save();
            }
        }
        if (is_array($this->orders_payment)) {
            foreach ($this->orders_payment as $item) {
                if ($orders_id > 0) {
                    $orders_payment = \common\models\Orders_Payment::find()->where(['orders_payment_id' => $item['orders_payment_id']])->one();
                    if ($is_create == true) {
                        if (!$orders_payment instanceof \common\models\Orders_Payment) {
                            $orders_payment = \common\models\Orders_Payment::find()->where(['orders_payment_order_id' => $orders->orders_id, 'orders_payment_module' => $item['orders_payment_module'], 'orders_payment_is_credit' => $item['orders_payment_is_credit'], 'orders_payment_transaction_id' => $item['orders_payment_transaction_id']])->one();
                            if (!$orders_payment instanceof \common\models\Orders_Payment) {
                                $orders_payment = new \common\models\Orders_Payment();
                                $orders_payment->load_default_values();
                            }
                        }
                        $orders_payment->orders_payment_order_id = $orders->orders_id;
                    }
                } else {
                    if (isset($item['orders_payment_id'])) {
                        unset($item['orders_payment_id']);
                    }
                    $orders_payment = new \common\models\Orders_Payment();
                    $orders_payment->load_default_values();
                    $orders_payment->orders_payment_order_id = $orders->orders_id;
                }
                $orders_payment->set_attributes($item, false);
                $orders_payment->save();
            }
        }
        if (is_array($this->orders_products)) {
            foreach ($this->orders_products as $key_op => $item) {
                if (isset($item['orders_products_allocate'])) {
                    $orders_products_allocate_data = $item['orders_products_allocate'];
                    unset($item['orders_products_allocate']);
                } else {
                    $orders_products_allocate_data = false;
                }
                if (isset($item['orders_products_attributes'])) {
                    $orders_products_attributes_data = $item['orders_products_attributes'];
                    unset($item['orders_products_attributes']);
                } else {
                    $orders_products_attributes_data = false;
                }
                if (isset($item['orders_products_download'])) {
                    $orders_products_download_data = $item['orders_products_download'];
                    unset($item['orders_products_download']);
                } else {
                    $orders_products_download_data = false;
                }
                if (isset($item['orders_products_status_history'])) {
                    $orders_products_status_history_data = $item['orders_products_status_history'];
                    unset($item['orders_products_status_history']);
                } else {
                    $orders_products_status_history_data = false;
                }
                if ($orders_id > 0) {
                    $orders_products = \common\models\Orders_Products::find()->where(['orders_products_id' => $item['orders_products_id']])->one();
                    if ($is_create == true) {
                        if (!$orders_products instanceof \common\models\Orders_Products) {
                            $orders_products = new \common\models\Orders_Products();
                            $orders_products->load_default_values();
                        }
                        $orders_products->orders_id = $orders->orders_id;
                    }
                } else {
                    if (isset($item['orders_products_id'])) {
                        unset($item['orders_products_id']);
                    }
                    $orders_products = new \common\models\Orders_Products();
                    $orders_products->load_default_values();
                    $orders_products->orders_id = $orders->orders_id;
                }
                $orders_products->set_attributes($item, false);
                if ($orders_products->save()) {
                    //$orders_products->orders_products_id
                    $this->orders_products[$key_op]['orders_products_id'] = $orders_products->orders_products_id;
                }
                if (is_array($orders_products_attributes_data)) {
                    foreach ($orders_products_attributes_data as $data) {
                        if ($orders_id > 0) {
                            $orders_products_attributes = \common\models\Orders_Products_Attributes::find()->where(['orders_products_attributes_id' => $data['orders_products_attributes_id']])->one();
                            if ($is_create == true) {
                                if (!$orders_products_attributes instanceof \common\models\Orders_Products_Attributes) {
                                    $orders_products_attributes = \common\models\Orders_Products_Attributes::find()->where(['orders_id' => $orders->orders_id, 'orders_products_id' => $orders_products->orders_products_id, 'products_options_id' => $data['products_options_id'], 'products_options_values_id' => $data['products_options_values_id']])->one();
                                    if (!$orders_products_attributes instanceof \common\models\Orders_Products_Attributes) {
                                        $orders_products_attributes = new \common\models\Orders_Products_Attributes();
                                        $orders_products_attributes->load_default_values();
                                    }
                                }
                                $orders_products_attributes->orders_id = $orders->orders_id;
                                $orders_products_attributes->orders_products_id = $orders_products->orders_products_id;
                            }
                        } else {
                            if (isset($data['orders_products_attributes_id'])) {
                                unset($data['orders_products_attributes_id']);
                            }
                            $orders_products_attributes = new \common\models\Orders_Products_Attributes();
                            $orders_products_attributes->load_default_values();
                            $orders_products_attributes->orders_id = $orders->orders_id;
                            $orders_products_attributes->orders_products_id = $orders_products->orders_products_id;
                        }
                        $orders_products_attributes->set_attributes($data, false);
                        $orders_products_attributes->save();
                    }
                }
            }
        }
        $this->tracking_number_record_array = is_array($this->tracking_number_record_array) ? $this->tracking_number_record_array : [];
        foreach ($this->tracking_number_record_array as $key_tn => &$tracking_number_record) {
            $tracking_number_id = (int) (isset($tracking_number_record['tracking_numbers_id']) ? $tracking_number_record['tracking_numbers_id'] : 0);
            unset($tracking_number_record['orders_id']);
            unset($tracking_number_record['tracking_numbers_id']);
            $tracking_number_record['tracking_number'] = trim(isset($tracking_number_record['tracking_number']) ? $tracking_number_record['tracking_number'] : '');
            $tracking_number_record['tracking_carriers_id'] = (int) (isset($tracking_number_record['tracking_carriers_id']) ? $tracking_number_record['tracking_carriers_id'] : 0);
            if ($tracking_number_record['tracking_number'] == '' or $tracking_number_record['tracking_carriers_id'] <= 0) {
                continue;
            }
            $tn_record = \common\models\Tracking_Numbers::find()->where(['orders_id' => $orders->orders_id, 'tracking_carriers_id' => $tracking_number_record['tracking_carriers_id'], 'tracking_number' => $tracking_number_record['tracking_number']])->one();
            if ($tn_record instanceof \common\models\Tracking_Numbers) {
                $tracking_number_record = $tn_record->get_attributes();
                continue;
            }
            if ($tracking_number_id > 0) {
                $tn_record = \common\models\Tracking_Numbers::find_one(['tracking_numbers_id' => $tracking_number_id]);
            }
            if (!$tn_record instanceof \common\models\Tracking_Numbers) {
                $tn_record = new \common\models\Tracking_Numbers();
                $tn_record->load_default_values();
                if ($tracking_number_id > 0) {
                    $tn_record->tracking_numbers_id = $tracking_number_id;
                }
            }
            $tn_record->set_attributes($tracking_number_record, false);
            $tn_record->orders_id = $orders->orders_id;
            if ($tn_record->save(false)) {
                $tracking_number_record = $tn_record->get_attributes();
            } else {
                unset($this->tracking_number_record_array[$key_tn]);
            }
        }
        unset($tracking_number_record);
        unset($tracking_number_id);
        unset($tn_record);
        unset($key_tn);
        $this->orders_id = $orders->orders_id;
        return $this->orders_id;
    }
}