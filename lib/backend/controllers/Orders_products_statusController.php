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
class Orders_products_status_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_SETTINGS_ORDERS_PRODUCTS_STATUS', 'BOX_ORDERS_PRODUCTS_STATUS'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'status', 'orders_products_status'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders_products_status/index'), 'title' => HEADING_TITLE_ORDERS_PRODUCTS_STATUS];
        $this->view->heading_title = HEADING_TITLE_ORDERS_PRODUCTS_STATUS;
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
        $ops_query = \common\models\Orders_Products_Status::find()->and_where(['language_id' => $languages_id]);
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $ops_query->and_where(['or', ['like', 'orders_products_status_name', tep_db_input(tep_db_prepare_input($_GET['search']['value']))], ['like', 'orders_products_status_name_long', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]]);
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $ops_query->order_by('orders_products_status_name_long ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 1:
                    $ops_query->order_by('orders_products_status_name ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                default:
                    $ops_query->order_by('orders_products_status_id ASC');
                    break;
            }
        } else {
            $ops_query->order_by('orders_products_status_id ASC');
        }
        $orders_products_status_query_numrows = $ops_query->count();
        if ($length > 0) {
            $ops_query->limit($length)->offset($start);
        }
        $ops_query = $ops_query->as_array(true)->all();
        $response_list = [];
        foreach ($ops_query as $ops_record) {
            $response_list[] = [$ops_record['orders_products_status_name_long'] . tep_draw_hidden_field('id', $ops_record['orders_products_status_id'], 'class="cell_identify"'), $ops_record['orders_products_status_name']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $orders_products_status_query_numrows, 'recordsFiltered' => $orders_products_status_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_statusactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders_products_status');
        $this->layout = false;
        $ops_record = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => Yii::$app->request->post('orders_products_status_id', 0), 'language_id' => $languages_id]);
        if ($ops_record) {
            $ops_name_original = ['TEXT_STATUS_LONG_' . \common\helpers\Order_Product::get_status_array()[$ops_record->orders_products_status_id]['key'], 'TEXT_STATUS_' . \common\helpers\Order_Product::get_status_array()[$ops_record->orders_products_status_id]['key']];
            $ops_name_original[0] = defined($ops_name_original[0]) ? constant($ops_name_original[0]) : $ops_name_original[0];
            $ops_name_original[1] = defined($ops_name_original[1]) ? constant($ops_name_original[1]) : $ops_name_original[1];
            echo '<div class="or_box_head" style="color: ' . \common\helpers\Order_Product::get_status_array()[$ops_record->orders_products_status_id]['colour'] . '">(' . implode(' / ', $ops_name_original) . ')</div>';
            echo '<div class="or_box_head" style="color: ' . $ops_record->get_colour() . '">' . $ops_record->orders_products_status_name_long . ' / ' . $ops_record->orders_products_status_name . '</div>';
            $orders_products_status_inputs_string = '';
            $languages = \common\helpers\Language::get_languages();
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $orders_products_status_inputs_string .= '<div class="col_desc">' . $languages[$i]['image'] . '&nbsp;' . \common\helpers\Order::get_orders_products_status_name($ops_record->orders_products_status_id, $languages[$i]['id']) . '</div>';
            }
            echo $orders_products_status_inputs_string;
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button class="btn btn-edit btn-no-margin" onclick="statusEdit(' . $ops_record->orders_products_status_id . ')">' . IMAGE_EDIT . '</button>';
            echo '</div>';
        }
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->top_buttons[] = '<span class="btn btn-confirm">' . IMAGE_UPDATE . '</span>';
        \common\helpers\Translation::init('admin/orders_products_status');
        $ops_record = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => Yii::$app->request->get('orders_products_status_id', 0)]);
        if (!$ops_record) {
            return $this->redirect('index');
        }
        $orders_products_status_inputs_string = [];
        $orders_products_status_inputs_string_long = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $orders_products_status_inputs_string[$languages[$i]['id']] = \yii\helpers\Html::input('text', 'orders_products_status_name[' . $languages[$i]['id'] . ']', \common\helpers\Order::get_orders_products_status_name($ops_record->orders_products_status_id, $languages[$i]['id'], false), ['class' => 'form-control']);
            $orders_products_status_inputs_string_long[$languages[$i]['id']] = \yii\helpers\Html::input('text', 'orders_products_status_name_long[' . $languages[$i]['id'] . ']', \common\helpers\Order::get_orders_products_status_name($ops_record->orders_products_status_id, $languages[$i]['id']), ['class' => 'form-control']);
        }
        $opsmm_array = $ops_record ? $ops_record->get_matrix_array(true) : [];
        $orders_products_status_manual_matrix_string = [];
        foreach (\common\models\Orders_Products_Status_Manual::find_all(['language_id' => $languages_id]) as $opsm_record) {
            $opsm_id = 'opsm_' . $opsm_record->orders_products_status_manual_id;
            $orders_products_status_manual_matrix_string[] = ['label' => '<label for="' . $opsm_id . '">' . $opsm_record->orders_products_status_manual_name_long . '</label>', 'element' => \yii\helpers\Html::checkbox('orders_products_status_manual_matrix[' . $opsm_record->orders_products_status_manual_id . ']', isset($opsmm_array[$opsm_record->orders_products_status_manual_id]), ['id' => $opsm_id, 'class' => 'form-control'])];
        }
        $this->selected_menu = ['settings', 'status', 'orders_products_status'];
        $this->view->heading_title = TEXT_INFO_HEADING_EDIT_ORDERS_PRODUCTS_STATUS;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders_products_status/index'), 'title' => TEXT_INFO_HEADING_EDIT_ORDERS_PRODUCTS_STATUS];
        return $this->render('edit', ['orders_products_status_id' => $ops_record->orders_products_status_id, 'orders_products_status_colour' => $ops_record->get_colour(), 'orders_products_status_inputs_string' => $orders_products_status_inputs_string, 'orders_products_status_inputs_string_long' => $orders_products_status_inputs_string_long, 'orders_products_status_manual_matrix_string' => $orders_products_status_manual_matrix_string, 'languages' => $languages]);
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/orders_products_status');
        $ops_record = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => Yii::$app->request->get('orders_products_status_id', 0)]);
        if ($ops_record) {
            $languages = \common\helpers\Language::get_languages();
            $orders_products_status_colour = trim($_POST['orders_products_status_colour']);
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $language_id = $languages[$i]['id'];
                $orders_products_status_name_array = $_POST['orders_products_status_name'];
                $orders_products_status_name_long_array = $_POST['orders_products_status_name_long'];
                $ops_record_edit = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => $ops_record->orders_products_status_id, 'language_id' => (int) $language_id]);
                $action = 'updated';
                $added = false;
                if (!$ops_record_edit) {
                    $added = $ops_record->orders_products_status_id;
                    $action = 'added';
                    $ops_record_edit = new \common\models\Orders_Products_Status();
                    $ops_record_edit->language_id = $language_id;
                    $ops_record_edit->orders_products_status_id = $ops_record->orders_products_status_id;
                }
                $ops_record_edit->orders_products_status_name = tep_db_prepare_input($orders_products_status_name_array[$language_id]);
                $ops_record_edit->orders_products_status_name_long = tep_db_prepare_input($orders_products_status_name_long_array[$language_id]);
                $ops_record_edit->orders_products_status_colour = $orders_products_status_colour;
                try {
                    $ops_record_edit->save(false);
                } catch (\Exception $exc) {
                    echo '<pre>';
                    print_r($exc);
                    echo '</pre>';
                }
            }
            $ops_record = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => $ops_record->orders_products_status_id]);
            if ($ops_record) {
                if (($ops_record = $ops_record->set_matrix_array(array_keys((array) \Yii::$app->request->post('orders_products_status_manual_matrix')))) !== true) {
                    echo '<pre>';
                    print_r($ops_record);
                    echo '</pre>';
                }
            }
            echo json_encode(['message' => 'Status ' . $action, 'messageType' => 'alert-success', 'added' => $added]);
        } else {
            echo json_encode(['message' => 'Status not found', 'messageType' => 'alert-danger', 'added' => false]);
        }
    }
}