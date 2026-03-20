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
namespace common\components\google;

use common\models\repositories\Google_Settings_Repository;
class Module_Provider extends Providers
{
    private static $modules = ['analytics', 'adwords', 'ecommerce', 'verification', 'tagmanger', 'reviews', 'klaviyo_analytics', 'klaviyo_ecommerce', 'facebook_pixel', 'twitter_pixel', 'tiktok_pixel', 'bing', 'gtag', 'hotjar'];
    private $gs_repository;
    public function __construct(Google_Settings_Repository $gs_repository)
    {
        $this->gs_repository = $gs_repository;
    }
    public function get_installed_modules($platform_id, $console = false)
    {
        static $front_active_cache = [];
        if (!$console && \frontend\design\Info::is_totally_admin()) {
            $modules = $this->gs_repository->get_settings(self::$modules, $platform_id);
        } else {
            if (isset($front_active_cache[(int) $platform_id])) {
                return $front_active_cache[(int) $platform_id];
            }
            $modules = $this->gs_repository->get_settings(self::$modules, $platform_id, true);
        }
        $mods = [];
        if (is_array($modules)) {
            foreach ($modules as $mod) {
                $module = $this->_describe_setting($mod, true);
                if (!is_object($module)) {
                    continue;
                }
                $mods[$mod['module']] = $module;
            }
        }
        $front_active_cache[(int) $platform_id] = $mods;
        return $mods;
    }
    private function get_module_object($module)
    {
        $class = "common\\modules\\analytic\\{$module}";
        if (class_exists($class)) {
            $object = new $class();
            $object->set_provider($this);
            $object->get_params();
            return $object;
        }
        return false;
    }
    public function get_uninstalled_modules($platform_id)
    {
        $mods = [];
        $installed = $this->get_installed_modules($platform_id);
        foreach (self::$modules as $_mod) {
            if (!isset($installed[$_mod])) {
                if ($module = $this->get_module_object($_mod)) {
                    $params = $module->get_params();
                    $mods = array_merge($mods, $params);
                }
            }
        }
        return $mods;
    }
    public function get_installed_by_id($id, $overload = true)
    {
        $setting = $this->gs_repository->find_by_id($id);
        if ($setting) {
            return $this->_describe_setting($setting, $overload);
        }
        return false;
    }
    public function get_active_by_code($code, $platform_id)
    {
        $setting = $this->gs_repository->get_setting($code, $platform_id, 1);
        if ($setting) {
            return $this->_describe_setting($setting, true);
        }
        return false;
    }
    private function _describe_setting($setting, $overload = true)
    {
        if ($module = $this->get_module_object($setting->module)) {
            if (tep_not_null($setting->info)) {
                $module->overload_config($setting->info);
            }
            $module->params = (array) $setting->get_attributes();
            return $module;
        }
        return false;
    }
    public function overload_config($config)
    {
        $this->config = unserialize($config);
        return $this;
    }
    public function save($module)
    {
        $setting = $this->gs_repository->find_by_id($module->params['google_settings_id']);
        if ($setting) {
            $this->gs_repository->update_setting($setting, [$this->gs_repository->get_config_holder() => serialize($module->config)]);
        }
    }
    public function perform($module, $action, $platform_id, $status = 0)
    {
        if ($object = $this->get_module_object($module)) {
            if (method_exists($this, $action)) {
                $this->{$action}($object, $platform_id, $status);
            }
        }
    }
    public function remove(modules\Abstract_Google $module, $platform_id, $status)
    {
        $installed = $this->gs_repository->get_setting($module->code, $platform_id);
        return $installed ? $this->gs_repository->delete($installed->google_settings_id) : false;
    }
    public function install(modules\Abstract_Google $module, $platform_id, $status)
    {
        $installed = $this->gs_repository->get_setting($module->code, $platform_id);
        if (!$installed) {
            return $this->gs_repository->create_setting($module->code, (string) $module->config[$module->code]['name'], serialize($module->config), $platform_id, $status);
        }
        return false;
    }
    public function status(modules\Abstract_Google $module, $platform_id, $status)
    {
        $setting = $this->gs_repository->get_setting($module->code, $platform_id);
        if ($setting) {
            return $this->gs_repository->update_setting($setting, ['status' => (int) $status]);
        }
        return false;
    }
    public static function notify()
    {
        \common\helpers\Translation::init('checkout/success');
        if (defined('TEXT_NEED_SETUP_ANALYTICS') && defined('IMAGE_BUTTON_NOTIFICATIONS')) {
            \common\helpers\Mail::send(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, IMAGE_BUTTON_NOTIFICATIONS, TEXT_NEED_SETUP_ANALYTICS, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
    }
    public function get_api_result($url, $method, $params = [])
    {
        //??what for
        $data = http_build_query($params);
        $fp = @file_get_contents($url . '?' . $data, false);
        return $fp;
    }
}