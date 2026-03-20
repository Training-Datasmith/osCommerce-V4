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
namespace backend\models\EP\Provider\Google;

use backend\models\EP\Messages;
use backend\models\EP\Provider\Datasource_Interface;
use common\models\Ecommerce_Tracking;
use common\models\Orders;
class Sync_Ecommerce implements Datasource_Interface
{
    protected $total_count = 0;
    protected $row_count = 0;
    protected $orders_list;
    protected $config = [];
    //2do period, latencity
    protected $platform_id = false;
    protected $gess = false;
    protected $installed_modules = false;
    protected $order_statuses = false;
    public $job_id;
    public function __construct($config)
    {
        $this->config = $config;
        if (empty($this->config['delays']['latencity']) || (int) $this->config['delays']['latencity'] < 1) {
            $this->config['delays']['latencity'] = 2;
        }
        if (empty($this->config['delays']['outdated']) || (int) $this->config['delays']['outdated'] < 1) {
            $this->config['delays']['outdated'] = 3;
        }
        $configured_statuses_string = is_array($this->config['order']['export_statuses']) ? implode(',', $this->config['order']['export_statuses']) : $this->config['order']['export_statuses'];
        if (strpos($configured_statuses_string, '*') !== false) {
            $this->order_statuses = true;
        } else {
            $this->order_statuses = \common\helpers\Order::extract_statuses($configured_statuses_string);
        }
    }
    public function allow_run_in_popup()
    {
        return true;
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
        /// import lost for last day(s)
        $date_start = strtotime((int) $this->config['delays']['outdated'] . ' day ago');
        $gess = new \common\components\google\Google_Ecommerce_Ss();
        foreach (\common\classes\platform::get_list(false) as $platform) {
            $gess->set_platform_id($platform['id']);
            $orders = $gess->get_transactions_report([date('Y-m-d', $date_start)]);
            if (is_array($orders)) {
                $oids = Orders::find()->select('orders_id')->where(['orders_id' => array_map('intval', array_keys($orders)), 'platform_id' => $platform['id']])->as_array()->column();
                foreach ($orders as $orders_id => $order) {
                    if (in_array($orders_id, $oids)) {
                        $et = Ecommerce_Tracking::find_one(['orders_id' => $orders_id]);
                        if (!$et) {
                            $message->info('Imported Transaction: ' . $orders_id);
                            $et = new Ecommerce_Tracking();
                            $et->set_attributes(['orders_id' => (int) $orders_id, 'via' => 'report', 'date_added' => new \yii\db\Expression('now()'), 'verified' => new \yii\db\Expression('now()'), 'verified_amount' => (float) $order['ga:transactionRevenue']], false);
                            $et->save(false);
                        }
                    }
                }
            }
        }
        $orders_query_raw = (new \yii\db\Query())->from(['o' => TABLE_ORDERS])->select('o.orders_id, o.date_purchased, o.payment_method,o.department_id, o.admin_id, o.platform_id, o.api_client_order_id ')->left_join('ecommerce_tracking et', 'et.orders_id=o.orders_id')->add_select('services, message_type, via, date_added, extra_info, id, verified, verified_amount');
        $orders_query_raw->and_where(['is', 'et.orders_id', null]);
        $orders_query_raw->and_where(['<=', 'o.date_purchased', date(\common\helpers\Date::DATABASE_DATETIME_FORMAT, strtotime((int) $this->config['delays']['latencity'] . ' hour ago'))]);
        //not to fresh
        $orders_query_raw->and_where(['>', 'o.date_purchased', date(\common\helpers\Date::DATABASE_DATETIME_FORMAT, strtotime((int) $this->config['delays']['outdated'] . ' days ago'))]);
        //not to old
        $orders_query_raw->order_by('platform_id, orders_id');
        if (is_array($this->order_statuses)) {
            $orders_query_raw->and_where(['o.orders_status' => $this->order_statuses]);
        }
        //$message->info($orders_query_raw->createCommand()->rawSql);
        $this->orders_list = $orders_query_raw->all();
        //$message->info(print_r($this->orders_list, 1));
        $this->total_count = count($this->orders_list);
        //$message->info('count ' . $this->total_count);
        if ($this->total_count) {
            $this->provider = (new \common\components\Google_Tools())->get_modules_provider();
            $this->gess = new \common\components\google\Google_Ecommerce_Ss();
        }
    }
    public function process_row(Messages $message)
    {
        set_time_limit(0);
        $item = current($this->orders_list);
        if (!$item || !$this->gess || !$this->provider) {
            return false;
        }
        try {
            if ($item['platform_id'] != $this->platform_id) {
                $this->platform_id = $item['platform_id'];
                $this->gess->set_platform_id($this->platform_id);
                $this->installed_modules = $this->provider->get_installed_modules($this->platform_id);
            }
            if (isset($this->installed_modules['ecommerce'])) {
                $order = new \common\classes\Order($item['orders_id']);
                $this->installed_modules['ecommerce']->force_server_side($order);
                $message->info('Processed Transaction: ' . $item['orders_id']);
            }
        } catch (\Exception $ex) {
            throw new \Exception('Processing order error ' . $ex->get_message() . ' Trace:' . $ex->get_trace_as_string());
        }
        $this->row_count++;
        next($this->orders_list);
        return true;
    }
    public function post_process(Messages $message)
    {
        return;
    }
}