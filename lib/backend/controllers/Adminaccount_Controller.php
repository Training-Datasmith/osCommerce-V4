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

use backend\models\Admin;
use common\helpers\Translation;
use common\models\Currencies;
use common\models\Customers;
use common\models\repositories\Admin_Repository;
use yii\helpers\Array_Helper;
class Adminaccount_Controller extends Sceleton
{
    private $admin_repository;
    public function __construct($id, $module, Admin_Repository $admin_repository, array $config = [])
    {
        Translation::init('admin/admin_account');
        $this->admin_repository = $admin_repository;
        parent::__construct($id, $module, $config);
    }
    public function action_index()
    {
        $this->selected_menu = ['administrator', 'adminaccount'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminaccount/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $expired_flag = false;
        if (defined('ADMIN_PASSWORD_EXPIRE') && ADMIN_PASSWORD_EXPIRE != 'Never') {
            $admin_id = tep_session_var('login_id');
            $my_account = $this->get_admin_obj($admin_id);
            $date_timestamp2 = false;
            switch (ADMIN_PASSWORD_EXPIRE) {
                case '1 Week':
                    $date_timestamp2 = strtotime('-1 week');
                    break;
                case '2 Weeks':
                    $date_timestamp2 = strtotime('-2 weeks');
                    break;
                case '1 Month':
                    $date_timestamp2 = strtotime('-1 month');
                    break;
                case '3 Months':
                    $date_timestamp2 = strtotime('-3 months');
                    break;
                case '6 Months':
                    $date_timestamp2 = strtotime('-6 months');
                    break;
                default:
                    break;
            }
            if ($date_timestamp2 !== false) {
                $date_timestamp1 = strtotime($my_account['password_last_update']);
                if ($date_timestamp1 === false) {
                    $expired_flag = true;
                } elseif ($date_timestamp1 < $date_timestamp2) {
                    $expired_flag = true;
                }
            }
        }
        $admin = new Admin();
        $admin_info = $admin->get_additional_info();
        return $this->render('index', ['expiredFlag' => $expired_flag, 'adminInfo' => $admin_info, 'hidden_admin_language' => isset($admin_info['hidden_admin_language']) && is_array($admin_info['hidden_admin_language']) ? $admin_info['hidden_admin_language'] : [], 'global_hidden_admin_language' => \common\helpers\Language::get_admin_hidden_languages()]);
    }
    public function action_hide_language()
    {
        $hide = \Yii::$app->request->post('hide', false);
        $id = (int) \Yii::$app->request->post('id', 0);
        $ret = ['error' => 1];
        if ($hide !== false && \common\helpers\Language::get_default_language_id() != $id) {
            $admin = new Admin();
            $admin_info = $admin->get_additional_info();
            $hidden_admin_language = isset($admin_info['hidden_admin_language']) && is_array($admin_info['hidden_admin_language']) ? $admin_info['hidden_admin_language'] : [];
            if ($hide) {
                $hidden_admin_language[] = $id;
                $hidden_admin_language = array_unique($hidden_admin_language);
            } else {
                $hidden_admin_language = array_diff($hidden_admin_language, [$id]);
            }
            $admin_info['hidden_admin_language'] = $hidden_admin_language;
            $admin->save_additional_info($admin_info);
            $hidden_admin_language = \common\helpers\Language::get_admin_hidden_languages();
            $ret = ['ok' => 1, 'hidden_admin_language' => array_values($hidden_admin_language)];
        }
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_adminaccountactions()
    {
        $this->layout = false;
        $admin_id = (int) \Yii::$app->request->post('admin_id');
        if ($admin_id == 0) {
            $admin_id = tep_session_var('login_id');
        }
        $my_account = $this->get_admin_obj($admin_id);
        $customers_query = tep_db_query('select customers_email_address, customers_firstname, customers_lastname from ' . TABLE_CUSTOMERS . ' where customers_id=' . $my_account['customers_id']);
        $customers = tep_db_fetch_array($customers_query);
        if ($customers) {
            $my_account['admin_customer'] = $customers['customers_firstname'] . ' ' . $customers['customers_lastname'] . ' <' . $customers['customers_email_address'] . '>';
        }
        if (!is_array($my_account)) {
            die("Wrong admin id: {$admin_id}");
        }
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image_svg'];
        }
        return $this->render_ajax('accountactions', ['myAccount' => $my_account, 'avatar' => @get_image_size(DIR_FS_CATALOG_IMAGES . $my_account['avatar']), 'image' => tep_image(DIR_WS_CATALOG_IMAGES . $my_account['avatar'], $my_account['admin_firstname'] . ' ' . $my_account['admin_lastname'])]);
    }
    public function get_admin_obj($admin_id)
    {
        return $this->admin_repository->get_admin_data($admin_id);
    }
    public function action_saveaccount()
    {
        $this->layout = false;
        $error = false;
        $message = '';
        $message_type = 'success';
        $html = '';
        $hidden_password = TEXT_INFO_PASSWORD_HIDDEN;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        if (is_array($my_account)) {
            $m_info = new \Object_Info($my_account);
        }
        $admin_id = (int) \Yii::$app->request->post('admin_id');
        if ($login_id !== $admin_id) {
            $error = true;
            $message = TEXT_MESS_WRONG_DATA;
        }
        $popupname = \Yii::$app->request->post('popupname');
        $admin_email_address = '';
        $stored_email = [];
        if ($popupname == 'name') {
            $admin_firstname = \Yii::$app->request->post('admin_firstname');
            $admin_lastname = \Yii::$app->request->post('admin_lastname');
        } elseif ($popupname == 'email') {
            $admin_email_address = \Yii::$app->request->post('admin_email_address');
            $stored_email[] = 'NONE';
            $check_email_query = tep_db_query('select admin_email_address from ' . TABLE_ADMIN . ' where admin_id <> ' . $admin_id . '');
            while ($check_email = tep_db_fetch_array($check_email_query)) {
                $stored_email[] = $check_email['admin_email_address'];
            }
            if (in_array($admin_email_address, $stored_email)) {
                $error = true;
                $message = TEXT_MESS_EMAIL_EXISTS;
            }
        } elseif ($popupname == 'password') {
            $password_confirmation = \Yii::$app->request->post('password_confirmation');
            $admin_password = \Yii::$app->request->post('admin_password');
            $admin_password_confirm = \Yii::$app->request->post('admin_password_confirm');
            $check_pass_query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
            $check_pass = tep_db_fetch_array($check_pass_query);
            if (!\common\helpers\Password::validate_password($password_confirmation, $check_pass['admin_password'], 'backend')) {
                $message = TEXT_MESS_PASSWORD_WRONG;
                $error = true;
            } elseif ($admin_password != $admin_password_confirm) {
                $message = TEXT_MESS_PASSWORD_WRONG;
                $error = true;
            } elseif ($admin_password == $password_confirmation) {
                $message = TEXT_MESS_PASSWORD_LIKE_CURRENT;
                $error = true;
            } else {
                if (defined('ADMIN_PASSWORD_BAN_EASY') && ADMIN_PASSWORD_BAN_EASY == 'True') {
                    $dont_accept_list = [$check_pass['admin_username'], $check_pass['admin_firstname'], $check_pass['admin_lastname'], $check_pass['admin_phone_number']];
                    foreach ($dont_accept_list as $dont_accept_item) {
                        if (!empty($dont_accept_item)) {
                            if (preg_match('/^' . preg_quote($dont_accept_item) . '/i', $admin_password)) {
                                $message = TEXT_MESS_PASSWORD_START_AT . ' ' . $dont_accept_item;
                                $error = true;
                            }
                            if (preg_match('/' . preg_quote($dont_accept_item) . '$/i', $admin_password)) {
                                $message = TEXT_MESS_PASSWORD_END_AT . ' ' . $dont_accept_item;
                                $error = true;
                            }
                        }
                    }
                    if ($error === false) {
                        $easy_pass_check = \common\models\Easy_Passwords::find()->where(['password' => $admin_password])->one();
                        if ($easy_pass_check instanceof \common\models\Easy_Passwords) {
                            $message = TEXT_MESS_PASSWORD_EASY;
                            $error = true;
                        }
                        unset($easy_pass_check);
                    }
                }
                if (defined('ADMIN_PASSWORD_USE_SAME') && ADMIN_PASSWORD_USE_SAME == 'True') {
                    if (\common\models\Admin_Old_Passwords::is_old($admin_id, tep_db_prepare_input($admin_password)) == true) {
                        $message = TEXT_MESS_PASSWORD_OLD;
                        $error = true;
                    }
                }
            }
        } elseif ($popupname == 'group') {
            $admin_groups_id = \Yii::$app->request->post('admin_groups_id');
        } elseif ($popupname == 'avatar') {
            $file_name = Uploads::move($_POST['avatar']);
            $avatar_img = $file_name ? $file_name : '';
        } elseif ($popupname == 'admin_username') {
            $admin_username = \Yii::$app->request->post('admin_username');
        } elseif ($popupname == 'customers_id') {
            $customers_id = \Yii::$app->request->post('customers_id');
        } elseif ($popupname == 'pin') {
            $pin = \Yii::$app->request->post('pin');
        } elseif ($popupname == 'pos_platform_id') {
            $pos_platform_id = \Yii::$app->request->post('pos_platform_id', 0);
        } elseif ($popupname == 'pos_currency_id') {
            $pos_currency_id = \Yii::$app->request->post('pos_currency_id', 0);
        }
        if ($error === false) {
            if ($popupname == 'name') {
                $sql_data_array['admin_firstname'] = tep_db_prepare_input($admin_firstname);
                $sql_data_array['admin_lastname'] = tep_db_prepare_input($admin_lastname);
            } elseif ($popupname == 'email') {
                $sql_data_array['admin_email_address'] = tep_db_prepare_input($admin_email_address);
            } elseif ($popupname == 'password') {
                if (defined('ADMIN_PASSWORD_USE_SAME') && ADMIN_PASSWORD_USE_SAME == 'True') {
                    \common\models\Admin_Old_Passwords::add_old($admin_id, tep_db_prepare_input($admin_password));
                }
                $sql_data_array['admin_password'] = \common\helpers\Password::encrypt_password(tep_db_prepare_input($admin_password), 'backend');
                $sql_data_array['password_last_update'] = 'now()';
            } elseif ($popupname == 'group') {
                $sql_data_array['admin_groups_id'] = tep_db_prepare_input($admin_groups_id);
            } elseif ($popupname == 'avatar') {
                $sql_data_array['avatar'] = tep_db_prepare_input($avatar_img);
            } elseif ($popupname == 'admin_username') {
                $sql_data_array['admin_username'] = tep_db_prepare_input($admin_username);
            } elseif ($popupname == 'customers_id') {
                $sql_data_array['customers_id'] = tep_db_prepare_input($customers_id);
            } elseif ($popupname == 'pin') {
                $sql_data_array['pin'] = tep_db_prepare_input($pin);
            } elseif ($popupname == 'pos_platform_id') {
                $sql_data_array['pos_platform_id'] = tep_db_prepare_input($pos_platform_id);
            } elseif ($popupname == 'pos_currency_id') {
                $sql_data_array['pos_currency_id'] = tep_db_prepare_input($pos_currency_id);
            }
            $sql_data_array['admin_modified'] = 'now()';
            tep_db_perform(TABLE_ADMIN, $sql_data_array, 'update', 'admin_id = \'' . $admin_id . '\'');
            $data_query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . $admin_id . "'");
            $data = tep_db_fetch_array($data_query);
            $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
            $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
            $STORE_NAME = $platform_config->const_value('STORE_NAME');
            $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
            $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
            if ($popupname == 'password') {
                $email_params = [];
                $email_params['STORE_NAME'] = $STORE_NAME;
                $email_params['NEW_PASSWORD'] = $admin_password;
                $email_params['CUSTOMER_FIRSTNAME'] = $data['admin_firstname'];
                $email_params['HTTP_HOST'] = \common\helpers\Output::get_clickable_link(HTTP_SERVER . DIR_WS_ADMIN);
                $email_params['CUSTOMER_EMAIL'] = $data['admin_email_address'];
                $email_params['STORE_OWNER_EMAIL_ADDRESS'] = $STORE_OWNER_EMAIL_ADDRESS;
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin Password Forgotten', $email_params);
                \common\helpers\Mail::send($data['admin_firstname'] . ' ' . $data['admin_lastname'], $data['admin_email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, $email_params);
            } else {
                $email_params = [];
                $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(HTTP_SERVER . DIR_WS_ADMIN);
                $email_params['CUSTOMER_FIRSTNAME'] = $data['admin_firstname'];
                $email_params['CUSTOMER_LASTNAME'] = $data['admin_lastname'];
                $email_params['CUSTOMER_EMAIL'] = $data['admin_email_address'];
                $email_params['STORE_OWNER'] = STORE_OWNER;
                $email_params['NEW_PASSWORD'] = $hidden_password;
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin update', $email_params);
                \common\helpers\Mail::send(
                    $data['admin_firstname'] . ' ' . $data['admin_lastname'],
                    $data['admin_email_address'],
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
            $message = TEXT_MESS_DATA_CHANGE_SUCCESS;
        }
        if ($error === true) {
            $message_type = 'warning';
        }
        if ($message != '') {
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
            //echo $html
            ?>
        <?php 
        }
    }
    public function action_nameform()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $html = '<div id="accountpopup">' . tep_draw_form('save_account_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_account_form" onSubmit="return saveAccount();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']) . tep_draw_hidden_field('popupname', 'name');
        $html .= '<table cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="dataTableContent">' . TEXT_INFO_FIRSTNAME . '</td>
                    <td class="dataTableContent">' . tep_draw_input_field('admin_firstname', $my_account['admin_firstname'], 'class="form-control"') . '</td>
                </tr>
                <tr>
                    <td class="dataTableContent">' . TEXT_INFO_LASTNAME . '</td><td class="dataTableContent">' . tep_draw_input_field('admin_lastname', $my_account['admin_lastname'], 'class="form-control"') . '</td>
                </tr>
            </table>
            <div class="btn-bar">
                <div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
                <div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
            </div></form></div>';
        return $html;
    }
    public function action_emailform()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $html = '<div id="accountpopup">' . tep_draw_form('save_account_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_account_form" onSubmit="return saveAccount();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']) . tep_draw_hidden_field('popupname', 'email');
        $html .= '<table cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="dataTableContent">' . TEXT_INFO_EMAIL . '</td>
                    <td class="dataTableContent">' . tep_draw_input_field('admin_email_address', $my_account['admin_email_address'], 'class="form-control"') . '</td>
                </tr>
            </table>
            <div class="btn-bar">
                <div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
                <div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
            </div></form></div>';
        return $html;
    }
    public function action_passwordform()
    {
        \common\helpers\Translation::init('main');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $password_confirmation = \Yii::$app->request->post('password_confirmation');
        return $this->render_partial('password-form.tpl', ['myAccount' => $my_account, 'password_confirmation' => $password_confirmation, 'action' => \common\helpers\Output::get_all_get_params(['action']) . 'action=update']);
    }
    public function action_changeavatar()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $html = '<div id="accountpopup">' . tep_draw_form('save_account_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_account_form" onSubmit="return saveAccount();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']) . tep_draw_hidden_field('popupname', 'avatar');
        $html .= '<div class="avatar_img"><div class="upload" data-name="avatar"></div></div>
            <div class="btn-bar">
                <div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
                <div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
            </div></form></div>';
        $html .= '<script type="text/javascript">$(document).ready(function(){$(".upload").uploads1();})</script>';
        return $html;
    }
    public function action_getpassword()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $html = '<div id="accountpopup">' . tep_draw_form('check_pass_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="check_pass_form" onSubmit="return checkPassword();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']);
        $html .= '<table cellspacing="0" cellpadding="0" width="100%">
							<tr>
									<td class="dataTableContent">' . TEXT_INFO_PASSWORD_CURRENT . '</td>
									<td class="dataTableContent">' . \common\helpers\Html::password_input('password_confirmation', '', ['class' => 'form-control']) . '</td>
							</tr>
					</table>
					<div class="btn-bar">
							<div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
							<div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
					</div></form><script>$(function(){$(\'input[type="password"]\').showPassword()})</script></div>';
        return $html;
    }
    public function action_checkpassword()
    {
        $this->layout = false;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $password_confirmation = \Yii::$app->request->post('password_confirmation');
        $check_pass_query = tep_db_query('select admin_password as confirm_password from ' . TABLE_ADMIN . " where admin_id = '" . $my_account['admin_id'] . "'");
        $check_pass = tep_db_fetch_array($check_pass_query);
        if (!\common\helpers\Password::validate_password($password_confirmation, $check_pass['confirm_password'], 'backend')) {
            ?>
                    <div class="alert alert-warning fade in">
                        <i data-dismiss="alert" class="icon-remove close"></i>
            <?php 
            echo TEXT_MESS_PASSWORD_WRONG;
            ?>
                    </div>
            <?php 
        } else {
            return $this->action_passwordform();
        }
    }
    public function action_deleteimage()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $sql_data_array['avatar'] = '';
        tep_db_perform(TABLE_ADMIN, $sql_data_array, 'update', 'admin_id = \'' . $my_account['admin_id'] . '\'');
        ?><div class="popup-box-wrap delete_popup"><div class="around-pop-up"></div><div class="popup-box"><div class="popup-heading cat-head"><?php 
        echo TEXT_EDITING_ACCOUNT;
        ?></div><div class="pop-up-content">
        								<div class="alert alert-success fade in">
        										<i data-dismiss="alert" class="icon-remove close"></i>
        <?php 
        echo TEXT_MESSTYPE_SUCCESS;
        ?>
        								</div>
        				</div></div></div>
        <?php 
    }
    public function action_usernameform()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $html = '<div id="accountpopup">' . tep_draw_form('save_account_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_account_form" onSubmit="return saveAccount();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']) . tep_draw_hidden_field('popupname', 'admin_username');
        $html .= '<table cellspacing="0" cellpadding="0" width="100%">
					<tr>
						<td class="dataTableContent">' . TEXT_INFO_USERNAME . '</td>
						<td class="dataTableContent">' . tep_draw_input_field('admin_username', $my_account['admin_username'], 'class="form-control"') . '</td>
					</tr>
				</table>
				<div class="btn-bar">
					<div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
					<div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
				</div></form></div>';
        return $html;
    }
    public function action_customerform()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $customers = [];
        $customers[] = ['id' => '', 'text' => TEXT_SELECT_CUSTOMER];
        $mail_query = tep_db_query('select customers_id, customers_email_address, customers_firstname, customers_lastname from ' . TABLE_CUSTOMERS . ' where 1 order by customers_lastname');
        while ($customers_values = tep_db_fetch_array($mail_query)) {
            $customers[] = ['id' => $customers_values['customers_id'], 'text' => $customers_values['customers_lastname'] . ', ' . $customers_values['customers_firstname'] . ' (' . $customers_values['customers_email_address'] . ')'];
        }
        $html = '<div id="accountpopup">' . tep_draw_form('save_account_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_account_form" onSubmit="return saveAccount();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']) . tep_draw_hidden_field('popupname', 'customers_id');
        $html .= '<table cellspacing="0" cellpadding="0" width="100%">
					<tr>
						<td class="dataTableContent">' . ENTRY_CUSTOMER . '</td>
						<td class="dataTableContent">' . tep_draw_pull_down_menu('customers_id', $customers, $my_account['customers_id'], 'class="form-control"') . '</td>
					</tr>
				</table>
				<div class="btn-bar">
					<div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
					<div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
				</div></form></div>';
        return $html;
    }
    public function action_select_customer_form()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        return $this->render_partial('customer-form.tpl', ['myAccount' => $my_account, 'action' => \common\helpers\Output::get_all_get_params(['action']) . 'action=update']);
    }
    public function action_select_pos_platform_form()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $a_platforms = \common\classes\platform::get_list(true, true);
        return $this->render_partial('pos-platform-form.tpl', ['myAccount' => $my_account, 'platformDropDown' => ['0' => TEXT_ALL] + Array_Helper::map($a_platforms, 'id', 'text'), 'posPlatformId' => isset($my_account['posPlatform']['platform_id']) ? $my_account['posPlatform']['platform_id'] : 0, 'action' => \common\helpers\Output::get_all_get_params(['action']) . 'action=update']);
    }
    public function action_select_pos_currency_form()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $a_currencies = Currencies::find()->where(['status' => 1])->order_by(['sort_order' => SORT_ASC])->all();
        return $this->render_partial('pos-currency-form.tpl', ['myAccount' => $my_account, 'currencyDropDown' => ['0' => TEXT_DEFAULT] + Array_Helper::map($a_currencies, 'currencies_id', 'code'), 'posCurrencyId' => isset($my_account['posPlatform']['platform_id']) ? $my_account['posPlatform']['platform_id'] : 0, 'action' => \common\helpers\Output::get_all_get_params(['action']) . 'action=update']);
    }
    public function action_get_customers($term = '', $limit = 25)
    {
        $a_customers = Customers::find()->select(['customers_id', 'customers_email_address', 'customers_firstname', 'customers_lastname'])->filter_where(['or', ['like', 'customers_email_address', $term], ['like', 'customers_firstname', $term], ['like', 'customers_lastname', $term], ['like', 'pin', $term], ['like', 'customers_telephone', $term]])->order_by('customers_lastname')->limit($limit)->as_array()->all();
        $result = [];
        if ($a_customers != null) {
            foreach ($a_customers as $customer) {
                $customer_text = $customer['customers_lastname'] . ', ' . $customer['customers_firstname'] . ' (' . $customer['customers_email_address'] . ')';
                $result[] = ['id' => $customer['customers_id'], 'label' => $customer_text, 'value' => $customer_text];
            }
        }
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result;
    }
    public function action_getpin()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $login_id = (int) tep_session_var('login_id');
        $my_account = $this->get_admin_obj($login_id);
        $html = '<div id="accountpopup">' . tep_draw_form('save_account_form', 'adminaccount', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_account_form" onSubmit="return saveAccount();"') . tep_draw_hidden_field('admin_id', $my_account['admin_id']) . tep_draw_hidden_field('popupname', 'pin');
        $html .= '<table cellspacing="0" cellpadding="0" width="100%">
					<tr>
						<td class="dataTableContent">' . TEXT_PIN . '</td>
						<td class="dataTableContent">' . tep_draw_input_field('pin', $my_account['pin'], 'class="form-control"') . '</td>
					</tr>
				</table>
				<div class="btn-bar">
					<div class="btn-left"><a href="javascript:void(0)" class="btn btn-cancel" onclick="return closePopup()">' . IMAGE_CANCEL . '</a></div>
					<div class="btn-right"><button class="btn btn-primary">' . IMAGE_UPDATE . '</button></div>
				</div></form></div>';
        return $html;
    }
    public function action_generate_password()
    {
        if (\Yii::$app->request->is_ajax) {
            $frontend = (int) \Yii::$app->request->get('frontend', 0);
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return \common\helpers\Password::randomize($frontend);
        }
    }
    public function action_first_setup()
    {
        global $login_id;
        $admin = \common\models\Admin::find_one($login_id);
        if (!$admin instanceof \common\models\Admin) {
            return $this->redirect(['index/']);
        }
        if ($admin->disposable != 1) {
            return $this->redirect(['index/']);
        }
        if (\Yii::$app->request->is_post) {
            $admin->admin_firstname = \Yii::$app->request->post('admin_firstname');
            $admin->admin_lastname = \Yii::$app->request->post('admin_lastname');
            $admin->admin_email_address = \Yii::$app->request->post('admin_email_address');
            $admin->admin_phone_number = \Yii::$app->request->post('admin_phone_number');
            $admin_password = \common\helpers\Password::randomize(false);
            //$admin_password = \Yii::$app->request->post('admin_password');
            $admin->admin_password = \common\helpers\Password::encrypt_password(tep_db_prepare_input($admin_password), 'backend');
            $admin->disposable = 0;
            $admin->save(false);
            $email_params = [];
            $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(HTTP_SERVER . DIR_WS_ADMIN);
            $email_params['CUSTOMER_FIRSTNAME'] = $admin->admin_firstname;
            $email_params['CUSTOMER_LASTNAME'] = $admin->admin_lastname;
            $email_params['CUSTOMER_EMAIL'] = $admin->admin_email_address;
            $email_params['STORE_OWNER'] = STORE_OWNER;
            $email_params['NEW_PASSWORD'] = $admin_password;
            list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin update', $email_params);
            \common\helpers\Mail::send($admin->admin_firstname . ' ' . $admin->admin_lastname, $admin->admin_email_address, $email_subject, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, [], '', '', ['add_br' => 'no']);
            return $this->redirect(['logout/']);
        }
        $this->layout = false;
        return $this->render('first-setup');
    }
}