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

use common\models\Platforms_Configuration;
class Configuration
{
    public static function get_configuration_key_value($lookup)
    {
        $configuration_query_raw = tep_db_query('select configuration_value from ' . TABLE_CONFIGURATION . " where configuration_key='" . $lookup . "'");
        $configuration_query = tep_db_fetch_array($configuration_query_raw);
        $lookup_value = $configuration_query['configuration_value'];
        return $lookup_value;
    }
    public static function get_platform_configuration_key_value($platform_id, $lookup)
    {
        $configuration_query_raw = tep_db_query('select configuration_value from ' . TABLE_PLATFORMS_CONFIGURATION . " where configuration_key='" . $lookup . "' and platform_id = '" . (int) $platform_id . "'");
        $configuration_query = tep_db_fetch_array($configuration_query_raw);
        $lookup_value = $configuration_query['configuration_value'];
        return $lookup_value;
    }
    public static function copy_platform_module_setting($target_platform_id, $source_platform_id = 0)
    {
        if (empty($source_platform_id)) {
            $source_platform_id = \common\classes\platform::default_id();
        }
        if ($target_platform_id == 0 || (int) $source_platform_id == (int) $target_platform_id) {
            return;
        }
        $source_config_array = \common\models\Platforms_Configuration::find()->where(['platform_id' => (int) $source_platform_id])->as_array()->all();
        foreach ($source_config_array as $source_config) {
            $target_config_model = \common\models\Platforms_Configuration::find()->where(['platform_id' => $target_platform_id, 'configuration_key' => $source_config['configuration_key']])->one();
            if (!$target_config_model) {
                $target_config_model = new Platforms_Configuration(array_merge($source_config, ['configuration_id' => null, 'platform_id' => $target_platform_id]));
                $target_config_model->save();
            }
        }
        \common\models\Visibility_Area::delete_all(['platform_id' => (int) $target_platform_id]);
        foreach (\common\models\Visibility_Area::find()->where(['platform_id' => (int) $source_platform_id])->as_array()->all() as $visibility_data) {
            $visibility_data['platform_id'] = (int) $target_platform_id;
            $copied_model = new \common\models\Visibility_Area($visibility_data);
            $copied_model->save();
        }
    }
}