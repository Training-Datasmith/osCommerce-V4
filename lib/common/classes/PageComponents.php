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
namespace common\classes;

use Yii;
class Page_Components
{
    public static function add_components($text)
    {
        if (!empty($text) && strpos($text, '##COMPONENT%') !== false) {
            $text = preg_replace_callback("/\\#\\#COMPONENT\\%([^\\#^\\%]+)[\\%]{0,1}([^\\#]{0,})##/", self::class . '::addComponent', $text);
        }
        return $text;
    }
    private static function add_component($matches)
    {
        $find_page = \common\models\Themes_Settings::find_one(['theme_name' => THEME_NAME, 'setting_group' => 'added_page', 'setting_value' => $matches[1]]);
        if (!$find_page) {
            return '';
        }
        $params = [];
        if ($matches[2]) {
            $arr = explode('=', $matches[2]);
            $params = [$arr[0] => $arr[1]];
        }
        return \frontend\design\Block::widget(['name' => \common\classes\design::page_name($matches[1]), 'params' => ['params' => $params]]);
    }
    public static function component_templates()
    {
        $platform_id = Yii::$app->request->get('platform_id');
        $platform = \common\models\Platforms_To_Themes::find_one($platform_id);
        $theme = \common\models\Themes::find_one($platform['theme_id']);
        $templates_query = \common\models\Themes_Settings::find()->where(['theme_name' => $theme['theme_name'], 'setting_group' => 'added_page'])->as_array()->all();
        $templates = [];
        foreach ($templates_query as $template) {
            if ($template['setting_name'] == 'components') {
                $templates[$template['theme_name']][$template['setting_name']][\common\classes\design::page_name($template['setting_value'])] = $template['setting_value'];
            }
        }
        foreach ($templates_query as $template) {
            if ($template['setting_name'] != 'components') {
                $templates[$template['theme_name']][$template['setting_name']][\common\classes\design::page_name($template['setting_value'])] = $template['setting_value'];
            }
        }
        return $templates;
    }
}