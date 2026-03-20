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
use yii\web\Controller;
/**
 * login controller to handle user requests.
 */
class Login_Controller extends Controller
{
    public function __construct($id, $module = null)
    {
        \common\helpers\Admin::check_backend_strict_access_allowed();
        \Yii::$app->view->title = TEXT_SIGN_IN . ' | ' . \common\classes\platform::name(\common\classes\platform::default_id()) . ' | ' . \Yii::$app->name;
        return parent::__construct($id, $module);
    }
    /**
     * Disable layout for the controller view
     */
    public $layout = false;
    public $error_message = '';
    //public $enableCsrfValidation = false;
    /**
     * Index action is the default action in a controller.
     */
    public function action_index()
    {
        global $language, $navigation;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/main');
        \common\helpers\Translation::init('admin/admin-login-view');
        $stamp = date('Y-m-d H:i:s', strtotime('-1 hour'));
        tep_db_query('update ' . TABLE_ADMIN . " set login_failture = 0, login_failture_ip = '', login_failture_date = NULL where login_failture > 2 and login_failture_date IS NOT NULL and login_failture_date < '" . $stamp . "'");
        $get = \Yii::$app->request->get();
        $get['login'] = '';
        // {{ From superadmin
        /*if (isset($get['uid']) && $get['uid'] > 0 && tep_not_null($get['tr'])) {
                    $check_admin = tep_db_fetch_array(tep_db_query("select admin_id, admin_groups_id, admin_firstname from admin where admin_id = '" . (int) $get['uid'] . "' and admin_password = '" . tep_db_input(tep_db_prepare_input($get['tr'])) . "'"));
                    if ($check_admin['admin_id'] > 0 && $get['uid'] == $check_admin['admin_id']) {
                        $login_id = $check_admin['admin_id'];
                        $login_groups_id = $check_admin['admin_groups_id'];
                        $login_firstname = $check_admin['admin_firstname'];
        
                        \common\helpers\Acl::saveManagerDeviceHash($login_id);
        
                        tep_session_register('login_id', $login_id);
                        tep_session_register('login_groups_id', $login_groups_id);
                        tep_session_register('login_first_name', $login_firstname);
        
                        tep_redirect(tep_href_link(FILENAME_DEFAULT));
                    }
                }*/
        $config = [];
        $action = '';
        if (\Yii::$app->request->get('action') == 'restore') {
            $action = 'restore';
            $config['captha_enabled'] = true;
        }
        $login_model = new \backend\forms\Login($config);
        // }}
        if (\Yii::$app->request->get('action', '') == 'process') {
            $error_message = TEXT_LOGIN_ERROR;
            if ($login_model->captha_enabled) {
                if (\Yii::$app->request->is_post and !in_array(\Yii::$app->request->post('action', ''), ['otp', 'authorize', 'gaauthorize'])) {
                    if ($login_model->load(Yii::$app->request->post()) && $login_model->validate()) {
                        if ($login_model->has_errors()) {
                            $error_message = '';
                            foreach ($login_model->get_errors() as $error) {
                                if (is_array($error)) {
                                    $error_message .= implode(', ', $error);
                                } elseif (is_string($error)) {
                                    $error_message .= $error;
                                }
                            }
                            $get['login'] = 'fail';
                        }
                    } else {
                        if ($login_model->has_errors()) {
                            $error_message = '';
                            foreach ($login_model->get_errors() as $error) {
                                if (is_array($error)) {
                                    $error_message .= implode(', ', $error);
                                } elseif (is_string($error)) {
                                    $error_message .= $error;
                                }
                            }
                        }
                        $get['login'] = 'fail';
                    }
                }
            }
            if ($get['login'] !== 'fail') {
                $email_address = isset($_POST['email_address']) ? tep_db_prepare_input($_POST['email_address']) : '';
                $password = isset($_POST['password']) ? tep_db_prepare_input($_POST['password']) : '';
                $admin_login_log_record = new \common\models\Admin_Login_Log();
                $admin_login_log_record->all_event = 1;
                $admin_login_log_record->all_device_id = \common\helpers\Acl::save_manager_device_hash(0);
                $admin_login_log_record->all_ip = \common\helpers\System::get_ip_address();
                $admin_login_log_record->all_agent = implode("\n", array_merge([\common\models\Admin_Login::get_ip_geo_information($admin_login_log_record->all_ip)], \common\helpers\System::get_http_user_info_array()));
                $admin_login_log_record->all_user_id = 0;
                $admin_login_log_record->all_user = $email_address;
                $admin_login_log_record->all_date = date('Y-m-d H:i:s');
                $admin_security_key = '';
                if (\Yii::$app->request->post('action', '') == 'authorize') {
                    $al_admin_login_id = trim(tep_session_is_registered('al_admin_login_id') ? tep_session_var('al_admin_login_id') : '');
                    tep_session_unregister('al_admin_login_id');
                    if ($al_admin_login_id == '') {
                        tep_redirect(tep_href_link(FILENAME_DEFAULT));
                    }
                    $email_address = $al_admin_login_id;
                    $admin_security_key = preg_replace('/[^0-9a-z]*/si', '', \Yii::$app->request->post('security_key', ''));
                    $admin_login_log_record->all_event = 3;
                    $admin_login_log_record->all_user = $email_address;
                }
                if (\Yii::$app->request->post('action', '') == 'gaauthorize') {
                    $al_admin_login_id = trim(tep_session_is_registered('al_admin_login_id') ? tep_session_var('al_admin_login_id') : '');
                    if ($al_admin_login_id == '') {
                        tep_redirect(tep_href_link(FILENAME_DEFAULT));
                    }
                    $email_address = $al_admin_login_id;
                }
                // Check if email exists
                $check_admin_query = tep_db_query('select * from ' . TABLE_ADMIN . " where (admin_email_address = '" . tep_db_input($email_address) . "' or admin_username='" . tep_db_input($email_address) . "')");
                if (!tep_db_num_rows($check_admin_query)) {
                    $get['login'] = 'fail';
                }
            }
            if ($get['login'] !== 'fail') {
                $check_admin = tep_db_fetch_array($check_admin_query);
                if (!\common\helpers\Password::validate_password($check_admin['admin_email_address'], $check_admin['admin_email_token'], 'backend')) {
                    $get['login'] = 'fail';
                    $error_message = 'TEXT_' . strtoupper(\common\models\Admin_Login_Log::$event_list[4]);
                    $error_message = defined($error_message) ? constant($error_message) : 'Wrong email security token';
                    $admin_login_log_record->all_event = 4;
                    $admin_login_log_record->all_user_id = $check_admin['admin_id'];
                    $admin_login_log_record->all_user = $check_admin['admin_email_address'];
                }
            }
            if ($get['login'] !== 'fail') {
                $admin_login_log_record->all_user_id = $check_admin['admin_id'];
                $admin_login_log_record->all_user = $check_admin['admin_email_address'];
                if (defined('ADMIN_LOGIN_OTP_ENABLE') and ADMIN_LOGIN_OTP_ENABLE == 'True' and !in_array(\Yii::$app->request->post('action', ''), ['otp', 'authorize', 'gaauthorize'])) {
                    $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
                    $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
                    $STORE_NAME = $platform_config->const_value('STORE_NAME');
                    $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                    $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                    $email_params = [];
                    $email_params['NEW_PASSWORD'] = \common\helpers\Password::randomize();
                    $email_params['STORE_NAME'] = $STORE_NAME;
                    $email_params['CUSTOMER_FIRSTNAME'] = $check_admin['admin_firstname'];
                    $email_params['HTTP_HOST'] = \common\helpers\Output::get_clickable_link(tep_href_link(FILENAME_LOGIN));
                    $email_params['CUSTOMER_EMAIL'] = $check_admin['admin_email_address'];
                    $email_params['STORE_OWNER_EMAIL_ADDRESS'] = $STORE_OWNER_EMAIL_ADDRESS;
                    tep_db_query('UPDATE `' . TABLE_ADMIN . "` SET `admin_password` = '" . tep_db_input(\common\helpers\Password::encrypt_password($email_params['NEW_PASSWORD'], 'backend')) . "', `reset_ip` = '" . tep_db_input(\common\helpers\System::get_ip_address()) . "', `reset_date` = now(), `password_last_update` = now() WHERE `admin_id` = '" . $check_admin['admin_id'] . "';");
                    list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin Password Forgotten', $email_params);
                    \common\helpers\Mail::send($check_admin['admin_firstname'] . ' ' . $check_admin['admin_lastname'], $check_admin['admin_email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, $email_params);
                    $login_model->captha_enabled = false;
                    return $this->render('index', ['passwordResetFileds' => [], 'loginModel' => $login_model, 'action' => 'otp', 'email' => $check_admin['admin_email_address']]);
                }
                $is_admin_no_password = false;
                $is_guest = (int) \Yii::$app->request->post('ad_is_guest', 0) > 0 ? true : false;
                $al_computer_id = md5($check_admin['admin_id'] . $check_admin['admin_email_address'] . ((defined('ADMIN_LOGIN_OTP_ENABLE') and ADMIN_LOGIN_OTP_ENABLE == 'True') ? $check_admin['admin_email_token'] : $check_admin['admin_password']) . $_SERVER['HTTP_USER_AGENT'] . \common\helpers\System::get_ip_address());
                if ($admin_security_key != '') {
                    $admin_login_record = \common\models\Admin_Login::get_by_id_computer($check_admin['admin_id'], $al_computer_id);
                    if (is_object($admin_login_record)) {
                        if ($admin_login_record->al_expire == '0000-00-00 00:00:00' and \common\helpers\Password::validate_password($admin_security_key, $admin_login_record->al_security_key, 'backend')) {
                            $al_expire = date('Y-m-d H:i:s', strtotime('+3 second'));
                            $security_key_expire = (int) \Yii::$app->request->post('security_key_expire', 0);
                            $security_key_expire_array = \common\models\Admin_Login::get_security_key_expire_array();
                            if ($is_guest != true and isset($security_key_expire_array[$security_key_expire]) and (int) $security_key_expire_array[$security_key_expire]['ale_expire_minutes'] > 0) {
                                $al_expire = date('Y-m-d H:i:s', strtotime('+' . (int) $security_key_expire_array[$security_key_expire]['ale_expire_minutes'] . ' minutes'));
                            }
                            $admin_login_record->al_expire = $al_expire;
                            $admin_login_record->save(false);
                            $is_admin_no_password = true;
                        } else {
                            $admin_login_record->delete();
                        }
                    }
                }
                $is_g_apassed = null;
                if (\Yii::$app->request->post('action', '') == 'gaauthorize') {
                    $ga_security_key = preg_replace('/[^0-9a-z]*/si', '', \Yii::$app->request->post('security_key', ''));
                    if ($ga_security_key != '') {
                        /**
                         * @var $ext \common\extensions\GoogleAuthenticator\GoogleAuthenticator
                         */
                        if ($ext = \common\helpers\Extensions::is_allowed('GoogleAuthenticator')) {
                            $is_g_apassed = $is_admin_no_password = $ext::check_two_step_auth($check_admin);
                        }
                    }
                }
                // Check that password is good
                if ($check_admin['login_failture'] >= 3) {
                    $get['login'] = 'fail';
                    $error_message = TEXT_LOGIN_BLOCK;
                } elseif ($is_admin_no_password !== true and ($p_check = \common\helpers\Password::validate_password($password, $check_admin['admin_password'], 'backend')) != true) {
                    $get['login'] = 'fail';
                    if ($p_check === 0) {
                        //passord format is changed, pwd is OK
                        $error_message = ADMIN_TEXT_LOGIN_REQUEST_NEW_PASSWORD;
                    } else {
                        if ($admin_login_log_record->all_event == 1) {
                            $admin_login_log_record->all_event = 2;
                        }
                        \common\models\Fraud::register_address();
                        $login_model = new \backend\forms\Login();
                        tep_db_query('update ' . TABLE_ADMIN . " set login_failture = login_failture + 1, login_failture_ip='" . tep_db_input(\common\helpers\System::get_ip_address()) . "', login_failture_date = now() where admin_id = '" . (int) $check_admin['admin_id'] . "'");
                        $login_failture = 3 - ($check_admin['login_failture'] + 1);
                        if ($login_failture < 0) {
                            $login_failture = 0;
                        }
                        $error_message = sprintf(TEXT_LOGIN_WARNING, $login_failture);
                        try {
                            $email_subject = '';
                            $email_message = '';
                            $parameter_array = ['DEVICE_DATE' => date('Y-m-d H:i:s'), 'DEVICE_AGENT' => $admin_login_log_record->all_agent, 'DEVICE_IP' => $admin_login_log_record->all_ip, 'LOGIN_URL' => Yii::$app->url_manager->create_absolute_url(['login'])];
                            switch ($admin_login_log_record->all_event) {
                                case 2:
                                    list($email_subject, $email_message) = \common\helpers\Mail::get_parsed_email_template('Admin Login Password Error', $parameter_array);
                                    break;
                                case 3:
                                    list($email_subject, $email_message) = \common\helpers\Mail::get_parsed_email_template('Admin Login Security Key Error', $parameter_array);
                                    break;
                            }
                            if ($email_subject != '' and $email_message != '') {
                                \common\helpers\Mail::send(trim(trim($check_admin['admin_firstname']) . ' ' . trim($check_admin['admin_lastname'])), trim($check_admin['admin_email_address']), $email_subject, $email_message, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $parameter_array);
                            }
                            unset($parameter_array);
                            unset($email_subject);
                            unset($email_message);
                        } catch (\Exception $exc) {
                            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'ErrorAdminLoginNotify');
                        }
                    }
                } else {
                    if ($check_admin['admin_two_step_auth'] != 'disabled' and (ADMIN_TWO_STEP_AUTH_ENABLED == 'true' or $check_admin['admin_two_step_auth'] != '')) {
                        $is_admin_logged = false;
                        /**
                         * @var $ext \common\extensions\GoogleAuthenticator\GoogleAuthenticator
                         */
                        if ($ext = \common\helpers\Extensions::is_allowed('GoogleAuthenticator')) {
                            if ($is_g_apassed) {
                                $is_admin_logged = true;
                            } else {
                                return $ext::ask_two_step_auth($check_admin);
                            }
                        } else {
                            $admin_login_record = \common\models\Admin_Login::get_by_id_computer($check_admin['admin_id'], $al_computer_id);
                            if (is_object($admin_login_record)) {
                                if (strtotime($admin_login_record->al_expire) > time()) {
                                    $is_admin_logged = true;
                                } else {
                                    $admin_login_record->delete();
                                }
                            }
                        }
                        if ($is_admin_logged != true) {
                            $al_security_key = \common\models\Admin_Login::security_key_generate();
                            $admin_login_record = new \common\models\Admin_Login();
                            $admin_login_record->al_admin_id = (int) $check_admin['admin_id'];
                            $admin_login_record->al_computer_id = trim($al_computer_id);
                            $admin_login_record->al_security_key = \common\helpers\Password::encrypt_password(preg_replace('/[^0-9a-z]*/si', '', $al_security_key), 'backend');
                            $admin_login_record->al_expire = '0000-00-00 00:00:00';
                            $admin_login_record->al_create = date('Y-m-d H:i:s');
                            $admin_login_record->save(false);
                            tep_session_register('al_admin_login_id', $check_admin['admin_email_address']);
                            $security_key_expire_array = [];
                            foreach (\common\models\Admin_Login::get_security_key_expire_array() as $login_expire_array) {
                                $security_key_expire_array[] = ['id' => $login_expire_array['ale_id'], 'text' => $login_expire_array['ale_title']];
                            }
                            $is_mobile = false;
                            if ($ext = \common\helpers\Extensions::is_allowed('MobileDetect')) {
                                $is_mobile = $ext::is_mobile_tablet_or_iphone();
                            }
                            $parameter_array = ['securityKeyExpireArray' => $security_key_expire_array, 'isMobile' => $is_mobile];
                            $two_step_auth_service = $check_admin['admin_two_step_auth'] != '' ? $check_admin['admin_two_step_auth'] : ADMIN_TWO_STEP_AUTH_SERVICE;
                            if ($two_step_auth_service == 'email' or trim($check_admin['admin_phone_number']) == '') {
                                $parameter_array['type'] = 'email';
                                \common\models\Admin_Login::security_key_email($check_admin, $al_security_key);
                            } else {
                                $parameter_array['type'] = 'sms';
                                if (\common\models\Admin_Login::security_key_sms($check_admin, $al_security_key) != true) {
                                    $parameter_array['type'] = 'email';
                                    \common\models\Admin_Login::security_key_email($check_admin, $al_security_key);
                                }
                            }
                            return $this->render('authorize', $parameter_array);
                        }
                    }
                    if (tep_session_is_registered('password_forgotten')) {
                        tep_session_unregister('password_forgotten');
                    }
                    $login_id = $check_admin['admin_id'];
                    $login_groups_id = $check_admin['admin_groups_id'];
                    $login_firstname = $check_admin['admin_firstname'];
                    $login_email_address = $check_admin['admin_email_address'];
                    $login_logdate = $check_admin['admin_logdate'];
                    $login_lognum = $check_admin['admin_lognum'];
                    $login_modified = $check_admin['admin_modified'];
                    $access_levels_id = $check_admin['access_levels_id'];
                    $language = $check_admin['languages'];
                    $admin_login_log_record->all_event = 10;
                    session_regenerate_id();
                    tep_session_register('login_id', $login_id);
                    tep_session_register('login_groups_id', $login_groups_id);
                    tep_session_register('login_first_name', $login_firstname);
                    tep_session_register('access_levels_id', $access_levels_id);
                    tep_session_register('language', $language);
                    $lng = new \common\classes\language();
                    $lng->set_language($language);
                    $languages_id = $lng->language['id'];
                    tep_session_register('languages_id', $languages_id);
                    $lng->set_locale();
                    $lng->load_vars();
                    //$date_now = date('Ymd');
                    $device_hash = \common\helpers\Acl::save_manager_device_hash($login_id);
                    tep_session_register('device_hash', $device_hash);
                    $admin_login_log_record->all_device_id = $device_hash;
                    try {
                        $admin_login_log_record->save(false);
                    } catch (\Exception $exc) {
                    }
                    if (\common\models\Admin_Login::check_admin_device($login_id, $device_hash, $is_guest) != true or \common\models\Admin_Login_Session::update_admin_session($login_id, $device_hash) != true) {
                        tep_redirect(tep_href_link(FILENAME_LOGOFF));
                    }
                    tep_db_query('update ' . TABLE_ADMIN . " set login_failture = 0, login_failture_ip = '', token = '', admin_logdate = now(), admin_lognum = admin_lognum+1 where admin_id = '" . (int) $login_id . "'");
                    \common\models\Fraud::clean_address();
                    $expired_flag = false;
                    if (defined('ADMIN_PASSWORD_EXPIRE') && ADMIN_PASSWORD_EXPIRE != 'Never') {
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
                            $date_timestamp1 = strtotime($check_admin['password_last_update']);
                            if ($date_timestamp1 === false) {
                                $expired_flag = true;
                            } elseif ($date_timestamp1 < $date_timestamp2) {
                                $expired_flag = true;
                            }
                        }
                    }
                    $disposable = $check_admin['disposable'];
                    if ($disposable) {
                        tep_redirect(tep_href_link('adminaccount/first-setup'));
                    } elseif ($login_lognum == 0 || !$login_logdate || $login_email_address == 'admin@localhost' || $login_modified == '0000-00-00 00:00:00') {
                        tep_redirect(tep_href_link(FILENAME_ADMIN_ACCOUNT));
                    } elseif ($expired_flag) {
                        tep_redirect(tep_href_link('adminaccount'));
                    } else if (sizeof($navigation->snapshot) > 0) {
                        $origin_href = tep_href_link($navigation->snapshot['page'], \common\helpers\Output::array_to_string($navigation->snapshot['get'], [tep_session_name()]), $navigation->snapshot['mode']);
                        $navigation->clear_snapshot();
                        tep_redirect($origin_href);
                    } else {
                        tep_redirect(tep_href_link(FILENAME_DEFAULT));
                    }
                }
            }
            if ($get['login'] == 'fail') {
                $this->error_message = $error_message;
            }
            try {
                if (isset($admin_login_log_record)) {
                    $admin_login_log_record->save(false);
                }
            } catch (\Exception $exc) {
            }
        }
        if (!\Yii::$app->request->is_ajax and tep_session_is_registered('admin_multi_session_error')) {
            $this->error_message = ADMIN_MULTI_SESSION_ERROR;
            tep_session_unregister('admin_multi_session_error');
        }
        $password_reset_fileds = ['firstname'];
        if (defined('RESET_PASSWORD_FIELDS')) {
            $password_reset_fileds = explode(', ', RESET_PASSWORD_FIELDS);
        }
        return $this->render('index', ['passwordResetFileds' => $password_reset_fileds, 'loginModel' => $login_model, 'action' => $action]);
    }
}