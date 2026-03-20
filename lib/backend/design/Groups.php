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

use common\classes\Images as CommonImages;
use common\helpers\Html;
use common\models\Design_Boxes_Groups;
use common\models\Design_Boxes_Groups_Category;
use common\models\Design_Boxes_Groups_Images;
use common\models\Design_Boxes_Groups_Languages;
use common\models\Themes_Settings;
use Yii;
use yii\helpers\Array_Helper;
use yii\helpers\File_Helper;
class Groups
{
    public static function group_file_path()
    {
        return DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']);
    }
    public static function synchronize()
    {
        $path = self::group_file_path();
        File_Helper::create_directory($path);
        chmod($path, 0755);
        $files = [];
        $files_path = File_Helper::find_files($path);
        if (is_array($files_path)) {
            foreach ($files_path as $file_path) {
                $file_path_arr = explode(DIRECTORY_SEPARATOR, $file_path);
                $files[end($file_path_arr)] = end($file_path_arr);
            }
        }
        $groups = Design_Boxes_Groups::find()->as_array()->all();
        foreach ($groups as $group) {
            if (isset($files[$group['file']]) && $files[$group['file']]) {
                unset($files[$group['file']]);
            } else {
                Design_Boxes_Groups::delete_all(['file' => $group['file']]);
            }
        }
        foreach ($files as $file) {
            if (!is_file($path . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }
            $zip = new \Zip_Archive();
            if (!$zip->open($path . DIRECTORY_SEPARATOR . $file, \Zip_Archive::CREATE)) {
                continue;
            }
            $info = json_decode($zip->get_from_name('info.json'), true);
            $images = json_decode($zip->get_from_name('images.json'), true);
            if (is_array($images)) {
                foreach ($images as $key => $image) {
                    $images[$key] = 'images/' . $image;
                }
            }
            $name = explode('.', $file);
            $design_boxes_groups = new Design_Boxes_Groups();
            $design_boxes_groups->file = $file;
            if (isset($info['name']) && $info['name']) {
                $design_boxes_groups->name = $info['name'];
            } else {
                $design_boxes_groups->name = $name[0];
            }
            if (isset($info['comment']) && $info['comment']) {
                $design_boxes_groups->comment = $info['comment'];
            }
            if (isset($info['page_type']) && $info['page_type']) {
                $design_boxes_groups->page_type = $info['page_type'];
            }
            if (isset($info['groupCategory']) && $info['groupCategory']) {
                $design_boxes_groups->category = $info['groupCategory'];
            }
            $design_boxes_groups->date_added = new \yii\db\Expression('now()');
            $design_boxes_groups->save();
            $group_id = $design_boxes_groups->get_primary_key();
            $image_fs_path = Common_Images::get_fs_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR . $group_id . DIRECTORY_SEPARATOR;
            $zip->extract_to($image_fs_path, $images);
            if (is_dir($image_fs_path . 'images')) {
                File_Helper::copy_directory($image_fs_path . 'images', $image_fs_path);
                File_Helper::remove_directory($image_fs_path . 'images');
            }
            $images = File_Helper::find_files($image_fs_path);
            if (is_array($images) && $group_id) {
                foreach ($images as $image) {
                    $file_path_arr = explode(DIRECTORY_SEPARATOR, $image);
                    $file = end($file_path_arr);
                    $design_boxes_groups_images = new Design_Boxes_Groups_Images();
                    $design_boxes_groups_images->boxes_group_id = $group_id;
                    $design_boxes_groups_images->file = $file;
                    $design_boxes_groups_images->save();
                }
            }
            $zip->close();
        }
    }
    public static function status()
    {
        $id = Yii::$app->request->post('id');
        $status = Yii::$app->request->post('status');
        $design_boxes_group = Design_Boxes_Groups::find_one($id);
        $design_boxes_group->status = (int) $status;
        $design_boxes_group->save();
        if ($design_boxes_group->errors) {
            return var_dump($design_boxes_group->errors);
        }
        return 'ok';
    }
    public static function save()
    {
        $id = Yii::$app->request->post('id');
        $name = Yii::$app->request->post('name');
        $page_type = Yii::$app->request->post('page_type');
        $design_boxes_group = Design_Boxes_Groups::find_one($id);
        $design_boxes_group->name = $name;
        $design_boxes_group->page_type = $page_type;
        $design_boxes_group->save();
        if ($design_boxes_group->errors) {
            return var_dump($design_boxes_group->errors);
        }
        return 'ok';
    }
    public static function delete()
    {
        $id = Yii::$app->request->post('id');
        $path = self::group_file_path();
        $design_boxes_group = Design_Boxes_Groups::find_one($id);
        $design_boxes_group->file;
        unlink($path . DIRECTORY_SEPARATOR . $design_boxes_group->file);
        Design_Boxes_Groups::delete_all(['id' => $id]);
        if ($design_boxes_group->errors) {
            return var_dump($design_boxes_group->errors);
        }
        return 'ok';
    }
    public static function basename($param, $suffix = null, $charset = 'utf-8')
    {
        if ($suffix) {
            $tmpstr = ltrim(mb_substr($param, mb_strrpos($param, DIRECTORY_SEPARATOR, 0, $charset), null, $charset), DIRECTORY_SEPARATOR);
            if (mb_strpos($param, $suffix, null, $charset) + mb_strlen($suffix, $charset) == mb_strlen($param, $charset)) {
                return str_ireplace($suffix, '', $tmpstr);
            } else {
                return ltrim(mb_substr($param, mb_strrpos($param, DIRECTORY_SEPARATOR, 0, $charset), null, $charset), DIRECTORY_SEPARATOR);
            }
        } else {
            return ltrim(mb_substr($param, mb_strrpos($param, DIRECTORY_SEPARATOR, 0, $charset), null, $charset), DIRECTORY_SEPARATOR);
        }
    }
    public static function get_widget_groups($type)
    {
        $widgets = [];
        $widgets[] = ['name' => 'title', 'title' => TEXT_WIDGET_GROUPS, 'type' => 'groups'];
        $design_boxes_groups = Design_Boxes_Groups::find()->where(['page_type' => $type, 'status' => 1])->or_where(['page_type' => '', 'status' => 1])->as_array()->all();
        if (is_array($design_boxes_groups)) {
            foreach ($design_boxes_groups as $group) {
                $widgets[] = ['name' => 'group-' . $group['id'], 'title' => $group['name'], 'type' => 'groups', 'description' => $group['comment']];
            }
        }
        return $widgets;
    }
    public static function get_widget_groups_categories()
    {
        $categories = ['header' => ['name' => 'header', 'title' => TEXT_HEADER], 'footer' => ['name' => 'footer', 'title' => TEXT_FOOTER], 'header-menu' => ['name' => 'header-menu', 'title' => 'Header menu'], 'pages' => ['name' => 'pages', 'title' => TEXT_PAGES], 'color' => ['name' => 'color', 'title' => TEXT_COLOR_SCHEME], 'font' => ['name' => 'font', 'title' => TEXT_FONTS]];
        $page_groups = Frontend_Structure::get_page_groups();
        $categories['pages']['children'] = $page_groups;
        $pages = Frontend_Structure::get_pages();
        foreach ($pages as $page) {
            Array_Helper::set_value($categories, ['pages', 'children', $page['group'], 'children', $page['name']], $page);
        }
        $groups_category = Design_Boxes_Groups_Category::find()->as_array()->all();
        foreach ($groups_category as $category) {
            $category_arr = explode('/', $category['parent_category']);
            $category_path = [];
            foreach ($category_arr as $category_level) {
                if (!$category_level) {
                    continue;
                }
                $category_path[] = $category_level;
                $category_path[] = 'children';
            }
            if (count($category_path)) {
                Array_Helper::set_value($categories, $category_path, [$category['name'] => ['name' => $category['name'], 'title' => $category['name'], 'category_id' => $category['boxes_group_category_id']]]);
            } else {
                $categories[$category['name']] = ['name' => $category['name'], 'title' => $category['name'], 'category_id' => $category['boxes_group_category_id']];
            }
        }
        return $categories;
    }
    public static function widget_groups_categories_dropdown($name, $selection = null, $options = [])
    {
        $categories = self::get_widget_groups_categories();
        $content = '<option name=""></option>';
        $content .= self::widget_groups_categories_level($categories, $selection);
        $options['name'] = $name;
        return Html::tag('select', "\n" . $content . "\n", $options);
    }
    public static function widget_groups_categories_level($categories, $selection, $indent = '')
    {
        $options = '';
        foreach ($categories as $category) {
            if (isset($category['name']) && $category['name']) {
                if ($category['name'] == 'home') {
                    $category['name'] = 'main';
                }
                $options .= '<option value="' . $category['name'] . '"' . ($category['name'] == $selection ? ' selected' : '') . '>' . $indent . $category['title'] . '</option>';
            }
            if (isset($category['children']) && $category['children']) {
                $options .= self::widget_groups_categories_level($category['children'], $selection, $indent . '&nbsp;&nbsp;&nbsp;');
            }
        }
        return $options;
    }
    public static function get_group($group_id)
    {
        $language_id = Yii::$app->settings->get('languages_id');
        if (!$group_id) {
            return [];
        }
        $group = Design_Boxes_Groups::find()->where(['id' => $group_id])->as_array()->one();
        if (!$group) {
            return [];
        }
        $group['images'] = Design_Boxes_Groups_Images::find()->where(['boxes_group_id' => $group_id])->as_array()->one();
        $languages = [];
        $design_boxes_groups_languages = Design_Boxes_Groups_Languages::find()->where(['boxes_group_id' => $group_id])->as_array()->all();
        if (is_array($design_boxes_groups_languages)) {
            foreach ($design_boxes_groups_languages as $language) {
                $languages[$language['language_id']] = $language;
            }
        }
        if (!isset($languages[$language_id]) || !$languages[$language_id]) {
            $languages[$language_id] = [];
        }
        if ((!isset($languages[$language_id]['title']) || !$languages[$language_id]['title']) && $group['name']) {
            $languages[$language_id]['title'] = $group['name'];
        }
        if ((!isset($languages[$language_id]['description']) || !$languages[$language_id]['description']) && $group['comment']) {
            $languages[$language_id]['description'] = $group['comment'];
        }
        $group['languages'] = $languages;
        foreach (['title', 'description'] as $field) {
            $group[$field] = '';
            if (isset($languages[$language_id][$field]) && $languages[$language_id][$field]) {
                $group[$field] = $languages[$language_id][$field];
                continue;
            }
            if (!is_array($languages)) {
                continue;
            }
            foreach ($languages as $language) {
                if (isset($language[$field])) {
                    $group[$field] = $language[$field];
                    break;
                }
            }
        }
        return $group;
    }
    public static function count_groups($category)
    {
        if (!isset($category['name'])) {
            return 0;
        }
        if ($category['name'] == 'home') {
            $category['name'] = 'main';
        }
        $count = Design_Boxes_Groups::find()->where(['category' => $category['name']])->count();
        if (isset($category['children']) && is_array($category['children'])) {
            foreach ($category['children'] as $sub_category) {
                if ($category['name'] == 'main' && $sub_category['name'] = 'home') {
                    continue;
                }
                $count += self::count_groups($sub_category);
            }
        }
        return $count;
    }
    public static function create_group($group)
    {
        $fs_catalog = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']) . DIRECTORY_SEPARATOR;
        $theme_name = $group['theme_name'];
        if (substr($group['file'], -4) != '.zip') {
            $group['file'] = $group['file'] . '.zip';
        }
        if (is_file($fs_catalog . $group['file'])) {
            return json_encode(['error' => sprintf(FILE_ALREADY_EXISTS, $group['file']), 'focus' => 'group[file]']);
        }
        File_Helper::create_directory($fs_catalog);
        chmod($fs_catalog, 0755);
        $zip = new \Zip_Archive();
        if ($zip->open($fs_catalog . $group['file'], \Zip_Archive::CREATE) !== true) {
            return json_encode(['error' => 'Error']);
        }
        if (!isset($group['pages']) || !is_array($group['pages'])) {
            return json_encode(['error' => CHOOSE_PAGES]);
        }
        $added_pages = [];
        $boxes = [];
        foreach ($group['pages'] as $page) {
            $design_boxes = \common\models\Design_Boxes_Tmp::find()->where(['block_name' => $page, 'theme_name' => $theme_name])->order_by('sort_order')->as_array()->all();
            foreach ($design_boxes as $key => $box) {
                $box_tree = Theme::blocks_tree($box['id']);
                $box_tree['sort_order'] = $key;
                $boxes[$page] = $box_tree;
            }
            $theme_added_pages = Themes_Settings::find()->where(['theme_name' => $theme_name, 'setting_group' => 'added_page'])->as_array()->all();
            foreach ($theme_added_pages as $added_page) {
                if (\common\classes\design::page_name($added_page['setting_value']) == $page) {
                    $added_pages[] = ['setting_name' => $added_page['setting_name'], 'setting_value' => $added_page['setting_value']];
                }
            }
        }
        $json = json_encode($boxes);
        $files = [];
        $zip->add_from_string('data.json', $json);
        foreach (Theme::$theme_files as $file) {
            $path = str_replace('frontend/themes/' . $theme_name . '/', '', $file);
            $path = str_replace('themes/' . $theme_name . '/', 'theme/', $path);
            $zip->add_file(DIR_FS_CATALOG . $file, $path);
            $files[] = $path;
        }
        $zip->add_from_string('files.json', json_encode($files));
        $zip->add_from_string('addedPages.json', json_encode($added_pages));
        $zip->close();
        return false;
    }
}