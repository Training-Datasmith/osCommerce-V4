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
namespace common\classes;

use yii\base\Invalid_Param_Exception;
use yii\db\Expression;
class Order extends extended\Order_Abstract implements extended\Transactions_Interface
{
    public function query($order_id)
    {
        parent::query($order_id);
        $this->tracking_number_load();
    }
    protected function tracking_number_load()
    {
        $tabled_numbers = [];
        $tracking_table = Order_Tracking_Number::get_tracking_from_table($this->order_id);
        foreach ($tracking_table as $tracking_model) {
            $tabled_numbers[$tracking_model->number] = $tracking_model;
        }
        $normalize_order_record = false;
        if (!is_array($this->info['tracking_number'])) {
            $this->info['tracking_number'] = [];
        }
        foreach ($this->info['tracking_number'] as $_idx => $tracking) {
            if (!is_object($tracking)) {
                $parsed_tracking = Order_Tracking_Number::instance_from_string($tracking, $this->order_id);
                if (isset($tabled_numbers[$parsed_tracking->number])) {
                    unset($this->info['tracking_number'][$_idx]);
                    continue;
                } else {
                    // missing in tracking table but exist in order - old schema
                    $parsed_tracking->save(false);
                    $parsed_tracking->refresh();
                    $normalize_order_record = true;
                }
                $this->info['tracking_number'][$_idx] = $parsed_tracking;
            }
        }
        foreach ($tabled_numbers as $tabled_number) {
            $this->info['tracking_number'][] = $tabled_number;
        }
        if ($normalize_order_record) {
            $order_model = \common\models\Orders::find_one($this->order_id);
            if ($order_model) {
                $order_model->set_attribute('tracking_number', implode(';', array_map('strval', $this->info['tracking_number'])));
                if ($order_model->get_dirty_attributes(['tracking_number'])) {
                    $order_model->set_attribute('last_modified', new Expression('NOW()'));
                    $order_model->save(false);
                }
            }
        }
    }
    public function remove_tracking_number($tracking_number_id)
    {
        foreach ($this->info['tracking_number'] as $_idx => $tracking_number) {
            if ($tracking_number->tracking_numbers_id == $tracking_number_id) {
                unset($this->info['tracking_number'][$_idx]);
            }
        }
        $this->save_tracking_numbers();
        \common\models\Tracking_Numbers_Export::delete_all(['tracking_numbers_id' => $tracking_number_id]);
    }
    public function add_tracking_number($tracking_number)
    {
        if (!is_object($tracking_number) || !$tracking_number instanceof \common\models\Tracking_Numbers) {
            $tracking_number = Order_Tracking_Number::instance_from_string(strval($tracking_number), $this->order_id);
        }
        if (empty($tracking_number->number)) {
            throw new Invalid_Param_Exception('Empty tracking number');
        }
        foreach ($this->info['tracking_number'] as $current_tracking_number) {
            if (strtolower($tracking_number->number) == strtolower($current_tracking_number->number)) {
                throw new Invalid_Param_Exception('Tracking number "' . $tracking_number->number . '" already added to order');
            }
        }
        $this->info['tracking_number'][] = $tracking_number;
    }
    public function save_tracking_numbers($send_email = true, $add_status_history = true)
    {
        $email_tracking_number = [];
        $processed_ids = [];
        foreach ($this->info['tracking_number'] as $tracking_number) {
            /**
             * @var $trackingNumber OrderTrackingNumber
             */
            $send_tracking_email = $tracking_number->get_dirty_attributes(['tracking_number']);
            if ($tracking_number->is_new_record || $tracking_number->get_dirty_attributes()) {
                $tracking_number->save();
                $tracking_number->refresh();
            }
            if ($tracking_number->is_products_modified()) {
                $tracking_number->save_products();
            }
            $processed_ids[] = $tracking_number->tracking_numbers_id;
            if ($send_tracking_email) {
                $email_tracking_number[] = $tracking_number;
            }
        }
        foreach (Order_Tracking_Number::find()->where(['orders_id' => $this->order_id])->and_filter_where(['NOT IN', 'tracking_numbers_id', $processed_ids])->all() as $remove_tracking) {
            $remove_tracking->delete();
        }
        $order_model = \common\models\Orders::find_one($this->order_id);
        if ($order_model) {
            $order_model->set_attribute('tracking_number', implode(';', array_map('strval', $this->info['tracking_number'])));
            if ($order_model->get_dirty_attributes(['tracking_number'])) {
                $order_model->set_attribute('last_modified', new Expression('NOW()'));
                $order_model->save(false);
            }
        }
        if (count($email_tracking_number) > 0 && ($send_email || $add_status_history)) {
            $_keep_platform_id = \Yii::$app->get('platform')->config()->get_id();
            //$order = new \common\classes\Order($this->order_id);
            $order = (new \common\services\Order_Manager(\Yii::$app->get('storage')))->get_order_instance_with_id('\common\classes\Order', $this->order_id);
            $platform_config = \Yii::$app->get('platform')->config($order->info['platform_id']);
            $notify_comments = '';
            $customer_notified = 0;
            $email_params_tracking = ['TRACKING_NUMBER' => '', 'TRACKING_NUMBER_URL' => ''];
            $TEXT_TRACKING_NUMBER = \common\helpers\Translation::get_translation_value('TEXT_TRACKING_NUMBER', 'admin/orders', $order->info['language_id']);
            foreach ($email_tracking_number as $tracking_number) {
                $tracking_data = \common\helpers\Order::parse_tracking_number($tracking_number);
                $str_tracking_number = (empty($tracking_data['carrier']) ? '' : "{$tracking_data['carrier']} ") . $tracking_data['number'];
                $notify_comments .= $TEXT_TRACKING_NUMBER . ': ' . $str_tracking_number . "\n";
                $email_params_tracking['TRACKING_NUMBER'] .= (empty($email_params_tracking['TRACKING_NUMBER']) ? '' : ', ') . $str_tracking_number;
                if (function_exists('tep_catalog_href_link')) {
                    $email_params_tracking['TRACKING_NUMBER_URL'] .= (empty($email_params_tracking['TRACKING_NUMBER_URL']) ? '' : ', ') . '<a href="' . $tracking_data['url'] . '" target="_blank">' . '<img border="0" alt="' . $tracking_data['number'] . '" src="' . tep_catalog_href_link('account/order-qrcode', 'oID=' . (int) $this->order_id . '&cID=' . (int) $this->customer['customer_id'] . '&tracking=1&tracking_number=' . urlencode($tracking_data['number']), 'SSL') . '">' . '</a>';
                } else {
                    $email_params_tracking['TRACKING_NUMBER_URL'] .= (empty($email_params_tracking['TRACKING_NUMBER_URL']) ? '' : ', ') . '<a href="' . $tracking_data['url'] . '" target="_blank">' . '<img border="0" alt="' . $tracking_data['number'] . '" src="' . tep_href_link('account/order-qrcode', 'oID=' . (int) $this->order_id . '&cID=' . (int) $this->customer['customer_id'] . '&tracking=1&tracking_number=' . urlencode($tracking_data['number']), 'SSL') . '">' . '</a>';
                }
            }
            $notify_comments = rtrim($notify_comments);
            if ($send_email) {
                $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                $email_params = \common\helpers\Mail::email_params_from_order($order);
                $email_params['TRACKING_NUMBER'] = $email_params_tracking['TRACKING_NUMBER'];
                $email_params['TRACKING_NUMBER_URL'] = $email_params_tracking['TRACKING_NUMBER_URL'];
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Add Tracking Number', $email_params, $order->info['language_id'], $order->info['platform_id']);
                \common\helpers\Mail::send($order->customer['name'], $order->customer['email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                $customer_notified = 1;
            }
            if ($add_status_history) {
                tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, ['orders_id' => $order->order_id, 'orders_status_id' => $order->info['order_status'], 'date_added' => 'now()', 'customer_notified' => $customer_notified, 'comments' => $notify_comments, 'admin_id' => isset($_SESSION['login_id']) ? (int) $_SESSION['login_id'] : 0]);
            }
            \Yii::$app->get('platform')->config($_keep_platform_id);
        }
    }
    public static function get_ar_model($new = false)
    {
        if ($new) {
            return parent::get_ar_model_new(new \common\models\Orders());
        } else {
            return \common\models\Orders::find();
        }
    }
    public function get_products_ar_model()
    {
        return \common\models\Orders_Products::find();
    }
    public function get_status_history_ar_model()
    {
        return \common\models\Orders_Status_History::find()->order_by('date_added, orders_status_history_id');
    }
    public function get_history_ar_model()
    {
        return \common\models\Orders_History::find();
    }
    public function is_hold_on()
    {
        $details = $this->get_details();
        if (!empty($details['hold_on_date']) && $details['hold_on_date'] > 2000 && $details['hold_on_date'] > date('Y-m-d H:i:s')) {
            return true;
        }
        return false;
    }
    public function remove_order($restock = false)
    {
        if ($this->order_id) {
            if ($restock) {
                \common\helpers\Order::restock($this->order_id);
            }
            \common\models\Orders_Products_Allocate::delete_all(['orders_id' => (int) $this->order_id]);
            \common\models\Tracking_Numbers::delete_all(['orders_id' => (int) $this->order_id]);
            \common\models\Tracking_Numbers_To_Orders_Products::delete_all(['orders_id' => (int) $this->order_id]);
            parent::remove_order();
        }
    }
    public function get_parent()
    {
        if ($this->order_id) {
            $parent = \common\models\Orders_Parent::find_one($this->order_id);
            if ($parent) {
                if (class_exists($parent->owner_class)) {
                    $class = new \ReflectionClass($parent->owner_class);
                    $class->model = $parent->owner_class::get_ar_model()->where(['child_id' => $this->order_id])->one();
                    return $class;
                }
            }
        }
        return false;
    }
    public function has_transactions()
    {
        if ($this->order_id) {
            return \common\models\Orders_Transactions::find()->where(['orders_id' => $this->order_id])->exists();
        }
        return 0;
    }
    public function maintain_splittering()
    {
        return true;
    }
    protected $splinters = [];
    /**
     * set spinters id in orders_splinters history
     * @params $splinters - rows for creating splinter instance
     **/
    public function set_splinters(array $splinters)
    {
        $this->splinters = $splinters;
    }
    public function get_splinters()
    {
        return $this->splinters;
    }
    public function save_order($order_id = 0)
    {
        parent::save_order($order_id);
        \common\helpers\System::ga_detection($this->manager);
        return $this->order_id;
    }
    public function notify_customer($products_ordered, $email_params = [], $email_template = '')
    {
        $notify_status = parent::notify_customer($products_ordered, $email_params, $email_template);
        if ($notify_status) {
            $this->notify_gift_cards();
        }
        return $notify_status;
    }
    public function notify_gift_cards()
    {
        if (is_array($this->products)) {
            foreach ($this->products as $product) {
                if ($product['model'] == \common\helpers\Gifts::get_virtual_gift_card_model()) {
                    if (isset($product['attributes'][0]['value_id']) && $product['attributes'][0]['value_id']) {
                        \common\helpers\Gifts::activate($product['attributes'][0]['value_id'], $this);
                    }
                }
            }
        }
    }
}