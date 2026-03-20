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
namespace common\api\models;

use yii\base\Behavior;
class Data_Map_Behavior extends Behavior
{
    public $related = [];
    protected $datetime_fields = [];
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
    public function populate_ar($data)
    {
        foreach ($data as $property => $property_value) {
            if (!$this->owner->has_attribute($property) || !$this->owner->can_set_property($property)) {
                continue;
            }
            $property_value = $this->cast_input_value($this->owner, $property, $property_value);
            $this->owner->{$property} = $property_value;
        }
    }
    public function populate_object($obj)
    {
        /*        if ( count($this->related)>0 ) {
                      foreach ($this->related as $children){
                          echo '<pre>'; var_dump($this->owner->{$children}); echo '</pre>';
                      }
                  }*/
        foreach (get_object_vars($obj) as $property => $_dummy) {
            if (!$this->owner->has_attribute($property) || !$this->owner->can_get_property($property)) {
                continue;
            }
            $obj->{$property} = $this->owner->{$property};
        }
    }
    public function get_changed_attributes($names = null)
    {
        $dirty_attributes = $this->owner->get_dirty_attributes($names);
        if ($this->owner->is_new_record) {
            return $dirty_attributes;
        }
        foreach ($dirty_attributes as $column => $new_value) {
            if (is_null($new_value) || is_null($this->owner->get_old_attribute($column))) {
            } elseif (!$this->owner->is_attribute_changed($column, false)) {
                unset($dirty_attributes[$column]);
            }
        }
        return $dirty_attributes;
    }
    public function is_modified()
    {
        $modified = false;
        if (!$this->owner->is_new_record) {
            $dirty_list = $this->get_dirty_attributes();
            if (count($dirty_list) > 0) {
                $modified = true;
            }
        }
        return $modified;
    }
}