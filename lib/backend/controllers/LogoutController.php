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

use common\helpers\Translation;
use Yii;
use yii\web\Controller;
/**
 * default controller to handle user requests.
 */
class Logout_Controller extends Controller
{
    /**
     * Index action is the default action in a controller.
     */
    public function action_index()
    {
        global $login_id, $device_hash;
        $alsl_hash = trim(Yii::$app->request->get('hash'));
        if (!empty($alsl_hash)) {
            Translation::init('admin/logout');
            $this->layout = false;
            return $this->render('sessions', ['formAction' => \yii\helpers\Url::to(['logout/', 'hash' => null]), 'alslHash' => $alsl_hash]);
        }
        $alsl_hash = trim(Yii::$app->request->post('hash'), '');
        if ($alsl_hash != '') {
            \common\models\Admin_Login_Session_Logoff::delete_all(['<', 'alsl_date_expire', date('Y-m-d H:i:s')]);
            $alsl_record = \common\models\Admin_Login_Session_Logoff::find_one(['alsl_hash' => $alsl_hash]);
            if ($alsl_record instanceof \common\models\Admin_Login_Session_Logoff) {
                $login_id = (int) $alsl_record->alsl_admin_id;
                $device_hash = $alsl_record->alsl_device_id;
                \common\models\Admin_Login::delete_all(['al_admin_id' => $login_id]);
                \common\models\Admin_Login_Session::delete_all(['als_admin_id' => $login_id]);
                \common\models\Admin_Device::delete_all(['ad_admin_id' => $login_id, 'ad_device_id' => $device_hash]);
                $alsl_record->delete();
            }
            unset($alsl_record);
        }
        unset($alsl_hash);
        if (!tep_session_is_registered('admin_multi_session_error')) {
            $admin_login_log_record = new \common\models\Admin_Login_Log();
            $admin_login_log_record->all_event = 20;
            $admin_login_log_record->all_device_id = $device_hash;
            $admin_login_log_record->all_ip = '';
            $admin_login_log_record->all_agent = '';
            $admin_login_log_record->all_user_id = $login_id;
            $admin_login_log_record->all_user = \common\models\Admin_Login_Log::get_admin_email($login_id);
            $admin_login_log_record->all_date = date('Y-m-d H:i:s');
            try {
                $admin_login_log_record->save();
            } catch (\Exception $exc) {
            }
        }
        \common\models\Admin_Login_Session::delete_all(['als_admin_id' => (int) $login_id, 'als_device_id' => trim($device_hash)]);
        //tep_session_destroy();
        tep_session_unregister('login_id');
        tep_session_unregister('login_firstname');
        tep_session_unregister('login_groups_id');
        tep_session_unregister('login_affiliate');
        tep_session_unregister('login_vendor');
        tep_session_unregister('device_hash');
        session_regenerate_id();
        $session = Yii::$app->session;
        $session->destroy();
        return $this->redirect(['login/']);
    }
}