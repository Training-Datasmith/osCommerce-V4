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

class Adminmembers_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_MEMBERS'];
    public function action_index()
    {
        $this->selected_menu = ['administrator', 'adminmembers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="' . \Yii::$app->url_manager->create_url(['adminmembers/adminedit']) . '" class="btn btn-primary">' . IMAGE_INSERT . '</a>';
        $this->view->admin_table = [['title' => TABLE_HEADING_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_EMAIL, 'not_important' => 0], ['title' => TABLE_HEADING_GROUPS, 'not_important' => 0], ['title' => TABLE_HEADING_LOGNUM, 'not_important' => 1]];
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) \Yii::$app->request->get('row', 0);
        $access_array = [];
        $access_array[0] = TEXT_ALL;
        $access_query = tep_db_query('select * from ' . TABLE_ACCESS_LEVELS . ' order by access_levels_id ');
        while ($access = tep_db_fetch_array($access_query)) {
            $access_array[$access['access_levels_id']] = $access['access_levels_name'];
        }
        $this->view->filter_status_types = \yii\helpers\Html::drop_down_list('aclID', (int) \Yii::$app->request->get('aclID', 0), $access_array, ['class' => 'form-control']);
        return $this->render('index');
    }
    public function action_memberlist()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $draw = \Yii::$app->request->get('draw');
        $start = \Yii::$app->request->get('start');
        $length = \Yii::$app->request->get('length');
        $search = '';
        if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_keywords = explode(' ', $keywords);
            if (is_array($search_keywords) && count($search_keywords) > 1) {
                $search_condition = ' where 1';
                foreach ($search_keywords as $key => $keyword) {
                    $search_condition .= ' and (';
                    $search_condition .= " a.admin_firstname like '%" . tep_db_input($keyword) . "%' ";
                    $search_condition .= " or a.admin_lastname like '%" . tep_db_input($keyword) . "%' ";
                    $search_condition .= " or a.admin_email_address like '%" . tep_db_input($keyword) . "%' ";
                    $search_condition .= ') ';
                }
            } else {
                $search_condition = " where (a.admin_firstname like '%" . $keywords . "%' or a.admin_lastname like '%" . $keywords . "%' or a.admin_email_address like '%" . $keywords . "%')";
            }
        } else {
            $search_condition = ' where 1 ';
        }
        $form_filter = \Yii::$app->request->get('filter');
        parse_str($form_filter, $filter);
        if ($filter['aclID'] > 0) {
            $search .= " and a.access_levels_id = '" . (int) $filter['aclID'] . "'";
        }
        if (isset($filter['status']) && $filter['status'] == 1) {
            $search .= " and a.login_failture < '3'";
        }
        if (isset($filter['status']) && $filter['status'] == 2) {
            $search .= " and a.login_failture > '2'";
        }
        $search_condition .= $search;
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'a.admin_firstname ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 1:
                    $order_by = 'a.admin_email_address ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 2:
                    $order_by = 'al.access_levels_name ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 3:
                    $order_by = 'a.admin_lognum ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                default:
                    $order_by = 'a.admin_lastname, a.admin_firstname';
                    break;
            }
        } else {
            $order_by = 'a.admin_firstname, a.admin_lastname';
        }
        $db_admin_query_raw = 'select a.*, al.access_levels_name
                            from ' . TABLE_ADMIN . ' a
                            left join ' . TABLE_ACCESS_LEVELS . " al ON a.access_levels_id = al.access_levels_id\n                            {$search_condition}\n                            order by {$order_by}";
        $current_page_number = $start / $length + 1;
        $db_admin_split = new \Split_Page_Results($current_page_number, $length, $db_admin_query_raw, $db_admin_query_numrows, 'a.admin_id');
        $db_admin_query = tep_db_query($db_admin_query_raw);
        $records_total = $records_filtered = 0;
        $response_list = [];
        while ($admin = tep_db_fetch_array($db_admin_query)) {
            $disabled_admin = '';
            if ($admin['login_failture'] > 2) {
                $disabled_admin = 'dis_module';
            }
            $response_list[] = ['<div class="' . $disabled_admin . '">' . $admin['admin_firstname'] . ' ' . $admin['admin_lastname'] . '<input class="cell_identify" type="hidden" value="' . $admin['admin_id'] . '">' . '</div>', '<div class="' . $disabled_admin . '">' . $admin['admin_email_address'] . '</div>', '<div class="' . $disabled_admin . '">' . $admin['access_levels_name'] . (empty($admin['admin_persmissions']) ? '' : ' (' . TEXT_MANUALLY_UPDATED . ')') . '</div>', '<div class="' . $disabled_admin . '">' . $admin['admin_lognum'] . '</div>'];
        }
        $_response = ['draw' => $draw, 'recordsTotal' => $db_admin_query_numrows, 'recordsFiltered' => $db_admin_query_numrows, 'data' => $response_list];
        echo json_encode($_response, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    public function action_adminmembersactions()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->layout = false;
        $admin_id = \Yii::$app->request->post('admin_id');
        $query = tep_db_query('
          select distinct(a.admin_id), a.*, al.access_levels_name
          from ' . TABLE_ADMIN . ' a
          left join ' . TABLE_ACCESS_LEVELS . " al ON a.access_levels_id = al.access_levels_id\n          where a.admin_id = '" . (int) $admin_id . "'");
        $admins = tep_db_fetch_array($query);
        if (!is_array($admins)) {
            die('Wrong data.');
        }
        $m_info = new \Object_Info($admins);
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or"><div>' . TEXT_INFO_FULLNAME . '</div><div>' . $m_info->admin_firstname . ' ' . $m_info->admin_lastname . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_EMAIL . '</div><div>' . $m_info->admin_email_address . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_PHONE . '</div><div>' . $m_info->admin_phone_number . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_GROUP . '</div><div>' . $m_info->access_levels_name . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_CREATED . '</div><div>' . \common\helpers\Date::date_short($m_info->admin_created) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_MODIFIED . '</div><div>' . \common\helpers\Date::date_short($m_info->admin_modified) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_LOGDATE . '</div><div>' . \common\helpers\Date::date_short($m_info->admin_logdate) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_LOGNUM . '</div><div>' . $m_info->admin_lognum . '</div></div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<a class="btn btn-edit btn-no-margin" href="' . \Yii::$app->url_manager->create_url(['adminmembers/adminedit', 'admin_id' => $m_info->admin_id]) . '">' . IMAGE_EDIT . '</a>' . '<button onclick="confirmDeleteAdmin(' . $m_info->admin_id . ')" class="btn btn-delete">' . IMAGE_DELETE . '</button>';
        if (\common\helpers\Acl::rule(['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_BOXES'])) {
            echo '<a class="btn btn-primary btn-process-order" href="' . \Yii::$app->url_manager->create_url(['adminmembers/override-permissions', 'admin_id' => $m_info->admin_id]) . '">' . TEXT_OVERRIDE_PERMISSIONS . '</a>';
        }
        if (\common\helpers\Acl::rule(['SUPERUSER'])) {
            echo '<a class="btn btn-process-order" href="' . \Yii::$app->url_manager->create_url(['adminmembers/assign-platforms', 'admin_id' => $m_info->admin_id]) . '">' . TEXT_ASSIGN_PLATFORMS . '</a>';
        }
        if (\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            echo '<button class="btn btn-process-order" onclick="assignWarehouses(' . $m_info->admin_id . ')">' . TEXT_ASSIGN_WAREHOUSES . '</button>';
        }
        if (\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
            echo '<button class="btn btn-process-order" onclick="assignSuppliers(' . $m_info->admin_id . ')">' . TEXT_ASSIGN_SUPPLIERS . '</button>';
        }
        if ($m_info->login_failture > 2) {
            if (\common\helpers\Acl::rule(['MANAGE_MEMBERS', 'TEXT_ENABLE_USER'])) {
                echo '<button class="btn btn-primary btn-process-order" onclick="enableUser(' . $m_info->admin_id . ')">' . TEXT_ENABLE_USER . '</button>';
            }
            if (!empty($m_info->login_failture_date)) {
                echo '<div class="row_or"><div>DATE:</div><div>' . \common\helpers\Date::date_short($m_info->login_failture_date) . '</div></div>';
            }
            if (!empty($m_info->login_failture_ip)) {
                echo '<div class="row_or"><div>IP:</div><div>' . $m_info->login_failture_ip . '</div></div>';
            }
        } else if (\common\helpers\Acl::rule(['MANAGE_MEMBERS', 'TEXT_DISABLE_USER'])) {
            echo '<button class="btn btn-primary btn-process-order" onclick="disableUser(' . $m_info->admin_id . ')">' . TEXT_DISABLE_USER . '</button>';
        }
        /**
         * @var $ext \common\extensions\GoogleAuthenticator\GoogleAuthenticator
         */
        if ($ext = \common\helpers\Extensions::is_allowed('GoogleAuthenticator')) {
            echo $ext::manage_buttons($m_info);
        }
        echo '<a class="btn btn-primary btn-process-order" href="' . \Yii::$app->url_manager->create_url(['adminmembers/admin-login-view', 'admin_id' => $m_info->admin_id]) . '">' . TEXT_ADMIN_LOGIN_VIEW . '</a>';
        echo '<a class="btn btn-danger btn-process-order active" href="' . \Yii::$app->url_manager->create_url(['adminmembers/admin-login-view', 'type' => 'invalid', 'admin_id' => $m_info->admin_id]) . '">' . (defined('TEXT_ADMIN_LOGIN_VIEW_INVALID') ? TEXT_ADMIN_LOGIN_VIEW_INVALID : '') . '</a>';
        echo '<a class="btn btn-primary btn-process-order" href="' . \Yii::$app->url_manager->create_url(['adminmembers/admin-device-view', 'admin_id' => $m_info->admin_id]) . '">' . TEXT_ADMIN_DEVICE_VIEW . '</a>';
        echo '<a class="btn btn-primary btn-process-order" href="' . \Yii::$app->url_manager->create_url(['adminmembers/admin-session-view', 'admin_id' => $m_info->admin_id]) . '">' . TEXT_ADMIN_SESSION_VIEW . '</a>';
        echo '<a class="btn btn-primary btn-process-order" href="' . \Yii::$app->url_manager->create_url(['adminmembers/admin-login-session-view', 'admin_id' => $m_info->admin_id]) . '">' . TEXT_ADMIN_LOGIN_SESSION_VIEW . '</a>';
        echo '</div>';
        \common\helpers\Translation::init('admin/customers');
        $title_data_pattern = sprintf(ENTRY_PASSWORD_ERROR, ADMIN_PASSWORD_MIN_LENGTH);
        $pass_data_pattern = '.{' . ADMIN_PASSWORD_MIN_LENGTH . '}';
        if (defined('ADMIN_PASSWORD_STRONG')) {
            if (ADMIN_PASSWORD_STRONG == 'ULNS') {
                $title_data_pattern = sprintf(ENTRY_PASSWORD_ULNS_ERROR, ADMIN_PASSWORD_MIN_LENGTH);
                $pass_data_pattern = addslashes('(?=.*\d)(?=.*\W+)(?=.*[a-z])(?=.*[A-Z]).{' . ADMIN_PASSWORD_MIN_LENGTH . '}');
            } elseif (ADMIN_PASSWORD_STRONG == 'ULN') {
                $title_data_pattern = sprintf(ENTRY_PASSWORD_ULN_ERROR, ADMIN_PASSWORD_MIN_LENGTH);
                $pass_data_pattern = addslashes('(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{' . ADMIN_PASSWORD_MIN_LENGTH . '}');
            }
        }
        \common\helpers\Translation::init('main');
        echo '<div class="btn-toolbar btn-toolbar-order btn-toolbar-pass"><span class="btn btn-pass-cus">' . T_UPDATE_PASS . '</span>
                            <script>
                            $(document).ready(function() {
                            $("a.popup").popUp();
                            $(".btn-pass-cus").on("click", function(){
                                alertMessage("<div class=\"popup-heading popup-heading-pass\">' . TEXT_UPDATE_PASSWORD_FOR . ' ' . $m_info->admin_firstname . '&nbsp;' . $m_info->admin_lastname . '</div><div class=\"popup-content\"><form name=\"passw_form\" id=\"passw_form\" action=\"' . tep_href_link('adminmembers', \common\helpers\Output::get_all_get_params(['admin_id', 'action']) . 'admin_id=' . $m_info->admin_id . '&action=password') . '\" method=\"post\"><table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr><td class=\"dataTableContent\"><a href=\"#\" class=\"generate_password\">' . TEXT_GENERATE_PASSWORD . '</a></td></tr><tr><td class=\"dataTableContent\">' . T_NEW_PASS . ':</td><td class=\"dataTableContent\"><input type=\"password\" data-required=\"' . $title_data_pattern . '\" data-pattern=\"' . $pass_data_pattern . '\" name=\"change_pass\" class=\"form-control\"></td></tr></table><div class=\"btn-bar\" style=\"padding-bottom: 0;\"><div class=\"btn-left\"><span class=\"btn btn-cancel\">' . IMAGE_CANCEL . '</span></div><div class=\"btn-right\"><input type=\"submit\" value=\"' . IMAGE_UPDATE . '\" class=\"btn btn-primary\"></div></div><input type=\"hidden\" name=\"admin_id\" value=\"' . $m_info->admin_id . '\"></form></div>");
                                passFormAfretShow();
                            });
                            });
                            </script>
                            </div>';
    }
    public function action_assign_platforms()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $admin_id = (int) \Yii::$app->request->get('admin_id');
        if ($admin_id == 0) {
            return $this->redirect(\Yii::$app->url_manager->create_url(['adminmembers']));
        }
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $assigned_platforms = \yii\helpers\Array_Helper::map(\common\models\Admin_Platforms::find()->select(['platform_id'])->where(['admin_id' => $admin_id])->as_array()->all(), 'platform_id', 'platform_id');
        return $this->render('assign-platforms', ['admin_id' => $admin_id, 'assigned_platforms' => $assigned_platforms, 'platforms' => \common\classes\platform::get_list(true, true)]);
    }
    public function action_assign_warehouses()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->layout = false;
        $admin_id = (int) \Yii::$app->request->post('admin_id');
        $assigned_warehouses = \yii\helpers\Array_Helper::map(\common\models\Admin_Warehouses::find()->select(['warehouse_id'])->where(['admin_id' => $admin_id])->as_array()->all(), 'warehouse_id', 'warehouse_id');
        echo tep_draw_form('admin', 'adminmembers', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="admin_edit" onSubmit="return check_form();"');
        echo '<div class="or_box_head">' . BOX_CATALOG_WAREHOUSES . '</div>';
        foreach (\common\helpers\Warehouses::get_warehouses(true) as $info) {
            echo '<div class="row_fields">';
            echo tep_draw_checkbox_field('warehouse_id[]', $info['id'], isset($assigned_warehouses[$info['id']])) . '<span>' . $info['text'] . '</span>';
            echo '</div>';
        }
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<input type="submit" class="btn btn-no-margin" value="' . IMAGE_UPDATE . '" >';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo '</div>';
        echo tep_draw_hidden_field('admin_id', $admin_id);
        echo tep_draw_hidden_field('action', 'warehouses');
        echo '</form>';
    }
    public function action_assign_suppliers()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->layout = false;
        $admin_id = (int) \Yii::$app->request->post('admin_id');
        $assigned_suppliers = \yii\helpers\Array_Helper::map(\common\models\Admin_Suppliers::find()->select(['suppliers_id'])->where(['admin_id' => $admin_id])->as_array()->all(), 'suppliers_id', 'suppliers_id');
        echo tep_draw_form('admin', 'adminmembers', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="admin_edit" onSubmit="return check_form();"');
        echo '<div class="or_box_head">' . BOX_CATALOG_SUPPIERS . '</div>';
        foreach (\common\helpers\Suppliers::get_suppliers(true) as $info) {
            echo '<div class="row_fields">';
            echo tep_draw_checkbox_field('suppliers_id[]', $info['suppliers_id'], isset($assigned_suppliers[$info['suppliers_id']])) . '<span>' . $info['suppliers_name'] . '</span>';
            echo '</div>';
        }
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<input type="submit" class="btn btn-no-margin" value="' . IMAGE_UPDATE . '" >';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo '</div>';
        echo tep_draw_hidden_field('admin_id', $admin_id);
        echo tep_draw_hidden_field('action', 'suppliers');
        echo '</form>';
    }
    public function action_adminedit()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        //$this->layout = false;
        $error = $entry_firstname_error = $entry_lastname_error = $entry_admin_email_address_error = false;
        $entry_admin_groups_name_error = false;
        $admin_id = (int) \Yii::$app->request->get('admin_id', 0);
        $query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = {$admin_id}; ");
        if ($admin = tep_db_fetch_array($query)) {
            $m_info = new \Object_Info($admin);
        }
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#admin_edit\').trigger(\'submit\')">' . IMAGE_UPDATE . '</span>';
        $access_array = [];
        $access_query = tep_db_query('select * from ' . TABLE_ACCESS_LEVELS . ' order by sort_order, access_levels_name ');
        while ($access = tep_db_fetch_array($access_query)) {
            $access_array[] = ['id' => $access['access_levels_id'], 'text' => $access['access_levels_name']];
        }
        /*$access_array[] = array(
              array('id' => 0, 'text' => 'none')
          );*/
        $admin_two_step_auth_array = [['id' => '', 'text' => TEXT_TWO_STEP_AUTH_DEFAULT], ['id' => 'email', 'text' => TEXT_TWO_STEP_AUTH_EMAIL], ['id' => 'sms', 'text' => TEXT_TWO_STEP_AUTH_SMS], ['id' => 'disabled', 'text' => TEXT_DISABLED]];
        \common\helpers\Translation::init('admin/texts');
        return $this->render('adminedit', ['mInfo' => $m_info ?? null, 'access_array' => $access_array, 'admin_id' => $admin_id, 'adminTwoStepAuthArray' => $admin_two_step_auth_array]);
    }
    public function action_confirmadmindelete()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        \common\helpers\Translation::init('admin/faqdesk');
        $this->layout = false;
        $admin_id = \Yii::$app->request->post('admin_id');
        $query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = {$admin_id}; ");
        if ($admin = tep_db_fetch_array($query)) {
            $m_info = new \Object_Info($admin);
        } else {
            die('Wrong admin data.');
        }
        echo tep_draw_form('admin', FILENAME_ADMIN_ACCOUNT, \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="admin_edit" onSubmit="return deleteAdmin();"');
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_ITEM . '</div>';
        echo '<div class="col_desc">' . TEXT_DELETE_ITEM_INTRO . ' ' . $m_info->admin_firstname . ' ' . $m_info->admin_lastname . '</div>';
        ?>
        <div class="btn-toolbar btn-toolbar-order">
            <button class="btn btn-delete btn-no-margin"><?php 
        echo IMAGE_DELETE;
        ?></button><?php 
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo tep_draw_hidden_field('admin_id', $m_info->admin_id);
        ?>
        </div>
        </form>
            <?php 
    }
    public function action_admindelete()
    {
        $this->layout = false;
        $admin_id = \Yii::$app->request->post('admin_id');
        if ((int) $admin_id == (int) \Yii::$app->session->get('login_id')) {
            die('Operation not permitted!');
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation((int) $admin_id)->set_type($log_universal::ULT_ADMIN_DELETE)->set_before_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one());
        }
        tep_db_query('delete from ' . TABLE_ADMIN . " where admin_id = '" . (int) $admin_id . "'");
        if (isset($log_universal)) {
            $log_universal->set_after_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one())->do_save(true);
            unset($log_universal);
        }
        try {
            \common\models\Admin_Login_Session::delete_all(['als_admin_id' => (int) $admin_id]);
            \common\models\Admin_Login::delete_all(['al_admin_id' => (int) $admin_id]);
        } catch (\Exception $exc) {
            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'ErrorDeleteAdminLogin');
        }
    }
    public function action_adminsubmit()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        //$this->layout = FALSE;
        $error = false;
        $message = '';
        $message_type = 'success';
        $admin_id = \Yii::$app->request->post('admin_id');
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation((int) $admin_id);
        }
        $action = \Yii::$app->request->post('action');
        if ($action == 'permissions') {
            if (isset($log_universal)) {
                $log_universal->set_type($log_universal::ULT_ADMIN_PLATFORM)->set_before_array(\common\models\Admin_Platforms::find()->select('platform_id')->where(['admin_id' => $admin_id])->index_by('platform_id')->as_array(true)->column());
            }
            $platform = \Yii::$app->request->post('platform_id');
            \common\models\Admin_Platforms::delete_all(['admin_id' => $admin_id]);
            if (is_array($platform)) {
                foreach ($platform as $value) {
                    $object = new \common\models\Admin_Platforms();
                    $object->platform_id = (int) $value;
                    $object->admin_id = (int) $admin_id;
                    $object->save();
                }
            }
            if (isset($log_universal)) {
                $log_universal->set_after_array(\common\models\Admin_Platforms::find()->select('platform_id')->where(['admin_id' => $admin_id])->index_by('platform_id')->as_array(true)->column())->do_save(true);
                unset($log_universal);
            }
            return 'ok';
            //$this->actionAdminmembersactions();
        } elseif ($action == 'warehouses') {
            if (isset($log_universal)) {
                $log_universal->set_type($log_universal::ULT_ADMIN_WAREHOUSE)->set_before_array(\common\models\Admin_Warehouses::find()->select('warehouse_id')->where(['admin_id' => $admin_id])->index_by('warehouse_id')->as_array(true)->column());
            }
            $warehouses = \Yii::$app->request->post('warehouse_id');
            \common\models\Admin_Warehouses::delete_all(['admin_id' => $admin_id]);
            if (is_array($warehouses)) {
                foreach ($warehouses as $value) {
                    $object = new \common\models\Admin_Warehouses();
                    $object->warehouse_id = (int) $value;
                    $object->admin_id = (int) $admin_id;
                    $object->save();
                }
            }
            if (isset($log_universal)) {
                $log_universal->set_after_array(\common\models\Admin_Warehouses::find()->select('warehouse_id')->where(['admin_id' => $admin_id])->index_by('warehouse_id')->as_array(true)->column())->do_save(true);
                unset($log_universal);
            }
            return $this->action_adminmembersactions();
        } elseif ($action == 'suppliers') {
            if (isset($log_universal)) {
                $log_universal->set_type($log_universal::ULT_ADMIN_SUPPLIER)->set_before_array(\common\models\Admin_Suppliers::find()->select('suppliers_id')->where(['admin_id' => $admin_id])->index_by('suppliers_id')->as_array(true)->column());
            }
            $suppliers = \Yii::$app->request->post('suppliers_id');
            \common\models\Admin_Suppliers::delete_all(['admin_id' => $admin_id]);
            if (is_array($suppliers)) {
                foreach ($suppliers as $value) {
                    $object = new \common\models\Admin_Suppliers();
                    $object->suppliers_id = (int) $value;
                    $object->admin_id = (int) $admin_id;
                    $object->save();
                }
            }
            if (isset($log_universal)) {
                $log_universal->set_after_array(\common\models\Admin_Suppliers::find()->select('suppliers_id')->where(['admin_id' => $admin_id])->index_by('suppliers_id')->as_array(true)->column())->do_save(true);
                unset($log_universal);
            }
            return $this->action_adminmembersactions();
        }
        if (isset($log_universal)) {
            $log_universal->set_before_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one());
        }
        $admin_firstname = tep_db_prepare_input($_POST['admin_firstname']);
        $admin_lastname = tep_db_prepare_input($_POST['admin_lastname'] ?? null);
        $admin_email_address = tep_db_prepare_input($_POST['admin_email_address']);
        $admin_phone_number = tep_db_prepare_input($_POST['admin_phone_number']);
        $admin_two_step_auth = tep_db_prepare_input($_POST['admin_two_step_auth']);
        $admin_group_level = tep_db_prepare_input($_POST['access_levels_name']);
        $frontend_translation = tep_db_prepare_input($_POST['frontend_translation'] ?? null);
        $sql_data_array = ['admin_id' => $admin_id, 'admin_firstname' => $admin_firstname, 'admin_lastname' => $admin_lastname, 'admin_email_address' => $admin_email_address, 'admin_phone_number' => $admin_phone_number, 'access_levels_id' => $admin_group_level, 'admin_two_step_auth' => $admin_two_step_auth, 'frontend_translation' => $frontend_translation ? 1 : 0];
        if (strlen($admin_firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
            $error = true;
            $message .= TEXT_INFO_FIRSTNAME . ' ' . sprintf(ENTRY_FIRST_NAME_ERROR, ENTRY_FIRST_NAME_MIN_LENGTH) . '<br/>';
        }
        if (trim($admin_email_address) == '') {
            $error = true;
            $message .= ENTRY_EMAIL_ADDRESS_CHECK_ERROR . '<br/>';
        } else {
            $check_dup = \common\models\Admin::find()->and_where(['like', 'admin_email_address', $admin_email_address, false])->and_where(['<>', 'admin_id', $admin_id])->exists();
            if ($check_dup) {
                $error = true;
                $message = ENTRY_EMAIL_ADDRESS_ERROR_EXISTS;
            }
        }
        foreach (\common\helpers\Hooks::get_list('adminmembers/before-save', '') as $filename) {
            include $filename;
        }
        if ($error === false) {
            $sql_data_array['admin_email_token'] = \common\helpers\Password::encrypt_password($sql_data_array['admin_email_address'], 'backend');
            if ((int) $admin_id > 0) {
                if (isset($log_universal)) {
                    $log_universal->set_type($log_universal::ULT_ADMIN_UPDATE);
                }
                tep_db_perform(TABLE_ADMIN, $sql_data_array, 'update', "admin_id = '" . (int) $admin_id . "'");
                tep_db_query('update ' . TABLE_ADMIN . " set admin_modified = now() where admin_id = '" . (int) $admin_id . "'");
                $message = SUCCESS_ADMIN_UPDATED;
            } else {
                if (isset($log_universal)) {
                    $log_universal->set_type($log_universal::ULT_ADMIN_CREATE);
                }
                $make_password = \common\helpers\Password::randomize();
                $sql_data_array['admin_password'] = \common\helpers\Password::encrypt_password($make_password, 'backend');
                $sql_data_array['password_last_update'] = 'now()';
                tep_db_perform(TABLE_ADMIN, $sql_data_array);
                $admin_id = tep_db_insert_id();
                $_GET['mID'] = $admin_id;
                // FIXME: Why do we need this?
                tep_db_query('update ' . TABLE_ADMIN . " set admin_created = now(), admin_modified = now() where admin_id = '" . (int) $admin_id . "'");
                $message = SUCCESS_ADMIN_CREATED;
                $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
                $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
                $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                $email_params = [];
                $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('admin'));
                $email_params['CUSTOMER_FIRSTNAME'] = $sql_data_array['admin_firstname'];
                $email_params['CUSTOMER_LASTNAME'] = $sql_data_array['admin_lastname'];
                $email_params['CUSTOMER_EMAIL'] = $sql_data_array['admin_email_address'];
                $email_params['STORE_OWNER'] = STORE_OWNER;
                $email_params['NEW_PASSWORD'] = $make_password;
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin create', $email_params);
                \common\helpers\Mail::send(
                    $sql_data_array['admin_firstname'] . ' ' . $sql_data_array['admin_lastname'],
                    $sql_data_array['admin_email_address'],
                    $email_subject,
                    //ADMIN_EMAIL_SUBJECT,
                    $email_text,
                    //sprintf(ADMIN_EMAIL_TEXT, $sql_data_array['admin_firstname'], \common\helpers\Output::get_clickable_link($adminUrl), $sql_data_array['admin_email_address'], $makePassword, STORE_OWNER),
                    STORE_OWNER,
                    STORE_OWNER_EMAIL_ADDRESS,
                    [],
                    '',
                    '',
                    ['add_br' => 'no']
                );
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('Communication')) {
                $ext::admin_action_admin_edit_save((int) $admin_id, \Yii::$app->request->post('communication_group_to_admin'));
            }
            if (false === \common\helpers\Acl::rule(['SUPERUSER'], 0, '', $admin_id)) {
                // offer to assign admin to platforms
                $check = \common\models\Admin_Platforms::find()->and_where(['admin_id' => $admin_id])->exists();
                if (!$check && count(\common\classes\platform::get_list(true, true)) > 0) {
                    $message_type = 'warning';
                    $message .= ' ' . sprintf(TEXT_ADMIN_ASSIGN_PLATFORMS, \Yii::$app->url_manager->create_url(['adminmembers/assign-platforms', 'admin_id' => $admin_id]));
                }
            }
            if (isset($log_universal)) {
                $log_universal->set_relation((int) $admin_id)->set_after_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one())->do_save(true);
                unset($log_universal);
            }
        }
        if ($error === true) {
            $message_type = 'warning';
            if ($message == '') {
                $message = WARN_UNKNOWN_ERROR;
            }
        }
        /** /
                ?>
                <div class="alert alert-<?= $messageType ?> fade in">
                <i data-dismiss="alert" class="icon-remove close"></i>
                <?= $message ?>
                </div>
        
                <?php
        
                 /**/
        $message_stack = \Yii::$container->get('message_stack');
        if ($error === true) {
            $message_stack->add($message, 'header', $message_type);
            return $this->action_adminedit();
            exit;
        } else {
            $message_stack->add_session($message, 'header', $message_type);
            echo '<script> window.location.replace("' . \Yii::$app->url_manager->create_url(['adminmembers/adminedit', 'admin_id' => $admin_id]) . '");</script>';
        }
    }
    public function action_override_permissions()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->selected_menu = ['administrator', 'adminmembers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $admin_id = (int) \Yii::$app->request->get('admin_id');
        $query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $admin = tep_db_fetch_array($query);
        if (!is_array($admin)) {
            die('Wrong data.');
        }
        $admin_persmissions = explode(',', $admin['admin_persmissions']);
        $check_access = tep_db_query('select access_levels_persmissions from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . (int) $admin['access_levels_id'] . "'");
        $access = tep_db_fetch_array($check_access);
        $selected_ids = explode(',', $access['access_levels_persmissions']);
        $acl_tree = \common\helpers\Acl::build_override_tree($selected_ids, $admin_persmissions);
        return $this->render('override-permissions', ['aclTree' => $acl_tree, 'admin_id' => $admin_id]);
    }
    public function action_recalc_acl()
    {
        $this->layout = false;
        $admin_id = (int) \Yii::$app->request->post('admin_id');
        $persmissions = \Yii::$app->request->post('persmissions');
        $query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $admin = tep_db_fetch_array($query);
        if (!is_array($admin)) {
            die('Wrong data.');
        }
        $check_access = tep_db_query('select access_levels_persmissions from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . (int) $admin['access_levels_id'] . "'");
        $access = tep_db_fetch_array($check_access);
        $selected_ids = explode(',', $access['access_levels_persmissions']);
        $admin_persmissions = [];
        foreach ($persmissions as $persmission) {
            if (!in_array($persmission, $selected_ids)) {
                $admin_persmissions[] = $persmission;
                //green - added
            }
        }
        foreach ($selected_ids as $selected) {
            if (!in_array($selected, $persmissions)) {
                $admin_persmissions[] = $selected * -1;
                //red - removed
            }
        }
        $acl_tree = \common\helpers\Acl::build_override_tree($selected_ids, $admin_persmissions);
        return $this->render('recalc-acl', ['aclTree' => $acl_tree]);
    }
    public function action_submit_permissions()
    {
        $admin_id = (int) \Yii::$app->request->post('admin_id');
        $query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $admin = tep_db_fetch_array($query);
        if (!is_array($admin)) {
            die('Wrong data.');
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation((int) $admin_id)->set_type($log_universal::ULT_ADMIN_PERMISSION)->set_before_array($admin);
        }
        $check_access = tep_db_query('select access_levels_persmissions from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . (int) $admin['access_levels_id'] . "'");
        $access = tep_db_fetch_array($check_access);
        $selected_ids = explode(',', $access['access_levels_persmissions']);
        $persmissions = \Yii::$app->request->post('persmissions');
        if (!is_array($persmissions)) {
            $persmissions = [];
        }
        $admin_persmissions = [];
        foreach ($persmissions as $persmission) {
            if (!in_array($persmission, $selected_ids)) {
                $admin_persmissions[] = $persmission;
                //green - added
            }
        }
        foreach ($selected_ids as $selected) {
            if (!in_array($selected, $persmissions)) {
                $admin_persmissions[] = $selected * -1;
                //red - removed
            }
        }
        $admin_persmissions = implode(',', $admin_persmissions);
        $sql_data_array = ['admin_persmissions' => $admin_persmissions];
        tep_db_perform(TABLE_ADMIN, $sql_data_array, 'update', "admin_id = '" . $admin_id . "'");
        if (isset($log_universal)) {
            $log_universal->set_after_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one())->do_save(true);
            unset($log_universal);
        }
        echo '<script> window.location.replace("' . \Yii::$app->url_manager->create_url(['adminmembers/override-permissions', 'admin_id' => $admin_id]) . '");</script>';
    }
    public function action_enable_admin()
    {
        $this->layout = false;
        $admin_id = \Yii::$app->request->post('admin_id');
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation((int) $admin_id)->set_type($log_universal::ULT_ADMIN_STATUS)->set_before_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one());
        }
        tep_db_query('update ' . TABLE_ADMIN . " set login_failture = 0 where admin_id = '" . (int) $admin_id . "'");
        if (isset($log_universal)) {
            $log_universal->set_after_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one())->do_save(true);
            unset($log_universal);
        }
    }
    public function action_disable_admin()
    {
        $this->layout = false;
        $admin_id = \Yii::$app->request->post('admin_id');
        if ((int) $admin_id == (int) \Yii::$app->session->get('login_id')) {
            die('Operation not permitted!');
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation((int) $admin_id)->set_type($log_universal::ULT_ADMIN_STATUS)->set_before_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one());
        }
        tep_db_query('update ' . TABLE_ADMIN . " set login_failture = 3 where admin_id = '" . (int) $admin_id . "'");
        if (isset($log_universal)) {
            $log_universal->set_after_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one())->do_save(true);
            unset($log_universal);
        }
        try {
            \common\models\Admin_Login_Session::delete_all(['als_admin_id' => (int) $admin_id]);
            \common\models\Admin_Login::delete_all(['al_admin_id' => (int) $admin_id]);
        } catch (\Exception $exc) {
            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'ErrorDisableAdminLogin');
        }
    }
    public function action_reset_admin_ga()
    {
        $this->layout = false;
        /**
         * @var $ext \common\extensions\GoogleAuthenticator\GoogleAuthenticator
         */
        if ($ext = \common\helpers\Extensions::is_allowed('GoogleAuthenticator')) {
            $admin_id = \Yii::$app->request->post('admin_id');
            echo $ext::reset_ga_secret($admin_id);
        }
    }
    public function action_generatepassword()
    {
        $this->layout = false;
        \common\helpers\Translation::init('account/password');
        \common\helpers\Translation::init('admin/admin_account');
        \common\helpers\Translation::init('main');
        $admin_id = \Yii::$app->request->post('admin_id');
        $admin_password = \Yii::$app->request->post('change_pass');
        $message_account_password = '';
        $save = true;
        if (empty($admin_password)) {
            $message_account_password = TEXT_MESS_PASSWORD_WRONG;
            $save = false;
        }
        if ($save) {
            $admin_info = \common\models\Admin::find()->where(['admin_id' => $admin_id])->one();
            if (!is_object($admin_info)) {
                $message_account_password = TEXT_INVALID_TOKEN;
                $save = false;
            }
        }
        if ($save) {
            if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
                $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
                $log_universal->set_relation((int) $admin_id)->set_type($log_universal::ULT_ADMIN_PASSWORD)->set_before_array($admin_info->to_array());
            }
            if (defined('ADMIN_PASSWORD_BAN_EASY') && ADMIN_PASSWORD_BAN_EASY == 'True') {
                $dont_accept_list = [$admin_info->admin_username, $admin_info->admin_firstname, $admin_info->admin_lastname, $admin_info->admin_phone_number];
                foreach ($dont_accept_list as $dont_accept_item) {
                    if (!empty($dont_accept_item)) {
                        preg_match('/^' . preg_quote($dont_accept_item) . '/i', $admin_password, $matches);
                        if (count($matches) > 0) {
                            $message_account_password = TEXT_MESS_PASSWORD_START_AT . ' ' . $dont_accept_item;
                            $save = false;
                        }
                        preg_match('/' . preg_quote($dont_accept_item) . '$/i', $admin_password, $matches);
                        if (count($matches) > 0) {
                            $message_account_password = TEXT_MESS_PASSWORD_END_AT . ' ' . $dont_accept_item;
                            $save = false;
                        }
                    }
                }
            }
        }
        if ($save) {
            if (defined('ADMIN_PASSWORD_BAN_EASY') && ADMIN_PASSWORD_BAN_EASY == 'True') {
                $easy_pass_check = \common\models\Easy_Passwords::find()->where(['password' => $admin_password])->one();
                if ($easy_pass_check instanceof \common\models\Easy_Passwords) {
                    $message_account_password = TEXT_MESS_PASSWORD_EASY;
                    $save = false;
                }
                unset($easy_pass_check);
            }
        }
        if ($save) {
            if (defined('ADMIN_PASSWORD_USE_SAME') && ADMIN_PASSWORD_USE_SAME == 'True') {
                if (\common\models\Admin_Old_Passwords::is_old($admin_info->admin_id, tep_db_prepare_input($admin_password)) == true) {
                    $message_account_password = TEXT_MESS_PASSWORD_OLD;
                    $save = false;
                }
            }
        }
        if ($save) {
            if (defined('ADMIN_PASSWORD_USE_SAME') && ADMIN_PASSWORD_USE_SAME == 'True') {
                \common\models\Admin_Old_Passwords::add_old($admin_info->admin_id, tep_db_prepare_input($admin_password));
            }
            $admin_info->admin_email_token = \common\helpers\Password::encrypt_password($admin_info->admin_email_address, 'backend');
            $admin_info->admin_password = \common\helpers\Password::encrypt_password(tep_db_prepare_input($admin_password), 'backend');
            $admin_info->password_last_update = date('Y-m-d H:i:s');
            $admin_info->clear_token();
            $message_account_password = TEXT_PASSWORD_CHANGED;
            if (isset($log_universal)) {
                $log_universal->set_after_array(\common\models\Admin::find()->where(['admin_id' => $admin_id])->as_array(true)->one())->do_save(true);
                unset($log_universal);
            }
            $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
            $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
            $STORE_NAME = $platform_config->const_value('STORE_NAME');
            $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
            $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
            $email_params = [];
            $email_params['STORE_NAME'] = $STORE_NAME;
            $email_params['NEW_PASSWORD'] = $admin_password;
            $email_params['CUSTOMER_FIRSTNAME'] = $admin_info->admin_firstname;
            $email_params['HTTP_HOST'] = \common\helpers\Output::get_clickable_link(HTTP_SERVER . DIR_WS_ADMIN);
            $email_params['CUSTOMER_EMAIL'] = $admin_info->admin_email_address;
            $email_params['STORE_OWNER_EMAIL_ADDRESS'] = $STORE_OWNER_EMAIL_ADDRESS;
            list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin Password Forgotten', $email_params);
            \common\helpers\Mail::send($admin_info->admin_firstname . ' ' . $admin_info->admin_lastname, $admin_info->admin_email_address, $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, $email_params);
            echo json_encode(['message' => $message_account_password, 'messageType' => 'alert-success']);
        } else {
            echo json_encode(['message' => $message_account_password, 'messageType' => 'alert-danger']);
        }
    }
    public function action_admin_login_view()
    {
        \common\helpers\Translation::init('admin/admin-login-view');
        $this->selected_menu = ['administrator', 'adminmembers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $type = trim(\Yii::$app->request->get('type', ''));
        $admin_id = (int) \Yii::$app->request->get('admin_id');
        $admin_record = \common\models\Admin::find_one($admin_id);
        if ($admin_record instanceof \common\models\Admin) {
            $admin_record = $admin_record->to_array();
        } elseif ($admin_id < 0) {
            $admin_record = ['admin_id' => -1];
        }
        $admin_record['type'] = $type;
        $this->view->log_table = [['title' => TABLE_HEADING_EVENT, 'not_important' => 0], ['title' => TABLE_HEADING_USER, 'not_important' => 0], ['title' => TABLE_HEADING_DEVICE, 'not_important' => 0], ['title' => TABLE_HEADING_IP, 'not_important' => 0], ['title' => TABLE_HEADING_AGENT, 'not_important' => 0], ['title' => TABLE_HEADING_DATE, 'not_important' => 0]];
        return $this->render('admin-login-view', ['adminRecord' => $admin_record]);
    }
    public function action_admin_login_view_list()
    {
        \common\helpers\Translation::init('admin/admin-login-view');
        $id = \Yii::$app->request->get('id', 0);
        $type = trim(\Yii::$app->request->get('type', ''));
        $email = \common\models\Admin_Login_Log::get_admin_email($id);
        $draw = \Yii::$app->request->get('draw', 1);
        $start = \Yii::$app->request->get('start', 0);
        $length = \Yii::$app->request->get('length', 10);
        $log_query = \common\models\Admin_Login_Log::find();
        if ($id >= 0) {
            $log_query->where(['or', ['all_user_id' => $id], ['all_user' => $email]]);
        }
        if ($type == 'invalid') {
            // for more info see \common\models\AdminLoginLog::$eventList
            $log_query->and_where(['IN', 'all_event', [1, 2, 3, 4]]);
        }
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $log_query->and_where(['or', ['like', 'all_user', tep_db_input(tep_db_prepare_input($_GET['search']['value']))], ['all_event' => tep_db_input(tep_db_prepare_input($_GET['search']['value']))]]);
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 5:
                    $log_query->order_by('all_date ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])) . ', all_id ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                default:
                    $log_query->order_by('all_date DESC, all_id DESC');
                    break;
            }
        } else {
            $log_query->order_by('all_date DESC');
        }
        $numrows = $log_query->count();
        if ($length > 0) {
            $log_query->limit($length)->offset($start);
        }
        $log_query = $log_query->as_array(true)->all();
        $response_list = [];
        $event_list = \common\models\Admin_Login_Log::$event_list;
        foreach ($event_list as &$event) {
            $event_tr = 'TEXT_' . strtoupper($event);
            $event = defined($event_tr) ? constant($event_tr) : $event;
            unset($event);
        }
        foreach ($log_query as $log_record) {
            $response_list[] = [(isset($event_list[$log_record['all_event']]) ? $event_list[$log_record['all_event']] : 'Unknown') . tep_draw_hidden_field('id', $log_record['all_id'], 'class="cell_identify"'), $log_record['all_user'], trim($log_record['all_device_id']), trim($log_record['all_ip']), trim($log_record['all_agent']), $log_record['all_date']];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $numrows, 'recordsFiltered' => $numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_admin_device_view()
    {
        \common\helpers\Translation::init('admin/admin-device-view');
        $this->selected_menu = ['administrator', 'adminmembers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $admin_id = (int) \Yii::$app->request->get('admin_id');
        $admin_record = \common\models\Admin::find_one($admin_id);
        if ($admin_record instanceof \common\models\Admin) {
            $admin_record = $admin_record->to_array();
            $this->view->device_table = [['title' => TABLE_HEADING_DEVICE, 'not_important' => 0], ['title' => TABLE_HEADING_LOGIN_DATE, 'not_important' => 0], ['title' => TABLE_HEADING_LOGIN_COUNT, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_ADD, 'not_important' => 0], ['title' => TABLE_HEADING_BLOCKED, 'not_important' => 0], ['title' => '', 'not_important' => 0]];
        } else {
            $admin_record = ['admin_id' => 0];
            $this->view->device_table = [['title' => TABLE_HEADING_MEMBER, 'not_important' => 0], ['title' => TABLE_HEADING_DEVICE, 'not_important' => 0], ['title' => TABLE_HEADING_LOGIN_DATE, 'not_important' => 0], ['title' => TABLE_HEADING_LOGIN_COUNT, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_ADD, 'not_important' => 0], ['title' => TABLE_HEADING_BLOCKED, 'not_important' => 0], ['title' => '', 'not_important' => 0]];
        }
        return $this->render('admin-device-view', ['adminRecord' => $admin_record]);
    }
    public function action_admin_device_view_list()
    {
        \common\helpers\Translation::init('admin/admin-device-view');
        $id = \Yii::$app->request->get('id', 0);
        $draw = \Yii::$app->request->get('draw', 1);
        $start = \Yii::$app->request->get('start', 0);
        $length = \Yii::$app->request->get('length', 10);
        $device_query = \common\models\Admin_Device::find()->alias('ad');
        if ($id > 0) {
            $device_query->where(['ad.ad_admin_id' => $id]);
        } else {
            $device_query->left_join(\common\models\Admin::table_name() . ' a', 'a.admin_id = ad.ad_admin_id')->select(['ad.*', 'TRIM(CONCAT(TRIM(a.admin_firstname), " ", TRIM(a.admin_lastname))) AS admin_name']);
        }
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            if ($id > 0) {
                $device_query->and_where(['like', 'ad.ad_device_id', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]);
            } else {
                $device_query->and_where(['OR', ['like', 'ad.ad_device_id', tep_db_input(tep_db_prepare_input($_GET['search']['value']))], ['like', 'TRIM(CONCAT(TRIM(a.admin_firstname), " ", TRIM(a.admin_lastname)))', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]]);
            }
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 1:
                    $device_query->order_by('ad.ad_date_login ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 2:
                    $device_query->order_by('ad.ad_login_count ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 3:
                    $device_query->order_by('ad.ad_date_add ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 4:
                    $device_query->order_by('ad.ad_is_blocked ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                default:
                    $device_query->order_by('ad.ad_date_login DESC');
                    break;
            }
        } else {
            $device_query->order_by('ad.ad_date_login DESC');
        }
        $numrows = $device_query->count();
        if ($length > 0) {
            $device_query->limit($length)->offset($start);
        }
        $device_query = $device_query->as_array(true)->all();
        $response_list = [];
        foreach ($device_query as $device_record) {
            if ($id > 0) {
                $response_list[] = [$device_record['ad_device_id'], $device_record['ad_date_login'], $device_record['ad_login_count'], $device_record['ad_date_add'], $device_record['ad_is_blocked'] == 0 ? TEXT_NO : TEXT_YES, '<a class="btn btn-primary" is_blocked="' . (int) $device_record['ad_is_blocked'] . '" onclick="return doAdminDeviceBlockToggle(\'' . $device_record['ad_device_id'] . '\', this);">' . ($device_record['ad_is_blocked'] == 0 ? TEXT_BUTTON_BLOCK : TEXT_BUTTON_UNBLOCK) . '</a>'];
            } else {
                $response_list[] = [$device_record['admin_name'], $device_record['ad_device_id'], $device_record['ad_date_login'], $device_record['ad_login_count'], $device_record['ad_date_add'], $device_record['ad_is_blocked'] == 0 ? TEXT_NO : TEXT_YES, '<a class="btn btn-primary" is_blocked="' . (int) $device_record['ad_is_blocked'] . '" onclick="return doAdminDeviceBlockToggle(\'' . $device_record['ad_device_id'] . '\', this, \'' . (int) $device_record['ad_admin_id'] . '\');">' . ($device_record['ad_is_blocked'] == 0 ? TEXT_BUTTON_BLOCK : TEXT_BUTTON_UNBLOCK) . '</a>'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $numrows, 'recordsFiltered' => $numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_admin_device_block_toggle()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/admin-device-view');
        $id = (int) \Yii::$app->request->post('id', 0);
        $device = trim(\Yii::$app->request->post('device', ''));
        $return = ['status' => 'error'];
        $device_record = \common\models\Admin_Device::find_one(['ad_device_id' => $device, 'ad_admin_id' => $id]);
        if ($device_record instanceof \common\models\Admin_Device) {
            $device_record->ad_is_blocked = (int) $device_record->ad_is_blocked > 0 ? 0 : 1;
            try {
                $device_record->save();
                if ($device_record->ad_is_blocked > 0) {
                    \common\models\Admin_Login_Session::delete_all(['als_admin_id' => (int) $id, 'als_device_id' => trim($device)]);
                }
                $return = ['status' => 'ok', 'button' => $device_record->ad_is_blocked == 0 ? TEXT_BUTTON_BLOCK : TEXT_BUTTON_UNBLOCK, 'blocked' => $device_record->ad_is_blocked == 0 ? TEXT_NO : TEXT_YES, 'is_blocked' => $device_record->ad_is_blocked];
            } catch (\Exception $exc) {
            }
        }
        echo json_encode($return);
    }
    public function action_admin_session_view()
    {
        \common\helpers\Translation::init('admin/admin-session-view');
        $this->selected_menu = ['administrator', 'adminmembers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $admin_id = (int) \Yii::$app->request->get('admin_id');
        $admin_record = \common\models\Admin::find_one($admin_id);
        if (!$admin_record instanceof \common\models\Admin) {
            die('Wrong data.');
        }
        $admin_record = $admin_record->to_array();
        $this->view->session_table = [['title' => TABLE_HEADING_COMPUTER, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_EXPIRE, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_CREATE, 'not_important' => 0], ['title' => '', 'not_important' => 0]];
        return $this->render('admin-session-view', ['adminRecord' => $admin_record]);
    }
    public function action_admin_session_view_list()
    {
        \common\helpers\Translation::init('admin/admin-session-view');
        $id = \Yii::$app->request->get('id', 0);
        $draw = \Yii::$app->request->get('draw', 1);
        $start = \Yii::$app->request->get('start', 0);
        $length = \Yii::$app->request->get('length', 10);
        $session_query = \common\models\Admin_Login::find()->where(['al_admin_id' => $id]);
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $session_query->and_where(['like', 'al_computer_id', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]);
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 1:
                    $session_query->order_by('al_expire ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 2:
                    $session_query->order_by('al_create ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                default:
                    $session_query->order_by('al_expire DESC');
                    break;
            }
        } else {
            $session_query->order_by('al_expire DESC');
        }
        $numrows = $session_query->count();
        if ($length > 0) {
            $session_query->limit($length)->offset($start);
        }
        $session_query = $session_query->as_array(true)->all();
        $response_list = [];
        foreach ($session_query as $session_record) {
            $response_list[] = [$session_record['al_computer_id'], $session_record['al_expire'], $session_record['al_create'], '<a class="btn btn-primary" onclick="return doAdminSessionDelete(\'' . $session_record['al_computer_id'] . '\', this);">' . TEXT_BUTTON_DELETE . '</a>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $numrows, 'recordsFiltered' => $numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_admin_session_delete()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/admin-session-view');
        $id = (int) \Yii::$app->request->post('id', 0);
        $computer = trim(\Yii::$app->request->post('computer', ''));
        $return = ['status' => 'error'];
        $session_record = \common\models\Admin_Login::find_one(['al_computer_id' => $computer, 'al_admin_id' => $id]);
        if ($session_record instanceof \common\models\Admin_Login) {
            try {
                $session_record->delete();
                $return = ['status' => 'ok'];
            } catch (\Exception $exc) {
            }
        }
        echo json_encode($return);
    }
    public function action_admin_login_session_view()
    {
        \common\helpers\Translation::init('admin/admin-login-session-view');
        $this->selected_menu = ['administrator', 'adminmembers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $admin_id = (int) \Yii::$app->request->get('admin_id');
        $admin_record = \common\models\Admin::find_one($admin_id);
        if ($admin_record instanceof \common\models\Admin) {
            $admin_record = $admin_record->to_array();
            $this->view->login_session_table = [['title' => TABLE_HEADING_DEVICE, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_LOGIN, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_ACTIVITY, 'not_important' => 0], ['title' => '', 'not_important' => 0]];
        } else {
            $admin_record = ['admin_id' => 0];
            $this->view->login_session_table = [['title' => TABLE_HEADING_MEMBER, 'not_important' => 0], ['title' => TABLE_HEADING_DEVICE, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_LOGIN, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_ACTIVITY, 'not_important' => 0], ['title' => '', 'not_important' => 0]];
        }
        return $this->render('admin-login-session-view', ['adminRecord' => $admin_record]);
    }
    public function action_admin_login_session_view_list()
    {
        \common\helpers\Translation::init('admin/admin-login-session-view');
        $id = \Yii::$app->request->get('id', 0);
        $draw = \Yii::$app->request->get('draw', 1);
        $start = \Yii::$app->request->get('start', 0);
        $length = \Yii::$app->request->get('length', 10);
        $login_session_query = \common\models\Admin_Login_Session::find()->alias('als');
        if ($id > 0) {
            $login_session_query->where(['als.als_admin_id' => $id]);
        } else {
            $login_session_query->left_join(\common\models\Admin::table_name() . ' a', 'a.admin_id = als.als_admin_id')->select(['als.*', 'TRIM(CONCAT(TRIM(a.admin_firstname), " ", TRIM(a.admin_lastname))) AS admin_name']);
        }
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            if ($id > 0) {
                $login_session_query->and_where(['like', 'als.als_device_id', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]);
            } else {
                $login_session_query->and_where(['OR', ['like', 'als.als_device_id', tep_db_input(tep_db_prepare_input($_GET['search']['value']))], ['like', 'TRIM(CONCAT(TRIM(a.admin_firstname), " ", TRIM(a.admin_lastname)))', tep_db_input(tep_db_prepare_input($_GET['search']['value']))]]);
            }
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 1:
                    $login_session_query->order_by('als.als_date_login ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 2:
                    $login_session_query->order_by('als.als_date_activity ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                default:
                    $login_session_query->order_by('als.als_date_activity DESC');
                    break;
            }
        } else {
            $login_session_query->order_by('als.als_date_activity DESC');
        }
        $numrows = $login_session_query->count();
        if ($length > 0) {
            $login_session_query->limit($length)->offset($start);
        }
        $login_session_query = $login_session_query->as_array(true)->all();
        $response_list = [];
        foreach ($login_session_query as $login_session_record) {
            if ($id > 0) {
                $response_list[] = [$login_session_record['als_device_id'], $login_session_record['als_date_login'], $login_session_record['als_date_activity'], '<a class="btn btn-primary" onclick="return doAdminLoginSessionDelete(\'' . $login_session_record['als_device_id'] . '\', this);">' . TEXT_BUTTON_DELETE . '</a>'];
            } else {
                $response_list[] = [$login_session_record['admin_name'], $login_session_record['als_device_id'], $login_session_record['als_date_login'], $login_session_record['als_date_activity'], '<a class="btn btn-primary" onclick="return doAdminLoginSessionDelete(\'' . $login_session_record['als_device_id'] . '\', this, \'' . (int) $login_session_record['als_admin_id'] . '\');">' . TEXT_BUTTON_DELETE . '</a>'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $numrows, 'recordsFiltered' => $numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_admin_login_session_delete()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/admin-login-session-view');
        $id = (int) \Yii::$app->request->post('id', 0);
        $device = trim(\Yii::$app->request->post('device', ''));
        $return = ['status' => 'error'];
        $login_session_record = \common\models\Admin_Login_Session::find_one(['als_device_id' => $device, 'als_admin_id' => $id]);
        if ($login_session_record instanceof \common\models\Admin_Login_Session) {
            try {
                $login_session_record->delete();
                $return = ['status' => 'ok'];
            } catch (\Exception $exc) {
            }
        }
        echo json_encode($return);
    }
}