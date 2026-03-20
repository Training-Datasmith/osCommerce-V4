<?php

declare (strict_types=1);
namespace backend\controllers;

use Yii;
use yii\web\Controller;
/**
 * Password forgotten controller to handle user requests.
 */
class Password_Forgotten_New_Password_Controller extends Controller
{
    /**
     * Disable layout for the controller view
     */
    public $layout = false;
    public function action_index()
    {
        if (\Yii::$app->request->is_ajax) {
            if (\Yii::$app->request->get('action', null) == 'gp') {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return \common\helpers\Password::randomize(false);
            }
            die;
        }
        $token = \Yii::$app->request->get('token', null);
        $admin_info = null;
        \common\models\Admin::update_all(['token' => '', 'token_date' => '0000-00-00 00:00:00'], ['<=', 'token_date', date('Y-m-d H:i:s', strtotime('-' . (int) trim(defined('FORGOTTEN_PASSWORD_TOKEN_EXPIRE_MIN') ? constant('FORGOTTEN_PASSWORD_TOKEN_EXPIRE_MIN') : 5) . ' minutes'))]);
        foreach (\common\models\Admin::find()->where(['!=', 'token', ''])->as_array(false)->each(10) as $a_record) {
            if (\common\helpers\Password::validate_password($token, $a_record->get_token(), 'backend') and \common\helpers\Password::validate_password($a_record->admin_email_address, $a_record->admin_email_token, 'backend')) {
                $admin_info = $a_record;
                break;
            }
        }
        unset($a_record);
        if (!is_object($admin_info)) {
            tep_admin_check_login();
            tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
        }
        \common\helpers\Translation::init('account/password');
        \common\helpers\Translation::init('admin/admin_account');
        \common\helpers\Translation::init('main');
        $message_account_password = '';
        if (empty($token)) {
            $message_account_password = TEXT_INVALID_TOKEN;
        } elseif (\Yii::$app->request->is_post) {
            $save = true;
            $post_token = \Yii::$app->request->post('token', null);
            if ($token != $post_token) {
                $message_account_password = TEXT_INVALID_TOKEN;
                $save = false;
            }
            if (!is_object($admin_info)) {
                $message_account_password = TEXT_INVALID_TOKEN;
                $save = false;
            }
            if ($save) {
                $admin_password = \Yii::$app->request->post('password_new', null);
                $admin_password_confirm = \Yii::$app->request->post('password_confirmation', null);
                if ($admin_password != $admin_password_confirm) {
                    $message_account_password = TEXT_MESS_PASSWORD_WRONG;
                    $save = false;
                }
            }
            if ($save) {
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
                        $message_account_password .= ', Please <a href="' . Yii::$app->url_manager->create_url(['password-forgotten-new-password/', 'token' => $token]) . '">Try again</a>';
                        $save = false;
                    }
                }
            }
            if ($save) {
                if (defined('ADMIN_PASSWORD_USE_SAME') && ADMIN_PASSWORD_USE_SAME == 'True') {
                    \common\models\Admin_Old_Passwords::add_old($admin_info->admin_id, tep_db_prepare_input($admin_password));
                }
                $admin_info->admin_password = \common\helpers\Password::encrypt_password(tep_db_prepare_input($admin_password), 'backend');
                $admin_info->password_last_update = date('Y-m-d H:i:s');
                $admin_info->clear_token();
                $message_account_password = TEXT_PASSWORD_CHANGED . ', Please <a href="' . Yii::$app->url_manager->create_url('login/') . '">Login</a>';
            }
        }
        return $this->render('index', ['account_password_action' => ['password-forgotten-new-password/', 'token' => $token], 'token' => $token, 'message_account_password' => $message_account_password]);
    }
}