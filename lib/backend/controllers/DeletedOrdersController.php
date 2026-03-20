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
class Deleted_Orders_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_REPORTS', 'BOX_DELETED_ORDERS'];
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('admin/deleted-orders');
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        $this->selected_menu = ['reports', 'deleted-orders'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('deleted-orders/index'), 'title' => BOX_DELETED_ORDERS];
        $this->view->heading_title = BOX_DELETED_ORDERS;
        $this->view->log_table = [['title' => TABLE_HEADING_DATE_ADDED, 'not_important' => 0], ['title' => TEXT_ORDER_ID, 'not_important' => 0], ['title' => 'Admin', 'not_important' => 0], ['title' => TABLE_HEADING_COMMENTS, 'not_important' => 0]];
        return $this->render('index');
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $records_total = 0;
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_condition = " where comments like '%" . $keywords . "%' ";
        } else {
            $search_condition = ' where 1 ';
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'date_added ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 1:
                    $order_by = 'orders_id ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 2:
                    $order_by = 'admin_id ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 3:
                    $order_by = 'comments ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                default:
                    $order_by = 'date_added';
                    break;
            }
        } else {
            $order_by = 'date_added';
        }
        $current_page_number = $start / $length + 1;
        $access_query_raw = 'select * from ' . \common\models\Orders_Delete_History::table_name() . " {$search_condition} order by {$order_by}";
        $_split = new \Split_Page_Results($current_page_number, $length, $access_query_raw, $records_total, 'orders_history_id');
        $access_query = tep_db_query($access_query_raw);
        while ($access = tep_db_fetch_array($access_query)) {
            $response_list[] = [\common\helpers\Date::datetime_short($access['date_added']) . '<input class="cell_identify" type="hidden" value="' . $access['orders_history_id'] . '">', $access['orders_id'], $access['admin_id'], $access['comments']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
}