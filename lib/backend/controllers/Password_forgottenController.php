<?php

declare (strict_types=1);
namespace backend\controllers;

use Yii;
use yii\web\Controller;
/**
 * Password forgotten controller to handle user requests.
 */
class Password_forgotten_Controller extends Controller
{
    /**
     * Disable layout for the controller view
     */
    public $layout = false;
    public $error_message = '';
    public $enable_csrf_validation = false;
    /**
     * Index action is the default action in a controller.
     */
    public function action_index()
    {
        $_GET['login'] = '';
        if (isset($_GET['action']) && $_GET['action'] == 'process') {
            $login_model = new \backend\forms\Login(['captha_enabled' => true]);
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
                    $_GET['login'] = 'captcha';
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
                $_GET['login'] = 'captcha';
            }
            if ($_GET['login'] == '') {
                if (\common\models\Admin_Password_Forgot_Log::is_blocked() == true) {
                    $_GET['login'] = 'ban';
                }
            }
            if ($_GET['login'] == '') {
                \common\models\Admin_Password_Forgot_Log::register();
                $email_address = Yii::$app->request->post('email_address', '');
                $log_times = \Yii::$app->request->post('log_times') + 1;
                if ($log_times >= 4) {
                    tep_session_register('password_forgotten');
                }
                // Check if email exists
                $check_admin_query = tep_db_query('select admin_id as check_id, admin_firstname as check_firstname, admin_lastname as check_lastname, admin_phone_number as check_phone_number, admin_email_address as check_email_address, admin_email_token as check_email_token, admin_username from ' . TABLE_ADMIN . " where admin_email_address = '" . tep_db_input($email_address) . "'");
                if (!tep_db_num_rows($check_admin_query)) {
                    $_GET['login'] = 'fail';
                } else {
                    $check_admin = tep_db_fetch_array($check_admin_query);
                    $password_reset_fileds = ['firstname'];
                    if (defined('RESET_PASSWORD_FIELDS')) {
                        $password_reset_fileds = explode(', ', RESET_PASSWORD_FIELDS);
                    }
                    $login_fail = false;
                    foreach ($password_reset_fileds as $password_reset_filed) {
                        switch ($password_reset_filed) {
                            case 'firstname':
                                $firstname = Yii::$app->request->post('firstname', '');
                                if ($check_admin['check_firstname'] != $firstname) {
                                    $login_fail = true;
                                }
                                break;
                            case 'lastname':
                                $lastname = Yii::$app->request->post('lastname', '');
                                if ($check_admin['check_lastname'] != $lastname) {
                                    $login_fail = true;
                                }
                                break;
                            case 'phone':
                                $phone = Yii::$app->request->post('phone', '');
                                if ($check_admin['check_phone_number'] != $phone) {
                                    $login_fail = true;
                                }
                                break;
                            case 'username':
                                $username = Yii::$app->request->post('username', '');
                                if ($check_admin['admin_username'] != $username) {
                                    $login_fail = true;
                                }
                                break;
                            default:
                                break;
                        }
                    }
                    if (!\common\helpers\Password::validate_password($check_admin['check_email_address'], $check_admin['check_email_token'], 'backend')) {
                        $login_fail = true;
                    }
                    if ($login_fail) {
                        $_GET['login'] = 'fail';
                    } else {
                        $_GET['login'] = 'success';
                        //{{
                        //\common\models\AdminPasswordForgotLog::clear();
                        $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
                        $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
                        $STORE_NAME = $platform_config->const_value('STORE_NAME');
                        $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                        $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                        $email_params = [];
                        if (defined('ADMIN_PASSWORD_FORGOTTEN_MODE') && ADMIN_PASSWORD_FORGOTTEN_MODE == 'invite') {
                            $admin_info = \common\models\Admin::find_one($check_admin['check_id']);
                            $email_params['NEW_PASSWORD_SENTENCE'] = '';
                            if ($admin_info) {
                                $token = $admin_info->update_token();
                                \common\helpers\Translation::init('account/password-forgotten');
                                $email_params['NEW_PASSWORD'] = \yii\helpers\Html::a(TEXT_PASSWORD_INVITATION_LINK, tep_href_link('password-forgotten-new-password/', 'token=' . $token, 'SSL'));
                                unset($token);
                            } else {
                                $email_params['NEW_PASSWORD'] = '';
                            }
                        } else {
                            $make_password = \common\helpers\Password::randomize();
                            $email_params['NEW_PASSWORD'] = $make_password;
                            tep_db_query('update ' . TABLE_ADMIN . " set admin_password = '" . tep_db_input(\common\helpers\Password::encrypt_password($make_password, 'backend')) . "', reset_ip='" . tep_db_input(\common\helpers\System::get_ip_address()) . "', reset_date = now(), password_last_update = now() where admin_id = '" . $check_admin['check_id'] . "'");
                        }
                        $email_params['STORE_NAME'] = $STORE_NAME;
                        $email_params['CUSTOMER_FIRSTNAME'] = $check_admin['check_firstname'];
                        $email_params['HTTP_HOST'] = \common\helpers\Output::get_clickable_link(tep_href_link(FILENAME_LOGIN));
                        $email_params['CUSTOMER_EMAIL'] = $check_admin['check_email_address'];
                        $email_params['STORE_OWNER_EMAIL_ADDRESS'] = $STORE_OWNER_EMAIL_ADDRESS;
                        list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Admin Password Forgotten', $email_params);
                        //}}
                        \common\helpers\Mail::send($check_admin['check_firstname'] . ' ' . ($check_admin['admin_lastname'] ?? null), $check_admin['check_email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, $email_params);
                    }
                }
            }
            echo $_GET['login'];
        }
    }
}