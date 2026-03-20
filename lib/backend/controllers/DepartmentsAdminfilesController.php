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

use common\models\Pdo_Connector;
use Yii;
class Departments_Adminfiles_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DEPARTMENTS', 'BOX_DEPARTMENTS_BOXES'];
    public function action_index()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $selected_department_id = (int) Yii::$app->request->get('department_id');
        $departments = [];
        $departments_query = tep_db_query('SELECT * FROM ' . TABLE_DEPARTMENTS . ' WHERE departments_status > 0');
        while ($department = tep_db_fetch_array($departments_query)) {
            if ($selected_department_id == 0) {
                $selected_department_id = $department['departments_id'];
            }
            $departments[] = ['id' => $department['departments_id'], 'text' => $department['departments_store_name'], 'link' => Yii::$app->url_manager->create_url(['departments-adminfiles/', 'department_id' => $department['departments_id']])];
        }
        $this->selected_menu = ['departments', 'departments-adminfiles'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('departments-adminfiles/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['departments-adminfiles/edit', 'department_id' => $selected_department_id]) . '" class="create_item">' . IMAGE_INSERT . '</a>';
        $this->view->access_table = [['title' => TABLE_HEADING_NAME, 'not_important' => 0]];
        return $this->render('index', ['departments' => $departments, 'selected_department_id' => $selected_department_id]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        if ($length == -1) {
            $length = 10000;
        }
        $response_list = [];
        $records_total = $records_filtered = 0;
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        $department_id = 0;
        if (isset($output['department_id'])) {
            $department_id = (int) $output['department_id'];
        }
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (is_array($departments)) {
            Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
            if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
                $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
                $search_condition = " where access_levels_name like '%" . $keywords . "%' ";
            } else {
                $search_condition = ' where 1 ';
            }
            if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
                switch ($_GET['order'][0]['column']) {
                    case 0:
                        $order_by = 'access_levels_name ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                        break;
                    default:
                        $order_by = 'access_levels_name';
                        break;
                }
            } else {
                $order_by = 'access_levels_name';
            }
            $access_query_raw = ' from ' . TABLE_ACCESS_LEVELS . " {$search_condition} order by {$order_by}";
            //---
            $current_page_number = $start / $length + 1;
            $offset = $length * ($current_page_number - 1);
            $sql_limit = ' limit ' . max($offset, 0) . ', ' . $length;
            Pdo_Connector::query('select count(*) as total' . $access_query_raw);
            $records_count = Pdo_Connector::fetch();
            $records_total = $records_count['total'];
            Pdo_Connector::query('select *' . $access_query_raw . $sql_limit);
            //---
            //$current_page_number = ( $start / $length ) + 1;
            //$_split = new \splitPageResults($current_page_number, $length, $accessQueryRaw, $recordsTotal, 'access_levels_id');
            //$accessQuery = tep_db_query($accessQueryRaw);
            while ($access = Pdo_Connector::fetch()) {
                //while ($access = tep_db_fetch_array($accessQuery)) {
                $response_list[] = [$access['access_levels_name'] . '<input class="cell_identify" type="hidden" value="' . $access['access_levels_id'] . '">'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_preview()
    {
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select * from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . $item_id . "'");
        $access = Pdo_Connector::fetch();
        if (is_array($access)) {
            echo '<div class="or_box_head">' . $access['access_levels_name'] . '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<a class="btn btn-edit btn-no-margin" href="' . Yii::$app->url_manager->create_url(['departments-adminfiles/edit', 'item_id' => $item_id, 'department_id' => $department_id]) . '">' . IMAGE_EDIT . '</a>';
            echo '<button class="btn btn-delete" onclick="accessDelete(\'' . $item_id . '\')">' . IMAGE_DELETE . '</button>';
            echo '</div>';
        }
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        \common\helpers\Translation::init('admin/categories');
        $this->selected_menu = ['departments', 'departments-adminfiles'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('departments-adminfiles/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        if (Yii::$app->request->is_post) {
            $item_id = (int) Yii::$app->request->post('item_id');
            $this->layout = false;
        } else {
            $item_id = (int) Yii::$app->request->get('item_id');
        }
        $department_id = (int) Yii::$app->request->get('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        if ($item_id > 0) {
            $action_name = IMAGE_EDIT;
        } else {
            $action_name = IMAGE_INSERT;
        }
        $access_query = Pdo_Connector::query('select * from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . $item_id . "'");
        $access = Pdo_Connector::fetch($access_query);
        $access_info = new \Object_Info(is_array($access) ? $access : ['access_levels_persmissions' => '']);
        $acl_tree = \common\helpers\Acl::build_tree_pdo($access_info->access_levels_persmissions);
        return $this->render('edit', ['actionName' => $action_name, 'accessInfo' => $access_info, 'aclTree' => $acl_tree, 'item_id' => $item_id, 'department_id' => $department_id]);
    }
    public function action_submit()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $item_id = (int) Yii::$app->request->post('item_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        $access_levels_name = Yii::$app->request->post('access_levels_name');
        $persmissions = Yii::$app->request->post('persmissions');
        if (!is_array($persmissions)) {
            $persmissions = [];
        }
        $access_levels_persmissions = implode(',', $persmissions);
        $sql_data_array = ['access_levels_name' => $access_levels_name, 'access_levels_persmissions' => $access_levels_persmissions];
        if ($item_id > 0) {
            Pdo_Connector::perform(TABLE_ACCESS_LEVELS, $sql_data_array, 'update', "access_levels_id = '" . (int) $item_id . "'");
        } else {
            Pdo_Connector::perform(TABLE_ACCESS_LEVELS, $sql_data_array);
            $item_id = Pdo_Connector::last_insert_id();
        }
        $message_type = 'success';
        $message = TEXT_MESSEAGE_SUCCESS;
        ?>
        <div class="popup-box-wrap pop-mess">
                <div class="around-pop-up"></div>
                <div class="popup-box">
                    <div class="pop-up-close pop-up-close-alert"></div>
                    <div class="pop-up-content">
                        <div class="popup-heading"><?php 
        echo TEXT_NOTIFIC;
        ?></div>
                        <div class="popup-content pop-mess-cont pop-mess-cont-<?php 
        echo $message_type;
        ?>">
                            <?php 
        echo $message;
        ?>
                        </div>   
                    </div>  
                    <div class="noti-btn">
                    <div></div>
                    <div><span class="btn btn-primary"><?php 
        echo TEXT_BTN_OK;
        ?></span></div>
                </div>
                </div> 
                <script>
                $('body').scrollTop(0);
                $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function(){
                    $(this).parents('.pop-mess').remove();
                });
                
            </script>
            </div>
<?php 
        echo '<script> window.location.replace("' . Yii::$app->url_manager->create_url(['departments-adminfiles/edit', 'item_id' => $item_id, 'department_id' => $department_id]) . '");</script>';
        //return $this->actionEdit();
    }
    public function action_delete()
    {
        $item_id = (int) Yii::$app->request->post('item_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('delete from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . $item_id . "'");
    }
    public function action_recalc_acl()
    {
        $this->layout = false;
        $persmissions = Yii::$app->request->post('persmissions');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        $acl_tree = \common\helpers\Acl::build_tree_pdo($persmissions);
        return $this->render('recalc-acl', ['aclTree' => $acl_tree]);
    }
}