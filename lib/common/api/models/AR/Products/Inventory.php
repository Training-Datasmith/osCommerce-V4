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
namespace common\api\models\AR\Products;

use backend\models\EP\Tools;
use common\api\models\AR\Ep_Map;
use common\api\models\AR\Products;
use common\api\models\AR\Products\Inventory\Prices as Inventory_Prices;
class Inventory extends Ep_Map
{
    protected $hide_fields = ['inventory_id'];
    protected $child_collections = ['prices' => [], 'warehouses_products' => []];
    protected $indexed_collections = ['warehouses_products' => 'common\api\models\AR\Products\WarehousesProducts'];
    protected $option_values_list = [];
    /**
     * @var Products
     */
    protected $parent_object;
    protected $update_product_stock = false;
    public function __construct(array $config = [])
    {
        $market_present = defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True';
        $groups_present = \common\helpers\Extensions::is_customer_groups_allowed();
        if (!$market_present && !$groups_present) {
            unset($this->child_collections['prices']);
        }
        $this->after_save_hooks['Product::doCache'] = 'reCalculateStockProduct';
        parent::__construct($config);
    }
    /**
     * @inheritdoc
     */
    public static function table_name()
    {
        return TABLE_INVENTORY;
    }
    /**
     * @inheritdoc
     */
    public static function primary_key()
    {
        return ['inventory_id'];
    }
    public function fill_option_value_list()
    {
        $this->option_values_list = $opt_val_match = [];
        if (preg_match_all('/{(\d+)}(\d+)/', $this->products_id, $opt_val_match)) {
            foreach ($opt_val_match[1] as $_idx => $opt_id) {
                $val_id = $opt_val_match[2][$_idx];
                $int_key = $opt_id . '-' . $val_id;
                $this->option_values_list[$int_key] = ['options_id' => $opt_id, 'options_values_id' => $val_id];
            }
        }
    }
    public function after_find()
    {
        parent::after_find();
        $this->fill_option_value_list();
    }
    public function export_array(array $fields = [])
    {
        $tools = new \backend\models\EP\Tools();
        $export = parent::export_array($fields);
        if (array_key_exists('stock_delivery_terms_id', $export) || in_array('stock_delivery_terms_text', $fields)) {
            $export['stock_delivery_terms_text'] = $tools->get_stock_delivery_terms($this->stock_delivery_terms_id);
        }
        if (array_key_exists('stock_indication_id', $export) || in_array('stock_indication_text', $fields)) {
            $export['stock_indication_text'] = $tools->get_stock_indication($this->stock_indication_id);
        }
        $export['attribute_map'] = array_values($this->option_values_list);
        foreach ($export['attribute_map'] as $idx => $option_value) {
            $export['attribute_map'][$idx]['options_name'] = $tools->get_option_name($option_value['options_id'], \common\classes\language::default_id());
            $export['attribute_map'][$idx]['options_values_name'] = $tools->get_option_value_name($option_value['options_values_id'], \common\classes\language::default_id());
        }
        return $export;
    }
    public function import_array($data)
    {
        $valid_attributes = false;
        if (is_object($this->parent_object)) {
            $valid_attributes = $this->parent_object->get_assigned_attribute_ids();
        }
        $tools = new \backend\models\EP\Tools();
        if (array_key_exists('stock_delivery_terms_text', $data)) {
            $data['stock_delivery_terms_id'] = $tools->lookup_stock_delivery_term_id($data['stock_delivery_terms_text']);
        }
        if (array_key_exists('stock_indication_text', $data)) {
            $data['stock_indication_id'] = $tools->lookup_stock_indication_id($data['stock_indication_text']);
        }
        if (isset($data['attribute_map']) && is_array($data['attribute_map'])) {
            foreach ($data['attribute_map'] as $idx => $attr_info) {
                $data['attribute_map'][$idx]['options_id'] = $tools->get_option_by_name($attr_info['options_name']);
                $data['attribute_map'][$idx]['options_values_id'] = $tools->get_option_value_by_name($data['attribute_map'][$idx]['options_id'], $attr_info['options_values_name']);
            }
            $this->option_values_list = [];
            foreach ($data['attribute_map'] as $idx => $attr_info) {
                if (is_array($valid_attributes) && !isset($valid_attributes[$attr_info['options_id']])) {
                    return false;
                }
                if (is_array($valid_attributes) && !in_array($attr_info['options_values_id'], $valid_attributes[$attr_info['options_id']])) {
                    return false;
                }
                $int_key = $attr_info['options_id'] . '-' . $attr_info['options_values_id'];
                $this->option_values_list[$int_key] = $attr_info;
            }
            $this->regenerate_fields(true);
        } elseif (preg_match_all('/{(\d+)}(\d+)/', $data['products_id'], $_import_attr)) {
            $data['attribute_map'] = [];
            $this->option_values_list = [];
            foreach ($_import_attr[1] as $__idx => $_opt_id) {
                $_val_id = $_import_attr[2][$__idx];
                if (is_array($valid_attributes) && !isset($valid_attributes[$_opt_id])) {
                    return false;
                }
                if (is_array($valid_attributes) && !in_array($_val_id, $valid_attributes[$_opt_id])) {
                    return false;
                }
                $int_key = $_opt_id . '-' . $_val_id;
                $attr_info = ['options_id' => $_opt_id, 'options_values_id' => $_val_id];
                $this->option_values_list[$int_key] = $attr_info;
                $data['attribute_map'][] = $attr_info;
            }
            $this->regenerate_fields(true);
        }
        if (strpos((string) $this->products_id, '{') === false) {
            return false;
        }
        if (isset($data['warehouses_products']) && is_array($data['warehouses_products'])) {
            unset($data['products_quantity']);
        }
        $result = parent::import_array($data);
        $this->regenerate_fields();
        return $result;
    }
    protected function regenerate_fields($only_uprid = false)
    {
        if (!is_object($this->parent_object)) {
            return;
        }
        $attr = [];
        foreach ($this->option_values_list as $opt_val_info) {
            $attr[$opt_val_info['options_id']] = $opt_val_info['options_values_id'];
        }
        ksort($attr);
        $this->products_id = \common\helpers\Inventory::normalize_id(\common\helpers\Inventory::get_uprid($this->parent_object->products_id, $attr));
        if (!$only_uprid) {
            $tools = new Tools();
            $this->products_name = \common\helpers\Product::get_products_name($this->parent_object->products_id, \common\classes\language::default_id());
            foreach ($attr as $value_id) {
                $this->products_name .= ' ' . $tools->get_option_value_name($value_id, \common\classes\language::default_id());
            }
        }
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->prid = $parent_object->products_id;
        $this->parent_object = $parent_object;
        $this->regenerate_fields();
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        $matched_attr_keys = array_intersect(array_keys($this->option_values_list), array_keys($imported_object->option_values_list));
        $object_match = count($matched_attr_keys) == count($this->option_values_list);
        if ($object_match) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function init_collection_by_lookup_key_prices($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        if (true) {
            if (!is_null($this->inventory_id)) {
                $db_map_collect = [];
                foreach (Inventory_Prices::find_all(['inventory_id' => $this->inventory_id]) as $obj) {
                    $key_code = $obj->currencies_id . '_' . $obj->groups_id;
                    $db_map_collect[$key_code] = $obj;
                }
                foreach (Inventory_Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                    if ($load_all || in_array($key_code, $lookup_keys)) {
                        $db_key_code = $lookup_pk['currencies_id'] . '_' . $lookup_pk['groups_id'];
                        if (isset($db_map_collect[$db_key_code])) {
                            $this->child_collections['prices'][$key_code] = $db_map_collect[$db_key_code];
                        } else {
                            $lookup_pk['inventory_id'] = $this->inventory_id;
                            $this->child_collections['prices'][$key_code] = new Inventory_Prices($lookup_pk);
                        }
                    }
                }
                unset($db_map_collect);
            } else {
                foreach (Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                    $this->child_collections['prices'][$key_code] = new Inventory_Prices($lookup_pk);
                }
            }
        } else {
            foreach (Inventory_Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                $this->child_collections['prices'][$key_code] = null;
                if (is_null($this->inventory_id)) {
                    $this->child_collections['prices'][$key_code] = new Inventory_Prices($lookup_pk);
                } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                    if (!isset($this->child_collections['prices'][$key_code])) {
                        $lookup_pk['inventory_id'] = $this->inventory_id;
                        $this->child_collections['prices'][$key_code] = Inventory_Prices::find_one($lookup_pk);
                        if (!is_object($this->child_collections['prices'][$key_code])) {
                            $this->child_collections['prices'][$key_code] = new Inventory_Prices($lookup_pk);
                        }
                    }
                }
            }
        }
        return $this->child_collections['prices'];
    }
    public function init_collection_by_lookup_key_warehouses_products($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        if (false) {
            if (!is_null($this->products_id)) {
                $db_map_collect = [];
                foreach (Warehouses_Products::find_all(['products_id' => $this->products_id]) as $obj) {
                    $key_code = $obj->warehouse_id . '_' . $obj->suppliers_id;
                    $db_map_collect[$key_code] = $obj;
                }
                foreach (Warehouses_Products::get_all_key_codes() as $key_code => $lookup_pk) {
                    $lookup_pk['products_id'] = $this->products_id;
                    if ($load_all || in_array($key_code, $lookup_keys)) {
                        if (isset($db_map_collect[$key_code])) {
                            $this->child_collections['warehouses_products'][$key_code] = $db_map_collect[$key_code];
                        } else {
                            $this->child_collections['warehouses_products'][$key_code] = new Warehouses_Products($lookup_pk);
                        }
                    }
                }
            } else {
                foreach (Warehouses_Products::get_all_key_codes() as $key_code => $lookup_pk) {
                    $this->child_collections['warehouses_products'][$key_code] = new Warehouses_Products($lookup_pk);
                }
            }
        } else if (!is_null($this->products_id)) {
            $db_map_collect = [];
            foreach (Warehouses_Products::find_all(['products_id' => strval($this->products_id)]) as $obj) {
                $key_code = $obj->warehouse_id . '_' . $obj->suppliers_id;
                if (!empty($obj->location_id)) {
                    $key_code .= '_' . $obj->location_id;
                }
                $db_map_collect[$key_code] = $obj;
            }
            foreach (Warehouses_Products::get_all_key_codes() as $key_code => $lookup_pk) {
                if ($load_all || in_array($key_code, $lookup_keys)) {
                    if (isset($db_map_collect[$key_code])) {
                        $this->child_collections['warehouses_products'][$key_code] = $db_map_collect[$key_code];
                    }
                }
            }
        }
        return $this->child_collections['warehouses_products'];
    }
    public function before_save($insert)
    {
        if ($insert) {
            if (is_null($this->products_name)) {
                $this->products_name = strval($this->parent_object->get_collection_product_name());
                $tools = Tools::get_instance();
                $attr = [];
                foreach ($this->option_values_list as $opt_val_info) {
                    $attr[$opt_val_info['options_id']] = $opt_val_info['options_values_id'];
                }
                ksort($attr);
                foreach ($attr as $value_id) {
                    $this->products_name .= ' ' . $tools->get_option_value_name($value_id, \common\classes\language::default_id());
                }
            }
            if (is_null($this->products_model)) {
                $this->products_model = '';
            }
            if (is_null($this->products_ean)) {
                $this->products_ean = '';
            }
            if (is_null($this->products_asin)) {
                $this->products_asin = '';
            }
            if (is_null($this->products_isbn)) {
                $this->products_isbn = '';
            }
            if (is_null($this->products_upc)) {
                $this->products_upc = '';
            }
            if (is_null($this->non_existent)) {
                $this->non_existent = 0;
            }
        }
        if ($this->get_dirty_attributes(['products_quantity'])) {
            $this->update_product_stock = true;
            $default_warehouse_id = intval(\common\helpers\Warehouses::get_default_warehouse());
            $default_wh = $default_warehouse_id . '_' . \common\helpers\Suppliers::get_default_supplier_id();
            if (count($this->child_collections['warehouses_products']) == 0) {
                $this->init_collection_by_lookup_key_warehouses_products(['*']);
            }
            if (!isset($this->child_collections['warehouses_products'][$default_wh])) {
                $this->child_collections['warehouses_products'][$default_wh] = new Warehouses_Products([]);
                $this->child_collections['warehouses_products'][$default_wh]->warehouse_id = $default_warehouse_id;
                $this->child_collections['warehouses_products'][$default_wh]->suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
                $this->child_collections['warehouses_products'][$default_wh]->parent_ep_map($this);
            }
            $this->child_collections['warehouses_products'][$default_wh]->warehouse_stock_quantity = $this->products_quantity;
            $this->re_calculate_stock_product();
            unset($this->products_quantity);
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if ($this->update_product_stock) {
            $inventory_quantity = tep_db_fetch_array(tep_db_query('SELECT SUM(products_quantity) AS left_quantity ' . 'FROM ' . TABLE_INVENTORY . ' ' . "WHERE prid = '" . (int) $this->prid . "' AND IFNULL(non_existent,0)=0 " . ' AND products_quantity>0'));
            tep_db_query('update ' . TABLE_PRODUCTS . " set products_quantity = '" . (int) $inventory_quantity['left_quantity'] . "' where products_id = '" . (int) $this->prid . "'");
            \common\helpers\Warehouses::update_sum_of_inventory_quantity((int) $this->prid);
            $this->update_product_stock = false;
        }
    }
    public function re_calculate_stock_product()
    {
        if (is_object($this->parent_object)) {
            $this->parent_object->initiate_after_save('Product::doCache');
        }
    }
}