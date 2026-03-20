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
namespace backend\controllers;

use Yii;
/**
 * default controller to handle user requests.
 */
class Zones_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_LOCATION', 'BOX_TAXES_ZONES'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'locations', 'zones'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('zones/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="#" class="btn btn-primary" onclick="return zoneEdit(0)">' . TEXT_INFO_HEADING_NEW_ZONE . '</a>';
        $this->view->zones_table = [['title' => TABLE_HEADING_COUNTRY_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_ZONE_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_ZONE_CODE, 'not_important' => 0]];
        return $this->render('index');
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $c_id = Yii::$app->request->get('cID', 0);
        $search = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_prepare_input($_GET['search']['value']);
            $search = " and (c.countries_name like '%" . tep_db_input($keywords) . "%' or c.countries_iso_code_2 like '%" . tep_db_input($keywords) . "%' or c.countries_iso_code_3 like '%" . tep_db_input($keywords) . "%' or z.zone_name like '%" . tep_db_input($keywords) . "%' or z.zone_code like '%" . tep_db_input($keywords) . "%')";
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'c.countries_name ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 1:
                    $order_by = 'z.zone_name ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 2:
                    $order_by = 'z.zone_code ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                default:
                    $order_by = 'c.sort_order, cd.categories_name';
                    break;
            }
        } else {
            $order_by = 'c.countries_name, z.zone_name';
        }
        $current_page_number = $start / $length + 1;
        $response_list = [];
        $zones_query_raw = 'select z.zone_id, c.countries_id, c.countries_name, z.zone_name, z.zone_code, z.zone_country_id from ' . TABLE_ZONES . ' z, ' . TABLE_COUNTRIES . " c where z.zone_country_id = c.countries_id and c.language_id = '" . $languages_id . "' " . $search . ' order by ' . $order_by;
        $zones_split = new \Split_Page_Results($current_page_number, $length, $zones_query_raw, $zones_query_numrows);
        $zones_query = tep_db_query($zones_query_raw);
        while ($zones = tep_db_fetch_array($zones_query)) {
            $response_list[] = [$zones['countries_name'] . tep_draw_hidden_field('id', $zones['zone_id'], 'class="cell_identify"'), $zones['zone_name'], $zones['zone_code']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $zones_query_numrows, 'recordsFiltered' => $zones_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_zonesactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/zones');
        $zones_id = Yii::$app->request->post('zones_id', 0);
        $this->layout = false;
        if ($zones_id) {
            $zone = tep_db_fetch_array(tep_db_query('select z.zone_id, c.countries_id, c.countries_name, z.zone_name, z.zone_code, z.zone_country_id from ' . TABLE_ZONES . ' z, ' . TABLE_COUNTRIES . " c where z.zone_country_id = c.countries_id and c.language_id = '" . $languages_id . "' and z.zone_id = '" . (int) $zones_id . "'"));
            $c_info = new \Object_Info($zone, false);
            echo '<div class="or_box_head">' . $c_info->zone_name . '</div>';
            echo '<div class="row_or_wrapp">';
            echo '<div class="row_or"><div>' . TEXT_INFO_ZONES_NAME . '</div><div>' . $c_info->zone_name . ' (' . $c_info->zone_code . ')</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_COUNTRY_NAME . '</div><div>' . $c_info->countries_name . '</div></div>';
            echo '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order"><button class="btn btn-edit btn-no-margin" onclick="zoneEdit(' . $zones_id . ')">' . IMAGE_EDIT . '</button><button class="btn btn-delete" onclick="zoneDelete(' . $zones_id . ')">' . IMAGE_DELETE . '</button></div>';
        }
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/zones');
        $zones_id = Yii::$app->request->get('zones_id', 0);
        $zone = tep_db_fetch_array(tep_db_query('select z.zone_id, c.countries_id, c.countries_name, z.zone_name, z.zone_code, z.zone_country_id from ' . TABLE_ZONES . ' z, ' . TABLE_COUNTRIES . " c where z.zone_country_id = c.countries_id and c.language_id = '" . $languages_id . "' and z.zone_id = '" . (int) $zones_id . "'"));
        $c_info = new \Object_Info($zone, false);
        $c_info->zone_id = $c_info->zone_id ?? null;
        echo tep_draw_form('zones', FILENAME_ZONES, 'page=' . \Yii::$app->request->get('page') . '&cID=' . $c_info->zone_id . '&action=save');
        if ($zones_id) {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_EDIT_ZONE . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_NEW_ZONE . '</div>';
        }
        echo '<div class="col_desc">' . TEXT_INFO_EDIT_INTRO . '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . TEXT_INFO_ZONES_NAME . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('zone_name', $c_info->zone_name ?? null) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . TEXT_INFO_ZONES_CODE . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('zone_code', $c_info->zone_code ?? null) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . TEXT_INFO_COUNTRY_NAME . '</div>';
        echo '<div class="main_value">' . \common\helpers\Html::drop_down_list('zone_country_id', $c_info->countries_id ?? null, \common\helpers\Country::new_get_countries('', true)) . '</div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order"><input type="button" value="' . IMAGE_UPDATE . '" class="btn btn-no-margin" onclick="zoneSave(' . ($c_info->zone_id ? $c_info->zone_id : 0) . ')"><input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement()"></div>';
        echo '</form>';
    }
    public function action_save()
    {
        global $language;
        \common\helpers\Translation::init('admin/zones');
        $zones_id = Yii::$app->request->get('zones_id', 0);
        if ($zones_id == 0) {
            $zone_country_id = tep_db_prepare_input($_POST['zone_country_id']);
            $zone_code = tep_db_prepare_input($_POST['zone_code']);
            $zone_name = tep_db_prepare_input($_POST['zone_name']);
            tep_db_query('insert into ' . TABLE_ZONES . " (zone_country_id, zone_code, zone_name) values ('" . (int) $zone_country_id . "', '" . tep_db_input($zone_code) . "', '" . tep_db_input($zone_name) . "')");
            $action = 'added';
        } else {
            $zone_country_id = tep_db_prepare_input($_POST['zone_country_id']);
            $zone_code = tep_db_prepare_input($_POST['zone_code']);
            $zone_name = tep_db_prepare_input($_POST['zone_name']);
            tep_db_query('update ' . TABLE_ZONES . " set zone_country_id = '" . (int) $zone_country_id . "', zone_code = '" . tep_db_input($zone_code) . "', zone_name = '" . tep_db_input($zone_name) . "' where zone_id = '" . (int) $zones_id . "'");
            $action = 'updated';
        }
        echo json_encode(['message' => 'County ' . $action, 'messageType' => 'alert-success']);
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/zones');
        $zones_id = Yii::$app->request->post('zones_id', 0);
        if ($zones_id) {
            tep_db_query('delete from ' . TABLE_ZONES . " where zone_id = '" . (int) $zones_id . "'");
        }
        echo 'reset';
    }
}