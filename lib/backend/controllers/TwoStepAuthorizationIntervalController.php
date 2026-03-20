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
class Two_Step_Authorization_Interval_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_CONFIGURATION', 'BOX_TWO_STEP_AUTH_INTERVAL'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'configuration', 'two-step-authorization-interval'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('two-step-authorization-interval/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="#" class="create_item" onclick="return tsaiEdit(0);">' . TEXT_TSAI_BUTTON_NEW . '</a>';
        $this->view->tsai_table = [['title' => TEXT_TSAI_TABLE_HEADING, 'not_important' => 0], ['title' => TEXT_SORT_ORDER, 'not_important' => 0]];
        $messages = $_SESSION['messages'] ?? null;
        unset($_SESSION['messages']);
        if (!is_array($messages)) {
            $messages = [];
        }
        return $this->render('index', ['messages' => $messages]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $order_by = true;
        $response_list = [];
        $select = \common\models\Admin_Login_Expire::find()->where(['ale_language_id' => $languages_id])->offset($start)->limit($length);
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $select->order_by(['ale_title' => strtoupper(trim($_GET['order'][0]['dir'])) == 'ASC' ? SORT_ASC : SORT_DESC]);
                    $order_by = false;
                    break;
                case 1:
                    $select->order_by(['ale_order' => strtoupper(trim($_GET['order'][0]['dir'])) == 'ASC' ? SORT_ASC : SORT_DESC]);
                    $order_by = false;
                    break;
            }
        }
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $select->and_where(['like', 'ale_title', trim($_GET['search']['value'])]);
        }
        if ($order_by == true) {
            $select->order_by(['ale_order' => SORT_ASC]);
        }
        $count = $select->count();
        foreach ($select->as_array(true)->all() as $row) {
            $response_list[] = [$row['ale_title'] . tep_draw_hidden_field('id', $row['ale_id'], 'class="cell_identify"'), $row['ale_order']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $count, 'recordsFiltered' => $count, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_view()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/two-step-authorization-interval');
        $ale_id = Yii::$app->request->post('ale_id', 0);
        $this->layout = false;
        if ($ale_id) {
            $o_info = \common\models\Admin_Login_Expire::find_one(['ale_id' => (int) $ale_id, 'ale_language_id' => $languages_id]);
            if ($o_info instanceof \common\models\Admin_Login_Expire) {
                echo '<div class="or_box_head">' . $o_info->ale_title . '</div>';
                $inputs_string = '';
                $languages = \common\helpers\Language::get_languages();
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    $title = '';
                    $ale_record = \common\models\Admin_Login_Expire::find_one(['ale_id' => (int) $o_info->ale_id, 'ale_language_id' => (int) $languages[$i]['id']]);
                    if ($ale_record instanceof \common\models\Admin_Login_Expire) {
                        $title = $ale_record->ale_title;
                    }
                    $inputs_string .= '<div class="col_desc">' . $languages[$i]['image'] . '&nbsp;' . $title . '</div>';
                }
                echo $inputs_string;
                echo '<div class="btn-toolbar btn-toolbar-order">';
                echo '<button class="btn btn-edit btn-no-margin" onclick="tsaiEdit(' . (int) $o_info->ale_id . ');">' . IMAGE_EDIT . '</button><button class="btn btn-delete" onclick="tsaiDelete(' . (int) $o_info->ale_id . ');">' . IMAGE_DELETE . '</button>';
                echo '</div>';
            }
        }
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/two-step-authorization-interval');
        $ale_id = (int) Yii::$app->request->get('ale_id', 0);
        $o_info = \common\models\Admin_Login_Expire::find_one(['ale_id' => $ale_id]);
        if (!$o_info instanceof \common\models\Admin_Login_Expire) {
            $o_info = new \common\models\Admin_Login_Expire();
        }
        echo tep_draw_form('tsaiEditForm', 'two-step-authorization-interval/save', 'ale_id=' . (int) $o_info->ale_id);
        if ($ale_id > 0) {
            echo '<div class="or_box_head">' . TEXT_TSAI_EDIT . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_TSAI_NEW . '</div>';
        }
        $inputs_string = '';
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $title = '';
            $ale_record = \common\models\Admin_Login_Expire::find_one(['ale_id' => (int) $o_info->ale_id, 'ale_language_id' => (int) $languages[$i]['id']]);
            if ($ale_record instanceof \common\models\Admin_Login_Expire) {
                $title = $ale_record->ale_title;
            }
            $inputs_string .= '<div class="langInput">' . $languages[$i]['image'] . tep_draw_input_field('ale_title[' . $languages[$i]['id'] . ']', $title) . '</div>';
        }
        echo '<div class="col_desc">' . TEXT_INFO_EDIT_INTRO . '</div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_TSAI_TITLE . '</div><div class="main_value">' . $inputs_string . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_TSAI_EXPIRE_MINUTES . '</div><div class="main_value">' . tep_draw_input_field('ale_expire_minutes', trim((int) $o_info->ale_expire_minutes)) . '</div></div>';
        echo '<div class="main_row"><div class="main_title">' . TEXT_SORT_ORDER . '</div><div class="main_value">' . tep_draw_input_field('ale_order', trim((int) $o_info->ale_order)) . '</div></div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<input type="button" value="' . IMAGE_UPDATE . '" class="btn btn-no-margin" onclick="tsaiSave(' . (int) ((int) $o_info->ale_id > 0 ? $o_info->ale_id : 0) . ');"><input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="tsaiReset();">';
        echo '</div>';
        echo '</form>';
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/two-step-authorization-interval');
        $ale_id = (int) Yii::$app->request->get('ale_id', 0);
        $ale_title = Yii::$app->request->post('ale_title', []);
        $ale_title = is_array($ale_title) ? $ale_title : [];
        $ale_expire_minutes = (int) Yii::$app->request->post('ale_expire_minutes', 0);
        $ale_expire_minutes = $ale_expire_minutes < 0 ? 0 : $ale_expire_minutes;
        $ale_order = (int) Yii::$app->request->post('ale_order', 0);
        $ale_order = $ale_order < 0 ? 0 : $ale_order;
        $action = 'updated';
        if ($ale_id <= 0) {
            $action = 'added';
            $ale_id = 1;
            $ale_record = \common\models\Admin_Login_Expire::find()->order_by(['ale_id' => SORT_DESC])->one();
            if ($ale_record instanceof \common\models\Admin_Login_Expire) {
                $ale_id = (int) $ale_record->ale_id + 1;
            }
        }
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $language_id = (int) $languages[$i]['id'];
            $ale_record = \common\models\Admin_Login_Expire::find_one(['ale_id' => $ale_id, 'ale_language_id' => $language_id]);
            if (!$ale_record instanceof \common\models\Admin_Login_Expire) {
                $ale_record = new \common\models\Admin_Login_Expire();
            }
            $ale_record->ale_id = $ale_id;
            $ale_record->ale_language_id = $language_id;
            $ale_record->ale_title = trim(isset($ale_title[$language_id]) ? $ale_title[$language_id] : '');
            $ale_record->ale_expire_minutes = $ale_expire_minutes;
            $ale_record->ale_order = $ale_order;
            $ale_record->save();
        }
        echo json_encode(['message' => 'Authorization interval ' . $action, 'messageType' => 'alert-success']);
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/two-step-authorization-interval');
        $ale_id = (int) Yii::$app->request->post('ale_id', 0);
        if ($ale_id > 0) {
            \common\models\Admin_Login_Expire::delete_all(['ale_id' => $ale_id]);
        }
    }
}