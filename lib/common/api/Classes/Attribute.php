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

class Attribute extends Abstract_Class
{
    public $attribute_id = 0;
    public $attribute_record = [];
    public $description_record_array = [];
    public $value_record_array = [];
    public $product_record_array = [];
    private static $description_record_field_list = ['language_id' => true, 'products_options_name' => true, 'products_options_image' => true, 'products_options_color' => true];
    private static $value_description_record_field_list = ['language_id' => true, 'products_options_values_name' => true, 'products_options_values_image' => true, 'products_options_values_color' => true];
    public function get_id()
    {
        return $this->attribute_id;
    }
    public function set_id($attribute_id)
    {
        $attribute_id = (int) $attribute_id;
        if ($attribute_id >= 0) {
            $this->attribute_id = $attribute_id;
            return true;
        }
        return false;
    }
    public function load($attribute_id)
    {
        $this->clear();
        $attribute_id = (int) $attribute_id;
        foreach (\common\models\Products_Options::find()->where(['products_options_id' => $attribute_id])->as_array(true)->all() as $attribute_record) {
            $this->attribute_id = $attribute_id;
            $attribute_record['products_options_name'] = trim($attribute_record['products_options_name']);
            if (!isset($this->attribute_record['products_options_id']) or $this->attribute_record['products_options_name'] == '' or $attribute_record['language_id'] == \common\classes\language::default_id() and $attribute_record['products_options_name'] != '') {
                $this->attribute_record = $attribute_record;
            }
            // DESCRIPTION
            $this->description_record_array[] = $attribute_record;
            // EOF DESCRIPTION
        }
        unset($attribute_record);
        if ($this->attribute_id > 0) {
            // VALUE
            foreach (\common\models\Products_Options_Values::find()->alias('av')->left_join(\common\models\Products_Options2products_Options_Values::table_name() . ' AS atv', 'atv.products_options_values_id = av.products_options_values_id')->where(['atv.products_options_id' => $attribute_id])->select(['*'])->as_array(true)->all() as $value_record) {
                $value_record['products_options_values_name'] = trim($value_record['products_options_values_name']);
                $value_record['descriptionRecordArray'] = isset($this->value_record_array[$value_record['products_options_values_id']]['descriptionRecordArray']) ? $this->value_record_array[$value_record['products_options_values_id']]['descriptionRecordArray'] : [];
                if (!isset($this->value_record_array[$value_record['products_options_values_id']]) or $this->value_record_array[$value_record['products_options_values_id']]['products_options_values_name'] == '' or $value_record['language_id'] == \common\classes\language::default_id() and $value_record['products_options_values_name'] != '') {
                    $this->value_record_array[$value_record['products_options_values_id']] = $value_record;
                }
                unset($value_record['descriptionRecordArray']);
                $this->value_record_array[$value_record['products_options_values_id']]['descriptionRecordArray'][] = $value_record;
            }
            // EOF VALUE
            // PRODUCT
            foreach (\common\models\Products_Attributes::find()->alias('pa')->left_join(\common\models\Products::table_name() . ' AS p', 'p.products_id = pa.products_id')->select(['pa.*', 'p.products_model'])->where(['options_id' => $attribute_id])->as_array(true)->all() as $product_record_array) {
                $product_record_array['downloadRecordArray'] = \common\models\Products_Attributes_Download::find()->where(['products_attributes_id' => $product_record_array['products_attributes_id']])->as_array(true)->all();
                $product_record_array['priceRecordArray'] = \common\models\Products_Attributes_Prices::find()->where(['products_attributes_id' => $product_record_array['products_attributes_id']])->as_array(true)->all();
                $this->product_record_array[] = $product_record_array;
            }
            unset($product_record_array);
            // EOF PRODUCT
            return true;
        }
        return false;
    }
    public function unrelate()
    {
        if (is_array($this->value_record_array)) {
            foreach ($this->value_record_array as &$value_record) {
                unset($value_record['products_options_values_id']);
            }
            unset($value_record);
        }
        return parent::unrelate();
    }
    public function validate()
    {
        $this->attribute_id = (int) ((int) $this->attribute_id > 0 ? $this->attribute_id : 0);
        if (!is_array($this->attribute_record)) {
            $this->message_add('Attribute Record is invalid!');
            return false;
        }
        if (!parent::validate()) {
            $this->message_add('Attribute is invalid!');
            return false;
        }
        $default_name = trim(isset($this->attribute_record['products_options_name']) ? $this->attribute_record['products_options_name'] : '');
        // DESCRIPTION
        $this->description_record_array = is_array($this->description_record_array) ? $this->description_record_array : [];
        foreach ($this->description_record_array as $key_d => &$description_record) {
            $description_record['language_id'] = (int) (isset($description_record['language_id']) ? $description_record['language_id'] : 0);
            if (isset($description_record['language_code'])) {
                $description_record['language_id'] = $this->get_language_id_by_code($description_record['language_code'], $description_record['language_id']);
            }
            if ($description_record['language_id'] > 0) {
                $description_record['products_options_name'] = trim(isset($description_record['products_options_name']) ? $description_record['products_options_name'] : '');
                $default_name = $default_name == '' ? $description_record['products_options_name'] : $default_name;
                foreach ($description_record as $field => $null) {
                    if (!isset(self::$description_record_field_list[$field])) {
                        unset($description_record[$field]);
                    }
                }
                unset($field);
                unset($null);
                continue;
            }
            unset($this->description_record_array[$key_d]);
        }
        unset($description_record);
        unset($key_d);
        // EOF DESCRIPTION
        if ($default_name == '' or count($this->description_record_array) == 0) {
            $this->message_add('Attribute Description is invalid!');
            return false;
        }
        unset($this->attribute_record['products_options_id']);
        $this->attribute_record['products_options_name_default'] = $default_name;
        unset($default_name);
        foreach ($this->attribute_record as $field => $null) {
            if (isset(self::$description_record_field_list[$field])) {
                unset($this->attribute_record[$field]);
            }
        }
        unset($field);
        unset($null);
        // VALUE
        $this->value_record_array = is_array($this->value_record_array) ? $this->value_record_array : [];
        foreach ($this->value_record_array as $key_v => &$value_record) {
            unset($value_record['products_options_id']);
            unset($value_record['products_options_values_to_products_options_id']);
            $default_value_name = trim(isset($value_record['products_options_values_name']) ? $value_record['products_options_values_name'] : '');
            // VALUE DESCRIPTION
            $value_record['descriptionRecordArray'] = (isset($value_record['descriptionRecordArray']) and is_array($value_record['descriptionRecordArray'])) ? $value_record['descriptionRecordArray'] : [];
            foreach ($value_record['descriptionRecordArray'] as $key_vd => &$description_record) {
                $description_record['language_id'] = (int) (isset($description_record['language_id']) ? $description_record['language_id'] : 0);
                if (isset($description_record['language_code'])) {
                    $description_record['language_id'] = $this->get_language_id_by_code($description_record['language_code'], $description_record['language_id']);
                }
                if ($description_record['language_id'] > 0) {
                    $description_record['products_options_values_name'] = trim(isset($description_record['products_options_values_name']) ? $description_record['products_options_values_name'] : '');
                    $default_value_name = $default_value_name == '' ? $description_record['products_options_values_name'] : $default_value_name;
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
                $value_record['products_options_values_name_default'] = $default_value_name;
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
        $this->attribute_id = 0;
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        // DESCRIPTION
        foreach ($this->description_record_array as $key_d => &$description_record) {
            $is_save_d = false;
            if ($description_record['products_options_name'] == '') {
                $description_record['products_options_name'] = $this->attribute_record['products_options_name_default'];
            }
            try {
                $attribute_class = \common\models\Products_Options::find()->where(['products_options_id' => $this->attribute_id, 'language_id' => $description_record['language_id']])->one();
                if (!$attribute_class instanceof \common\models\Products_Options) {
                    $attribute_class = new \common\models\Products_Options();
                    $attribute_class->load_default_values();
                    if ($this->attribute_id <= 0) {
                        $attribute_id_max = (int) \common\models\Products_Options::find()->max('products_options_id');
                        if ($attribute_id_max > 0) {
                            $this->attribute_id = $attribute_id_max + 1;
                        }
                        unset($attribute_id_max);
                    }
                    if ($this->attribute_id > 0) {
                        $attribute_class->products_options_id = $this->attribute_id;
                    }
                }
                $attribute_class->set_attributes($this->attribute_record, false);
                $attribute_class->set_attributes($description_record, false);
                if ($attribute_class->save(false)) {
                    $is_save_d = true;
                    $description_record = $attribute_class->to_array();
                    $this->attribute_id = $attribute_class->products_options_id;
                } else {
                    $this->message_add($attribute_class->get_error_summary(true));
                }
            } catch (\Exception $exc) {
                $this->message_add($exc->get_message());
            }
            unset($attribute_class);
            if ($is_save_d != true) {
                unset($this->description_record_array[$key_d]);
            }
            unset($is_save_d);
        }
        unset($this->attribute_record['products_options_name_default']);
        unset($description_record);
        unset($key_d);
        // EOF DESCRIPTION
        if (count($this->description_record_array) == 0) {
            return $return;
        }
        $return = $this->attribute_id;
        $this->description_record_array = array_values($this->description_record_array);
        $this->attribute_record = $this->attribute_record + $this->description_record_array[0];
        // VALUE
        foreach ($this->value_record_array as $key_v => &$value_record) {
            $attribute_value_id = (int) (isset($value_record['products_options_values_id']) ? $value_record['products_options_values_id'] : 0);
            unset($value_record['products_options_values_id']);
            // VALUE DESCRIPTION
            foreach ($value_record['descriptionRecordArray'] as $key_vd => &$value_description_record) {
                $is_save_vd = false;
                $value_description_record['products_options_values_name'] = trim($value_description_record['products_options_values_name']);
                if ($value_description_record['products_options_values_name'] == '') {
                    $value_description_record['products_options_values_name'] = $value_record['products_options_values_name_default'];
                }
                try {
                    $attribute_value_class = \common\models\Products_Options_Values::find()->where(['products_options_values_id' => $attribute_value_id, 'language_id' => $value_description_record['language_id']])->one();
                    if (!$attribute_value_class instanceof \common\models\Products_Options_Values) {
                        $attribute_value_class = new \common\models\Products_Options_Values();
                        $attribute_value_class->load_default_values();
                        if ($attribute_value_id <= 0) {
                            $attribute_value_id_max = (int) \common\models\Products_Options_Values::find()->max('products_options_values_id');
                            if ($attribute_value_id_max > 0) {
                                $attribute_value_id = $attribute_value_id_max + 1;
                            }
                            unset($attribute_value_id_max);
                        }
                        if ($attribute_value_id > 0) {
                            $attribute_value_class->products_options_values_id = $attribute_value_id;
                        }
                    }
                    $attribute_value_class->set_attributes($value_record, false);
                    $attribute_value_class->set_attributes($value_description_record, false);
                    if ($attribute_value_class->save(false)) {
                        $is_save_vd = true;
                        $value_description_record = $attribute_value_class->to_array();
                    } else {
                        $this->message_add($attribute_value_class->get_error_summary(true));
                    }
                } catch (\Exception $exc) {
                    $this->message_add($exc->get_message());
                }
                unset($attribute_value_class);
                if ($is_save_vd != true) {
                    unset($value_record['descriptionRecordArray'][$key_vd]);
                }
                unset($is_save_vd);
            }
            unset($value_description_record);
            unset($key_vd);
            // EOF VALUE DESCRIPTION
            unset($value_record['products_options_values_name_default']);
            // VALUE TO ATTRIBUTE
            $is_save_va = false;
            if ($attribute_value_id > 0 and count($value_record['descriptionRecordArray']) > 0) {
                $value_record['descriptionRecordArray'] = array_values($value_record['descriptionRecordArray']);
                try {
                    $attribute_value_class = \common\models\Products_Options2products_Options_Values::find_one(['products_options_id' => $this->attribute_id, 'products_options_values_id' => $attribute_value_id]);
                    if (!$attribute_value_class instanceof \common\models\Products_Options2products_Options_Values) {
                        $attribute_value_class = new \common\models\Products_Options2products_Options_Values();
                        $attribute_value_class->products_options_id = $this->attribute_id;
                        $attribute_value_class->products_options_values_id = $attribute_value_id;
                    }
                    if ($attribute_value_class->save(false)) {
                        $is_save_va = true;
                        foreach ($value_record['descriptionRecordArray'] as &$value_description_record) {
                            $value_description_record = $value_description_record + $attribute_value_class->to_array();
                        }
                        unset($value_description_record);
                        $value_record = $value_record + $value_record['descriptionRecordArray'][0];
                    } else {
                        $this->message_add($attribute_value_class->get_error_summary(true));
                    }
                } catch (\Exception $exc) {
                    $this->message_add($exc->get_message());
                }
                unset($attribute_value_class);
            }
            if ($is_save_va != true) {
                unset($this->value_record_array[$key_v]);
            }
            unset($is_save_va);
            // EOF VALUE TO ATTRIBUTE
            unset($attribute_value_id);
        }
        unset($value_record);
        unset($key_v);
        // EOF VALUE
        unset($is_replace);
        return $return;
    }
}