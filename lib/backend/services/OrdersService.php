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
namespace backend\services;

use common\classes\currencies;
use common\classes\extended\Order_Abstract;
use common\classes\platform_config;
use common\models\Orders;
use common\models\repositories\Not_Found_Exception;
use common\models\repositories\Order_Repository;
use common\models\repositories\Orders_Status_History_Repository;
use common\services\Customers_Service;
class Orders_Service
{
    /** @var OrderRepository */
    private $order_repository;
    /** @var CustomersService */
    private $customers_service;
    /** @var OrdersTotalService */
    private $orders_total_service;
    /** @var OrdersStatusHistoryRepository */
    private $orders_status_history_repository;
    public function __construct(Order_Repository $order_repository, Customers_Service $customers_service, Orders_Total_Service $orders_total_service, Orders_Status_History_Repository $orders_status_history_repository)
    {
        $this->order_repository = $order_repository;
        $this->customers_service = $customers_service;
        $this->orders_total_service = $orders_total_service;
        $this->orders_status_history_repository = $orders_status_history_repository;
    }
    /**
     * @param int|array $orderId
     * @param bool $asArray
     * @return Orders|Orders[]
     */
    public function get_by_id($order_id, bool $as_array = false)
    {
        return $this->order_repository->get_by_id($order_id, $as_array);
    }
    public function get_totals_from_data_order(array $totals)
    {
        $result = [];
        for ($i = 0, $n = count($totals); $i < $n; $i++) {
            if (file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/' . $totals[$i]['class'] . '.php')) {
                include_once DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/' . $totals[$i]['class'] . '.php';
            }
            if (class_exists($totals[$i]['class'])) {
                $object = new $totals[$i]['class']();
                $result[$object->code] = $totals[$i];
            }
        }
        return $result;
    }
    public function send_request_pay(Order_Abstract $order, currencies $currencies)
    {
        if (!is_object($order) || !is_object($currencies)) {
            throw new Not_Found_Exception('Order data not found');
        }
        $totals = $this->get_totals_from_data_order($order->totals);
        $ot_paid_value = $totals['ot_paid']['value_inc_tax'] ?? 0;
        $ot_total_value = $totals['ot_total']['value_inc_tax'] ?? 0;
        $update_and_pay_amount = round($ot_total_value, 2) - round($ot_paid_value, 2);
        /**
         * @var platform_config $platform_config
         */
        $platform_config = \Yii::$app->get('platform')->config($order->info['platform_id']);
        $STORE_NAME = $platform_config->const_value('STORE_NAME');
        $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
        $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
        $customer = $this->customers_service->get_by_id((int) $order->customer['customer_id']);
        $token = $this->customers_service->set_login_token($customer);
        $email_params = [];
        $email_params['STORE_NAME'] = $STORE_NAME;
        $email_params['CUSTOMER_NAME'] = $order->customer['firstname'] . ' ' . $order->customer['lastname'];
        $email_params['ORDER_NUMBER'] = method_exists($order, 'getOrderNumber') ? $order->get_order_number() : $order->order_id;
        $email_params['REQUEST_MESSAGE'] = $currencies->format(abs($update_and_pay_amount));
        $email_params['REQUEST_URL'] = tep_catalog_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'action=payment_request&order_id=' . $order->order_id . '&email_address=' . $order->customer['email_address'] . '&token=' . $token, 'SSL', false);
        $email_params['CUSTOMER_FIRSTNAME'] = $order->customer['firstname'];
        $email_params['ORDER_DATE_LONG'] = strftime(DATE_FORMAT_LONG);
        $email_params['ORDER_DATE_LONG'] = strftime(DATE_FORMAT_LONG);
        if ($ext = \common\helpers\Acl::check_extension_allowed('DelayedDespatch', 'allowed')) {
            $email_params['ORDER_DATE_LONG'] .= $ext::mail_info($order->info['delivery_date']);
        }
        $email_params['PRODUCTS_ORDERED'] = $order->get_products_html_for_email();
        $email_params['ORDER_TOTALS'] = '';
        $order_total_output = [];
        foreach ($order->totals as $total) {
            if (class_exists($total['code'])) {
                if (\Yii::$container->get($total['code'])->visibility()) {
                    $platform_id = 0;
                    if ((!defined('PLATFORM_ID') || PLATFORM_ID == 0) && (int) $order->info['platform_id'] > 0) {
                        $platform_id = $order->info['platform_id'];
                    } elseif (defined('PLATFORM_ID') && PLATFORM_ID > 0) {
                        $platform_id = PLATFORM_ID;
                    }
                    if (\Yii::$container->get($total['code'])->visibility($platform_id, 'TEXT_EMAIL')) {
                        $order_total_output[] = \Yii::$container->get($total['code'])->display_text($platform_id, 'TEXT_EMAIL', $total);
                    }
                }
            }
        }
        $email_params['ORDER_TOTALS'] = \frontend\design\boxes\email\Order_Totals::widget(['params' => ['order_total_output' => $order_total_output, 'platform_id' => $order->info['platform_id']]]);
        $email_params['BILLING_ADDRESS'] = \common\helpers\Address::address_format($order->billing['format_id'], $order->billing, 0, '', '<br>');
        $email_params['DELIVERY_ADDRESS'] = '';
        if ($order->content_type !== 'virtual') {
            $email_params['DELIVERY_ADDRESS'] = \common\helpers\Address::address_format($order->delivery['format_id'], $order->delivery, 0, '', '<br>');
        }
        $email_params['PAYMENT_METHOD'] = $order->info['payment_method'];
        $email_params['SHIPPING_METHOD'] = $order->info['shipping_method'];
        list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Request for payment', $email_params, -1, $order->info['platform_id']);
        \common\helpers\Mail::send($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, [], '', '', ['add_br' => 'no', 'platform_id' => $order->info['platform_id']]);
        return ['messageType' => 'success', 'message' => SUCCESS_ORDER_UPDATED];
    }
    public function set_pay_on_order(Orders $order, $amount, currencies $currencies, bool $add_comment = false, int $admin_id = 0)
    {
        if (!is_numeric($amount)) {
            throw new Not_Found_Exception('Order data not found');
        }
        $totals = $this->orders_total_service->get_by_order_id($order->orders_id);
        $ot_total = $totals['ot_total'];
        $tax_rate = 1;
        if ($ot_total->value_inc_tax > 0) {
            $tax_rate = $ot_total->value_exc_vat / $ot_total->value_inc_tax;
        }
        $ot_paid = $totals['ot_paid'];
        $ot_paid_new_value = $ot_paid->value_inc_tax + $amount * $currencies->get_market_price_rate($order->currency, DEFAULT_CURRENCY);
        $ot_paid_new_text = $currencies->format($ot_paid_new_value, true, $order->currency, $order->currency_value);
        $ot_data = ['value_inc_tax' => $ot_paid_new_value, 'text_inc_tax' => $ot_paid_new_text, 'value' => $ot_paid_new_value, 'text' => $ot_paid_new_text, 'value_exc_vat' => $ot_paid_new_value * $tax_rate, 'text_exc_tax' => $currencies->format($ot_paid_new_value * $tax_rate, true, $order->currency, $order->currency_value)];
        $this->orders_total_service->update($ot_paid, $ot_data);
        $ot_due = $totals['ot_due'];
        $ot_due_new_value = $ot_due->value_inc_tax - $amount * $currencies->get_market_price_rate($order->currency, DEFAULT_CURRENCY);
        if ($ot_due_new_value < 0) {
            $ot_due_new_value = 0;
        }
        $ot_due_new_text = $currencies->format($ot_due_new_value, true, $order->currency, $order->currency_value);
        $ot_data = ['value_inc_tax' => $ot_due_new_value, 'text_inc_tax' => $ot_due_new_text, 'value' => $ot_due_new_value, 'text' => $ot_due_new_text, 'value_exc_vat' => $ot_due_new_value * $tax_rate, 'text_exc_tax' => $currencies->format($ot_due_new_value * $tax_rate, true, $order->currency, $order->currency_value)];
        $this->orders_total_service->update($ot_due, $ot_data);
        if ($add_comment) {
            $comments = ' ' . TEXT_PAID_AMOUNT . ' ' . $currencies->format($amount, true, $order->currency, $order->currency_value);
            $this->change_status($order, $order->orders_status, $comments, $admin_id);
        }
        return true;
    }
    /**
     * @param Orders $order
     * @param array $params
     * @param bool $validate
     * @param bool $safeOnly
     * @return array|bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function edit(Orders $order, array $params = [], bool $validate = false, bool $safe_only = false)
    {
        return $this->order_repository->edit($order, $params, $validate, $safe_only);
    }
    /**
     * @param Orders $order
     * @param int $orderStatusId
     * @param string $comments
     * @param int $customerNotified
     * @param int $adminId
     * @param string $smsComments
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function change_status(Orders $order, int $order_status_id, string $comments = '', int $customer_notified = 0, int $admin_id = 0, string $sms_comments = '')
    {
        $this->order_repository->change_status($order, $order_status_id);
        $this->add_history($order, $order_status_id, $comments, $customer_notified, $admin_id, $sms_comments);
    }
    public function add_history(Orders $order, int $order_status_id, string $comments = '', int $customer_notified = 0, int $admin_id = 0, string $sms_comments = '')
    {
        $order_status_history = $this->orders_status_history_repository->create($order->orders_id, $order_status_id, $comments, $customer_notified, $admin_id, $sms_comments);
        $this->orders_status_history_repository->save($order_status_history);
    }
}