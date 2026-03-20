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

class Extensions
{
    private static $cache_allowed = [];
    private static $cache_enabled = [];
    // cache for Acl::checkXXX instead of const 'ext_EXTENSION_STATUS'
    /**
     * Returns extension class if it exists and is allowed
     * @param string $code extension classname
     * @return bool|mixed|string
     */
    public static function is_allowed(string $code)
    {
        return self::allowed($code);
    }
    /**
     * return extension class if allowed and $func return true
     * @param string $code
     * @param string $func
     * @param array $args
     * @return bool|mixed|string
     */
    public static function is_allowed_and(string $code, string $func, array $args = null)
    {
        if (($ext = self::is_allowed($code)) && (method_exists($ext, $func) && call_user_func([$ext, $func], $args) || method_exists($ext, 'cfg') && class_exists($cfg_class = $ext::cfg()) && method_exists($cfg_class, $func) && call_user_func([$cfg_class, $func], $args))) {
            return $ext;
        }
        return false;
    }
    public static function is_allowed_and_method_exist(string $code, string $func)
    {
        if (($ext = self::is_allowed($code)) && method_exists($ext, $func)) {
            return $ext;
        }
        return false;
    }
    /**
     * Calls $func if extension $code is allowed
     * @param string $code - extension classname
     * @param string $func - static extension function name
     * @param array $args - args for function $func
     * @return false|mixed - false if extension is not allowed or return value of $func
     */
    public static function call_if_allowed(string $code, string $func, array $args = [])
    {
        if (($ext = self::is_allowed($code)) && method_exists($ext, $func)) {
            return call_user_func_array([$ext, $func], $args);
        }
        return false;
    }
    private static function allowed(string $code, $func = 'allowed')
    {
        if ($func == 'allowed') {
            if (!isset(self::$cache_allowed[$code])) {
                self::$cache_allowed[$code] = Acl::check_extension_allowed($code);
            }
            return self::$cache_allowed[$code];
        }
        return Acl::check_extension_allowed($code, $func);
    }
    /*
     * @return null|common\extensions\UserGroups\UserGroups
     */
    public static function is_customer_groups_allowed()
    {
        return self::is_allowed('UserGroups');
    }
    /*
     * @return null|common\extensions\CronScheduler\CronScheduler
     */
    public static function is_cron_scheduler($func_name = null)
    {
        $ext = self::is_allowed('CronScheduler');
        if ($ext && (empty($func_name) || method_exists($ext, $func_name))) {
            return $ext;
        }
    }
    /*
     * @return null|common\extensions\Inventory\Inventory
     */
    public static function is_inventory_allowed()
    {
        return self::is_allowed('Inventory');
    }
    private static function get_state($code)
    {
        if (!isset(self::$cache_enabled[$code])) {
            $row = \common\models\Platforms_Configuration::find_one(['configuration_key' => $code . '_EXTENSION_STATUS', 'platform_id' => 0]);
            if (empty($row) || !class_exists("\\common\\extensions\\{$code}\\{$code}")) {
                self::$cache_enabled[$code] = 'uninstalled';
            } elseif ($row->configuration_value == 'True') {
                self::$cache_enabled[$code] = 'enabled';
            } else {
                self::$cache_enabled[$code] = 'disabled';
            }
        }
        return self::$cache_enabled[$code];
    }
    public static function is_enabled($code)
    {
        return self::get_state($code) == 'enabled';
    }
    public static function is_installed($code)
    {
        return in_array(self::get_state($code), ['enabled', 'disabled']);
    }
    public static function is_uninstalled($code)
    {
        return self::get_state($code) == 'uninstalled';
    }
    public static function is_disabled($code)
    {
        return self::get_state($code) == 'disabled';
    }
    public static function clear_cache($code = null)
    {
        if (empty($code)) {
            self::$cache_enabled = [];
            self::$cache_allowed = [];
        } else {
            unset(self::$cache_enabled[$code]);
            unset(self::$cache_allowed[$code]);
        }
    }
    /**
     * @param $code - extension classname
     * @return null|string null - success, string - error message
     */
    public static function install_safe($code)
    {
        try {
            self::install($code);
        } catch (\Exception $e) {
            \Yii::error(sprintf("%s: %s\n%s", __FUNCTION__, $e->get_message(), $e->get_trace_as_string()));
            return $e->get_message();
        }
    }
    public static function install($code)
    {
        $ext = \common\helpers\Acl::check_extension($code, 'allowed');
        if (!$ext) {
            throw new \Exception("Extension {$code} not found");
        }
        if (self::is_allowed($code)) {
            throw new \Exception("Extension {$code} already installed");
        }
        $obj = new $ext();
        $obj->install(0);
        $obj->enable_module(0, true);
        self::clear_cache($code);
    }
    /**
     * @param $code - extension classname
     * @param bool $forceIfUninstalled set true to call remove method even if extension is already uninstalled
     * @param array|null $options uninstall options ['userConfirmedDropDatatables', 'userConfirmedDeleteAcl']
     * @return null|string null - success, string - error message
     */
    public static function uninstall($code, $force_if_uninstalled = false, $options = null)
    {
        $ext = self::is_allowed($code);
        if (!$ext) {
            if ($force_if_uninstalled) {
                $ext = \common\helpers\Acl::check_extension($code, 'enabled');
                if (!$ext) {
                    throw new \Exception("Extenstion {$code} is not exist on the disk");
                }
            } else {
                throw new \Exception("Extenstion {$code} is not installed");
            }
        }
        $obj = new $ext();
        if (is_array($options)) {
            foreach ($options as $option) {
                if (!property_exists($obj, $options)) {
                    throw new \Exception("Property {$option} is not exists in extenstion {$code}");
                }
                $obj->{$options} = true;
            }
        }
        $obj->remove(0);
        $obj->enable_module(0, false);
        self::clear_cache($code);
    }
    /**
     * @param $code - extension classname
     * @param bool $forceIfUninstalled set true to call remove method even if extension is already uninstalled
     * @param array|null $options uninstall options
     * @return null|string null - success, string - error message
     */
    public static function uninstall_safe($code, $force_if_uninstalled = false, $options = null)
    {
        try {
            self::uninstall($code, $force_if_uninstalled, $options);
        } catch (\Exception $e) {
            \Yii::error(sprintf("%s: %s\n%s", __FUNCTION__, $e->get_message(), $e->get_trace_as_string()));
            return $e->get_message();
        }
    }
    public static function get_base_dir_relative($code)
    {
        return 'lib/common/extensions/' . $code;
    }
    /**
     * Get image file name for extension $code
     * @param $code - extension class
     * @param $imageFN - base image file name like 'image1.png'
     * @param $defImageFN - path to default image
     * @return null|string
     */
    public static function get_image_relative($code, $image_fn, $def_image_fn = null)
    {
        $base_dir = self::get_base_dir_relative($code) . '/';
        if (file_exists(\Yii::get_alias('@site_root/' . $res = $base_dir . 'images/' . $image_fn))) {
            return $res;
        } elseif (file_exists(\Yii::get_alias('@site_root/' . $res = $base_dir . $image_fn))) {
            return $res;
        } else {
            return $def_image_fn;
        }
    }
    /**
     * @param string $class className of extension
     * @param string $relativeModelName 'models\Collections' or just 'Collections'
     * @param string|null $allowedFunc
     * @return \yii\db\ActiveRecord|null
     */
    public static function get_model($class, $relative_model_name, $allowed_func = 'allowed')
    {
        /** @var \common\classes\modules\ModuleExtensions $ext */
        if ($ext = self::allowed($class, 'enabled')) {
            if (method_exists($ext, 'getModel') && ($model = $ext::get_model($relative_model_name)) && class_exists($model)) {
                return $model;
            }
            if (!empty($allowed_func) && !(method_exists($ext, $allowed_func) && call_user_func([$ext, $allowed_func]))) {
                return null;
            }
            $reflection_class = new \ReflectionClass($ext);
            $namespace = $reflection_class->get_namespace_name();
            $model_class = $namespace . "\\{$relative_model_name}";
            if (!class_exists($model_class) || $class == $relative_model_name) {
                $model_class = $namespace . "\\models\\{$relative_model_name}";
            }
            if (class_exists($model_class) && \Yii::$app->db->schema->get_table_schema($model_class::tablename()) !== null) {
                return $model_class;
            }
        }
    }
    /**
     * Check extensions for hide or show in "Modules Restrictions Visibility on pages"
     * @param $visibilityConstant string
     * @return bool
     */
    public static function is_visibility($visibility_constant)
    {
        $const = ['Quotations' => ['TEXT_EMAIL_QUOTE', 'TEXT_QUOTE_CART', 'TEXT_QUOTE_CHECKOUT'], 'Samples' => ['TEXT_EMAIL_SAMPLE']];
        foreach ($const as $extension => $constants) {
            if (in_array($visibility_constant, $constants)) {
                return self::is_allowed($extension);
            }
        }
        return true;
    }
    /**
     * Check extensions and POS available for "Modules Restrictions Available for"
     * @param $variant string
     * @return bool
     */
    public static function is_visibility_variant($variant)
    {
        $ext_variants = ['shop_quote' => 'Quotations', 'shop_sample' => 'Samples', 'moderator' => 'GroupAdministrator'];
        if ($variant == 'pos') {
            return self::is_pos_exist();
        }
        if (!empty($ext_variants[$variant])) {
            return self::is_allowed($ext_variants[$variant]);
        }
        return true;
    }
    /**
     * Get correct visibility variants for "Modules Restrictions Available for"
     * @param $variants array | string
     * @return array
     * @uses isVisibilityVariant() for check available extensions and POS
     */
    public static function get_visibility_variants($variants)
    {
        $result = [];
        if (is_array($variants)) {
            foreach ($variants as $variant) {
                if (self::is_visibility_variant($variant)) {
                    $result[] = $variant;
                }
            }
        } else if (self::is_visibility_variant($variants)) {
            $result[] = $variants;
        }
        return $result;
    }
    public static function is_pos_exist()
    {
        return file_exists(\Yii::get_alias('@pos'));
    }
    public static function check_setup($ext_class, $setup_func_name = null)
    {
        if (($ext = self::is_allowed($ext_class)) && method_exists($ext, 'checkSetup')) {
            return $ext::check_setup($setup_func_name);
        }
        return false;
    }
    public static function get_overwritten_cfg_keys()
    {
        static $keys_all = null;
        if (is_null($keys_all)) {
            $keys_all = \Yii::$app->get_cache()->get_or_set('overwritten-config-keys', function () {
                $res = [];
                $extensions = new \Directory_Iterator(\Yii::$aliases['@common'] . '/extensions/');
                foreach ($extensions as $ext_file) {
                    $class = $ext_file->get_filename();
                    if (method_exists(self::class, 'checkSetup') && $setup = self::check_setup($class, 'getOverwrittenCfgKeys')) {
                        $keys = $setup::get_overwritten_cfg_keys();
                        if (is_array($keys) && count($keys) > 0) {
                            foreach ($keys as &$arr) {
                                $arr['extension'] = $class;
                            }
                            $res = array_merge($res, $keys);
                        }
                    }
                }
                return $res;
            }, 0, new \yii\caching\Tag_Dependency(['tags' => ['extension_changed']]));
        }
        return $keys_all;
    }
    public static function get_overwritten_cfg_key($config_key)
    {
        $res = self::get_overwritten_cfg_keys()[$config_key] ?? null;
        if (is_array($res) && !isset($res['value'])) {
            $class = $res['extension'];
            $default_value = '<a href="%s">%s</a>';
            \common\helpers\Translation::init('configuration');
            $default_caption = defined('TEXT_EXTENSION_OVERWRITE_CONFIG_KEY') ? TEXT_EXTENSION_OVERWRITE_CONFIG_KEY : 'The extension <strong>%s</strong> enhances this option</a>';
            $url = \Yii::$app->url_manager->create_url(['modules/edit', 'set' => 'extensions', 'module' => $class]);
            $caption = isset($arr['caption']) ? $arr['caption'] : sprintf($default_caption, $class);
            $res['value'] = sprintf($default_value, $url, $caption);
        }
        return $res;
    }
}