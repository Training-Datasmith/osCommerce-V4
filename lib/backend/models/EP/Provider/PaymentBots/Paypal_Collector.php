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
namespace backend\models\EP\Provider\Payment_Bots;

use backend\models\EP\Messages;
use backend\models\EP\Provider\Datasource_Interface;
use Yii;
use yii\db\Query;
class Paypal_Collector implements Datasource_Interface
{
    protected $total_count = 0;
    protected $row_count = 0;
    protected $job_task;
    protected $config = [];
    protected $check_order_ids = [];
    protected $start_job_server_gmt_time = '';
    protected $use_modify_time_check = true;
    protected $is_error_occurred_during_check = false;
    private $transaction_manager;
    private $order_manager;
    private $begining;
    public function __construct($config)
    {
        $this->config = $config;
    }
    public function allow_run_in_popup()
    {
        return false;
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
        $migrate = new \common\classes\Migration();
        if (!$migrate->is_table_exists('paypal_cron')) {
            $migrate->create_table('paypal_cron', ['id' => \yii\db\Schema::TYPE_PK, 'platform_id' => $migrate->integer(), 'payment_class' => $migrate->string(64), 'start_date' => $migrate->date_time(), 'end_date' => $migrate->date_time()], '');
        }
        $this->order_manager = new \common\services\Order_Manager(Yii::$app->get('storage'));
        if (is_array($this->config['payments'])) {
            foreach ($this->config['payments'] as $platform => $modules) {
                if (is_array($modules)) {
                    foreach ($modules as $module) {
                        $this->job_task = $this->get_job($module, $platform, $message);
                        if ($this->job_task) {
                            break 2;
                        }
                    }
                }
            }
        }
        if (!$this->job_task) {
            $message->info('No data');
            $message->progress(100);
            return false;
        }
        $this->total_count = 1;
        $this->after_process_filename = tempnam($this->config['workingDirectory'], 'after_process');
        $this->after_process_file = fopen($this->after_process_filename, 'w+');
    }
    protected function get_installed_moduels($platform)
    {
        static $cache = [];
        if (!isset($cache[$platform])) {
            $installed = (new Query())->select('configuration_value')->from('platforms_configuration')->where(['configuration_key' => 'MODULE_PAYMENT_INSTALLED', 'platform_id' => $platform])->one();
            $cache[$platform] = [];
            if ($installed) {
                $modules = explode(';', $installed['configuration_value']);
                foreach ($modules as $module) {
                    $class = pathinfo($module, PATHINFO_FILENAME);
                    if (!in_array($class, $cache[$platform]) && class_exists("\\common\\modules\\orderPayment\\{$class}")) {
                        $cache[$platform][] = $class;
                    }
                }
            }
        }
        return $cache[$platform];
    }
    protected function get_job($class, $platform, $message)
    {
        $job = false;
        $modules = $this->get_installed_moduels($platform);
        if (in_array($class, $modules)) {
            $last_job = (new Query())->select(['*'])->from('paypal_cron')->where(['payment_class' => $class, 'platform_id' => $platform])->order_by('id desc')->one();
            if (!$last_job) {
                $message->info("{$class} for " . \common\classes\platform::name($platform) . ' started');
                $year = $this->config['payments'][$platform]['begining'];
                if (!$year) {
                    $year = date('Y', strtotime('-1 year'));
                }
                $this->begining = date('Y-m-d H:i:s', mktime(0, 0, 0, 1, 1, $year));
                //configure it
                $start = new \DateTime($this->begining);
            } else {
                $start = new \DateTime($last_job['end_date']);
            }
            $end = clone $start;
            $end = $end->add(new \DateInterval('P31D'));
            $now = new \DateTime();
            $diff = $now->diff($start);
            if (!$diff->y && !$diff->m && $diff->d <= 31) {
                $end = $now->sub(new \DateInterval('P1D'));
            }
            $now = new \DateTime();
            $diff = $now->diff($end);
            if (!$diff->y && !$diff->m && !$diff->d) {
                return false;
            }
            //check if it is loaded till today
            $config = new \common\classes\platform_config($platform);
            $config->constant_up();
            $builder = new \common\classes\modules\Module_Builder($this->order_manager);
            $payment = $builder(['class' => "\\common\\modules\\orderPayment\\{$class}"]);
            if (is_object($payment) && $payment instanceof \common\classes\modules\Transaction_Search_Interface) {
                $job = ['payment' => $payment, 'start_date' => $start->format(DATE_ATOM), 'end_date' => $end->format(DATE_ATOM), 'platform' => $platform, 'fields' => ['start_date' => $this->config['payments'][$platform][$payment->code]['start_date'], 'end_date' => $this->config['payments'][$platform][$payment->code]['end_date']]];
            }
        }
        return $job;
    }
    public function process_row(Messages $message)
    {
        set_time_limit(0);
        if (!$this->job_task) {
            return false;
        }
        try {
            $this->process_task($this->job_task, $message);
        } catch (\Exception $ex) {
            throw new \Exception('Processing task error ' . $ex->get_message() . ' Trace:' . $ex->get_trace_as_string());
        }
        return true;
    }
    public function post_process(Messages $message)
    {
        return;
    }
    public function process_task(&$task, Messages $message, $use_after_process = false)
    {
        if ($task) {
            $payment = $task['payment'];
            $params = [];
            if (is_array($task['fields'])) {
                if (isset($task['fields']['start_date'])) {
                    $params[$task['fields']['start_date']] = $task['start_date'];
                }
                if (isset($task['fields']['end_date'])) {
                    $params[$task['fields']['end_date']] = $task['end_date'];
                }
            }
            $params['no_modify'] = true;
            try {
                $def_pl_id = \common\classes\platform::default_id();
                $transactions = $payment->search($params);
                if ($transactions) {
                    foreach ($transactions as $transaction) {
                        $info = $transaction['transaction_info'];
                        $rows = \yii\helpers\Array_Helper::index(\common\models\Paypalipn_Txn::find_all(['txn_id' => $info['transaction_id']]), 'platform_id');
                        $row = null;
                        if (isset($rows[$task['platform']])) {
                            $row = $rows[$task['platform']];
                        }
                        if (!$row && isset($rows[0])) {
                            $row = $rows[0];
                            if ($row->item_number) {
                                $row->is_assigned = 1;
                                $order = \common\models\Orders::find()->select(['platform_id'])->where(['orders_id' => (int) $row->item_number])->one();
                            }
                            $row->platform_id = $order ? $order->platform_id : $def_pl_id;
                        }
                        //'platform_id' => $task['platform']
                        if ($row) {
                            if (!$row->payment_class) {
                                $row->payment_class = $payment->code;
                            }
                        } else {
                            $row = new \common\models\Paypalipn_Txn();
                            $row->load_default_values();
                            $row->set_attributes(['txn_id' => $info['transaction_id'], 'receiver_email' => '', 'item_name' => isset($info['transaction_subject']) ? $info['transaction_subject'] : '', 'payment_status' => isset($info['transaction_status']) ? $payment->describe_status($info['transaction_status']) : '', 'mc_gross' => (float) $info['transaction_amount']['value'], 'mc_fee' => abs((float) $info['fee_amount']['value']), 'mc_currency' => (string) $info['transaction_amount']['currency_code'], 'txn_type' => 'cron', 'payment_class' => $payment->code, 'is_assigned' => 0, 'platform_id' => $task['platform']], false);
                            if (isset($info['transaction_initiation_date'])) {
                                $row->set_attribute('payment_date', date('Y-m-d H:i:s', strtotime($info['transaction_initiation_date'])));
                            }
                            if (isset($info['sales_tax_amount'])) {
                                $row->set_attribute('tax', $info['sales_tax_amount']['value']);
                            }
                        }
                        $payer_info = $transaction['payer_info'];
                        if ($payer_info) {
                            $row->set_attribute('payer_email', strval($payer_info['email_address']));
                            $row->set_attribute('payer_id', strval($payer_info['account_id']));
                            $status = $payer_info['payer_status'] == 'Y' ? 'verified' : 'unverified';
                            $row->set_attribute('payer_status', $status);
                            if ($payer_info['payer_name']) {
                                if (isset($payer_info['payer_name']['given_name'])) {
                                    $row->set_attribute('first_name', strval($payer_info['payer_name']['given_name']));
                                    $row->set_attribute('last_name', strval($payer_info['payer_name']['surname']));
                                } else {
                                    $ex = explode(' ', $payer_info['payer_name']['alternate_full_name']);
                                    $fname = $ex[0];
                                    unset($ex[0]);
                                    $lname = implode(' ', $ex);
                                    $row->set_attribute('first_name', strval($fname));
                                    $row->set_attribute('last_name', strval($lname));
                                }
                            }
                            $status = $payer_info['address_status'] == 'Y' ? 'confirmed' : '';
                            $row->set_attribute('address_status', $status);
                        }
                        $shipping = $transaction['shipping_info'];
                        if ($shipping && $shipping['address']) {
                            $country = \common\helpers\Country::get_country_info_by_iso($shipping['address']['country_code']);
                            $row->set_attribute('address_street', strval($shipping['address']['line1']));
                            $row->set_attribute('address_city', strval($shipping['address']['city']));
                            $row->set_attribute('address_state', strval($shipping['address']['line2']));
                            $row->set_attribute('address_zip', strval($shipping['address']['postal_code']));
                            $row->set_attribute('address_country', strval($country ? $country['title'] : ''));
                        }
                        $row->save(false);
                    }
                }
            } catch (\Exception $ex) {
            }
            $params = [];
            $values = ['platform_id' => $task['platform'], 'payment_class' => $task['payment']->code, 'start_date' => date('Y-m-d H:i:s', strtotime($task['start_date'])), 'end_date' => date('Y-m-d H:i:s', strtotime($task['end_date']))];
            $sql = (new \yii\db\Query_Builder(\Yii::$app->db))->insert('paypal_cron', $values, $params);
            Yii::$app->db->create_command($sql, $params)->execute();
            $message->info("{$task['payment']->code} for " . \common\classes\platform::name($task['platform']) . ' to ' . date('d m Y', strtotime($task['end_date'])) . ' completed');
        }
        $task = false;
        return false;
    }
}