<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\helpers;

class Platform_Config
{
    public static function get_value($key, $platform_id = -1)
    {
        if (defined($key)) {
            //?add for better priority && $platformId < 1
            return constant($key);
        } else {
            if ($platform_id < 1) {
                if ((int) \common\classes\platform::active_id() > 0) {
                    $platform_id = (int) \common\classes\platform::active_id();
                } else {
                    $platform_id = (int) \common\classes\platform::default_id();
                }
            }
            $__platform = \Yii::$app->get('platform');
            $platform_config = $__platform->config($platform_id);
            return $platform_config->const_value($key);
        }
    }
    /**
     * return country_id from config or constant
     * @param int $platformId
     * @return int
     */
    public static function get_store_country($platform_id = -1)
    {
        $address = self::get_default_address($platform_id);
        $country_id = $address['country_id'] ?? 0;
        if (empty($country_id) && defined('STORE_COUNTRY') && STORE_COUNTRY > 0) {
            $country_id = STORE_COUNTRY;
        }
        return $country_id;
    }
    public static function get_default_address($platform_id = -1)
    {
        if ($platform_id < 1) {
            if ((int) \common\classes\platform::active_id() > 0) {
                $platform_id = (int) \common\classes\platform::active_id();
            } else {
                $platform_id = (int) \common\classes\platform::default_id();
            }
        }
        $__platform = \Yii::$app->get('platform');
        $platform_config = $__platform->config($platform_id);
        return $platform_config->get_platform_address();
    }
    public static function get_field_value($field, $platform_id = -1)
    {
        if ($platform_id < 1) {
            if ((int) \common\classes\platform::active_id() > 0) {
                $platform_id = (int) \common\classes\platform::active_id();
            } else {
                $platform_id = (int) \common\classes\platform::default_id();
            }
        }
        $__platform = \Yii::$app->get('platform');
        $platform_config = $__platform->config($platform_id);
        return $platform_config->get_platform_data_field($field);
    }
    public static $_cache = [];
    public static function get_val($key, $default = null, $platform_id = 0)
    {
        if (array_key_exists($platform_id . $key, self::$_cache)) {
            return self::$_cache[$platform_id . $key] ?? $default;
        } else {
            $configuration_value = \common\models\Platforms_Configuration::find()->where(['platform_id' => $platform_id, 'configuration_key' => $key])->select('configuration_value')->scalar();
            self::$_cache[$platform_id . $key] = $configuration_value ?? null;
            return $configuration_value ?? $default;
        }
    }
    public static function set_val($key, $value, $platform_id = 0)
    {
        unset(self::$_cache[$platform_id . $key]);
        $row = \common\models\Platforms_Configuration::find_one(['platform_id' => $platform_id, 'configuration_key' => $key]);
        if (empty($row)) {
            $row = new \common\models\Platforms_Configuration();
            $row->platform_id = $platform_id;
            $row->configuration_key = $key;
        }
        $row->configuration_value = $value;
        $row->save(false);
    }
}