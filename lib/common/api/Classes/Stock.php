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
namespace common\api\Classes;

class Stock extends Abstract_Class
{
    public $stock_record_array = [];
    private static $allow_field_list = ['prid' => true, 'products_id' => true, 'products_status' => true, 'manual_control_status' => true, 'manual_stock_unlimited' => true, 'stock_indication_id' => true, 'stock_delivery_terms_id' => true, 'stock_reorder_level' => true, 'stock_reorder_quantity' => true, 'stock_control' => true, 'products_id_stock' => true, 'reorder_auto' => true, 'without_inventory' => true, 'attributeRecordArray' => true, 'warehouseRecordArray' => true];
    private function load_inventory($u_product_id = '')
    {
        $u_product_id = trim(\common\helpers\Inventory::normalize_id_excl_virtual($u_product_id));
        if (\common\helpers\Inventory::is_inventory($u_product_id) == true) {
            $inventory_record = \common\helpers\Inventory::get_record($u_product_id);
            if ($inventory_record instanceof \common\models\Inventory) {
                if (!isset($this->stock_record_array[trim($inventory_record->products_id)])) {
                    $attribute_record_array = [];
                    $language_id = \common\classes\language::default_id();
                    $language_code = \common\classes\language::get_code($language_id, true);
                    \common\helpers\Inventory::normalize_inventory_id($inventory_record->products_id, $attribute_array);
                    $attribute_array = is_array($attribute_array) ? $attribute_array : [];
                    foreach ($attribute_array as $attribute_id => $attribute_value_id) {
                        $attribute_record = \common\models\Products_Options2products_Options_Values::find()->alias('atv')->left_join(\common\models\Products_Options::table_name() . ' a', 'a.products_options_id = atv.products_options_id')->left_join(\common\models\Products_Options_Values::table_name() . ' av', 'av.products_options_values_id = atv.products_options_values_id')->where(['atv.products_options_id' => $attribute_id, 'atv.products_options_values_id' => $attribute_value_id, 'a.language_id' => $language_id, 'av.language_id' => $language_id])->select('*')->as_array(true)->one();
                        if (!is_array($attribute_record)) {
                            return false;
                        }
                        $attribute_record['language_code'] = $language_code;
                        $attribute_record_array[] = $attribute_record;
                        unset($attribute_record);
                    }
                    unset($attribute_value_id);
                    unset($attribute_array);
                    unset($language_code);
                    unset($attribute_id);
                    unset($language_id);
                    $this->stock_record_array[trim($inventory_record->products_id)] = ['inventory_id' => (int) $inventory_record->inventory_id, 'prid' => (int) $inventory_record->prid, 'products_id' => trim($inventory_record->products_id), 'products_model' => trim($inventory_record->products_model), 'stock_indication_id' => (int) $inventory_record->stock_indication_id, 'stock_delivery_terms_id' => (int) $inventory_record->stock_delivery_terms_id, 'stock_control' => (int) $inventory_record->stock_control, 'attributeRecordArray' => $attribute_record_array, 'warehouseRecordArray' => \common\models\Warehouses_Products::find()->alias('p')->left_join(\common\models\Warehouses::table_name() . ' w', 'w.warehouse_id = p.warehouse_id')->left_join(\common\models\Suppliers::table_name() . ' s', 's.suppliers_id = p.suppliers_id')->left_join(\common\models\Locations::table_name() . ' l', 'l.location_id = p.location_id')->left_join(\common\models\Location_Blocks::table_name() . ' lb', 'l.block_id = lb.block_id')->where(['prid' => (int) $inventory_record->prid, 'products_id' => trim($inventory_record->products_id)])->select(['p.*', 'w.warehouse_name', 's.suppliers_name', 'l.location_name', 'lb.block_name'])->as_array(true)->all()];
                    unset($attribute_record_array);
                }
                unset($inventory_record);
                unset($u_product_id);
                return true;
            }
        }
        return false;
    }
    private function load_product($product_id = 0)
    {
        $product_id = (int) $product_id;
        $product_record = \common\helpers\Product::get_record($product_id);
        if ($product_record instanceof \common\models\Products) {
            if (!isset($this->stock_record_array[trim($product_record->products_id)])) {
                $this->stock_record_array[trim($product_record->products_id)] = ['inventory_id' => 0, 'prid' => (int) $product_record->products_id, 'products_id' => trim($product_record->products_id), 'products_model' => trim($product_record->products_model), 'products_status' => (int) $product_record->products_status, 'manual_control_status' => (int) $product_record->manual_control_status, 'manual_stock_unlimited' => (int) $product_record->manual_stock_unlimited, 'stock_indication_id' => (int) $product_record->stock_indication_id, 'stock_delivery_terms_id' => (int) $product_record->stock_delivery_terms_id, 'stock_reorder_level' => (int) $product_record->stock_reorder_level, 'stock_reorder_quantity' => (int) $product_record->stock_reorder_quantity, 'stock_control' => (int) $product_record->stock_control, 'products_id_stock' => (int) $product_record->products_id_stock, 'reorder_auto' => (int) $product_record->reorder_auto, 'without_inventory' => (int) $product_record->without_inventory, 'warehouseRecordArray' => \common\models\Warehouses_Products::find()->alias('p')->left_join(\common\models\Warehouses::table_name() . ' w', 'w.warehouse_id = p.warehouse_id')->left_join(\common\models\Suppliers::table_name() . ' s', 's.suppliers_id = p.suppliers_id')->left_join(\common\models\Locations::table_name() . ' l', 'l.location_id = p.location_id')->left_join(\common\models\Location_Blocks::table_name() . ' lb', 'l.block_id = lb.block_id')->where(['prid' => (int) $product_record->products_id, 'products_id' => trim((int) $product_record->products_id)])->select(['p.*', 'w.warehouse_name', 's.suppliers_name', 'l.location_name', 'lb.block_name'])->as_array(true)->all()];
            }
            $child_array = \common\helpers\Product::get_child_array($product_id);
            if (count($child_array) > 0) {
                foreach ($child_array as $child) {
                    if (!isset($this->stock_record_array[trim($child['product_id'])])) {
                        $this->load_product($child['product_id']);
                    }
                }
                unset($child);
            } elseif (\common\helpers\Acl::check_extension_allowed('Inventory', 'allowed')) {
                foreach (\common\models\Inventory::find()->where(['prid' => (int) $product_record->products_id])->as_array(true)->all() as $inventory_record) {
                    if (!isset($this->stock_record_array[trim($inventory_record['products_id'])])) {
                        $this->load_inventory($inventory_record['products_id']);
                    }
                }
                unset($inventory_record);
            }
            unset($product_record);
            unset($child_array);
            unset($product_id);
            return true;
        }
        return false;
    }
    public function load($u_product_id_array = [])
    {
        $this->clear();
        if (!is_array($u_product_id_array)) {
            $u_product_id_array = \common\models\Products::find()->select('products_id')->as_array(true)->column();
        }
        foreach ($u_product_id_array as $u_product_id) {
            if (trim((int) $u_product_id) == trim($u_product_id) or !\common\helpers\Extensions::is_allowed('Inventory')) {
                $this->load_product($u_product_id);
            }
            $this->load_inventory($u_product_id);
        }
        unset($u_product_id_array);
        unset($u_product_id);
        return true;
    }
    public function validate()
    {
        if (!is_array($this->stock_record_array)) {
            return false;
        }
        if (!parent::validate()) {
            return false;
        }
        $warehouse_name_list = [];
        foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
            $warehouse_name_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
        }
        unset($warehouse_record);
        $supplier_name_list = [];
        foreach (\common\models\Suppliers::find()->as_array(true)->all() as $supplier_record) {
            $supplier_name_list[$supplier_record['suppliers_id']] = $supplier_record['suppliers_name'];
        }
        unset($supplier_record);
        $location_list = [];
        foreach (\common\models\Locations::find()->as_array(true)->all() as $location_record) {
            $location_list[$location_record['location_id']] = $location_record['location_name'];
        }
        unset($location_record);
        /*$locationBlockList = [];
          foreach (\common\models\LocationBlocks::find()->asArray(true)->all() as $locationBlockRecord) {
              $locationBlockList[$locationBlockRecord['block_id']] = $locationBlockRecord['block_name'];
          }
          unset($locationBlockRecord);*/
        foreach ($this->stock_record_array as $key => &$stock_record) {
            $stock_record['products_model'] = trim(isset($stock_record['products_model']) ? $stock_record['products_model'] : '');
            $stock_record['attributeRecordArray'] = (isset($stock_record['attributeRecordArray']) and is_array($stock_record['attributeRecordArray'])) ? $stock_record['attributeRecordArray'] : [];
            $stock_record['warehouseRecordArray'] = (isset($stock_record['warehouseRecordArray']) and is_array($stock_record['warehouseRecordArray'])) ? $stock_record['warehouseRecordArray'] : [];
            if ($stock_record['products_model'] != '') {
                /*if ((count($stockRecord['attributeRecordArray']) > 0) == true) {}*/
                $search_record = \common\models\Inventory::find()->where(['products_model' => $stock_record['products_model']])->as_array(true)->all();
                if (count($search_record) == 0) {
                    $search_record = \common\models\Products::find()->where(['products_model' => $stock_record['products_model']])->as_array(true)->all();
                }
                $u_product_id = trim(isset($stock_record['products_id']) ? $stock_record['products_id'] : '');
                if ($u_product_id != '' and count($search_record) > 1) {
                    foreach ($search_record as $exact_record) {
                        if ($u_product_id === trim($exact_record['products_id'])) {
                            $search_record = [$exact_record];
                            break;
                        }
                    }
                    unset($exact_record);
                }
                unset($u_product_id);
                if (count($search_record) != 1) {
                    unset($this->stock_record_array[$key]);
                    continue;
                }
                $search_record = $search_record[0];
                $stock_record['prid'] = (int) (isset($search_record['prid']) ? $search_record['prid'] : $search_record['products_id']);
                $stock_record['products_id'] = trim($search_record['products_id']);
                unset($search_record);
            }
            $stock_record['products_id'] = trim(isset($stock_record['products_id']) ? $stock_record['products_id'] : '0');
            $stock_record['prid'] = (int) (isset($stock_record['prid']) ? $stock_record['prid'] : $stock_record['products_id']);
            if ($stock_record['prid'] <= 0 or $stock_record['prid'] != (int) $stock_record['products_id']) {
                unset($this->stock_record_array[$key]);
                continue;
            }
            foreach ($stock_record as $field => $null) {
                if (!isset(self::$allow_field_list[$field])) {
                    unset($stock_record[$field]);
                }
            }
            unset($field);
            unset($null);
            foreach ($stock_record['warehouseRecordArray'] as $key_w => &$warehouse_record) {
                $warehouse_record['prid'] = $stock_record['prid'];
                $warehouse_record['products_id'] = $stock_record['products_id'];
                if (isset($warehouse_record['products_model'])) {
                    $warehouse_record['products_model'] = trim($warehouse_record['products_model']);
                }
                if (isset($warehouse_record['warehouse_name']) and trim($warehouse_record['warehouse_name']) != '') {
                    $warehouse_record['warehouse_id'] = (int) array_search($warehouse_record['warehouse_name'], $warehouse_name_list);
                }
                if (isset($warehouse_record['suppliers_name']) and trim($warehouse_record['suppliers_name']) != '') {
                    $warehouse_record['suppliers_id'] = (int) array_search($warehouse_record['suppliers_name'], $supplier_name_list);
                }
                if (isset($warehouse_record['location_name'])) {
                    $warehouse_record['location_id'] = (int) array_search($warehouse_record['location_name'], $location_list);
                }
                /*if (isset($warehouseRecord['block_name']) AND (trim($warehouseRecord['block_name']) != '')) {
                      $warehouseRecord['block_id'] = (int)array_search($warehouseRecord['block_name'], $locationBlockList);
                  }*/
                $warehouse_record['warehouse_id'] = (int) (isset($warehouse_record['warehouse_id']) ? $warehouse_record['warehouse_id'] : 0);
                $warehouse_record['suppliers_id'] = (int) (isset($warehouse_record['suppliers_id']) ? $warehouse_record['suppliers_id'] : 0);
                $warehouse_record['location_id'] = (int) (isset($warehouse_record['location_id']) ? $warehouse_record['location_id'] : 0);
                $warehouse_record['block_id'] = (int) (isset($warehouse_record['block_id']) ? $warehouse_record['block_id'] : 0);
                $warehouse_record['warehouse_id'] = (int) ($warehouse_record['warehouse_id'] <= 0 ? \common\helpers\Warehouses::get_default_warehouse() : $warehouse_record['warehouse_id']);
                $warehouse_record['suppliers_id'] = (int) ($warehouse_record['suppliers_id'] <= 0 ? \common\helpers\Suppliers::get_default_supplier_id() : $warehouse_record['suppliers_id']);
                $warehouse_record['location_id'] = (int) ($warehouse_record['location_id'] <= 0 ? 0 : $warehouse_record['location_id']);
                $warehouse_record['block_id'] = (int) ($warehouse_record['block_id'] <= 0 ? 0 : $warehouse_record['block_id']);
            }
            unset($warehouse_record);
            unset($key_w);
        }
        unset($warehouse_name_list);
        //unset($locationBlockList);
        unset($supplier_name_list);
        unset($location_list);
        unset($stock_record);
        unset($key);
        if (count($this->stock_record_array) == 0) {
            return false;
        }
        return true;
    }
    public function create()
    {
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        $do_cache_list = [];
        foreach ($this->stock_record_array as $key => &$stock_record) {
            $is_save = false;
            try {
                $search_record = \common\models\Inventory::find()->where(['products_id' => $stock_record['products_id']])->as_array(false)->all();
                if (count($search_record) == 0) {
                    $search_record = \common\models\Products::find()->where(['products_id' => $stock_record['prid']])->as_array(false)->all();
                }
                if (count($search_record) == 1) {
                    $search_record = $search_record[0];
                    $search_record->set_attributes($stock_record, false);
                    if ($search_record->save(false)) {
                        $is_save = true;
                        foreach ($stock_record['warehouseRecordArray'] as $key_w => &$warehouse_record) {
                            $is_save_w = false;
                            try {
                                $warehouse_class = \common\models\Warehouses_Products::find()->where(['prid' => $warehouse_record['prid'], 'products_id' => $warehouse_record['products_id'], 'warehouse_id' => $warehouse_record['warehouse_id'], 'suppliers_id' => $warehouse_record['suppliers_id'], 'location_id' => $warehouse_record['location_id']])->as_array(false)->one();
                                if (!$warehouse_class instanceof \common\models\Warehouses_Products) {
                                    $warehouse_class = new \common\models\Warehouses_Products();
                                    $warehouse_class->load_default_values();
                                }
                                $warehouse_class->set_attributes($warehouse_record, false);
                                if ($warehouse_class->save() == true) {
                                    $is_save_w = true;
                                    if ((float) $warehouse_class->warehouse_stock_quantity <= 0) {
                                        unset($stock_record['warehouseRecordArray'][$key_w]);
                                        $warehouse_class->delete();
                                    } else {
                                        $warehouse_record = $warehouse_class->to_array() + $warehouse_record;
                                    }
                                } else {
                                    $this->message_add($warehouse_class->get_error_summary(true));
                                }
                            } catch (\Exception $exc) {
                                $this->message_add($exc->get_message());
                            }
                            unset($warehouse_class);
                            if ($is_save_w != true) {
                                unset($stock_record['warehouseRecordArray'][$key_w]);
                            }
                            unset($is_save_w);
                        }
                        unset($warehouse_record);
                        unset($key_w);
                        foreach ($stock_record as $field => $null) {
                            if (isset($search_record->{$field})) {
                                $stock_record[$field] = $search_record->{$field};
                            } elseif (!is_array($stock_record[$field])) {
                                unset($stock_record[$field]);
                            }
                        }
                        unset($field);
                        unset($null);
                    } else {
                        $this->message_add($search_record->get_error_summary(true));
                    }
                }
            } catch (\Exception $exc) {
                $this->message_add($exc->get_message());
            }
            unset($search_record);
            if ($is_save != true) {
                unset($this->stock_record_array[$key]);
            } else {
                $do_cache_list[(int) $stock_record['products_id']] = (int) $stock_record['products_id'];
            }
            unset($is_save);
        }
        unset($stock_record);
        unset($key);
        $return = true;
        foreach ($do_cache_list as $product_id) {
            $return = (\common\helpers\Product::do_cache($product_id) and $return);
        }
        unset($do_cache_list);
        unset($product_id);
        unset($is_replace);
        return $return;
    }
}