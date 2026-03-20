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
namespace backend\design\data;

use backend\design\Style;
use backend\design\Theme;
use common\models\Themes as ThemesModel;
class Themes
{
    public static function get_list()
    {
        $themes = Themes_Model::find()->order_by('sort_order')->as_array()->all();
        return $themes;
    }
    public static function sort_order($params)
    {
        foreach ($params as $key => $theme_id) {
            $theme = Themes_Model::find_one(['id' => $theme_id]);
            $theme->sort_order = $key;
            $theme->save();
        }
        return json_encode(['ok']);
    }
    public static function add_theme($params)
    {
        \common\helpers\Translation::init('admin/design');
        if (!$params['title']) {
            return json_encode(['code' => 406, 'text' => THEME_TITLE_REQUIRED]);
        }
        if (!$params['theme_name']) {
            $params['theme_name'] = \common\classes\design::page_name($params['title']);
        }
        if (!preg_match("/^[a-z0-9_\\-]+\$/", $params['theme_name'])) {
            return json_encode(['code' => 1, 'text' => 'Enter only lowercase letters and numbers for theme name']);
        }
        $theme = Themes_Model::find_one(['theme_name' => $params['theme_name']]);
        if ($theme) {
            return json_encode(['code' => 406, 'text' => 'Theme with this name already exist']);
        }
        $parent_theme = '';
        if ($params['parent_theme'] && $params['theme_source'] == 'theme' && $params['parent_theme_files'] == 'link') {
            $parent_theme = $params['parent_theme'];
        }
        $theme = new Themes_Model();
        $theme->theme_name = $params['theme_name'];
        $theme->title = $params['title'];
        $theme->install = 1;
        $theme->is_default = 0;
        $theme->sort_order = Themes_Model::find()->max('sort_order') + 1;
        $theme->parent_theme = $parent_theme;
        $theme->save();
        if ($params['parent_theme'] && $params['theme_source'] == 'theme') {
            //Theme::copyTheme($params['theme_name'], $params['parent_theme'], $params['parent_theme_files']);
            //Theme::copyTheme($params['theme_name'] . '-mobile', $params['parent_theme'] . '-mobile', $params['parent_theme_files']);
        }
        if ($params['theme_source'] == 'url' || $params['theme_source'] == 'computer') {
            if ($params['theme_source'] == 'url') {
                $theme_file = $params['theme_source_url'];
            } else {
                $theme_file = \Yii::get_alias('@webroot');
                $theme_file .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $params['theme_source_computer'];
            }
            if (!Theme::import($params['theme_name'], $theme_file)) {
                return json_encode(['code' => 406, 'text' => 'Wrong theme file']);
            }
        }
        //Style::createCache($params['theme_name']);
        //Style::createCache($params['theme_name'] . '-mobile');
        return json_encode(['code' => 200, 'themes' => Themes_Model::find()->order_by('sort_order')->as_array()->all()]);
    }
}