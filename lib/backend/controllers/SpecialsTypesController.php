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

use common\models\Specials;
use common\models\Specials_Types;
use Yii;
class Specials_Types_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_SPECIALS_TAGS'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'specials-types'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('specials-types/index'), 'title' => TEXT_SPECIALS_TAGS];
        $this->view->heading_title = TEXT_SPECIALS_TAGS;
        $this->top_buttons[] = '<a href="#" class="create_item" onclick="return specialsTypeEdit(0)">' . IMAGE_INSERT . '</a>';
        $this->view->specials_type_table = [['title' => TABLE_TEXT_NAME, 'not_important' => 0]];
        $messages = $_SESSION['messages'] ?? null;
        unset($_SESSION['messages']);
        return $this->render('index', ['messages' => $messages]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $search = Yii::$app->request->get('search', []);
        $order = Yii::$app->request->get('order', []);
        if ($length == -1) {
            $length = 1000;
        }
        $specials_types = Specials_Types::find()->where(['language_id' => (int) $languages_id]);
        if ($search['value']) {
            $keywords = tep_db_input(tep_db_prepare_input($search['value']));
            $specials_types->and_where(['or', "specials_type_name like '%" . $keywords . "%'", "specials_type_code like '%" . $keywords . "%'"]);
        }
        $order_by = 'specials_type_name';
        if (($order[0]['column'] ?? null) === 0 && $order[0]['dir']) {
            $order_by = 'specials_type_name ' . tep_db_prepare_input($order[0]['dir']);
        }
        $specials_types->order_by($order_by);
        $num_rows = $specials_types->count();
        $specials_types->limit($length);
        $specials_types->offset($start);
        $specials_types_arr = $specials_types->as_array()->all();
        $response_list = [];
        foreach ($specials_types_arr as $specials_type) {
            $response_list[] = [$specials_type['specials_type_name'] . tep_draw_hidden_field('id', $specials_type['specials_type_id'], 'class="cell_identify"')];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $num_rows, 'recordsFiltered' => $num_rows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_actions()
    {
        $languages_id = (int) \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/specials-types');
        $specials_type_id = (int) Yii::$app->request->post('specials_type_id', 0);
        $this->layout = false;
        if ($specials_type_id) {
            $specials_type = Specials_Types::find()->where(['specials_type_id' => $specials_type_id, 'language_id' => $languages_id])->as_array()->one();
            $c_info = new \Object_Info($specials_type, false);
            echo '<div class="or_box_head">' . $c_info->specials_type_name . '</div>';
            echo '<div class="or_box_head">' . $c_info->specials_type_code . '</div>';
            echo '<div class="row_or_wrapp">';
            echo '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button class="btn btn-primary btn-edit btn-no-margin" onclick="specialsTypeEdit(' . $specials_type_id . ')">' . IMAGE_EDIT . '</button>' . '<button class="btn btn-delete" onclick="specialsTypeDelete(' . $specials_type_id . ')">' . IMAGE_DELETE . '</button>';
            echo '</div>';
        }
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/specials-types');
        $specials_type_id = Yii::$app->request->get('specials_type_id', 0);
        $specials_type_names = [];
        if ($specials_type_id) {
            $specials_type_names = Specials_Types::find()->where(['specials_type_id' => $specials_type_id])->as_array()->index_by('language_id')->all();
        }
        $specials_type_name_inputs_string = '';
        $specials_type_code_inputs_string = '';
        $languages = \common\helpers\Language::get_languages();
        foreach ($languages as $languages) {
            $specials_type_name_inputs_string .= '<div class="langInput">' . $languages['image'] . tep_draw_input_field('specials_type_name[' . $languages['id'] . ']', isset($specials_type_names[$languages['id']]['specials_type_name']) ? $specials_type_names[$languages['id']]['specials_type_name'] : '') . '</div>';
            $specials_type_code_inputs_string .= '<div class="langInput">' . $languages['image'] . tep_draw_input_field('specials_type_code[' . $languages['id'] . ']', isset($specials_type_names[$languages['id']]['specials_type_code']) ? $specials_type_names[$languages['id']]['specials_type_code'] : '') . '</div>';
        }
        echo tep_draw_form('specials_type', 'specials-type/save', 'specials_type_id=' . $specials_type_id . '&action=save');
        if ($specials_type_id) {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_EDIT_SPECIALS_TAG . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_NEW_SPECIALS_TAG . '</div>';
        }
        //echo '<div class="col_desc">' . TEXT_INFO_EDIT_INTRO . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_SPECIALS_TAG_NAME . '</div>';
        echo $specials_type_name_inputs_string;
        echo '<div class="col_desc">' . TEXT_INFO_SPECIALS_TAG_CODE . '</div>';
        echo $specials_type_code_inputs_string;
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<input type="button" value="' . ($specials_type_id ? IMAGE_UPDATE : IMAGE_SAVE) . '" class="btn btn-no-margin" onclick="specialsTypeSave(' . ($specials_type_id ? $specials_type_id : 0) . ')">' . '<input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement()">';
        echo '</div>';
        echo '</form>';
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/specials-types');
        $specials_type_id = Yii::$app->request->get('specials_type_id', 0);
        $specials_type_names = tep_db_prepare_input(Yii::$app->request->post('specials_type_name', []));
        $specials_type_codes = tep_db_prepare_input(Yii::$app->request->post('specials_type_code', []));
        $new = false;
        if (empty($specials_type_id)) {
            $specials_type_id = Specials_Types::find()->max('specials_type_id') + 1;
            $new = true;
        }
        $languages = \common\helpers\Language::get_languages();
        $default_language_id = \common\helpers\Language::get_default_language_id();
        $res = ['message' => 'success', 'messageType' => 'alert-success'];
        foreach ($languages as $language) {
            $type = Specials_Types::find_one(['specials_type_id' => $specials_type_id, 'language_id' => $language['id']]);
            if (!$type) {
                $type = new Specials_Types();
            }
            try {
                $type->attributes = ['specials_type_id' => $specials_type_id, 'language_id' => $language['id'], 'specials_type_name' => $specials_type_names[$language['id']] ? $specials_type_names[$language['id']] : $specials_type_names[$default_language_id], 'specials_type_code' => $specials_type_codes[$language['id']] ? $specials_type_codes[$language['id']] : $specials_type_codes[$default_language_id]];
                $r = $type->save(false);
                if (!$r) {
                    $res = ['message' => 'not validated' . $e->get_message(), 'messageType' => 'alert-warning'];
                }
            } catch (\Exception $e) {
                $res = ['message' => $e->get_message(), 'messageType' => 'alert-warning'];
                if ($new) {
                    Specials_Types::delete_all(['specials_type_id' => $specials_type_id]);
                    break;
                }
            }
        }
        echo json_encode($res);
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/specials-types');
        $specials_type_id = (int) Yii::$app->request->post('specials_type_id');
        //Specials::findAll(['specials_type_id' => $specialsTypeId])->delete();
        Specials_Types::delete_all(['specials_type_id' => $specials_type_id]);
        tep_db_query('update ' . TABLE_SPECIALS . " set specials_type_id=0 where specials_type_id='" . (int) $specials_type_id . "'");
        echo 'reset';
    }
}