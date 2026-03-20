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

use yii\web\Controller;
/**
 * Controller is the customized base controller class.
 * All controller classes for this application should extend from this base class.
 */
class Sceleton extends Controller
{
    public $enable_csrf_validation = false;
    /**
     * @var array the breadcrumbs of the current page.
     */
    public $navigation = [];
    /**
     * @var array
     */
    public $top_buttons = [];
    /**
     * @var stdClass the variables for smarty.
     */
    public $view = null;
    /**
     * Access Control List
     * @var array current access level
     */
    public $acl = null;
    /**
     * Selected items in menu
     * @var array
     */
    public $selected_menu = [];
    public function __construct($id, $module = null)
    {
        \common\helpers\Admin::check_backend_strict_access_allowed();
        if (($this->acl[0] ?? null) === 'BOX_HEADING_DEPARTMENTS') {
            //skip superadmin menu
        } elseif (!is_null($this->acl)) {
            $last_element = is_array($this->acl) ? end($this->acl) : $this->acl;
            $wtf = \common\helpers\Admin_Box::build_navigation($last_element);
            if (!empty($wtf)) {
                $this->acl = $wtf;
                // have no idea why $this->acl was always overrided before
            }
            \common\helpers\Acl::check_access($this->acl);
        }
        $this->layout = 'main.tpl';
        \Yii::$app->view->title = \Yii::$app->name;
        $this->view = new \stdClass();
        $this->view->translations = null;
        $this->view->heading_title = null;
        $this->view->notification_count = 0;
        $this->view->error_message = null;
        $this->view->use_popup_mode = null;
        \common\helpers\Menu_Helper::categories_to_menu_message();
        \common\helpers\Admin::app_shop_connected_message();
        return parent::__construct($id, $module);
    }
    public function bind_action_params($action, $params)
    {
        if ($action->id == 'index') {
            \common\helpers\Translation::init('admin/' . $action->controller->id);
        } else {
            \common\helpers\Translation::init('admin/' . $action->controller->id . '/' . $action->id);
        }
        \common\helpers\Translation::init('admin/main');
        \common\helpers\Translation::init('main');
        return parent::bind_action_params($action, $params);
    }
    public function before_action($action)
    {
        foreach (\common\helpers\Hooks::get_list('sceleton/before-action') as $filename) {
            include $filename;
        }
        $events = new \backend\components\Admin_Events();
        $events->register_notification_event();
        return parent::before_action($action);
    }
    public function actions()
    {
        $actions = parent::actions();
        $actions = array_merge($actions, \common\helpers\Acl::get_extension_actions($this->id));
        return $actions;
    }
    public function render($view, $params = [])
    {
        if (isset($this->navigation)) {
            $last_element = end($this->navigation);
            if (isset($last_element['title'])) {
                \Yii::$app->view->title = strip_tags($last_element['title']) . ' | ' . \common\classes\platform::name(\common\classes\platform::default_id()) . ' | ' . \Yii::$app->name;
            }
        }
        \backend\design\Data::main_data();
        return parent::render($view, $params);
    }
}