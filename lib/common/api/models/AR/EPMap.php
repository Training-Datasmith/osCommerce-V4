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
namespace common\api\models\AR;

use yii\base\Invalid_Config_Exception;
use yii\db\Active_Record;
use yii\helpers\Inflector;
class Ep_Map extends Active_Record
{
    protected $hide_fields = [];
    public $pending_removal = false;
    protected $after_save_hooks = [];
    // map methods
    protected $_after_save_hooks = [];
    // set for call
    protected $child_collections = [];
    protected $indexed_collections = [];
    protected $indexed_collections_append_flag = [];
    protected $loaded_collections = [];
    protected $model_flags = [];
    public function __construct(array $config = [])
    {
        $init_array = [];
        if (count($config) > 0) {
            $fields = array_flip($this->attributes());
            foreach (array_keys($config) as $field) {
                if (isset($fields[$field])) {
                    $init_array[$field] = $config[$field];
                }
            }
        }
        parent::__construct($init_array);
    }
    public function get_possible_keys()
    {
        $keys = array_merge($this->attributes(), $this->custom_fields());
        if (count($this->hide_fields) > 0) {
            $keys = array_diff($keys, $this->hide_fields);
        }
        return $keys;
    }
    public function to_array(array $fields = [], array $expand = [], $recursive = true)
    {
        if (count($this->hide_fields) > 0) {
            // {{ allow force export hidden fields
            $entity_fields = @array_merge($this->attributes(), $this->custom_fields());
            if (count($fields) > 0) {
                $fields = @array_intersect($entity_fields, $fields);
            } else {
                $fields = @array_diff($entity_fields, $this->hide_fields);
            }
            // }}
        }
        return parent::to_array($fields, $expand, $recursive);
    }
    public function indexed_collection_append_mode($collection_name, $append_append = true)
    {
        if (isset($this->indexed_collections[$collection_name])) {
            $this->indexed_collections_append_flag[$collection_name] = $append_append;
        }
    }
    public function initiate_after_save($action)
    {
        $this->_after_save_hooks[$action] = $action;
    }
    public function set_model_flag($flag_name, $flag_value)
    {
        $this->model_flags[$flag_name] = $flag_value;
    }
    public function before_save($insert)
    {
        if ($insert) {
            // {{ fix SQL ERROR insert NULL into non null column
            $schema_columns = $this->get_table_schema()->columns;
            if (is_array($schema_columns)) {
                foreach ($schema_columns as $column_name => $table_column) {
                    /**
                     * @var $tableColumn \yii\db\ColumnSchema
                     */
                    if (!$table_column->allow_null && $table_column->db_typecast($this->{$column_name}) === null) {
                        if (is_null($table_column->default_value)) {
                            $this->set_attribute($column_name, !is_null($table_column->php_typecast('')) ? $table_column->php_typecast('') : $table_column->php_typecast(0));
                        } else {
                            $this->set_attribute($column_name, $table_column->default_value);
                        }
                    }
                }
            }
            // }} fix SQL ERROR insert NULL into non null column
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        foreach ($this->child_collections as $collection_name => $collection) {
            if (!is_array($collection)) {
                continue;
            }
            if ($insert) {
                //$newPrimaryValues = $this->getPrimaryKey(true);
                foreach ($collection as $collection_item) {
                    /**
                     * @var EPMap $collectionItem
                     */
                    $collection_item->parent_ep_map($this);
                    /*foreach ( $newPrimaryValues as $key=>$val ) {
                          if ( $collectionItem->hasAttribute($key) ) $collectionItem->setAttribute($key, $val);
                      }*/
                    if ($collection_item->pending_removal) {
                        continue;
                    }
                    $collection_item->insert();
                }
            } else {
                foreach ($collection as $idx => $collection_item) {
                    $collection_item->parent_ep_map($this);
                    if ($collection_item->pending_removal) {
                        $collection_item->delete();
                        unset($collection[$idx]);
                    } else if ($collection_item->is_new_record) {
                        $collection_item->insert();
                    } else {
                        $collection_item->update();
                    }
                }
            }
        }
        foreach (array_keys($this->_after_save_hooks) as $hook_method) {
            if (!isset($this->after_save_hooks[$hook_method])) {
                continue;
            }
            $method = $this->after_save_hooks[$hook_method];
            if ($this->has_method($method)) {
                call_user_func_array([$this, $method], []);
            }
        }
    }
    public function export_array(array $fields = [])
    {
        $data = $this->to_array($fields, [], false);
        if (count($fields) == 0) {
            foreach (array_keys($this->child_collections) as $child_key) {
                $fields[$child_key]['*'] = [];
            }
        }
        foreach (array_keys($this->child_collections) as $collection_name) {
            if (!isset($fields[$collection_name])) {
                continue;
            }
            $child_fields = $fields[$collection_name];
            $filter_child = isset($child_fields['*']) ? $child_fields['*'] : [];
            $method_name = 'initCollectionByLookupKey_' . Inflector::id2camel($collection_name, '_');
            if (method_exists($this, $method_name)) {
                call_user_func_array([$this, $method_name], [array_keys($child_fields)]);
            }
            if (is_array($this->child_collections[$collection_name])) {
                $data[$collection_name] = [];
            }
            foreach ($this->child_collections[$collection_name] as $export_key => $child_ar) {
                $filter_export_child = $filter_child;
                if (isset($child_fields[$export_key])) {
                    $filter_export_child = array_merge($filter_child, $child_fields[$export_key]);
                } elseif (!isset($child_fields['*'])) {
                    continue;
                }
                if (in_array('*', $filter_export_child)) {
                    $filter_export_child = [];
                }
                $data[$collection_name][$export_key] = $child_ar->export_array($filter_export_child);
            }
        }
        return $data;
    }
    public function import_array($data)
    {
        if (!is_array($data)) {
            return false;
        }
        try {
            $schema_columns = $this->get_table_schema()->columns;
            if (!is_array($schema_columns)) {
                $schema_columns = [];
            }
        } catch (Invalid_Config_Exception $ex) {
            $schema_columns = [];
        }
        foreach ($data as $key => $value) {
            if ($this->has_attribute($key)) {
                // {{ some type cast using db schema
                if (isset($schema_columns[$key])) {
                    if ($value === '' && in_array($schema_columns[$key]->php_type, ['integer', 'boolean', 'double'])) {
                        $value = 0;
                    }
                    if ($value !== '' && !is_null($value) && !is_object($value) && !is_array($value)) {
                        $value = $schema_columns[$key]->php_typecast($value);
                    }
                }
                // }} some type cast using db schema
                $this->set_attribute($key, $value);
            } elseif (isset($this->child_collections[$key])) {
                $method_name = 'initCollectionByLookupKey_' . Inflector::id2camel($key, '_');
                if (method_exists($this, $method_name)) {
                    call_user_func_array([$this, $method_name], [['*']]);
                }
                if ($key == 'warehouses_products') {
                    foreach ($this->child_collections[$key] as $import_key => $child_ar) {
                        if (isset($value[$import_key]) && is_array($value[$import_key])) {
                            $child_ar->import_array($value[$import_key]);
                            unset($value[$import_key]);
                        } else {
                            $child_ar->pending_removal = true;
                        }
                    }
                    if (count($value) > 0) {
                        foreach ($value as $import_key => $import_data) {
                            $instance = \Yii::create_object(['class' => $this->indexed_collections[$key], 'keyCode' => $import_key]);
                            /**
                             * @var $instance EPMap
                             */
                            $instance->load_default_values();
                            $instance->parent_ep_map($this);
                            if (!$instance->import_array($import_data)) {
                                continue;
                            }
                            if (method_exists($instance, 'getKeyCode')) {
                                if (isset($this->child_collections[$key][$instance->get_key_code()])) {
                                    $this->child_collections[$key][$instance->get_key_code()]->import_array($import_data);
                                    $this->child_collections[$key][$instance->get_key_code()]->pending_removal = false;
                                } else {
                                    $this->child_collections[$key][$instance->get_key_code()] = $instance;
                                }
                            } else {
                                $this->child_collections[$key][] = $instance;
                            }
                        }
                    }
                } elseif (isset($this->indexed_collections[$key])) {
                    foreach ($this->child_collections[$key] as $current_idx => $child_ar) {
                        $child_ar->pending_removal = true;
                    }
                    $matched_idx_list = [];
                    if (is_array($value)) {
                        foreach ($value as $indexed_value) {
                            $instance = \Yii::create_object($this->indexed_collections[$key]);
                            $instance->parent_ep_map($this);
                            if (!$instance->import_array($indexed_value)) {
                                continue;
                            }
                            $match_current_ar = false;
                            foreach ($this->child_collections[$key] as $current_idx => $child_ar) {
                                if (isset($matched_idx_list[$current_idx])) {
                                    continue;
                                }
                                /**
                                 * @var EPMap $childAR
                                 */
                                if ($child_ar->match_indexed_value($instance)) {
                                    $matched_idx_list[$current_idx] = $current_idx;
                                    //                                echo '<pre>'; var_dump($indexedValue); echo '</pre>';
                                    // {{
                                    $child_ar->pending_removal = false;
                                    $child_ar->parent_ep_map($this);
                                    $child_ar->import_array($indexed_value);
                                    // }}
                                    $match_current_ar = true;
                                    break;
                                }
                            }
                            if (!$match_current_ar) {
                                $this->child_collections[$key][] = $instance;
                            }
                        }
                    }
                    if (isset($this->indexed_collections_append_flag[$key]) && $this->indexed_collections_append_flag[$key]) {
                        foreach ($this->child_collections[$key] as $current_idx => $child_ar) {
                            $child_ar->pending_removal = false;
                        }
                    }
                } else {
                    $import_data_array = isset($value['*']) ? $value['*'] : [];
                    foreach ($this->child_collections[$key] as $import_key => $child_ar) {
                        if (isset($value[$import_key]) && is_array($value[$import_key])) {
                            if (count($import_data_array) > 0) {
                                $child_ar->import_array(array_replace_recursive($import_data_array, $value[$import_key]));
                            } else {
                                $child_ar->import_array($value[$import_key]);
                            }
                        } elseif (count($import_data_array) > 0) {
                            $child_ar->import_array($import_data_array);
                        }
                    }
                }
            }
        }
        return true;
    }
    public function custom_fields()
    {
        return [];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        return false;
    }
    public function get_dirty_attributes($names = null)
    {
        $dirty_attributes = parent::get_dirty_attributes($names);
        if ($this->is_new_record) {
            return $dirty_attributes;
        }
        foreach ($dirty_attributes as $column => $new_value) {
            if (is_null($new_value) || is_null($this->get_old_attribute($column))) {
            } elseif (!$this->is_attribute_changed($column, false)) {
                unset($dirty_attributes[$column]);
            }
        }
        return $dirty_attributes;
    }
    public function get_child_collection_names()
    {
        return array_keys($this->child_collections);
    }
    public function refresh()
    {
        $this->_after_save_hooks = [];
        foreach (array_keys($this->child_collections) as $collection_name) {
            if (isset($this->indexed_collections[$collection_name])) {
                $this->child_collections[$collection_name] = false;
            } else {
                $this->child_collections[$collection_name] = [];
            }
        }
        return parent::refresh();
    }
    public function is_modified()
    {
        $modified = false;
        if (!$this->is_new_record) {
            $dirty_list = $this->get_dirty_attributes();
            if (count($dirty_list) > 0) {
                $modified = true;
            } else {
                foreach ($this->child_collections as $child_collection_name => $child_a_rs) {
                    if (is_array($child_a_rs) && count($child_a_rs) > 0) {
                        foreach ($child_a_rs as $child_ar) {
                            if ($child_ar->pending_removal || $child_ar->is_new_record || $child_ar->is_modified()) {
                                $modified = true;
                                break;
                            }
                        }
                    }
                    if ($modified) {
                        break;
                    }
                }
            }
        }
        return $modified;
    }
}