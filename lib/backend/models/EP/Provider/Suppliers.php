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
use backend\models\EP\Messages;
use common\helpers\Currencies;
use common\models\Suppliers as sModel;
class Suppliers extends Provider_Abstract implements Import_Interface, Export_Interface
{
    protected $fields = [];
    protected $data = [];
    protected $e_ptools;
    protected $_processed_pids = [];
    protected $import_folder;
    protected $entry_counter = 0;
    protected $_products_lookup = [];
    protected $_not_processed_product_categories = [];
    protected $file_primary_columns = [];
    protected $export_query;
    public function init()
    {
        parent::init();
        $this->init_fields();
        $this->e_ptools = new EP\Tools();
    }
    protected function init_fields()
    {
        $this->fields = [];
        $this->fields[] = ['name' => 'suppliers_name', 'value' => 'Suppliers name', 'is_key' => true];
        $this->fields[] = ['name' => 'is_default', 'value' => 'Default?'];
        $this->fields[] = ['name' => 'status', 'value' => 'Status'];
        $this->fields[] = ['name' => 'contact_name', 'value' => 'Contact Name'];
        $this->fields[] = ['name' => 'contact_phone', 'value' => 'Phone'];
        $this->fields[] = ['name' => 'send_email', 'value' => 'Email'];
        $this->fields[] = ['name' => 'street_address', 'value' => 'Street Address'];
        $this->fields[] = ['name' => 'suburb', 'value' => 'Suburb'];
        $this->fields[] = ['name' => 'city', 'value' => 'Town/City'];
        $this->fields[] = ['name' => 'postcode', 'value' => 'Post Code'];
        $this->fields[] = ['name' => 'state', 'value' => 'State/Province'];
        $this->fields[] = ['name' => 'country', 'value' => 'Country'];
        $this->fields[] = ['name' => 'awrs_no', 'value' => 'AWRS Number'];
        $this->fields[] = ['name' => 'sage_code', 'value' => 'Sage Code'];
        $this->fields[] = ['name' => 'payment_delay', 'value' => 'Payment Delay'];
        $this->fields[] = ['name' => 'supply_delay', 'value' => 'Supply Delay'];
        //$this->fields[] = array('name' => 'condition', 'value' => 'Condition');
        $this->fields[] = ['name' => 'condition_description', 'value' => 'Condition description'];
        $this->fields[] = ['name' => 'currencies_id', 'value' => 'Default currency code', 'set' => 'set_currency', 'get' => 'get_currency'];
        $this->fields[] = ['name' => 'delivery_days_min', 'value' => 'Delivery: min days'];
        $this->fields[] = ['name' => 'delivery_days_max', 'value' => 'Delivery: max days'];
        $this->fields[] = ['name' => 'company', 'value' => 'Company Name'];
        $this->fields[] = ['name' => 'company_vat', 'value' => 'Company VAT Number'];
        $this->fields[] = ['name' => 'supplier_prices_with_tax', 'value' => 'Price incl. tax'];
        $this->fields[] = ['name' => 'tax_rate', 'value' => 'Tax rate'];
        if ($ext = \common\helpers\Extensions::is_allowed('EventSystem')) {
            $this->fields = array_merge($this->fields, $ext::partner()->get_export_additional_fields());
        }
    }
    public function prepare_export($use_columns, $filter)
    {
        $this->build_sources($use_columns);
        $main_source = $this->main_source;
        $filter_sql = '';
        if (false && is_array($filter)) {
            if (isset($filter['category_id']) && $filter['category_id'] > 0) {
                $categories = [(int) $filter['category_id']];
                \common\helpers\Categories::get_subcategories($categories, $categories[0]);
                $filter_sql .= 'AND p.products_id IN(SELECT products_id FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id IN('" . implode("','", $categories) . "')) ";
            }
        }
        $main_sql = "SELECT {$main_source['select']} sp.suppliers_id " . 'FROM ' . TABLE_SUPPLIERS . ' sp ' . 'WHERE 1 ' . " {$filter_sql} " . 'ORDER BY sp.suppliers_id';
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
        foreach ($data_sources as $source_key => $source_data) {
            if ($source_data['init_function'] && method_exists($this, $source_data['init_function'])) {
                call_user_func_array([$this, $source_data['init_function']], $source_data['params']);
            }
        }
        foreach ($export_columns as $db_key => $export) {
            if (isset($export['get']) && method_exists($this, $export['get'])) {
                $this->data[$db_key] = call_user_func_array([$this, $export['get']], [$export, $this->data['products_id'] ?? null]);
            }
        }
        if ($ext = \common\helpers\Extensions::is_allowed('EventSystem')) {
            $ext::partner()->export_additional_fields_values($this->data);
        }
        return $this->data;
    }
    public function import_row($data, Messages $message)
    {
        $this->build_sources(array_keys($data));
        $this->data = $data;
        $export_columns = $this->export_columns;
        $main_source = $this->main_source;
        $file_primary_column = $this->file_primary_column;
        $is_updated = false;
        if (!array_key_exists($file_primary_column, $this->data)) {
            throw new EP\Exception('Primary key not found in file');
        }
        $file_primary_value = $this->data[$file_primary_column];
        if (!isset($this->data[$file_primary_column]) || empty($this->data[$file_primary_column]) || trim($this->data[$file_primary_column]) == '') {
            $message->info('Empty primary column "' . $file_primary_column . '". Skipped');
            return false;
        }
        $update_data_array = [];
        foreach ($main_source['columns'] as $file_column => $db_column) {
            if (!array_key_exists($file_column, $this->data)) {
                continue;
            }
            if (isset($export_columns[$db_column]['set']) && method_exists($this, $export_columns[$db_column]['set'])) {
                call_user_func_array([$this, $export_columns[$db_column]['set']], [$export_columns[$db_column], $this->data['inventory_id'] ?? null, $message]);
            }
            $update_data_array[$db_column] = $this->data[$file_column];
        }
        try {
            $suppliers = S_Model::find()->where($file_primary_column . ' like :name', [':name' => $file_primary_value]);
            //          echo $supplier->createCommand()->getRawSql();
            if ($suppliers->count() > 1) {
                $message->info('"' . $file_primary_value . '" not unique - found ' . $suppliers->count() . ' rows. Skipped');
                return false;
            } else {
                $supplier = $suppliers->one();
                if (is_null($supplier)) {
                    //$supplier = new sModel;
                    $supplier = new S_Model(['scenario' => 'insert']);
                } else {
                    $supplier->scenario = 'update';
                }
                $auth_params = [];
                if ($supplier->load($update_data_array, '') && $supplier->validate() && $supplier->save_supplier($auth_params)) {
                    if ($ext = \common\helpers\Extensions::is_allowed('EventSystem')) {
                        $ext::partner()->save_partner_additional_fields($supplier->suppliers_id, $this->data);
                    }
                    $this->entry_counter++;
                } else {
                    $message->info('"' . $file_primary_value . '" Errors: ' . print_r($supplier->get_errors(), 1) . ' Skipped');
                    return false;
                }
            }
        } catch (\Exception $e) {
            $message->info('"' . $file_primary_value . '"' . print_r($e->get_message(), 1) . ' Skipped');
            $this->report_error($e);
            return false;
        }
        return $is_updated;
    }
    public function post_process(Messages $message)
    {
        $message->info('Processed ' . $this->entry_counter . ' rows');
        $message->info('Done');
    }
    public function get_currency($field_data)
    {
        $ret = false;
        if (!empty($this->data[$field_data['name']])) {
            $ret = Currencies::get_currency_code($this->data[$field_data['name']]);
        }
        if ($ret === false) {
            $ret = Currencies::system_currency_code();
        }
        return $ret;
    }
    public function set_currency($field_data, $products_id)
    {
        if ($this->data[$field_data['name']] === '') {
            $this->data[$field_data['name']] = Currencies::get_currency_id(Currencies::system_currency_code());
        } else {
            $code = $this->data[$field_data['name']];
            $this->data[$field_data['name']] = Currencies::get_currency_id($code);
            if ($this->data[$field_data['name']] === false) {
                $this->data[$field_data['name']] = Currencies::get_currency_id_by_name($code);
            }
            if ($this->data[$field_data['name']] === false) {
                $this->data[$field_data['name']] = Currencies::get_currency_id(Currencies::system_currency_code());
            }
        }
        return $this->data[$field_data['name']];
    }
}