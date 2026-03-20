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

use common\helpers\Acl;
use Yii;
class Extensions_Controller extends Sceleton
{
    public function __construct($id, $mod = null)
    {
        $module = Yii::$app->request->get('module');
        if ($ext = \common\helpers\Acl::check_extension($module, 'acl')) {
            $this->acl = $ext::get_acl('adminActionIndex');
        }
        parent::__construct($id, $mod);
    }
    public function action_index()
    {
        $module = Yii::$app->request->get('module');
        $action = Yii::$app->request->get('action', 'adminActionIndex');
        $err_msg = null;
        if ($ext = Acl::check_extension($module, $action)) {
            if ($ext::allowed()) {
                if (method_exists($ext, 'initTranslation')) {
                    $ext::init_translation('init_beforeaction');
                }
                if ($action != 'actionRefreshTranslation' && !empty($acl = $ext::get_acl($action))) {
                    $this->acl = $acl;
                    Acl::check_access($acl);
                }
                if (!method_exists($ext, 'beforeAction') || $ext::before_action($action)) {
                    return $ext::$action();
                }
            } else {
                $err_msg = 'Extension is not allowed: ' . \yii\helpers\Html::encode($module);
            }
        } else if (Acl::check_extension($module)) {
            $err_msg = 'Extension has not this action: ' . \yii\helpers\Html::encode($module) . '::' . \yii\helpers\Html::encode($action);
        } else {
            $err_msg = 'Extension does not exist: ' . \yii\helpers\Html::encode($module);
        }
        if (!empty($err_msg)) {
            \Yii::error($err_msg);
            if (\common\helpers\System::is_development()) {
                die($err_msg);
            }
        }
    }
}