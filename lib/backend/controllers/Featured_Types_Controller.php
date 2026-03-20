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

use common\models\Featured;
use common\models\Featured_Types;
use Yii;
/**
 * default controller to handle user requests.
 */
class Featured_Types_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_FEATURED_TYPES'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'featured-types'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('featured-types/index'), 'title' => TEXT_FEATURED_TYPES];
        $this->view->heading_title = TEXT_FEATURED_TYPES;
        $this->top_buttons[] = '<a href="#" class="btn btn-primary" onclick="return featuredTypeEdit(0)">' . IMAGE_INSERT . '</a>';
        $this->view->featured_type_table = [['title' => TABLE_TEXT_NAME, 'not_important' => 0]];
        $messages = Yii::$app->session->get('messages');
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
        $featured_types = Featured_Types::find()->where(['language_id' => (int) $languages_id]);
        if ($search['value']) {
            $keywords = tep_db_input(tep_db_prepare_input($search['value']));
            $featured_types->and_where("featured_type_name like '%" . $keywords . "%'");
        }
        $order_by = 'featured_type_name';
        if (($order[0]['column'] ?? null) === 0 && ($order[0]['dir'] ?? null)) {
            $order_by = 'featured_type_name ' . tep_db_prepare_input($order[0]['dir']);
        }
        $featured_types->order_by($order_by);
        $num_rows = $featured_types->count();
        $featured_types->limit($length);
        $featured_types->offset($start);
        $featured_types_arr = $featured_types->as_array()->all();
        $response_list = [];
        foreach ($featured_types_arr as $featured_type) {
            $response_list[] = [$featured_type['featured_type_name'] . tep_draw_hidden_field('id', $featured_type['featured_type_id'], 'class="cell_identify"')];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $num_rows, 'recordsFiltered' => $num_rows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_actions()
    {
        $languages_id = (int) \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/featured-types');
        $featured_type_id = (int) Yii::$app->request->post('featured_type_id', 0);
        $this->layout = false;
        if ($featured_type_id) {
            $featured_type = Featured_Types::find()->where(['featured_type_id' => $featured_type_id, 'language_id' => $languages_id])->as_array()->one();
            $c_info = new \Object_Info($featured_type, false);
            echo '<div class="or_box_head">' . $c_info->featured_type_name . '</div>';
            echo '<div class="row_or_wrapp">';
            echo '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button class="btn btn-primary btn-edit btn-no-margin" onclick="featuredTypeEdit(' . $featured_type_id . ')">' . IMAGE_EDIT . '</button>' . '<button class="btn btn-delete" onclick="featuredTypeDelete(' . $featured_type_id . ')">' . IMAGE_DELETE . '</button>';
            echo '</div>';
        }
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/featured-types');
        $featured_type_id = Yii::$app->request->get('featured_type_id', 0);
        $featured_type_names = [];
        if ($featured_type_id) {
            $featured_types = Featured_Types::find()->where(['featured_type_id' => $featured_type_id])->as_array()->all();
            foreach ($featured_types as $featured_type) {
                $featured_type_names[$featured_type['language_id']] = $featured_type['featured_type_name'];
            }
        }
        $featured_type_name_inputs_string = '';
        $languages = \common\helpers\Language::get_languages();
        foreach ($languages as $languages) {
            $featured_type_name_inputs_string .= '<div class="langInput">' . $languages['image'] . tep_draw_input_field('featured_type_name[' . $languages['id'] . ']', isset($featured_type_names[$languages['id']]) ? $featured_type_names[$languages['id']] : '') . '</div>';
        }
        echo tep_draw_form('featured_type', 'featured-type/save', 'featured_type_id=' . $featured_type_id . '&action=save');
        if ($featured_type_id) {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_EDIT_FEATURED_TYPE . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_INFO_HEADING_NEW_FEATURED_TYPE . '</div>';
        }
        //echo '<div class="col_desc">' . TEXT_INFO_EDIT_INTRO . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_FEATURED_TYPE_NAME . '</div>';
        echo $featured_type_name_inputs_string;
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<input type="button" value="' . IMAGE_UPDATE . '" class="btn btn-no-margin" onclick="featuredTypeSave(' . ($featured_type_id ? $featured_type_id : 0) . ')">' . '<input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement()">';
        echo '</div>';
        echo '</form>';
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/featured-types');
        $featured_type_id = Yii::$app->request->get('featured_type_id', 0);
        $featured_type_names = tep_db_prepare_input(Yii::$app->request->post('featured_type_name', []));
        if (empty($featured_type_id)) {
            $featured_type_id = Featured_Types::find()->max('featured_type_id') + 1;
        }
        $languages = \common\helpers\Language::get_languages();
        $default_language_id = \common\helpers\Language::get_default_language_id();
        foreach ($languages as $language) {
            $type = Featured_Types::find_one(['featured_type_id' => $featured_type_id, 'language_id' => $language['id']]);
            if (!$type) {
                $type = new Featured_Types();
            }
            $type->attributes = ['featured_type_id' => $featured_type_id, 'language_id' => $language['id'], 'featured_type_name' => $featured_type_names[$language['id']] ? $featured_type_names[$language['id']] : $featured_type_names[$default_language_id]];
            $type->save();
        }
        echo json_encode(['message' => 'success', 'messageType' => 'alert-success']);
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/featured-types');
        $featured_type_id = (int) Yii::$app->request->post('featured_type_id');
        foreach (Featured::find_all(['featured_type_id' => $featured_type_id]) as $featured_model) {
            $featured_model->delete();
        }
        if ($featured_type_model = Featured_Types::find_one(['featured_type_id' => $featured_type_id])) {
            $featured_type_model->delete();
        }
        echo 'reset';
    }
}