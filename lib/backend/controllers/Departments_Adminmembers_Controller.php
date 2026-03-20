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

use common\helpers\Affiliate;
use common\models\Pdo_Connector;
use Yii;
class Departments_Adminmembers_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DEPARTMENTS', 'BOX_DEPARTMENTS_MEMBERS'];
    public function action_index()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->selected_menu = ['departments', 'departments-adminmembers'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('departments-adminmembers/'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="#" class="create_item" onclick="return editAdmin(0)">' . IMAGE_INSERT . '</a>';
        $this->view->admin_table = [['title' => TABLE_HEADING_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_EMAIL, 'not_important' => 0], ['title' => TABLE_HEADING_GROUPS, 'not_important' => 0], ['title' => TABLE_HEADING_LOGNUM, 'not_important' => 1]];
        $this->view->filters = new \Object_Info(['row' => 0]);
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $selected_department_id = (int) Yii::$app->request->get('department_id');
        $departments = [];
        foreach (\common\classes\department::get_list() as $department_variant) {
            if ($selected_department_id == 0) {
                $selected_department_id = $department_variant['departments_id'];
            }
            $department_variant['link'] = Yii::$app->url_manager->create_url(['departments-adminmembers/', 'department_id' => $department_variant['departments_id']]);
            $departments[] = $department_variant;
        }
        return $this->render('index', ['departments' => $departments, 'selected_department_id' => $selected_department_id]);
    }
    public function action_memberlist()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $draw = Yii::$app->request->get('draw');
        $start = Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length');
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
            $search = '';
            if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
                $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
                $search_condition = " where (a.admin_firstname like '%" . $keywords . "%' or a.admin_lastname like '%" . $keywords . "%' or a.admin_email_address like '%" . $keywords . "%')";
            } else {
                $search_condition = ' where 1 ';
            }
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
            $db_admin_query_raw = ' from ' . TABLE_ADMIN . ' a
                                left join ' . TABLE_ACCESS_LEVELS . " al ON a.access_levels_id = al.access_levels_id\n                                {$search_condition}\n                                order by {$order_by}";
            //-------
            $current_page_number = $start / $length + 1;
            $offset = $length * ($current_page_number - 1);
            $sql_limit = ' limit ' . max($offset, 0) . ', ' . $length;
            Pdo_Connector::query('select count(*) as total' . $db_admin_query_raw);
            $records_count = Pdo_Connector::fetch();
            //$recordsCount = tep_db_fetch_array(tep_db_query("select count(*) as total" . $db_admin_query_raw));
            $records_total = $records_count['total'];
            //$db_admin_query = tep_db_query("select a.*, al.access_levels_name" . $db_admin_query_raw . $sqlLimit);
            Pdo_Connector::query('select a.*, al.access_levels_name' . $db_admin_query_raw . $sql_limit);
            //--------
            //$db_admin_split = new \splitPageResults($current_page_number, $length, $db_admin_query_raw, $recordsTotal, 'a.admin_id');
            //$db_admin_query = tep_db_query($db_admin_query_raw);
            while ($admin = Pdo_Connector::fetch()) {
                //while ($admin = tep_db_fetch_array($db_admin_query)) {
                $disabled_admin = '';
                if ($admin['login_failture'] > 2) {
                    $disabled_admin = 'dis_module';
                }
                $response_list[] = ['<div class="' . $disabled_admin . '">' . $admin['admin_firstname'] . ' ' . $admin['admin_lastname'] . '<input class="cell_identify" type="hidden" value="' . $admin['admin_id'] . '">' . '</div>', '<div class="' . $disabled_admin . '">' . $admin['admin_email_address'] . '</div>', '<div class="' . $disabled_admin . '">' . $admin['access_levels_name'] . (empty($admin['admin_persmissions']) ? '' : ' (' . TEXT_MANUALLY_UPDATED . ')') . '</div>', '<div class="' . $disabled_admin . '">' . $admin['admin_lognum'] . '</div>'];
                $records_filtered++;
            }
        }
        $_response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        echo json_encode($_response, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    public function action_adminmembersactions()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->layout = false;
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        $check_dev_admin = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_ADMIN . " WHERE admin_id='" . (int) $_SESSION['login_id'] . "' AND admin_email_address LIKE '%@holbi.co.uk'"));
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('
          select distinct(a.admin_id), a.admin_groups_id, a.admin_firstname, a.admin_lastname,
          a.admin_email_address, a.admin_password, a.admin_created, a.admin_modified, a.admin_logdate,
          a.admin_lognum, a.login_failture, a.login_failture_date, a.login_failture_ip, a.individual_id,
          al.access_levels_name
          from ' . TABLE_ADMIN . ' a
          left join ' . TABLE_ACCESS_LEVELS . " al ON a.access_levels_id = al.access_levels_id\n          where a.admin_id = '" . (int) $admin_id . "'");
        $admins = Pdo_Connector::fetch();
        if (!is_array($admins)) {
            die('Wrong data.');
        }
        $m_info = new \Object_Info($admins);
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or"><div>' . TEXT_INFO_FULLNAME . '</div><div>' . $m_info->admin_firstname . ' ' . $m_info->admin_lastname . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_EMAIL . '</div><div>' . $m_info->admin_email_address . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_GROUP . '</div><div>' . $m_info->access_levels_name . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_CREATED . '</div><div>' . \common\helpers\Date::date_short($m_info->admin_created) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_MODIFIED . '</div><div>' . \common\helpers\Date::date_short($m_info->admin_modified) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_LOGDATE . '</div><div>' . \common\helpers\Date::date_short($m_info->admin_logdate) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_LOGNUM . '</div><div>' . $m_info->admin_lognum . '</div></div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<button class="btn btn-edit btn-no-margin" onclick="editAdmin(' . $m_info->admin_id . ')">' . IMAGE_EDIT . '</button>' . (!Affiliate::is_logged() ? '<button onclick="confirmDeleteAdmin(' . $m_info->admin_id . ')" class="btn btn-delete">' . IMAGE_DELETE . '</button>' : '');
        // . '<a class="hidden btn" href="' . tep_href_link(FILENAME_ORDERS, 'mID=' . $mInfo->admin_id) . '">' . IMAGE_ORDERS . '</a><a class="hidden btn btn-primary" href="' . tep_href_link(FILENAME_MAIL, 'customer=' . $mInfo->customers_email_address) . '">' . IMAGE_EMAIL . '</a>';
        echo '<a class="btn btn-primary btn-process-order" href="' . Yii::$app->url_manager->create_url(['departments-adminmembers/override-permissions', 'admin_id' => $m_info->admin_id, 'department_id' => $department_id]) . '">' . TEXT_OVERRIDE_PERMISSIONS . '</a>';
        if ($m_info->login_failture > 2) {
            if (\common\helpers\Acl::rule(['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_MEMBERS', 'TEXT_ENABLE_USER'])) {
                echo '<button class="btn btn-primary btn-process-order" onclick="enableUser(' . $m_info->admin_id . ')">' . TEXT_ENABLE_USER . '</button>';
            }
            if (!empty($m_info->login_failture_date)) {
                echo '<div class="row_or"><div>DATE:</div><div>' . \common\helpers\Date::date_short($m_info->login_failture_date) . '</div></div>';
            }
            if (!empty($m_info->login_failture_ip)) {
                echo '<div class="row_or"><div>IP:</div><div>' . $m_info->login_failture_ip . '</div></div>';
            }
        } else if (\common\helpers\Acl::rule(['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_MEMBERS', 'TEXT_DISABLE_USER'])) {
            echo '<button class="btn btn-primary btn-process-order" onclick="disableUser(' . $m_info->admin_id . ')">' . TEXT_DISABLE_USER . '</button>';
        }
        echo '</div>';
        if ($check_dev_admin['c'] > 0) {
            \common\helpers\Translation::init('admin/customers');
            echo '<div class="btn-toolbar btn-toolbar-order btn-toolbar-pass"><span class="btn btn-pass-cus">' . T_UPDATE_PASS . '</span>
                <script>
                $(document).ready(function() {
                $("a.popup").popUp();
                $(".btn-pass-cus").on("click", function(){
                    alertMessage("<div class=\"popup-heading popup-heading-pass\">' . TEXT_UPDATE_PASSWORD_FOR . ' ' . $m_info->admin_firstname . '&nbsp;' . $m_info->admin_lastname . '</div><div class=\"popup-content popup-content-pass\"><form name=\"passw_form\" action=\"' . tep_href_link('adminmembers', \common\helpers\Output::get_all_get_params(['admin_id', 'action']) . 'admin_id=' . $m_info->admin_id . '&action=password') . '\" method=\"post\" onsubmit=\"return check_passw_form(' . (int) ENTRY_PASSWORD_MIN_LENGTH . ');\"><label>' . T_NEW_PASS . ':</label><input type=\"hidden\" name=\"department_id\" value=\"' . $department_id . '\"><input type=\"hidden\" name=\"admin_id\" value=\"' . $m_info->admin_id . '\"><input type=\"password\" name=\"change_pass\" class=\"form-control\" size=\"16\"><div class=\"btn-bar\" style=\"padding-bottom: 0;\"><div class=\"btn-left\"><span class=\"btn btn-cancel\">' . IMAGE_CANCEL . '</span></div><div class=\"btn-right\"><input type=\"submit\" value=\"' . IMAGE_UPDATE . '\" class=\"btn btn-primary\"></div></div></form></div>");
                });
                });
                </script>
                </div>';
        }
    }
    public function action_adminedit()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->layout = false;
        $error = $entry_firstname_error = $entry_lastname_error = $entry_admin_email_address_error = false;
        $entry_admin_groups_name_error = false;
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select * from ' . TABLE_ADMIN . " where admin_id = {$admin_id}; ");
        if ($admin = Pdo_Connector::fetch()) {
            $m_info = new \Object_Info($admin);
        } else {
            $m_info = new \common\models\Admin();
            $m_info->load_default_values();
        }
        $access_array = [];
        Pdo_Connector::query('select * from ' . TABLE_ACCESS_LEVELS . ' order by access_levels_id ');
        while ($access = Pdo_Connector::fetch()) {
            $access_array[] = ['id' => $access['access_levels_id'], 'text' => $access['access_levels_name']];
        }
        /*$access_array[] = array(
              array('id' => 0, 'text' => 'none')
          );*/
        ?>

        <?php 
        echo tep_draw_form('admin', 'departments-adminmembers', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="admin_edit" onSubmit="return check_form();"') . tep_draw_hidden_field('default_address_id', $m_info->admin_email_address);
        echo '<div class="or_box_head">' . CATEGORY_PERSONAL . '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . ENTRY_FIRST_NAME . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('admin_firstname', $m_info->admin_firstname, 'maxlength="32" class="form-control"', true) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . ENTRY_LAST_NAME . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('admin_lastname', $m_info->admin_lastname, 'maxlength="32" class="form-control"', false) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . ENTRY_EMAIL_ADDRESS . '</div>';
        echo '<div class="main_value">' . tep_draw_input_field('admin_email_address', $m_info->admin_email_address, 'maxlength="100" class="form-control"', true) . '</div>';
        echo '</div>';
        echo '<div class="main_row">';
        echo '<div class="main_title">' . TEXT_INFO_GROUP . '</div>';
        echo '<div class="main_value">' . tep_draw_pull_down_menu('access_levels_name', $access_array, is_object($m_info) ? $m_info->access_levels_id : 0, 'class="form-control"', false) . '</div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        if ($admin_id > 0) {
            if (!Affiliate::is_logged()) {
                echo '<input type="submit" class="btn btn-no-margin" value="' . IMAGE_UPDATE . '" >';
            }
            echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
            ?>
        <?php 
        } else {
            ?>
            <?php 
            echo '<input type="submit" class="btn btn-no-margin" value="' . IMAGE_INSERT . '" >';
            echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
            ?>
            <?php 
        }
        echo '</div>';
        ?>
        <?php 
        echo tep_draw_hidden_field('admin_id', $m_info->admin_id);
        echo tep_draw_hidden_field('department_id', $department_id);
        ?>
        </form>
        <?php 
    }
    public function action_confirmadmindelete()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        \common\helpers\Translation::init('admin/faqdesk');
        $this->layout = false;
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select * from ' . TABLE_ADMIN . " where admin_id = {$admin_id}; ");
        if ($admin = Pdo_Connector::fetch()) {
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
        echo tep_draw_hidden_field('department_id', $department_id);
        ?>
        </div>
        </form>
            <?php 
    }
    public function action_admindelete()
    {
        $this->layout = false;
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('delete from ' . TABLE_ADMIN . " where admin_id = '" . (int) $admin_id . "'");
    }
    private function randomize()
    {
        $salt = 'abchefghjkmnpqrstuvwxyz0123456789';
        srand((float) microtime() * 1000000);
        $i = 0;
        $pass = '';
        while ($i <= 7) {
            $num = rand() % 33;
            $tmp = substr($salt, $num, 1);
            $pass = $pass . $tmp;
            $i++;
        }
        return $pass;
    }
    public function action_adminsubmit()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->layout = false;
        $error = false;
        $message = '';
        $message_type = 'success';
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        $admin_firstname = tep_db_prepare_input($_POST['admin_firstname']);
        $admin_lastname = tep_db_prepare_input($_POST['admin_lastname']);
        $admin_email_address = tep_db_prepare_input($_POST['admin_email_address']);
        $admin_group_level = tep_db_prepare_input($_POST['access_levels_name']);
        $sql_data_array = ['admin_id' => $admin_id, 'admin_firstname' => $admin_firstname, 'admin_lastname' => $admin_lastname, 'admin_email_address' => $admin_email_address, 'access_levels_id' => $admin_group_level];
        if (strlen($admin_firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
            $error = true;
            $message .= 'Firstname: ' . sprintf(ENTRY_FIRST_NAME_ERROR, ENTRY_FIRST_NAME_MIN_LENGTH) . '<br/>';
        }
        if (trim($admin_email_address) == '') {
            $error = true;
            $message .= ENTRY_EMAIL_ADDRESS_CHECK_ERROR . '<br/>';
        }
        $stored_email[] = 'NONE';
        Pdo_Connector::query('select admin_email_address from ' . TABLE_ADMIN . ' where admin_id <> ' . $admin_id . '');
        while ($check_email = Pdo_Connector::fetch()) {
            $stored_email[] = $check_email['admin_email_address'];
        }
        if (in_array($admin_email_address, $stored_email)) {
            $error = true;
            $message = 'Email already in use';
        }
        if ($error === false) {
            if ((int) $admin_id > 0) {
                Pdo_Connector::perform(TABLE_ADMIN, $sql_data_array, 'update', "admin_id = '" . (int) $admin_id . "'");
                Pdo_Connector::query('update ' . TABLE_ADMIN . " set admin_modified = now() where admin_id = '" . (int) $admin_id . "'");
                $message = SUCCESS_ADMIN_UPDATED;
            } else {
                $make_password = $this->randomize();
                $sql_data_array['admin_password'] = \common\helpers\Password::encrypt_password($make_password, 'backend');
                Pdo_Connector::perform(TABLE_ADMIN, $sql_data_array);
                $admin_id = Pdo_Connector::last_insert_id();
                $_GET['mID'] = $admin_id;
                // FIXME: Why do we need this?
                Pdo_Connector::query('update ' . TABLE_ADMIN . " set admin_created = now(), admin_modified = now() where admin_id = '" . (int) $admin_id . "'");
                $message = SUCCESS_ADMIN_CREATED;
                if ($departments['departments_enable_ssl'] == 1) {
                    $admin_url = 'https://' . $departments['departments_https_server'] . $departments['departments_https_catalog'] . 'admin/';
                } else {
                    $admin_url = 'http://' . $departments['departments_http_server'] . $departments['departments_http_catalog'] . 'admin/';
                }
                $email_params = [];
                $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link($admin_url);
                $email_params['CUSTOMER_FIRSTNAME'] = $sql_data_array['admin_firstname'];
                $email_params['CUSTOMER_LASTNAME'] = $sql_data_array['admin_lastname'];
                $email_params['CUSTOMER_EMAIL'] = $sql_data_array['admin_email_address'];
                $email_params['STORE_OWNER'] = STORE_OWNER;
                $email_params['NEW_PASSWORD'] = $make_password;
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin update', $email_params);
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
        }
        if ($error === true) {
            $message_type = 'warning';
            if ($message == '') {
                $message = WARN_UNKNOWN_ERROR;
            }
        }
        ?>
        <div class="alert alert-<?php 
        echo $message_type;
        ?> fade in">
            <i data-dismiss="alert" class="icon-remove close"></i>
        <?php 
        echo $message;
        ?>
        </div>
        <?php 
        $check_admin_id = Yii::$app->request->post('admin_id');
        if ($check_admin_id > 0) {
            $this->action_adminmembersactions();
        }
    }
    public function action_override_permissions()
    {
        \common\helpers\Translation::init('admin/adminmembers');
        $this->selected_menu = ['departments', 'departments-adminmembers'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('departments-adminmembers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $admin_id = (int) Yii::$app->request->get('admin_id');
        $department_id = (int) Yii::$app->request->get('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $admin = Pdo_Connector::fetch();
        if (!is_array($admin)) {
            die('Wrong data.');
        }
        $admin_persmissions = explode(',', $admin['admin_persmissions']);
        Pdo_Connector::query('select access_levels_persmissions from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . (int) $admin['access_levels_id'] . "'");
        $access = Pdo_Connector::fetch();
        $selected_ids = explode(',', $access['access_levels_persmissions']);
        $acl_tree = \common\helpers\Acl::build_override_tree_pdo($selected_ids, $admin_persmissions);
        return $this->render('override-permissions', ['aclTree' => $acl_tree, 'admin_id' => $admin_id, 'department_id' => $department_id]);
    }
    public function action_recalc_acl()
    {
        $this->layout = false;
        $admin_id = (int) Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        $persmissions = Yii::$app->request->post('persmissions');
        $query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $admin = tep_db_fetch_array($query);
        if (!is_array($admin)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select access_levels_persmissions from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . (int) $admin['access_levels_id'] . "'");
        $access = Pdo_Connector::fetch($check_access);
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
        $acl_tree = \common\helpers\Acl::build_override_tree_pdo($selected_ids, $admin_persmissions);
        return $this->render('recalc-acl', ['aclTree' => $acl_tree]);
    }
    public function action_submit_permissions()
    {
        $admin_id = (int) Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $admin = Pdo_Connector::fetch();
        if (!is_array($admin)) {
            die('Wrong data.');
        }
        Pdo_Connector::query('select access_levels_persmissions from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . (int) $admin['access_levels_id'] . "'");
        $access = Pdo_Connector::fetch();
        $selected_ids = explode(',', $access['access_levels_persmissions']);
        $persmissions = Yii::$app->request->post('persmissions');
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
        Pdo_Connector::perform(TABLE_ADMIN, $sql_data_array, 'update', "admin_id = '" . $admin_id . "'");
        echo '<script> window.location.replace("' . Yii::$app->url_manager->create_url(['departments-adminmembers/override-permissions', 'admin_id' => $admin_id, 'department_id' => $department_id]) . '");</script>';
    }
    public function action_enable_admin()
    {
        $this->layout = false;
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('update ' . TABLE_ADMIN . " set login_failture = 0 where admin_id = '" . (int) $admin_id . "'");
    }
    public function action_disable_admin()
    {
        $this->layout = false;
        $admin_id = Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('update ' . TABLE_ADMIN . " set login_failture = 3 where admin_id = '" . (int) $admin_id . "'");
    }
    public function action_generatepassword()
    {
        $this->layout = false;
        $admin_id = \Yii::$app->request->post('admin_id');
        $department_id = (int) Yii::$app->request->post('department_id');
        $change_pass = \Yii::$app->request->post('change_pass');
        $departments = tep_db_fetch_array(tep_db_query('select departments_db_server_host, departments_db_server_username, departments_db_server_password, departments_db_database from ' . TABLE_DEPARTMENTS . " where departments_id = '" . (int) $department_id . "'"));
        if (!is_array($departments)) {
            die('Wrong data.');
        }
        Pdo_Connector::init(['host' => $departments['departments_db_server_host'], 'user' => $departments['departments_db_server_username'], 'password' => $departments['departments_db_server_password'], 'dbname' => $departments['departments_db_database']]);
        Pdo_Connector::query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
        $data = Pdo_Connector::fetch();
        if (!empty($change_pass) && is_array($data)) {
            $new_password = \common\helpers\Password::encrypt_password($change_pass, 'backend');
            Pdo_Connector::query('update ' . TABLE_ADMIN . " set admin_password = '" . $new_password . "' where admin_id = '" . (int) $admin_id . "'");
            $email_params = [];
            $email_params['STORE_NAME'] = STORE_NAME;
            $email_params['NEW_PASSWORD'] = $change_pass;
            $email_params['CUSTOMER_FIRSTNAME'] = $data['admin_firstname'];
            $email_params['HTTP_HOST'] = \common\helpers\Output::get_clickable_link(HTTP_SERVER . DIR_WS_ADMIN);
            $email_params['CUSTOMER_EMAIL'] = $data['admin_email_address'];
            list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin Password Forgotten', $email_params);
            \common\helpers\Mail::send($data['admin_firstname'] . ' ' . $data['admin_lastname'], $data['admin_email_address'], $email_subject, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_params);
        }
    }
}