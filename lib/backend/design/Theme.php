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
namespace backend\design;

use common\classes\design;
use common\classes\Images;
use common\models\Banners;
use common\models\Banners_Groups;
use common\models\Banners_Groups_Sizes;
use common\models\Design_Boxes;
use common\models\Design_Boxes_Settings;
use common\models\Design_Boxes_Settings_Tmp;
use common\models\Design_Boxes_Tmp;
use common\models\Modules;
use common\models\Themes;
use common\models\Themes_Settings;
use common\models\Themes_Styles;
use common\models\Themes_Styles_Groups;
use common\models\Themes_Styles_Main;
use Yii;
use yii\helpers\File_Helper;
class Theme
{
    public static $theme_files = [];
    public static $export_import_type = 'theme';
    public static function export($theme_name, $output = 'download')
    {
        $tmp_path = DIR_FS_CATALOG;
        $img_path = $tmp_path;
        $tmp_path = \Yii::get_alias('@runtime') . DIRECTORY_SEPARATOR;
        //$tmp_path .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $backup_file = $theme_name;
        $zip = new \Zip_Archive();
        if ($zip->open($tmp_path . $backup_file . '.zip', \Zip_Archive::CREATE) === true) {
            $theme = $theme_name;
            $theme_folder = '/desktop';
            for ($i = 0; $i < 2 && $theme; $i++) {
                $json = self::get_theme_json($theme);
                $themes_arr = [];
                $parents = [];
                $parents_query = tep_db_query('select theme_name, parent_theme from ' . TABLE_THEMES);
                while ($item = tep_db_fetch_array($parents_query)) {
                    $themes_arr[$item['theme_name']] = $item['parent_theme'];
                }
                $parent_theme = $theme;
                $parents[] = $theme;
                while ($themes_arr[$parent_theme] ?? null) {
                    $parents[] = $themes_arr[$parent_theme];
                    $parent_theme = $themes_arr[$parent_theme];
                }
                $parents = array_reverse($parents);
                foreach ($parents as $theme_parent) {
                    $root_path = DIR_FS_CATALOG;
                    $path = $root_path . 'themes' . DIRECTORY_SEPARATOR . $theme_parent . DIRECTORY_SEPARATOR;
                    $files = self::theme_files($theme_parent);
                    foreach ($files as $item) {
                        $item = ltrim($item, '\//');
                        $item = str_replace('\\', '/', $item);
                        $zip->add_file($path . $item, $theme_folder . '/' . $item);
                        // add css, js, images, fonts
                    }
                    $tpl_path = $root_path . 'lib' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;
                    $tpl_path .= 'themes' . DIRECTORY_SEPARATOR . $theme_parent . DIRECTORY_SEPARATOR;
                    $tpl_files = self::theme_files($theme_parent, '', true);
                    foreach ($tpl_files as $item) {
                        $item = str_replace('\\', '/', $item);
                        $zip->add_file($tpl_path . $item, $theme_folder . '/tpl' . $item);
                        // add tpl files
                    }
                }
                $zip->add_from_string($theme_folder . '/theme-tree.json', $json);
                foreach (Uploads::$archive_images as $item) {
                    // add images from different places, by records in db
                    if (is_file($img_path . $item['old'])) {
                        if (!in_array('img' . DIRECTORY_SEPARATOR . $item['new'], $files)) {
                            $item['new'] = str_replace('\\', '/', $item['new']);
                            $zip->add_file($img_path . $item['old'], $theme_folder . '/img/' . $item['new']);
                        }
                    }
                }
                $theme_folder = '/mobile';
                $theme = $theme_name . '-mobile';
            }
            if ($_SESSION['exportItems']['menus'] ?? null) {
                $menus = [];
                foreach ($_SESSION['exportItems']['menus'] as $menu => $checked) {
                    if ($checked == 'false') {
                        continue;
                    }
                    $menus[$menu] = \common\helpers\Menu_Helper::menu_tree($menu);
                }
                if (count($menus)) {
                    $menus_json = json_encode($menus);
                    $zip->add_from_string('menu.json', $menus_json);
                }
            }
            if ($_SESSION['exportItems']['banners'] ?? null) {
                $banners = [];
                $banner_images = [];
                foreach ($_SESSION['exportItems']['banners'] as $banner_group => $checked) {
                    if ($checked == 'false') {
                        continue;
                    }
                    $group_data = \common\helpers\Banner::group_data($banner_group);
                    $group_images = \common\helpers\Banner::group_images($group_data, $banner_images);
                    $banners['groupSettings'][$banner_group] = \common\helpers\Banner::group_settings($banner_group);
                    $banners[$banner_group] = $group_images[1];
                    $banner_images = $group_images[0];
                }
                if (count($banners)) {
                    foreach ($banner_images as $image_name => $image_path) {
                        if (is_file(DIR_FS_CATALOG_IMAGES . $image_path)) {
                            $zip->add_file(DIR_FS_CATALOG_IMAGES . $image_path, $image_path);
                        }
                    }
                    $banners_json = json_encode($banners);
                    $zip->add_from_string('banners/banners.json', $banners_json);
                }
            }
            $zip->close();
            $backup_file .= '.zip';
            if ($output == 'filename') {
                return $tmp_path . $backup_file;
            } else {
                header('Cache-Control: none');
                header('Pragma: none');
                header('Content-type: application/x-octet-stream');
                header('Content-disposition: attachment; filename=' . $backup_file);
                readfile($tmp_path . $backup_file);
                unlink($tmp_path . $backup_file);
            }
        }
        return '';
    }
    public static function export_block($id, $type, $params)
    {
        self::$export_import_type = 'block';
        $fs_catalog = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']) . DIRECTORY_SEPARATOR;
        $theme_name = $params['theme_name'];
        $design_boxes = \common\models\Design_Boxes_Tmp::find()->where([$type => $id, 'theme_name' => $theme_name])->order_by('sort_order')->as_array()->all();
        if ($params['block-name']) {
            $theme_archive = design::page_name($params['block-name']);
        } else {
            $theme_archive = $theme_name . '_' . ($id == 'box' ? $design_boxes[0]['widget_name'] . '_' : '') . $id;
        }
        File_Helper::create_directory($fs_catalog);
        chmod($fs_catalog, 0755);
        $name = $theme_archive;
        for ($i = 1; $i < 100 && file_exists($fs_catalog . $theme_archive . '.zip'); $i++) {
            $theme_archive = $name . '-' . $i;
        }
        $zip = new \Zip_Archive();
        if ($zip->open($fs_catalog . $theme_archive . '.zip', \Zip_Archive::CREATE) !== true) {
            return 'Error';
        }
        $boxes = [];
        foreach ($design_boxes as $key => $box) {
            $box_tree = self::blocks_tree($box['id']);
            $box_tree['sort_order'] = $key;
            $boxes[] = $box_tree;
        }
        $json = json_encode($boxes);
        $files = [];
        $zip->add_from_string('data.json', $json);
        foreach (self::$theme_files as $file) {
            $path = str_replace('frontend/themes/' . $theme_name . '/', '', $file);
            $path = str_replace('themes/' . $theme_name . '/', 'theme/', $path);
            $zip->add_file(DIR_FS_CATALOG . $file, $path);
            $files[] = $path;
        }
        $zip->add_from_string('files.json', json_encode($files));
        $info = ['name' => $params['block-name'] ?? '', 'name_title' => $params['block-title'] ?? '', 'groupCategory' => $params['group-categories'] ?? '', 'comment' => $params['comment'] ?? '', 'page_type' => $params['page_type'] ?? ''];
        $zip->add_from_string('info.json', json_encode($info));
        if ($params['image']) {
            $zip->add_from_string('images/screenshot.png', base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $params['image'])));
            $zip->add_from_string('images.json', json_encode(['screenshot.png']));
        }
        $zip->close();
        $theme_archive .= '.zip';
        \backend\design\Groups::synchronize();
        if ($params['save-to-groups']) {
            $message = sprintf(SAVED_TO_GROUPS, $params['block-name']);
        } else {
            $message = sprintf(COMMON_CREATED, $params['block-name']);
        }
        return json_encode(['text' => $message, 'filename' => $theme_archive, 'extensionWidgets' => self::extension_widgets()]);
    }
    public static function import($theme_name, $archive_filename)
    {
        $path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR;
        $path_desktop = $path . 'desktop' . DIRECTORY_SEPARATOR;
        $path_mobile = $path . 'mobile' . DIRECTORY_SEPARATOR;
        $arr_mobile = '';
        $zip = new \Zip_Archive();
        if ($zip->open($archive_filename, \Zip_Archive::CREATE) === true) {
            if (!file_exists($path)) {
                try {
                    File_Helper::create_directory($path, 0777);
                } catch (\Exception $ex) {
                }
                //mkdir($path);
            }
            if ($zip->extract_to($path)) {
                clearstatcache();
                $extracted_files = File_Helper::find_files($path, ['recursive' => true]);
                if (!is_array($extracted_files)) {
                    $extracted_files = [];
                }
                foreach ($extracted_files as $extracted_file) {
                    if (is_file($extracted_file)) {
                        @chmod($extracted_file, 0666);
                    } elseif (is_dir($extracted_file)) {
                        @chmod($extracted_file, 0777);
                    }
                }
            }
            if (!is_dir($path_desktop)) {
                $path_desktop = $path;
            }
            $arr_desktop = json_decode(file_get_contents($path_desktop . 'theme-tree.json'), true);
            if (is_file($path_mobile . 'theme-tree.json')) {
                $arr_mobile = json_decode(file_get_contents($path_mobile . 'theme-tree.json'), true);
            }
            if (is_file($path . 'menu.json')) {
                $menu_data = json_decode(file_get_contents($path . 'menu.json'), true);
                foreach ($menu_data as $menu => $data) {
                    \common\helpers\Menu_Helper::create_menu($menu, $data);
                }
            }
            if (is_file($path . 'banners' . DIRECTORY_SEPARATOR . 'banners.json')) {
                $platform_ids = self::get_platform_ids($theme_name);
                $banner_data = json_decode(file_get_contents($path . 'banners/banners.json'), true);
                $banners_ids = \common\helpers\Banner::setup_banners($banner_data, $path, $platform_ids);
                file_put_contents($path . 'banners-ids.json', json_encode($banners_ids));
            }
            Theme::copy_files($theme_name);
        } else {
            return false;
        }
        if (is_array($arr_desktop) && $theme_name) {
            Steps::import_theme(['theme_name' => $theme_name]);
            Theme::import_theme($arr_desktop, $theme_name);
            if ($arr_mobile) {
                Theme::import_theme($arr_mobile, $theme_name . '-mobile');
                Steps::import_theme(['theme_name' => $theme_name . '-mobile']);
            }
            \common\models\Design_Boxes_Cache::delete_all(['theme_name' => $theme_name]);
            \common\models\Design_Boxes_Cache::delete_all(['theme_name' => $theme_name . '-mobile']);
            Style::create_cache($theme_name);
            Style::create_cache($theme_name . '-mobile');
            return true;
        }
        return false;
    }
    public static function import_block($file_name, $params, $file = '')
    {
        $path_tmp = implode(DIRECTORY_SEPARATOR, [DIR_FS_CATALOG, 'themes', $params['theme_name'], 'tmp']);
        $zip = new \Zip_Archive();
        if ($zip->open($file_name, \Zip_Archive::CREATE) === true) {
            if (!file_exists($path_tmp)) {
                try {
                    File_Helper::create_directory($path_tmp, 0777);
                } catch (\Exception $ex) {
                }
            }
            if ($zip->extract_to($path_tmp)) {
                clearstatcache();
            }
        } else {
            return 'Error: archive is broken';
        }
        $boxes_arr = json_decode(file_get_contents($path_tmp . DIRECTORY_SEPARATOR . 'data.json'), true);
        if (!is_array($boxes_arr)) {
            return 'Error: data.json';
        }
        $box_id_arr = [];
        if ($boxes_arr['microtime'] ?? false) {
            if ($file) {
                $boxes_arr['settings'][] = ['setting_name' => 'from_file', 'setting_value' => $file];
            }
            $box_id_arr[] = Theme::blocks_tree_import($boxes_arr, $params['theme_name'], $params['block_name'], $params['sort_order']);
        } else {
            foreach ($boxes_arr as $block_name => $arr) {
                if ($file) {
                    $arr['settings'][] = ['setting_name' => 'from_file', 'setting_value' => $file];
                }
                if (is_int($block_name)) {
                    $sort_order = $params['sortOrder'] ?? $params['sort_order'] + $arr['sort_order'];
                    $block_boxes = Design_Boxes_Tmp::find()->where(['block_name' => $params['block_name'], 'theme_name' => $params['theme_name']])->and_where(['>=', 'sort_order', $sort_order])->all();
                    foreach ($block_boxes as $box) {
                        $box->sort_order = $box->sort_order + 1;
                        $box->save();
                    }
                    $box_id_arr[] = Theme::blocks_tree_import($arr, $params['theme_name'], $params['block_name'], $sort_order);
                } else {
                    $box_id_arr[] = Theme::blocks_tree_import($arr, $params['theme_name'], $block_name);
                }
            }
        }
        if (is_file($path_tmp . DIRECTORY_SEPARATOR . 'files.json')) {
            $files = json_decode(file_get_contents($path_tmp . DIRECTORY_SEPARATOR . 'files.json'), true);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (!preg_match('/^lib\//', $file)) {
                        continue;
                    }
                    $setting_value = $file;
                    $file_from = $path_tmp . DIRECTORY_SEPARATOR . $setting_value;
                    if (!is_file($file_from)) {
                        continue;
                    }
                    $setting_value = preg_replace('/^lib\//', 'lib/frontend/themes/' . $params['theme_name'] . '/', $setting_value);
                    if (is_file($setting_value)) {
                        continue;
                    }
                    $destiny_folder = substr($setting_value, 0, strrpos($setting_value, DIRECTORY_SEPARATOR));
                    File_Helper::create_directory(DIR_FS_CATALOG . $destiny_folder, 0777);
                    copy($file_from, DIR_FS_CATALOG . $setting_value);
                    @chmod(DIR_FS_CATALOG . $setting_value, 0666);
                }
            }
        }
        File_Helper::remove_directory($path_tmp);
        return [$boxes_arr, $box_id_arr];
    }
    public static function get_platform_ids($theme_name)
    {
        $platforms = \common\models\Platforms_To_Themes::find()->alias('p2t')->select('p2t.platform_id')->inner_join(\common\models\Themes::table_name() . ' as t', 'p2t.theme_id = t.id')->where(['t.theme_name' => $theme_name])->as_array()->all();
        $ids = [];
        foreach ($platforms as $platform) {
            $ids[] = $platform['platform_id'];
        }
        return $ids;
    }
    public static function get_theme_json($theme_name)
    {
        $theme = [];
        $query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($theme_name) . "' and block_name not like 'block-%'");
        while ($item = tep_db_fetch_array($query)) {
            $theme['blocks'][] = self::blocks_tree($item['id'], true);
        }
        $query = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "'");
        while ($item = tep_db_fetch_array($query)) {
            if ($item['setting_group'] == 'css' && $item['setting_name'] == 'css') {
                preg_match_all("/url\\([\\'\"]{0,1}([^\\)\\'\"]+)/", $item['setting_value'], $out, PREG_PATTERN_ORDER);
                $css_img_arr = [];
                foreach ($out[1] as $img) {
                    if (substr($img, 0, 2) != '//' && substr($img, 0, 4) != 'http') {
                        if (!$css_img_arr[$img]) {
                            $css_img_arr[$img] = Uploads::add_archive_images('background_image', $img);
                        }
                    }
                }
                foreach ($css_img_arr as $path => $img) {
                    $item['setting_value'] = str_replace($path, $img, $item['setting_value']);
                }
            }
            $item['setting_value'] = str_replace('/' . $theme_name . '/', '/<theme_name>/', $item['setting_value']);
            $theme['settings'][] = ['setting_group' => $item['setting_group'], 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value']];
        }
        $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($theme_name) . "'");
        while ($item = tep_db_fetch_array($query)) {
            $item['value'] = Uploads::add_archive_images($item['attribute'], $item['value']);
            $v_arr = Style::v_arr($item['visibility']);
            foreach ($v_arr as $v_key => $v_item) {
                if ($v_item > 10) {
                    $v_media = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where id = '" . $v_item . "'"));
                    $v_arr[$v_key] = $v_media['setting_value'] ?? '';
                }
            }
            $item['visibility'] = Style::v_str($v_arr, true);
            $theme['styles'][] = ['selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']];
        }
        $theme['main_styles'] = Themes_Styles_Main::find()->where(['theme_name' => $theme_name])->as_array()->all();
        $theme['main_styles_groups'] = Themes_Styles_Groups::find()->where(['theme_name' => $theme_name])->as_array()->all();
        return json_encode($theme);
    }
    public static function theme_files($theme_name, $path = '', $tpl = false)
    {
        $theme_path = DIR_FS_CATALOG;
        if ($tpl) {
            $theme_path .= 'lib' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;
            $theme_path .= 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR;
        } else {
            $theme_path .= 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR;
        }
        $files_arr = [];
        $arr = file_exists($theme_path . $path) ? scandir($theme_path . $path) : [];
        foreach ($arr as $item) {
            if ($item != '.' && $item != '..' && $item != 'updates' && $item != 'cache') {
                if (is_dir($theme_path . $path . DIRECTORY_SEPARATOR . $item)) {
                    $files_arr = array_merge($files_arr, self::theme_files($theme_name, $path . DIRECTORY_SEPARATOR . $item, $tpl));
                } else {
                    $files_arr[] = $path . ($path ? DIRECTORY_SEPARATOR : '') . $item;
                }
            }
        }
        return $files_arr;
    }
    public static $widget_translation_keys_widgets_names = ['Html_box', 'ClosableBox', 'SendForm', 'Tabs'];
    public static $widget_translation_keys_settings_names = ['text', 'tab_1', 'tab_2', 'tab_3', 'tab_4', 'tab_5', 'tab_6', 'tab_7', 'tab_8', 'tab_9', 'tab_10', 'title', 'success', 'text'];
    public static function widget_translation_keys($widget)
    {
        $translations = [];
        if (!in_array($widget['widget_name'], self::$widget_translation_keys_widgets_names)) {
            return $translations;
        }
        if (isset($widget['settings']) && is_array($widget['settings'])) {
            foreach ($widget['settings'] as $setting) {
                if (!isset($setting['setting_name']) || !in_array($setting['setting_name'], self::$widget_translation_keys_settings_names)) {
                    continue;
                }
                preg_match_all('/##([A-Z\_0-9]+)##/', $setting['setting_value'], $matches);
                foreach ($matches[1] as $key) {
                    $translation = \common\models\Translation::find()->where(['translation_key' => $key])->as_array()->all();
                    foreach ($translation as $t) {
                        if (substr($t['translation_entity'], 0, 5) == 'admin') {
                            continue;
                        }
                        if (!isset($translations[$t['translation_entity']])) {
                            $translations[$t['translation_entity']] = [];
                        }
                        if (!isset($translations[$t['translation_entity']][$t['translation_key']])) {
                            $translations[$t['translation_entity']][$t['translation_key']] = [];
                        }
                        $code = \common\helpers\Language::get_language_code($t['language_id'], false);
                        if (!isset($translations[$t['translation_entity']][$t['translation_key']][$code])) {
                            $translations[$t['translation_entity']][$t['translation_key']][$code] = $t['translation_value'];
                        }
                    }
                }
            }
        }
        return $translations;
    }
    public static function add_translations($translations)
    {
        if (!isset($translations) || !is_array($translations) || !count($translations)) {
            return null;
        }
        foreach ($translations as $entity => $keys) {
            foreach ($keys as $key => $language_codes) {
                foreach ($language_codes as $code => $translat) {
                    $language = \common\helpers\Language::get_language_id($code);
                    if (!($language['languages_id'] ?? null)) {
                        continue;
                    }
                    $language_id = $language['languages_id'];
                    $tr = \common\models\Translation::find_one(['language_id' => $language_id, 'translation_key' => $key, 'translation_entity' => $entity]);
                    if (!$tr) {
                        $translation = new \common\models\Translation();
                        $translation->language_id = $language_id;
                        $translation->translation_key = $key;
                        $translation->translation_entity = $entity;
                        $translation->translation_value = $translat;
                        $translation->save();
                    }
                }
            }
        }
    }
    public static function add_css($css, $theme_name, $widget_name, $rewrite = true)
    {
        if (!isset($css) || !is_array($css) || !count($css)) {
            return null;
        }
        $main_styles = \backend\design\Style::main_styles($theme_name);
        foreach ($css as $class_name => $properties) {
            if ($class_name == 'current') {
                $class_name = self::widget_name_to_css_class($widget_name);
            }
            if ($rewrite) {
                Themes_Styles::delete_all(['accessibility' => $class_name, 'theme_name' => $theme_name]);
            } elseif (Themes_Styles::find_one(['accessibility' => $class_name, 'theme_name' => $theme_name])) {
                continue;
            }
            foreach ($properties as $property) {
                if (($property['value_main_style'] ?? null) && !isset($main_styles[$property['value']]) && !isset($main_styles[preg_replace('/\-[0-9]+$/', '-1', $property['value'])])) {
                    $value = $property['value_main_style'];
                } else {
                    $value = $property['value'];
                }
                $property['value'] = self::import_files($property, 'css', $theme_name);
                $themes_styles = new Themes_Styles();
                $themes_styles->theme_name = (string) $theme_name;
                $themes_styles->selector = (string) $property['selector'];
                $themes_styles->attribute = (string) $property['attribute'];
                $themes_styles->value = (string) $value;
                $themes_styles->visibility = (string) self::get_id_style_media_sizes($property['visibility'], $theme_name);
                $themes_styles->media = (string) $property['media'];
                $themes_styles->accessibility = (string) $class_name;
                $themes_styles->save();
            }
            \backend\design\Style::create_cache($theme_name, $class_name);
        }
    }
    public static function get_style_media_sizes($size_id, $theme_name, $style_id = 0)
    {
        static $size = [];
        if (!($size[$theme_name] ?? null)) {
            $size[$theme_name] = [];
        }
        if (isset($size[$theme_name][$size_id])) {
            return $size[$theme_name][$size_id];
        }
        $size_val = false;
        $parts = explode(',', $size_id);
        $themes_setting = \common\models\Themes_Settings::find_one(['id' => $parts[0], 'theme_name' => $theme_name]);
        if ($themes_setting) {
            $size_val = $themes_setting->setting_value;
            if ($parts[1] ?? false) {
                $size_val = $size_val . ',' . $parts[1];
            }
        }
        if ($size_val) {
            $size[$theme_name][$size_id] = $size_val;
            return $size_val;
        } else {
            $size[$theme_name][$size_id] = '';
            return '';
        }
    }
    public static function get_id_style_media_sizes($style_media_size, $theme_name)
    {
        if (strlen($style_media_size) < 2) {
            return $style_media_size;
        }
        static $size = [];
        if (!($size[$theme_name] ?? null)) {
            $size[$theme_name] = [];
        }
        if (isset($size[$theme_name][$style_media_size])) {
            return $size[$theme_name][$style_media_size];
        }
        $size_id = false;
        $parts = explode(',', $style_media_size);
        $themes_setting = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => 'extend', 'setting_name' => 'media_query', 'setting_value' => $parts[0]]);
        if ($themes_setting) {
            $size_id = $themes_setting->id . ($parts[1] ?? false ? ',' . $parts[1] : '');
        }
        if (!$size_id) {
            $themes_setting = new Themes_Settings();
            $themes_setting->theme_name = $theme_name;
            $themes_setting->setting_group = 'extend';
            $themes_setting->setting_name = 'media_query';
            $themes_setting->setting_value = $style_media_size;
            $themes_setting->save();
            $size_id = $themes_setting->get_primary_key();
        }
        $size[$theme_name][$style_media_size] = $size_id;
        return $size_id;
    }
    public static function widget_name_to_css_class($widget_name)
    {
        $class = preg_replace('/([A-Z])/', '-$1', $widget_name);
        $class = str_replace('\\', '-', $class);
        $class = '.w-' . $class;
        $class = str_replace('--', '-', $class);
        $class = strtolower($class);
        return $class;
    }
    public static function get_css_by_class($class, $theme_name)
    {
        static $response = [];
        if (!($response[$theme_name] ?? null)) {
            $response[$theme_name] = [];
        }
        if (isset($response[$theme_name][$class])) {
            return $response[$theme_name][$class];
        }
        $themes_styles = \common\models\Themes_Styles::find()->where(['accessibility' => $class, 'theme_name' => $theme_name])->as_array()->all();
        if (!$themes_styles || !is_array($themes_styles)) {
            $response[$theme_name][$class] = [];
            return [];
        }
        $main_styles = \backend\design\Style::main_styles($theme_name);
        foreach ($themes_styles as $key => $style) {
            if ((int) $style['visibility'] > 10) {
                $themes_styles[$key]['visibility'] = self::get_style_media_sizes($style['visibility'], $style['theme_name'], 1429180);
            }
            $themes_styles[$key]['value'] = self::add_files($style, 'css', $theme_name);
            if ($main_styles[$themes_styles[$key]['value']] ?? false) {
                $themes_styles[$key]['value_main_style'] = $main_styles[$themes_styles[$key]['value']];
            }
        }
        $response[$theme_name][$class] = $themes_styles;
        return $themes_styles;
    }
    public static function get_widget_class_css($classes, $theme_name)
    {
        $class_arr = explode(' ', $classes);
        $response = [];
        foreach ($class_arr as $class) {
            $class = trim($class);
            if (!$class) {
                continue;
            }
            $css = self::get_css_by_class('.' . $classes, $theme_name);
            if ($css) {
                $response['.' . $class] = $css;
            }
        }
        return $response;
    }
    public static function extension_widgets($widget_name = '')
    {
        static $widgets = [];
        if ($widget_name) {
            $widget_path = explode('\\', $widget_name);
            if (count($widget_path) > 2 || count($widget_path) > 1 && ctype_upper(substr($widget_path[0], 0, 1))) {
                if (array_search($widget_name, array_column($widgets, 'name')) === false) {
                    $status = 'no';
                    $path = DIR_FS_CATALOG . 'common/extensions/' . $widget_name;
                    if (is_dir($path)) {
                        $status = Modules::find()->where(['code' => $widget_path[0]])->count() ? 'installed' : 'not-installed';
                    }
                    $widgets[] = ['name' => $widget_name, 'extension' => $widget_path[0], 'status' => $status];
                }
            }
        }
        return $widgets;
    }
    public static function blocks_tree($id, $images = false)
    {
        $arr = [];
        $query = tep_db_fetch_array(tep_db_query('select widget_name, widget_params, sort_order, block_name, theme_name, microtime from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $id . "'"));
        if (!$query) {
            return [];
        }
        $arr['microtime'] = $query['microtime'];
        $arr['block_name'] = $query['block_name'];
        $arr['widget_name'] = $query['widget_name'];
        $arr['widget_params'] = $query['widget_params'];
        $arr['sort_order'] = $query['sort_order'];
        if (!$images) {
            self::add_files_tpl($query['widget_name'], $query['theme_name']);
            $widget_css_class = self::widget_name_to_css_class($arr['widget_name']);
            $css['current'] = self::get_css_by_class($widget_css_class, $query['theme_name']);
        }
        self::extension_widgets($arr['widget_name']);
        $main_styles = \backend\design\Style::main_styles($query['theme_name']);
        $query2 = tep_db_query('
select dbs.setting_name, dbs.setting_value, dbs.visibility, dbs.microtime, l.code
from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . ' dbs left join ' . TABLE_LANGUAGES . " l on dbs.language_id = l.languages_id\r\nwhere dbs.box_id = '" . (int) $id . "'\r\n");
        while ($item2 = tep_db_fetch_array($query2)) {
            if ($images) {
                $item2['setting_value'] = Uploads::add_archive_images($item2['setting_name'], $item2['setting_value']);
            } else {
                $item2['setting_value'] = self::add_files($item2, 'box', $query['theme_name']);
            }
            $v_arr = Style::v_arr($item2['visibility']);
            foreach ($v_arr as $v_key => $v_item) {
                if ($v_item > 10) {
                    $v_media = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where id = '" . $v_item . "'"));
                    if ($v_media) {
                        $v_arr[$v_key] = $v_media['setting_value'];
                    }
                }
            }
            $item2['visibility'] = Style::v_str($v_arr, true);
            $item2['setting_value'] = str_replace('/' . $query['theme_name'] . '/', '/<theme_name>/', $item2['setting_value']);
            $settings = ['microtime' => $item2['microtime'], 'setting_name' => $item2['setting_name'], 'setting_value' => $item2['setting_value'], 'language_id' => $item2['code'] ? $item2['code'] : 0, 'visibility' => $item2['visibility']];
            if ($main_styles[$item2['setting_value']] ?? false) {
                $settings['setting_value_main_style'] = $main_styles[$item2['setting_value']];
            }
            $arr['settings'][] = $settings;
            if (!$images && $item2['setting_name'] == 'style_class') {
                $css = array_merge($css, self::get_widget_class_css($item2['setting_value'], $query['theme_name']));
            }
        }
        if (self::$export_import_type == 'block' && $arr['widget_name'] == 'Banner' && isset($arr['settings'])) {
            $arr['banner'] = self::add_banner($arr['settings'], $query['theme_name']);
        }
        if (!$images) {
            $arr['css'] = $css;
        }
        $arr['translation'] = self::widget_translation_keys($arr);
        foreach (\common\helpers\Hooks::get_list('design/export-block') as $filename) {
            include $filename;
        }
        if ($query['widget_name'] == 'BatchSelectedProducts') {
            $arr['settings'][] = ['setting_name' => 'cross_id', 'setting_value' => $id, 'language_id' => 0, 'visibility' => ''];
        }
        if ($query['widget_name'] == 'BlockBox' || $query['widget_name'] == 'email\BlockBox' || $query['widget_name'] == 'invoice\Container' || $query['widget_name'] == 'cart\CartTabs' || $query['widget_name'] == 'ClosableBox') {
            $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . "'");
            if (tep_db_num_rows($query) > 0) {
                while ($item = tep_db_fetch_array($query)) {
                    $arr['sub_1'][] = self::blocks_tree($item['id'], $images);
                }
            }
            $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . "-2'");
            if (tep_db_num_rows($query) > 0) {
                while ($item = tep_db_fetch_array($query)) {
                    $arr['sub_2'][] = self::blocks_tree($item['id'], $images);
                }
            }
            $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . "-3'");
            if (tep_db_num_rows($query) > 0) {
                while ($item = tep_db_fetch_array($query)) {
                    $arr['sub_3'][] = self::blocks_tree($item['id'], $images);
                }
            }
            $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . "-4'");
            if (tep_db_num_rows($query) > 0) {
                while ($item = tep_db_fetch_array($query)) {
                    $arr['sub_4'][] = self::blocks_tree($item['id'], $images);
                }
            }
            $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . "-5'");
            if (tep_db_num_rows($query) > 0) {
                while ($item = tep_db_fetch_array($query)) {
                    $arr['sub_5'][] = self::blocks_tree($item['id'], $images);
                }
            }
        } elseif ($query['widget_name'] == 'Tabs') {
            for ($i = 1; $i < 11; $i++) {
                $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . '-' . $i . "'");
                if (tep_db_num_rows($query) > 0) {
                    while ($item = tep_db_fetch_array($query)) {
                        $arr['sub_' . $i][] = self::blocks_tree($item['id'], $images);
                    }
                }
            }
        } elseif ($query['widget_name'] == 'WidgetsAria') {
            $aria = Design_Boxes_Settings_Tmp::find()->where(['box_id' => $id, 'setting_name' => 'aria_name'])->as_array()->one();
            if ($aria['setting_value'] ?? false) {
                $area_boxes = Design_Boxes_Tmp::find()->where(['theme_name' => $query['theme_name'], 'block_name' => $aria['setting_value']])->as_array()->all();
                foreach ($area_boxes as $area_box) {
                    $arr['WidgetsAria'][] = self::blocks_tree($area_box['id'], $images);
                }
            }
        }
        return $arr;
    }
    public static function import_theme($arr, $theme_name)
    {
        \common\models\Design_Boxes::delete_all(['theme_name' => $theme_name]);
        \common\models\Design_Boxes_Settings::delete_all(['theme_name' => $theme_name]);
        \common\models\Design_Boxes_Tmp::delete_all(['theme_name' => $theme_name]);
        \common\models\Design_Boxes_Settings_Tmp::delete_all(['theme_name' => $theme_name]);
        \common\models\Themes_Styles::delete_all(['theme_name' => $theme_name]);
        \common\models\Themes_Settings::delete_all(['theme_name' => $theme_name]);
        if (is_array($arr['settings'] ?? null)) {
            foreach ($arr['settings'] as $item) {
                if ($item['setting_group'] == 'css' && $item['setting_name'] == 'css') {
                    $item['setting_value'] = str_replace('$$', 'themes/' . $theme_name . '/img/', $item['setting_value']);
                }
                $item['setting_value'] = str_replace('<theme_name>', $theme_name, $item['setting_value']);
                $sql_data_array = ['theme_name' => $theme_name, 'setting_group' => $item['setting_group'], 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value']];
                tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
            }
        }
        if (is_array($arr['blocks'] ?? null)) {
            foreach ($arr['blocks'] as $item) {
                self::blocks_tree_import($item, $theme_name, '', '', false, false);
                \yii\caching\Tag_Dependency::invalidate(\Yii::$app->get_cache(), 'translation');
            }
        }
        foreach (['', 'Tmp'] as $key) {
            $sesign_boxes = '\common\models\DesignBoxes' . $key;
            $sesign_boxes_settings = '\common\models\DesignBoxesSettings' . $key;
            $cross_ids = $sesign_boxes_settings::find()->where(['setting_name' => 'cross_id'])->all();
            if (!$cross_ids) {
                continue;
            }
            foreach ($cross_ids as $cross_id) {
                $cross_items = $sesign_boxes_settings::find()->alias('s')->select('s.*')->inner_join($sesign_boxes::table_name() . ' b', 's.box_id = b.id')->where(['s.setting_name' => 'batchSelectedWidget', 's.setting_value' => $cross_id->setting_value, 'b.theme_name' => $theme_name])->all();
                if (!$cross_items) {
                    continue;
                }
                foreach ($cross_items as $cross_item) {
                    $cross_item->setting_value = $cross_id->box_id;
                    $cross_item->save(false);
                }
                $cross_id->delete();
            }
        }
        if (is_array($arr['styles'] ?? null)) {
            foreach ($arr['styles'] as $item) {
                if (substr($item['value'], 0, 2) == '$$') {
                    $item['value'] = 'themes/' . $theme_name . '/img/' . substr_replace($item['value'], '', 0, 2);
                }
                if (strlen($item['visibility']) > 1) {
                    $v_arr = Style::v_arr($item['visibility'], true);
                    foreach ($v_arr as $v_key => $v_item) {
                        if (strlen($v_item) > 1) {
                            $vis_query = tep_db_fetch_array(tep_db_query('select id from ' . TABLE_THEMES_SETTINGS . " where setting_value = '" . tep_db_input($v_item) . "' and setting_name = 'media_query' and theme_name = '" . tep_db_input($theme_name) . "'"));
                            $v_arr[$v_key] = $vis_query['id'];
                        } else {
                            $v_arr[$v_key] = $v_item;
                        }
                    }
                    $item['visibility'] = Style::v_str($v_arr);
                }
                $sql_data_array = ['theme_name' => $theme_name, 'selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']];
                tep_db_perform(TABLE_THEMES_STYLES, $sql_data_array);
                //tep_db_perform(TABLE_THEMES_STYLES_TMP, $sql_data_array);
            }
        }
        if (isset($arr['main_styles']) && is_array($arr['main_styles'])) {
            Themes_Styles_Main::delete_all(['theme_name' => $theme_name]);
            foreach ($arr['main_styles'] as $style) {
                $main_style = new Themes_Styles_Main();
                $main_style->theme_name = $theme_name;
                $main_style->name = $style['name'];
                $main_style->value = $style['value'];
                $main_style->type = $style['type'];
                $main_style->sort_order = $style['sort_order'];
                $main_style->group_id = $style['group_id'] ?? 0;
                $main_style->save(false);
            }
        }
        if (isset($arr['main_styles_groups']) && is_array($arr['main_styles_groups'])) {
            Themes_Styles_Groups::delete_all(['theme_name' => $theme_name]);
            foreach ($arr['main_styles_groups'] as $style) {
                $style_group = new Themes_Styles_Groups();
                $style_group->theme_name = $theme_name;
                $style_group->group_id = $style['group_id'];
                $style_group->group_name = $style['group_name'];
                $style_group->sort_order = $style['sort_order'];
                $style_group->tab = $style['tab'];
                $style_group->save(false);
            }
        }
        self::elements_save($theme_name);
    }
    public static function blocks_tree_import($arr, $theme_name, $block_name = '', $sort_order = '', $save = false, $new_microtime = true)
    {
        $microtime = $new_microtime || !isset($arr['microtime']) ? microtime(true) . rand(0, 99) : $arr['microtime'];
        if (!($arr['block_name'] ?? false)) {
            return '';
        }
        $sql_data_array = ['microtime' => $microtime, 'theme_name' => $theme_name, 'block_name' => $block_name ? $block_name : $arr['block_name'], 'widget_name' => $arr['widget_name'], 'widget_params' => $arr['widget_params'], 'sort_order' => $sort_order ? $sort_order : $arr['sort_order']];
        tep_db_perform(TABLE_DESIGN_BOXES_TMP, $sql_data_array);
        $box_id = tep_db_insert_id();
        if ($save) {
            $sql_data_array = array_merge($sql_data_array, ['id' => $box_id]);
            tep_db_perform(TABLE_DESIGN_BOXES, $sql_data_array);
        }
        self::extension_widgets($arr['widget_name']);
        if (isset($arr['translation'])) {
            self::add_translations($arr['translation']);
        }
        self::add_css($arr['css'] ?? null, $theme_name, $arr['widget_name']);
        foreach (\common\helpers\Hooks::get_list('design/import-block') as $filename) {
            include $filename;
        }
        $banner_settings = false;
        if ($arr['widget_name'] == 'Banner' && isset($arr['banner']) && is_array($arr['banner'])) {
            $banner_settings = self::apply_banners($arr['banner'], $theme_name);
        }
        $main_styles = \backend\design\Style::main_styles($theme_name);
        $check_duplicates = [];
        if (is_array($arr['settings'] ?? null) && count($arr['settings'])) {
            foreach ($arr['settings'] as $item) {
                if ($banner_settings && $item['setting_name'] == 'banners_group') {
                    $item['setting_value'] = $banner_settings['groupId'];
                }
                if ($banner_settings && $item['setting_name'] == 'ban_id') {
                    $item['setting_value'] = $banner_settings['bannerId'];
                }
                $language_id = 0;
                $key = true;
                if ($item['language_id'] ?? false) {
                    $lan_query = tep_db_fetch_array(tep_db_query('select languages_id from ' . TABLE_LANGUAGES . " where code = '" . tep_db_input($item['language_id']) . "'"));
                    if ($lan_query['languages_id'] ?? false) {
                        $language_id = $lan_query['languages_id'];
                    } else {
                        $key = false;
                    }
                }
                $visibility = '';
                if (($item['visibility'] ?? false) && strlen($item['visibility']) > 1) {
                    $v_arr = Style::v_arr($item['visibility'], true);
                    foreach ($v_arr as $v_key => $v_item) {
                        if (strlen($v_item) > 1) {
                            $vis_query = tep_db_fetch_array(tep_db_query('select id from ' . TABLE_THEMES_SETTINGS . " where setting_value = '" . tep_db_input($v_item) . "' and setting_name = 'media_query' and theme_name = '" . tep_db_input($theme_name) . "'"));
                            $v_arr[$v_key] = $vis_query['id'] ?? null;
                        }
                    }
                    $visibility = Style::v_str($v_arr);
                } else {
                    $visibility = $item['visibility'] ?? '';
                }
                if ($key && !isset($check_duplicates[$item['setting_name']][$language_id][$visibility])) {
                    if (str_contains($item['setting_value'], '$$') && str_contains($item['setting_value'], '<theme_name>')) {
                        $item['setting_value'] = trim($item['setting_value'], '$$');
                    }
                    if (substr($item['setting_value'], 0, 2) == '$$') {
                        $item['setting_value'] = 'themes/' . $theme_name . '/img/' . substr_replace($item['setting_value'], '', 0, 2);
                    }
                    $item['setting_value'] = self::import_files($item, 'box', $theme_name);
                    $item['setting_value'] = str_replace('<theme_name>/', $theme_name . '/', $item['setting_value']);
                    if (($item['setting_value_main_style'] ?? null) && !isset($main_styles[$item['setting_value']]) && !isset($main_styles[preg_replace('/\-[0-9]+$/', '-1', $item['setting_value'])])) {
                        $setting_value = $item['setting_value_main_style'];
                    } else {
                        $setting_value = $item['setting_value'];
                    }
                    $sql_data_array = ['box_id' => $box_id, 'microtime' => $microtime, 'theme_name' => $theme_name, 'setting_name' => $item['setting_name'], 'setting_value' => $setting_value, 'language_id' => $language_id, 'visibility' => $visibility];
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, $sql_data_array);
                    $set_id = tep_db_insert_id();
                    if ($save) {
                        $sql_data_array = array_merge($sql_data_array, ['id' => $set_id]);
                        tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, $sql_data_array);
                    }
                    $check_duplicates[$item['setting_name']][$language_id][$visibility] = 1;
                }
            }
        }
        if ($arr['widget_name'] == 'BlockBox' || $arr['widget_name'] == 'email\BlockBox' || $arr['widget_name'] == 'invoice\Container' || $arr['widget_name'] == 'cart\CartTabs' || $arr['widget_name'] == 'ClosableBox') {
            if (is_array($arr['sub_1'] ?? null) && count($arr['sub_1']) > 0) {
                foreach ($arr['sub_1'] as $item) {
                    self::blocks_tree_import($item, $theme_name, 'block-' . $box_id, '', $save, $new_microtime);
                }
            }
            if (is_array($arr['sub_2'] ?? null) && count($arr['sub_2']) > 0) {
                foreach ($arr['sub_2'] as $item) {
                    self::blocks_tree_import($item, $theme_name, 'block-' . $box_id . '-2', '', $save, $new_microtime);
                }
            }
            if (is_array($arr['sub_3'] ?? null) && count($arr['sub_3']) > 0) {
                foreach ($arr['sub_3'] as $item) {
                    self::blocks_tree_import($item, $theme_name, 'block-' . $box_id . '-3', '', $save, $new_microtime);
                }
            }
            if (is_array($arr['sub_4'] ?? null) && count($arr['sub_4']) > 0) {
                foreach ($arr['sub_4'] as $item) {
                    self::blocks_tree_import($item, $theme_name, 'block-' . $box_id . '-4', '', $save, $new_microtime);
                }
            }
            if (is_array($arr['sub_5'] ?? null) && count($arr['sub_5']) > 0) {
                foreach ($arr['sub_5'] as $item) {
                    self::blocks_tree_import($item, $theme_name, 'block-' . $box_id . '-5', '', $save, $new_microtime);
                }
            }
        } elseif ($arr['widget_name'] == 'Tabs') {
            for ($i = 1; $i < 11; $i++) {
                if (is_array($arr['sub_' . $i] ?? null) && count($arr['sub_1']) > 0) {
                    foreach ($arr['sub_' . $i] as $item) {
                        self::blocks_tree_import($item, $theme_name, 'block-' . $box_id . '-' . $i, '', $save, $new_microtime);
                    }
                }
            }
        } elseif ($arr['widget_name'] == 'WidgetsAria' && isset($arr['settings']) && is_array($arr['settings'])) {
            $area_name = '';
            foreach ($arr['settings'] as $setting) {
                if ($setting['setting_name'] == 'aria_name') {
                    $area_name = $setting['setting_value'];
                    break;
                }
            }
            if (is_array($arr['WidgetsAria'] ?? null) && count($arr['WidgetsAria']) > 0) {
                $boxes = Design_Boxes_Tmp::find()->where(['theme_name' => $theme_name, 'block_name' => $area_name])->as_array()->all();
                foreach ($boxes as $box) {
                    Theme::delete_block($box['id']);
                }
                Design_Boxes_Tmp::delete_all(['theme_name' => $theme_name, 'block_name' => $area_name]);
                foreach ($arr['WidgetsAria'] as $item) {
                    self::blocks_tree_import($item, $theme_name, $area_name, '', $save, false);
                }
            }
        }
        return $box_id;
    }
    public static function save_theme_version($theme_name)
    {
        $theme_version = \common\models\Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => 'hide', 'setting_name' => 'theme_version']);
        if (!$theme_version) {
            $theme_version = new \common\models\Themes_Settings();
            $theme_version->theme_name = $theme_name;
            $theme_version->setting_group = 'hide';
            $theme_version->setting_name = 'theme_version';
            $theme_version->setting_value = 0;
        }
        $theme_version->setting_value += 1;
        $theme_version->save();
    }
    public static function elements_save($theme_name)
    {
        self::save_theme_version($theme_name);
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS . ' where box_id in (select id from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($theme_name) . "')");
        //the order is important (empty settings first)
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('DELETE db FROM ' . TABLE_DESIGN_BOXES . ' db INNER JOIN ' . TABLE_DESIGN_BOXES_TMP . " dbt ON dbt.id=db.id WHERE dbt.theme_name='" . tep_db_input($theme_name) . "';");
        tep_db_query('DELETE db FROM ' . TABLE_DESIGN_BOXES_SETTINGS . ' db INNER JOIN ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " dbt ON dbt.id=db.id WHERE dbt.theme_name='" . tep_db_input($theme_name) . "';");
        tep_db_query('INSERT INTO ' . TABLE_DESIGN_BOXES . ' SELECT * FROM ' . TABLE_DESIGN_BOXES_TMP . " WHERE theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('INSERT INTO ' . TABLE_DESIGN_BOXES_SETTINGS . ' SELECT * FROM ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " WHERE theme_name = '" . tep_db_input($theme_name) . "'");
    }
    public static function copy_files($theme_name)
    {
        $path = DIR_FS_CATALOG;
        $path_tpl = $path . 'lib' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;
        $path_tpl .= 'themes' . DIRECTORY_SEPARATOR . $theme_name;
        $path_files = $path . 'themes' . DIRECTORY_SEPARATOR . $theme_name;
        $path_desktop_tmp = $path_files . DIRECTORY_SEPARATOR . 'desktop';
        $path_mobile_tmp = $path_files . DIRECTORY_SEPARATOR . 'mobile';
        if (is_dir($path_desktop_tmp . DIRECTORY_SEPARATOR . 'tpl')) {
            File_Helper::copy_directory($path_desktop_tmp . DIRECTORY_SEPARATOR . 'tpl', $path_tpl);
            File_Helper::remove_directory($path_desktop_tmp . DIRECTORY_SEPARATOR . 'tpl');
        } elseif (is_dir($path_files . DIRECTORY_SEPARATOR . 'tpl')) {
            // if old exported theme
            File_Helper::copy_directory($path_files . DIRECTORY_SEPARATOR . 'tpl', $path_tpl);
            File_Helper::remove_directory($path_files . DIRECTORY_SEPARATOR . 'tpl');
        }
        if (is_dir($path_mobile_tmp . DIRECTORY_SEPARATOR . 'tpl')) {
            File_Helper::copy_directory($path_mobile_tmp . DIRECTORY_SEPARATOR . 'tpl', $path_tpl . '-mobile');
            File_Helper::remove_directory($path_mobile_tmp . DIRECTORY_SEPARATOR . 'tpl');
        }
        if (is_dir($path_desktop_tmp)) {
            File_Helper::copy_directory($path_desktop_tmp, $path_files);
            File_Helper::remove_directory($path_desktop_tmp);
        }
        if (is_dir($path_mobile_tmp)) {
            File_Helper::copy_directory($path_mobile_tmp, $path_files . '-mobile');
            File_Helper::remove_directory($path_mobile_tmp);
        }
    }
    public static function copy_theme($theme_name, $parent_theme, $parent_theme_files = '')
    {
        set_time_limit(0);
        $id_array = [];
        $visibility_array = [];
        $visibility_style_array = [];
        $themes_arr = [];
        $parents = [];
        $parents_query = tep_db_query('select theme_name, parent_theme from ' . TABLE_THEMES);
        while ($item = tep_db_fetch_array($parents_query)) {
            $themes_arr[$item['theme_name']] = $item['parent_theme'];
        }
        $parent_theme = $parent_theme;
        $parents[] = $parent_theme;
        if (isset($themes_arr[$parent_theme]) && is_array($themes_arr[$parent_theme])) {
            while ($themes_arr[$parent_theme]) {
                $parents[] = $themes_arr[$parent_theme];
                $parent_theme = $themes_arr[$parent_theme];
            }
        }
        $parents = array_reverse($parents);
        if ($parent_theme_files == 'copy') {
            $query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($parent_theme) . "'");
            while ($item = tep_db_fetch_array($query)) {
                $sql_data_array = ['microtime' => $item['microtime'], 'theme_name' => $theme_name, 'block_name' => $item['block_name'], 'widget_name' => $item['widget_name'], 'widget_params' => $item['widget_params'], 'sort_order' => $item['sort_order']];
                tep_db_perform(TABLE_DESIGN_BOXES, $sql_data_array);
                $new_row_id = tep_db_insert_id();
                $sql_data_array['id'] = $new_row_id;
                tep_db_perform(TABLE_DESIGN_BOXES_TMP, $sql_data_array);
                $query2 = tep_db_query('select * from ' . TABLE_DESIGN_BOXES_SETTINGS . " where box_id = '" . (int) $item['id'] . "'");
                while ($item2 = tep_db_fetch_array($query2)) {
                    if ($parent_theme_files == 'copy' && ($item2['setting_name'] == 'background_image' || $item2['setting_name'] == 'logo')) {
                        foreach ($parents as $parent_item) {
                            $item2['setting_value'] = str_replace($parent_item, $theme_name, $item2['setting_value']);
                        }
                    }
                    $sql_data_array = ['microtime' => $item2['microtime'], 'theme_name' => $theme_name, 'box_id' => $new_row_id, 'setting_name' => $item2['setting_name'], 'setting_value' => $item2['setting_value'], 'language_id' => $item2['language_id'], 'visibility' => $item2['visibility']];
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, $sql_data_array);
                    $new_row_id_2 = tep_db_insert_id();
                    $sql_data_array['id'] = $new_row_id_2;
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, $sql_data_array);
                    foreach (Style::v_arr($item2['visibility']) as $v_item) {
                        if ($v_item > 10) {
                            $visibility_array[$new_row_id_2] = $item2['visibility'];
                            break;
                        }
                    }
                }
                $id_array[$item['id']] = $new_row_id;
            }
            $query = tep_db_query('select id, block_name from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($theme_name) . "'");
            while ($item = tep_db_fetch_array($query)) {
                preg_match('/[a-z]-([0-9]+)/', $item['block_name'], $matches);
                if ($matches[1] ?? null) {
                    $new_block_name = str_replace($matches[1], $id_array[$matches[1]], $item['block_name']);
                    $sql_data_array = ['block_name' => $new_block_name];
                    tep_db_perform(TABLE_DESIGN_BOXES, $sql_data_array, 'update', " id = '" . $item['id'] . "'");
                    tep_db_perform(TABLE_DESIGN_BOXES_TMP, $sql_data_array, 'update', " id = '" . $item['id'] . "'");
                }
            }
        }
        $query = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($parent_theme) . "'");
        while ($item = tep_db_fetch_array($query)) {
            if ($parent_theme_files == 'copy' && ($item['setting_name'] == 'css' || $item['setting_name'] == 'javascript' || $item['setting_name'] == 'font_added')) {
                foreach ($parents as $parent_item) {
                    $item['setting_value'] = str_replace($parent_item, $theme_name, $item['setting_value']);
                }
            }
            $sql_data_array = ['theme_name' => $theme_name, 'setting_group' => $item['setting_group'], 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value']];
            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
            $new_media_id = tep_db_insert_id();
            if ($item['setting_name'] == 'media_query' && $parent_theme_files == 'copy') {
                $visibility_style_array[$item['id']] = $new_media_id;
                foreach ($visibility_array as $settings_id => $old_media_id) {
                    $v_arr = Style::v_arr($old_media_id);
                    foreach ($v_arr as $v_key => $omi) {
                        if ($omi > 10) {
                            if ($omi == $item['id']) {
                                $v_arr[$v_key] = $new_media_id;
                                $sql_data_array = [];
                                $sql_data_array['visibility'] = Style::v_str($v_arr);
                                tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, $sql_data_array, 'update', " id = '" . $settings_id . "'");
                                tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, $sql_data_array, 'update', " id = '" . $settings_id . "'");
                            }
                        }
                    }
                }
            }
        }
        if ($parent_theme_files == 'copy') {
            $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($parent_theme) . "'");
        } else {
            $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($parent_theme) . "' and accessibility = '.b-bottom'");
        }
        while ($item = tep_db_fetch_array($query)) {
            $visibility_array = explode(',', $item['visibility']);
            $new_visibility_array = [];
            foreach ($visibility_array as $v) {
                if ($visibility_style_array[$v] ?? null) {
                    $new_visibility_array[] = $visibility_style_array[$v];
                } else {
                    $new_visibility_array[] = $v;
                }
            }
            $item['visibility'] = implode(',', $new_visibility_array);
            if ($parent_theme_files == 'copy') {
                $item['value'] = str_replace($parent_theme, $theme_name, $item['value']);
            }
            $sql_data_array = ['theme_name' => $theme_name, 'selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']];
            tep_db_perform(TABLE_THEMES_STYLES, $sql_data_array);
        }
        $main_styles = Themes_Styles_Main::find()->where(['theme_name' => $parent_theme])->all();
        if (is_array($main_styles)) {
            foreach ($main_styles as $style) {
                $new_style = new Themes_Styles_Main();
                $new_style->attributes = $style->attributes;
                $new_style->group_id = $style->group_id;
                $new_style->theme_name = $theme_name;
                $new_style->save();
            }
        }
        $styles_groups = Themes_Styles_Groups::find()->where(['theme_name' => $parent_theme])->all();
        if (is_array($main_styles)) {
            foreach ($styles_groups as $group) {
                $styles_group = new Themes_Styles_Groups();
                $styles_group->attributes = $group->attributes;
                $styles_group->tab = $group->tab;
                $styles_group->theme_name = $theme_name;
                $styles_group->save();
            }
        }
        if ($parent_theme_files == 'copy') {
            foreach ($parents as $parent_item) {
                $path = \Yii::get_alias('@webroot');
                $path .= DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
                $parent_path = $path . 'themes' . DIRECTORY_SEPARATOR . $parent_item;
                $tpl = $path . 'lib' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;
                $tpl_parent = $tpl . 'themes' . DIRECTORY_SEPARATOR . $parent_item;
                $tpl .= 'themes' . DIRECTORY_SEPARATOR . $theme_name;
                $path .= 'themes' . DIRECTORY_SEPARATOR . $theme_name;
                if (is_dir($parent_path)) {
                    File_Helper::copy_directory($parent_path, $path);
                }
                if (is_dir($tpl_parent)) {
                    File_Helper::copy_directory($tpl_parent, $tpl);
                }
            }
        } else {
            $path = \Yii::get_alias('@webroot');
            $path .= DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
            $screenshot = $path . 'themes' . DIRECTORY_SEPARATOR . $parent_theme . DIRECTORY_SEPARATOR . 'screenshot.png';
            $path .= 'themes' . DIRECTORY_SEPARATOR . $theme_name;
            if (file_exists($screenshot)) {
                if (!file_exists($path)) {
                    mkdir($path);
                }
                copy($screenshot, $path . DIRECTORY_SEPARATOR . 'screenshot.png');
            }
        }
    }
    public static function theme_remove($theme_name, $remove_files = true)
    {
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_TMP . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_THEMES . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_THEMES_STYLES_CACHE . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_THEMES_STEPS . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('delete from ' . TABLE_THEMES_STYLES_CACHE . " where theme_name = '" . tep_db_input($theme_name) . "'");
        Themes_Styles_Main::delete_all(['theme_name' => $theme_name]);
        Themes_Styles_Groups::delete_all(['theme_name' => $theme_name]);
        $theme_backups = DIR_FS_CATALOG . 'lib' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $theme_name;
        File_Helper::remove_directory($theme_backups);
        if ($remove_files) {
            $count = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES . " where parent_theme = '" . tep_db_input($theme_name) . "'"));
            if ($count['total'] == 0) {
                $path = DIR_FS_CATALOG;
                $path_lib = $path . 'lib' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;
                $path_lib .= 'themes' . DIRECTORY_SEPARATOR . $theme_name;
                $path .= 'themes' . DIRECTORY_SEPARATOR . $theme_name;
                File_Helper::remove_directory($path_lib);
                File_Helper::remove_directory($path);
            }
        }
    }
    public static function get_theme_title($theme_name)
    {
        static $theme_title = [];
        if (isset($theme_title[$theme_name])) {
            return $theme_title[$theme_name];
        }
        $mobile = false;
        if (strpos($theme_name, '-mobile')) {
            $theme_name = str_replace('-mobile', '', $theme_name);
            $mobile = true;
        }
        $query = tep_db_fetch_array(tep_db_query('select title from ' . TABLE_THEMES . " where theme_name = '" . tep_db_input($theme_name) . "'"));
        $theme_title[$theme_name] = $query['title'];
        if ($mobile && defined('TEXT_MOBILE')) {
            $theme_title[$theme_name] .= ' (' . TEXT_MOBILE . ')';
        }
        return $theme_title[$theme_name];
    }
    public static function use_mobile_theme($theme_name)
    {
        $theme = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_name = 'use_mobile_theme'"));
        if ($theme['setting_value']) {
            return true;
        }
        return false;
    }
    public static function get_theme_name($platform_id)
    {
        static $_cache = [];
        if (isset($_cache[$platform_id])) {
            return $_cache[$platform_id];
        }
        if ($platform_id) {
            $platform_config = new \common\classes\platform_config($platform_id);
            $platform_config->constant_up();
            if ($platform_config->is_virtual() || $platform_config->is_marketplace()) {
                $theme = tep_db_fetch_array(tep_db_query('select t.theme_name from platforms_to_themes AS p2t INNER JOIN themes as t ON (p2t.theme_id=t.id) where p2t.is_default = 1 and p2t.platform_id = ' . (int) \common\classes\platform::default_id()));
            } else {
                $theme = tep_db_fetch_array(tep_db_query('select t.theme_name from ' . TABLE_THEMES . ' t, ' . TABLE_PLATFORMS_TO_THEMES . " p2t where p2t.is_default = 1 and t.id = p2t.theme_id and p2t.platform_id='" . $platform_id . "'"));
            }
        } else {
            $theme = tep_db_fetch_array(tep_db_query('select theme_name from ' . TABLE_THEMES));
        }
        $_cache[$platform_id] = $theme['theme_name'] ?? '';
        return $_cache[$platform_id];
    }
    /**
     * copy image in theme image dir and save name in db, use only with save design/settings, it use POST data
     * @param string   $name image name in db, theme_settings.setting_name
     * @return string  path and filename saved in db, 'themes/themename/img/imagename.jpg'
     */
    public static function save_theme_image($name)
    {
        $post = Yii::$app->request->post();
        $image_mod = \common\models\Themes_Settings::find_one(['theme_name' => $post['theme_name'], 'setting_group' => 'hide', 'setting_name' => $name]);
        if (!$image_mod) {
            $image_mod = new \common\models\Themes_Settings();
        }
        $uploaded_file = \common\helpers\Image::prepare_saving_image($image_mod->setting_value ?? '', $post[$name], $post[$name . '_upload'], 'themes' . DIRECTORY_SEPARATOR . $post['theme_name'] . DIRECTORY_SEPARATOR . 'img', false, true);
        $image_mod->attributes = ['theme_name' => $post['theme_name'], 'setting_group' => 'hide', 'setting_name' => $name, 'setting_value' => $uploaded_file];
        if (!$uploaded_file) {
            $image_mod->delete();
        } else {
            $image_mod->save();
        }
        return $uploaded_file;
    }
    public static function save_favicon()
    {
        $uploaded_file = self::save_theme_image('favicon');
        if (!$uploaded_file) {
            return false;
        }
        $path = \Yii::get_alias('@webroot');
        $path .= DIRECTORY_SEPARATOR;
        $path .= '..';
        $path .= DIRECTORY_SEPARATOR;
        $path .= 'themes';
        $path .= DIRECTORY_SEPARATOR;
        $path .= $_GET['theme_name'];
        $path .= DIRECTORY_SEPARATOR;
        $theme = $path;
        $path .= 'icons';
        $path .= DIRECTORY_SEPARATOR;
        if (!file_exists($path)) {
            if (!file_exists($theme)) {
                mkdir($theme);
            }
            mkdir($path);
        }
        if (!is_file(DIR_FS_CATALOG . $uploaded_file)) {
            return false;
        }
        $info = getimagesize(DIR_FS_CATALOG . $uploaded_file);
        $mime = $info['mime'];
        if ($mime == 'image/jpeg') {
            $im = @imagecreatefromjpeg(DIR_FS_CATALOG . $uploaded_file);
        } elseif ($mime == 'image/png') {
            $im = @imagecreatefrompng(DIR_FS_CATALOG . $uploaded_file);
        } elseif ($mime == 'image/gif') {
            $im = @imagecreatefromgif(DIR_FS_CATALOG . $uploaded_file);
        }
        if (!$im) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $icons = [['size' => 57, 'name' => 'apple-icon-57x57.png'], ['size' => 60, 'name' => 'apple-icon-60x60.png'], ['size' => 72, 'name' => 'apple-icon-72x72.png'], ['size' => 76, 'name' => 'apple-icon-76x76.png'], ['size' => 114, 'name' => 'apple-icon-114x114.png'], ['size' => 120, 'name' => 'apple-icon-120x120.png'], ['size' => 144, 'name' => 'apple-icon-144x144.png'], ['size' => 152, 'name' => 'apple-icon-152x152.png'], ['size' => 180, 'name' => 'apple-icon-180x180.png'], ['size' => 512, 'name' => 'apple-icon-512x512.png'], ['size' => 192, 'name' => 'android-icon-192x192.png'], ['size' => 32, 'name' => 'favicon-32x32.png'], ['size' => 96, 'name' => 'favicon-96x96.png'], ['size' => 16, 'name' => 'favicon-16x16.png'], ['size' => 16, 'name' => 'favicon.ico'], ['size' => 144, 'name' => 'ms-icon-144x144.png'], ['size' => 36, 'name' => 'android-icon-36x36.png'], ['size' => 48, 'name' => 'android-icon-48x48.png'], ['size' => 72, 'name' => 'android-icon-72x72.png'], ['size' => 96, 'name' => 'android-icon-96x96.png'], ['size' => 144, 'name' => 'android-icon-144x144.png'], ['size' => 192, 'name' => 'android-icon-192x192.png'], ['size' => 512, 'name' => 'android-icon-512x512.png']];
        foreach ($icons as $icon) {
            $l = $icon['size'];
            if ($w > $h) {
                $left = round(0 - ($l * ($w / $h) - $l) / 2);
                $top = 0;
                $width = round($l * ($w / $h));
                $height = $l;
            } else {
                $left = 0;
                $top = round(0 - ($l * ($h / $w) - $l) / 2);
                $width = $l;
                $height = round($l * ($h / $w));
            }
            $im1 = imagecreatetruecolor($l, $l);
            imagealphablending($im1, false);
            imagesavealpha($im1, true);
            imagecopyresampled($im1, $im, $left, $top, 0, 0, $width, $height, $w, $h);
            imagepng($im1, $path . $icon['name']);
            imagedestroy($im1);
        }
        imagedestroy($im);
        return true;
    }
    public static function save_page_settings($post)
    {
        $theme_name = tep_db_prepare_input($post['theme_name']);
        $page_name = tep_db_prepare_input($post['page_name']);
        if (is_array($post['added_page_settings'] ?? null)) {
            foreach ($post['added_page_settings'] as $setting => $value) {
                $count = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($page_name) . "' and (setting_value = '" . tep_db_input($setting) . "' or setting_value like '" . tep_db_input($setting) . ":%')"));
                if ($value) {
                    $sql_data_array = ['theme_name' => $theme_name, 'setting_group' => 'added_page_settings', 'setting_name' => $page_name, 'setting_value' => $value == 'on' ? $setting : $setting . ':' . $value];
                    if ($count['total']) {
                        tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array, 'update', "theme_name = '" . $theme_name . "' and \tsetting_group = 'added_page_settings' and\tsetting_name='" . $page_name . "'");
                    } else {
                        tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
                    }
                } elseif ($count['total'] > 0) {
                    tep_db_query('delete from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($page_name) . "' and (setting_value = '" . tep_db_input($setting) . "' or setting_value like '" . tep_db_input($setting) . ":%')");
                }
            }
        }
    }
    public static function get_theme_pages($page_name, $theme_name)
    {
        $settings = \common\models\Themes_Settings::find()->where(['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_name' => $page_name])->as_array()->all();
        $pages = [];
        foreach ($settings as $setting) {
            $pages[] = $setting['setting_value'];
        }
        return $pages;
    }
    public static function get_page_name($block_id)
    {
        $block_name = Design_Boxes_Tmp::find_one(['id' => $block_id])->block_name;
        if (substr($block_name, 0, 6) != 'block-') {
            return $block_name;
        }
        $block = explode('-', $block_name);
        return self::get_page_name($block[1]);
    }
    public static function themes_by_group($group_id)
    {
        $themes_array = \common\models\Themes::find()->where(['themes_group_id' => $group_id])->order_by('sort_order')->as_array()->all();
        $themes = [];
        foreach ($themes_array as $item) {
            if ($item['theme_name'] == \common\classes\design::page_name(BACKEND_THEME_NAME) && !\common\helpers\Acl::rule(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_HEADING_THEMES', 'BOX_BACKEND_THEME_EDIT'])) {
                continue;
            }
            $use_mobile_theme = Themes_Settings::find_one(['theme_name' => $item['theme_name'], 'setting_name' => 'use_mobile_theme'])->setting_value ?? null;
            $action = 'design/elements';
            if ($use_mobile_theme) {
                $action = 'design/choose-view';
            }
            $item['link'] = Yii::$app->url_manager->create_url([$action, 'theme_name' => $item['theme_name']]);
            $item['parent_theme_title'] = false;
            if ($item['parent_theme']) {
                $parent = \common\models\Themes::find_one(['theme_name' => $item['parent_theme']]);
                $item['parent_theme_title'] = $parent['title'];
            }
            $themes[] = $item;
        }
        return $themes;
    }
    public static function add_files_tpl($widget_name, $theme_name)
    {
        $ds = DIRECTORY_SEPARATOR;
        $widget_path = explode('\\', $widget_name);
        $last = count($widget_path) - 1;
        $widget_path[$last] = preg_replace('/([A-Z])/', '-$1', $widget_path[$last]);
        $widget_path[$last] = strtolower($widget_path[$last]);
        $widget_path[$last] = trim($widget_path[$last], '-');
        $widget_path = array_merge(['lib', 'frontend', 'themes', $theme_name, 'boxes'], $widget_path);
        $widget = implode($ds, $widget_path) . '.tpl';
        $js_path = ['lib', 'frontend', 'themes', $theme_name, 'js', 'boxes'];
        $js = implode($ds, $js_path) . $ds . str_replace('\\', $ds, $widget_name) . '.js';
        if (is_file(DIR_FS_CATALOG . $ds . $widget)) {
            self::$theme_files[$widget] = $widget;
        }
        if (is_file(DIR_FS_CATALOG . $ds . $js)) {
            self::$theme_files[$js] = $js;
        }
        $widget_class = '\frontend\design\boxes\\' . $widget_name;
        if (!class_exists($widget_class)) {
            $widget_class = '\common\extensions\\' . $widget_name;
        }
        if (class_exists($widget_class) && isset($widget_class::$files) && is_array($widget_class::$files)) {
            foreach ($widget_class::$files as $file) {
                $file_path = implode($ds, ['lib', 'frontend', 'themes', $theme_name, $file]);
                if (is_file(DIR_FS_CATALOG . $ds . $file_path)) {
                    self::$theme_files[$file_path] = $file_path;
                }
            }
        }
    }
    public static function add_files($settings, $type, $theme_name)
    {
        if ($type == 'box') {
            $setting_name = $settings['setting_name'];
            $setting_value = $settings['setting_value'];
        } else {
            $setting_name = $settings['attribute'];
            $setting_value = $settings['value'];
        }
        if (in_array($setting_name, ['background_image', 'background-image', 'logo', 'image', 'file', 'poster'])) {
            if (str_contains($setting_value, 'url(')) {
                $setting_value = str_replace('url(', '', $setting_value);
                $setting_value = trim($setting_value, ') \' "');
            }
            if (is_file(DIR_FS_CATALOG . DIRECTORY_SEPARATOR . $setting_value)) {
                self::$theme_files[$setting_value] = $setting_value;
            }
            $setting_value = str_replace('themes/' . $theme_name . '/', 'theme/', $setting_value);
        }
        return $setting_value;
    }
    public static function import_files($settings, $type, $theme_name)
    {
        if ($type == 'box') {
            $setting_name = $settings['setting_name'];
            $setting_value = $settings['setting_value'];
        } else {
            $setting_name = $settings['attribute'];
            $setting_value = $settings['value'];
        }
        if (in_array($setting_name, ['background_image', 'background-image', 'logo', 'image', 'file', 'poster'])) {
            $file_from = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['themes', $theme_name, 'tmp', $setting_value]);
            $setting_value = preg_replace('/^theme\//', 'themes/' . $theme_name . '/', $setting_value);
            if (is_file($file_from)) {
                $folders_arr = explode(DIRECTORY_SEPARATOR, $setting_value);
                array_pop($folders_arr);
                $path2 = DIR_FS_CATALOG;
                foreach ($folders_arr as $item) {
                    if (!$item) {
                        continue;
                    }
                    $path2 .= $item . DIRECTORY_SEPARATOR;
                    if (!file_exists($path2)) {
                        mkdir($path2, 0777);
                        @chmod($path2, 0777);
                    }
                }
                $i = 1;
                $dot_pos = strrpos($setting_value, '.');
                $end = substr($setting_value, $dot_pos);
                $temp_name = $setting_value;
                while (is_file(DIR_FS_CATALOG . $temp_name)) {
                    $temp_name = substr($setting_value, 0, $dot_pos) . '-' . $i . $end;
                    $temp_name = str_replace(' ', '_', $temp_name);
                    $i++;
                }
                File_Helper::create_directory(dirname(DIR_FS_CATALOG . $temp_name), 0666);
                copy($file_from, DIR_FS_CATALOG . $temp_name);
                @chmod(DIR_FS_CATALOG . $temp_name, 0666);
                \common\classes\Images::create_webp($temp_name, true, '');
                $setting_value = $temp_name;
            }
        }
        return $setting_value;
    }
    public static function delete_block($id, $desktop = false)
    {
        $design_boxes = Design_Boxes_Tmp::find()->where(['block_name' => 'block-' . $id])->or_where(['block_name' => 'block-' . $id . '-2'])->or_where(['block_name' => 'block-' . $id . '-3'])->or_where(['block_name' => 'block-' . $id . '-4'])->or_where(['block_name' => 'block-' . $id . '-5'])->as_array()->all();
        foreach ($design_boxes as $design_box) {
            Design_Boxes_Tmp::delete_all(['id' => $design_box['id']]);
            Design_Boxes_Settings_Tmp::delete_all(['box_id' => $design_box['id']]);
            self::delete_block($design_box['id']);
        }
        if ($desktop) {
            $design_boxes = Design_Boxes::find()->where(['block_name' => 'block-' . $id])->or_where(['block_name' => 'block-' . $id . '-2'])->or_where(['block_name' => 'block-' . $id . '-3'])->or_where(['block_name' => 'block-' . $id . '-4'])->or_where(['block_name' => 'block-' . $id . '-5'])->as_array()->all();
            foreach ($design_boxes as $design_box) {
                Design_Boxes::delete_all(['id' => $design_box['id']]);
                Design_Boxes_Settings::delete_all(['box_id' => $design_box['id']]);
                self::delete_block($design_box['id'], true);
            }
        }
    }
    public static function get_widgets_in_placeholder($placeholder, $theme_name)
    {
        $design_boxes = Design_Boxes_Tmp::find()->where(['block_name' => $placeholder, 'theme_name' => $theme_name])->as_array()->all();
        $blocks_arr = [];
        foreach ($design_boxes as $design_box) {
            $blocks_tree = self::blocks_tree($design_box['id']);
            $blocks_arr[] = $blocks_tree;
        }
        return self::get_widgets_from_tree($blocks_arr);
    }
    public static function get_widgets_from_tree($blocks_tree, $fields = [])
    {
        foreach ($blocks_tree as $block) {
            if ($block['widget_name'] != 'BlockBox') {
                $fields[] = $block;
            } else {
                for ($i = 1; $i < 6; $i++) {
                    if (isset($block['sub_' . $i]) && is_array($block['sub_' . $i])) {
                        $fields = self::get_widgets_from_tree($block['sub_' . $i], $fields);
                    }
                }
            }
        }
        return $fields;
    }
    public static function add_banner($settings, $theme_name = '')
    {
        $banner_group = $type = $banner_id = '';
        foreach ($settings as $setting) {
            if ($setting['setting_name'] == 'banners_group') {
                if (preg_match('/^[0-9]+$/', $setting['setting_value'])) {
                    $banners_groups = Banners_Groups::find_one($setting['setting_value']);
                    if ($banners_groups) {
                        $banner_group = $banners_groups->banners_group;
                    } else {
                        $banner_group = $setting['setting_value'];
                    }
                } else {
                    $banner_group = $setting['setting_value'];
                }
            }
        }
        $platforms = [];
        if ($theme_name) {
            $platforms = self::get_platform_ids($theme_name);
        }
        $banners = [];
        $group_data = \common\helpers\Banner::group_data($banner_group, $platforms, true);
        $group_images = \common\helpers\Banner::group_images($group_data, []);
        $banners['groupSettings'][$banner_group] = Banners_Groups_Sizes::find()->alias('bgs')->inner_join(\common\models\Banners_Groups::table_name() . ' bg', 'bg.id = bgs.group_id')->where(['banners_group' => $banner_group])->as_array()->all();
        $banners[$banner_group] = $group_images[1];
        $banner_images = $group_images[0];
        if (count($banners)) {
            foreach ($banner_images as $image_name => $image_path) {
                if (is_file(DIR_FS_CATALOG . 'images/' . $image_path)) {
                    //$zip->addFile(DIR_FS_CATALOG_IMAGES . $imagePath, $imagePath);
                    self::$theme_files['images/' . $image_path] = 'images/' . $image_path;
                }
            }
        }
        return $banners;
    }
    public static function apply_banners($banner, $theme_name)
    {
        $path_tmp = implode(DIRECTORY_SEPARATOR, [DIR_FS_CATALOG, 'themes', $theme_name, 'tmp', 'images']) . DIRECTORY_SEPARATOR;
        $platform_ids = self::get_platform_ids($theme_name);
        [$banner_id] = \common\helpers\Banner::setup_banners($banner, $path_tmp, $platform_ids, true);
        $banner = Banners::find_one(['banners_id' => $banner_id]);
        return ['groupId' => $banner ? $banner->group_id : 0, 'bannerId' => $banner_id];
    }
}