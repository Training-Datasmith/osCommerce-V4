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
use common\api\models\AR\Group;
class Import_Groups implements Datasource_Interface
{
    protected $total_count = 0;
    protected $row_count = 0;
    protected $groups_list = [];
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
        //$key = "jkajsdhfajfg&^jsaji0123";
        $mg = new Soap_Client($this->config['client']);
        $this->client = $mg->get_client();
        $this->session = $mg->login_client();
        $this->config['assign_platform'] = \common\classes\platform::default_id();
        $this->get_group_list();
        $this->total_count = count($this->groups_list);
        $this->after_process_filename = tempnam($this->config['workingDirectory'], 'after_process');
        $this->after_process_file = fopen($this->after_process_filename, 'w+');
    }
    public function get_group_list()
    {
        try {
            $result = $this->client->call($this->session, 'customer_group.list');
            if (is_array($result) && count($result)) {
                (new Group())->delete_all();
            }
            $this->groups_list = $result;
        } catch (\Exception $ex) {
            throw new \Exception('Download remote stores info error');
        }
        return $result;
    }
    public function process_row(Messages $message)
    {
        $remote_group = current($this->groups_list);
        if (!$remote_group) {
            return false;
        }
        $this->process_remote_group($remote_group);
        $this->row_count++;
        next($this->groups_list);
        return true;
    }
    public function post_process(Messages $message)
    {
        return;
    }
    protected function process_remote_group($remote_group)
    {
        static $timing = ['soap' => 0, 'local' => 0];
        $t1 = microtime(true);
        $group = new Group();
        if ($group) {
            $t2 = microtime(true);
            $group->import_array($this->map($remote_group));
            if ($group->validate()) {
                $group->save();
            }
        }
        $t3 = microtime(true);
        $timing['local'] += $t3 - $t2;
    }
    public function map($data)
    {
        return ['groups_id' => $data['customer_group_id'], 'groups_name' => $data['customer_group_code']];
    }
}