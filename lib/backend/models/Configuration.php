<?php

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
namespace backend\models;

use common\helpers\Translation;
use common\models\Countries;
use yii\helpers\Array_Helper;
use yii\helpers\Html;
class Configuration
{
    private static $tax_address_options = [0 => TEXT_BILLING_ADDRESS, 1 => TEXT_SHIPPING_ADDRESS, 2 => TEXT_BILLING_SHIPPING_ADDRESS];
    // Alias function for Store configuration values in the Administration Tool
    public static function tep_cfg_pull_down_country_list()
    {
        $keys = func_get_args();
        eval('list($country_id, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return tep_draw_pull_down_menu($name, \common\helpers\Country::get_countries(), $country_id);
    }
    public static function tep_cfg_pull_down_zone_list()
    {
        $keys = func_get_args();
        eval('list($zone_id,) = array(' . $keys[0] . ');');
        return tep_draw_pull_down_menu('configuration_value', \common\helpers\Zones::get_country_zones(STORE_COUNTRY), $zone_id);
    }
    public static function tep_cfg_pull_down_tax_classes()
    {
        $keys = func_get_args();
        eval('list($tax_class_id, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $tax_class_array = [['id' => '0', 'text' => TEXT_NONE]];
        $tax_class_query = tep_db_query('select tax_class_id, tax_class_title from ' . TABLE_TAX_CLASS . ' order by tax_class_title');
        while ($tax_class = tep_db_fetch_array($tax_class_query)) {
            $tax_class_array[] = ['id' => $tax_class['tax_class_id'], 'text' => $tax_class['tax_class_title']];
        }
        return tep_draw_pull_down_menu($name, $tax_class_array, $tax_class_id, 'class="form-control"');
    }
    ////
    // Function to read in text area in admin
    public static function tep_cfg_textarea()
    {
        $keys = func_get_args();
        if (is_array($keys[0])) {
            $text = $keys[0]['value'];
            $key = $keys[0]['key'];
        } else {
            eval('list($text, $key) = array(' . $keys[0] . ');');
        }
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return tep_draw_textarea_field($name, false, 35, 5, $text);
    }
    public static function tep_cfg_get_zone_name()
    {
        $keys = func_get_args();
        eval('list($zone_id,) = array(' . $keys[0] . ');');
        $zone_query = tep_db_query('select zone_name from ' . TABLE_ZONES . " where zone_id = '" . (int) $zone_id . "'");
        if (!tep_db_num_rows($zone_query)) {
            return $zone_id;
        } else {
            $zone = tep_db_fetch_array($zone_query);
            return $zone['zone_name'];
        }
    }
    public static function tep_cfg_select_multioption_order_statuses()
    {
        global $languages_id;
        $keys = func_get_args();
        eval('list($key_value, $key) = array(' . $keys[0] . ');');
        $string = '';
        $key_values = explode(', ', $key_value);
        $statuses_array = \common\helpers\Order::get_status('', true);
        for ($i = 0; $i < sizeof($statuses_array); $i++) {
            $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
            $string .= '<br><label><input type="checkbox" name="' . $name . '" value="' . $statuses_array[$i]['id'] . '"';
            if (in_array($statuses_array[$i]['id'], $key_values)) {
                $string .= 'CHECKED';
            }
            $string .= '> ' . $statuses_array[$i]['text'] . '</label>';
        }
        $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
        $string .= '<input type="hidden" name="' . $name . '" value="--none--">';
        return $string;
    }
    ////
    // Alias function for Store configuration values in the Administration Tool
    public static function tep_cfg_select_option()
    {
        global $languages_id;
        $string = '';
        $keys = func_get_args();
        $select_array = [];
        $key_value = null;
        $key = '';
        eval('list($select_array, $key_value, $key) = array(' . $keys[0] . ');');
        for ($i = 0, $n = sizeof($select_array); $i < $n; $i++) {
            $name = tep_not_null($key) ? 'configuration[' . $key . ']' : 'configuration_value';
            $string .= '<label class="radio-label"><input type="radio" name="' . $name . '" value="' . $select_array[$i] . '"';
            if ($key_value == $select_array[$i]) {
                $string .= ' CHECKED';
            }
            $_t = Translation::get_translation_value(strtoupper(str_replace(' ', '_', $select_array[$i])), 'configuration', $languages_id);
            $_t = tep_not_null($_t) ? $_t : $select_array[$i];
            $string .= '> ' . $_t . '</label><br>';
        }
        return $string;
    }
    ////
    // Alias function for module configuration keys
    public static function tep_mod_select_option()
    {
        global $languages_id;
        $keys = func_get_args();
        eval('list($select_array, $key_name, $key_value) = array(' . $keys[0] . ');');
        if (is_array($select_array)) {
            foreach ($select_array as $key => $value) {
                if (is_int($key)) {
                    $key = $value;
                }
                $string .= '<br><input type="radio" name="configuration[' . $key_name . ']" value="' . $key . '"';
                if ($key_value == $key) {
                    $string .= ' CHECKED';
                }
                $_t = Translation::get_translation_value(strtoupper(str_replace(' ', '_', $value)), 'configuration', $languages_id);
                $_t = tep_not_null($_t) ? $_t : $value;
                $string .= '> ' . $value;
            }
        }
        return $string;
    }
    public static function tep_cfg_pull_down_zone_classes()
    {
        $keys = func_get_args();
        eval('list($zone_class_id, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $zone_class_array = [['id' => '0', 'text' => TEXT_NONE]];
        $zone_class_query = tep_db_query('select geo_zone_id, geo_zone_name from ' . TABLE_GEO_ZONES . ' order by geo_zone_name');
        while ($zone_class = tep_db_fetch_array($zone_class_query)) {
            $zone_class_array[] = ['id' => $zone_class['geo_zone_id'], 'text' => $zone_class['geo_zone_name']];
        }
        return tep_draw_pull_down_menu($name, $zone_class_array, $zone_class_id);
    }
    public static function tep_cfg_pull_down_order_statuses()
    {
        global $languages_id;
        $keys = func_get_args();
        eval('list($order_status_id, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $statuses_array = [['id' => '0', 'text' => TEXT_DEFAULT]];
        /*$statuses_query = tep_db_query("select orders_status_id, orders_status_name from " . TABLE_ORDERS_STATUS . " where language_id = '" . (int)$languages_id . "' order by orders_status_name");
          while ($statuses = tep_db_fetch_array($statuses_query)) {
            $statuses_array[] = array('id' => $statuses['orders_status_id'],
            'text' => $statuses['orders_status_name']);
          }*/
        $orders_status_groups_query = tep_db_query('select orders_status_groups_id, orders_status_groups_name, orders_status_groups_color from ' . TABLE_ORDERS_STATUS_GROUPS . " where language_id = '" . (int) $languages_id . "' order by orders_status_groups_id");
        while ($orders_status_groups = tep_db_fetch_array($orders_status_groups_query)) {
            $statuses_array[] = ['optgroup' => $orders_status_groups['orders_status_groups_id'], 'text' => $orders_status_groups['orders_status_groups_name']];
            $orders_status_query = tep_db_query('select orders_status_id, orders_status_name from ' . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "' and orders_status_groups_id='" . (int) $orders_status_groups['orders_status_groups_id'] . "' order by orders_status_name");
            while ($orders_status = tep_db_fetch_array($orders_status_query)) {
                $statuses_array[] = ['id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']];
            }
            $statuses_array[] = ['optgroup' => $orders_status_groups['orders_status_groups_id']];
        }
        return tep_draw_pull_down_menu($name, $statuses_array, $order_status_id);
    }
    public static function set_email_template()
    {
        $keys = func_get_args();
        eval('list($selected_value, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return Html::drop_down_list($name, $selected_value, \common\helpers\Mail::email_templates_list(), ['class' => 'form-control', 'options' => []]);
    }
    // setGroupedOrderStatuses(false, => w/o any status
    // setGroupedOrderStatuses('[Any order status]', => any status
    // setGroupedOrderStatuses(true, => any status
    public static function set_grouped_order_statuses()
    {
        $keys = func_get_args();
        eval('list($any_status, $order_statuses, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        //$selected_statues = \common\helpers\Order::extractStatuses($order_statuses);
        $selected_statues = preg_split('/,\s*/', $order_statuses, -1, PREG_SPLIT_NO_EMPTY);
        $order_statuses_select = [];
        if ($any_status === false) {
        } else {
            $order_statuses_select['*'] = is_string($any_status) ? $any_status : '[Any order status]';
        }
        foreach (\common\helpers\Order::get_statuses_grouped(true) as $option) {
            $order_statuses_select[$option['id']] = html_entity_decode($option['text'], null, 'UTF-8');
        }
        return Html::drop_down_list($name, $selected_statues, $order_statuses_select, ['class' => 'form-control', 'style' => 'height:auto; min-height:150px', 'multiple' => true, 'options' => []]);
    }
    public static function use_grouped_order_statuses($order_statuses)
    {
        $selected_statues = \common\helpers\Order::extract_statuses($order_statuses);
        return \common\helpers\Order::get_status_name(implode(',', $selected_statues));
    }
    // Alias function for array of configuration values in the Administration Tool
    public static function tep_cfg_select_multioption()
    {
        $keys = func_get_args();
        eval('list($select_array, $key_value, $key) = array(' . $keys[0] . ');');
        $string = '';
        for ($i = 0; $i < sizeof($select_array); $i++) {
            $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
            $string .= '<br><input type="checkbox" name="' . $name . '" value="' . $select_array[$i] . '"' . ' id="' . $select_array[$i] . '"';
            $key_values = explode(', ', $key_value);
            if (in_array($select_array[$i], $key_values)) {
                $string .= 'CHECKED';
            }
            $string .= '> <label for="' . $select_array[$i] . '">' . \common\helpers\Translation::get_value($select_array[$i]) . '</label>';
        }
        $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
        $string .= '<input type="hidden" name="' . $name . '" value="--none--">';
        return $string;
    }
    public static function multi_option()
    {
        $keys = func_get_args();
        if (count($keys) == 1 && is_string($keys[0])) {
            eval('list($type, $select_array, $key_value, $key) = array(' . $keys[0] . ');');
        } else {
            list($type, $select_array, $key_value, $key) = func_get_args();
        }
        $selected_values = preg_split('/,\s?/', $key_value, -1, PREG_SPLIT_NO_EMPTY);
        $indexed = \yii\helpers\Array_Helper::is_indexed($select_array, true);
        $variants = $select_array;
        if ($indexed) {
            $variants = array_combine($select_array, $select_array);
        }
        $string = '';
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        if ($type == 'dropdown') {
            $string .= \common\helpers\Html::drop_down_list($name, $key_value, $variants, ['class' => 'form-control']);
        } else {
            foreach ($variants as $value => $value_text) {
                if ($type == 'radio') {
                    $string .= '<div><label>' . \common\helpers\Html::radio($name, in_array($value, $selected_values), ['value' => $value, 'class' => 'multiOption']) . ' ' . $value_text . '</label></div>';
                } else {
                    $string .= '<div><label>' . \common\helpers\Html::checkbox($name . '[]', in_array($value, $selected_values), ['value' => $value, 'class' => 'multiOption']) . ' ' . $value_text . '</label></div>';
                    $string .= \common\helpers\Html::hidden_input($name . '[]', '--none--');
                }
            }
        }
        return $string;
    }
    public static function translate_config($configuration)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $_t = Translation::get_translation_value(strtoupper(str_replace(' ', '_', $configuration['configuration_value'])), 'configuration', $languages_id);
        if ($_t === false) {
            $_t = Translation::get_translation_value(strtoupper(preg_replace('/[^a-z\d_]+/i', '_', $configuration['configuration_key'] . ' VALUE ' . $configuration['configuration_value'])), 'configuration', $languages_id);
        }
        $_t = tep_not_null($_t) ? $_t : $configuration['configuration_value'];
        return $_t;
    }
    //create a select list to display list of themes available for selection
    public static function tep_cfg_pull_down_template_list()
    {
        $keys = func_get_args();
        eval('list($template_id, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $template_query = tep_db_query('select template_id, template_name from ' . TABLE_TEMPLATE . ' order by template_name');
        while ($template = tep_db_fetch_array($template_query)) {
            $template_array[] = ['id' => $template['template_name'], 'text' => $template['template_name']];
        }
        return tep_draw_pull_down_menu($name, $template_array, $template_id);
    }
    public static function tep_cfg_get_timezone_name()
    {
        $keys = func_get_args();
        eval('list($zone_id,) = array(' . $keys[0] . ');');
        foreach (\common\helpers\System::get_timezones() as $unused => $timezone) {
            if ($timezone['id'] === $zone_id) {
                return $timezone['text'];
            }
        }
        return '';
    }
    public static function tep_cfg_pull_down_timezone_list()
    {
        $keys = func_get_args();
        eval('list($zone_id,) = array(' . $keys[0] . ');');
        return tep_draw_pull_down_menu('configuration_value', \common\helpers\System::get_timezones(), $zone_id);
    }
    public static function cfg_get_information_name()
    {
        $info_id = 0;
        $keys = func_get_args();
        eval('list($info_id,) = array(' . $keys[0] . ');');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $i = \common\models\Information::find()->where(['information_id' => $info_id, 'languages_id' => $languages_id, 'affiliate_id' => 0, 'platform_id' => \common\classes\platform::default_id()])->as_array()->one();
        return empty($i['info_title']) ? '' : $i['info_title'];
    }
    public static function cfg_get_information_list()
    {
        $info_id = 0;
        $keys = func_get_args();
        eval('list($info_id,) = array(' . $keys[0] . ');');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $i = \common\models\Information::find()->select(['text' => 'info_title', 'id' => 'information_id'])->where(['languages_id' => $languages_id, 'affiliate_id' => 0, 'platform_id' => \common\classes\platform::default_id()])->as_array()->order_by('info_title')->all();
        $i = array_merge([['id' => 0, 'text' => TEXT_NONE]], $i);
        return tep_draw_pull_down_menu('configuration_value', $i, $info_id);
    }
    public static function tep_cfg_select_download_status()
    {
        $keys = func_get_args();
        $vals = str_getcsv($keys[0], ',', '\'');
        list($key_value, $key) = $vals;
        $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value[]';
        $select_array = \common\helpers\Order::get_status('', true);
        $key_value_array = explode(',', $key_value);
        $string = '';
        for ($i = 0; $i < sizeof($select_array); $i++) {
            //$string .= '<br><input type="checkbox" name="' . $select_array[$i]['text'] . '" value="' . $select_array[$i]['id'] . '"';
            $string .= '<br><input type="checkbox" name="' . $name . '" value="' . $select_array[$i]['id'] . '"';
            for ($j = 0; $j < sizeof($key_value_array); $j++) {
                if ($key_value_array[$j] == $select_array[$i]['id']) {
                    $string .= ' CHECKED';
                }
            }
            $string .= '> ' . $select_array[$i]['text'];
        }
        $string .= '<br><input type="hidden" name="flag" value="exist">';
        return $string;
    }
    public static function tep_cfg_select_user_group()
    {
        $keys = func_get_args();
        $vals = str_getcsv($keys[0], ',', '\'');
        list($key_value, $key) = $vals;
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $arr = [0 => TEXT_MAIN];
        $tmp = \common\helpers\Group::get_customer_groups_list();
        if (is_array($tmp)) {
            $arr += $tmp;
        }
        if (is_array($arr)) {
            return \common\helpers\Html::drop_down_list($name, $key_value, $arr);
        }
        /*
                    $status_array = array();
                    $status_array[] = array('id' => '0', 'text' => TEXT_NONE);
                    $status_query = tep_db_query("select * from " . TABLE_GROUPS);
                    while ($status = tep_db_fetch_array($status_query)){
                      $status_array[] = array('id' => $status['groups_id'], 'text' => $status['groups_name']);
                    }
                    return tep_draw_pull_down_menu('configuration_value', $status_array, $key_value);
        */
    }
    public static function tep_cfg_select_user_edit_group()
    {
        $keys = func_get_args();
        eval('list($key_value,) = array(' . $keys[0] . ');');
        $status_array = [];
        $status_array[] = ['id' => '0', 'text' => TEXT_NONE];
        $status_query = tep_db_query('select * from ' . TABLE_GROUPS);
        while ($status = tep_db_fetch_array($status_query)) {
            $status_array[] = ['id' => $status['groups_id'], 'text' => $status['groups_name']];
        }
        return tep_draw_pull_down_menu('groups_id', $status_array, $key_value);
    }
    public static function tep_cfg_color()
    {
        $keys = func_get_args();
        eval('list($color,) = array(' . $keys[0] . ');');
        return '
<div class="colors-inp">
  <div id="cp3" class="input-group colorpicker-component">
    <input type="text" name="configuration_value" value="' . $color . '" class="form-control" placeholder="' . TEXT_COLOR_ . '" />
    <span class="input-group-append"><span class="input-group-text colorpicker-input-addon"><i></i></span></span>
  </div>
</div>
<script>
$(function(){
     var cp = $(\'.colorpicker-component:not(.colorpicker-element)\');
        cp.colorpicker({ sliders: {
          saturation: { maxLeft: 200, maxTop: 200 },
          hue: { maxTop: 200 },
          alpha: { maxTop: 200 }
        }}).on(\'changeColor\', changeStyle).on(\'changeColor\', function(){
          window.boxInputChanges[$(\'input\', this).attr(\'name\')] = $(\'input\', this).val()
        });
})
</script>';
    }
    public static function time_zones_select()
    {
        $keys = func_get_args();
        eval('list($key_value,) = array(' . $keys[0] . ');');
        $time_zones_variants = [];
        $tz_groups = ['Europe' => \DateTimeZone::EUROPE, 'America' => \DateTimeZone::AMERICA, 'Africa' => \DateTimeZone::AFRICA, 'Australia' => \DateTimeZone::AUSTRALIA, 'Pacific' => \DateTimeZone::PACIFIC, 'Asia' => \DateTimeZone::ASIA, 'Antarctica' => \DateTimeZone::ANTARCTICA, 'Arctic' => \DateTimeZone::ARCTIC, 'Atlantic' => \DateTimeZone::ATLANTIC, 'Indian' => \DateTimeZone::INDIAN, 'UTC' => \DateTimeZone::UTC];
        $languages_id = \Yii::$app->settings->get('languages_id');
        $iso2country = Array_Helper::map(Countries::find()->where(['language_id' => $languages_id])->order_by('countries_name')->all(), 'countries_iso_code_2', 'countries_name');
        $utc = new \DateTime('now', new \DateTimeZone('UTC'));
        $option_data_attributes = [];
        $suggest = [];
        $_suggest_country_info = \common\helpers\Country::get_country_info_by_id(\Yii::$app->get('platform')->get_config(\common\classes\platform::default_id())->const_value('STORE_COUNTRY'));
        $suggest_country_iso = $_suggest_country_info['countries_iso_code_2'];
        unset($_suggest_country_info);
        foreach ($tz_groups as $tz_group_label => $tz_group_id) {
            $offsets = [];
            $time_zones_variants[$tz_group_label] = [];
            foreach (\DateTimeZone::list_identifiers($tz_group_id) as $time_zone_ident) {
                $time_zone = new \DateTimeZone($time_zone_ident);
                $transition = $time_zone->get_transitions($utc->get_timestamp(), $utc->get_timestamp());
                $abbr = $transition[0]['abbr'];
                $offset = round($time_zone->get_offset($utc) / 60);
                if ($offset) {
                    $hour = floor($offset / 60);
                    $minutes = floor(abs($offset) % 60);
                    $format = sprintf('%+d', $hour);
                    if ($minutes) {
                        $format .= ':' . sprintf('%02u', $minutes);
                    }
                } else {
                    $format = '';
                }
                $offsets[] = $offset;
                $tz_info = $time_zone->get_location();
                if (is_array($tz_info) && !empty($tz_info['country_code'])) {
                    $option_data_attributes[$time_zone_ident] = ['data-country_code' => $tz_info['country_code']];
                    if (isset($iso2country[$tz_info['country_code']])) {
                        $option_data_attributes[$time_zone_ident]['data-country_name'] = $iso2country[$tz_info['country_code']];
                    }
                }
                $time_zone_select_label = 'UTC' . $format . ($abbr !== 'UTC' ? " ({$abbr})" : '') . ($time_zone_ident !== 'UTC' ? ' – ' . $time_zone_ident : '');
                $time_zones_variants[$tz_group_label][$time_zone_ident] = $time_zone_select_label;
                if (is_array($tz_info) && !empty($tz_info['country_code']) && $suggest_country_iso == $tz_info['country_code']) {
                    $suggest[$time_zone_ident] = $time_zone_select_label;
                }
            }
            array_multisort($offsets, array_keys($time_zones_variants[$tz_group_label]), $time_zones_variants[$tz_group_label]);
        }
        if (!empty($suggest)) {
            $time_zones_variants = array_merge(['Store time zones' => $suggest], $time_zones_variants);
        }
        // https://www.wikiwand.com/en/List_of_tz_database_time_zones
        $obsolete_map = ['America/Coral_Harbour' => 'America/Panama', 'America/Godthab' => 'America/Nuuk', 'America/Montreal' => 'America/Toronto', 'America/Nipigon' => 'America/Toronto', 'America/Pangnirtung' => 'America/Iqaluit', 'America/Rainy_River' => 'America/Winnipeg', 'America/Santa_Isabel' => 'America/Tijuana', 'America/Thunder_Bay' => 'America/Toronto', 'America/Yellowknife' => 'America/Edmonton', 'Asia/Chongqing' => 'Asia/Shanghai', 'Asia/Harbin' => 'Asia/Shanghai', 'Asia/Kashgar' => 'Asia/Urumqi', 'Asia/Calcutta' => 'Asia/Kolkata', 'Asia/Rangoon' => 'Asia/Yangon', 'Australia/Currie' => 'Australia/Hobart', 'Europe/Kiev' => 'Europe/Kyiv', 'Europe/Uzhgorod' => 'Europe/Kyiv', 'Europe/Zaporozhye' => 'Europe/Kyiv', 'Pacific/Enderbury' => 'Pacific/Kanton', 'Pacific/Johnston' => 'Pacific/Honolulu'];
        $js = '<script src="plugins/moment.min.js"></script>';
        //$js .= '<script src="plugins/moment-timezone/builds/moment-timezone.min.js"></script>';
        $js .= '<script src="plugins/moment-timezone.min.js"></script>';
        $js .= '<script type="text/javascript" src="plugins/timezone-picker/timezone-picker.min.js"></script>';
        ob_start();
        ?>
<script type="text/javascript">
    $('.js-complete').select2({ });
    $('.js-btn-tz-map').on('click',function () {
        bootbox.dialog({
            title: <?php 
        echo json_encode(defined('TEXT_HEAD_TIME_ZONE_POPUP') ? TEXT_HEAD_TIME_ZONE_POPUP : 'Select time zone');
        ?>,
            message:
            '<div id="tzMap" style="min-height:500px; margin-bottom: 20px"></div>' +
            '<script type="text/javascript">$("#tzMap").timezonePicker({ selectedColor: \'#2F5984\', selectBox:false, quickLink:[] }); ' +
            '$("#tzMap").data("timezonePicker").setValue($("#selTimeZones").val());' +
            '</'+'scr'+'ipt>',
            onEscape: true,
            buttons:{
                confirm: {
                    label: <?php 
        echo json_encode(defined('IMAGE_SELECT') ? IMAGE_SELECT : 'Select');
        ?>,
                    className: 'btn-success',
                    callback: function() {
                        var obsolete_map = <?php 
        echo json_encode($obsolete_map);
        ?>;
                        var selectedTZ = $("#tzMap").data("timezonePicker").getValue();
                        if ( selectedTZ && selectedTZ.length>0 ) {
                            for( var i=0; i<selectedTZ.length;i++ ){
                                var timezone_code = selectedTZ[i].timezone;
                                if (typeof obsolete_map[timezone_code] !== 'undefined') {
                                    timezone_code = obsolete_map[timezone_code];
                                }
                                if ( $("#selTimeZones option").filter('[value="'+timezone_code+'"]').length==0 ) continue;
                                $("#selTimeZones").val(timezone_code).trigger('change');
                                if ( $("#selTimeZones").val()==timezone_code ) break;
                            }
                        }
                    }
                },
                cancel: { label: <?php 
        echo json_encode(defined('TEXT_BTN_NO') ? TEXT_BTN_NO : 'No');
        ?> }
            }
        });
        //map:clicked
    });
</script>
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <?php 
        echo Html::drop_down_list('configuration_value', $key_value, $time_zones_variants, ['class' => 'select2 js-complete select2-input', 'id' => 'selTimeZones', 'options' => $option_data_attributes]);
        ?>
                </td>
            </tr>
            <tr>
                <td align="center">&nbsp;<br><button type="button" class="js-btn-tz-map btn btn-1"><?php 
        echo defined('TEXT_OPEN_MAP') ? TEXT_OPEN_MAP : 'Open map';
        ?></button></td>
            </tr>
        </table>
<?php 
        $js .= ob_get_clean();
        return $js;
        //return $js.('configuration_value', $timeZonesVariants, $key_value);
        //return $js.tep_draw_pull_down_menu('configuration_value', $timeZonesVariants, $key_value);
    }
    public static function cfg_true_get_order_status($list = '')
    {
        $default = preg_split('/[, ]/', $list, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($default)) {
            foreach ($default as $idx => $status_id) {
                $status_name = \common\helpers\Order::get_order_status_name($status_id);
                $default[$idx] = empty($status_name) ? $status_id . '?' : $status_name;
            }
            $ret = implode(', ', $default);
        } else {
            $ret = $default;
        }
        return $ret;
    }
    public static function cfg_upload_file($cfg_data)
    {
        $cfg_values = explode(',', trim(stripslashes($cfg_data)));
        $key = null;
        $value = null;
        foreach ($cfg_values as $c_value) {
            $c_value = str_replace(["'", '"'], '', trim(stripslashes($c_value)));
            if (empty($c_value)) {
                continue;
            }
            if (preg_match('/MODULE_PAYMENT.*/', $c_value)) {
                $key = $c_value;
                break;
            }
            $value = $c_value;
        }
        if ($key) {
            $id = 'configuration[' . $key . ']';
            $file = \yii\helpers\Html::file_input($id, $value, ['class' => ' file-config', 'id' => $id]) . (!empty($value) ? $value : '');
            $js = <<<EOD
            <script>
                var files = []
                handleFileSelect = function(e){
                    var _files = e.target.files;
                    for (var i = 0, f; f = _files[i]; i++) {
                        reader = new FileReader();
                        reader.onload = (function(theFile) {
                            files[e.target.name] = theFile;
                        })(f);
                        reader.readAsDataURL(f);
                    }
                }
                document.getElementById('{$id}').addEventListener('change', handleFileSelect, false);
            </script>
            EOD;
            $file .= $js;
        }
        return $file;
    }
    public static function cfg_true_set_order_status($single = true, $list = '', $key = '')
    {
        if ($single == 'true') {
            $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        } else {
            $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value[]';
        }
        $values = \common\helpers\Order::get_status();
        $default = preg_split('/[, ]/', $list, -1, PREG_SPLIT_NO_EMPTY);
        $field = '<select ' . ($single == 'true' ? '' : 'size="' . min(count($values), 5) . '" multiple="multiple" ') . ' name="' . \common\helpers\Output::output_string($name) . '"';
        for ($i = 0, $n = sizeof($values); $i < $n; $i++) {
            $field .= '<option value="' . \common\helpers\Output::output_string($values[$i]['id']) . '"';
            if (in_array($values[$i]['id'], $default)) {
                $field .= ' SELECTED';
            }
            $field .= '>' . \common\helpers\Output::output_string($values[$i]['text'], ['"' => '&quot;', '\'' => '&#039;', '<' => '&lt;', '>' => '&gt;']) . '</option>';
        }
        $field .= '</select>';
        return $field;
    }
    public static function cfg_supplier_price_selection_mode()
    {
        $keys = func_get_args();
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return \common\helpers\Html::drop_down_list($name, $value, ['Manual' => TEXT_MANUAL, 'Auto' => TEXT_AUTO]);
    }
    public static function cfg_supplier_price_rule_priority()
    {
        $keys = func_get_args();
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return \common\helpers\Html::drop_down_list($name, $value, ['Category,Brand,Supplier' => 'Category, Brand, Supplier', 'Brand,Category,Supplier' => 'Brand, Category, Supplier']);
    }
    public static function cfg_supplier_price_select()
    {
        $keys = func_get_args();
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return \common\helpers\Html::drop_down_list($name, $value, ['Disabled' => 'Disabled', 'Cheapest, In stock' => 'Cheapest, In stock', 'Supplier order' => 'Use supplier sort order', 'Based on priority rules' => 'Based on priority rules']);
    }
    public static function value_updated($key, $value)
    {
        switch ($key) {
            case 'SUPPLIER_UPDATE_PRICE_MODE':
                \common\helpers\Suppliers::on_update_price_mode_switch($value);
                break;
            default:
        }
    }
    protected static function shipping_modules_with_methods()
    {
        $modules_list = [];
        foreach (\common\helpers\Modules::shipping_modules() as $module) {
            $modules_list[$module->code] = $module->title;
            $methods = $module->possible_methods();
            if (count($methods) > 0) {
                foreach ($methods as $method_id => $method_name) {
                    $modules_list[$module->code . '_' . $method_id] = $module->title . ' : ' . $method_name;
                }
            }
        }
        return $modules_list;
    }
    public static function show_selected_shipping($value)
    {
        $modules_list = static::shipping_modules_with_methods();
        if (is_string($value) && strpos($value, ',') !== false) {
            $values = preg_split('/,\s?/', $value, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $values = [$value];
        }
        foreach ($values as $_idx => $value) {
            $values[$_idx] = isset($modules_list[$value]) ? $modules_list[$value] : $value;
        }
        return implode(', ', $values);
    }
    public static function select_shipping()
    {
        $keys = func_get_args();
        eval('list($multiple, $value, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $modules_list = static::shipping_modules_with_methods();
        return \common\helpers\Html::drop_down_list($name, $value, $modules_list, ['multiple' => $multiple]);
    }
    public static function tep_get_country_name($country_id, $lan_id = 0)
    {
        return \common\helpers\Country::get_country_name($country_id, $lan_id);
    }
    public static function cfg_multi_sortable()
    {
        $keys = func_get_args();
        $possible_values = [];
        $string = $key_value = $key = '';
        eval('list($possible_values, $key_value, $key) = array(' . $keys[0] . ');');
        $key_values = explode(', ', $key_value);
        $string .= '  <script>
                      $( function() {
                        $( ".sortable" ).sortable();
                        $( ".sortable" ).disableSelection();
                        $(".uniform").uniform();
                      } );
                    </script>';
        $string .= '<ul class="sortable">';
        $tmp = array_diff($possible_values, $key_values);
        $possible_values = array_merge($key_values, $tmp);
        for ($i = 0, $n = count($possible_values); $i < $n; $i++) {
            $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
            $string .= '<li ><span class="handle"><i class="icon-hand-paper-o"></i></span>';
            $string .= '<div class="name">';
            $string .= \common\helpers\Html::checkbox($name, in_array($possible_values[$i], $key_values), ['value' => $possible_values[$i], 'id' => 'conf_val_' . $possible_values[$i]]);
            $string .= '<label for="conf_val_' . $possible_values[$i] . '">';
            $string .= $possible_values[$i];
            $string .= '</label></div>';
            $string .= '</li>';
        }
        $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
        $string .= '</ul>';
        return $string;
    }
    protected static function variants_checkout_recalculate_fields()
    {
        return ['street_address' => ENTRY_STREET_ADDRESS, 'suburb' => ENTRY_SUBURB, 'city' => ENTRY_CITY, 'postcode' => ENTRY_POST_CODE, 'state' => ENTRY_STATE, 'country' => ENTRY_COUNTRY];
    }
    public static function set_checkout_recalculate_fields()
    {
        $keys = func_get_args();
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $value = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return '<div>' . \common\helpers\Html::checkbox_list($name, $value, static::variants_checkout_recalculate_fields(), ['separator' => "<br />\n", 'class' => 'js-checkout-fields text-left']) . '</div>' . \common\helpers\Html::hidden_input($name . '[]', 'country') . '<script>$(document).ready(function(){ $(\'.js-checkout-fields input[value="country"]\').attr(\'disabled\', \'disabled\') })</script>';
    }
    public static function get_checkout_recalculate_fields($value)
    {
        $values = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $variants = static::variants_checkout_recalculate_fields();
        foreach ($values as $idx => $value) {
            if (isset($variants[$value])) {
                $values[$idx] = $variants[$value];
            }
        }
        return implode(', ', $values);
    }
    public static function variants_backend_product_name()
    {
        return ['Listing' => 'Product Listing', 'Orders' => 'Orders Detail', 'PackingSlip' => 'Packing Slip', 'Invoice' => 'Invoice'];
    }
    public static function get_backend_product_name($value)
    {
        $values = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $variants = static::variants_backend_product_name();
        foreach ($values as $idx => $value) {
            if (isset($variants[$value])) {
                $values[$idx] = $variants[$value];
            }
        }
        return implode(', ', $values);
    }
    public static function set_backend_product_name()
    {
        $keys = func_get_args();
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $value = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return '<div>' . \common\helpers\Html::checkbox_list($name, $value, static::variants_backend_product_name(), ['separator' => "<br />\n", 'class' => 'text-left']) . '</div>';
    }
    public static function get_listing_sort_order($value)
    {
        $values = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $variants = \common\helpers\Sorting::get_possible_sort_options();
        foreach ($values as $idx => $value) {
            if (isset($variants[$value])) {
                $values[$idx] = $variants[$value];
            }
        }
        return implode(', ', $values);
    }
    public static function set_listing_sort_order()
    {
        $keys = func_get_args();
        $value = $key = '';
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $value = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        return '<div>' . \common\helpers\Html::radio_list($name, $value, $variants = \common\helpers\Sorting::get_possible_sort_options(), ['separator' => "<br />\n", 'class' => 'text-left']) . '</div>';
    }
    public static function get_auto_complete_field(...$arguments)
    {
        try {
            /** @var callable $func */
            /** @var string $value */
            /** @var string $key */
            eval('[$func, $value, $key] = [' . $arguments[0] . '];');
            $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
            $params = $func([]);
            $script = <<<JS
                        var params{$key} = {$params};
                        \$('#id_{$key}').autocomplete({
                            source: function (req, res) {
                                var words = req.term.split(' ');
                                var results = \$.grep(params{$key}, function(item, index) {
                                    var sentence = item.toLowerCase();
                                    return words.every(function(word) {
                                        return sentence.indexOf(word.toLowerCase()) >= 0;
                                    });
                                });
                                res(results);
                            },
                            appendTo: '#id{$key}Wrap',
                            autoFocus: true,
                            delay: 0,
                            minLength: 0,
                        }).focus(function () {
                            \$(this).autocomplete("search",\$(this).val());
                        });
            JS;
            \Yii::$app->get_view()->register_js($script);
            return sprintf('%s<div id="id%sWrap" style="position: relative;"></div>', \common\helpers\Html::Input('text', $name, $value, ['class' => 'form-control', 'id' => 'id_' . $key]), $key);
        } catch (\Exception $e) {
            return $e->get_message();
        }
    }
    public static function get_drop_down_field(...$arguments)
    {
        try {
            /** @var callable $func */
            /** @var string $value */
            /** @var string $key */
            eval('[$func, $value, $key] = [' . $arguments[0] . '];');
            $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
            $params = $func([]);
            return sprintf('%s', \common\helpers\Html::drop_down_list($name, $value, $params, ['class' => 'form-control', 'id' => 'id_' . $key]));
        } catch (\Exception $e) {
            return $e->get_message();
        } catch (\Throwable $e) {
            return $e->get_message();
        }
    }
    public static function get_drop_down_depend_field(...$arguments)
    {
        try {
            /** @var callable $func */
            /** @var string $keyDepend */
            /** @var string $value */
            /** @var string $key */
            eval('[$func, $keyDepend, $value, $key] = [' . $arguments[0] . '];');
            $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
            $key_depend = 'id_' . $key_depend;
            $params = $func([]);
            $script = <<<JS
                        var params{$key} = {$params};
                        \$('#{$key_depend}').on('change', function() {
                          \$('#id_{$key}').html('');
                          /*
                            WARNING reserved property
                            item.id
                            item.depend
                            item.text
                           */
                          \$.each(params{$key}, function (index, item) {
                              if (item.depend ===  \$('#{$key_depend}').val()){
                                var option = \$("<option></option>")
                                \$('#id_{$key}').append(option);
                                option.attr('value', item.id).text(item.text);
                                if (item.id === '{$value}') {
                                    option.prop('selected', true);
                                }
                              }
                            });
                        });
                        \$('#{$key_depend}').change();
            JS;
            \Yii::$app->get_view()->register_js($script);
            return sprintf('%s', \common\helpers\Html::drop_down_list($name, $value, [], ['class' => 'form-control', 'id' => 'id_' . $key]));
        } catch (\Exception $e) {
            return $e->get_message();
        }
    }
    public static function get_auto_complete_ajax_depend_field(...$arguments)
    {
        try {
            /** @var callable $func */
            /** @var string $keyDepend */
            /** @var string $method */
            /** @var string $suggestProperty */
            /** @var string $value */
            /** @var string $key */
            eval('[$func, $keyDepend, $method, $suggestProperty, $value, $key] = [' . $arguments[0] . '];');
            $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
            $key_depend = 'id_' . $key_depend;
            $method = mb_strtolower($method);
            $url = $func([]);
            $script = <<<JS
                          \$('#{$key_depend}').on('change', function() {
                            if (!\$('#{$key_depend}').val()) {
                                return false;
                            }
                            \$('#id_{$key}').html('');
                            \$.{$method}('{$url}', {
                                "{$suggest_property}": \$('#{$key_depend}').val(),
                            }, function (response) {
                                /*
                                    WARNING reserved property
                                    response.success
                                    response.data = [
                                        [
                                            id,
                                            text
                                        ]
                                    ]
                                */
                                if (response.success && response.data) {
                                    \$.each(response.data, function (index, item) {
                                        var option = \$("<option></option>")
                                        \$('#id_{$key}').append(option);
                                        option.attr('value', item.id).text(item.text);
                                        if (item.id === '{$value}') {
                                            option.prop('selected', true);
                                        }
                                    });
                                }
                            });
                          });
                          \$('#{$key_depend}').change();
            JS;
            \Yii::$app->get_view()->register_js($script);
            return sprintf('%s', \common\helpers\Html::drop_down_list($name, $value, [], ['class' => 'form-control', 'id' => 'id_' . $key]));
        } catch (\Exception $e) {
            return $e->get_message();
        }
    }
    public static function input_with_choice(...$arguments)
    {
        try {
            /** @var callable $func */
            /** @var string $value */
            /** @var string $key */
            eval('[$func, $value, $key] = [' . $arguments[0] . '];');
            $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
            $options = $func($value);
            $script = <<<JS
                            var {$key}_field = \$(".{$key}inputField");
                            var {$key}_result = \$(".{$key}input");
                            if (\$('.{$key}radio:checked').attr('data-disable') === "1") {
                                {$key}_field.hide();
                            }
                            \$(".{$key}radio").on('click', function() {
                                var current = \$(this);
                                if (current.attr('data-disable') === "1") {
                                    {$key}_field.hide();
                                } else {
                                    {$key}_field.show();
                                }
                                if (current.attr('data-change') === "direct") {
                                    {$key}_result.val(current.val());
                                }
                                if (current.attr('data-change') === "lazy") {
                                    {$key}_result.val({$key}_result.val());
                                }
                            });
                            {$key}_field.on('keyup change', function() {
                              {$key}_result.val({$key}_field.val());
                            });
            JS;
            $text = '';
            foreach ($options as $option_name => $option) {
                $text .= '<div style="display: block;text-align: left;padding: 0;"><label>' . \common\helpers\Html::radio("{$name}radio", $option['value'] === $value, ['data-disable' => $option['disableInput'], 'data-change' => $option['changeInput'], 'value' => $option['value'], 'class' => "uniform {$key}radio", 'id' => "id_{$key}_{$option_name}"]) . ' ' . $option_name . '</label></div>';
            }
            $text .= '<div style="display: block;text-align: left;padding: 0;">' . \common\helpers\Html::input('text', "{$name}inputField", $value, ['class' => "form-control {$key}inputField"]) . \common\helpers\Html::input('hidden', $name, $value, ['class' => "form-control {$key}input"]) . '</div>';
            return $text . "<script>{$script}</script>";
        } catch (\Exception $e) {
            //return  $e->getMessage();
        }
    }
    public static function get_tax_address_by($value)
    {
        $values = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        $variants = self::$tax_address_options;
        foreach ($values as $idx => $value) {
            if (isset($variants[$value])) {
                $values[$idx] = $variants[$value];
            }
        }
        return implode(', ', $values);
    }
    public static function set_tax_address_by()
    {
        $keys = func_get_args();
        $value = $key = '';
        $value = $keys[0]['value'];
        $key = $keys[0]['key'];
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $value = preg_split('/,\s?/', strval($value), -1, PREG_SPLIT_NO_EMPTY);
        return '<div>' . \common\helpers\Html::radio_list($name, $value, $variants = self::$tax_address_options, ['separator' => "<br />\n", 'class' => 'text-left']) . '</div>';
    }
    public static function upsxml_cfg_select_multioption_indexed()
    {
        $string = '';
        $keys = func_get_args();
        eval('list($select_array, $key_value, $key) = array(' . $keys[0] . ');');
        for ($i = 0; $i < sizeof($select_array); $i++) {
            $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
            $string .= '<br><input type="checkbox" name="' . $name . '" value="' . $select_array[$i] . '"';
            $key_values = explode(', ', $key_value);
            if (in_array($select_array[$i], $key_values)) {
                $string .= ' CHECKED';
            }
            $string .= '> ' . constant('UPSXML_' . trim($select_array[$i]));
        }
        $name = $key ? 'configuration[' . $key . '][]' : 'configuration_value';
        $string .= '<input type="hidden" name="' . $name . '" value="--none--">';
        return $string;
    }
    public static function get_drop_down_image_types()
    {
        $keys = func_get_args();
        $value = $key = '';
        eval('list($value, $key) = array(' . $keys[0] . ');');
        $name = $key ? 'configuration[' . $key . ']' : 'configuration_value';
        $select_array = [];
        foreach (\common\classes\Images::get_image_types() as $type) {
            $select_array[$type['image_types_name']] = sprintf('%s (%sx%s)', $type['image_types_name'], $type['image_types_x'], $type['image_types_y']);
        }
        return Html::drop_down_list($name, $value, $select_array, ['class' => 'form-control', 'options' => []]);
    }
}