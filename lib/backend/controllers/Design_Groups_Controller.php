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
namespace backend\controllers;

use backend\design\Frontend_Structure;
use backend\design\Groups;
use backend\design\Steps;
use backend\design\Style;
use backend\design\Theme;
use backend\design\Uploads;
use backend\models\Admin;
use common\classes\Images as CommonImages;
use common\models\Design_Boxes;
use common\models\Design_Boxes_Cache;
use common\models\Design_Boxes_Groups;
use common\models\Design_Boxes_Groups_Category;
use common\models\Design_Boxes_Groups_Images;
use common\models\Design_Boxes_Groups_Languages;
use common\models\Design_Boxes_Settings;
use common\models\Design_Boxes_Settings_Tmp;
use common\models\Design_Boxes_Tmp;
use common\models\Themes;
use common\models\Themes_Settings;
use common\models\Themes_Styles;
use common\models\Themes_Styles_Cache;
use common\models\Themes_Styles_Groups;
use common\models\Themes_Styles_Main;
use Yii;
use yii\helpers\Array_Helper;
use yii\helpers\File_Helper;
/**
 *
 */
class Design_Groups_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_HEADING_THEMES'];
    public $designer_mode = '';
    public $designer_mode_title = '';
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('admin/design');
        $admin = new Admin();
        $this->designer_mode = $admin->get_additional_data('designer_mode');
        switch ($this->designer_mode) {
            case 'advanced':
                $this->designer_mode_title = EDIT_MODE . ': ' . ADVANCED_MODE;
                break;
            case 'expert':
                $this->designer_mode_title = EDIT_MODE . ': ' . EXPERT_MODE;
                break;
            default:
                $this->designer_mode_title = EDIT_MODE . ': ' . BASIC_MODE;
        }
        return parent::__construct($id, $module);
    }
    public function action_index()
    {
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/groups'), 'title' => BOX_HEADING_THEMES];
        $this->view->heading_title = BOX_HEADING_THEMES;
        $this->top_buttons[] = '<span class="btn btn-primary btn-add-group">' . TEXT_IMPORT . '</span>';
        $this->top_buttons[] = '<span class="btn btn-primary btn-add-group-category">' . TEXT_CREATE_NEW_CATEGORY . '</span>';
        $this->top_buttons[] = '<span class="btn btn-primary btn-create-group">' . CREATE_GROUP . '</span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $row = Yii::$app->request->get('row');
        $category = Yii::$app->request->get('category');
        Groups::synchronize();
        \backend\design\Data::add_js_data(['widgetGroupsCategories' => Groups::get_widget_groups_categories()]);
        return $this->render('index.tpl', ['menu' => 'groups', 'theme_name' => Yii::$app->request->get('theme_name'), 'row' => $row, 'category' => $category, 'designer_mode' => $this->designer_mode]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $search = Yii::$app->request->get('search');
        if ($length == -1) {
            $length = 10000;
        }
        $keywords = '';
        if ($search) {
            $keywords = $search['value'];
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        $category = $output['category'];
        $response_list = [];
        $categories_list = Groups::get_widget_groups_categories();
        $current_category = '';
        if ($category) {
            $categories = explode('/', $category);
            foreach ($categories as $_category) {
                $categories_list = Array_Helper::get_value($categories_list, [$_category, 'children'], []);
            }
            $current_category = end($categories);
        }
        if ($current_category == 'home') {
            $current_category = 'main';
        }
        $groups = Design_Boxes_Groups::find()->alias('g')->left_join(Design_Boxes_Groups_Languages::table_name() . ' gl', 'gl.boxes_group_id = g.id and gl.language_id = ' . $languages_id)->where(['category' => $current_category])->as_array()->all();
        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (!$group['name']) {
                    continue;
                }
                $img = Design_Boxes_Groups_Images::find()->where(['boxes_group_id' => $group['id']])->as_array()->one();
                $image_fs_path = Common_Images::get_fs_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR . $group['id'] . DIRECTORY_SEPARATOR;
                $image_ws_path = Common_Images::get_ws_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR . $group['id'] . DIRECTORY_SEPARATOR;
                if (isset($img['file']) && is_file($image_fs_path . $img['file'])) {
                    $image = '<img src="' . $image_ws_path . $img['file'] . '">';
                } else {
                    $image = '<div class="no-image"></div>';
                }
                $response_list[] = ['<div class="double-click image-cell" data-id="' . $group['id'] . '">
                        ' . $image . '
                     </div>', '<div class="double-click name-cell group-name" data-id="' . $group['id'] . '">' . (isset($group['title']) && $group['title'] ? $group['title'] : $group['name']) . '</div>', '<div class="double-click file-cell" data-id="' . $group['id'] . '">' . $group['file'] . '</div>', '<div class="double-click type-cell" data-id="' . $group['id'] . '">' . $group['page_type'] . '</div>', '<div class="double-click status-cell" data-id="' . $group['id'] . '">
                        <input type="checkbox" class="group-status" name="status[' . $group['id'] . ']" value="' . $group['id'] . '"' . ($group['status'] ? ' checked' : '') . '>
                     </div>'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => count($response_list), 'recordsFiltered' => count($response_list), 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_categories_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $request = Yii::$app->request->get();
        $category = $request['category'];
        $response_list = [];
        $categories_list = Groups::get_widget_groups_categories();
        $current_category = '';
        if ($category) {
            $categories = explode('/', $category);
            foreach ($categories as $_category) {
                $categories_list = Array_Helper::get_value($categories_list, [$_category, 'children'], []);
            }
            $current_category = end($categories);
        }
        if ($current_category == 'home') {
            $current_category = 'main';
        }
        $groups_count = Design_Boxes_Groups::find()->where(['category' => $current_category])->count();
        if (is_array($categories_list)) {
            foreach ($categories_list as $category) {
                if (!isset($category['name']) || !$category['name']) {
                    continue;
                }
                $count = Groups::count_groups($category);
                if (!$count && !isset($request['show_empty'])) {
                    continue;
                }
                if ($current_category == 'home' && $category['name'] == 'home') {
                    continue;
                }
                $response_list[] = ['name' => $category['name'], 'title' => $category['title'], 'count' => $count];
            }
        }
        echo json_encode(['categories' => $response_list, 'groupsCount' => $groups_count]);
    }
    public function action_upload()
    {
        if (isset($_FILES['file'])) {
            $path = Groups::group_file_path();
            $i = 1;
            $dot_pos = strrpos($_FILES['file']['name'], '.');
            $end = substr($_FILES['file']['name'], $dot_pos);
            $temp_name = $_FILES['file']['name'];
            while (is_file($path . DIRECTORY_SEPARATOR . $temp_name)) {
                $temp_name = substr($_FILES['file']['name'], 0, $dot_pos) . '-' . $i . $end;
                $temp_name = str_replace(' ', '_', $temp_name);
                $i++;
            }
            $upload_file = $path . DIRECTORY_SEPARATOR . $temp_name;
            if (!is_writeable(dirname($path))) {
                $response = ['status' => 'error', 'text' => 'Directory "' . $path . '" not writeable'];
            } elseif (!is_uploaded_file($_FILES['file']['tmp_name']) || filesize($_FILES['file']['tmp_name']) == 0) {
                $response = ['status' => 'error', 'text' => 'File upload error'];
            } else if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_file)) {
                $text = '';
                $response = ['status' => 'ok', 'text' => $text];
            } else {
                $response = ['status' => 'error'];
            }
            \backend\design\Groups::synchronize();
        }
        echo json_encode($response);
    }
    public function action_edit()
    {
        $group_id = Yii::$app->request->get('group_id', 0);
        $row_id = Yii::$app->request->get('row_id', 0);
        $category = Yii::$app->request->get('category', '');
        $language_id = Yii::$app->settings->get('languages_id');
        if (!$group_id) {
            $group_id = Design_Boxes_Groups::find()->max('id') + 1;
            return $this->redirect(Yii::$app->url_manager->create_url(['design-groups/edit', 'group_id' => $group_id, 'category' => $category, 'new_group' => 1]));
        }
        $group = Groups::get_group($group_id);
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['title' => 'Widget group: "' . Array_Helper::get_value($group, ['languages', $language_id, 'title']) . '"'];
        $this->top_buttons[] = '<span class="btn btn-confirm btn-save">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design-groups/view', 'row_id' => $row_id, 'category' => $category, 'group_id' => $group_id]) . '" class="btn btn-primary">' . IMAGE_VIEW . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design-groups', 'row_id' => $row_id, 'category' => $category]) . '" class="btn">' . IMAGE_BACK . '</a>';
        $group['categoryDropdown'] = Groups::widget_groups_categories_dropdown('group[category]', $group['category'] ?? '', ['class' => 'form-control']);
        $page_types_arr = ['main' => 'main'];
        $page_types = Frontend_Structure::get_page_types();
        foreach ($page_types as $type => $cont) {
            if ($type == 'main') {
                $type = 'index';
            }
            $page_types_arr[$type] = $type;
        }
        $group['typesDropdown'] = \common\helpers\Html::drop_down_list('group[page_type]', $group['page_type'] ?? '', $page_types_arr, ['class' => 'form-control']);
        $group['images'] = Design_Boxes_Groups_Images::find()->select('file')->where(['boxes_group_id' => $group_id])->as_array()->all();
        $themes = Themes::find()->where(['not in', 'theme_name', \common\classes\design::page_name(BACKEND_THEME_NAME)])->as_array()->all();
        return $this->render('edit.tpl', ['groupId' => $group_id, 'group' => $group, 'images' => [], 'languages' => \common\helpers\Language::get_languages(), 'new' => true, 'backUrl' => Yii::$app->url_manager->create_url(['design-groups', 'row_id' => $row_id, 'category' => $category]), 'themes' => $themes, 'new_group' => Yii::$app->request->get('new_group', 0)]);
    }
    public function action_view()
    {
        $group_id = Yii::$app->request->get('group_id', 0);
        $row_id = Yii::$app->request->get('row_id', 0);
        $category = Yii::$app->request->get('category', 0);
        $language_id = Yii::$app->settings->get('languages_id');
        $group = Groups::get_group($group_id);
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['title' => 'Widget group: "' . Array_Helper::get_value($group, ['languages', $language_id, 'title']) . '"'];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design-groups/edit', 'row_id' => $row_id, 'category' => $category, 'group_id' => $group_id]) . '" class="btn btn-primary">' . IMAGE_EDIT . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design-groups', 'row_id' => $row_id, 'category' => $category]) . '" class="btn">' . IMAGE_BACK . '</a>';
        $group['images'] = Design_Boxes_Groups_Images::find()->select('file')->where(['boxes_group_id' => $group_id])->as_array()->all();
        return $this->render('view.tpl', ['groupId' => $group_id, 'group' => $group, 'imagesCount' => count($group['images']), 'languages' => \common\helpers\Language::get_languages(), 'new' => true, 'languageId' => $language_id, 'backUrl' => Yii::$app->url_manager->create_url(['design-groups', 'row_id' => $row_id, 'category' => $category]), 'themes' => \common\models\Themes::find()->as_array()->all()]);
    }
    public function action_group_bar()
    {
        $this->layout = false;
        $group_id = Yii::$app->request->get('group_id', 0);
        $row_id = Yii::$app->request->get('row_id', 0);
        $category = Yii::$app->request->get('category', 0);
        $category_id = Yii::$app->request->get('category_id', 0);
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        return $this->render('group-bar.tpl', ['groupId' => $group_id, 'group' => Groups::get_group($group_id), 'rowId' => $row_id, 'category' => $category, 'categoryId' => $category_id]);
    }
    public function action_group_category_bar()
    {
        $this->layout = false;
        $group_id = Yii::$app->request->get('group_id', 0);
        $row_id = Yii::$app->request->get('row_id', 0);
        $category = Yii::$app->request->get('name', 0);
        $title = Yii::$app->request->get('title', 0);
        $category_id = Yii::$app->request->get('category_id', 0);
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        return $this->render('group-category-bar.tpl', ['groupId' => $group_id, 'group' => Groups::get_group($group_id), 'rowId' => $row_id, 'category' => $category, 'title' => $title, 'categoryId' => $category_id]);
    }
    public function action_save()
    {
        $this->layout = false;
        $group_id = Yii::$app->request->post('group_id', 0);
        $group = Yii::$app->request->post('group', []);
        $language_id = \Yii::$app->settings->get('languages_id');
        $file_path = Groups::group_file_path() . DIRECTORY_SEPARATOR;
        $image_path = Common_Images::get_fs_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR . $group_id . DIRECTORY_SEPARATOR;
        $new_group = false;
        $info_content = [];
        $design_boxes_groups = Design_Boxes_Groups::find_one(['id' => $group_id]);
        if (!$design_boxes_groups) {
            $design_boxes_groups = new Design_Boxes_Groups();
            if ($group_id) {
                $design_boxes_groups->id = $group_id;
            }
            $design_boxes_groups->date_added = new \yii\db\Expression('NOW()');
            $error = Groups::create_group($group);
            if ($error) {
                return json_encode($error);
            }
            $new_group = true;
        }
        $name = Array_Helper::get_value($group, ['languages', $language_id, 'title'], false);
        if ($name) {
            $design_boxes_groups->name = $name;
            $info_content['name'] = $name;
        }
        $comment = Array_Helper::get_value($group, ['languages', $language_id, 'description'], false);
        if ($comment) {
            $design_boxes_groups->comment = $comment;
            $info_content['comment'] = $comment;
        }
        if ($design_boxes_groups->file != $group['file']) {
            if (substr($group['file'], -4) != '.zip') {
                $group['file'] = $group['file'] . '.zip';
            }
            if (is_file($file_path . $group['file']) && !$new_group) {
                return json_encode(['error' => sprintf('File "%s" already exists, please enter other name', $group['file']), 'focus' => 'group[file]']);
            }
            if (is_file($file_path . $design_boxes_groups->file)) {
                rename($file_path . $design_boxes_groups->file, $file_path . $group['file']);
            }
            $design_boxes_groups->file = $group['file'];
        }
        $design_boxes_groups->page_type = $group['page_type'];
        $info_content['page_type'] = $group['page_type'];
        $design_boxes_groups->status = $group['status'] ? $group['status'] : 0;
        $design_boxes_groups->category = $group['category'];
        $info_content['groupCategory'] = $group['category'];
        $design_boxes_groups->save();
        $group_id = $design_boxes_groups->get_primary_key();
        $info_content['languages'] = [];
        $languages = \common\helpers\Language::get_languages();
        foreach ($languages as $language) {
            $design_boxes_groups_languages = Design_Boxes_Groups_Languages::find_one(['boxes_group_id' => $group_id, 'language_id' => $language['id']]);
            if (!$design_boxes_groups_languages) {
                $design_boxes_groups_languages = new Design_Boxes_Groups_Languages();
                $design_boxes_groups_languages->boxes_group_id = $group_id;
                $design_boxes_groups_languages->language_id = $language['id'];
            }
            $design_boxes_groups_languages->title = Array_Helper::get_value($group, ['languages', $language['id'], 'title'], '');
            $design_boxes_groups_languages->description = Array_Helper::get_value($group, ['languages', $language['id'], 'description'], '');
            $design_boxes_groups_languages->save();
            if ($design_boxes_groups_languages->title || $design_boxes_groups_languages->description) {
                $info_content['languages'][$language['code']] = ['title' => $design_boxes_groups_languages->title, 'description' => $design_boxes_groups_languages->description];
            }
        }
        $info_content['images'] = [];
        $old_images = Design_Boxes_Groups_Images::find()->where(['boxes_group_id' => $group_id])->as_array()->all();
        if (is_array($old_images)) {
            foreach ($old_images as $key => $old_image) {
                $key = array_search($old_image['file'], $group['images']);
                if ($key === false) {
                    $file = $image_path . $old_image['file'];
                    if (is_file($file)) {
                        unlink($file);
                    }
                    Design_Boxes_Groups_Images::delete_all(['boxes_group_image_id' => $old_image['boxes_group_image_id']]);
                } else {
                    $info_content['images'][] = $group['images'][$key];
                    unset($group['images'][$key]);
                }
            }
        }
        if (isset($group['images']) && is_array($group['images'])) {
            foreach ($group['images'] as $image) {
                $design_boxes_groups_images = new Design_Boxes_Groups_Images();
                $design_boxes_groups_images->file = $image;
                $design_boxes_groups_images->save();
                $info_content['images'][] = $image;
            }
        }
        if (isset($group['image_upload']) && is_array($group['image_upload'])) {
            foreach ($group['image_upload'] as $image) {
                if ($image_name = Uploads::move($image, DIR_WS_IMAGES . 'widget-groups' . DIRECTORY_SEPARATOR . $group_id . DIRECTORY_SEPARATOR, false)) {
                    $design_boxes_groups_images = new Design_Boxes_Groups_Images();
                    $design_boxes_groups_images->file = $image_name;
                    $design_boxes_groups_images->boxes_group_id = $group_id;
                    $design_boxes_groups_images->save();
                    $info_content['images'][] = $image_name;
                }
            }
        }
        chmod($file_path . $group['file'], 0755);
        $zip = new \Zip_Archive();
        if ($zip->open($file_path . $group['file'], \Zip_Archive::CREATE)) {
            $zip->delete_name('images/');
            $zip->delete_name('info.json');
            $zip->add_from_string('info.json', json_encode($info_content));
            foreach ($info_content['images'] as $image) {
                if (is_file($image_path . $image)) {
                    $zip->add_file($image_path . $image, 'images/' . $image);
                }
            }
            $zip->close();
        }
        $success_message = 'Saved';
        return json_encode(['text' => $success_message, 'html' => $this->action_edit()]);
    }
    public function action_delete_group()
    {
        $group_id = Yii::$app->request->post('groupId');
        $group = Design_Boxes_Groups::find_one(['id' => $group_id]);
        if (!$group) {
            return json_encode(['error' => GROUP_NOT_FOUND]);
        }
        $file_path = Groups::group_file_path();
        if (is_file($file_path . DIRECTORY_SEPARATOR . $group->file)) {
            unlink($file_path . DIRECTORY_SEPARATOR . $group->file);
        }
        Groups::synchronize();
        return json_encode(['text' => TEXT_REMOVED]);
    }
    public function action_delete_category()
    {
        $category_id = Yii::$app->request->post('categoryId');
        Design_Boxes_Groups_Category::delete_all(['boxes_group_category_id' => $category_id]);
        return json_encode(['text' => TEXT_REMOVED]);
    }
    public function action_switch_status()
    {
        $this->layout = false;
        $group_id = Yii::$app->request->post('groupId');
        $status = Yii::$app->request->post('status');
        $group = Design_Boxes_Groups::find_one(['id' => $group_id]);
        if (!$group) {
            return json_encode(['error' => GROUP_NOT_FOUND]);
        }
        $group->status = $status;
        $group->save();
        return json_encode(['text' => MESSAGE_SAVED]);
    }
    public function action_wizard()
    {
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/groups'), 'title' => 'Create theme'];
        $this->view->heading_title = 'Create theme';
        $theme_name = Yii::$app->request->get('theme_name', '');
        $language_id = Yii::$app->settings->get('languages_id');
        $image_fs_path = Common_Images::get_fs_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR;
        $image_ws_path = Common_Images::get_ws_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR;
        $group_lists = [['title' => TEXT_HEADER, 'category' => 'header', 'multiSelect' => false], ['title' => TEXT_FOOTER, 'category' => 'footer', 'multiSelect' => true], ['title' => 'Header menu', 'category' => 'header-menu', 'multiSelect' => false], ['title' => 'Headings', 'category' => 'headings', 'multiSelect' => false], ['title' => 'Buttons', 'category' => 'buttons', 'multiSelect' => false], ['title' => 'Price', 'category' => 'price', 'multiSelect' => false], ['title' => 'Form', 'category' => 'form', 'multiSelect' => false], ['title' => TEXT_HOME, 'category' => 'main', 'multiSelect' => true], ['title' => TEXT_PRODUCT, 'category' => 'product', 'multiSelect' => true]];
        $groups_categories = Design_Boxes_Groups_Category::find()->as_array()->all();
        foreach ($groups_categories as $groups_category) {
            $group_lists[] = ['title' => $groups_category['name'], 'category' => $groups_category['name'], 'multiSelect' => false];
        }
        $group_lists[] = ['title' => TEXT_COLOR_SCHEME, 'category' => 'color', 'multiSelect' => false];
        $group_lists[] = ['title' => TEXT_FONTS, 'category' => 'font', 'multiSelect' => false];
        $file_path = Groups::group_file_path() . DIRECTORY_SEPARATOR;
        foreach ($group_lists as $category_key => $category) {
            $list = Design_Boxes_Groups::find()->alias('g')->left_join(Design_Boxes_Groups_Languages::table_name() . ' gl', 'g.id = gl.boxes_group_id and gl.language_id = ' . $language_id)->where(['category' => $category['category']])->as_array()->all();
            if ($list && is_array($list)) {
                foreach ($list as $item_key => $item) {
                    $images = Design_Boxes_Groups_Images::find()->where(['boxes_group_id' => $item['id']])->as_array()->all();
                    foreach ($images as $image_key => $image) {
                        if (is_file($image_fs_path . $item['id'] . DIRECTORY_SEPARATOR . $image['file'])) {
                            $images[$image_key]['image'] = $image_ws_path . $item['id'] . DIRECTORY_SEPARATOR . $image['file'];
                        } else {
                            unset($images[$image_key]);
                        }
                    }
                    $list[$item_key]['images'] = $images;
                }
                if ($category['category'] == 'color') {
                    foreach ($list as $item_key => $item) {
                        $colors = [];
                        $zip = new \Zip_Archive();
                        if ($zip->open($file_path . $item['file'], \Zip_Archive::CREATE)) {
                            $json = $zip->get_from_name('data.json');
                            $group_data = json_decode($json, true);
                            $zip->close();
                            if (!is_array($group_data)) {
                                continue;
                            }
                            foreach ($group_data as $color) {
                                if ($color['main_style'] ?? false) {
                                    $colors[$color['value']][] = $color['name'];
                                }
                            }
                        }
                        $list[$item_key]['colors'] = $colors;
                    }
                }
                $group_lists[$category_key]['list'] = $list;
                $files_query = Design_Boxes_Tmp::find()->alias('b')->select(['bs.setting_value'])->distinct()->left_join(Design_Boxes_Settings_Tmp::table_name() . ' bs', 'b.id = bs.box_id')->where(['b.theme_name' => $theme_name, 'b.block_name' => $category['category'], 'bs.setting_name' => 'from_file'])->as_array()->all();
                $files = [];
                foreach ($files_query as $file) {
                    $files[] = $file['setting_value'];
                }
                $group_lists[$category_key]['files'] = $files;
            } else {
                unset($group_lists[$category_key]);
            }
        }
        $theme_title = '';
        if ($theme_name) {
            $theme = Themes::find_one(['theme_name' => $theme_name]);
            if ($theme) {
                $theme_title = $theme->title;
            }
        }
        return $this->render('wizard.tpl', ['themeName' => $theme_name, 'theme_name' => $theme_name, 'themeTitle' => $theme_title, 'group_id' => Yii::$app->request->get('group_id', 0), 'groupLists' => $group_lists, 'designer_mode' => $this->designer_mode, 'menu' => 'wizard']);
    }
    public function action_create_theme()
    {
        $title = Yii::$app->request->post('title');
        $group_id = Yii::$app->request->post('group_id', 0);
        $theme_name = \common\classes\design::page_name($title);
        if (in_array($theme_name, ['new_theme', 'origin'])) {
            return json_encode(['error' => 'This name reserved for the system']);
        }
        if (Themes::find_one(['theme_name' => $theme_name])) {
            return json_encode(['error' => THEME_ALREADY_EXISTS]);
        }
        $ts = Themes_Settings::find()->where(['theme_name' => 'origin', 'setting_name' => 'block_copy_theme'])->as_array()->one();
        if ($ts['setting_value'] ?? false) {
            if ($ts['setting_value'] + 600 > time()) {
                return json_encode(['error' => SYSTEM_NOT_READY]);
            } else {
                Themes_Settings::delete_all(['setting_name' => 'block_copy_theme']);
            }
        }
        $themes = new Themes();
        $themes->theme_name = $theme_name;
        $themes->title = $title;
        $themes->description = '';
        $themes->install = 1;
        $themes->is_default = 0;
        $themes->sort_order = Themes::find()->max('sort_order') + 1;
        $themes->parent_theme = '0';
        $themes->themes_group_id = $group_id;
        $themes->save();
        if ($themes->errors) {
            return json_encode(['error' => $themes->errors]);
        }
        $theme_setting = new Themes_Settings();
        $theme_setting->theme_name = 'origin';
        $theme_setting->setting_group = 'hide';
        $theme_setting->setting_name = 'block_copy_theme';
        $theme_setting->setting_value = (string) time();
        $theme_setting->save();
        Design_Boxes::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Design_Boxes_Tmp::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Design_Boxes_Settings::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Design_Boxes_Settings_Tmp::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Themes_Settings::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Themes_Styles::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Themes_Styles_Main::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Themes_Styles_Groups::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        Themes_Styles_Cache::update_all(['theme_name' => $theme_name], ['theme_name' => 'new_theme']);
        $bottom_css = '';
        $ds = DIRECTORY_SEPARATOR;
        $bottom_file = DIR_FS_CATALOG . 'themes' . $ds . 'basic' . $ds . 'css' . $ds . 'bottom.css';
        if (file_exists($bottom_file)) {
            $bottom_css = file_get_contents($bottom_file);
        }
        $bottom_css .= \backend\design\Style::get_css($theme_name, ['.b-bottom']);
        $bottom_css = \frontend\design\Info::minify_css($bottom_css);
        $file_path = DIR_FS_CATALOG . 'themes' . $ds . $theme_name . $ds . 'css' . $ds;
        File_Helper::create_directory($file_path);
        file_put_contents($file_path . 'style.css', $bottom_css);
        return json_encode(['text' => THEME_ADDED, 'theme_name' => $theme_name]);
    }
    public function action_copy_new_theme()
    {
        if (!Design_Boxes_Tmp::find_one(['theme_name' => 'new_theme'])) {
            Theme::copy_theme('new_theme', 'origin', 'copy');
            Style::create_cache('new_theme');
            Themes_Settings::delete_all(['setting_name' => 'block_copy_theme']);
            return 'copied';
        }
        return 'exist';
    }
    public function action_set_group()
    {
        $theme_name = Yii::$app->request->post('theme_name');
        $group_ids = Yii::$app->request->post('group_id', 0);
        $category = $category_block_name = Yii::$app->request->post('category', 0);
        $path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']);
        $odd_data = [];
        $new_data = [];
        $boxes = Design_Boxes_Tmp::find()->where(['theme_name' => $theme_name])->and_where(['or', ['block_name' => $category], ['widget_params' => $category]])->as_array()->all();
        foreach ($boxes as $box) {
            if ($box['widget_params'] == $category) {
                $category_block_name = 'block-' . $box['id'];
            }
            $odd_data[$category][] = Theme::blocks_tree($box['id']);
            Theme::delete_block($box['id']);
        }
        Design_Boxes_Tmp::delete_all(['theme_name' => $theme_name, 'block_name' => $category]);
        $errors = [];
        $sort_order = 1;
        foreach ($group_ids as $group_id) {
            $group = Design_Boxes_Groups::find_one($group_id);
            if (!$group) {
                $errors[] = 'Group not found: ' . $group_id;
                continue;
            }
            $file = $group->file;
            if (!is_file($path . DIRECTORY_SEPARATOR . $file)) {
                $errors[] = 'Group file not found: ' . $file;
                continue;
            }
            $zip = new \Zip_Archive();
            if ($zip->open($path . DIRECTORY_SEPARATOR . $file, \Zip_Archive::CREATE) === true) {
                $json = $zip->get_from_name('data.json');
                $group_data = json_decode($json, true);
                foreach ($group_data as $block_name => $data) {
                    if (!is_int($block_name)) {
                        $new_data[$block_name][] = $data;
                        $boxes = Design_Boxes_Tmp::find()->where(['theme_name' => $theme_name, 'block_name' => $block_name])->as_array()->all();
                        foreach ($boxes as $box) {
                            $odd_data[$block_name][] = Theme::blocks_tree($box['id']);
                            Theme::delete_block($box['id']);
                        }
                        Design_Boxes_Tmp::delete_all(['theme_name' => $theme_name, 'block_name' => $block_name]);
                    } else {
                        $new_data[$category_block_name][] = $data;
                    }
                }
                $added_pages_json = $zip->get_from_name('addedPages.json');
                if ($added_pages_json) {
                    $added_pages = json_decode($added_pages_json, true);
                    if (is_array($added_pages)) {
                        foreach ($added_pages as $added_page) {
                            $new_theme_pge = new Themes_Settings();
                            $new_theme_pge->theme_name = $theme_name;
                            $new_theme_pge->setting_group = 'added_page';
                            $new_theme_pge->setting_name = $added_page['setting_name'];
                            $new_theme_pge->setting_value = $added_page['setting_value'];
                            $new_theme_pge->save(false);
                        }
                    }
                }
                $zip->close();
            }
            $params = [];
            $params['theme_name'] = $theme_name;
            $params['block_name'] = $category_block_name;
            $params['sort_order'] = $sort_order;
            $sort_order = $sort_order + 10;
            $import_block = Theme::import_block($path . DIRECTORY_SEPARATOR . $file, $params, $file);
            if (!is_array($import_block)) {
                $errors[] = $import_block;
            }
        }
        if (count($errors)) {
            return json_encode(['error' => $errors]);
        }
        Theme::elements_save($theme_name);
        Design_Boxes_Cache::delete_all(['theme_name' => $theme_name]);
        $log = ['old' => $odd_data, 'new' => $new_data, 'theme_name' => $theme_name];
        if (Theme::extension_widgets()) {
            $log['extensionWidgets'] = Theme::extension_widgets();
        }
        Steps::set_group($log);
        return json_encode(['text' => GROUP_APPLIED, 'widgets' => Theme::extension_widgets()]);
    }
    public function action_set_styles()
    {
        $theme_name = Yii::$app->request->post('theme_name');
        $group_ids = Yii::$app->request->post('group_id', 0);
        $category = Yii::$app->request->post('category', 0);
        $path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']);
        $group_id = $group_ids[0];
        if ($category == 'color') {
            $category = ['color', 'color-var', 'color-opacity'];
        } elseif ($category == 'font') {
            $category = ['font', 'font-var'];
        }
        $group = Design_Boxes_Groups::find_one($group_id);
        if (!$group) {
            return json_encode(['error' => GROUP_NOT_FOUND . ': ' . $group_id]);
        }
        $file = $group->file;
        if (!is_file($path . DIRECTORY_SEPARATOR . $file)) {
            return json_encode(['error' => GROUP_FILE_NOT_FOUND . ': ' . $file]);
        }
        $zip = new \Zip_Archive();
        if ($zip->open($path . DIRECTORY_SEPARATOR . $file, \Zip_Archive::CREATE) !== true) {
            return json_encode(['error' => 'ZipArchive error']);
        }
        $json = $zip->get_from_name('data.json');
        $styles = json_decode($json, true);
        $new_styles = [];
        $new_groups = [];
        $old_styles = Themes_Styles_Main::find()->where(['theme_name' => $theme_name, 'type' => $category])->as_array()->all();
        $old_groups = Themes_Styles_Groups::find()->where(['theme_name' => $theme_name])->as_array()->all();
        Themes_Styles_Main::delete_all(['theme_name' => $theme_name, 'type' => $category]);
        foreach ($styles['main'] as $style) {
            $themes_styles = new Themes_Styles_Main();
            $themes_styles->theme_name = $theme_name;
            $themes_styles->name = $style['name'];
            $themes_styles->value = $style['value'];
            $themes_styles->type = $style['type'];
            $themes_styles->sort_order = $style['sort_order'];
            $themes_styles->group_id = $style['group_id'];
            $themes_styles->save();
            $new_styles[] = array_merge($style, ['theme_name' => $theme_name]);
            if ($style['type'] == 'font' && isset($style['font_settings'])) {
                $font_settings = str_replace('themes/<theme_name>', 'themes/' . $theme_name, $style['font_settings']);
                $theme_setting = Themes_Settings::find()->where(['theme_name' => $theme_name, 'setting_group' => 'extend', 'setting_name' => 'font_added'])->and_where(['like', 'setting_value', $style['value']])->one();
                if (!$theme_setting || !preg_match("/font-family: [\\'\"]{0,1}" . $style['value'] . "[\\'\"]{0,1};/", $theme_setting->setting_value)) {
                    $files = [];
                    preg_match_all("/url\\([\\'\"]{0,1}(themes\\/" . $theme_name . "[^'^\"^\\)]+)\\?[^'^\"^)]+[\\'\"]{0,1}\\)/", $font_settings, $files);
                    if (isset($files[1]) && is_array($files[1])) {
                        File_Helper::create_directory(DIR_FS_CATALOG . 'themes/' . $theme_name . '/fonts/');
                        foreach ($files[1] as $file) {
                            $file_path = explode('/', $file);
                            $file_path = explode('\\', end($file_path));
                            $file_name = end($file_path);
                            $zip->extract_to(DIR_FS_CATALOG . 'themes/' . $theme_name . '/fonts/', $file_name);
                        }
                    }
                    $theme_setting = new Themes_Settings();
                    $theme_setting->theme_name = $theme_name;
                    $theme_setting->setting_group = 'extend';
                    $theme_setting->setting_name = 'font_added';
                    $theme_setting->setting_value = $font_settings;
                    $theme_setting->save();
                }
            }
        }
        Themes_Styles_Groups::delete_all(['theme_name' => $theme_name]);
        foreach ($styles['groups'] as $group) {
            $themes_styles = new Themes_Styles_Groups();
            $themes_styles->theme_name = $theme_name;
            $themes_styles->group_id = $group['group_id'];
            $themes_styles->group_name = $group['group_name'];
            $themes_styles->sort_order = $group['sort_order'];
            $themes_styles->tab = $group['tab'];
            $themes_styles->save();
            $new_groups[] = array_merge($group, ['theme_name' => $theme_name]);
        }
        $zip->close();
        Design_Boxes_Cache::delete_all(['theme_name' => $theme_name]);
        Style::create_cache($theme_name);
        $themes_path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['themes', $theme_name, 'cache']);
        if (file_exists($themes_path)) {
            File_Helper::remove_directory($themes_path);
        }
        Steps::set_styles(['old' => $old_styles, 'new' => $new_styles, 'old_groups' => $old_groups, 'new_groups' => $new_groups, 'type' => $category, 'theme_name' => $theme_name]);
        return json_encode(['text' => TEXT_APPLIED]);
    }
    public function action_set_css()
    {
        $theme_name = Yii::$app->request->post('theme_name');
        $group_ids = Yii::$app->request->post('group_id', 0);
        $category = Yii::$app->request->post('category', 0);
        $path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']);
        $group_id = $group_ids[0];
        $group = Design_Boxes_Groups::find_one($group_id);
        if (!$group) {
            return json_encode(['error' => GROUP_NOT_FOUND . ': ' . $group_id]);
        }
        $file = $group->file;
        if (!is_file($path . DIRECTORY_SEPARATOR . $file)) {
            return json_encode(['error' => GROUP_FILE_NOT_FOUND . ': ' . $file]);
        }
        $zip = new \Zip_Archive();
        if ($zip->open($path . DIRECTORY_SEPARATOR . $file, \Zip_Archive::CREATE) !== true) {
            return json_encode(['error' => 'ZipArchive error']);
        }
        $json = $zip->get_from_name('data.json');
        $styles = json_decode($json, true);
        $old_styles = Style::get_css_elements($theme_name, $category);
        Style::set_css_elements($theme_name, $styles);
        Steps::set_css(['old' => $old_styles, 'new' => $styles, 'theme_name' => $theme_name]);
        return json_encode(['text' => TEXT_APPLIED]);
    }
    public function action_add_category()
    {
        $parent_category = Yii::$app->request->post('category', '');
        $name = Yii::$app->request->post('name');
        if (Design_Boxes_Groups_Category::find_one(['parent_category' => $parent_category, 'name' => $name])) {
            return json_encode(['error' => sprintf(CATEGORY_ALREADY_EXISTS, $parent_category)]);
        }
        $category = new Design_Boxes_Groups_Category();
        $category->name = $name;
        $category->parent_category = $parent_category;
        $category->save();
        if ($category->errors) {
            return json_encode(['error' => $category->errors]);
        }
        return json_encode(['text' => TEXT_APPLIED]);
    }
    public function action_get_pages()
    {
        $theme_name = Yii::$app->request->get('theme_name', '');
        $page_groups = Frontend_Structure::get_page_groups();
        $categories = [];
        foreach ($page_groups as $page_group) {
            $categories[$page_group['name']] = ['title' => $page_group['title'] ? $page_group['title'] : $page_group['name'], 'key' => $page_group['name'], 'folder' => true, 'checkbox' => false];
        }
        $pages = Frontend_Structure::get_pages();
        foreach ($pages as $page) {
            if (!($page['group'] ?? false)) {
                continue;
            }
            if (!isset($categories[$page['group']])) {
                $categories[$page['group']] = ['title' => $page['group'], 'children' => [], 'folder' => true];
            }
            if (!isset($categories[$page['group']]['children'])) {
                $categories[$page['group']]['children'] = [];
            }
            $categories[$page['group']]['children'][] = ['title' => $page['title'], 'key' => $page['page_name'], 'checkbox' => true];
        }
        $pages_tree = [];
        foreach ($categories as $category) {
            if ($category['children'] ?? false) {
                $pages_tree[] = $category;
            }
        }
        return json_encode($pages_tree);
    }
    public function action_get_groups()
    {
        $language_id = Yii::$app->settings->get('languages_id');
        $names = Yii::$app->request->get('names', []);
        $theme_name = Yii::$app->request->get('theme_name', '');
        $image_fs_path = Common_Images::get_fs_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR;
        $image_ws_path = Common_Images::get_ws_catalog_images_path() . 'widget-groups' . DIRECTORY_SEPARATOR;
        $group_lists = [];
        $groups = ['header' => ['title' => TEXT_HEADER, 'multiSelect' => false], 'footer' => ['title' => TEXT_FOOTER, 'multiSelect' => true], 'header-menu' => ['title' => 'Header menu', 'multiSelect' => false], 'main' => ['title' => TEXT_HOME, 'multiSelect' => true], 'product' => ['title' => TEXT_PRODUCT, 'multiSelect' => true]];
        foreach ($names as $category) {
            $list = Design_Boxes_Groups::find()->alias('g')->left_join(Design_Boxes_Groups_Languages::table_name() . ' gl', 'g.id = gl.boxes_group_id and gl.language_id = ' . $language_id)->where(['category' => $category])->as_array()->all();
            if ($list && is_array($list)) {
                if ($groups[$category]) {
                    $group_lists[$category] = $groups[$category];
                } else {
                    $group_lists[$category] = ['multiSelect' => true];
                }
                foreach ($list as $item_key => $item) {
                    $images = Design_Boxes_Groups_Images::find()->where(['boxes_group_id' => $item['id']])->as_array()->all();
                    foreach ($images as $image_key => $image) {
                        if (is_file($image_fs_path . $item['id'] . DIRECTORY_SEPARATOR . $image['file'])) {
                            $images[$image_key]['image'] = $image_ws_path . $item['id'] . DIRECTORY_SEPARATOR . $image['file'];
                        } else {
                            unset($images[$image_key]);
                        }
                    }
                    $list[$item_key]['images'] = $images;
                }
                $group_lists[$category]['list'] = $list;
            }
            $files_query = Design_Boxes_Tmp::find()->alias('b')->select(['bs.setting_value'])->distinct()->left_join(Design_Boxes_Settings_Tmp::table_name() . ' bs', 'b.id = bs.box_id')->where(['b.theme_name' => $theme_name, 'b.block_name' => $category, 'bs.setting_name' => 'from_file'])->as_array()->all();
            $files = [];
            foreach ($files_query as $file) {
                $files[] = $file['setting_value'];
            }
            $group_lists[$category]['files'] = $files;
        }
        return json_encode($group_lists);
    }
}