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

class Admin_Templates
{
    public static $pages = ['backendOrder' => TABLE_HEADING_ORDER, 'backendOrdersList' => ORDERS_LIST];
    public static function templates_list($access_levels_id)
    {
        $templates_list = [];
        $admin_templates = [];
        $admin_templates_data = \common\models\Admin_Templates::find()->where(['access_levels_id' => $access_levels_id])->as_array()->all();
        foreach ($admin_templates_data as $template) {
            $admin_templates[$template['page']] = $template['template'];
        }
        foreach (self::$pages as $page => $title) {
            $themes_settings = \common\models\Themes_Settings::find()->where(['theme_name' => \common\classes\design::page_name(BACKEND_THEME_NAME), 'setting_group' => 'added_page', 'setting_name' => $page])->as_array()->all();
            $templates = ['' => TEXT_DEFAULT];
            if (is_array($themes_settings)) {
                foreach ($themes_settings as $setting) {
                    $templates[\common\classes\design::page_name($setting['setting_value'])] = $setting['setting_value'];
                }
            }
            $templates_list[] = ['name' => $page, 'title' => $title, 'selectedTemplate' => $admin_templates[$page] ?? null, 'templates' => $templates];
        }
        return $templates_list;
    }
    public static function save($pages, $access_levels_id)
    {
        if (is_array($pages)) {
            foreach ($pages as $page => $template) {
                $page_template = \common\models\Admin_Templates::find_one(['page' => $page, 'access_levels_id' => $access_levels_id]);
                if (!$page_template) {
                    $page_template = new \common\models\Admin_Templates();
                    $page_template->access_levels_id = $access_levels_id;
                    $page_template->page = $page;
                }
                $page_template->template = $template;
                $page_template->save();
            }
        }
    }
}