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
use backend\models\EP\Formatter;
use backend\models\EP\Messages;
use common\models\Products_Assets;
use common\models\Products_Assets_Fields;
use common\models\Products_Assets_Values;
class Assets extends Provider_Abstract implements Import_Interface, Export_Interface
{
    protected $fields = [];
    protected $data = [];
    protected $e_ptools;
    protected $_language_id;
    protected $last_product_lookup = [];
    protected $updated_product_ids = [];
    protected $export_query;
    public function init()
    {
        parent::init();
        $this->_language_id = intval($this->languages_id);
        $this->init_fields();
        $this->e_ptools = new EP\Tools();
    }
    protected function init_fields()
    {
        $this->fields = [];
        $this->fields[] = ['name' => 'products_model', 'calculated' => true, 'value' => 'Products Model', 'is_key' => true];
        $this->fields[] = ['name' => 'products_name', 'calculated' => true, 'value' => 'Products Name'];
        $this->fields[] = ['name' => 'warehouse_id', 'calculated' => true, 'value' => 'Warehouse Name'];
        $this->fields[] = ['name' => 'products_assets_fields_name', 'calculated' => true, 'value' => 'Field'];
        $this->fields[] = ['name' => 'products_assets_value', 'calculated' => true, 'value' => 'Value'];
    }
    public function prepare_export($use_columns, $filter)
    {
        $this->build_sources($use_columns);
        $main_source = $this->main_source;
        $filter_sql = '';
        if (is_array($filter)) {
            if (isset($filter['products_id']) && is_array($filter['products_id']) && count($filter['products_id']) > 0) {
                $filter_sql .= "AND p.products_id IN ('" . implode("','", array_map('intval', $filter['products_id'])) . "') ";
            }
            if (isset($filter['category_id']) && $filter['category_id'] > 0) {
                $categories = [(int) $filter['category_id']];
                \common\helpers\Categories::get_subcategories($categories, $categories[0]);
                $filter_sql .= 'AND p.products_id IN(SELECT products_id FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id IN('" . implode("','", $categories) . "')) ";
            }
        }
        //echo '<pre>'; var_dump($export_columns,$data_sources); echo '</pre>';
        $main_sql = 'SELECT pa.products_assets_id, p.products_id, pa.warehouse_id, pa.suppliers_id, ifnull(i.products_model, p.products_model) as products_model, paf.products_assets_fields_name, pav.products_assets_value, if(i.products_id,1,0) as is_uprid, pa.uprid ' . 'FROM ' . Products_Assets::table_name() . ' pa ' . ' LEFT JOIN ' . Products_Assets_Fields::table_name() . " paf ON paf.language_id='" . $this->_language_id . "' " . ' LEFT JOIN ' . Products_Assets_Values::table_name() . ' pav ON pav.products_assets_id=pa.products_assets_id AND paf.products_assets_fields_id = pav.products_assets_fields_id' . ' INNER JOIN ' . TABLE_PRODUCTS . ' p on p.products_id = pa.products_id ' . ' LEFT JOIN ' . TABLE_INVENTORY . ' i on pa.uprid = i.products_id ' . " WHERE pa.orders_id = 0 and 1 {$filter_sql} " . ' ORDER BY p.products_id, pa.products_assets_id ';
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
        $this->data['warehouse_id'] = \common\helpers\Warehouses::get_warehouse_name($this->data['warehouse_id']);
        $this->data['products_name'] = \common\helpers\Product::get_products_name($this->data['products_id'], $this->_language_id);
        if ($this->data['is_uprid']) {
            $this->data['products_name'] .= ' ' . \common\helpers\Inventory::get_inventory_name_by_uprid($this->data['uprid']);
        }
        foreach ($export_columns as $db_key => $export) {
            if (isset($export['get']) && method_exists($this, $export['get'])) {
                $this->data[$db_key] = call_user_func_array([$this, $export['get']], [$export, $this->data['products_id']]);
            }
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
        $this->data = $data;
        static $check_required_columns = true;
        //??
        if ($check_required_columns) {
            $error = false;
            foreach (['products_model', 'products_name', 'products_assets_fields_name', 'products_assets_value'] as $required_file_column) {
                if (!array_key_exists($required_file_column, $this->data)) {
                    $message->info('Required column "' . $export_columns[$required_file_column]['value'] . '" not found in file');
                }
            }
            if ($error) {
                return;
            }
            $check_required_columns = false;
        }
        $file_primary_value = $this->data[$file_primary_column];
        if (empty($file_primary_value)) {
            $message->info('Empty "' . $export_columns[$file_primary_column]['value'] . '" column. Row skipped');
            return false;
        }
        if (!isset($this->last_product_lookup[$file_primary_value])) {
            $get_main_data_r = \common\models\Products::find()->select(['products_id', 'stock_indication_id'])->where(['products_model' => $file_primary_value]);
            $found_rows = $get_main_data_r->count();
            $is_inventory = false;
            if (!$found_rows) {
                $get_main_data_r = \common\models\Inventory::find()->select(['products_id', 'stock_indication_id'])->where(['products_model' => $file_primary_value]);
                $found_rows = $get_main_data_r->count();
                $is_inventory = $found_rows > 0;
            }
            $this->last_product_lookup = [$file_primary_value => ['found_rows' => $found_rows, 'data' => $found_rows > 0 ? $get_main_data_r->one() : false, 'isInventory' => $is_inventory]];
            if ($found_rows > 1) {
                $message->info('Product "' . $file_primary_value . '" not unique - found ' . $found_rows . ' rows. Skipped');
            } elseif ($found_rows == 0) {
                $message->info('Product "' . $file_primary_value . '" not found. Skipped');
            }
            //$entry_counter++;
        }
        $found_rows = $this->last_product_lookup[$file_primary_value]['found_rows'];
        if ($found_rows > 1) {
            // error data not unique
            //$message->info('Product "'.$file_primary_value.'" not unique - found '.$found_rows.' rows. Skipped');
            return false;
        } elseif ($found_rows == 0) {
            // dummy
            //$message->info('Product "'.$file_primary_value.'" not found. Skipped');
            return false;
        } else {
            $db_main_data = $this->last_product_lookup[$file_primary_value]['data'];
            $products_id = $db_main_data->products_id;
            if (\common\helpers\Attributes::has_product_attributes($products_id) && \common\helpers\Extensions::is_allowed('Inventory') && !$is_inventory) {
                $products_id = \common\helpers\Inventory::get_first_invetory($products_id);
                $db_main_data = \common\models\Inventory::find()->where(['products_id' => $products_id, 'prid' => (int) $products_id])->one();
                Products_Assets::update_all(['uprid' => $products_id], ['products_id' => (int) $products_id, 'uprid' => (int) $products_id]);
            }
            $is_inventory = $this->last_product_lookup[$file_primary_value]['isInventory'];
        }
        $this->data['products_id'] = $products_id;
        $this->data['warehouse_id'] = $this->get_warehouse_id($this->data['warehouse_id']);
        $this->data['field_id'] = $this->get_field($this->data['products_assets_fields_name']);
        $this->data['products_assets_value'] = trim($this->data['products_assets_value']);
        if (!$this->data['field_id']) {
            $message->info('Asset field name incorrect. Skipped');
            return false;
        }
        if (!isset($this->updated_product_ids[(int) $products_id . '_' . $products_id])) {
            $this->updated_product_ids[(int) $products_id . '_' . $products_id] = [];
        }
        $tmp = ['field_id' => $this->data['field_id'], 'value' => $this->data['products_assets_value'], 'db_data' => $db_main_data];
        $assets = Products_Assets::find()->where(['products_id' => (int) $products_id, 'uprid' => $products_id])->join_with('assetValues')->all();
        $insert = false;
        if ($assets) {
            $found = false;
            foreach ($assets as $asset) {
                if ($this->data['products_assets_value'] == $asset->asset_values[0]->products_assets_value) {
                    $found = $asset;
                    $tmp['asset_id'] = $asset->products_assets_id;
                    break;
                }
            }
            unset($assets);
            if ($found) {
                $found->warehouse_id = $this->data['warehouse_id'];
                $found->suppliers_id = 0;
                $found->save(false);
            } else {
                $insert = true;
            }
        } else {
            $insert = true;
        }
        if ($insert) {
            $product_asset = new Products_Assets();
            $product_asset->set_attributes(['products_id' => (int) $this->data['products_id'], 'uprid' => $this->data['products_id'], 'warehouse_id' => $this->data['warehouse_id'], 'suppliers_id' => 0, 'orders_id' => 0], false);
            $product_asset->insert(false);
            $product_asset_value = new Products_Assets_Values();
            $product_asset_value->products_assets_fields_id = $this->data['field_id'];
            $product_asset_value->products_assets_value = $this->data['products_assets_value'];
            $product_asset->link('assetValues', $product_asset_value);
            $tmp['asset_id'] = $product_asset->products_assets_id;
            unset($product_asset);
            unset($product_asset_value);
        }
        $this->updated_product_ids[(int) $products_id . '_' . $products_id][] = $tmp;
        return true;
    }
    private function get_warehouse_id($warehouse_name = '')
    {
        static $warehouses = [];
        static $default = null;
        if (!$warehouses) {
            $warehouses = \yii\helpers\Array_Helper::index(\common\helpers\Warehouses::get_warehouses(true), 'text', 'id');
            $default = \common\helpers\Warehouses::get_default_warehouse();
        }
        if ($warehouse_name && isset($warehouses[$warehouse_name])) {
            return $warehouses[$warehouse_name];
        } else {
            return $default;
        }
    }
    private function get_field(string $field_name = '')
    {
        static $fields = [];
        if ($field_name) {
            if (!isset($fields[$field_name])) {
                $a_field = Products_Assets_Fields::find()->where(['products_assets_fields_name' => $field_name, 'language_id' => $this->_language_id])->one();
                if (!$a_field) {
                    foreach (\common\helpers\Language::get_languages(true) as $language) {
                        $af = new Products_Assets_Fields();
                        $af->set_attributes(['language_id' => $language['id'], 'products_assets_fields_name' => $field_name, 'date_added' => new \yii\db\Expression('now()')], false);
                        $af->insert(false);
                    }
                    unset($af);
                    if (!$a_field) {
                        $a_field = Products_Assets_Fields::find()->where(['products_assets_fields_name' => $field_name, 'language_id' => $this->_language_id])->one();
                    }
                }
                $fields[$field_name] = $a_field->products_assets_fields_id;
            }
        }
        return $fields[$field_name] ?? false;
    }
    public function post_process(Messages $message)
    {
        $this->ending_process();
        $message->info('Done');
        $this->e_ptools->done('properties_import');
    }
    private function ending_process()
    {
        if ($this->updated_product_ids) {
            $ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed');
            foreach ($this->updated_product_ids as $prids_key => $data) {
                $ex = explode('_', $prids_key);
                $prid = (int) $ex[0];
                $uprid = $ex[1];
                $ids = \yii\helpers\Array_Helper::get_column($data, 'asset_id');
                foreach (Products_Assets::find()->where(['and', ['orders_id' => 0, 'products_id' => $prid, 'uprid' => $uprid], ['not in', 'products_assets_id', $ids]])->all() as $to_delete) {
                    $to_delete->delete();
                }
                unset($to_delete);
                if ($ext) {
                    if (is_object($data[0]['db_data'])) {
                        try {
                            $response = $ext::check_stock($data[0]['db_data']->products_id, true);
                        } catch (\Exception $ex) {
                        }
                    }
                }
            }
            unset($data);
            $this->updated_product_ids = [];
        }
    }
    public function import(Formatter\Formatter_Interface $input, EP\Messages $message)
    {
        $entry_counter = 0;
        $this->last_product_lookup = [];
        $this->updated_product_ids = [];
        //$check_required_columns = true;
        while ($data = $input->read_array()) {
            if ($this->import_row($data)) {
            }
        }
        $this->post_process($message);
    }
    public static function is_export_available()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            return $ext::allowed();
        }
        return false;
    }
    public static function is_import_available()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            return $ext::allowed();
        }
        return false;
    }
}