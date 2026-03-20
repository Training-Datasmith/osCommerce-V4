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
namespace backend\controllers;

use common\helpers\Currencies as CurrenciesHelper;
use Yii;
/**
 * default controller to handle user requests.
 */
class Currencies_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_LOCALIZATION', 'BOX_LOCALIZATION_CURRENCIES'];
    public static $price_tables = [TABLE_PRODUCTS_PRICES => [['products_group_price', 'bonus_points_price', 'bonus_points_cost', 'products_group_price_pack_unit', 'products_group_price_packaging', 'products_price_configurator', 'shipping_surcharge_price'], ['products_group_discount_price', 'products_group_discount_price_pack_unit', 'products_group_discount_price_packaging']], TABLE_INVENTORY_PRICES => [['inventory_group_price', 'inventory_full_price'], ['inventory_group_discount_price', 'inventory_discount_full_price']], TABLE_OPTIONS_TEMPLATES_ATTRIBUTES_PRICES => [['attributes_group_price'], ['attributes_group_discount_price']], TABLE_PRODUCTS_ATTRIBUTES_PRICES => [['attributes_group_price'], ['attributes_group_discount_price']], TABLE_SPECIALS_PRICES => [['specials_new_products_price'], []], TABLE_VIRTUAL_GIFT_CARD_PRICES => [['products_price'], []], TABLE_GIFT_WRAP_PRODUCTS => [['gift_wrap_price'], []]];
    public static $group_price_tables = [TABLE_PRODUCTS_PRICES => ['from' => TABLE_PRODUCTS, 'fields' => ['groups_id' => 0, 'currencies_id' => 0, 'products_id' => 'products_id', 'products_group_price' => 'products_price', 'bonus_points_price' => 'bonus_points_price', 'bonus_points_cost' => 'bonus_points_cost', 'products_group_price_pack_unit' => 'products_price_pack_unit', 'products_price_configurator' => 'products_price_configurator', 'products_group_price_packaging' => 'products_price_packaging', 'shipping_surcharge_price' => 'shipping_surcharge_price', 'products_group_discount_price' => 'products_price_discount', 'products_group_discount_price_pack_unit' => 'products_price_discount_pack_unit', 'products_group_discount_price_packaging' => 'products_price_discount_packaging']], TABLE_INVENTORY_PRICES => ['from' => TABLE_INVENTORY, 'fields' => ['groups_id' => 0, 'currencies_id' => 0, 'products_id' => 'products_id', 'prid' => 'prid', 'inventory_id' => 'inventory_id', 'inventory_group_price' => 'inventory_price', 'inventory_full_price' => 'inventory_full_price', 'inventory_group_discount_price' => 'inventory_discount_price', 'inventory_discount_full_price' => 'inventory_discount_full_price', 'price_prefix' => 'price_prefix']], TABLE_OPTIONS_TEMPLATES_ATTRIBUTES_PRICES => ['from' => TABLE_OPTIONS_TEMPLATES_ATTRIBUTES, 'fields' => ['groups_id' => 0, 'currencies_id' => 0, 'options_templates_attributes_id' => 'options_templates_attributes_id', 'attributes_group_price' => 'options_values_price', 'attributes_group_discount_price' => 'products_attributes_discount_price']], TABLE_PRODUCTS_ATTRIBUTES_PRICES => ['from' => TABLE_PRODUCTS_ATTRIBUTES, 'fields' => ['groups_id' => 0, 'currencies_id' => 0, 'products_attributes_id' => 'products_attributes_id', 'attributes_group_price' => 'options_values_price', 'attributes_group_discount_price' => 'products_attributes_discount_price']], TABLE_SPECIALS_PRICES => ['from' => TABLE_SPECIALS, 'fields' => ['groups_id' => 0, 'currencies_id' => 0, 'specials_id' => 'specials_id', 'specials_new_products_price' => 'specials_new_products_price']]];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'localization', 'currencies'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('currencies/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        //        $this->topButtons[] = '<a href="currencies/update" class="btn btn-primary">' . TEXT_UPDATE_CURRENCIES . '</a>';
        $this->top_buttons[] = '<a href="#" class="btn btn-primary" onclick="return currencyEdit(0)">' . TEXT_INFO_HEADING_NEW_CURRENCY . '</a>';
        $this->view->currencies_table = [['title' => TABLE_HEADING_CURRENCY_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_CURRENCY_CODES, 'not_important' => 0], ['title' => TABLE_HEADING_CURRENCY_VALUE, 'not_important' => 0], ['title' => TABLE_HEADING_STATUS, 'not_important' => 0]];
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        return $this->render('index', ['messages' => $messages]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $c_id = Yii::$app->request->get('cID', 0);
        if ($length == -1) {
            $length = 1000;
        }
        $search = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search = " and (title like '%" . tep_db_input($keywords) . "%' or code like '%" . tep_db_input($keywords) . "%')";
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'title ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 1:
                    $order_by = 'code ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                default:
                    $order_by = 'title';
                    break;
            }
        } else {
            $order_by = 'sort_order, title';
        }
        $current_page_number = $start / $length + 1;
        $response_list = [];
        $currency_query_raw = 'select currencies_id, title, code, symbol_left, symbol_right, decimal_point, thousands_point, decimal_places, last_updated, value, status from ' . TABLE_CURRENCIES . ' where 1 ' . $search . ' order by ' . $order_by;
        $currency_query_numrows = false;
        $currency_split = new \Split_Page_Results($current_page_number, $length, $currency_query_raw, $currency_query_numrows);
        $currency_query = tep_db_query($currency_query_raw);
        while ($currency = tep_db_fetch_array($currency_query)) {
            $response_list[] = ['<div class="handle_cat_list"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="cat_name cat_name_attr cat_no_folder">' . (DEFAULT_CURRENCY == $currency['code'] ? '<b>' . $currency['title'] . ' (' . TEXT_DEFAULT . ')</b>' : $currency['title']) . tep_draw_hidden_field('id', $currency['currencies_id'], 'class="cell_identify"') . '<input class="cell_type" type="hidden" value="curr" >', $currency['code'], number_format($currency['value'], 8), '<input type="checkbox" value="' . $currency['currencies_id'] . '" name="status" class="check_on_off"' . ($currency['status'] == 1 ? ' checked="checked"' : '') . '>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $currency_query_numrows, 'recordsFiltered' => $currency_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_currencyactions()
    {
        \common\helpers\Translation::init('admin/currencies');
        $currencies = Yii::$container->get('currencies');
        $currencies_id = Yii::$app->request->post('currencies_id', 0);
        $this->layout = false;
        if ($currencies_id) {
            $currency = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_CURRENCIES . " where currencies_id ='" . (int) $currencies_id . "'"));
            $c_info = new \Object_Info($currency, false);
            echo '<div class="or_box_head">' . $c_info->title . '</div>';
            echo '<div class="row_or_wrapp">';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_TITLE . '</div><div>' . $c_info->title . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_CODE . '</div><div>' . $c_info->code . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_CODE_NUMERIC . '</div><div>' . $c_info->code_number . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_SYMBOL_LEFT . '</div><div>' . $c_info->symbol_left . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_SYMBOL_RIGHT . '</div><div>' . $c_info->symbol_right . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_DECIMAL_POINT . '</div><div>' . $c_info->decimal_point . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_THOUSANDS_POINT . '</div><div>' . $c_info->thousands_point . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_DECIMAL_PLACES . '</div><div>' . $c_info->decimal_places . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_LAST_UPDATED . '</div><div>' . \common\helpers\Date::date_short($c_info->last_updated) . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_VALUE . '</div><div>' . number_format($c_info->value, 8) . '</div></div>';
            if (tep_not_null($c_info->code) && tep_not_null(DEFAULT_CURRENCY)) {
                echo '<div class="row_or"><div>' . TEXT_INFO_CURRENCY_EXAMPLE . '</div><div>' . $currencies->format('30', false, DEFAULT_CURRENCY) . ' = ' . $currencies->format('30', true, $c_info->code) . '</div></div>';
            }
            echo '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button class="btn btn-edit btn-no-margin" onclick="currencyEdit(' . $currencies_id . ')">' . IMAGE_EDIT . '</button><button class="btn btn-delete" onclick="currencyDelete(' . $currencies_id . ')">' . IMAGE_DELETE . '</button>';
            if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed() && $c_info->code == DEFAULT_CURRENCY) {
                echo '<button class="btn btn-edit btn-no-margin" onclick="currencyRecalculate(\'' . $c_info->code . '\')">' . IMAGE_RECALCULATE . '</button>';
            }
            echo '</div>';
        }
    }
    public function action_recalculate()
    {
        \common\helpers\Translation::init('admin/currencies');
        $currencies_code = Yii::$app->request->get('currenciesCode', '');
        $switch_on_marketing = Yii::$app->request->post('switchOnMarketing', '');
        $switch_off_marketing = Yii::$app->request->post('switchOffMarketing', '');
        $round_type = Yii::$app->request->post('roundType', 'ceil');
        $round_to = Yii::$app->request->post('roundTo', '');
        $qty_discount = Yii::$app->request->post('qtyDiscount', 'donotchange');
        $process_tables = Yii::$app->request->post('processTables', []);
        $gross_tax = Yii::$app->request->post('grossTax', 0);
        if (count($process_tables) == 0) {
            $process_tables = array_keys(self::$price_tables);
        }
        if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed() && $currencies_code == DEFAULT_CURRENCY) {
            if ($currencies_code != '') {
                $currencies = Yii::$container->get('currencies');
                if (isset($currencies->currencies[$currencies_code]) && isset($currencies->currencies[$currencies_code]['value']) && (float) $currencies->currencies[$currencies_code]['value'] > 0) {
                    $currency_id = $currencies->currencies[$currencies_code]['id'];
                    $def_currency_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
                    if ($currency_id == $def_currency_id) {
                        /// update 0 to currencies_id and vise versa in all prices tables first.
                        if ($switch_on_marketing) {
                            //if groups is off the 0 currency could be not entered. Insert from main tables.
                            foreach (self::$group_price_tables as $table => $details) {
                                $ins = 'insert ignore into ' . $table . ' (';
                                $frm = ' select ';
                                foreach ($details['fields'] as $to => $from) {
                                    $ins .= $to . ',';
                                    $frm .= $from . ',';
                                }
                                $ins = substr($ins, 0, -1) . ')';
                                $frm = substr($frm, 0, -1) . ' from ' . $details['from'];
                                tep_db_query($ins . $frm);
                            }
                            if (USE_MARKET_PRICES == 'True') {
                                foreach (array_keys(self::$price_tables) as $table) {
                                    tep_db_query('update IGNORE ' . $table . " set currencies_id='" . $def_currency_id . "' where currencies_id=0 ");
                                    //danger or save trash forever :(
                                    tep_db_query('delete from ' . $table . ' where currencies_id=0 ');
                                }
                            }
                        }
                        if ($switch_off_marketing) {
                            foreach (array_keys(self::$price_tables) as $table) {
                                tep_db_query('update ' . $table . " set currencies_id=0 where currencies_id='" . $def_currency_id . "' on duplicate currencies_id='" . $def_currency_id . "' ");
                            }
                        }
                    } else {
                        // not default currency
                        $rate = $currencies->currencies[$currencies_code]['value'];
                        $decimals = $currencies->currencies[$currencies_code]['decimal_places'];
                        if ((float) $gross_tax > 0) {
                            $rate *= 1 + (float) $gross_tax / 100;
                        }
                        $expression = '';
                        switch ($round_type) {
                            case 'ceil':
                                $expression .= 'ceil( ##FIELD## * ' . $rate . ') ';
                                break;
                            case 'round':
                                $expression .= 'round( ##FIELD## * ' . $rate . ", {$decimals}) ";
                                break;
                            case 'floor':
                                $expression .= 'floor( ##FIELD## * ' . $rate . ') ';
                                break;
                        }
                        switch ($round_to) {
                            case '.99':
                                $expression .= '-0.01';
                                break;
                            case '.95':
                                $expression .= '-0.05';
                                break;
                            case '.90':
                                $expression .= '-0.10';
                                break;
                            default:
                                if ($round_to != '') {
                                    $round_to = (float) $round_to;
                                    if ($round_to == 0) {
                                        $expression = ' round(' . $expression . ')';
                                    } else {
                                        $expression = '/**/if/**/(' . $expression . '>' . $round_to . '/**/,/**/' . $round_to . ' * round(' . $expression . '/' . $round_to . ')/***/,/***/ ' . $round_to . ')';
                                    }
                                }
                        }
                        if ((float) $gross_tax > 0) {
                            $expression = '(' . $expression . ')/' . (1 + (float) $gross_tax / 100);
                        }
                        $expression_eval = str_replace(['/**/if/**/', '/**/,/**/', '/***/,/***/'], ['', '?', ':'], $expression);
                        $expression = 'if(##FIELD## >0, ' . $expression . ', ##FIELD## )';
                        foreach (self::$price_tables as $table => $fields) {
                            if (!in_array($table, $process_tables)) {
                                continue;
                            }
                            $columns = $pri_fields = [];
                            $res = tep_db_query("SHOW COLUMNS FROM `{$table}`");
                            if (!$res) {
                                continue;
                            }
                            while ($field = tep_db_fetch_array($res)) {
                                if ($field['Extra'] != 'auto_increment') {
                                    $columns[$field['Field']] = $field;
                                    if ($field['Key'] == 'PRI') {
                                        $pri_fields[] = $field['Field'];
                                    }
                                }
                            }
                            if (count($pri_fields) == 0) {
                                //gift_wrap_products mlya
                                $res = tep_db_query("SHOW indexes FROM `{$table}` WHERE Non_unique=0 and Key_name<>'Primary'");
                                if (!$res) {
                                    continue;
                                }
                                $a = [];
                                $tmp = false;
                                while ($field = tep_db_fetch_array($res)) {
                                    $a[$field['Key_name']][] = $field['Column_name'];
                                    if ($field['Column_name'] == 'currencies_id') {
                                        $tmp = $field['Key_name'];
                                    }
                                }
                                if ($tmp !== false && isset($a[$tmp])) {
                                    $pri_fields = $a[$tmp];
                                } else {
                                    // no primary field to update
                                    continue;
                                }
                            }
                            $cols['target'] = array_diff(array_keys($columns), array_merge($fields[0], $fields[1], ['currencies_id']));
                            $cols['source'] = $cols['target'];
                            $cols['target'][] = 'currencies_id';
                            $cols['source'][] = (int) $currency_id;
                            $cols['onduplicate'] = [];
                            foreach ($fields[0] as $field) {
                                $cols['target'][] = $field;
                                $cols['source'][] = str_replace('##FIELD##', $field, $expression);
                                $cols['onduplicate'][] = $field . ' = ' . str_replace('##FIELD##', 't1.' . $field, $expression);
                            }
                            if (is_array($fields[1]) && in_array($qty_discount, ['reset', 'update'])) {
                                // q-ty discount fields
                                foreach ($fields[1] as $field) {
                                    // reset first
                                    $cols['target'][] = $field;
                                    $cols['source'][] = "''";
                                    $cols['onduplicate'][] = $field . " = ''";
                                }
                            }
                            $sql = 'insert into ' . $table . ' (' . implode(', ', $cols['target']) . ') ' . ' select distinct ' . implode(', ', $cols['source']) . ' from ' . $table . ' t1 where currencies_id in (' . (int) $def_currency_id . ', 0)' . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $cols['onduplicate']) . '';
                            //echo "$sql ;\n";
                            tep_db_query($sql);
                            if (count($fields[1]) > 0 && in_array($qty_discount, ['update'])) {
                                // q-ty discount fields
                                // recalculate
                                $r = tep_db_query('select * from ' . $table . ' where (' . implode("<>'' or ", $fields[1]) . "<>'') and currencies_id in (" . (int) $def_currency_id . ', 0)');
                                while ($data = tep_db_fetch_array($r)) {
                                    $sql = ' update ' . $table . ' set ';
                                    foreach ($fields[1] as $field) {
                                        $discount_str = '';
                                        if (trim($data[$field]) != '') {
                                            $tmp = \common\helpers\Product::parse_qty_discount_array($data[$field]);
                                            if (is_array($tmp)) {
                                                foreach ($tmp as $qty => $value) {
                                                    $val = false;
                                                    eval('$val = ' . str_replace('##FIELD##', $value, $expression_eval) . ';');
                                                    if ($val !== false) {
                                                        $discount_str .= $qty . ':' . $val . ';';
                                                    }
                                                }
                                            }
                                        }
                                        $sql .= $field . "='" . tep_db_input($discount_str) . "', ";
                                    }
                                    $sql = substr($sql, 0, -2);
                                    $sql .= ' where 1 ';
                                    foreach ($pri_fields as $field) {
                                        if ($field != 'currencies_id') {
                                            $sql .= ' and ' . $field . "= '" . tep_db_input(tep_db_prepare_input($data[$field])) . "'";
                                        } else {
                                            $sql .= ' and ' . $field . "= '" . (int) $currency_id . "'";
                                        }
                                    }
                                    tep_db_query($sql);
                                }
                            }
                        }
                    }
                }
            }
        }
        echo json_encode(['message' => TEXT_RECALCULATED, 'messageType' => 'alert-success']);
    }
    public function action_recalculate_params()
    {
        \common\helpers\Translation::init('admin/currencies');
        $this->layout = false;
        $currencies_code = Yii::$app->request->get('currenciesCode', DEFAULT_CURRENCY);
        $currencies = Yii::$container->get('currencies');
        $c_info = new \Object_Info($currencies->currencies[$currencies_code], false);
        $fix_marketing_switch = false;
        if ($currencies_code == DEFAULT_CURRENCY) {
            foreach (array_keys(self::$price_tables) as $table) {
                $r = tep_db_query('select currencies_id from ' . $table . ' where currencies_id=0 limit 1');
                if (tep_db_num_rows($r)) {
                    $fix_marketing_switch = true;
                    break;
                }
            }
        }
        if (!$fix_marketing_switch) {
            foreach (self::$group_price_tables as $table => $details) {
                $d = tep_db_fetch_array(tep_db_query('select groups_id, currencies_id, count(*) as ttl from ' . $table . ' where groups_id=0  and currencies_id in (' . (int) $currencies->currencies[DEFAULT_CURRENCY]['id'] . ', 0) group by groups_id, currencies_id order by count(*) limit 1'));
                $d1 = tep_db_fetch_array(tep_db_query('select count(*) as ttl from ' . $details['from'] . ' where 1'));
                if ($d1['ttl'] != $d['ttl']) {
                    //echo " $table " . $d['ttl'] . " != " . $d1['ttl'] . ' ' . ("select groups_id, currencies_id, count(*) as ttl from " . $table . " where groups_id=0  group by groups_id, currencies_id order by count(*) limit 1") . "\n";
                    $fix_marketing_switch = true;
                    break;
                }
            }
        }
        return $this->render('recalculate', ['cInfo' => $c_info, 'fixMarketingSwitch' => $fix_marketing_switch, 'tables' => array_keys(self::$price_tables)]);
    }
    public function action_edit()
    {
        global $language;
        \common\helpers\Translation::init('admin/currencies');
        $currencies_id = Yii::$app->request->get('currencies_id', 0);
        $currency = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_CURRENCIES . " where currencies_id ='" . (int) $currencies_id . "'"));
        $c_info = new \Object_Info($currency, false);
        $c_info->currencies_id = $c_info->currencies_id ?? null;
        $c_info->status = $c_info->status ?? null;
        echo tep_draw_form('currencies', FILENAME_CURRENCIES . '/save', 'currencies_id=' . $c_info->currencies_id . '&action=save');
        if ($currencies_id) {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_EDIT_CURRENCY . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_NEW_CURRENCY . '</div>';
        }
        echo '<div class="col_desc">' . TEXT_INFO_EDIT_INTRO . '</div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_TITLE . '</div><div class="main_value">' . tep_draw_input_field('title', $c_info->title ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_CODE . '</div><div class="main_value">' . tep_draw_input_field('code', $c_info->code ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_CODE_NUMERIC . '</div><div class="main_value">' . tep_draw_input_field('code_number', $c_info->code_number ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_SYMBOL_LEFT . '</div><div class="main_value">' . tep_draw_input_field('symbol_left', $c_info->symbol_left ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_SYMBOL_RIGHT . '</div><div class="main_value">' . tep_draw_input_field('symbol_right', $c_info->symbol_right ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_DECIMAL_POINT . '</div><div class="main_value">' . tep_draw_input_field('decimal_point', $c_info->decimal_point ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_THOUSANDS_POINT . '</div><div class="main_value">' . tep_draw_input_field('thousands_point', $c_info->thousands_point ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_DECIMAL_PLACES . '</div><div class="main_value">' . tep_draw_input_field('decimal_places', $c_info->decimal_places ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_CURRENCY_VALUE . '</div><div class="main_value">' . tep_draw_input_field('value', $c_info->value ?? null, 'class="form-control"') . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . substr(TEXT_STATUS, 0, -1) . ' (' . ($c_info->status ? IMAGE_ICON_STATUS_GREEN : IMAGE_ICON_STATUS_RED) . ')' . '&nbsp;' . tep_draw_checkbox_field('status', 1, $c_info->status) . '</div></div>';
        if (DEFAULT_CURRENCY != ($c_info->code ?? null)) {
            echo '<div class="main_bottom">' . tep_draw_checkbox_field('default') . '<span>' . TEXT_INFO_SET_AS_DEFAULT . '</span></div>';
        }
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<input type="button" value="' . IMAGE_UPDATE . '" class="btn btn-no-margin" onclick="currencySave(' . ($c_info->currencies_id ? $c_info->currencies_id : 0) . ')"><input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement()">';
        echo '</div>';
        echo '</form>';
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/currencies');
        $currency_id = Yii::$app->request->get('currencies_id', 0);
        $title = tep_db_prepare_input(\Yii::$app->request->post('title'));
        $code = tep_db_prepare_input(\Yii::$app->request->post('code'));
        $code_number = tep_db_prepare_input(\Yii::$app->request->post('code_number'));
        $symbol_left = tep_db_prepare_input(\Yii::$app->request->post('symbol_left'), false);
        $symbol_right = tep_db_prepare_input(\Yii::$app->request->post('symbol_right'), false);
        $decimal_point = tep_db_prepare_input(\Yii::$app->request->post('decimal_point'));
        $thousands_point = tep_db_prepare_input(\Yii::$app->request->post('thousands_point'));
        $decimal_places = tep_db_prepare_input(\Yii::$app->request->post('decimal_places'));
        $value = tep_db_prepare_input(\Yii::$app->request->post('value'));
        $status = tep_db_prepare_input(\Yii::$app->request->post('status'));
        $check = tep_db_query('select * from ' . TABLE_CURRENCIES . " where code = '" . $code . "'" . ($currency_id ? " and currencies_id != '" . $currency_id . "'" : ''));
        if (tep_db_num_rows($check)) {
            echo json_encode(['message' => sprintf(TEXT_CURRENCY_CODE_ALREADY_EXISTS, $code), 'messageType' => 'alert-warning']);
            exit;
        }
        if ($code == DEFAULT_CURRENCY) {
            if (!$status) {
                echo json_encode(['message' => ERROR_DEFAULT_CURRENCY_INACTIVE, 'messageType' => 'alert-danger']);
                exit;
            }
        }
        $sql_data_array = ['title' => $title, 'code' => $code, 'code_number' => $code_number, 'symbol_left' => $symbol_left, 'symbol_right' => $symbol_right, 'decimal_point' => $decimal_point, 'thousands_point' => $thousands_point, 'decimal_places' => $decimal_places, 'value' => $value, 'status' => $status];
        if ($currency_id == 0) {
            $action = 'added';
            $currency = new \common\models\Currencies();
        } elseif ($currency_id) {
            $action = 'updated';
            $currency = \common\models\Currencies::find_one(['currencies_id' => (int) $currency_id]);
        }
        $currency->set_attributes($sql_data_array, false);
        $currency->save(false);
        if (isset($_POST['default']) && $_POST['default'] == 'on') {
            tep_db_query('update ' . TABLE_CONFIGURATION . " set configuration_value = '" . tep_db_input($code) . "' where configuration_key = 'DEFAULT_CURRENCY'");
            $currency->status = 1;
            $currency->save(false);
        }
        if (!$currency->status) {
            Currencies_Helper::correct_supplier_currency($currency);
        }
        echo json_encode(['message' => 'Currency is ' . $action, 'messageType' => 'alert-success']);
    }
    public function action_update()
    {
        \common\helpers\Translation::init('admin/currencies');
        $messages = Currencies_Helper::batch_rate_update(\common\models\Currencies::find());
        $_SESSION['messages'] = $messages;
        $this->redirect(['currencies/index']);
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/currencies');
        $messages = [];
        $currencies_id = tep_db_prepare_input(Yii::$app->request->post('currencies_id'));
        $currency_query = tep_db_query('select code from ' . TABLE_CURRENCIES . " where currencies_id = '" . (int) $currencies_id . "'");
        $currency = tep_db_fetch_array($currency_query);
        $remove_currency = true;
        if ($currency['code'] == DEFAULT_CURRENCY) {
            $remove_currency = false;
            ?>
              <div class="alert fade in alert-danger">
                  <i data-dismiss="alert" class="icon-remove close"></i>
                  <span id="message_plce"><?php 
            echo ERROR_REMOVE_DEFAULT_CURRENCY;
            ?></span>
              </div>
<?php 
        }
        if (!$remove_currency) {
            echo '<input type="button" class="btn btn-primary" value="' . IMAGE_CANCEL . '" onclick="resetStatement()">';
        } else {
            $currency_query = tep_db_query('select currencies_id from ' . TABLE_CURRENCIES . " where code = '" . DEFAULT_CURRENCY . "'");
            $currency = tep_db_fetch_array($currency_query);
            if ($currency['currencies_id'] == $currencies_id) {
                tep_db_query('update ' . TABLE_CONFIGURATION . " set configuration_value = '' where configuration_key = 'DEFAULT_CURRENCY'");
            }
            tep_db_query('delete from ' . TABLE_CURRENCIES . " where currencies_id = '" . (int) $currencies_id . "'");
            echo 'reset';
        }
    }
    public function action_sort_order()
    {
        $moved_id = (int) $_POST['sort_curr'];
        $ref_array = isset($_POST['curr']) && is_array($_POST['curr']) ? array_map('intval', $_POST['curr']) : [];
        if ($moved_id && in_array($moved_id, $ref_array)) {
            // {{ normalize
            $order_counter = 0;
            $order_list_r = tep_db_query('SELECT currencies_id, sort_order ' . 'FROM ' . TABLE_CURRENCIES . ' ' . 'WHERE 1 ' . 'ORDER BY sort_order, title');
            while ($order_list = tep_db_fetch_array($order_list_r)) {
                $order_counter++;
                tep_db_query('UPDATE ' . TABLE_CURRENCIES . " SET sort_order='{$order_counter}' WHERE currencies_id='{$order_list['currencies_id']}' ");
            }
            // }} normalize
            $get_current_order_r = tep_db_query('SELECT currencies_id, sort_order ' . 'FROM ' . TABLE_CURRENCIES . ' ' . "WHERE currencies_id IN('" . implode("','", $ref_array) . "') " . 'ORDER BY sort_order');
            $ref_ids = [];
            $ref_so = [];
            while ($_current_order = tep_db_fetch_array($get_current_order_r)) {
                $ref_ids[] = (int) $_current_order['currencies_id'];
                $ref_so[] = (int) $_current_order['sort_order'];
            }
            foreach ($ref_array as $_idx => $id) {
                tep_db_query('UPDATE ' . TABLE_CURRENCIES . " SET sort_order='{$ref_so[$_idx]}' WHERE currencies_id='{$id}' ");
            }
        }
    }
    public function action_switch_status()
    {
        $id = Yii::$app->request->post('id');
        $status = Yii::$app->request->post('status');
        $currency = \common\models\Currencies::find_one(['currencies_id' => (int) $id]);
        if ($currency['code'] == DEFAULT_CURRENCY) {
            if ($status != 'true') {
                \common\helpers\Translation::init('admin/currencies');
                echo json_encode(['message' => ERROR_DEFAULT_CURRENCY_INACTIVE, 'messageType' => 'alert-danger']);
                exit;
            }
        }
        $currency->status = $status == 'true' ? 1 : 0;
        $currency->save(false);
        Currencies_Helper::correct_platform_languages();
        if (!$currency->status) {
            Currencies_Helper::correct_supplier_currency($currency);
        }
        echo json_encode(['message' => 'Currency is updated', 'messageType' => 'alert-success']);
        exit;
    }
}