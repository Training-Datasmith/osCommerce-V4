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
namespace backend\design\orders\payments;

use yii\base\Widget;
class Amazone extends Widget
{
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if ($amazon_info = tep_db_fetch_array(tep_db_query("select * from amazon_payment_orders where orders_id ='" . (int) $this->order->order_id . "'"))) {
            $allow_close = in_array($amazon_info['amazon_status'], ['Open']);
            $allow_capture = in_array($amazon_info['amazon_auth_status'], ['Open']);
            $allow_refund = in_array($amazon_info['amazon_capture_status'], ['Completed']);
            $amazon_log = array_map('unserialize', explode("#\n\n#", $amazon_info['custom_data']));
            return $this->render('amazone', ['manager' => $this->manager, 'order' => $this->order, 'allowClose' => $allow_close, 'allowCapture' => $allow_capture, 'allowRefund' => $allow_refund, 'amazonLog' => $amazon_log, 'amazonInfo' => $amazon_info]);
        }
    }
}