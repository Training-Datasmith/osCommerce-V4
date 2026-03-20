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

use common\helpers\Html;
use Yii;
/**
 * geo_zones controller to handle user requests.
 */
class Geo_zones_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_LOCATION', 'BOX_GEO_ZONES'];
    public function action_index()
    {
        global $language;
        $this->selected_menu = ['settings', 'locations', 'geo_zones'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('geo_zones/index'), 'title' => BOX_GEO_ZONES];
        $this->top_buttons[] = '<a href="#" id="add_cat" class="btn btn-primary" onclick="return editCategory(0)">' . IMAGE_INSERT . '</a><a href="#" id="add_prop" class="create_item" onclick="return editProduct(0)" style="display: none;">' . IMAGE_INSERT . '</a>';
        $this->view->heading_title = HEADING_TITLE;
        $this->view->catalog_table = [['title' => TABLE_HEADING_GEO_ZONES, 'not_important' => 0], ['title' => TEXT_BILLING_ADDRESS, 'not_important' => 0], ['title' => ENTRY_SHIPPING_ADDRESS, 'not_important' => 0], ['title' => TEXT_TAXABLE, 'not_important' => 0]];
        $this->view->zone_table = [['title' => TABLE_HEADING_COUNTRY_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_COUNTRY_ZONE, 'not_important' => 0], ['title' => TEXT_POSTCODE_START, 'not_important' => 0], ['title' => TEXT_POSTCODE_END, 'not_important' => 0]];
        return $this->render('index');
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/geo_zones');
        $current_category_id = Yii::$app->request->get('id', 0);
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        if ($length == -1) {
            $length = 10000;
        }
        $response_list = [];
        $zones_query_numrows = 0;
        if ($current_category_id > 0) {
            $response_list[] = ['<span class="parent_cats"><i class="icon-circle"></i><i class="icon-circle"></i><i class="icon-circle"></i></span><input class="cell_identify" type="hidden" value="' . 0 . '"><input class="cell_type" type="hidden" value="parent">', '', '', ''];
            $zones_query_raw = 'select a.*, c.countries_name, z.zone_name ' . 'from ' . TABLE_ZONES_TO_GEO_ZONES . ' a ' . ' left join ' . TABLE_COUNTRIES . " c on a.zone_country_id = c.countries_id and c.language_id = '" . (int) $languages_id . "' " . ' left join ' . TABLE_ZONES . ' z on a.zone_id = z.zone_id where a.geo_zone_id = ' . $current_category_id . ' ' . 'order by c.countries_name, association_id';
            $current_page_number = $start / $length + 1;
            $zones_split = new \Split_Page_Results($current_page_number, $length, $zones_query_raw, $zones_query_numrows, 'a.association_id');
            $zones_query = tep_db_query($zones_query_raw);
            while ($zones = tep_db_fetch_array($zones_query)) {
                $response_list[] = ['<input class="cell_identify" type="hidden" value="' . $zones['association_id'] . '"><input class="cell_type" type="hidden" value="product">' . ($zones['countries_name'] ? $zones['countries_name'] : TEXT_ALL_COUNTRIES), $zones['zone_id'] ? $zones['zone_name'] : PLEASE_SELECT, $zones['postcode_start'] ? $zones['postcode_start'] : '', $zones['postcode_end'] ? $zones['postcode_end'] : ''];
            }
        } else {
            $zones_query_raw = 'select * from ' . TABLE_GEO_ZONES . ' order by geo_zone_name';
            $current_page_number = $start / $length + 1;
            $zones_split = new \Split_Page_Results($current_page_number, $length, $zones_query_raw, $zones_query_numrows, 'geo_zone_id');
            $zones_query = tep_db_query($zones_query_raw);
            while ($zones = tep_db_fetch_array($zones_query)) {
                $response_list[] = ['<div class="cat_name cat_name_attr">' . $zones['geo_zone_name'] . '<input class="cell_identify" type="hidden" value="' . $zones['geo_zone_id'] . '"><input class="cell_type" type="hidden" value="category"></div>', '<input type="checkbox" value="' . $zones['billing_status'] . '" data-id = "bill-' . $zones['geo_zone_id'] . '" class="check_on_off"' . ($zones['billing_status'] == 1 ? ' checked="checked"' : '') . '>', '<input type="checkbox" value="' . $zones['shipping_status'] . '" data-id = "ship-' . $zones['geo_zone_id'] . '" class="check_on_off"' . ($zones['shipping_status'] == 1 ? ' checked="checked"' : '') . '>', '<input type="checkbox" value="' . $zones['taxable'] . '" data-id = "tax-' . $zones['geo_zone_id'] . '" class="check_on_off"' . ($zones['taxable'] == 1 ? ' checked="checked"' : '') . '>'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $zones_query_numrows, 'recordsFiltered' => $zones_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_switch_status()
    {
        list($type, $id) = explode('-', Yii::$app->request->post('id', ''));
        $status = Yii::$app->request->post('status');
        switch ($type) {
            case 'bill':
                tep_db_query('update ' . TABLE_GEO_ZONES . " set billing_status = '" . ($status == 'true' ? 1 : 0) . "' where geo_zone_id = '" . (int) $id . "'");
                break;
            case 'ship':
                tep_db_query('update ' . TABLE_GEO_ZONES . " set shipping_status = '" . ($status == 'true' ? 1 : 0) . "' where geo_zone_id = '" . (int) $id . "'");
                break;
            case 'tax':
                tep_db_query('update ' . TABLE_GEO_ZONES . " set taxable = '" . ($status == 'true' ? 1 : 0) . "' where geo_zone_id = '" . (int) $id . "'");
                break;
            default:
                break;
        }
        echo 'reset';
        exit;
    }
    public function action_categoryactions()
    {
        \common\helpers\Translation::init('admin/geo_zones');
        $this->layout = false;
        $categories_id = Yii::$app->request->post('categories_id');
        $zones_query = tep_db_query('select geo_zone_id, geo_zone_name, geo_zone_description, last_modified, date_added from ' . TABLE_GEO_ZONES . " where geo_zone_id='" . (int) $categories_id . "'");
        $zones = tep_db_fetch_array($zones_query);
        $num_zones_query = tep_db_query('select count(*) as num_zones from ' . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . (int) $zones['geo_zone_id'] . "' group by geo_zone_id");
        $num_zones = tep_db_fetch_array($num_zones_query);
        $num_zones['num_zones'] = $num_zones['num_zones'] ?? null;
        if ($num_zones['num_zones'] > 0) {
            $zones['num_zones'] = $num_zones['num_zones'];
        } else {
            $zones['num_zones'] = 0;
        }
        $z_info = new \Object_Info($zones);
        echo '<div class="or_box_head">' . $z_info->geo_zone_name . '</div>';
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or"><div>' . TEXT_INFO_NUMBER_ZONES . '</div><div>' . $z_info->num_zones . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_DATE_ADDED . '</div><div>' . \common\helpers\Date::date_short($z_info->date_added) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_ZONE_DESCRIPTION . '</div><div>' . $z_info->geo_zone_description . '</div></div>';
        if (tep_not_null($z_info->last_modified)) {
            echo '<div class="row_or"><div>' . TEXT_INFO_LAST_MODIFIED . '</div><div>' . \common\helpers\Date::date_short($z_info->last_modified) . '</div></div>';
        }
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<button onclick="return editCategory(' . $z_info->geo_zone_id . ')" class="btn btn-no-margin btn-primary btn-edit">' . IMAGE_EDIT . '</button>';
        echo '<button onclick="return confirmDeleteCategory(' . $z_info->geo_zone_id . ')" class="btn btn-delete">' . IMAGE_DELETE . '</button>';
        echo '</div>';
    }
    public function action_confirmcategorydelete()
    {
        \common\helpers\Translation::init('admin/geo_zones');
        $this->layout = false;
        $categories_id = Yii::$app->request->post('category_id');
        $zones_query = tep_db_query('select geo_zone_id, geo_zone_name, geo_zone_description, last_modified, date_added from ' . TABLE_GEO_ZONES . " where geo_zone_id='" . (int) $categories_id . "'");
        $zones = tep_db_fetch_array($zones_query);
        $num_zones_query = tep_db_query('select count(*) as num_zones from ' . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . (int) $zones['geo_zone_id'] . "' group by geo_zone_id");
        $num_zones = tep_db_fetch_array($num_zones_query);
        if ($num_zones['num_zones'] > 0) {
            $zones['num_zones'] = $num_zones['num_zones'];
        } else {
            $zones['num_zones'] = 0;
        }
        $z_info = new \Object_Info($zones);
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_ZONE . '</div>';
        echo tep_draw_form('zones', FILENAME_GEO_ZONES, 'zpage=' . \Yii::$app->request->get('zpage') . '&zID=' . $z_info->geo_zone_id . '&action=deleteconfirm_zone', 'post', 'id="option_delete" onsubmit="return deleteCategory();"');
        echo '<div class="col_desc">' . TEXT_INFO_DELETE_ZONE_INTRO . '</div>';
        echo '<div class="col_desc">' . $z_info->geo_zone_name . '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">' . tep_draw_hidden_field('category_id', $categories_id) . '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()"></div>';
        echo '</form>';
    }
    public function action_categorydelete()
    {
        $z_id = (int) Yii::$app->request->post('category_id');
        tep_db_query('delete from ' . TABLE_GEO_ZONES . " where geo_zone_id = '" . (int) $z_id . "'");
        tep_db_query('delete from ' . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . (int) $z_id . "'");
    }
    public function action_categoryedit()
    {
        \common\helpers\Translation::init('admin/geo_zones');
        $this->layout = false;
        $categories_id = (int) Yii::$app->request->post('category_id');
        $zones_query = tep_db_query('select geo_zone_id, geo_zone_name, geo_zone_description, last_modified, date_added from ' . TABLE_GEO_ZONES . " where geo_zone_id='" . (int) $categories_id . "'");
        $zones = tep_db_fetch_array($zones_query);
        $zones['geo_zone_id'] = $zones['geo_zone_id'] ?? null;
        $num_zones_query = tep_db_query('select count(*) as num_zones from ' . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . (int) $zones['geo_zone_id'] . "' group by geo_zone_id");
        $num_zones = tep_db_fetch_array($num_zones_query);
        $num_zones['num_zones'] = $num_zones['num_zones'] ?? null;
        if ($num_zones['num_zones'] > 0) {
            $zones['num_zones'] = $num_zones['num_zones'];
        } else {
            $zones['num_zones'] = 0;
        }
        $z_info = new \Object_Info($zones);
        $heading = [];
        $contents = [];
        $heading[] = ['text' => '<b>' . TEXT_INFO_HEADING_EDIT_ZONE . '</b>'];
        echo tep_draw_form('zones', FILENAME_GEO_ZONES, 'zpage=' . \Yii::$app->request->get('zpage') . '&zID=' . $z_info->geo_zone_id . '&action=save_zone', 'post', 'id="option_save" onsubmit="return checkCategoryForm();"');
        echo '<div class="or_box_head">' . TEXT_INFO_EDIT_ZONE_INTRO . '</div>';
        echo '<div class="row_or row_or_block"><label class="main">' . TEXT_INFO_ZONE_NAME . '</label>' . tep_draw_input_field('geo_zone_name', $z_info->geo_zone_name ?? null, 'class="form-control"') . '</div>';
        echo '<div class="row_or row_or_block"><label class="main">' . TEXT_INFO_ZONE_DESCRIPTION . '</label>' . tep_draw_input_field('geo_zone_description', $z_info->geo_zone_description ?? null, 'class="form-control"') . '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">' . tep_draw_hidden_field('category_id', $categories_id) . '<button class="btn btn-no-margin">' . IMAGE_UPDATE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()"></div>';
        echo '</form>';
    }
    public function action_categorysubmit()
    {
        $z_id = (int) Yii::$app->request->post('category_id');
        $geo_zone_name = tep_db_prepare_input(Yii::$app->request->post('geo_zone_name'));
        $geo_zone_description = tep_db_prepare_input(Yii::$app->request->post('geo_zone_description'));
        if ($z_id == 0) {
            tep_db_query('insert into ' . TABLE_GEO_ZONES . " (geo_zone_name, geo_zone_description, date_added) values ('" . tep_db_input($geo_zone_name) . "', '" . tep_db_input($geo_zone_description) . "', now())");
        } else {
            tep_db_query('update ' . TABLE_GEO_ZONES . " set geo_zone_name = '" . tep_db_input($geo_zone_name) . "', geo_zone_description = '" . tep_db_input($geo_zone_description) . "', last_modified = now() where geo_zone_id = '" . (int) $z_id . "'");
        }
    }
    public function action_productactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/geo_zones');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $zones_query = tep_db_query('select a.association_id, a.zone_country_id, c.countries_name, a.zone_id, a.geo_zone_id, a.last_modified, a.date_added, z.zone_name from ' . TABLE_ZONES_TO_GEO_ZONES . ' a left join ' . TABLE_COUNTRIES . " c on a.zone_country_id = c.countries_id and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . ' z on a.zone_id = z.zone_id where a.association_id = ' . $products_id);
        $zones = tep_db_fetch_array($zones_query);
        $s_info = new \Object_Info($zones);
        echo '<div class="or_box_head">' . $s_info->countries_name . '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order"><button onclick="return editProduct(' . $products_id . ')" class="btn btn-no-margin btn-primary btn-edit">' . IMAGE_EDIT . '</button><button onclick="return confirmDeleteProduct(' . $products_id . ')" class="btn btn-delete">' . IMAGE_DELETE . '</button></div>';
        echo '<br>' . TEXT_INFO_DATE_ADDED . ' ' . \common\helpers\Date::date_short($s_info->date_added);
        if (tep_not_null($s_info->last_modified)) {
            echo '<br>' . TEXT_INFO_LAST_MODIFIED . ' ' . \common\helpers\Date::date_short($s_info->last_modified);
        }
    }
    public function action_confirmproductdelete()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/geo_zones');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $zones_query = tep_db_query('select a.association_id, a.zone_country_id, c.countries_name, a.zone_id, a.geo_zone_id, a.last_modified, a.date_added, z.zone_name from ' . TABLE_ZONES_TO_GEO_ZONES . ' a left join ' . TABLE_COUNTRIES . " c on a.zone_country_id = c.countries_id and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . ' z on a.zone_id = z.zone_id where a.association_id = ' . $products_id);
        $zones = tep_db_fetch_array($zones_query);
        $s_info = new \Object_Info($zones);
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_SUB_ZONE . '</div>';
        echo tep_draw_form('zones', FILENAME_GEO_ZONES, \common\helpers\Output::get_all_get_params(['action']), 'post', 'id="option_delete" onsubmit="return deleteProduct();"');
        echo '<div class="col_desc">' . TEXT_INFO_DELETE_SUB_ZONE_INTRO . '</div>';
        echo '<div class="col_desc">' . $s_info->countries_name . '</div>';
        echo '<br>' . tep_draw_hidden_field('association_id', $s_info->association_id) . '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo '</form>';
    }
    public function action_productdelete()
    {
        $s_id = (int) Yii::$app->request->post('association_id');
        tep_db_query('delete from ' . TABLE_ZONES_TO_GEO_ZONES . " where association_id = '" . $s_id . "'");
    }
    public function action_productedit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/geo_zones');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $z_id = (int) Yii::$app->request->post('geo_zone_id');
        $zones_query = tep_db_query('select a.*, c.countries_name, z.zone_name from ' . TABLE_ZONES_TO_GEO_ZONES . ' a left join ' . TABLE_COUNTRIES . " c on a.zone_country_id = c.countries_id and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . ' z on a.zone_id = z.zone_id where a.association_id = ' . $products_id);
        $zones = tep_db_fetch_array($zones_query);
        $s_info = new \Object_Info($zones);
        $s_info->geo_zone_id = $s_info->geo_zone_id ?? null;
        if ($s_info->geo_zone_id <= 0) {
            $s_info->geo_zone_id = $z_id;
        }
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_EDIT_SUB_ZONE . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_EDIT_SUB_ZONE_INTRO . '</div>';
        echo '<div class="col_desc">' . ($s_info->countries_name ?? null) . '</div>';
        echo tep_draw_form('zones', FILENAME_GEO_ZONES, \common\helpers\Output::get_all_get_params(['action']), 'post', 'id="option_save" onsubmit="return checkProductForm();"');
        echo '<div class="row_or row_or_block"><label>' . TEXT_INFO_COUNTRY . '</label>' . tep_draw_pull_down_menu('zone_country_id', \common\helpers\Country::get_countries('', false, TEXT_ALL_COUNTRIES), $s_info->zone_country_id ?? null, 'onChange="update_zone(this.form);" class="form-control"') . '</div>';
        echo '<div class="row_or row_or_block"><label>' . TEXT_INFO_COUNTRY_ZONE . '</label>' . tep_draw_pull_down_menu('zone_id', \common\helpers\Zones::prepare_country_zones_pull_down($s_info->zone_country_id ?? null), $s_info->zone_id ?? null, 'class="form-control"') . '</div>';
        echo '<div class="row_or row_or_block"><label>' . TEXT_POSTCODE_START . '</label>' . Html::text_input('postcode_start', $s_info->postcode_start ?? null, ['class' => 'form-control']) . '</div>';
        echo '<div class="row_or row_or_block"><label>' . TEXT_POSTCODE_END . '</label>' . Html::text_input('postcode_end', $s_info->postcode_end ?? null, ['class' => 'form-control']) . '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">' . tep_draw_hidden_field('association_id', $s_info->association_id ?? null) . tep_draw_hidden_field('geo_zone_id', $s_info->geo_zone_id ?? null) . '<button class="btn btn-no-margin">' . IMAGE_UPDATE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()"></div>';
        echo '</form>';
    }
    public function action_productsubmit()
    {
        $s_id = (int) Yii::$app->request->post('association_id');
        $z_id = (int) Yii::$app->request->post('geo_zone_id');
        $zone_country_id = (int) Yii::$app->request->post('zone_country_id');
        $zone_id = (int) Yii::$app->request->post('zone_id');
        $postcode_start = Yii::$app->request->post('postcode_start', '');
        $postcode_end = Yii::$app->request->post('postcode_end', '');
        if ($s_id == 0) {
            //check
            $check = tep_db_fetch_array(tep_db_query('select association_id ' . 'from ' . TABLE_ZONES_TO_GEO_ZONES . ' ' . "where geo_zone_id = '" . (int) $z_id . "' and zone_country_id = '" . (int) $zone_country_id . "' and zone_id = '" . (int) $zone_id . "' " . " AND postcode_start='" . tep_db_input($postcode_start) . "' AND postcode_end='" . tep_db_input($postcode_end) . "'"));
            if (!$check) {
                tep_db_query('insert into ' . TABLE_ZONES_TO_GEO_ZONES . ' (zone_country_id, zone_id, geo_zone_id, postcode_start, postcode_end, date_added) ' . "values ('" . (int) $zone_country_id . "', '" . (int) $zone_id . "', '" . (int) $z_id . "', '" . tep_db_input($postcode_start) . "', '" . tep_db_input($postcode_end) . "', now())");
            }
        } else {
            tep_db_query('update ' . TABLE_ZONES_TO_GEO_ZONES . ' ' . "set geo_zone_id = '" . (int) $z_id . "', zone_country_id = '" . (int) $zone_country_id . "', " . "postcode_start='" . tep_db_input($postcode_start) . "', postcode_end='" . tep_db_input($postcode_end) . "', " . 'zone_id = ' . (tep_not_null($zone_id) ? "'" . (int) $zone_id . "'" : 'null') . ', last_modified = now() ' . "where association_id = '" . (int) $s_id . "'");
        }
    }
}