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
namespace backend\design\editor;

use yii\base\Widget;
class Order_Statuses_List extends Widget
{
    public $manager;
    public $admin;
    public $hide = false;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $enquire = [];
        $orders_statuses = [];
        $detected_status = null;
        if ($this->manager->has_cart() && $this->manager->is_instance()) {
            $cart = $this->manager->get_cart();
            $type = $this->manager->get_instance_type();
            if ($cart->order_id) {
                $_admins = [];
                $enquire = $this->manager->get_order_instance()->get_status_history_ar_model()->join_with('group')->where(['orders_id' => $cart->order_id])->as_array()->all();
            }
            if ($type == 'order') {
                $totals = \yii\helpers\Array_Helper::index($this->manager->get_total_output(false), 'code');
                if ($totals['ot_due']) {
                    if (defined('ORDER_STATUS_PART_AMOUNT') && (int) ORDER_STATUS_PART_AMOUNT > 0 && defined('ORDER_STATUS_FULL_AMOUNT') && (int) ORDER_STATUS_FULL_AMOUNT > 0) {
                        $detected_status = floatval($totals['ot_due']['value_inc_tax']) > 0 ? ORDER_STATUS_PART_AMOUNT : ORDER_STATUS_FULL_AMOUNT;
                        if (!\common\helpers\Order::is_status_exist($detected_status)) {
                            $detected_status = null;
                        }
                    }
                }
            }
            if (is_null($detected_status)) {
                $current_status_id = $this->manager->get_order_instance()->get_ar_model()->select('orders_status')->where(['orders_id' => $cart->order_id])->scalar();
                $detected_status = $current_status_id ?? DEFAULT_ORDERS_STATUS_ID;
            }
            if ($type) {
                $orders_statuses = $this->get_statuses();
            }
            return $this->render('order-statuses-list', ['CommentsWithStatus' => true, 'enquire' => $enquire, 'orders_statuses' => $orders_statuses, 'hide' => $this->hide, 'status' => $detected_status, 'manager' => $this->manager]);
        }
    }
    public function get_statuses()
    {
        $type = \yii\helpers\Inflector::camelize($this->manager->get_instance_type());
        if (class_exists('\common\helpers\\' . $type)) {
            $type = '\common\helpers\\' . $type;
            return $type::get_status_list();
        }
        return [];
    }
}