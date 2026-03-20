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
namespace common\helpers;

use Yii;
class Acl
{
    public const ACL_CACHE_LIFETIME = 5;
    public const DEVICE_HASH_SOURCES = ['HTTP_USER_AGENT', 'HTTP_ACCEPT_LANGUAGE'];
    //const COOKIE_SALT = '__tladhs'; //doesn't work now cross domain cookies impossible
    protected static function get_admin_acl_data($login_id)
    {
        static $last_data = [];
        if (!isset($last_data[(int) $login_id])) {
            $last_data[(int) $login_id] = false;
            $check_admin = tep_db_query('select access_levels_id, admin_persmissions from ' . TABLE_ADMIN . " where admin_id = '" . (int) $login_id . "'");
            if (tep_db_num_rows($check_admin) > 0) {
                $admin = tep_db_fetch_array($check_admin);
                $admin['admin_persmissions'] = explode(',', (string) $admin['admin_persmissions']);
                $last_data[(int) $login_id] = $admin;
            }
        }
        return $last_data[(int) $login_id];
    }
    protected static function get_access_level_data($access_levels_id)
    {
        static $last_data = [];
        if (!isset($last_data[(int) $access_levels_id])) {
            $last_data[(int) $access_levels_id] = false;
            $check_access = tep_db_query('SELECT access_levels_persmissions FROM ' . TABLE_ACCESS_LEVELS . " WHERE access_levels_id = '" . (int) $access_levels_id . "'");
            $access = tep_db_fetch_array($check_access);
            $last_data[(int) $access_levels_id] = explode(',', (string) $access['access_levels_persmissions']);
        }
        return $last_data[(int) $access_levels_id];
    }
    public static function rule($rules, $root_id = 0, $selected_ids = '', $admin_id = false)
    {
        global $login_id;
        if (empty($admin_id)) {
            $admin_id = $login_id;
        }
        if (is_string($rules)) {
            $rules = [$rules];
        }
        if (is_string($selected_ids)) {
            if (empty($selected_ids)) {
                $admin = static::get_admin_acl_data($admin_id);
                if (!is_array($admin)) {
                    return false;
                }
                $admin_persmissions = $admin['admin_persmissions'];
                $selected_ids = static::get_access_level_data((int) $admin['access_levels_id']);
                if (count($admin_persmissions) > 0) {
                    foreach ($admin_persmissions as $permission_is) {
                        if (empty($permission_is)) {
                            continue;
                        }
                        if ($permission_is > 0) {
                            if (!in_array($permission_is, $selected_ids)) {
                                $selected_ids[] = $permission_is;
                                //add to list
                            }
                        } elseif ($permission_is < 0) {
                            if (false !== $key = array_search(abs($permission_is), $selected_ids)) {
                                unset($selected_ids[$key]);
                                //remove from list
                            }
                        }
                    }
                }
            } else {
                $selected_ids = explode(',', $selected_ids);
            }
        }
        $current_key = array_shift($rules);
        static $acl_list = false;
        if (!is_array($acl_list)) {
            $acl_list = [];
            $preload_r = tep_db_query('select access_control_list_id, access_control_list_key, parent_id from ' . TABLE_ACCESS_CONTROL_LIST . '');
            if (tep_db_num_rows($preload_r) > 0) {
                while ($preload = tep_db_fetch_array($preload_r)) {
                    $key = (int) $preload['parent_id'] . '@' . (string) $preload['access_control_list_key'];
                    $acl_list[$key] = $preload['access_control_list_id'];
                }
            }
        }
        $key = (int) $root_id . '@' . (string) $current_key;
        if (!isset($acl_list[$key])) {
            $check_query = tep_db_query('select access_control_list_id from ' . TABLE_ACCESS_CONTROL_LIST . " where access_control_list_key = '" . tep_db_input($current_key) . "' and parent_id = '" . (int) $root_id . "'");
            if (tep_db_num_rows($check_query) > 0) {
                $check = tep_db_fetch_array($check_query);
                $acl_list[$key] = $check['access_control_list_id'];
            }
        }
        if (isset($acl_list[$key])) {
            $current_id = $acl_list[$key];
        } else {
            if (defined('DISABLE_NEW_ACL') && DISABLE_NEW_ACL == 1) {
                return false;
            }
            if (empty($current_key)) {
                return true;
            }
            $sql_data_array = ['parent_id' => (int) $root_id, 'access_control_list_key' => $current_key];
            tep_db_perform(TABLE_ACCESS_CONTROL_LIST, $sql_data_array);
            $current_id = tep_db_insert_id();
        }
        if (count($rules) > 0) {
            $response = self::rule($rules, $current_id, $selected_ids);
        } else {
            $response = true;
        }
        if (!in_array($current_id, $selected_ids)) {
            $response = false;
        }
        return $response;
    }
    public static function build_tree($selected_ids = '', $root_id = 0)
    {
        $response = [];
        if (is_string($selected_ids)) {
            $selected_ids = explode(',', $selected_ids);
        }
        if (!is_array($selected_ids)) {
            $selected_ids = [];
        }
        $access_query = tep_db_query('select * from ' . TABLE_ACCESS_CONTROL_LIST . " where parent_id = '" . $root_id . "' order by sort_order");
        while ($access = tep_db_fetch_array($access_query)) {
            $current_id = $access['access_control_list_id'];
            if (defined($access['access_control_list_key'])) {
                $current_name = constant($access['access_control_list_key']);
            } else {
                $current_name = $access['access_control_list_key'];
            }
            /*if (in_array($currentId, $selectedIds)) {
                  $child = self::buildTree($selectedIds, $currentId);
              } else {
                  $child = [];
              }*/
            $child = self::build_tree($selected_ids, $current_id);
            $response[] = ['id' => $current_id, 'text' => $current_name, 'selected' => in_array($current_id, $selected_ids), 'child' => $child];
        }
        return $response;
    }
    public static function build_tree_pdo($selected_ids = '', $root_id = 0)
    {
        $response = [];
        if (is_string($selected_ids)) {
            $selected_ids = explode(',', $selected_ids);
        }
        if (!is_array($selected_ids)) {
            $selected_ids = [];
        }
        $access_query = \common\models\Pdo_Connector::query('select * from ' . TABLE_ACCESS_CONTROL_LIST . " where parent_id = '" . $root_id . "' order by sort_order");
        while ($access = \common\models\Pdo_Connector::fetch($access_query)) {
            $current_id = $access['access_control_list_id'];
            if (defined($access['access_control_list_key'])) {
                eval('$currentName =  ' . $access['access_control_list_key'] . ';');
            } else {
                $current_name = $access['access_control_list_key'];
            }
            /*if (in_array($currentId, $selectedIds)) {
                  $child = self::buildTreePDO($selectedIds, $currentId);
              } else {
                  $child = [];
              }*/
            $child = self::build_tree_pdo($selected_ids, $current_id);
            $response[] = ['id' => $current_id, 'text' => $current_name, 'selected' => in_array($current_id, $selected_ids), 'child' => $child];
        }
        return $response;
    }
    public static function build_override_tree($selected_ids = '', $admin_persmissions = '', $root_id = 0)
    {
        $response = [];
        if (is_string($selected_ids)) {
            $selected_ids = explode(',', $selected_ids);
        }
        if (is_string($admin_persmissions)) {
            $admin_persmissions = explode(',', $admin_persmissions);
        }
        $access_query = tep_db_query('select * from ' . TABLE_ACCESS_CONTROL_LIST . " where parent_id = '" . $root_id . "' order by sort_order");
        while ($access = tep_db_fetch_array($access_query)) {
            $current_id = $access['access_control_list_id'];
            if (defined($access['access_control_list_key'])) {
                eval('$currentName =  ' . $access['access_control_list_key'] . ';');
            } else {
                $current_name = $access['access_control_list_key'];
            }
            $show_childs = true;
            $selected = 0;
            if (in_array($current_id, $selected_ids)) {
                if (in_array($current_id * -1, $admin_persmissions)) {
                    $current_name = '<font color="red">' . $current_name . '</font>';
                    //red - removed
                    $show_childs = false;
                    $selected = 0;
                } else {
                    //normal mode
                    $show_childs = true;
                    $selected = 1;
                }
            } elseif (in_array($current_id, $admin_persmissions)) {
                $current_name = '<font color="green">' . $current_name . '</font>';
                //green - added
                $show_childs = true;
                $selected = 1;
            } else {
                //normal mode
                $show_childs = false;
                $selected = 0;
            }
            /*if ($showChilds) {
                  $child = self::buildOverrideTree($selectedIds, $adminPersmissions, $currentId);
              } else {
                  $child = [];
              }*/
            $child = self::build_override_tree($selected_ids, $admin_persmissions, $current_id);
            $response[] = ['id' => $current_id, 'text' => $current_name, 'selected' => $selected, 'child' => $child];
        }
        return $response;
    }
    public static function build_override_tree_pdo($selected_ids = '', $admin_persmissions = '', $root_id = 0)
    {
        $response = [];
        if (is_string($selected_ids)) {
            $selected_ids = explode(',', $selected_ids);
        }
        if (is_string($admin_persmissions)) {
            $admin_persmissions = explode(',', $admin_persmissions);
        }
        $access_query = \common\models\Pdo_Connector::query('select * from ' . TABLE_ACCESS_CONTROL_LIST . " where parent_id = '" . $root_id . "' order by sort_order");
        while ($access = \common\models\Pdo_Connector::fetch($access_query)) {
            $current_id = $access['access_control_list_id'];
            if (defined($access['access_control_list_key'])) {
                eval('$currentName =  ' . $access['access_control_list_key'] . ';');
            } else {
                $current_name = $access['access_control_list_key'];
            }
            $show_childs = true;
            $selected = 0;
            if (in_array($current_id, $selected_ids)) {
                if (in_array($current_id * -1, $admin_persmissions)) {
                    $current_name = '<font color="red">' . $current_name . '</font>';
                    //red - removed
                    $show_childs = false;
                    $selected = 0;
                } else {
                    //normal mode
                    $show_childs = true;
                    $selected = 1;
                }
            } elseif (in_array($current_id, $admin_persmissions)) {
                $current_name = '<font color="green">' . $current_name . '</font>';
                //green - added
                $show_childs = true;
                $selected = 1;
            } else {
                //normal mode
                $show_childs = false;
                $selected = 0;
            }
            /*if ($showChilds) {
                  $child = self::buildOverrideTree($selectedIds, $adminPersmissions, $currentId);
              } else {
                  $child = [];
              }*/
            $child = self::build_override_tree_pdo($selected_ids, $admin_persmissions, $current_id);
            $response[] = ['id' => $current_id, 'text' => $current_name, 'selected' => $selected, 'child' => $child];
        }
        return $response;
    }
    public static function check_access($rules)
    {
        if (false == \common\helpers\Acl::rule($rules)) {
            die('Access denied.');
        }
    }
    public const extPath = '\common\extensions\\';
    /**
     *
     * @param type $class
     * @param type $method = null - check to be sure to use extension classes
     * @param type $own_method
     * @return boolean
     */
    public static function check_extension($class, $method = null, $own_method = false)
    {
        $path = $class;
        if (strpos($class, '\\')) {
            $parts = explode('\\', $class);
            $class = $parts[sizeof($parts) - 1];
            $path = implode('\\', $parts);
        }
        if (!class_exists(self::extPath . $path . '\\' . $class)) {
            return false;
        }
        if (empty($method)) {
            return true;
        }
        if (!method_exists(self::extPath . $path . '\\' . $class, $method)) {
            return false;
        }
        if ($own_method) {
            $ref = new \ReflectionClass(self::extPath . $path . '\\' . $class);
            if ($ref->has_method($method)) {
                $_method = $ref->get_method($method);
                if ($_method->class == 'yii\base\Widget') {
                    return false;
                }
            }
            if (!$ref->has_method($method)) {
                return false;
            }
        }
        return self::extPath . $path . '\\' . $class;
    }
    public static function check_extension_installed($class, $method = null, $own_method = false)
    {
        $ext = self::check_extension($class, $method, $own_method);
        if (!$ext) {
            return false;
        }
        if (strpos($class, '\\')) {
            $parts = explode('\\', $class);
            $class = $parts[sizeof($parts) - 1];
        }
        return \common\helpers\Extensions::is_enabled($class) ? $ext : false;
    }
    public static function check_extension_allowed($class, $method = 'allowed', $own_method = false)
    {
        if ($extension = self::check_extension($class, $method, $own_method)) {
            if (call_user_func([$extension, $method])) {
                return $extension;
            }
        }
        return false;
    }
    /**
     * @param string $class className of extension
     * @param string $relativeModelName 'models\Collections' or just 'Collections'
     * @param string|null $allowedFunc
     * @return \yii\db\ActiveRecord|null
     */
    public static function check_extension_table_exist($class, $relative_model_name, $allowed_func = 'allowed')
    {
        return \common\helpers\Extensions::get_model($class, $relative_model_name, $allowed_func);
    }
    /**
     * @param string $relativeClassName 'helpers\Collections'
     */
    public static function check_extension_allowed_class($class, $relative_class_name)
    {
        if ($ext = self::check_extension_allowed($class)) {
            $class_name = $ext . "\\{$relative_class_name}";
            if (class_exists($class_name)) {
                return $class_name;
            }
        }
    }
    public static function get($class)
    {
        return self::extPath . $class . '\\' . $class;
    }
    public static function get_extension_pages()
    {
        $pages = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if (($_w = self::check_extension($class, 'getPages')) && $_w::allowed()) {
                $_pages = $_w::get_pages();
                if (is_array($_pages) && count($_pages)) {
                    foreach ($_pages as $wd) {
                        $pages[] = $wd;
                    }
                }
                if (!is_array($pages)) {
                    $pages = [];
                }
            }
        }
        return $pages;
    }
    public static function get_extension_actions($controller_id)
    {
        $actions = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if ($_w = self::check_extension($class, 'getControllerActions')) {
                $_actions = $_w::get_controller_actions($controller_id);
                if (is_array($_actions) && count($_actions) > 0) {
                    $actions = array_merge($actions, $_actions);
                }
                if (!is_array($actions)) {
                    $actions = [];
                }
            }
        }
        return $actions;
    }
    public static function get_extension_page_types()
    {
        $pages = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if ($_w = self::check_extension($class, 'getPageTypes')) {
                $_pages = $_w::get_page_types();
                if (is_array($_pages) && count($_pages)) {
                    foreach ($_pages as $wd) {
                        $pages[] = $wd;
                    }
                }
                if (!is_array($pages)) {
                    $pages = [];
                }
            }
        }
        return $pages;
    }
    public static function get_extension_widgets($type)
    {
        $widgets = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if (($_w = self::check_extension($class, 'getWidgets')) && $_w::allowed()) {
                $_widgets = $_w::get_widgets($type);
                if (is_array($_widgets) && count($_widgets)) {
                    foreach ($_widgets as $wd) {
                        if (empty($wd['type'])) {
                            $wd['type'] = 'general';
                        }
                        $widgets[] = $wd;
                    }
                }
                if (!is_array($widgets)) {
                    $widgets = [];
                }
            }
        }
        return $widgets;
    }
    public static function get_extension_ep_data_sources()
    {
        $widgets = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if (!self::check_extension_allowed($class)) {
                continue;
            }
            if ($_w = self::check_extension($class, 'getEpDataSources')) {
                $_widgets = $_w::get_ep_data_sources();
                if (is_array($_widgets)) {
                    $widgets = array_merge($widgets, $_widgets);
                }
            }
        }
        return $widgets;
    }
    public static function get_extension_ep_providers()
    {
        $widgets = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if (!self::check_extension_allowed($class, 'enabled')) {
                continue;
            }
            if ($_w = self::check_extension($class, 'getEpProviders')) {
                $_widgets = $_w::get_ep_providers();
                if (is_array($_widgets)) {
                    $widgets = $widgets + $_widgets;
                }
            }
        }
        return $widgets;
    }
    public static function apply_extension_meta_tags($meta_tags)
    {
        $extensions = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensions as $ext) {
            $class = $ext->get_filename();
            if (!self::check_extension_allowed($class, 'enabled')) {
                continue;
            }
            if ($_w = self::check_extension($class, 'getMetaTagKeys')) {
                $_applied_tags = $_w::get_meta_tag_keys($meta_tags);
                if (is_array($_applied_tags) && count($_applied_tags) > 0) {
                    $meta_tags = $_applied_tags;
                }
            }
        }
        return $meta_tags;
    }
    public static function get_extension_create_images_settings()
    {
        $settings = [];
        $extensioins = new \Directory_Iterator(Yii::$aliases['@common'] . '/extensions/');
        foreach ($extensioins as $ext) {
            $class = $ext->get_filename();
            if ($_w = self::check_extension($class, 'getCreateImagesSettings')) {
                $_settings = $_w::get_create_images_settings();
                if (is_array($_settings) && count($_settings)) {
                    foreach ($_settings as $wd) {
                        $settings[] = $wd;
                    }
                }
                if (!is_array($settings)) {
                    $settings = [];
                }
            }
        }
        return $settings;
    }
    private static function _get_device_hash_string()
    {
        $str = '';
        foreach (self::DEVICE_HASH_SOURCES as $source) {
            if (isset($_SERVER[$source])) {
                $str .= $_SERVER[$source];
            }
        }
        $str .= \common\helpers\System::get_ip_address();
        return $str;
    }
    public static function save_manager_device_hash($login_id)
    {
        $str = '';
        //2DO problem with intranet devices as X_FORWARDED_FOR etc often is not set
        /* and cookies couldn't be set / checked for another domain.
                if (function_exists('random_int')) {
                  $salt = random_int(1000000, 9999999);
                } else {
                  $salt = rand(1000000, 9999999);
                }
        
                $q = tep_db_query( "select p.* from platforms p where p.status=1 and p.is_virtual=0" );
                while ($platform = tep_db_fetch_array($q)) {
                  $parsed = parse_url('http://' . $platform['platform_url'] . '/');
                  \common\helpers\System::setcookie(self::COOKIE_SALT, $salt, 0, '/', $parsed['host']);
                }
                $tmp = \common\helpers\System::getcookie(self::COOKIE_SALT);
                if ($tmp == $salt) {
                  $str .= $salt;
                }*/
        $str .= self::_get_device_hash_string();
        $str = md5($str);
        tep_db_query('update ' . TABLE_ADMIN . " set device_hash = '" . tep_db_input($str) . "' where admin_id = '" . (int) $login_id . "'");
        return $str;
    }
    public static function check_rule_by_device_hash($rule)
    {
        $ret = false;
        $str = '';
        //$str = \common\helpers\System::getcookie(self::COOKIE_SALT);
        $str .= self::_get_device_hash_string();
        // not defined on frontend " . TABLE_ADMIN . "
        $check_admin = tep_db_query('select a.admin_persmissions, al.access_levels_persmissions from admin a join ' . TABLE_ACCESS_LEVELS . " al on a.access_levels_id=al.access_levels_id where device_hash = '" . tep_db_input(md5($str)) . "' order by admin_logdate DESC");
        if (tep_db_num_rows($check_admin) > 0) {
            // admin's permissions override AL permissions (>0 allow <0 forbid)
            $admin = tep_db_fetch_array($check_admin);
            $al_ids = explode(',', (string) $admin['access_levels_persmissions']);
            $admin_persmissions = !empty($admin['admin_persmissions']) ? explode(',', $admin['admin_persmissions']) : [];
            $a = $r = [];
            foreach ($admin_persmissions as $v) {
                if (!empty($v)) {
                    if ($v > 0) {
                        $a[] = $v;
                    } else {
                        $r[] = abs($v);
                    }
                }
            }
            $rule_ids = array_diff(array_merge($al_ids, $a), $r);
            $ret = self::rule($rule, 0, $rule_ids);
        }
        return $ret;
    }
    /*
     * run extension widget
     */
    public static function run_extension_widget($widge_name, $widget_array)
    {
        if (($ext_widget = self::check_extension($widge_name, 'run', true)) && (!method_exists($ext_widget, 'allowed') || $ext_widget::allowed())) {
            $widget_array = array_merge($widget_array, ['name' => $widge_name]);
            return $ext_widget::widget($widget_array);
        } else {
            return false;
        }
    }
    public static function is_frontend_translation()
    {
        static $status;
        if (!is_bool($status) && \Yii::$app instanceof \yii\console\Application) {
            $status = false;
        }
        if (isset($status)) {
            return $status;
        }
        if (defined('DIR_WS_ADMIN')) {
            $status = false;
            return false;
        }
        $admin = \common\models\Admin::find()->where(['device_hash' => md5(self::_get_device_hash_string()), 'frontend_translation' => '1'])->cache(self::ACL_CACHE_LIFETIME)->as_array()->one();
        if (!$admin) {
            $status = false;
            return false;
        }
        $cookies = Yii::$app->request->cookies;
        if ($admin && $cookies->has('frontend_translation')) {
            return true;
        }
        if (!strpos(Yii::$app->request->headers['referer'], '/admin/texts')) {
            return false;
        }
        if (\common\helpers\Acl::check_rule_by_device_hash(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_TRANSLATION_TEXTS'])) {
            $status = true;
        } else {
            $status = false;
        }
        if ($status) {
            $access_levels = \common\models\Access_Levels::find()->where(['access_levels_id' => $admin['access_levels_id']])->as_array()->one();
            $cookies = Yii::$app->response->cookies;
            $cookies->add(new \yii\web\Cookie(['name' => 'frontend_translation', 'value' => $access_levels['access_levels_persmissions'], 'expire' => time() + 300]));
        }
        return $status;
    }
}