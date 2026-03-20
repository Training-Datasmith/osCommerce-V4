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

use Yii;
/**
 * default controller to handle user requests.
 */
class Orders_products_status_manual_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_SETTINGS_ORDERS_PRODUCTS_STATUS', 'BOX_ORDERS_PRODUCTS_STATUS_MANUAL'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'status', 'orders_products_status_manual'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders_products_status_manual/index'), 'title' => HEADING_TITLE_ORDERS_PRODUCTS_STATUS];
        $this->view->heading_title = HEADING_TITLE_ORDERS_PRODUCTS_STATUS;
        $this->top_buttons[] = '<a href="#" class="btn btn-primary" onclick="return statusEdit(0)">' . TEXT_INFO_HEADING_NEW_ORDERS_PRODUCTS_STATUS . '</a>';
        $this->view->status_table = [['title' => TABLE_HEADING_ORDERS_PRODUCTS_STATUS, 'not_important' => 0], ['title' => '', 'not_important' => 0]];
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
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $opsm_query = \common\models\Orders_Products_Status_Manual::find()->and_where(['language_id' => $languages_id]);
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $opsm_query->and_where(['or', ['like', 'orders_products_status_manual_name', tep_db_input(tep_db_prepare_input($_GET['search']['value']))], ['like', 'orders_products_status_manual_name_long', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]]);
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $opsm_query->order_by('orders_products_status_manual_name_long ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 1:
                    $opsm_query->order_by('orders_products_status_manual_name ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                default:
                    $opsm_query->order_by('orders_products_status_manual_id ASC');
                    break;
            }
        } else {
            $opsm_query->order_by('orders_products_status_manual_id ASC');
        }
        $orders_products_status_manual_query_numrows = $opsm_query->count();
        if ($length > 0) {
            $opsm_query->limit($length)->offset($start);
        }
        $opsm_query = $opsm_query->as_array(true)->all();
        $response_list = [];
        foreach ($opsm_query as $opsm_record) {
            $response_list[] = [$opsm_record['orders_products_status_manual_name_long'] . tep_draw_hidden_field('id', $opsm_record['orders_products_status_manual_id'], 'class="cell_identify"'), $opsm_record['orders_products_status_manual_name']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $orders_products_status_manual_query_numrows, 'recordsFiltered' => $orders_products_status_manual_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_statusactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders_products_status_manual');
        $this->layout = false;
        $opsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => Yii::$app->request->post('orders_products_status_manual_id', 0), 'language_id' => $languages_id]);
        if ($opsm_record) {
            echo '<div class="or_box_head" style="color: ' . $opsm_record->get_colour() . '">' . $opsm_record->orders_products_status_manual_name_long . ' / ' . $opsm_record->orders_products_status_manual_name . '</div>';
            $orders_products_status_manual_inputs_string = '';
            $languages = \common\helpers\Language::get_languages();
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $orders_products_status_manual_inputs_string .= '<div class="col_desc">' . $languages[$i]['image'] . '&nbsp;' . \common\helpers\Order::get_orders_products_status_manual_name($opsm_record->orders_products_status_manual_id, $languages[$i]['id']) . '</div>';
            }
            echo $orders_products_status_manual_inputs_string;
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button class="btn btn-edit btn-no-margin" onclick="statusEdit(' . $opsm_record->orders_products_status_manual_id . ')">' . IMAGE_EDIT . '</button><button class="btn btn-delete" onclick="statusDelete(' . $opsm_record->orders_products_status_manual_id . ')">' . IMAGE_DELETE . '</button>';
            echo '</div>';
        }
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->top_buttons[] = '<span class="btn btn-confirm">' . IMAGE_SAVE . '</span>';
        \common\helpers\Translation::init('admin/orders_products_status_manual');
        $opsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => Yii::$app->request->get('orders_products_status_manual_id', 0), 'language_id' => $languages_id]);
        $orders_products_status_manual_id = 0;
        $orders_products_status_manual_colour = '#000000';
        if ($opsm_record) {
            $orders_products_status_manual_id = $opsm_record->orders_products_status_manual_id;
            $orders_products_status_manual_colour = $opsm_record->get_colour();
        }
        $orders_products_status_manual_inputs_string = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $orders_products_status_manual_inputs_string[$languages[$i]['id']] = \yii\helpers\Html::input('text', 'orders_products_status_manual_name[' . $languages[$i]['id'] . ']', \common\helpers\Order::get_orders_products_status_manual_name($orders_products_status_manual_id, $languages[$i]['id'], false), ['class' => 'form-control']);
            $orders_products_status_manual_inputs_string_long[$languages[$i]['id']] = \yii\helpers\Html::input('text', 'orders_products_status_manual_name_long[' . $languages[$i]['id'] . ']', \common\helpers\Order::get_orders_products_status_manual_name($orders_products_status_manual_id, $languages[$i]['id']), ['class' => 'form-control']);
        }
        $opsmm_array = $opsm_record ? $opsm_record->get_matrix_array(true) : [];
        $orders_products_status_matrix_string = [];
        foreach (\common\models\Orders_Products_Status::find_all(['language_id' => $languages_id]) as $ops_record) {
            $ops_id = 'ops_' . $ops_record->orders_products_status_id;
            $orders_products_status_matrix_string[] = ['label' => '<label for="' . $ops_id . '">' . $ops_record->orders_products_status_name_long . '</label>', 'element' => \yii\helpers\Html::checkbox('orders_products_status_matrix[' . $ops_record->orders_products_status_id . ']', isset($opsmm_array[$ops_record->orders_products_status_id]), ['id' => $ops_id, 'class' => 'form-control'])];
        }
        if ($orders_products_status_manual_id) {
            $title = TEXT_INFO_HEADING_EDIT_ORDERS_PRODUCTS_STATUS;
        } else {
            $title = TEXT_INFO_HEADING_NEW_ORDERS_PRODUCTS_STATUS;
        }
        $this->selected_menu = ['settings', 'status', 'orders_products_status_manual'];
        $this->view->heading_title = $title;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders_products_status_manual/index'), 'title' => $title];
        return $this->render('edit', ['orders_products_status_manual_id' => $orders_products_status_manual_id, 'orders_products_status_manual_colour' => $orders_products_status_manual_colour, 'orders_products_status_manual_inputs_string' => $orders_products_status_manual_inputs_string, 'orders_products_status_manual_inputs_string_long' => $orders_products_status_manual_inputs_string_long, 'orders_products_status_matrix_string' => $orders_products_status_matrix_string, 'languages' => $languages]);
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/orders_products_status_manual');
        $insert_id = $orders_products_status_manual_id = intval(Yii::$app->request->get('orders_products_status_manual_id', 0));
        if ($orders_products_status_manual_id == 0) {
            $next_id = \common\models\Orders_Products_Status_Manual::find()->select('max(orders_products_status_manual_id) AS count')->as_array(true)->one();
            $insert_id = $next_id['count'] + 1;
        }
        $languages = \common\helpers\Language::get_languages();
        $orders_products_status_manual_colour = trim($_POST['orders_products_status_manual_colour']);
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $orders_products_status_manual_name_array = $_POST['orders_products_status_manual_name'];
            $orders_products_status_manual_name_long_array = $_POST['orders_products_status_manual_name_long'];
            $language_id = $languages[$i]['id'];
            $opsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => $orders_products_status_manual_id, 'language_id' => (int) $language_id]);
            $action = 'updated';
            $added = false;
            if (!$opsm_record) {
                $added = $insert_id;
                $action = 'added';
                $opsm_record = new \common\models\Orders_Products_Status_Manual();
                $opsm_record->language_id = $language_id;
                $opsm_record->orders_products_status_manual_id = $orders_products_status_manual_id == 0 ? $insert_id : $orders_products_status_manual_id;
            }
            $opsm_record->orders_products_status_manual_name = tep_db_prepare_input($orders_products_status_manual_name_array[$language_id]);
            $opsm_record->orders_products_status_manual_name_long = tep_db_prepare_input($orders_products_status_manual_name_long_array[$language_id]);
            $opsm_record->orders_products_status_manual_colour = $orders_products_status_manual_colour;
            try {
                $opsm_record->save(false);
            } catch (\Exception $e) {
                echo '<pre>';
                print_r($e);
                echo '</pre>';
            }
        }
        $opsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => $orders_products_status_manual_id == 0 ? $insert_id : $orders_products_status_manual_id]);
        if ($opsm_record) {
            if (($opsm_record = $opsm_record->set_matrix_array(array_keys((array) $_POST['orders_products_status_matrix']))) !== true) {
                echo '<pre>';
                print_r($opsm_record);
                echo '</pre>';
            }
        }
        echo json_encode(['message' => 'Status ' . $action, 'messageType' => 'alert-success', 'added' => $added]);
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/orders_products_status_manual');
        $orders_products_status_manual_id = Yii::$app->request->post('orders_products_status_manual_id', 0);
        if ($orders_products_status_manual_id) {
            $remove_status = true;
            $product = \common\models\Orders_Products::find()->select('COUNT(*) AS count')->and_where(['orders_products_status_manual' => $orders_products_status_manual_id])->as_array(true)->one();
            $error = [];
            if ($product['count'] > 0) {
                $remove_status = false;
                $error = ['message' => ERROR_ORDERS_PRODUCTS_STATUS_USED_IN_ORDERS_PRODUCTS, 'messageType' => 'alert-danger'];
            } else {
                $history = \common\models\Orders_Products_Status_History::find()->select('COUNT(*) AS count')->and_where(['orders_products_status_manual_id' => $orders_products_status_manual_id])->as_array(true)->one();
                if ($history['count'] > 0) {
                    $remove_status = false;
                    $error = ['message' => ERROR_ORDERS_PRODUCTS_STATUS_USED_IN_ORDERS_PRODUCTS_HISTORY, 'messageType' => 'alert-danger'];
                }
            }
            if (!$remove_status) {
                ?>
                <div class="alert fade in <?php 
                echo $error['messageType'];
                ?>">
                    <i data-dismiss="alert" class="icon-remove close"></i>
                    <span id="message_plce"><?php 
                echo $error['message'];
                ?></span>
                </div>
                <?php 
            } else {
                $opsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => $orders_products_status_manual_id]);
                if ($opsm_record) {
                    $opsm_record = $opsm_record->set_matrix_array([]);
                }
                \common\models\Orders_Products_Status_Manual::delete_all(['orders_products_status_manual_id' => $orders_products_status_manual_id]);
                echo 'reset';
            }
        }
    }
}