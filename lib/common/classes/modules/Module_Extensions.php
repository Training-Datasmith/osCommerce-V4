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
namespace common\classes\modules;

use common\classes\platform;
class Module_Extensions extends Module
{
    public $is_extension = true;
    public $user_confirmed_drop_datatables = false;
    // check this field in remove function if extention is able to drop own datatables into isAbleToDropDatatables()
    public $assign_to_access_levels = 1;
    public $user_confirmed_delete_acl = false;
    public function __construct()
    {
        $ref = new \ReflectionClass(get_called_class());
        $this->code = $ref->get_short_name();
        $this->namespace = $ref->get_namespace_name();
        $this->title = \yii\helpers\Inflector::camel2words($this->code);
        static::init_translation_array('init_constructor');
    }
    public static function allowed()
    {
        if (self::enabled()) {
            static::init_translation_array('init_always');
            return true;
        }
        return false;
    }
    public static function check_setup($method_name = null)
    {
        $reflect = new \ReflectionClass(static::class);
        $class_name = $reflect->get_namespace_name() . '\Setup';
        if (!class_exists($class_name)) {
            return null;
        }
        if (empty($method_name) || is_string($method_name) && method_exists($class_name, $method_name)) {
            return $class_name;
        }
        if (is_array($method_name)) {
            foreach ($method_name as $name) {
                if (!method_exists($class_name, $name)) {
                    return null;
                }
            }
            return $class_name;
        }
    }
    public static function get_description()
    {
        if ($setup = static::check_setup('getDescription')) {
            return $setup::get_description();
        }
        return parent::get_description();
    }
    public static function get_version_history()
    {
        if ($setup = static::check_setup('getVersionHistory')) {
            return $setup::get_version_history();
        }
        return parent::get_version_history();
    }
    public static function get_version()
    {
        if ($setup = static::check_setup('getVersion')) {
            return $setup::get_version();
        }
        return parent::get_version();
    }
    /**
     * @return null|false|object (stdClass)
     * object - since v4.09 + AppShop application created after v4.09
     */
    public static function get_distrib_obj()
    {
        $fn = static::get_ext_dir() . '/distribution.json';
        if (file_exists($fn) && $text = file_get_contents($fn)) {
            return json_decode($text);
        }
    }
    /**
     * @return void|null|string - string is application revision
     */
    public static function get_revision()
    {
        $distrib_obj = self::get_distrib_obj();
        if (is_object($distrib_obj)) {
            return $distrib_obj->revision ?? null;
        }
    }
    protected static function get_translation_array()
    {
        if ($setup = static::check_setup('getTranslationArray')) {
            return $setup::get_translation_array();
        }
        return [];
    }
    public static function get_translation_value_own($key, $entity = null, $default = '##key##')
    {
        static $arr = null;
        if (is_null($arr)) {
            $arr = self::get_translation_array();
            if (!is_array($arr)) {
                $arr = [];
            }
        }
        if (!empty($entity) && isset($arr[$entity][$key])) {
            return $arr[$entity][$key];
        } else {
            foreach ($arr as $translations) {
                if (isset($translations[$key])) {
                    return $translations[$key];
                }
            }
        }
        if ($default == '##key##') {
            return $key;
        }
        return $default;
    }
    public static function get_translation_value($key, $entity = '')
    {
        $res = \common\helpers\Translation::get_value($key, $entity, null);
        if (!$res) {
            $res = self::get_translation_value_own($key, $entity);
        }
        return $res;
    }
    public static function get_translated_array(array $translation_keys)
    {
        $res = [];
        foreach ($translation_keys as $val) {
            $res[$val] = static::get_translation_value($val);
        }
        return $res;
    }
    public static function get_admin_menu()
    {
        if ($setup = static::check_setup('getAdminMenu')) {
            return $setup::get_admin_menu();
        }
        return [];
    }
    public static function get_required_modules()
    {
        if ($setup = static::check_setup('getRequiredModules')) {
            return $setup::get_required_modules();
        }
    }
    public static function get_admin_hooks()
    {
        if ($setup = static::check_setup('getAdminHooks')) {
            return $setup::get_admin_hooks();
        }
    }
    public static function get_widgets($type = 'general')
    {
        if (!self::allowed()) {
            return '';
        }
        if ($setup = static::check_setup('getWidgets')) {
            $res = $setup::get_widgets($type);
            if (empty($res)) {
                return '';
            } else {
                static::init_translation('init_widget');
                foreach ($res as &$wd) {
                    if (isset($wd['type']) && empty($wd['type'])) {
                        $wd['type'] = 'general';
                    }
                }
                return $res;
            }
        }
    }
    public static function get_pages()
    {
        if (!self::allowed()) {
            return '';
        }
        if ($setup = static::check_setup('getPages')) {
            $res = $setup::get_pages();
            return $res;
        }
    }
    public static function show_settings($settings)
    {
    }
    public static function get_ep_data_sources()
    {
        if ($setup = static::check_setup('getEpDataSources')) {
            return $setup::get_ep_data_sources();
        }
    }
    public static function get_ep_providers()
    {
        if ($setup = static::check_setup('getEpProviders')) {
            return $setup::get_ep_providers();
        }
    }
    public function enable_module($platform_id, $flag)
    {
        $res = parent::enable_module($platform_id, $flag);
        if ($res !== false) {
            \yii\caching\Tag_Dependency::invalidate(\Yii::$app->cache, 'extension_changed');
        }
        return $res;
    }
    /* Not needed for overriding if Setup::install is implemented */
    public function install($platform_id)
    {
        try {
            $migrate = new \common\classes\Migration();
            $migrate->compact = true;
            self::install_translation_array($platform_id, $migrate);
            $this->append_acl($platform_id, $migrate);
            \common\helpers\Menu_Helper::create_admin_menu_items(static::get_admin_menu());
            if ($setup = static::check_setup('install')) {
                $installed = self::get_installed();
                if (!empty($installed->version_db ?? null)) {
                    \Yii::warning("Extension already exists in DB. Ver={$installed->version} VerDB={$installed->version_db}");
                }
                $setup::install($platform_id, $migrate);
            }
            \common\helpers\Hooks::register_hooks($this->get_admin_hooks(), $this->code);
            \backend\design\Style::validate_cache();
            self::install_cron_jobs();
            $res = parent::install($platform_id);
            \yii\caching\Tag_Dependency::invalidate(\Yii::$app->cache, 'extension_changed');
            return $res;
        } catch (\Exception $ex) {
            \Yii::warning($ex->get_message() . ' ' . $ex->get_trace_as_string(), "Extensions/{$this->code}");
            throw $ex;
        }
    }
    /* Not needed for overriding if Setup::remove is implemented */
    public function remove($platform_id)
    {
        try {
            $migrate = new \common\classes\Migration();
            $migrate->compact = true;
            if ($setup = static::check_setup('remove')) {
                $setup::remove($platform_id, $migrate, $this->user_confirmed_drop_datatables);
            }
            if ($this->user_confirmed_drop_datatables && $setup = static::check_setup('getDropDatabasesArray')) {
                $migrate->drop_tables($setup::get_drop_databases_array());
                self::remove_cron_jobs();
                \common\helpers\Modules::change_module($this->code, 'remove_drop');
            }
            if ($this->user_confirmed_delete_acl && $setup = static::check_setup('getAclArray')) {
                self::drop_acl($platform_id, $migrate);
            }
            \common\helpers\Menu_Helper::remove_admin_menu_items(static::get_admin_menu());
            static::remove_translation_array($platform_id, $migrate, $this->user_confirmed_delete_acl);
            // after remove menu
            \common\helpers\Hooks::unregister_hooks($this->code);
            $res = parent::remove($platform_id);
            \common\helpers\Modules::change_module($this->code, 'remove');
            \yii\caching\Tag_Dependency::invalidate(\Yii::$app->cache, 'extension_changed');
            return $res;
        } catch (\Exception $ex) {
            \Yii::warning($ex->get_message() . ' ' . $ex->get_trace_as_string(), "Extensions/{$this->code}");
            throw $ex;
        }
    }
    public static function is_able_to_delete_acl()
    {
        return boolval(static::check_setup(['getAclArray']));
    }
    public static function is_able_to_drop_datatables()
    {
        return boolval(static::check_setup(['getDropDatabasesArray']));
    }
    /* Not needed for overriding if Setup::getAclArray is implemented */
    public static function acl()
    {
        if ($setup = static::check_setup('getAclArray')) {
            $acl = $setup::get_acl_array();
            $acl_default = $acl['default'] ?? $acl[0] ?? [];
            $action = \Yii::$app->request->get('action', '');
            if (!empty($action)) {
                return $acl[$action] ?? $acl_default;
            }
            return $acl_default;
        }
        return [];
    }
    public function describe_status_key()
    {
        return new Module_Status($this->code . '_EXTENSION_STATUS', 'True', 'False');
    }
    public function describe_sort_key()
    {
    }
    public function configure_keys()
    {
        $keys0 = [$this->code . '_EXTENSION_STATUS' => ['title' => $this->title . ' status', 'value' => 'False', 'set_function' => 'tep_cfg_select_option(array(\'True\', \'False\'), ']];
        $keys = $this->get_configure_keys_area(true, 'restrictions', 'platform');
        if (is_array($keys)) {
            $keys0 = array_merge($keys0, $keys);
        }
        return $keys0;
    }
    public function configure_keys_platforms()
    {
        return $keys = $this->get_configure_keys_area(false, 'platforms');
    }
    public function add_platform_key($platform_id, $key, $data)
    {
        $this->add_config_key($platform_id, $key, $data);
    }
    public static function get_setup_configure_keys()
    {
        if ($setup = static::check_setup('getConfigureKeys')) {
            $keys = $setup::get_configure_keys(self::get_module_code());
            if (is_array($keys)) {
                array_walk($keys, function (&$value, $key) {
                    foreach (['title', 'description'] as $key_item) {
                        if (isset($value[$key_item]) && preg_match('/##([\w_\-]*)##/', $value[$key_item], $match)) {
                            $value[$key_item] = static::get_translation_value($match[1]);
                        }
                    }
                });
                return $keys;
            }
        }
    }
    public function get_configure_keys_area(bool $include_empty_area = true, $include_area = null, $exclude_area = null)
    {
        $keys = self::get_setup_configure_keys();
        if (is_array($keys) && !empty($keys)) {
            $include_area = is_array($include_area) ? $include_area : explode(',', $include_area ?? '');
            $exclude_area = is_array($exclude_area) ? $exclude_area : explode(',', $exclude_area ?? '');
            return array_filter($keys, function ($value) use ($include_empty_area, $include_area, $exclude_area) {
                if (empty($value['area'])) {
                    return $include_empty_area;
                } else {
                    return in_array($value['area'], $include_area) && !in_array($value['area'], $exclude_area);
                }
            });
        }
    }
    public static function enabled()
    {
        $class = (new \ReflectionClass(get_called_class()))->get_short_name();
        if (class_exists('\common\helpers\Extensions')) {
            return \common\helpers\Extensions::is_enabled($class);
        } else {
            // issue: system update && uninstall && resetMenu
            // but can't remove because of include platforms into application_top
            return defined($class . '_EXTENSION_STATUS') && constant($class . '_EXTENSION_STATUS') == 'True';
        }
    }
    public static function get_meta_tag_keys($meta_tags)
    {
        if (($setup = static::check_setup('getMetaTagKeys')) && self::allowed()) {
            return $setup::get_meta_tag_keys($meta_tags);
        } else {
            return $meta_tags;
        }
    }
    /**
     * Attach actions for the controller.
     *
     * ```php
     * return [
     *     'action1' => '\common\extensions\Extension\Extension\actions\backend\Action1',
     *     'action2' => [
     *         'class' => '\common\extensions\Extension\Extension\actions\frontend\Action2',
     *         'property1' => 'value1',
     *         'property2' => 'value2',
     *     ],
     * ];
     * ```
     *
     * @param $controllerId
     * @return array
     */
    public static function get_controller_actions($controller_id)
    {
        // used enabled to prevent init translations
        if (self::enabled() && $setup = static::check_setup('getControllerActions')) {
            return $setup::get_controller_actions($controller_id);
        }
        return [];
    }
    public static function get_cron_jobs(bool $check_allowed = true)
    {
        if ((!$check_allowed || self::allowed()) && $setup = static::check_setup('getCronJobs')) {
            return $setup::get_cron_jobs();
        }
        return [];
    }
    private static function install_cron_jobs()
    {
        if ($cron_sheduler = \common\helpers\Extensions::is_allowed('CronScheduler')) {
            $jobs = \common\helpers\Cron::get_extension_jobs(self::get_module_code(), false);
            if (is_array($jobs)) {
                foreach ($jobs as $job) {
                    if ($job['active'] ?? false) {
                        $cron_sheduler::add_job($job);
                    }
                }
            }
        }
    }
    private static function remove_cron_jobs()
    {
        if ($cron_sheduller = \common\helpers\Extensions::is_cron_scheduler('removeJobsExtension')) {
            $cron_sheduller::remove_jobs_extension(static::get_module_code());
        }
    }
    private function append_acl($platform_id, \common\classes\Migration $migrate)
    {
        if ($setup = static::check_setup('getAclArray')) {
            $acl_array = $setup::get_acl_array();
            if (is_array($acl_array) && count($acl_array) > 0) {
                foreach ($acl_array as $key => $acl) {
                    $migrate->append_acl($acl, $this->assign_to_access_levels);
                }
            }
        }
    }
    private static function drop_acl($platform_id, $migrate)
    {
        if ($setup = static::check_setup('getAclArray')) {
            $acl_array = $setup::get_acl_array();
            if (is_array($acl_array) && count($acl_array) > 0) {
                // place parent keys in the end to delete their later
                uasort($acl_array, function ($a, $b) {
                    return count($b) <=> count($a);
                });
                foreach ($acl_array as $key => $acl) {
                    $migrate->drop_acl($acl);
                }
                if (isset($acl_array['default'])) {
                    $migrate->drop_acl($acl_array['default']);
                }
            }
        }
    }
    /**
     * get Acl chain for entity/action
     * @param string $action - name of action
     * @param string $entity - 'extension' or 'ControllerClassName'
     * @param bool $matchSimilar = false - checks only full match $entity/$action
     *                           = true - if $entity/$action not found, checks keys: $entity/$action, $entity, 'default'
     * @return null|array
     */
    public static function get_acl(string $action = '', string $entity = 'extension', bool $match_similar = true)
    {
        if ($setup = static::check_setup('getAclArray')) {
            $acl_array = $setup::get_acl_array();
            $action_suffix = empty($action) ? '' : "/{$action}";
            foreach (["{$entity}{$action_suffix}", $entity, $action, 'default'] as $key) {
                if (!empty($key) && isset($acl_array[$key])) {
                    return $acl_array[$key];
                } elseif (!$match_similar) {
                    return null;
                }
            }
        }
    }
    protected static function install_translation_array($platform_id, $migrate)
    {
        $translation_array = static::get_translation_array();
        if (is_array($translation_array) && count($translation_array) > 0) {
            foreach ($translation_array as $entity => $keys_array) {
                if (static::is_process_translation_pair($entity, $keys_array, 'install')) {
                    $without_magic_keys = array_filter($keys_array, function ($key) {
                        return is_string($key) && substr($key, 0, 2) != '__';
                    }, ARRAY_FILTER_USE_KEY);
                    $migrate->add_translation($entity, $without_magic_keys, true);
                }
            }
        }
    }
    /**
     * @param $platform_id int 0
     * @param $migrate \common\classes\Migration
     * @param bool $fullDel
     * @return void
     */
    protected static function remove_translation_array($platform_id, $migrate, bool $full_del = false)
    {
        $translation_array = static::get_translation_array();
        if (is_array($translation_array) && count($translation_array) > 0) {
            foreach ($translation_array as $entity => $keys_array) {
                if (static::is_process_translation_pair($entity, $keys_array, 'remove_entity')) {
                    $migrate->remove_translation($entity);
                } elseif (static::is_process_translation_pair($entity, $keys_array, 'remove_keys')) {
                    $keys = array_keys($keys_array);
                    if (!empty($keys)) {
                        // don't remove entity for empty array
                        $migrate->remove_translation($entity, $keys);
                    }
                } elseif ($full_del && static::is_process_translation_pair($entity, $keys_array, 'remove_keys_if_acl_removing')) {
                    $keys = array_keys($keys_array);
                    if (!empty($keys)) {
                        // don't remove entity for empty array
                        if ($entity = 'admin/main') {
                            $used_keys = \common\models\Admin_Boxes::find()->where(['title' => $keys])->select('title')->column();
                            if (!empty($used_keys)) {
                                $keys = array_diff($keys, $used_keys);
                            }
                        }
                        $migrate->remove_translation($entity, $keys);
                    }
                }
            }
        }
    }
    //it's protected for overriding, not for using
    //use ::initTranslation() for custom initializing
    protected static function init_translation_array($from = 'init_directcall')
    {
        if (!\common\helpers\System::is_yii_loaded()) {
            return;
        }
        if (is_bool($from)) {
            $from = 'init_directcall';
        }
        $transl = static::get_translation_array();
        if (is_array($transl)) {
            // make sure 'main' gets initialized first
            foreach ($transl as $entity => $keys_array) {
                if (in_array($entity, ['main', 'admin/main'])) {
                    if (static::is_process_translation_pair($entity, $keys_array, $from)) {
                        \common\helpers\Translation::init($entity);
                    }
                }
            }
            foreach ($transl as $entity => $keys_array) {
                if (static::is_process_translation_pair($entity, $keys_array, $from)) {
                    \common\helpers\Translation::init($entity);
                }
            }
        }
    }
    public static function init_translation($from = 'init_directcall')
    {
        static::init_translation_array($from);
    }
    private const TRANSLATION_KEYS_DEF_OPERATIONS = [
        'main' => ['install', 'remove_keys_if_acl_removing'],
        // 'main' and 'admin/main' keys
        'extension' => ['install', 'remove_entity', 'init_always', 'init_controller', 'init_beforeaction', 'init_widget', 'init_directcall'],
        // extension keys
        'external' => ['install', 'init_always', 'init_controller', 'init_beforeaction', 'init_widget', 'init_directcall'],
        'other' => ['install', 'remove_keys', 'init_always', 'init_controller', 'init_beforeaction', 'init_widget', 'init_directcall'],
    ];
    private static function is_process_translation_pair($key, $value, $operation)
    {
        $config = $value['__config__'] ?? self::get_default($key, $value);
        return in_array($operation, $config);
    }
    private static function get_default($key, $value)
    {
        if (isset($value['__config_as__'])) {
            if (is_string($value['__config_as__']) && isset(self::TRANSLATION_KEYS_DEF_OPERATIONS[$value['__config_as__']])) {
                return self::TRANSLATION_KEYS_DEF_OPERATIONS[$value['__config_as__']];
            } else {
                \Yii::warning('Wrong __config_as__ value: ' . var_export($value['__config_as__'], true));
            }
        }
        if (in_array($key, ['main', 'admin/main'])) {
            return self::TRANSLATION_KEYS_DEF_OPERATIONS['main'];
        } elseif (\common\helpers\Php8::str_start_with($key, 'extensions/')) {
            return self::TRANSLATION_KEYS_DEF_OPERATIONS['extension'];
        } elseif (isset($value['__external__']) && !\common\helpers\Extensions::is_uninstalled($value['__external__']['extension'])) {
            return self::TRANSLATION_KEYS_DEF_OPERATIONS['external'];
        } else {
            return self::TRANSLATION_KEYS_DEF_OPERATIONS['other'];
        }
    }
    /**
     * @param $migrate \common\classes\Migration
     */
    public static function reinstall_translation($migrate)
    {
        static::remove_translation_array(null, $migrate, true);
        static::install_translation_array(null, $migrate);
    }
    /*
     * Developer can use it to update translation constants by URL:
     * https://localhost/admin/extensions?module=YourExt&action=actionRefreshTranslation
     */
    public static function action_refresh_translation()
    {
        if (\common\helpers\System::is_development()) {
            $migrate = new \common\classes\Migration();
            $migrate->compact = true;
            static::reinstall_translation($migrate);
            echo 'translation reinstalled<br>';
        }
    }
    public static function get_ext_dir()
    {
        return self::get_base_dir();
    }
    public static function get_base_dir()
    {
        return \Yii::get_alias('@site_root/' . self::get_base_dir_relative());
    }
    public static function get_base_dir_relative()
    {
        return \common\helpers\Extensions::get_base_dir_relative(self::get_module_code());
    }
    public static function get_image_relative($image_fn, $def_image_relative_fn = null)
    {
        return \common\helpers\Extensions::get_image_relative(self::get_module_code(), $image_fn, $def_image_relative_fn);
    }
    private static function get_view_file($view)
    {
        return '@common/extensions/' . self::get_module_code() . '/views/' . $view;
    }
    public static function render($view, $params = [])
    {
        $params['_extension_render'] = static::get_module_code();
        $html = Render_Extensions::widget(['template' => self::get_view_file($view), 'params' => $params]);
        return \Yii::$app->controller->render_content($html);
    }
    public static function render_ajax($view, $params = [])
    {
        $params['_extension_render'] = static::get_module_code();
        return Render_Extensions::widget(['template' => self::get_view_file($view), 'params' => $params]);
    }
    public static function render_content($view, $params = [])
    {
        return self::render($view, $params);
    }
    /*
     * If extension store its model in non standard folder.
     * For example UsersGroups extensions and its models into common/models folder
     * @return null|string - yii\db\ActiveRecord class
     */
    public static function get_model($model_name)
    {
    }
    /**
     * Get Dbg helper if consts DBG_ExtClassName === true
     * @return \common\helpers\Dbg
     */
    public static function dbg()
    {
        return \common\helpers\Dbg::if_defined(self::get_module_code());
    }
    /**
     * Get config value that
     * @param string $name
     * @return void|mixed void - in production mode if if config name is not found in Setup::getConfigureKeys
     * @throws Exception in development mode if config name is not found in Setup::getConfigureKeys
     */
    public static function get_cfg_value(string $name, $platform_id = null)
    {
        try {
            $cfg_keys = self::get_setup_configure_keys();
            \common\helpers\Assert::key_exists($cfg_keys, $name, "The config key {$name} is not found in Setup::getConfigureKeys: %s");
            $platform_id = ($cfg_keys[$name]['area'] ?? null) == 'platforms' ? $platform_id ?? platform::current_id() : 0;
            $def_value = $cfg_keys[$name]['value'] ?? '';
            return \common\helpers\Platform_Config::get_val($name, $def_value, $platform_id);
        } catch (\Throwable $e) {
            \common\helpers\Php::handle_error_prod($e);
        }
    }
    public static function get_cfg_value_lower(string $name, $platform_id = null)
    {
        return strtolower(self::get_cfg_value($name, $platform_id));
    }
    public static function before_action($action)
    {
        return true;
    }
}