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

class Property extends Abstract_Class
{
    public $property_id = 0;
    public $property_record = [];
    public $description_record_array = [];
    public $value_record_array = [];
    public $product_record_array = [];
    private static $value_description_record_field_list = ['language_id' => true, 'values_text' => true];
    public function get_id()
    {
        return $this->property_id;
    }
    public function set_id($property_id)
    {
        $property_id = (int) $property_id;
        if ($property_id >= 0) {
            $this->property_id = $property_id;
            return true;
        }
        return false;
    }
    public function load($property_id)
    {
        $this->clear();
        $property_id = (int) $property_id;
        $property_record = \common\models\Properties::find()->where(['properties_id' => $property_id])->as_array(true)->one();
        unset($property_id);
        if (is_array($property_record)) {
            $this->property_id = (int) $property_record['properties_id'];
            $this->property_record = $property_record;
        }
        unset($property_record);
        if ($this->property_id > 0) {
            // DESCRIPTION
            foreach (\common\models\Properties_Description::find()->where(['properties_id' => $this->property_id])->as_array(true)->all() as $property_description_record) {
                $property_description_record['properties_name'] = trim($property_description_record['properties_name']);
                $property_description_record['properties_description'] = trim($property_description_record['properties_description']);
                $this->description_record_array[] = $property_description_record;
            }
            unset($property_description_record);
            // EOF DESCRIPTION
            // VALUE
            foreach (\common\models\Properties_Values::find()->where(['properties_id' => $this->property_id])->as_array(true)->all() as $value_record) {
                $value_record['values_text'] = trim($value_record['values_text']);
                $value_record['descriptionRecordArray'] = isset($this->value_record_array[$value_record['values_id']]['descriptionRecordArray']) ? $this->value_record_array[$value_record['values_id']]['descriptionRecordArray'] : [];
                if (!isset($this->value_record_array[$value_record['values_id']]) or $this->value_record_array[$value_record['values_id']]['values_text'] == '' or $value_record['language_id'] == \common\classes\language::default_id() and $value_record['values_text'] != '') {
                    $this->value_record_array[$value_record['values_id']] = $value_record;
                }
                unset($value_record['descriptionRecordArray']);
                $this->value_record_array[$value_record['values_id']]['descriptionRecordArray'][] = $value_record;
            }
            // EOF VALUE
            // PRODUCT
            $this->product_record_array = \common\models\Properties2Propducts::find()->alias('pp')->left_join(\common\models\Products::table_name() . ' AS p', 'p.products_id = pp.products_id')->where(['pp.properties_id' => $this->property_id])->select(['pp.*', 'p.products_model'])->as_array(true)->all();
            // EOF PRODUCT
            return true;
        }
        return false;
    }
    public function unrelate()
    {
        if (is_array($this->value_record_array)) {
            foreach ($this->value_record_array as &$value_record) {
                unset($value_record['values_id']);
            }
            unset($value_record);
        }
        return parent::unrelate();
    }
    public function validate()
    {
        $this->property_id = (int) ((int) $this->property_id > 0 ? $this->property_id : 0);
        if (!is_array($this->property_record)) {
            $this->message_add('Property Record is invalid!');
            return false;
        }
        if (!parent::validate()) {
            $this->message_add('Property is invalid!');
            return false;
        }
        $default_name = '';
        // DESCRIPTION
        $this->description_record_array = is_array($this->description_record_array) ? $this->description_record_array : [];
        foreach ($this->description_record_array as $key_d => &$description_record) {
            $description_record['language_id'] = (int) (isset($description_record['language_id']) ? $description_record['language_id'] : 0);
            if (isset($description_record['language_code'])) {
                $description_record['language_id'] = $this->get_language_id_by_code($description_record['language_code'], $description_record['language_id']);
            }
            if ($description_record['language_id'] > 0) {
                $description_record['properties_name'] = trim(isset($description_record['properties_name']) ? $description_record['properties_name'] : '');
                $default_name = $default_name == '' ? $description_record['properties_name'] : $default_name;
                continue;
            }
            unset($this->description_record_array[$key_d]);
        }
        unset($description_record);
        unset($key_d);
        // EOF DESCRIPTION
        if ($default_name == '' or count($this->description_record_array) == 0) {
            $this->message_add('Property Description is invalid!');
            return false;
        }
        unset($this->property_record['properties_id']);
        $this->property_record['properties_name_default'] = $default_name;
        unset($default_name);
        // VALUE
        $this->value_record_array = is_array($this->value_record_array) ? $this->value_record_array : [];
        foreach ($this->value_record_array as $key_v => &$value_record) {
            unset($value_record['properties_id']);
            $default_value_name = trim(isset($value_record['values_text']) ? $value_record['values_text'] : '');
            // VALUE DESCRIPTION
            $value_record['descriptionRecordArray'] = (isset($value_record['descriptionRecordArray']) and is_array($value_record['descriptionRecordArray'])) ? $value_record['descriptionRecordArray'] : [];
            foreach ($value_record['descriptionRecordArray'] as $key_vd => &$description_record) {
                $description_record['language_id'] = (int) (isset($description_record['language_id']) ? $description_record['language_id'] : 0);
                if (isset($description_record['language_code'])) {
                    $description_record['language_id'] = $this->get_language_id_by_code($description_record['language_code'], $description_record['language_id']);
                }
                if ($description_record['language_id'] > 0) {
                    $description_record['values_text'] = trim(isset($description_record['values_text']) ? $description_record['values_text'] : '');
                    $default_value_name = $default_value_name == '' ? $description_record['values_text'] : $default_value_name;
                    foreach ($description_record as $field => $null) {
                        if (!isset(self::$value_description_record_field_list[$field])) {
                            unset($description_record[$field]);
                        }
                    }
                    unset($field);
                    unset($null);
                    continue;
                }
                unset($value_record['descriptionRecordArray'][$key_vd]);
            }
            unset($description_record);
            unset($key_vd);
            // EOF VALUE DESCRIPTION
            if ($default_value_name != '' and count($value_record['descriptionRecordArray']) > 0) {
                $value_record['values_text_default'] = $default_value_name;
                foreach ($value_record as $field => $null) {
                    if (isset(self::$value_description_record_field_list[$field])) {
                        unset($value_record[$field]);
                    }
                }
                unset($field);
                unset($null);
                continue;
            }
            unset($this->value_record_array[$key_v]);
        }
        unset($default_value_name);
        unset($value_record);
        unset($key_v);
        // VALUE
        $this->product_record_array = is_array($this->product_record_array) ? $this->product_record_array : [];
        return true;
    }
    public function create()
    {
        $this->property_id = 0;
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        try {
            $property_class = \common\models\Properties::find()->where(['properties_id' => $this->property_id])->one();
            if (!$property_class instanceof \common\models\Properties) {
                $property_class = new \common\models\Properties();
                $property_class->load_default_values();
                if ($this->property_id > 0) {
                    $property_class->properties_id = $this->property_id;
                } else {
                    $this->unrelate();
                }
            }
            $property_class->set_attributes($this->property_record, false);
            if ($property_class->save(false)) {
                $default_name = $this->property_record['properties_name_default'];
                $this->property_id = $property_class->properties_id;
                $this->property_record = $property_class->to_array();
                unset($property_class);
                $return = $this->property_id;
                // DESCRIPTION
                foreach ($this->description_record_array as $key_d => &$description_record) {
                    $is_save_d = false;
                    if ($description_record['properties_name'] == '') {
                        $description_record['properties_name'] = $default_name;
                    }
                    try {
                        $description_record['properties_id'] = $this->property_id;
                        $property_description_class = \common\models\Properties_Description::find()->where(['properties_id' => $this->property_id, 'language_id' => $description_record['language_id']])->one();
                        if (!$property_description_class instanceof \common\models\Properties_Description) {
                            $property_description_class = new \common\models\Properties_Description();
                            $property_description_class->load_default_values();
                        }
                        $property_description_class->set_attributes($description_record, false);
                        if ($property_description_class->save(false)) {
                            $is_save_d = true;
                            $description_record = $property_description_class->to_array();
                        } else {
                            $this->message_add($property_description_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($property_description_class);
                    if ($is_save_d != true) {
                        unset($this->description_record_array[$key_d]);
                    }
                    unset($is_save_d);
                }
                unset($description_record);
                unset($default_name);
                unset($key_d);
                // EOF DESCRIPTION
                // VALUE
                foreach ($this->value_record_array as $key_v => &$value_record) {
                    $value_record['properties_id'] = $this->property_id;
                    $property_value_id = (int) (isset($value_record['values_id']) ? $value_record['values_id'] : 0);
                    unset($value_record['values_id']);
                    // VALUE DESCRIPTION
                    foreach ($value_record['descriptionRecordArray'] as $key_vd => &$value_description_record) {
                        $is_save_vd = false;
                        $value_description_record['values_text'] = trim($value_description_record['values_text']);
                        if ($value_description_record['values_text'] == '') {
                            $value_description_record['values_text'] = $value_record['values_text_default'];
                        }
                        try {
                            $property_value_class = \common\models\Properties_Values::find()->where(['properties_id' => $this->property_id, 'language_id' => $value_description_record['language_id']]);
                            if ($property_value_id > 0) {
                                $property_value_class->and_where(['values_id' => $property_value_id]);
                            } else {
                                $property_value_class->and_where(['values_text' => $value_description_record['values_text']]);
                            }
                            $property_value_class = $property_value_class->one();
                            if (!$property_value_class instanceof \common\models\Properties_Values) {
                                $property_value_class = new \common\models\Properties_Values();
                                $property_value_class->load_default_values();
                                if ($property_value_id <= 0) {
                                    $property_value_id_max = (int) \common\models\Properties_Values::find()->max('values_id');
                                    if ($property_value_id_max > 0) {
                                        $property_value_id = $property_value_id_max + 1;
                                    }
                                    unset($property_value_id_max);
                                }
                                if ($property_value_id > 0) {
                                    $property_value_class->values_id = $property_value_id;
                                }
                            }
                            $property_value_class->set_attributes($value_record, false);
                            $property_value_class->set_attributes($value_description_record, false);
                            if ($property_value_class->save(false)) {
                                $is_save_vd = true;
                                $property_value_id = (int) $property_value_class->values_id;
                                $value_description_record = $property_value_class->to_array();
                            } else {
                                $this->message_add($property_value_class->get_error_summary(true));
                            }
                        } catch (\Exception $exc) {
                            $this->message_add($exc->get_message());
                        }
                        unset($property_value_class);
                        if ($is_save_vd != true) {
                            unset($value_record['descriptionRecordArray'][$key_vd]);
                        }
                        unset($is_save_vd);
                    }
                    unset($value_description_record);
                    unset($key_vd);
                    // EOF VALUE DESCRIPTION
                    if ($property_value_id > 0 and count($value_record['descriptionRecordArray']) > 0) {
                        $value_record['descriptionRecordArray'] = array_values($value_record['descriptionRecordArray']);
                        $value_record = $value_record + $value_record['descriptionRecordArray'][0];
                    } else {
                        unset($this->value_record_array[$key_v]);
                    }
                    unset($value_record['values_text_default']);
                    unset($property_value_id);
                }
                unset($value_record);
                unset($key_v);
                // EOF VALUE
                unset($is_replace);
            } else {
                $this->message_add($property_class->get_error_summary(true));
            }
        } catch (\Exception $exc) {
            $this->message_add($exc->get_message());
        }
        return $return;
    }
}