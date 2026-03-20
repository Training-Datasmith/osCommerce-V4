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
namespace backend\models\EP\Provider;

use backend\models\EP\Array_Transform;
use backend\models\EP\Exception;
use backend\models\EP\Messages;
use backend\models\EP\Tools;
use common\api\models\AR\Customer;
use common\api\models\AR\Customer\Address;
class Customers extends Provider_Abstract implements Import_Interface, Export_Interface
{
    protected $data = [];
    protected $entry_counter = 0;
    protected $export_query;
    public function init()
    {
        parent::init();
        $this->init_fields();
    }
    protected function init_fields()
    {
        $this->fields = [];
        $this->fields = [];
        $this->fields[] = ['name' => 'customer.customers_email_address', 'value' => 'Customers Email Address', 'is_key_part' => true, 'is_key' => true];
        $dummy = new Customer();
        $dummy->set_model_flag('credit_amount_delta', true);
        $dummy->set_model_flag('customers_bonus_points_delta', true);
        $attr = $dummy->get_possible_keys();
        $column_cover = ['customers_firstname' => ['value' => 'Customers Firstname', 'is_key_part' => true], 'customers_lastname' => ['value' => 'Customers Lastname', 'is_key_part' => true], 'customers_email_address' => false, 'customers_id' => false, 'dob_flag' => ['value' => 'GDPR Dob Flag'], 'departments_id' => false, 'customers_default_address_id' => false, 'admin_id' => false, 'credit_amount' => ['value' => 'Credit Amount (read only)'], 'customers_bonus_points' => ['value' => 'Customers Bonus Points (read only)'], 'customers_company' => ['value' => 'Account Company'], 'customers_company_vat' => false, 'sap_servers_id' => false, '_api_time_modified' => false, 'opc_temp_account' => ['value' => 'Is Guest?'], 'customers_dob' => ['get' => 'getDate', 'set' => 'setDate'], 'platform_id' => ['value' => 'Platform', 'get' => 'getPlatformName', 'set' => 'setPlatformName'], 'groups_id' => ['value' => 'Group', 'get' => 'getGroupName', 'set' => 'setGroupName'], 'customers_currency_id' => ['value' => 'Currency', 'get' => 'getCurrencyCode', 'set' => 'setCurrencyCode']];
        foreach ($attr as $key) {
            $column_describe = ['name' => 'customer.' . $key, 'value' => ucwords(preg_replace('/[ \._]/', ' ', $key))];
            if (isset($column_cover[$key])) {
                if ($column_cover[$key] == false) {
                    continue;
                }
                if (is_array($column_cover[$key])) {
                    $column_describe = array_merge($column_describe, $column_cover[$key]);
                }
            }
            $this->fields[] = $column_describe;
        }
        $dummy = new Address();
        $attr = $dummy->get_possible_keys();
        $column_cover = [
            'address_book_id' => false,
            '_api_time_modified' => false,
            'is_default' => false,
            'entry_company' => ['value' => 'Company Name'],
            'entry_company_vat' => ['value' => 'Company Vat'],
            'entry_country_id' => ['value' => 'Address Country', 'get' => 'getCountryISO', 'set' => 'setCountry'],
            'entry_zone_id' => false,
            //['value' => 'Address State','get'=>'getCountryState', 'set'=>'setCountryState'],
            'entry_gender' => false,
            'entry_telephone' => ['value' => 'Phone'],
            'entry_firstname' => ['value' => 'Address Firstname'],
            'entry_lastname' => ['value' => 'Address Lastname'],
            'entry_street_address' => ['value' => 'Street address'],
            'entry_suburb' => ['value' => 'Suburb'],
            'entry_postcode' => ['value' => 'Postcode'],
            'entry_city' => ['value' => 'City'],
            'entry_state' => ['value' => 'State'],
        ];
        $pattern = [];
        foreach ($attr as $key) {
            $column_describe = ['name' => 'address.' . $key, 'value' => ucwords(preg_replace('/[ \._]/', ' ', str_replace('entry_', 'address_', $key)))];
            if (isset($column_cover[$key])) {
                if ($column_cover[$key] == false) {
                    continue;
                }
                if (is_array($column_cover[$key])) {
                    $column_describe = array_merge($column_describe, $column_cover[$key]);
                }
            }
            //$this->fields[] = $columnDescribe;
            $pattern[] = $column_describe;
        }
        for ($i = 0; $i <= Customer::max_addresses(); $i++) {
            // Up to 5 addressbook etries, + 1 new, + default is first
            foreach ($pattern as $v) {
                $v['name'] = str_replace('address.', 'address.' . $i . '.', $v['name']);
                if ($i == 0) {
                    $v['value'] = 'Default ' . $v['value'];
                } else {
                    $v['value'] .= ' ' . $i;
                }
                $this->fields[] = $v;
            }
        }
        //        $dummy = new Customer\Info();
        //        $ss = $dummy->getPossibleKeys();
        $this->fields[] = ['name' => 'info.customers_info_date_account_created', 'value' => 'Date Created', 'set' => 'setDatetime'];
    }
    public function prepare_export($use_columns, $filter)
    {
        $this->build_sources($use_columns);
        $main_source = $this->main_source;
        $filter_sql = '';
        if (is_array($filter)) {
        }
        $main_sql = 'SELECT customers_id ' . 'FROM ' . TABLE_CUSTOMERS . ' ' . "WHERE 1 {$filter_sql} " . " AND customers_email_address != 'removed' " . 'ORDER BY customers_id';
        $this->export_query = tep_db_query($main_sql);
    }
    public function export_row()
    {
        $this->data = tep_db_fetch_array($this->export_query);
        if (!is_array($this->data)) {
            return $this->data;
        }
        $data_sources = $this->data_sources;
        $export_columns = $this->export_columns;
        static $export_group_columns = false;
        if (!is_array($export_group_columns)) {
            $export_keys = array_keys($export_columns);
            $export_group_columns = Array_Transform::convert_flat_to_multi_dimensional(array_combine($export_keys, $export_keys));
        }
        $customer_model = Customer::find_one($this->data['customers_id']);
        $customer_data = $customer_model->export_array([]);
        if (!empty($export_group_columns['customer'])) {
            foreach ($customer_data as $__key => $__val) {
                if (is_array($__val)) {
                    continue;
                }
                $this->data['customer.' . $__key] = $__val;
            }
        }
        if (is_array($customer_data['addresses'])) {
            foreach ($customer_data['addresses'] as $idx => $address) {
                //if ( $customerData['customers_default_address_id']!=$address['address_book_id'] ) continue;
                foreach ($address as $__key => $__val) {
                    $this->data['address.' . $idx . '.' . $__key] = $__val;
                }
            }
        }
        if (is_array($customer_data['info'])) {
            foreach ($customer_data['info'] as $address) {
                foreach ($address as $__key => $__val) {
                    $this->data['info.' . $__key] = $__val;
                }
            }
        }
        foreach ($export_columns as $db_key => $export) {
            if (isset($export['get']) && method_exists($this, $export['get'])) {
                $this->data[$db_key] = call_user_func_array([$this, $export['get']], [$export, $this->data['customers_id']]);
            }
            $this->data[$db_key] = isset($this->data[$db_key]) ? $this->data[$db_key] : '';
        }
        return $this->data;
    }
    public function import_row($data, Messages $message)
    {
        $this->build_sources(array_keys($data));
        $export_columns = $this->export_columns;
        $main_source = $this->main_source;
        $data_sources = $this->data_sources;
        $file_primary_column = $this->file_primary_column;
        $this->file_primary_columns;
        $extra_cols = array_intersect_key($data, $this->file_primary_columns);
        $this->data = $data;
        $is_updated = false;
        if (!array_key_exists($file_primary_column, $data) && count($extra_cols) != count($this->file_primary_columns)) {
            throw new Exception('Primary key not found in file');
        }
        $lookup_email = $this->data[$file_primary_column];
        if (empty($lookup_email)) {
            $customer_models = Customer::find()->where(['customers_firstname' => $extra_cols['customer.customers_firstname'], 'customers_lastname' => $extra_cols['customer.customers_lastname']])->all();
        } else {
            $customer_models = Customer::find()->where(['customers_email_address' => $lookup_email])->all();
        }
        if (count($customer_models) > 1) {
            $message->info('Duplicate email address. Skipped');
            return false;
        }
        if (count($customer_models) == 1) {
            $customer_model = reset($customer_models);
        } else {
            $customer_model = new Customer();
            $customer_model->load_default_values();
        }
        $this->data['customers_id'] = $customer_model->customers_id;
        // {{
        $customer_model->set_model_flag('credit_amount_delta', true);
        $customer_model->set_model_flag('customers_bonus_points_delta', true);
        // }}
        $update_data_array = [];
        foreach ($main_source['columns'] as $file_column => $db_column) {
            if (!array_key_exists($file_column, $data)) {
                continue;
            }
            if (isset($export_columns[$db_column]['set']) && method_exists($this, $export_columns[$db_column]['set'])) {
                call_user_func_array([$this, $export_columns[$db_column]['set']], [$export_columns[$db_column], $this->data['customers_id'], $message]);
            }
            if (array_key_exists($file_column, $this->data)) {
                $update_data_array[$db_column] = $this->data[$file_column];
            }
        }
        $import_data = Array_Transform::convert_flat_to_multi_dimensional($this->data);
        $customer_import_data = $import_data['customer'];
        /*
                if ( $customerModel->customers_default_address_id ) {
           $importData['address']['address_book_id'] = $customerModel->customers_default_address_id;
                }
        */
        if (isset($import_data['address']) && is_array($import_data['address']) && count($import_data['address']) > 0) {
            foreach ($import_data['address'] as $idx => $address) {
                // need remove all empty addresses
                $all_empty = true;
                foreach ($address as $a_val) {
                    if (is_numeric($a_val)) {
                        if (!empty($a_val)) {
                            $all_empty = false;
                            break;
                        }
                    } elseif (!empty($a_val)) {
                        $all_empty = false;
                        break;
                    }
                }
                if ($all_empty) {
                    unset($import_data['address'][$idx]);
                }
            }
            $import_data['address'] = array_values($import_data['address']);
            $import_data['address'][0]['is_default'] = 1;
            $customer_import_data['addresses'] = $import_data['address'];
            $customer_model->indexed_collection_append_mode('addresses', true);
        }
        if (isset($import_data['info']) && is_array($import_data['info'])) {
            $customer_import_data['info'] = [$import_data['info']];
        }
        $customer_model->import_array($customer_import_data);
        if ($customer_model->save(false)) {
            $this->entry_counter++;
        }
        return true;
    }
    public function post_process(Messages $message)
    {
        $message->info('Processed ' . $this->entry_counter . ' customers');
        $message->info('Done');
    }
    protected function get_platform_name()
    {
        return Tools::get_instance()->get_platform_name($this->data['customer.platform_id']);
    }
    protected function set_platform_name($field_data, $customer_id, Messages $message)
    {
        $file_value = $this->data[$field_data['name']];
        $db_value = Tools::get_instance()->get_platform_id($file_value);
        if (empty($db_value)) {
            $message->info('Platform "' . $file_value . '" not found');
            $db_value = \common\classes\platform::default_id();
        }
        $this->data[$field_data['name']] = (int) $db_value;
    }
    protected function get_group_name()
    {
        return $this->data['customer.groups_id'] == 0 ? '' : Tools::get_instance()->get_customer_group_name($this->data['customer.groups_id']);
    }
    protected function set_group_name($field_data, $customer_id, Messages $message)
    {
        $file_value = $this->data[$field_data['name']];
        if ($file_value === '') {
            $db_value = 0;
        } else {
            $db_value = Tools::get_instance()->get_customer_group_id($file_value);
            if (empty($db_value)) {
                $message->info('Customer group "' . $file_value . '" not found');
            }
        }
        $this->data[$field_data['name']] = (int) $db_value;
    }
    protected function get_currency_code()
    {
        return \common\helpers\Currencies::get_currency_code($this->data['customer.customers_currency_id']);
    }
    protected function set_currency_code($field_data, $customer_id, Messages $message)
    {
        $file_value = $this->data[$field_data['name']];
        $db_value = \common\helpers\Currencies::get_currency_id($file_value);
        if ($db_value === false) {
            $message->info('Currency code "' . $file_value . '" not valid');
            $db_value = \common\helpers\Currencies::system_currency_code();
        }
        $this->data[$field_data['name']] = (int) $db_value;
    }
    protected function get_country_iso($field_data)
    {
        $file_value = $this->data[$field_data['name']] ?? null;
        if (empty($file_value)) {
            return '';
        }
        $country_info = Tools::get_instance()->get_country_info($file_value);
        return is_array($country_info) ? $country_info['countries_iso_code_2'] : '';
    }
    protected function set_country($field_data, $customer_id, Messages $message)
    {
        $file_value = $this->data[$field_data['name']];
        $db_value = Tools::get_instance()->get_country_id($file_value);
        if (empty($db_value)) {
            $message->info('Country "' . $file_value . '" not found');
        }
        $this->data[$field_data['name']] = (int) $db_value;
    }
    protected function set_datetime($field_data, $customer_id, Messages $message)
    {
        $file_value = $this->data[$field_data['name']];
        $db_value = Tools::get_instance()->parse_date($file_value);
        if (empty($db_value) || $db_value < 1971) {
            $db_value = date('Y-m-d H:i:s');
        }
        $this->data[$field_data['name']] = $db_value;
        return $this->data[$field_data['name']];
    }
    protected function set_date($field_data, $customer_id, Messages $message)
    {
        $file_value = $this->data[$field_data['name']];
        $db_value = Tools::get_instance()->parse_date($file_value);
        $this->data[$field_data['name']] = $db_value;
        return $this->data[$field_data['name']];
    }
    protected function get_date($field_data, $customer_id)
    {
        $this->data[$field_data['name']] = substr($this->data[$field_data['name']], 0, 10);
        return $this->data[$field_data['name']];
    }
}