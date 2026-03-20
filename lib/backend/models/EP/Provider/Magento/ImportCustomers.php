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
use backend\models\EP\Provider\Magento\maps\Customer_Map;
class Import_Customers implements Datasource_Interface
{
    protected $total_count = 0;
    protected $row_count = 0;
    protected $customers_list;
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
        $this->init_db();
    }
    public function allow_run_in_popup()
    {
        return true;
    }
    public function init_db()
    {
        tep_db_query('CREATE TABLE IF NOT EXISTS ep_holbi_soap_link_customers(
   ep_directory_id INT(11) NOT NULL,
   remote_customers_id INT(11) NOT NULL,
   local_customers_id INT(11) NOT NULL,
   KEY(ep_directory_id, remote_customers_id),
   UNIQUE KEY(local_customers_id)
);');
        tep_db_query('CREATE TABLE IF NOT EXISTS ep_holbi_soap_link_addresses(
   ep_directory_id INT(11) NOT NULL,
   remote_address_id INT(11) NOT NULL,
   local_address_id INT(11) NOT NULL,
   KEY(ep_directory_id, remote_address_id),
   UNIQUE KEY(local_address_id)
);');
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
        $mg = new Soap_Client($this->config['client']);
        $this->client = $mg->get_client();
        $this->session = $mg->login_client();
        $this->config['assign_platform'] = \common\classes\platform::default_id();
        $this->get_customers_list();
        $this->total_count = count($this->customers_list);
        $this->after_process_filename = tempnam($this->config['workingDirectory'], 'after_process');
        $this->after_process_file = fopen($this->after_process_filename, 'w+');
    }
    public function get_customers_list()
    {
        try {
            $this->customers_list = $this->client->call($this->session, 'customer.list');
            if (is_array($this->customers_list)) {
                if (isset($this->config['trunkate_customers'])) {
                    \common\helpers\Customer::trunk_customers();
                    tep_db_query("delete from ep_holbi_soap_link_customers where ep_directory_id = '" . (int) $this->config['directoryId'] . "'");
                    tep_db_query("delete from ep_holbi_soap_link_addresses where ep_directory_id = '" . (int) $this->config['directoryId'] . "'");
                }
            }
            //echo '<pre>';print_r($this->customers_list);die;
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customers list error');
        }
    }
    public function get_customer_info($id)
    {
        try {
            $result = $this->client->call($this->session, 'customer.info', $id);
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customer ' . $id . ' info error');
        }
        return $result;
    }
    public function get_customer_address_info($id)
    {
        try {
            $result = $this->client->call($this->session, 'customer_address.info', $id);
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customer address info error');
        }
        return $result;
    }
    public function get_customer_addresses($id)
    {
        try {
            $result = $this->client->call($this->session, 'customer_address.list', $id);
            if ($result) {
                if (is_array($result)) {
                    foreach ($result as $key => $address) {
                        $result[$key] = array_merge($result[$key], $this->get_customer_address_info($address['customer_address_id']));
                    }
                }
            }
        } catch (\Exception $ex) {
            throw new \Exception('Fetch customer ' . $id . ' info error');
        }
        return $result;
    }
    public function process_row(Messages $message)
    {
        $remote_customer = current($this->customers_list);
        if (!$remote_customer) {
            return false;
        }
        try {
            $this->process_remote_customer($remote_customer['customer_id'], true);
        } catch (\Exception $ex) {
            throw new \Exception('Processing customer error (' . $remote_customer['email'] . ')');
        }
        $this->row_count++;
        next($this->customers_list);
        return true;
    }
    public function post_process(Messages $message)
    {
        return;
    }
    protected function process_remote_customer($remote_customer_id)
    {
        static $timing = ['soap' => 0, 'local' => 0];
        $t1 = microtime(true);
        $local_id = $this->lookup_local_id($remote_customer_id);
        if (!$local_id) {
            $remote_customer = $this->get_customer_info($remote_customer_id);
            $t2 = microtime(true);
            $timing['soap'] += $t2 - $t1;
            if ($remote_customer) {
                $remote_customer['addresses'] = $this->get_customer_addresses($remote_customer_id);
                $import_array = $this->map($remote_customer);
                if (!is_array($import_array) || !count($import_array)) {
                    return false;
                }
                $import_array['platform_id'] = $this->config['assign_platform'];
                $import_array['customers_status'] = 1;
                $local_id = false;
                try {
                    $local_customer = \common\api\models\AR\Customer::find()->where(['customers_email_address' => $remote_customer['email']])->order_by('opc_temp_account')->one();
                } catch (\Exception $ex) {
                    echo '<pre>';
                    print_r($ex);
                }
                if (!$local_customer) {
                    $local_customer = new \common\api\models\AR\Customer();
                }
                try {
                    $local_customer->import_array($import_array);
                } catch (\Exception $ex) {
                    echo '<pre>';
                    print_r($ex);
                }
                if ($local_customer->validate()) {
                    try {
                        $local_customer->save(false);
                    } catch (\Exception $ex) {
                        echo $ex->get_message();
                    }
                    $local_id = $local_customer->customers_id;
                    $this->link_remote_with_local_id($remote_customer_id, $local_id);
                    $this->link_remote_address_with_local($local_customer->init_collection_by_lookup_key_addresses(null));
                    if ($local_id) {
                        if (isset($remote_customer['addresses'][0]['telephone']) && !empty($remote_customer['addresses'][0]['telephone'])) {
                            $local_customer->set_attribute('customers_telephone', $remote_customer['addresses'][0]['telephone']);
                            $local_customer->update();
                        }
                    }
                    //echo '<pre>!!! '; var_dump($localId); echo '</pre>';
                }
                unset($local_customer);
            }
        }
        $t3 = microtime(true);
        $timing['local'] += $t3 - $t2;
        //echo '<pre>';  var_dump($timing);    echo '</pre>';
    }
    protected function link_remote_address_with_local($addresses)
    {
        if (is_array($addresses)) {
            foreach ($addresses as $address) {
                if ($address instanceof \common\api\models\AR\Customer\Address && $address->save_lookup) {
                    $local_ab_id = $address->get_attribute('address_book_id');
                    tep_db_query('INSERT INTO ep_holbi_soap_link_addresses(ep_directory_id, remote_address_id, local_address_id ) ' . ' VALUES ' . " ('" . (int) $this->config['directoryId'] . "', '" . $address->save_lookup . "','" . $local_ab_id . "') " . "ON DUPLICATE KEY UPDATE ep_directory_id='" . (int) $this->config['directoryId'] . "', remote_address_id='" . $address->save_lookup . "'");
                }
            }
        }
    }
    protected function lookup_local_id($remote_id)
    {
        $get_local_id_r = tep_db_query('SELECT local_customers_id ' . 'FROM ep_holbi_soap_link_customers ' . "WHERE ep_directory_id='" . (int) $this->config['directoryId'] . "' " . " AND remote_customers_id='" . $remote_id . "'");
        if (tep_db_num_rows($get_local_id_r) > 0) {
            $_local_id = tep_db_fetch_array($get_local_id_r);
            tep_db_free_result($get_local_id_r);
            return $_local_id['local_customers_id'];
        }
        return false;
    }
    protected function link_remote_with_local_id($remote_id, $local_id)
    {
        tep_db_query('INSERT INTO ep_holbi_soap_link_customers(ep_directory_id, remote_customers_id, local_customers_id ) ' . ' VALUES ' . " ('" . (int) $this->config['directoryId'] . "', '" . $remote_id . "','" . $local_id . "') " . "ON DUPLICATE KEY UPDATE ep_directory_id='" . (int) $this->config['directoryId'] . "', remote_customers_id='" . $remote_id . "'");
        return true;
    }
    protected function map($response_object)
    {
        $customer = json_decode(json_encode($response_object), true);
        $t1 = microtime(true);
        $simple = [];
        $simple = Customer_Map::aplly_mapping($customer);
        $simple['customer_id'] = $customer['customer_id'];
        //echo '<pre>';print_r($simple);
        return $simple;
    }
}