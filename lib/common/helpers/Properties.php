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
namespace common\helpers;

class Properties
{
    public static function is_property_in_array($properties_id, $properties_array)
    {
        if (in_array($properties_id, $properties_array)) {
            return true;
        } else {
            static $group_by_parent;
            if (!is_array($group_by_parent)) {
                $prop_list = \common\models\Properties::find()->select(['properties_id', 'parent_id'])->as_array()->all();
                $group_by_parent = \yii\helpers\Array_Helper::map($prop_list, 'properties_id', 'properties_id', 'parent_id');
            }
            if (isset($group_by_parent[(int) $properties_id])) {
                foreach ($group_by_parent[(int) $properties_id] as $_child_prop_id) {
                    if (self::is_property_in_array($_child_prop_id, $properties_array)) {
                        return true;
                    }
                }
            }
            return false;
            /*
            $properties_query = tep_db_query("select properties_id from " . TABLE_PROPERTIES . " where parent_id = '" . (int) $properties_id . "'");
            if (tep_db_num_rows($properties_query) > 0) {
                while ($properties = tep_db_fetch_array($properties_query)) {
                    if (self::is_property_in_array($properties['properties_id'], $properties_array)) {
                        return true;
                    }
                }
            } else {
                return false;
            }
            */
        }
    }
    public static function generate_properties_tree($parent_id = '0', $properties_array = [], $values_array = [], $properties_tree_array = '', $throughout_id = '', $val_extra = [])
    {
        global $languages_id;
        if (!is_array($properties_tree_array)) {
            $properties_tree_array = [];
        }
        // {{ speedup listing (properties by flag: listing, compare ...)
        $only_requested_property_ids = false;
        if (is_array($properties_array) && count($properties_array) > 0 && count($properties_array) < 50) {
            sort($properties_array);
            $_cache_key = implode('_', $properties_array);
            static $_prev_data = [];
            if (!isset($_prev_data[$_cache_key])) {
                $only_requested_property_ids = [];
                $_get_parents_for = $properties_array;
                do {
                    $_get_parents_r = tep_db_query('SELECT properties_id, parent_id ' . 'FROM ' . TABLE_PROPERTIES . ' ' . "WHERE properties_id IN('" . implode("', '", array_map('intval', $_get_parents_for)) . "')");
                    if (tep_db_num_rows($_get_parents_r) == 0) {
                        break;
                    }
                    $_get_parents_for = [];
                    while ($_get_parent = tep_db_fetch_array($_get_parents_r)) {
                        $only_requested_property_ids[$_get_parent['properties_id']] = (int) $_get_parent['properties_id'];
                        $only_requested_property_ids[$_get_parent['parent_id']] = (int) $_get_parent['parent_id'];
                        $_get_parents_for[$_get_parent['parent_id']] = $_get_parent['parent_id'];
                    }
                } while (true);
                $_prev_data[$_cache_key] = $only_requested_property_ids;
            }
            $only_requested_property_ids = $_prev_data[$_cache_key];
        }
        // }} speedup listing
        $properties_query = tep_db_query('select p.properties_id, p.properties_type, p.decimals, p.parent_id, p.extra_values, if(length(pd.properties_name_alt) > 0, pd.properties_name_alt, pd.properties_name) as properties_name, pd.properties_image, pd.properties_color, pu.properties_units_title from ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . ' pd left join ' . TABLE_PROPERTIES_UNITS . " pu on pu.properties_units_id = pd.properties_units_id where p.parent_id = '" . (int) $parent_id . "' and p.properties_id = pd.properties_id and pd.language_id = '" . (int) $languages_id . "' " . (is_array($only_requested_property_ids) && count($only_requested_property_ids) > 0 ? " AND p.properties_id IN('" . implode("','", $only_requested_property_ids) . "')" : '') . " order by (p.properties_type = 'category'), p.sort_order, properties_name");
        $sort = 1;
        while ($properties = tep_db_fetch_array($properties_query)) {
            if (self::is_property_in_array($properties['properties_id'], $properties_array)) {
                $values = [];
                $images = [];
                $colors = [];
                $extra_values = [];
                //if extra_values
                if (is_array($values_array[$properties['properties_id']] ?? null)) {
                    if ($properties['properties_type'] == 'flag') {
                        if ($values_array[$properties['properties_id']][0]) {
                            $values[1] = TEXT_YES;
                        } else {
                            $values[0] = TEXT_NO;
                        }
                    } else {
                        $values_array_indexes = array_flip($values_array[$properties['properties_id']]);
                        $properties_values_query = tep_db_query('select values_id, values_text, values_number, values_number_upto, values_alt, values_color, values_image, values_prefix, values_postfix from ' . TABLE_PROPERTIES_VALUES . " where properties_id = '" . (int) $properties['properties_id'] . "' and values_id in ('" . implode("','", $values_array[$properties['properties_id']]) . "') and language_id = '" . (int) $languages_id . "' order by sort_order, " . ($properties['properties_type'] == 'number' || $properties['properties_type'] == 'interval' ? 'values_number' : 'values_text'));
                        while ($properties_values = tep_db_fetch_array($properties_values_query)) {
                            if ($properties['properties_type'] == 'interval') {
                                $properties_values['values'] = number_format($properties_values['values_number'], $properties['decimals'], '.', '') . ' - ' . number_format($properties_values['values_number_upto'], $properties['decimals'], '.', '');
                            } elseif ($properties['properties_type'] == 'number') {
                                $properties_values['values'] = number_format($properties_values['values_number'], $properties['decimals'], '.', '');
                            } else {
                                $properties_values['values'] = $properties_values['values_text'];
                            }
                            if ($properties['extra_values'] == 1 && isset($val_extra[$properties['properties_id']][$values_array_indexes[$properties_values['values_id']]])) {
                                $extra_values[$properties_values['values_id']] = $properties_values['values_prefix'] . $val_extra[$properties['properties_id']][$values_array_indexes[$properties_values['values_id']]] . $properties_values['values_postfix'];
                            } else {
                                $extra_values[$properties_values['values_id']] = '';
                            }
                            $values[$properties_values['values_id']] = $properties_values['values'];
                            $images[$properties_values['values_id']] = $properties_values['values_image'];
                            $colors[$properties_values['values_id']] = $properties_values['values_color'];
                        }
                    }
                }
                $temp_throughout_id = $throughout_id;
                if ($temp_throughout_id != '') {
                    $temp_throughout_id .= '.';
                }
                $temp_throughout_id .= $sort;
                $properties_tree_array[$properties['properties_id']] = ['properties_id' => $properties['properties_id'], 'properties_name' => $properties['properties_name'] . (tep_not_null($properties['properties_units_title']) ? ' (' . $properties['properties_units_title'] . ')' : ''), 'properties_image' => $properties['properties_image'], 'properties_color' => $properties['properties_color'], 'properties_type' => $properties['properties_type'], 'parent_id' => $parent_id, 'throughoutID' => $temp_throughout_id, 'values' => $values, 'extra_values' => $extra_values, 'images' => $images, 'colors' => $colors];
                $properties_tree_array = self::generate_properties_tree($properties['properties_id'], $properties_array, $values_array, $properties_tree_array, $temp_throughout_id);
                $sort++;
            }
        }
        return $properties_tree_array ?? null;
    }
    public static function get_properties_color($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select properties_color from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $language_id . "'"));
        return $data['properties_color'] ?? null;
    }
    public static function get_properties_name($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select properties_name from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $language_id . "'"));
        return $data['properties_name'] ?? null;
    }
    public static function get_properties_name_alt($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select properties_name_alt from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $language_id . "'"));
        return $data['properties_name_alt'] ?? null;
    }
    public static function get_properties_description($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select properties_description from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $language_id . "'"));
        return $data['properties_description'] ?? null;
    }
    public static function get_properties_image($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select properties_image from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $language_id . "'"));
        return $data['properties_image'] ?? null;
    }
    public static function get_properties_value($properties_value_id, $language_id)
    {
        return \common\models\Properties_Values::find()->where(['values_id' => $properties_value_id, 'language_id' => $language_id])->one();
    }
    public static function get_properties_units_title($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select pu.properties_units_title from ' . TABLE_PROPERTIES_DESCRIPTION . ' pd left join ' . TABLE_PROPERTIES_UNITS . " pu on pu.properties_units_id = pd.properties_units_id where pd.properties_id = '" . (int) $properties_id . "' and pd.language_id = '" . (int) $language_id . "'"));
        return $data['properties_units_title'] ?? null;
    }
    public static function get_properties_seo_page_name($properties_id, $language_id)
    {
        $data = tep_db_fetch_array(tep_db_query('select properties_seo_page_name from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $language_id . "'"));
        return $data['properties_seo_page_name'] ?? null;
    }
    public static function remove_property($properties_id)
    {
        $child_properties_query = tep_db_query('select properties_id from ' . TABLE_PROPERTIES . " where parent_id = '" . (int) $properties_id . "'");
        while ($child_properties = tep_db_fetch_array($child_properties_query)) {
            self::remove_property($child_properties['properties_id']);
        }
        tep_db_query('delete from ' . TABLE_PROPERTIES . " where properties_id = '" . (int) $properties_id . "'");
        tep_db_query('delete from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "'");
        tep_db_query('delete from ' . TABLE_PROPERTIES_VALUES . " where properties_id = '" . (int) $properties_id . "'");
        tep_db_query('delete from ' . TABLE_PROPERTIES_TO_PRODUCTS . " where properties_id = '" . (int) $properties_id . "'");
        tep_db_query('delete from ' . TABLE_FILTERS . " where filters_type='property' and properties_id = '" . (int) $properties_id . "'");
    }
    public static function check_filter_table($property_id)
    {
        if ((int) $property_id <= 0) {
            return;
        }
        $get_flag_state_r = tep_db_query('SELECT display_filter FROM ' . TABLE_PROPERTIES . " WHERE properties_id='" . (int) $property_id . "'");
        if (tep_db_num_rows($get_flag_state_r) > 0) {
            $_flag_state = tep_db_fetch_array($get_flag_state_r);
            if ($_flag_state['display_filter'] != 0) {
                return;
            }
            // display_filter = 0
            tep_db_query('DELETE FROM ' . TABLE_FILTERS . ' ' . "WHERE filters_type='property' AND properties_id = '" . (int) $property_id . "' ");
        }
    }
    public static function get_properties_tree($parent_id = '0', $spacing = '', $properties_tree_array = '', $categories_only = true)
    {
        global $languages_id;
        if (!is_array($properties_tree_array)) {
            $properties_tree_array = [];
        }
        if (sizeof($properties_tree_array) < 1) {
            $properties_tree_array[] = ['id' => '0', 'text' => TEXT_TOP, 'type' => 'category'];
        }
        $properties_query = tep_db_query('select p.properties_id, if(length(pd.properties_name_alt) > 0, pd.properties_name_alt, pd.properties_name) as properties_name, p.parent_id, p.properties_type from ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . " pd where p.properties_id = pd.properties_id and pd.language_id = '" . (int) $languages_id . "' and p.parent_id = '" . (int) $parent_id . "'" . ($categories_only ? " and p.properties_type = 'category'" : '') . " order by IF(p.properties_type = 'category',0,1), p.sort_order, properties_name");
        while ($properties = tep_db_fetch_array($properties_query)) {
            $properties_tree_array[] = ['id' => $properties['properties_id'], 'name' => $properties['properties_name'], 'text' => $spacing . $properties['properties_name'], 'type' => $properties['properties_type']];
            $properties_tree_array = self::get_properties_tree($properties['properties_id'], $spacing . '&nbsp;&nbsp;&nbsp;&nbsp;', $properties_tree_array, $categories_only);
        }
        return $properties_tree_array;
    }
    public static function get_properties()
    {
        global $languages_id;
        $properties = \common\models\Properties::find()->alias('pr')->join_with(['descriptions' => function (\yii\db\Active_Query $query) use ($languages_id) {
            return $query->alias('prd')->on_condition(['prd.language_id' => $languages_id])->order_by('prd.properties_name');
        }, 'values' => function (\yii\db\Active_Query $query) use ($languages_id) {
            return $query->alias('prv')->on_condition(['prv.language_id' => $languages_id])->order_by('prv.values_text');
        }])->order_by('pr.sort_order')->all();
        return $properties;
    }
    public static function get_products_to_property($properties_id)
    {
        if ($properties_id) {
            return \common\models\Properties2Propducts::find()->where(['properties_id' => $properties_id])->all();
        }
        return [];
    }
    public static function get_products_to_property_value($value_id)
    {
        if ($value_id) {
            return \common\models\Properties2Propducts::find()->where(['values_id' => $value_id])->all();
        }
        return [];
    }
    /*
     * [{"title": "Node 1", "key": "1"},
     {"title": "Folder 2", "key": "2", "folder": true, "children": [
    {"title": "Node 2.1", "key": "3"},
    {"title": "Node 2.2", "key": "4"}
      ]}
    ]
    */
    public static function properties_to_fancy($properties, $selected = [])
    {
        $data = [];
        try {
            foreach ($properties as $property) {
                $data[] = ['title' => $property->has_property('descriptions') ? $property->descriptions[0]->properties_name : $property->values_text, 'key' => $property->has_property('values_id') ? 'pv_' . $property->values_id : 'pr_' . $property->properties_id, 'folder' => $property->has_property('values') ? true : false, 'expanded' => true, 'selected' => in_array($property->has_property('values_id') ? 'pv_' . $property->values_id : 'pr_' . $property->properties_id, $selected), 'children' => $property->has_property('values') ? self::properties_to_fancy($property->values, $selected) : []];
            }
        } catch (\Exception $ex) {
            echo '<pre>';
            print_r($ex);
        }
        return $data;
    }
}