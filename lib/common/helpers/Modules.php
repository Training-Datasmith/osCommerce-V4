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

use common\classes\modules\Module_Label;
use common\classes\modules\Module_Payment;
use common\classes\modules\Module_Shipping;
class Modules
{
    public static function load_visibility($platform_id, $for_code)
    {
        static $modules_visibility = false;
        if (!is_array($modules_visibility)) {
            $modules_visibility = [];
            foreach (\common\models\Modules_Visibility::find()->where(['platform_id' => (int) $platform_id])->select(['code', 'area'])->as_array()->all() as $module_visibility) {
                $modules_visibility[strtolower($module_visibility['code'])] = explode(',', $module_visibility['area']);
            }
        }
        return isset($modules_visibility[strtolower($for_code)]) ? $modules_visibility[strtolower($for_code)] : false;
    }
    public static function count_modules($modules = '')
    {
        $count = 0;
        if (empty($modules)) {
            return $count;
        }
        $modules_array = explode(';', $modules);
        for ($i = 0, $n = sizeof($modules_array); $i < $n; $i++) {
            $class = substr($modules_array[$i], 0, strrpos($modules_array[$i], '.'));
            if (is_object($GLOBALS[$class])) {
                if ($GLOBALS[$class]->enabled) {
                    $count++;
                }
            }
        }
        return $count;
    }
    public static function count_payment_modules()
    {
        return self::count_modules(MODULE_PAYMENT_INSTALLED);
    }
    public static function count_shipping_modules()
    {
        return self::count_modules(MODULE_SHIPPING_INSTALLED);
    }
    /**
     * get shipping modules for [current] platform
     * @param int|null $platformId
     * @return ModuleShipping[] \common\helpers\namespaceModuleClass
     */
    public static function shipping_modules($platform_id = null)
    {
        Translation::init('shipping');
        $modules_list = [];
        if (is_null($platform_id)) {
            $platform_id = \Yii::$app->get('platform')->config()->get_id();
        }
        $MODULE_INSTALLED = \common\helpers\Configuration::get_platform_configuration_key_value($platform_id, 'MODULE_SHIPPING_INSTALLED');
        $modules_files = explode(';', $MODULE_INSTALLED);
        foreach ($modules_files as $modules_file) {
            $module_class = substr($modules_file, 0, strrpos($modules_file, '.'));
            $namespace_module_class = '\common\modules\orderShipping\\' . $module_class;
            if (is_file(DIR_FS_CATALOG . DIR_WS_MODULES . 'shipping/' . $modules_file)) {
                include_once DIR_FS_CATALOG . DIR_WS_MODULES . 'shipping/' . $modules_file;
            }
            if (class_exists($namespace_module_class)) {
                $modules_list[$module_class] = new $namespace_module_class();
            }
        }
        return $modules_list;
    }
    /**
     * get payment modules for [current] platform
     * @param int|null $platformId
     * @return ModulePayment[] \common\helpers\namespaceModuleClass
     */
    public static function payment_modules($platform_id = null)
    {
        Translation::init('payment');
        $modules_list = [];
        if (is_null($platform_id)) {
            $platform_id = \Yii::$app->get('platform')->config()->get_id();
        }
        $MODULE_INSTALLED = \common\helpers\Configuration::get_platform_configuration_key_value($platform_id, 'MODULE_PAYMENT_INSTALLED');
        $modules_files = explode(';', $MODULE_INSTALLED);
        foreach ($modules_files as $modules_file) {
            $module_class = substr($modules_file, 0, strrpos($modules_file, '.'));
            $namespace_module_class = '\common\modules\orderPayment\\' . $module_class;
            if (is_file(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/' . $modules_file)) {
                include_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/' . $modules_file;
            }
            if (class_exists($namespace_module_class)) {
                $modules_list[$module_class] = new $namespace_module_class();
            }
        }
        return $modules_list;
    }
    public static function get_labels_list($platform_id)
    {
        //$path = \Yii::getAlias('@common');
        //$path .= DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'label';
        $labels_list = [];
        $installed_modules_str = '';
        $get_actual_value = tep_db_fetch_array(tep_db_query('SELECT configuration_value FROM ' . TABLE_PLATFORMS_CONFIGURATION . " WHERE configuration_key='MODULE_LABEL_INSTALLED' AND platform_id='" . intval($platform_id) . "'"));
        if (is_array($get_actual_value)) {
            $installed_modules_str = $get_actual_value['configuration_value'];
        }
        $installed_modules = explode(';', $installed_modules_str);
        if (is_array($installed_modules)) {
            foreach ($installed_modules as $file) {
                if (substr($file, strrpos($file, '.') + 1) == 'php') {
                    //$labelsList[substr($file, 0, -4)] = substr($file, 0, -4);
                    $file = substr($file, 0, strrpos($file, '.'));
                    $class = 'common\modules\label\\' . $file;
                    if (class_exists($class) && is_subclass_of($class, 'common\classes\modules\ModuleLabel')) {
                        $module = new $class();
                        $active = $module->is_module_enabled($platform_id);
                        if ($active) {
                            $labels_list[$file] = $file;
                        }
                    }
                }
            }
        }
        /*if ($dir = @dir($path)) {
              while ($file = $dir->read()) {
                  if (!is_dir($path . $file)) {
                      if (substr($file, strrpos($file, '.') + 1) == 'php') {
                          $labelsList[substr($file, 0, -4)] = substr($file, 0, -4);
                      }
                  }
              }
              ksort($labelsList);
              $dir->close();
          }*/
        return $labels_list;
    }
    /**
     * @return ModuleLabel[]
     */
    public static function label_modules()
    {
        $platform_id = \Yii::$app->get('platform')->config()->get_id();
        $collection = [];
        foreach (static::get_labels_list($platform_id) as $class) {
            $namespace_module_class = 'common\modules\label\\' . $class;
            if (class_exists($namespace_module_class) && is_subclass_of($namespace_module_class, 'common\classes\modules\ModuleLabel')) {
                $label = new $namespace_module_class();
                $collection[$class] = $label;
            }
        }
        return $collection;
    }
    public static function get_module_installed(string $code, string $type = 'extension', $platform_id = 0)
    {
        return \common\models\Modules::find_one(['code' => $code, 'type' => $type, 'platform_id' => $platform_id]);
    }
    /**
     * Get installed module version
     * @param string $code - class name of module: 'UserGroups' or 'paypal_partner'
     * @param string $type - type of module: extension/payment/shipping/
     * @return null|ModuleVer
     */
    public static function get_module_ver_installed(string $code, string $type = 'extension')
    {
        $row = self::get_module_installed($code, $type);
        if (!empty($row)) {
            return \common\classes\modules\Module_Ver::parse_number($row->version);
        }
    }
    public static function get_module_ver_db_installed(string $code, string $type = 'extension')
    {
        $row = self::get_module_installed($code, $type);
        if (!empty($row) && !empty($row->version_db)) {
            return \common\classes\modules\Module_Ver::parse_number($row->version_db);
        }
    }
    /**
     * Get module version of existing module file (it may be not installed)
     * @param string $code - module classname: 'UserGroups' or 'paypal_partner'
     * @param string $type - type of module: extension/payment/shipping/
     */
    public static function get_module_ver_file(string $code, string $type = 'extension')
    {
        if ($type == 'extension' && $ext = \common\helpers\Acl::check_extension($code, 'always')) {
            return \common\classes\modules\Module_Ver::parse($ext::get_version());
        } elseif ($class = \common\classes\modules\Module::get_class($type, $code)) {
            return \common\classes\modules\Module_Ver::parse($class::get_version());
        } else {
            \Yii::warning("Cannot get version for {$type}: {$code}");
        }
    }
    public static function change_module(string $code, $do, array $params = [], string $type = 'extension', $platform_id = 0)
    {
        if ($type == 'extension') {
            $platform_id = 0;
        }
        $row_old = \common\models\Modules::find()->where(['code' => $code, 'type' => $type, 'platform_id' => $platform_id])->one();
        $row = empty($row_old) ? new \common\models\Modules() : $row_old;
        $version = empty($params['version']) ? self::get_module_ver_file($code, $type) : \common\classes\modules\Module_Ver::parse($params['version']);
        if (empty($version)) {
            $version = new \common\classes\modules\Module_Ver(0, 0, 2);
        }
        $row->code = $code;
        $row->platform_id = $platform_id;
        $row->type = $type;
        switch ($do) {
            case 'install':
                $row->version = $version->to_number();
                $row->version_db = $row->version;
                $row->state = \common\models\Modules::STATE_INSTALLED;
                break;
            case 'enable':
                $row->version = $version->to_number();
                $row->state = \common\models\Modules::STATE_ENABLED;
                break;
            case 'disable':
                $row->state = \common\models\Modules::STATE_INSTALLED;
                break;
            case 'remove':
                $row->state = \common\models\Modules::STATE_UNINSTALLED;
                if ($row->is_new_record) {
                    $row->version = $version->to_number();
                    //                    $row->version_db = null;
                }
                break;
            case 'remove_drop':
                $row->version_db = null;
                \common\helpers\Modules_Migrations::clear($code, $type);
                break;
            case 'upgrade':
                $row->version = $version->to_number();
                $row->version_db = \common\classes\modules\Module_Ver::parse($params['version_db'])->to_number();
                break;
            case 'downgrade':
                $row->version_db = \common\classes\modules\Module_Ver::parse($params['version_db'])->to_number();
                break;
            default:
                throw new \Exception('Unknown operation');
        }
        $row->save(false);
    }
    public static function install_ext_safe($code)
    {
        return \common\helpers\Extensions::install_safe($code);
    }
    public static function get_info_link_for_extension($module)
    {
        return '<a href="' . \Yii::$app->url_manager->create_url(['modules/edit', 'platform_id' => 0, 'set' => 'extensions', 'module' => $module]) . '" target="_blank" title="Extension: ' . $module . '" class="extension-info-ico"><i class="icon-info-circle"></i></a>';
    }
}