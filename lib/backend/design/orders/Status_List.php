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

use yii\base\Widget;
class Status_List extends Widget
{
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $orders_statuses = [];
        $orders_statuses_options = [];
        $orders_statuses = [];
        $orders_status_array = [];
        $orders_statuses = \common\helpers\Order::get_status_list(false, true, $this->order->info['order_status']);
        foreach (\common\helpers\Order::get_statuses(true, $this->order->info['order_status']) as $orders_status) {
            if (is_array($orders_status->statuses)) {
                foreach ($orders_status->statuses as $status) {
                    if ($status->order_evaluation_state_id > 0) {
                        $orders_statuses_options[$status->orders_status_id]['evaluation_state_id'] = $status->order_evaluation_state_id;
                    }
                }
            }
        }
        return $this->render('status-list', ['manager' => $this->manager, 'order' => $this->order, 'ordersStatuses' => $orders_statuses, 'ordersStatusesOptions' => $orders_statuses_options]);
    }
}