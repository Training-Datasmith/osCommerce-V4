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
namespace Osc_Link\XML;

use yii\base\Model;
use yii\db\Active_Record;
interface Template
{
    public function set_variable($name, $var);
    public function get_html($template);
}
class Related_Serialize
{
    protected $_configure_map = [];
    protected $models_compare_pk = [];
    protected $import_tuning = null;
    public function set_configure_map($configure_map)
    {
        $this->_configure_map = $configure_map;
        if (isset($this->_configure_map['importTuning']) && is_object($this->_configure_map['importTuning']) && $this->_configure_map['importTuning'] instanceof Import_Tuning_Interface) {
            $this->import_tuning = $this->_configure_map['importTuning'];
        }
    }
    public function import($filename)
    {
        $result = ['new' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0];
        $xml_parser = new Xm_Lto_Data_Parser();
        $xml_parser->set_configure_map($this->_configure_map['Data']);
        $xml_parser->parse_file($filename);
        $process_configure = [];
        foreach ($this->_configure_map['Data'] as $process_model => $_process_configure) {
            $process_configure = $_process_configure;
            $collect_tag = '/data/' . str_replace('>', '/', $process_configure['xmlCollection']);
            $xml_parser->set_collect_path($collect_tag);
            break;
        }
        while ($data = $xml_parser->read()) {
            if (is_object($data) && $data instanceof Io_Data) {
                if ($data->is_importable($process_model)) {
                    $res = $this->import_model($process_model, $data, $process_configure);
                    $result[$res] += 1;
                    if (isset($this->import_tuning)) {
                        $this->import_tuning->after_import_entity($process_model, $data, $res);
                    }
                }
            }
        }
        return $result;
    }
    private function is_skipped($process_model, $data)
    {
        if ($data->skipped) {
            \Osc_Link\Logger::get()->log_record($process_model::tablename(), 'was skipped for the following reason(s): ' . implode(', ', $data->skip_reasons));
        }
        return $data->skipped;
    }
    // get data considering renamed
    private static function get_property_data($property_name, $data, $process_configure)
    {
        if (isset($process_configure['properties'][$property_name]['renamed'])) {
            if (!isset($data->data[$process_configure['properties'][$property_name]['renamed']])) {
                \Osc_Link\Logger::printf('Column %s must be renamed to %s but it is not found', $property_name, $process_configure['properties'][$property_name]['renamed']);
            }
            return $data->data[$process_configure['properties'][$property_name]['renamed']] ?? null;
        } else {
            return $data->data[$property_name] ?? null;
        }
    }
    public function import_model($process_model, $data, $process_configure, $parent_object = null)
    {
        $process_obj = false;
        $data->skipped = false;
        if (isset($process_configure['importFind']) && is_callable($process_configure['importFind'])) {
            $update_object = call_user_func_array($process_configure['importFind'], [$data, $parent_object]);
        }
        if (is_object($process_model) && $process_model instanceof \yii\db\Active_Record) {
            $update_object = $process_model;
            $process_model = $update_object->class_name();
        } else {
            $process_obj = \Yii::create_object($process_model);
            $lookup_by_pk = [];
            foreach (array_keys($process_obj->get_primary_key(true)) as $property) {
                if ($data->data['@' . $property] ?? null) {
                    $lookup_by_pk[$property] = $data->data['@' . $property];
                } elseif (isset($data->data[$property])) {
                    if (is_object($data->data[$property]) && $data->data[$property] instanceof Io_Map) {
                        //$ableConfig = is_array($processConfigure) && isset($processConfigure['properties'][$property]);
                        $data->data[$property]->table = $process_configure['properties'][$property]['table'] ?? $process_model::table_name();
                        $data->data[$property]->attribute = $process_configure['properties'][$property]['attribute'] ?? $property;
                        $lookup_by_pk[$property] = self::get_property_data($property, $data, $process_configure)->to_import_model();
                        if ($data->data[$property] instanceof Io_Language_Map && empty($lookup_by_pk[$property])) {
                            $data->skipped = true;
                            $data->skip_reasons[] = 'The language "' . $data->data[$property]->language . '" is not installed (externalId=' . $data->data[$property]->external_id . ')';
                        }
                    } elseif (is_object($data->data[$property]) && $data->data[$property] instanceof Complex) {
                        $lookup_by_pk[$property] = $data->data[$property]->to_import_model();
                    } else {
                        $lookup_by_pk[$property] = $data->data[$property];
                    }
                }
            }
            if (!count($lookup_by_pk) == 0 || array_search(null, $lookup_by_pk, true) === false) {
                $update_object = $process_model::find_one($lookup_by_pk);
            }
        }
        //        if ($data->skipped) {
        //            \OscLink\Logger::get()->log_record($processModel::tablename(), 'was skipped for the following reason(s): ' . implode(', ', $data->skipReasons));
        //            return 'skipped';
        //        }
        if ($this->is_skipped($process_model, $data)) {
            return 'skipped';
        }
        /**
         * @var $updateObject \yii\db\ActiveRecord
         */
        if ($update_object) {
            // fill all related collections
            if (isset($process_configure['withRelated']) && is_array($process_configure['withRelated'])) {
                foreach ($process_configure['withRelated'] as $collection_property => $collection_config) {
                    $_dummy_array = $update_object->{$collection_property};
                    unset($_dummy_array);
                }
            }
        } else {
            $update_object = is_object($process_obj) ? $process_obj : \Yii::create_object($process_model);
            $update_object->load_default_values();
        }
        $is_inserted_new_record = $update_object->is_new_record;
        $update_object->detach_behaviors();
        if (is_object($parent_object) && $update_object->can_set_property('parentObject')) {
            $update_object->parent_object = $parent_object;
        }
        /**
         * @var $updateObject \yii\db\ActiveRecord
         */
        // {{ populate data in model
        foreach ($data->data as $property => $property_value) {
            if ($update_object->has_attribute($property)) {
            } elseif ($update_object->can_set_property($property)) {
            } else {
                continue;
            }
            if (is_object($property_value)) {
                if ($property_value instanceof Io_Attachment) {
                    $property_value = $property_value->to_import_model();
                } elseif ($property_value instanceof Complex) {
                    $property_value = $property_value->to_import_model();
                }
            }
            if ($data->data[$property] instanceof Io_Order_Status && empty($property_value)) {
                $data->skipped = true;
                $data->skip_reasons[] = 'The order status "' . $data->data[$property]->name . '" will be skipped for order #' . ($data->data['orders_id']->internal_id ?? null);
                break;
            }
            $property_value = $this->cast_input_value($update_object, $property, $property_value);
            $update_object->{$property} = $property_value;
        }
        // }} populate data in model
        if ($this->is_skipped($process_model, $data)) {
            return 'skipped';
        }
        if (isset($process_configure['beforeImportSave']) && is_callable($process_configure['beforeImportSave'])) {
            call_user_func_array($process_configure['beforeImportSave'], [$update_object, $data]);
        }
        if (isset($this->import_tuning)) {
            $this->import_tuning->before_import_save($update_object, $data);
        }
        try {
            $update_object->save(false);
        } catch (\Exception $e) {
            \Osc_Link\Logger::get()->log_record($update_object, 'error while ' . ($is_inserted_new_record ? 'added' : 'updated') . ' - ' . $e->get_message());
            return 'error';
        }
        $update_object->refresh();
        $table_schema = $update_object->get_table_schema();
        //echo '<pre>'; var_dump($updateObject->getPrimaryKey(true)); echo '</pre>';
        // {{ pass new AutoInc id to mapping
        foreach ($table_schema->primary_key as $name) {
            if ($table_schema->columns[$name]->auto_increment) {
                $property_value = self::get_property_data($name, $data, $process_configure);
                if (is_object($property_value) && $property_value instanceof Io_Map) {
                    $property_value->after_import_model($update_object->{$name});
                }
            }
        }
        // }} pass new AutoInc id to mapping
        // current model processed
        if (isset($process_configure['withRelated']) && is_array($process_configure['withRelated'])) {
            foreach ($process_configure['withRelated'] as $collection_property => $collection_config) {
                if (!isset($data->data[$collection_property]) || !is_object($data->data[$collection_property]) || !$data->data[$collection_property] instanceof Io_Data_Related) {
                    continue;
                }
                $imported_collection = $data->data[$collection_property]->data;
                // {{ load up current dp data & remember existing refs
                $related_collection = false;
                if (!$is_inserted_new_record) {
                    $related_collection = $update_object->{$collection_property};
                }
                if (!is_array($related_collection)) {
                    $related_collection = [];
                } else {
                    // {{ set parent object for import
                    foreach ($related_collection as $related_model) {
                        if (!$related_model->can_set_property('parentObject')) {
                            break;
                        }
                        $related_model->parent_object = $update_object;
                    }
                    // }} set parent object for import
                }
                $related_removal_keys = array_flip(array_keys($related_collection));
                // }} load up current dp data & remember existing refs
                $related_collection_active_query_getter = 'get' . ucfirst($collection_property);
                /**
                 * @var $relatedActiveQuery \yii\db\ActiveQuery
                 */
                $related_active_query = $update_object->{$related_collection_active_query_getter}();
                unset($related_collection_active_query_getter);
                // {{ make $relatedObject as model skel
                /**
                 * @var $relatedObject \yii\db\ActiveRecord
                 */
                $related_object = \Yii::create_object($related_active_query->model_class);
                $related_object->load_default_values(false);
                // }} make $relatedObject as model skel
                if (!isset($this->models_compare_pk[$related_active_query->model_class])) {
                    $related_pk_keys = $related_object->get_primary_key(true);
                    $compare_pk_keys = array_keys($related_pk_keys);
                    /*
                                        if ( isset($relatedActiveQuery->link) && is_array($relatedActiveQuery->link) ) {
                                            $comparePkKeys = array_diff($comparePkKeys, array_values($relatedActiveQuery->link));
                                        }
                    */
                    $this->models_compare_pk[$related_active_query->model_class] = $compare_pk_keys;
                }
                //'link array map to related import data';
                foreach ($imported_collection as $idx => $record_data) {
                    /**
                     * @var IOData $recordData
                     */
                    if (isset($related_active_query->link) && is_array($related_active_query->link)) {
                        foreach ($related_active_query->link as $relate_key => $parent_key) {
                            if ($update_object->has_attribute($parent_key)) {
                                $imported_collection[$idx]->data[$relate_key] = $update_object->{$parent_key};
                            }
                        }
                    }
                }
                // find match in related collection
                foreach ($imported_collection as $idx => $imported_io_data) {
                    $matched_idx_in_db_collection = false;
                    foreach ($related_collection as $db_idx => $database_record) {
                        if (!isset($related_removal_keys[$db_idx])) {
                            continue;
                        }
                        if ($this->collection_model_compare($database_record, $imported_io_data)) {
                            $matched_idx_in_db_collection = $db_idx;
                        }
                        if ($matched_idx_in_db_collection !== false) {
                            break;
                        }
                    }
                    if ($matched_idx_in_db_collection !== false) {
                        $this->import_model($related_collection[$matched_idx_in_db_collection], $imported_io_data, $collection_config, $update_object);
                        unset($related_removal_keys[$matched_idx_in_db_collection]);
                        unset($imported_collection[$idx]);
                    }
                    //echo '<pre>??MATCH '; var_dump($matchedIdxInDbCollection); echo '</pre>';
                }
                // {{ remove not processed ActiveRecords
                foreach ($related_removal_keys as $related_removal_key) {
                    //echo '$relatedCollection[$relatedRemovalKey]->delete()';
                    $related_collection[$related_removal_key]->delete();
                }
                // }} remove not processed ActiveRecords
                // {{ insert new records
                foreach ($imported_collection as $import_data) {
                    $this->import_model($related_active_query->model_class, $import_data, $collection_config, $update_object);
                }
                // }} insert new records
            }
        }
        if (isset($process_configure['afterImport']) && is_callable($process_configure['afterImport'])) {
            call_user_func_array($process_configure['afterImport'], [$update_object, $data]);
        }
        if (isset($this->import_tuning)) {
            $this->import_tuning->after_import($update_object, $data, $is_inserted_new_record);
        }
        return $is_inserted_new_record ? 'new' : 'updated';
    }
    protected function cast_input_value(\yii\db\Active_Record $record, $attribute, $input_value)
    {
        $property_value = $input_value;
        try {
            $schema_columns = $record->get_table_schema()->columns;
            if (!is_array($schema_columns)) {
                $schema_columns = [];
            }
        } catch (\yii\base\Invalid_Config_Exception $ex) {
            $schema_columns = [];
        }
        // {{ some type cast using db schema
        if (isset($schema_columns[$attribute])) {
            $table_column = $schema_columns[$attribute];
            /**
             * @var $tableColumn \yii\db\ColumnSchema
             */
            if ($property_value === '' && in_array($table_column->php_type, ['integer', 'boolean', 'double'])) {
                $property_value = 0;
            }
            if ($property_value !== '' && !is_null($property_value) && !is_object($property_value) && !is_array($property_value)) {
                $property_value = $table_column->php_typecast($property_value);
            }
            if (is_null($property_value) && !$table_column->allow_null) {
                if (is_null($table_column->default_value)) {
                    $property_value = !is_null($table_column->php_typecast('')) ? $table_column->php_typecast('') : $table_column->php_typecast(0);
                } else {
                    $property_value = $table_column->default_value;
                }
            }
            if (($table_column->type === 'decimal' || $table_column->type === 'float') && is_string($property_value) && strlen($property_value) !== 0) {
                $dot_position = strpos($property_value, '.');
                if ($dot_position === false) {
                    $property_value = number_format((float) $property_value, $table_column->scale, '.', '');
                } else {
                    $input_value_scale = strlen($property_value) - $dot_position - 1;
                    if ($input_value_scale > $table_column->scale) {
                        $property_value = number_format((float) $property_value, $table_column->scale, '.', '');
                    } elseif ($input_value_scale < $table_column->scale) {
                        $property_value = number_format((float) $property_value, $table_column->scale, '.', '');
                    }
                }
            }
        }
        // }} some type cast using db schema
        return $property_value;
    }
    protected function collection_model_compare(\yii\db\Active_Record $database_record, Io_Data $imported_record)
    {
        $same = false;
        $use_attribute_match = true;
        $model_class = get_class($database_record);
        $compare_pk_keys = isset($this->models_compare_pk[$model_class]) ? $this->models_compare_pk[$model_class] : [];
        if (count($compare_pk_keys) > 0) {
            $all_pk_match = true;
            foreach ($compare_pk_keys as $related_pk_key) {
                if (!isset($imported_record->data[$related_pk_key])) {
                    $all_pk_match = false;
                    break;
                }
                $imported_value = $imported_record->data[$related_pk_key];
                if (is_object($imported_value)) {
                    if ($imported_value instanceof Complex) {
                        $imported_value = $imported_value->to_import_model();
                    } else {
                        $imported_value = (string) $imported_value;
                    }
                }
                if ($database_record->{$related_pk_key} != $imported_value) {
                    $all_pk_match = false;
                    break;
                }
            }
            if ($all_pk_match) {
                $same = true;
                $use_attribute_match = false;
            }
        }
        if ($use_attribute_match) {
            $match_by_attributes = true;
            foreach ($imported_record->data as $import_attribute => $import_value) {
                if ($database_record->has_attribute($import_attribute)) {
                    if (is_object($import_value)) {
                        if ($import_value instanceof Complex) {
                            $import_value = $import_value->to_import_model();
                        } else {
                            $import_value = (string) $import_value;
                        }
                    }
                    $import_value = $this->cast_input_value($database_record, $import_attribute, $import_value);
                    if ($database_record->{$import_attribute} !== $import_value) {
                        $match_by_attributes = false;
                        break;
                    }
                }
            }
            if ($match_by_attributes) {
                $same = true;
            }
        }
        return $same;
    }
    /**
     *
     */
    public function export(Xml_Writer $writer)
    {
        $writer->export_begin(isset($this->_configure_map['Header']) ? $this->_configure_map['Header'] : []);
        foreach (array_keys($this->_configure_map['Data']) as $model_class) {
            //$object = Yii::createObject($modelClass);
            $collection_config = $this->_configure_map['Data'][$model_class];
            $data = $this->export_collection($model_class::find(), $collection_config, $writer);
            unset($data);
            //$IOProject->exportData($data);
        }
        $writer->export_end();
    }
    public function export_collection(\yii\db\Active_Query $collection, array $collection_config, $writer = null, $parent_object = null)
    {
        $data = new Io_Data_Related();
        $data->meta = $collection_config;
        //$collection->limit(2);
        if (isset($collection_config['softGroup']) && !empty($collection_config['softGroup']['column'])) {
            $group_column = $collection_config['softGroup']['column'];
            $collection->select($group_column)->distinct()->order_by($group_column);
        }
        if (!empty($collection_config['where'])) {
            $collection->and_where($collection_config['where']);
        }
        if (!empty($collection_config['orderBy'])) {
            $collection->order_by($collection_config['orderBy']);
        }
        foreach ($collection->batch(200) as $records) {
            foreach ($records as $record) {
                /**
                 * @var $record ActiveRecord
                 */
                if (is_object($parent_object) && $record->can_set_property('parentObject')) {
                    $record->parent_object = $parent_object;
                }
                if (is_object($writer)) {
                    $writer->export_data($this->export_model($record, $collection_config));
                } else {
                    $data->data[] = $this->export_model($record, $collection_config);
                }
            }
        }
        return $data;
    }
    public function export_model(\yii\db\Active_Record $record, $record_config)
    {
        $data = new Io_Data();
        $data->meta = $record_config;
        /**
         * @var $record \yii\db\BaseActiveRecord
         */
        if (!isset($record_config['properties']) || !is_array($record_config['properties'])) {
            $record_config['properties'] = [];
        }
        $export_properties = [];
        //$tableSchema = $record->getTableSchema();
        /**
         * @var $tableSchema yii\db\TableSchema
         */
        $primary_keys = $record->get_primary_key(true);
        $model_attributes = $record->attributes();
        $described_attributes = isset($record_config['properties']) && is_array($record_config['properties']) ? array_keys($record_config['properties']) : [];
        $unknown = array_diff($described_attributes, $model_attributes);
        foreach ($unknown as $unknown_attribute) {
            if (isset($record_config['properties'][$unknown_attribute]) && $record_config['properties'][$unknown_attribute] === false) {
                continue;
            }
            if ($record->can_get_property($unknown_attribute)) {
                $model_attributes[] = $unknown_attribute;
            }
        }
        foreach ($model_attributes as $attribute) {
            if (isset($record_config['hideProperties']) && in_array($attribute, $record_config['hideProperties'])) {
                continue;
            }
            // hide relation
            $attribute_value = $record->{$attribute};
            if (isset($record_config['properties'][$attribute])) {
                $property_mapper = $record_config['properties'][$attribute];
                if ($property_mapper === false) {
                    continue;
                    // hide
                } elseif (is_string($property_mapper) && !empty($property_mapper)) {
                    $attribute = $property_mapper;
                    // rename
                } elseif (is_array($property_mapper)) {
                    if (!empty($property_mapper['rename'])) {
                        $attribute = $property_mapper['rename'];
                    }
                    if (empty($property_mapper['table'])) {
                        $property_mapper['table'] = $record::table_name();
                    }
                    if (empty($property_mapper['attribute'])) {
                        $property_mapper['attribute'] = $attribute;
                    }
                    $property_mapper['value'] = $attribute_value;
                    if (isset($property_mapper['record']) && $property_mapper['record'] === true) {
                        $property_mapper['record'] = $record;
                    }
                    $attribute_value = Io_Core::create_object($property_mapper);
                }
            } elseif (array_key_exists($attribute, $primary_keys)) {
                $attribute_value = Io_Core::create_object(['class' => 'IOPK', 'table' => $record::table_name(), 'attribute' => $attribute, 'value' => $attribute_value]);
            }
            $export_properties[$attribute] = $attribute_value;
        }
        $data->data = $export_properties;
        $data->export_pk = [];
        foreach (array_keys($primary_keys) as $pk_attribute) {
            if (array_key_exists($pk_attribute, $export_properties)) {
                $data->export_pk[] = $pk_attribute;
            }
        }
        if (isset($record_config['withRelated'])) {
            foreach ($record_config['withRelated'] as $collection_property => $collection_config) {
                $relate_active_query_getter = 'get' . ucfirst($collection_property);
                if (!$record->has_method($relate_active_query_getter)) {
                    continue;
                }
                $related_active_query = $record->{$relate_active_query_getter}();
                /**
                 * @var $relatedActiveQuery \yii\db\ActiveQuery
                 */
                if (isset($related_active_query->link) && is_array($related_active_query->link)) {
                    if (!isset($collection_config['hideProperties'])) {
                        $collection_config['hideProperties'] = [];
                    }
                    foreach ($related_active_query->link as $rel_prop => $current_prop) {
                        //$collectionConfig['hideProperties'][] = $relProp;
                        if (!in_array($current_prop, $data->export_pk)) {
                            unset($data->data[$current_prop]);
                        }
                    }
                    //$collectionConfig['hideProperties'] = array_merge($collectionConfig['hideProperties'],array_keys($relatedActiveQuery->link));
                }
                //$relatedClass = $relatedActiveQuery->modelClass;
                $data->data[$collection_property] = $this->export_collection($related_active_query, $collection_config, null, $record);
            }
        }
        return $data;
    }
    private function get_feed_model()
    {
        foreach ($this->_configure_map['Data'] as $process_model => $process_configure) {
            break;
        }
        return $process_model;
    }
    private function get_entity_name()
    {
        $process_model = $this->get_feed_model();
        $pk = $process_model::get_table_schema()->primary_key;
        //\common\helpers\Assert::assert(count($pk) == 1);
        return sprintf('%s.%s', $process_model::tablename(), $pk[0]);
    }
    private function get_entity_id()
    {
        return \common\extensions\Osc_Link\models\Entity::find_one(['project_id' => 1, 'entity_name' => $this->get_entity_name()])->id ?? null;
    }
    public function clean()
    {
        $result = ['mapped' => 0, 'deleted' => 0, 'not_found' => 0, 'deleted_related' => 0, 'error' => 0];
        $entity_id = $this->get_entity_id();
        if (empty($entity_id)) {
            \Osc_Link\Logger::get()->log('The entity name was not found: ' . $this->get_entity_name());
            return $result;
        }
        $map_query = \common\extensions\Osc_Link\models\Mapping::find()->where(['entity_id' => $entity_id]);
        $result['mapped'] = $map_query->count();
        $model_name = $this->get_feed_model();
        $model = \Yii::create_object($model_name);
        $model_config = $this->_configure_map['Data'][$model_name];
        $pk = $model::get_table_schema()->primary_key[0];
        $before_delete = $model_config['beforeDelete'] ?? null;
        \common\helpers\Assert::assert(!is_null($before_delete) || count($model::get_table_schema()->primary_key) == 1, "Could not auto delete {$model_name}");
        $related_methods = [];
        if (isset($model_config['withRelated'])) {
            foreach ($model_config['withRelated'] as $collection_property => $collection_config) {
                $relate_active_query_getter = 'get' . ucfirst($collection_property);
                \common\helpers\Assert::has_method($model, $relate_active_query_getter);
                $related_methods[] = $relate_active_query_getter;
            }
        }
        $num_current = 1;
        $num_count = $map_query->count();
        foreach ($map_query->each() as $map) {
            $res = null;
            \Osc_Link\Logger::add_prefix(sprintf('%s(%d)', $model::tablename(), $map->internal_id));
            try {
                \Osc_Link\Logger::print('Deleting started');
                $row = $model::find_one([$pk => $map->internal_id]);
                if (empty($row)) {
                    $res = 'not_found';
                    \Osc_Link\Logger::get()->log('ID was not found (probably was deleted before)');
                    continue;
                }
                $transaction = is_callable($before_delete) ? null : \Yii::$app->db->begin_transaction();
                try {
                    // delete via beforeDelete func
                    if (is_callable($before_delete)) {
                        $res = call_user_func_array($before_delete, [$row, $map->internal_id]);
                        \Osc_Link\Logger::printf('Deleted in custom function: %s', $res);
                        // auto delete
                    } else {
                        foreach ($related_methods as $get_query) {
                            $related_query = $row->{$get_query}();
                            $related_pk = [];
                            if (!empty($related_query->model_class)) {
                                $related_model = \Yii::create_object($related_query->model_class);
                                $related_pk = $related_model::get_table_schema()->primary_key;
                            }
                            foreach ($related_query->each() as $related_row) {
                                $related_id = '';
                                foreach ($related_pk as $key) {
                                    if (!empty($related_id)) {
                                        $related_id .= '-';
                                    }
                                    $related_id .= $related_row->{$key};
                                }
                                \Osc_Link\Logger::printf('Deleting related %s(%d)', $get_query, $related_id);
                                $related_row->delete();
                                \Osc_Link\Logger::printf('Deleted related %s(%d)', $get_query, $related_id);
                                $result['deleted_related']++;
                            }
                        }
                        $row->delete();
                        \Osc_Link\Logger::print('Deleted');
                        $res = 'deleted';
                    }
                    if (isset($this->import_tuning)) {
                        $this->import_tuning->after_clean($row, $map->internal_id, $res);
                    }
                    if (!is_null($transaction)) {
                        $transaction->commit();
                    }
                } catch (\Throwable $e) {
                    if (!is_null($transaction)) {
                        $transaction->roll_back();
                    }
                    $res = 'error';
                    $msg = sprintf('Error while cleaning %s(%d): %s', $model_name, $map->internal_id, $e->get_message());
                    \Osc_Link\Logger::print($msg);
                    \Yii::warning("{$msg}\n" . $e->get_trace_as_string());
                }
            } finally {
                \Osc_Link\Logger::clear_prefix();
                \common\helpers\Assert::key_exists($result, $res);
                $result[$res]++;
                if (isset($this->import_tuning)) {
                    $this->import_tuning->after_clean_entity($model, $map->internal_id, $res);
                }
            }
            \Osc_Link\Progress::Percent(intval(100 * $num_current++ / $num_count));
        }
        return $result;
    }
}