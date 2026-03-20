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

use backend\components\Location_Search_Trait;
use common\helpers\Translation;
use common\models\Postal_Codes;
use yii;
use yii\helpers\Url;
class Postal_Codes_Controller extends Sceleton
{
    use Location_Search_Trait;
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_LOCATION', 'BOX_POSTAL_CODES'];
    public function action_index()
    {
        Translation::init('admin/postal-codes');
        Translation::init('admin/geo_zones');
        $this->selected_menu = ['settings', 'locations', 'postal-codes'];
        $this->navigation[] = ['link' => Url::to_route('index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="#" class="btn btn-primary" onclick="return entryEdit(0)">' . TEXT_NEW . '</a>';
        $this->view->column_table = [['title' => ENTRY_POST_CODE, 'not_important' => 0], ['title' => ENTRY_SUBURB, 'not_important' => 0], ['title' => ENTRY_CITY, 'not_important' => 0], ['title' => TABLE_HEADING_COUNTRY_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_ZONE_NAME, 'not_important' => 0]];
        return $this->render('index');
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $search_words = '';
        if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
            $search_words = tep_db_prepare_input($_GET['search']['value']);
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        if ($length == -1) {
            $length = 10000;
        }
        $query_raw = \common\models\Postal_Codes::find()->alias('p')->join('left join', \common\models\Countries::table_name() . ' c', "c.countries_id=p.country_id AND c.language_id='" . (int) $languages_id . "'")->join('left join', \common\models\Cities::table_name() . ' t', 't.city_id=p.city_id')->join('left join', \common\models\Zones::table_name() . ' z', 'z.zone_id=p.zone_id')->select(['p.id', 'p.postcode', 'p.suburb', 'c.countries_name', 't.city_name', 'z.zone_name']);
        if ($search_words) {
            $query_raw->and_where(['or', ['like', 'p.postcode', $search_words], ['like', 'p.suburb', $search_words], ['like', 'z.zone_name', $search_words]]);
        }
        $query_raw->order_by(['p.postcode' => SORT_ASC]);
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            $sort_dir = strtolower($_GET['order'][0]['dir']) == 'desc' ? SORT_DESC : SORT_ASC;
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $query_raw->order_by(['p.postcode' => $sort_dir]);
                    break;
                case 1:
                    $query_raw->order_by(['p.suburb' => $sort_dir]);
                    break;
                case 2:
                    $query_raw->order_by(['t.city_name' => $sort_dir]);
                    break;
            }
        }
        $total = $query_raw->count();
        $query_raw->limit($length)->offset($start);
        $response_list = [];
        foreach ($query_raw->as_array()->all() as $db_data) {
            $response_list[] = [$db_data['postcode'] . '<input type="hidden" class="cell_identify" value="' . $db_data['id'] . '">', $db_data['suburb'], (string) $db_data['city_name'], (string) $db_data['countries_name'], (string) $db_data['zone_name']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_actions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        Translation::init('admin/cities');
        Translation::init('admin/zones');
        Translation::init('admin/geo_zones');
        $item_id = Yii::$app->request->post('item_id', 0);
        $this->layout = false;
        if ($item_id) {
            $postal = \common\models\Postal_Codes::find()->where(['id' => $item_id])->as_array()->one();
            $postal['countries_name'] = \common\helpers\Country::get_country_name($postal['country_id']);
            $postal['zone_name'] = \common\helpers\Zones::get_zone_name($postal['country_id'], $postal['zone_id'], '');
            $postal['city_name'] = \common\models\Cities::find()->where(['city_id' => $postal['city_id']])->select('city_name')->scalar();
            $c_info = new \Object_Info($postal, false);
            echo '<div class="or_box_head">' . $c_info->city_name . '</div>';
            echo '<div class="row_or_wrapp">';
            echo '<div class="row_or"><div>' . ENTRY_POST_CODE . ':</div><div>' . $c_info->postcode . ' </div></div>';
            echo '<div class="row_or"><div>' . ENTRY_SUBURB . ':</div><div>' . $c_info->suburb . ' </div></div>';
            echo '<div class="row_or"><div>' . ENTRY_CITY . ':</div><div>' . $c_info->city_name . ' </div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_ZONE_NAME . '</div><div>' . $c_info->zone_name . ' </div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_COUNTRY_NAME . '</div><div>' . $c_info->countries_name . '</div></div>';
            echo '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order"><button class="btn btn-edit btn-no-margin" onclick="entryEdit(' . $postal['id'] . ')">' . IMAGE_EDIT . '</button><button class="btn btn-delete" onclick="entryDelete(' . $postal['id'] . ')">' . IMAGE_DELETE . '</button></div>';
        }
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        Translation::init('admin/postal-codes');
        Translation::init('admin/zones');
        Translation::init('admin/geo_zones');
        $item_id = Yii::$app->request->get('item_id', 0);
        $c_info = false;
        if ($item_id) {
            $c_info = Postal_Codes::find_one($item_id);
        }
        if (!$c_info) {
            $c_info = new Postal_Codes();
            $c_info->load_default_values();
        }
        echo tep_draw_form('cities', 'save', 'item_id=' . $c_info->id . '&action=save');
        if ($c_info->id) {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_EDIT_POSTCODE . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_NEW_POSTCODE . '</div>';
        }
        echo '<div class="col_desc">' . TEXT_INFO_EDIT_INTRO . '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . ENTRY_POST_CODE . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('postcode', $c_info->postcode) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . ENTRY_SUBURB . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('suburb', $c_info->suburb) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . ENTRY_CITY . '</div>';
        echo '<div class="main_value">' . tep_draw_hidden_field('city_id', $c_info->city_id) . tep_draw_input_field('city_name', \common\models\Cities::find()->where(['city_id' => $c_info->city_id])->select('city_name')->scalar()) . '<div id="acCityName" style="font-size: 12px"></div>' . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . TEXT_INFO_COUNTRY_NAME . '</div>';
        echo '<div class="main_value">' . \common\helpers\Html::drop_down_list('country_id', $c_info->country_id, \common\helpers\Country::new_get_countries('--', true), ['onchange' => 'update_zone(this.form)']) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . TEXT_INFO_ZONES_NAME . '</div>';
        echo '<div class="main_value">' . \common\helpers\Html::drop_down_list('zone_id', $c_info->zone_id, \yii\helpers\Array_Helper::map(\common\helpers\Zones::prepare_country_zones_pull_down($c_info->country_id), 'id', 'text')) . '</div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order"><input type="button" value="' . IMAGE_UPDATE . '" class="btn btn-no-margin" onclick="entrySave(' . ($c_info->id ? $c_info->id : 0) . ')"><input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement()"></div>';
        echo '</form>';
    }
    public function action_save()
    {
        Translation::init('admin/postal-codes');
        $item_id = Yii::$app->request->get('item_id', 0);
        $postcode = tep_db_prepare_input($_POST['postcode']);
        $suburb = tep_db_prepare_input($_POST['suburb']);
        $country_id = tep_db_prepare_input($_POST['country_id']);
        $zone_id = tep_db_prepare_input($_POST['zone_id']);
        $city_id = tep_db_prepare_input($_POST['city_id']);
        $city_name = tep_db_prepare_input($_POST['city_name']);
        if ($city_name) {
            $city_info = \common\models\Cities::find()->where(['city_name' => $city_name])->and_filter_where(['city_country_id' => empty($country_id) ? null : $country_id])->and_filter_where(['city_zone_id' => empty($zone_id) ? null : $zone_id])->select(['city_id', 'city_zone_id', 'city_country_id'])->as_array()->one();
            if ($city_info) {
                $city_id = $city_info['city_id'];
                if (empty($zone_id)) {
                    $zone_id = $city_info['city_zone_id'];
                }
            } else {
                $new_city_model = new \common\models\Cities();
                $new_city_model->set_attributes(['city_country_id' => $country_id, 'city_zone_id' => $zone_id, 'city_code' => '', 'city_name' => $city_name], false);
                $new_city_model->save(false);
                $city_id = $new_city_model->city_id;
            }
        } else {
            $city_id = 0;
        }
        if ($item_id == 0) {
            $item_model = new Postal_Codes();
            $item_model->set_attributes(['country_id' => (int) $country_id, 'zone_id' => (int) $zone_id, 'city_id' => (int) $city_id, 'suburb' => (string) $suburb, 'postcode' => (string) $postcode], false);
            $item_model->save(false);
            $action = 'added';
        } else {
            $item_model = Postal_Codes::find_one((int) $item_id);
            if ($item_model) {
                $item_model->set_attributes(['country_id' => (int) $country_id, 'zone_id' => (int) $zone_id, 'city_id' => (int) $city_id, 'suburb' => (string) $suburb, 'postcode' => (string) $postcode], false);
                $item_model->save(false);
            }
            $action = 'updated';
        }
        echo json_encode(['message' => 'Postcode ' . $action, 'messageType' => 'alert-success']);
    }
    public function action_delete()
    {
        $item_id = Yii::$app->request->post('item_id', 0);
        if ($item_id) {
            if ($item_model = Postal_Codes::find_one($item_id)) {
                $item_model->delete();
            }
        }
        echo 'reset';
    }
}