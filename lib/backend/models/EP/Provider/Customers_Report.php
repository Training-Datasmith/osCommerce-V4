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

use backend\models\EP;
class Customers_Report extends Provider_Abstract implements Export_Interface
{
    protected $data = [];
    protected $e_ptools;
    protected $entry_counter = 0;
    protected $export_query;
    public function init()
    {
        parent::init();
        $this->init_fields();
        $this->e_ptools = new EP\Tools();
    }
    public function init_fields()
    {
        $this->fields = [];
        $this->fields[] = ['name' => 'platform_id', 'value' => 'Frontend Name', 'get' => 'getPlatformName'];
        $this->fields[] = ['name' => 'opc_temp_account', 'value' => 'Is Guest?', 'get' => 'getIsGuest'];
        $this->fields[] = ['name' => 'customers_email_address', 'value' => 'Email'];
        $this->fields[] = ['name' => 'customers_firstname', 'value' => 'First Name'];
        $this->fields[] = ['name' => 'customers_lastname', 'value' => 'Last Name'];
        $this->fields[] = ['name' => 'customers_company', 'value' => 'Company'];
        $this->fields[] = ['name' => 'customers_telephone', 'value' => 'Telephone'];
        $this->fields[] = ['name' => 'customers_newsletter', 'value' => 'Newsletter status', 'get' => 'getNewsletterStatus'];
        $this->fields[] = ['name' => 'groups_id', 'value' => 'Group Name', 'get' => 'getGroupName'];
        $this->fields[] = ['name' => 'customers_status', 'value' => 'Status'];
        $this->fields[] = ['name' => 'entry_country_id', 'value' => 'Country', 'get' => 'getCountryName'];
        $this->fields[] = ['name' => 'customers_info_date_account_created', 'value' => 'Account Create Date', 'get' => 'getDate'];
    }
    public function get_platform_name($field_data)
    {
        $key = $field_data['name'];
        if ($this->data[$key]) {
            $this->data[$key] = $this->e_ptools->get_platform_name($this->data[$key]);
        }
        return $this->data[$key];
    }
    public function get_is_guest($field_data)
    {
        $key = $field_data['name'];
        $this->data[$key] = $this->data[$key] ? 'Y' : '';
        return $this->data[$key];
    }
    public function get_newsletter_status($field_data)
    {
        $key = $field_data['name'];
        $this->data[$key] = $this->data[$key] ? 'Subscribed' : '';
        return $this->data[$key];
    }
    public function get_group_name($field_data)
    {
        static $groups = [];
        $key = $field_data['name'];
        $group_id = $this->data[$key];
        $this->data[$key] = '';
        if ($group_id) {
            if (!isset($groups[$group_id])) {
                $groups[$group_id] = \common\helpers\Group::get_user_group_name($group_id);
            }
        }
        if (isset($groups[$group_id])) {
            $this->data[$key] = $groups[$group_id];
        }
        return $this->data[$key];
    }
    public function get_country_name($field_data)
    {
        $key = $field_data['name'];
        $country_id = $this->data[$key];
        $this->data[$key] = '';
        if ($country_id) {
            $country_info = $this->e_ptools->get_country_info($country_id);
            if (is_array($country_info) && isset($country_info['countries_name'])) {
                $this->data[$key] = $country_info['countries_name'];
            }
        }
        return $this->data[$key];
    }
    public function get_date($field_data)
    {
        $key = $field_data['name'];
        $date_value = $this->data[$key];
        $this->data[$key] = '';
        if ($date_value > 0 && substr($date_value, 0, 10) != '0000-00-00') {
            $this->data[$key] = substr($date_value, 0, 10);
        }
        return $this->data[$key];
    }
    public function prepare_export($use_columns, $filter)
    {
        $this->build_sources($use_columns);
        $main_source = $this->main_source;
        $filter_sql = '';
        if (is_array($filter)) {
            if (isset($filter['platform_id']) && $filter['platform_id'] > 0) {
                $filter_sql .= "AND c.platform_id = '" . (int) $filter['platform_id'] . "' ";
            }
        }
        $main_sql = 'SELECT c.*, ab.entry_country_id, ci.customers_info_date_account_created ' . 'FROM ' . TABLE_CUSTOMERS . ' c ' . ' LEFT JOIN ' . TABLE_ADDRESS_BOOK . ' ab ON ab.address_book_id=c.customers_default_address_id ' . ' LEFT JOIN ' . TABLE_CUSTOMERS_INFO . ' ci ON ci.customers_info_id=c.customers_id ' . "WHERE 1 {$filter_sql} " . 'ORDER BY c.customers_lastname';
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
        foreach ($export_columns as $db_key => $export) {
            if (isset($export['get']) && method_exists($this, $export['get'])) {
                $this->data[$db_key] = call_user_func_array([$this, $export['get']], [$export, $this->data['categories_id']]);
            }
            $this->data[$db_key] = isset($this->data[$db_key]) ? $this->data[$db_key] : '';
        }
        return $this->data;
    }
}