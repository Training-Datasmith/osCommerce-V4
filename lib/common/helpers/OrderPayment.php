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

class Order_Payment
{
    public const OPYS_PENDING = 0;
    public const OPYS_PROCESSING = 10;
    public const OPYS_SUCCESSFUL = 20;
    public const OPYS_REFUSED = 30;
    public const OPYS_REFUNDED = 40;
    public const OPYS_CANCELLED = 50;
    public const OPYS_DISCOUNTED = 100;
    /**
     * add new payment record from order AND transaction info
     * @global int $login_id admin id
     * @param \common\classes\Order $orderInstance
     * @param float|bool $orderPaymentAmount order total amount
     * @param int $ordersPaymentStatus payment status code
     * @param array $transactionInformationArray ['id' => , 'status' 'commentary' 'date', 'parent_id', 'fulljson']
     * @return \common\models\OrdersPayment|boolean
     */
    public static function create_debit_from_order($order_instance = null, $order_payment_amount = false, $orders_payment_status = false, $transaction_information_array = [], $deferred = 0)
    {
        $return = false;
        if ($order_instance instanceof \common\classes\Order) {
            if ($order_payment_amount === false) {
                $order_payment_amount = $order_instance->info['total_inc_tax'];
            }
            $order_payment_amount = (float) $order_payment_amount;
            if ($order_payment_amount == 0) {
                return $return;
            }
            $orders_payment_status = (int) ($orders_payment_status === false ? self::OPYS_PENDING : $orders_payment_status);
            $orders_payment_status_list = self::get_status_list();
            $orders_payment_status = !isset($orders_payment_status_list[$orders_payment_status]) ? self::OPYS_PENDING : $orders_payment_status;
            unset($orders_payment_status_list);
            $transaction_information_array = is_array($transaction_information_array) ? $transaction_information_array : [];
            if (empty($order_instance->order_id) && !empty($order_instance->parent_id)) {
                $order_id = (int) $order_instance->parent_id;
            } else {
                $order_id = (int) $order_instance->order_id;
            }
            $payment_class = !empty($transaction_information_array['payment_class']) ? $transaction_information_array['payment_class'] : $order_instance->info['payment_class'];
            $payment_method = !empty($transaction_information_array['payment_method']) ? $transaction_information_array['payment_method'] : $order_instance->info['payment_method'];
            $order_payment_record = new \common\models\Orders_Payment();
            $order_payment_record->orders_payment_id_parent = !empty($transaction_information_array['parent_id']) ? $transaction_information_array['parent_id'] : 0;
            $order_payment_record->orders_payment_order_id = $order_id;
            $order_payment_record->orders_payment_module = trim($payment_class);
            $order_payment_record->orders_payment_module_name = trim($payment_method);
            $order_payment_record->orders_payment_is_credit = 0;
            $order_payment_record->deferred = (int) $deferred;
            $order_payment_record->orders_payment_status = $orders_payment_status;
            $order_payment_record->orders_payment_amount = $order_payment_amount;
            $order_payment_record->orders_payment_currency = trim($order_instance->info['currency']);
            $order_payment_record->orders_payment_currency_rate = (float) $order_instance->info['currency_value'];
            $order_payment_record->orders_payment_snapshot = json_encode(self::get_order_payment_snapshot($order_instance));
            $order_payment_record->orders_payment_transaction_id = trim(isset($transaction_information_array['id']) ? $transaction_information_array['id'] : '');
            $order_payment_record->orders_payment_transaction_status = trim(isset($transaction_information_array['status']) ? $transaction_information_array['status'] : '');
            $order_payment_record->orders_payment_transaction_commentary = trim(isset($transaction_information_array['commentary']) ? $transaction_information_array['commentary'] : '');
            $order_payment_record->orders_payment_transaction_date = trim(isset($transaction_information_array['date']) ? $transaction_information_array['date'] : '0000-00-00 00:00:00');
            if (!empty($transaction_information_array['fulljson'])) {
                $order_payment_record->orders_payment_transaction_full = trim($transaction_information_array['fulljson']);
            }
            global $login_id;
            $order_payment_record->orders_payment_admin_create = (int) $login_id;
            unset($login_id);
            $order_payment_record->orders_payment_date_create = date('Y-m-d H:i:s');
            try {
                if ($order_payment_record->save()) {
                    $return = $order_payment_record;
                }
            } catch (\Exception $exc) {
                \Yii::warning($exc->get_message());
            }
            unset($order_payment_record);
        } else {
            \Yii::warning('createDebitFromOrder - not order: ' . get_class($order_instance));
        }
        unset($transaction_information_array);
        unset($orders_payment_status);
        unset($order_payment_amount);
        unset($order_instance);
        return $return;
    }
    /**
     *
     * @global int $login_id
     * @param string $orderPaymentModule
     * @param string $orderPaymentTransactionId
     * @return \common\models\OrdersPayment|boolean
     */
    public static function search_record($order_payment_module = '', $order_payment_transaction_id = '')
    {
        $order_payment_module = trim($order_payment_module);
        $order_payment_transaction_id = trim($order_payment_transaction_id);
        if ($order_payment_module == '' or $order_payment_transaction_id == '') {
            return false;
        }
        $order_payment_record = \common\models\Orders_Payment::find()->where(['orders_payment_module' => $order_payment_module])->and_where(['orders_payment_transaction_id' => $order_payment_transaction_id])->order_by(['orders_payment_date_create' => SORT_DESC, 'orders_payment_id' => SORT_DESC])->one();
        if (!$order_payment_record instanceof \common\models\Orders_Payment) {
            $order_payment_record = new \common\models\Orders_Payment();
            $order_payment_record->orders_payment_module = $order_payment_module;
            $order_payment_record->orders_payment_transaction_id = $order_payment_transaction_id;
            $order_payment_record->orders_payment_status = self::OPYS_PENDING;
            global $login_id;
            $order_payment_record->orders_payment_admin_create = (int) $login_id;
            unset($login_id);
        }
        return $order_payment_record;
    }
    public static function get_array_by_order_id($order_id = 0, $as_array = true)
    {
        $return = [];
        foreach (\common\models\Orders_Payment::find()->where(['orders_payment_order_id' => (int) $order_id])->order_by(['orders_payment_date_create' => SORT_ASC])->as_array($as_array)->all() as $order_payment_record) {
            $return[] = $order_payment_record;
        }
        unset($order_payment_record);
        return $return;
    }
    public static function get_array_parent_by_order_id($order_id = 0, $as_array = true)
    {
        $return = [];
        foreach (\common\models\Orders_Payment::find()->where(['orders_payment_order_id' => (int) $order_id])->and_where(['orders_payment_id_parent' => 0])->as_array($as_array)->all() as $order_payment_record) {
            $return[] = $order_payment_record;
        }
        unset($order_payment_record);
        return $return;
    }
    public static function get_array_child_by_parent_id($order_payment_id_parent = 0, $as_array = true)
    {
        $return = [];
        foreach (\common\models\Orders_Payment::find()->where(['orders_payment_id_parent' => (int) $order_payment_id_parent])->as_array($as_array)->all() as $order_payment_record) {
            $return[] = $order_payment_record;
        }
        unset($order_payment_record);
        return $return;
    }
    /** @deprecated hueta
     * calculates only transactions w/o parent_id (s kakogo X??)
     * [ PP order -> ] authorisation -> capture -> refund - ZHOPA
     */
    public static function get_array_status_by_order_id_total($order_id = 0, $order_total = 0)
    {
        $return = false;
        $order_id = (int) $order_id;
        $order_total = (float) $order_total;
        if ($order_total < 0) {
            $order_total = 0;
        }
        $debit = 0;
        $credit = 0;
        $discount = 0;
        foreach (self::get_array_parent_by_order_id($order_id) as $order_payment_parent_record) {
            $order_payment_parent_record['orders_payment_amount'] = (float) ((float) $order_payment_parent_record['orders_payment_amount'] <= 0 ? 0 : $order_payment_parent_record['orders_payment_amount']);
            $order_payment_parent_record['orders_payment_currency_rate'] = (float) ((float) $order_payment_parent_record['orders_payment_currency_rate'] <= 0 ? 1 : $order_payment_parent_record['orders_payment_currency_rate']);
            if (in_array((int) $order_payment_parent_record['orders_payment_status'], [self::OPYS_SUCCESSFUL, self::OPYS_REFUNDED, self::OPYS_DISCOUNTED])) {
                if ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_SUCCESSFUL) {
                    $debit += $order_payment_parent_record['orders_payment_amount'] / $order_payment_parent_record['orders_payment_currency_rate'];
                } elseif ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_REFUNDED) {
                    $credit += $order_payment_parent_record['orders_payment_amount'] / $order_payment_parent_record['orders_payment_currency_rate'];
                } elseif ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_DISCOUNTED) {
                    $discount += $order_payment_parent_record['orders_payment_amount'] / $order_payment_parent_record['orders_payment_currency_rate'];
                }
                foreach (self::get_array_child_by_parent_id($order_payment_parent_record['orders_payment_id']) as $order_payment_child_record) {
                    if (in_array((int) $order_payment_child_record['orders_payment_status'], [self::OPYS_REFUNDED, self::OPYS_DISCOUNTED])) {
                        $order_payment_child_record['orders_payment_amount'] = (float) ((float) $order_payment_child_record['orders_payment_amount'] <= 0 ? 0 : $order_payment_child_record['orders_payment_amount']);
                        $order_payment_child_record['orders_payment_currency_rate'] = (float) ((float) $order_payment_child_record['orders_payment_currency_rate'] <= 0 ? 1 : $order_payment_child_record['orders_payment_currency_rate']);
                        if ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_SUCCESSFUL) {
                            if ((int) $order_payment_child_record['orders_payment_status'] == self::OPYS_DISCOUNTED) {
                                $discount += $order_payment_child_record['orders_payment_amount'] / $order_payment_child_record['orders_payment_currency_rate'];
                            } elseif ((int) $order_payment_child_record['orders_payment_status'] == self::OPYS_REFUNDED) {
                                $credit += $order_payment_child_record['orders_payment_amount'] / $order_payment_child_record['orders_payment_currency_rate'];
                            }
                        } elseif ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_REFUNDED) {
                            // ???
                        } elseif ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_DISCOUNTED) {
                            if ((int) $order_payment_child_record['orders_payment_status'] == self::OPYS_REFUNDED) {
                                $discount -= $order_payment_child_record['orders_payment_amount'] / $order_payment_child_record['orders_payment_currency_rate'];
                            }
                        }
                    }
                }
                unset($order_payment_child_record);
            } elseif (in_array((int) $order_payment_parent_record['orders_payment_status'], [])) {
            }
        }
        unset($order_payment_parent_record);
        $discount = self::to_amount($discount);
        $discount = $discount <= 0 ? 0 : $discount;
        $return = ['status' => 0, 'total' => $order_total, 'debit' => self::to_amount($debit), 'credit' => self::to_amount($credit), 'discount' => $discount, 'paid' => 0, 'due' => 0, 'over' => 0];
        $return['paid'] = $return['debit'] + $return['discount'] - $return['credit'];
        $return['due'] = $return['total'] - $return['paid'];
        if ($return['due'] > 0) {
            $return['status'] = 1;
        } elseif ($return['due'] < 0) {
            $return['status'] = -1;
            $return['over'] = abs($return['due']);
            $return['due'] = 0;
        }
        unset($credit);
        unset($debit);
        return $return;
    }
    /**
    * [draft] - calculate paid and refund total values in order payment table
    * @param int $orderId
    * @param float $orderTotal
    * @return array|false array:     [status] => 1 -  has due 0 eq -1 overpay
        [total] => 17.45 - order total (NOT calculated, passed in params)
        [debit] => 17.45 - successful payment transactions (OPYS_SUCCESSFUL)
        [credit] => 1.15  - refunded transactions (OPYS_REFUNDED)
        [discount] => 0 ??
        [paid] => 16.3,      [due] => 1.15   [over] => 0 calculated based on values above
    */
    public static function get_total_status_array($order_id = 0, $order_total = 0)
    {
        $return = false;
        $order_id = (int) $order_id;
        $order_total = (float) $order_total;
        if ($order_total < 0) {
            $order_total = 0;
        }
        $debit = 0;
        $credit = 0;
        $discount = 0;
        $q = \common\models\Orders_Payment::find()->where(['orders_payment_order_id' => (int) $order_id])->as_array();
        foreach ($q->all() as $order_payment_parent_record) {
            $order_payment_parent_record['orders_payment_amount'] = (float) ((float) $order_payment_parent_record['orders_payment_amount'] <= 0 ? 0 : $order_payment_parent_record['orders_payment_amount']);
            $order_payment_parent_record['orders_payment_currency_rate'] = (float) ((float) $order_payment_parent_record['orders_payment_currency_rate'] <= 0 ? 1 : $order_payment_parent_record['orders_payment_currency_rate']);
            if (in_array((int) $order_payment_parent_record['orders_payment_status'], [self::OPYS_SUCCESSFUL, self::OPYS_REFUNDED, self::OPYS_DISCOUNTED])) {
                if ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_SUCCESSFUL) {
                    $debit += $order_payment_parent_record['orders_payment_amount'] / $order_payment_parent_record['orders_payment_currency_rate'];
                } elseif ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_REFUNDED) {
                    $credit += $order_payment_parent_record['orders_payment_amount'] / $order_payment_parent_record['orders_payment_currency_rate'];
                } elseif ((int) $order_payment_parent_record['orders_payment_status'] == self::OPYS_DISCOUNTED) {
                    $discount += $order_payment_parent_record['orders_payment_amount'] / $order_payment_parent_record['orders_payment_currency_rate'];
                }
            } elseif (in_array((int) $order_payment_parent_record['orders_payment_status'], [])) {
            }
        }
        unset($order_payment_parent_record);
        $discount = self::to_amount($discount);
        $discount = $discount <= 0 ? 0 : $discount;
        $return = ['status' => 0, 'total' => $order_total, 'debit' => self::to_amount($debit), 'credit' => self::to_amount($credit), 'discount' => $discount, 'paid' => 0, 'due' => 0, 'over' => 0];
        $return['paid'] = $return['debit'] + $return['discount'] - $return['credit'];
        $return['due'] = $return['total'] - $return['paid'];
        if ($return['due'] > 0) {
            $return['status'] = 1;
        } elseif ($return['due'] < 0) {
            $return['status'] = -1;
            $return['over'] = abs($return['due']);
            $return['due'] = 0;
        }
        unset($credit);
        unset($debit);
        return $return;
    }
    public static function get_amount_available($order_payment_record = 0)
    {
        $return = 0;
        $order_payment_record = self::get_record($order_payment_record);
        if ($order_payment_record instanceof \common\models\Orders_Payment) {
            if ($order_payment_record->orders_payment_id_parent == 0) {
                $return = (float) $order_payment_record->orders_payment_amount;
                foreach (self::get_array_child_by_parent_id($order_payment_record->orders_payment_id) as $payment_child_record) {
                    if (in_array($payment_child_record['orders_payment_status'], [self::OPYS_REFUNDED, self::OPYS_DISCOUNTED])) {
                        $return -= (float) $payment_child_record['orders_payment_amount'];
                    }
                }
                unset($payment_child_record);
            } else {
                $return = self::get_amount_available($order_payment_record->orders_payment_id_parent);
            }
        }
        return $return;
    }
    private static function to_amount($amount = 0)
    {
        return round((float) $amount, 2);
    }
    public static function get_record($order_payment_id = 0)
    {
        return $order_payment_id instanceof \common\models\Orders_Payment ? $order_payment_id : \common\models\Orders_Payment::find_one(['orders_payment_id' => (int) $order_payment_id]);
    }
    public static function get_status_list($for_status = false, $is_credit = false)
    {
        $return = [self::OPYS_PENDING => TEXT_STATUS_OPYS_PENDING, self::OPYS_PROCESSING => TEXT_STATUS_OPYS_PROCESSING, self::OPYS_SUCCESSFUL => TEXT_STATUS_OPYS_SUCCESSFUL, self::OPYS_REFUSED => TEXT_STATUS_OPYS_REFUSED, self::OPYS_REFUNDED => TEXT_STATUS_OPYS_REFUNDED, self::OPYS_CANCELLED => TEXT_STATUS_OPYS_CANCELLED, self::OPYS_DISCOUNTED => TEXT_STATUS_OPYS_DISCOUNTED];
        $is_credit = (int) $is_credit > 0 ? true : false;
        if ($for_status !== false) {
            switch ($for_status) {
                case self::OPYS_PENDING:
                    unset($return[self::OPYS_REFUSED]);
                    unset($return[self::OPYS_REFUNDED]);
                    unset($return[self::OPYS_DISCOUNTED]);
                    break;
                case self::OPYS_PROCESSING:
                    unset($return[self::OPYS_REFUNDED]);
                    unset($return[self::OPYS_CANCELLED]);
                    unset($return[self::OPYS_DISCOUNTED]);
                    break;
                case self::OPYS_SUCCESSFUL:
                    //unset($return[self::OPYS_PENDING]);
                    //unset($return[self::OPYS_PROCESSING]);
                    //unset($return[self::OPYS_REFUSED]);
                    unset($return[self::OPYS_REFUNDED]);
                    unset($return[self::OPYS_CANCELLED]);
                    unset($return[self::OPYS_DISCOUNTED]);
                    break;
                case self::OPYS_REFUSED:
                    unset($return[self::OPYS_PROCESSING]);
                    unset($return[self::OPYS_SUCCESSFUL]);
                    unset($return[self::OPYS_REFUNDED]);
                    unset($return[self::OPYS_CANCELLED]);
                    unset($return[self::OPYS_DISCOUNTED]);
                    break;
                case self::OPYS_REFUNDED:
                    unset($return[self::OPYS_PENDING]);
                    unset($return[self::OPYS_PROCESSING]);
                    unset($return[self::OPYS_SUCCESSFUL]);
                    unset($return[self::OPYS_REFUSED]);
                    unset($return[self::OPYS_CANCELLED]);
                    break;
                case self::OPYS_CANCELLED:
                    unset($return[self::OPYS_PROCESSING]);
                    unset($return[self::OPYS_SUCCESSFUL]);
                    unset($return[self::OPYS_REFUSED]);
                    unset($return[self::OPYS_REFUNDED]);
                    unset($return[self::OPYS_DISCOUNTED]);
                    break;
                case self::OPYS_DISCOUNTED:
                    unset($return[self::OPYS_PENDING]);
                    unset($return[self::OPYS_PROCESSING]);
                    unset($return[self::OPYS_SUCCESSFUL]);
                    unset($return[self::OPYS_REFUSED]);
                    //unset($return[self::OPYS_REFUNDED]);
                    unset($return[self::OPYS_CANCELLED]);
                    break;
            }
        }
        return $return;
    }
    public static function get_order_payment_snapshot($order_instance = null)
    {
        $return = ['product' => [], 'total' => ['subtotal' => ['price_exc' => 0, 'price_inc' => 0], 'shipping' => ['module' => '', 'price_exc' => 0, 'price_inc' => 0], 'tax' => ['price_exc' => 0, 'price_inc' => 0], 'discount' => ['price_exc' => 0, 'price_inc' => 0], 'coupon' => ['id' => 0, 'type' => '', 'price_exc' => 0, 'price_inc' => 0], 'total' => ['price_exc' => 0, 'price_inc' => 0], 'paid' => ['price_exc' => 0, 'price_inc' => 0], 'due' => ['price_exc' => 0, 'price_inc' => 0], 'refund' => ['price_exc' => 0, 'price_inc' => 0]]];
        if ($order_instance instanceof \common\classes\Order) {
            foreach ($order_instance->products as $order_product) {
                $order_product_array = ['prid' => (int) $order_product['id'], 'uprid' => \common\helpers\Inventory::normalize_id_excl_virtual($order_product['template_uprid']), 'model' => trim($order_product['model']), 'tax_rate' => (float) $order_product['tax'], 'price_exc' => (float) $order_product['final_price'], 'price_inc' => (float) $order_product['final_price'], 'qty' => (int) $order_product['qty'], 'qty_cnld' => (int) ($order_product['qty_cnld'] ?? 0), 'qty_rcvd' => (int) ($order_product['qty_rcvd'] ?? 0), 'qty_dspd' => (int) ($order_product['qty_dspd'] ?? 0), 'qty_dlvd' => (int) ($order_product['qty_dlvd'] ?? 0)];
                $order_product_array['price_inc'] = round((float) $order_product_array['price_exc'] * (1 + $order_product_array['tax_rate'] / 100), 2);
                $return['product'][] = $order_product_array;
                unset($order_product_array);
            }
            unset($order_product);
            foreach ($order_instance->totals as $order_total) {
                $code = strtolower(isset($order_total['code']) ? substr($order_total['code'], 3) : '');
                switch ($code) {
                    case 'subtotal':
                    case 'shipping':
                    case 'tax':
                    case 'total':
                        $order_total_array = ['price_exc' => (float) $order_total['value_exc_vat'], 'price_inc' => (float) $order_total['value_inc_tax']];
                        if ($code == 'shipping') {
                            $order_total_array['module'] = trim($order_instance->info['shipping_class']);
                        } elseif ($code == 'coupon') {
                            $order_total_array['id'] = 0;
                            //???
                            $order_total_array['type'] = '';
                            //???
                        }
                        $return['total'][$code] = $order_total_array;
                        unset($order_total_array);
                        break;
                    case 'paid':
                    case 'due':
                    case 'refund':
                        $order_total_array = ['price_exc' => (float) $order_total['value_inc_tax'], 'price_inc' => (float) $order_total['value_inc_tax']];
                        $return['total'][$code] = $order_total_array;
                        unset($order_total_array);
                        break;
                }
            }
            unset($order_total);
        }
        return $return;
    }
    /**
     * update transaction details, order paid/due/refunded totals, <order status, and send notification>.
     * payment class getTransactionDetails and parseTransactionDetails are called
     * init payment class according appropriate platform details.
     * @param array|common\models\OrdersPayment $data
     * @param \common\classes\modules\TransactionalInterface $class
     * @param \common\services\OrderManager $orderManager
     * @param bool $updateStatusAndNotify
     * @return true|string true or error message
     */
    public static function update_transaction_details($data, &$class, &$order_manager, $update_status_and_notify = true)
    {
        $rs = true;
        if ($data instanceof \common\models\Orders_Payment) {
            $data = $data->attributes;
        }
        if (is_object($class) && $class instanceof \common\classes\modules\Transactional_Interface && method_exists($class, 'parseTransactionDetails')) {
            try {
                $details = $class->get_transaction_details($data['orders_payment_transaction_id']);
                /** @var \common\services\PaymentTransactionManager $tManager */
                $t_manager = $order_manager->get_transaction_manager($class);
                $response = $class->parse_transaction_details($details);
                /** @var \common\classes\Order $order */
                $order = $order_manager->get_order_instance_with_id('\common\classes\Order', $data['orders_payment_order_id']);
                $ret = $t_manager->update_payment_transaction($response['transaction_id'], array_merge($data, $response));
                if ($ret) {
                    //updated transaction - update totals, order status and notify customer if required
                    $updated = false;
                    if ($order) {
                        $updated = $order->update_paid_totals();
                    }
                    if ($updated) {
                        //update order status and notify customer if required
                        $status = '';
                        if (isset($updated['paid']) && $updated['details']['debit'] > 0) {
                            //if ($updated['details']['status']>0) {// has due
                            if (abs(round($updated['details']['total'], 2) - round($updated['details']['debit'], 2)) < 0.01) {
                                $status = $class->paid_order_status();
                            } else {
                                $status = $class->partly_paid_order_status();
                            }
                        } elseif (isset($updated['refund']) && ($updated['details']['credit'] > 0 || $updated['details']['due'] > 0)) {
                            $tmp = ($updated['details']['credit'] ?? 0) > 0 ? $updated['details']['credit'] : $updated['details']['due'];
                            if (abs(round($updated['details']['total'], 2) - round($tmp, 2)) < 0.01) {
                                $status = $class->refund_order_status();
                            } else {
                                $status = $class->partial_refund_order_status();
                            }
                        }
                        if ($update_status_and_notify && !empty($status) && $status != $order->info['order_status']) {
                            $order->update_status_and_notify($status);
                        }
                    }
                } elseif (is_null($ret)) {
                    \Yii::warning(' #### ' . print_r($response, 1), 'TLDEBUG');
                    $rs = $data['orders_payment_transaction_id'] . ' - ' . $class->code . ' - ' . TEXT_MESSAGE_ERROR_INCORRECT_TRANSACTION;
                }
            } catch (Exception $ex) {
                \Yii::warning(' #### ' . print_r($ex->get_message(), 1), 'TLDEBUG');
                $rs = $data['orders_payment_transaction_id'] . ' - ' . $class->code . ' - ' . $ex->get_message();
            }
        } else {
            //backward compatibility
            /** @var \common\services\PaymentTransactionManager $tManager */
            $t_manager = $order_manager->get_transaction_manager($class);
            $details = $class->get_transaction_details($data['orders_payment_transaction_id'], $t_manager);
        }
        return $rs;
    }
    /**
     *
     * @param int $id
     * @return int
     */
    public static function has_children($id)
    {
        $q = \common\models\Orders_Payment::find()->and_where(['orders_payment_id_parent' => (int) $id]);
        return $q->count();
    }
}