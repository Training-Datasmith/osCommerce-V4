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

use common\api\models\AR\Catalog_Property\Property_Description;
use common\api\models\AR\Ep_Map;
use common\helpers\Seo;
use common\models\Properties_Values;
class Properties extends Ep_Map
{
    protected $hide_fields = ['products_id'];
    public $property_type;
    public static function table_name()
    {
        return TABLE_PROPERTIES_TO_PRODUCTS;
    }
    public static function primary_key()
    {
        return ['products_id', 'properties_id', 'values_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function import_array($data)
    {
        //$data['names'];
        if (!empty($data['property_type'])) {
            $this->property_type = $data['property_type'];
        } else {
            $this->property_type = 'text';
        }
        if (isset($data['name_path'])) {
            $lookup_id = 0;
            $level_names = [];
            foreach ($data['name_path'] as $lang_code => $prop_name_path) {
                $prop_names = explode(';', $prop_name_path);
                $lang_id = $lang_code == '*' ? 0 : \common\classes\language::get_id($lang_code);
                foreach ($prop_names as $_level => $_name) {
                    $level_names[$_level][$lang_id] = $_name;
                }
            }
            foreach ($level_names as $__idx => $level_name_array) {
                $parent_id = $lookup_id;
                $lookup_id = $this->lookup_properties_by_name(current($level_name_array), $parent_id, $lang_id);
                if ($lookup_id == 0) {
                    $lookup_id = $this->create_properties($__idx + 1 == count($level_names) ? $this->property_type : 'category', $level_name_array, $parent_id, $lang_id);
                } elseif (count($level_name_array) > 1) {
                    foreach ($level_name_array as $lang_id => $prop_name) {
                        if (Property_Description::update_all(['properties_name' => $prop_name, 'properties_seo_page_name' => Seo::make_property_slug(['properties_id' => (int) $lookup_id, 'properties_name' => $prop_name])], ['properties_id' => (int) $lookup_id, 'language_id' => (int) $lang_id])) {
                            $this->lookup_properties_by_name((int) $lookup_id, -1, 0);
                        }
                    }
                }
            }
            $data['properties_id'] = $lookup_id;
        }
        if (isset($data['values']) && !empty($data['properties_id'])) {
            $lookup_id = 0;
            $value_names = [];
            foreach ($data['values'] as $lang_code => $prop_value) {
                $lang_id = $lang_code == '*' ? 0 : \common\classes\language::get_id($lang_code);
                $value_names[$lang_id] = $prop_value;
            }
            if (isset($value_names[0])) {
                foreach (\common\classes\language::get_all() as $__lang_all) {
                    if (isset($value_names[(int) $__lang_all['id']])) {
                        continue;
                    }
                    $value_names[(int) $__lang_all['id']] = $value_names[0];
                }
                unset($value_names[0]);
            }
            reset($value_names);
            $prop_value = current($value_names);
            $get_value_id_r = tep_db_query('SELECT values_id ' . 'FROM ' . TABLE_PROPERTIES_VALUES . ' ' . "WHERE properties_id='" . $data['properties_id'] . "' AND values_text='" . tep_db_input($prop_value) . "' " . 'LIMIT 1 ');
            if (tep_db_num_rows($get_value_id_r) > 0) {
                $_value_id = tep_db_fetch_array($get_value_id_r);
                $data['values_id'] = $_value_id['values_id'];
            } else {
                $max_value = tep_db_fetch_array(tep_db_query('SELECT MAX(values_id) AS current_max_id FROM ' . TABLE_PROPERTIES_VALUES));
                $values_id = intval($max_value['current_max_id']) + 1;
                $prop_value_slug = Seo::make_property_value_slug(['properties_id' => $data['properties_id'], 'values_text' => $prop_value]);
                if ($this->property_type == 'number') {
                    tep_db_query('INSERT INTO ' . TABLE_PROPERTIES_VALUES . ' (values_id, properties_id, language_id, values_text, values_number, values_seo_page_name ) ' . "SELECT '{$values_id}', '" . $data['properties_id'] . "', languages_id, '" . tep_db_input($prop_value) . "', '" . tep_db_input($prop_value) . "', '" . tep_db_input($prop_value_slug) . "' FROM " . TABLE_LANGUAGES . ' WHERE languages_status=1 ');
                } else {
                    tep_db_query('INSERT INTO ' . TABLE_PROPERTIES_VALUES . ' (values_id, properties_id, language_id, values_text, values_seo_page_name ) ' . "SELECT '{$values_id}', '" . $data['properties_id'] . "', languages_id, '" . tep_db_input($prop_value) . "', '" . tep_db_input($prop_value_slug) . "' FROM " . TABLE_LANGUAGES . ' WHERE languages_status=1 ');
                }
                $data['values_id'] = $values_id;
            }
            if (count($value_names) > 1) {
                foreach ($value_names as $lang_id => $prop_value) {
                    $prop_value_slug = Seo::make_property_value_slug(['values_id' => $data['values_id'], 'language_id' => $lang_id, 'properties_id' => $data['properties_id'], 'values_text' => $prop_value]);
                    if ($this->property_type == 'number') {
                        Properties_Values::update_all(['values_text' => $prop_value, 'values_number' => $prop_value, 'values_seo_page_name' => $prop_value_slug], ['values_id' => $data['values_id'], 'properties_id' => $data['properties_id'], 'language_id' => $lang_id]);
                    } else {
                        Properties_Values::update_all(['values_text' => $prop_value, 'values_seo_page_name' => $prop_value_slug], ['values_id' => $data['values_id'], 'properties_id' => $data['properties_id'], 'language_id' => $lang_id]);
                    }
                }
            }
        }
        if (empty($data['properties_id']) || empty($data['values_id'])) {
            return false;
        }
        return parent::import_array($data);
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (count($fields) == 0 || in_array('name', $fields)) {
            $prop_names = $this->get_properties_name_arr($this->properties_id);
            $data['names'] = $prop_names['names'];
            $data['name_path'] = $prop_names['names'];
            $parent_id = $prop_names['parent_id'];
            while ($parent_id > 0) {
                $prop_names = $this->get_properties_name_arr($parent_id);
                foreach ($data['name_path'] as $lang_code => $saved_path) {
                    $data['name_path'][$lang_code] = (isset($prop_names['names'][$lang_code]) ? $prop_names['names'][$lang_code] : '') . ';' . $saved_path;
                }
                $parent_id = $prop_names['parent_id'];
            }
        }
        if (count($fields) == 0 || in_array('values', $fields)) {
            $data['values'] = [];
            $get_data_r = tep_db_query('SELECT pv.language_id, pv.values_text ' . 'FROM ' . TABLE_PROPERTIES_VALUES . ' pv ' . "WHERE pv.values_id='" . $this->values_id . "' " . " AND pv.properties_id='" . $this->properties_id . "' ");
            if (tep_db_num_rows($get_data_r) > 0) {
                while ($_data = tep_db_fetch_array($get_data_r)) {
                    $data['values'][\common\classes\language::get_code($_data['language_id'])] = $_data['values_text'];
                }
            }
        }
        return $data;
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (intval($imported_object->properties_id) == intval($this->properties_id) && intval($imported_object->values_id) == intval($this->values_id)) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    protected function get_properties_name_arr($id)
    {
        $result = ['parent_id' => 0, 'names' => []];
        $get_data_r = tep_db_query('SELECT p.parent_id, pd.language_id, pd.properties_name ' . 'FROM ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . ' pd ' . 'WHERE p.properties_id=pd.properties_id ' . " AND p.properties_id='" . $id . "' ");
        if (tep_db_num_rows($get_data_r) > 0) {
            while ($_data = tep_db_fetch_array($get_data_r)) {
                $result['parent_id'] = $_data['parent_id'];
                $result['names'][\common\classes\language::get_code($_data['language_id'])] = $_data['properties_name'];
            }
        }
        return $result;
    }
    protected function lookup_properties_by_name($prop_name, $parent_id, $lang_id)
    {
        static $lookups = [];
        if ($parent_id === -1) {
            $idx = array_search($prop_name, $lookups);
            if ($idx !== false) {
                unset($lookups[$idx]);
            }
            return;
        }
        $key = (int) $parent_id . '^' . (int) $lang_id . '^' . $prop_name;
        if (isset($lookups[$key])) {
            return $lookups[$key];
        }
        $prop_id = 0;
        $get_data_r = tep_db_query('SELECT p.properties_id ' . 'FROM ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . ' pd ' . 'WHERE p.properties_id=pd.properties_id ' . " AND pd.properties_name = '" . tep_db_input($prop_name) . "' " . " AND p.parent_id='" . $parent_id . "' " . 'LIMIT 1');
        if (tep_db_num_rows($get_data_r) > 0) {
            $get_data = tep_db_fetch_array($get_data_r);
            $prop_id = $get_data['properties_id'];
            $lookups[$key] = $prop_id;
        }
        return $prop_id;
    }
    protected function create_properties($type, $prop_name, $parent_id, $lang_id)
    {
        tep_db_perform(TABLE_PROPERTIES, ['parent_id' => $parent_id, 'properties_type' => $type, 'date_added' => 'now()']);
        $prop_id = tep_db_insert_id();
        if (is_array($prop_name)) {
            $prop_names = $prop_name;
            $prop_name = '';
            foreach ($prop_names as $lang_id => $_prop_name) {
                if (empty($prop_name) || $lang_id == \common\classes\language::default_id()) {
                    $prop_name = $_prop_name;
                }
                $property_seo_name = Seo::make_property_slug(['properties_id' => (int) $prop_id, 'properties_name' => $_prop_name]);
                tep_db_query('INSERT IGNORE INTO ' . TABLE_PROPERTIES_DESCRIPTION . ' (properties_id, language_id, properties_name, properties_seo_page_name) ' . "VALUES ('" . (int) $prop_id . "', '" . (int) $lang_id . "', '" . tep_db_input($_prop_name) . "', '" . tep_db_input($property_seo_name) . "')");
            }
        }
        $property_seo_name = Seo::make_property_slug(['properties_id' => (int) $prop_id, 'properties_name' => $prop_name]);
        tep_db_query('INSERT IGNORE INTO ' . TABLE_PROPERTIES_DESCRIPTION . ' (properties_id, language_id, properties_name, properties_seo_page_name) ' . "SELECT '" . (int) $prop_id . "', languages_id, '" . tep_db_input($prop_name) . "', '" . tep_db_input($property_seo_name) . "' FROM " . TABLE_LANGUAGES . ' WHERE languages_status=1');
        return $prop_id;
    }
}