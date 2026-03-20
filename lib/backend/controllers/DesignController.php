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

use backend\design\Backups;
use backend\design\Data;
use backend\design\File_Manager;
use backend\design\Frontend_Structure;
use backend\design\Groups;
use backend\design\Steps;
use backend\design\Style;
use backend\design\Theme;
use backend\design\Uploads;
use backend\models\Admin;
use common\classes\design;
use common\helpers\Language;
use common\helpers\Translation;
use common\models\Design_Boxes;
use common\models\Design_Boxes_Cache;
use common\models\Design_Boxes_Settings_Tmp;
use common\models\Design_Boxes_Tmp;
use common\models\Platforms;
use common\models\Themes_Settings;
use common\models\Themes_Styles;
use common\models\Themes_Styles_Groups;
use common\models\Themes_Styles_Main;
use frontend\design\Info;
use Yii;
use yii\helpers\Array_Helper;
use yii\helpers\File_Helper;
/**
 *
 */
class Design_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_HEADING_THEMES'];
    public $designer_mode = '';
    public $designer_mode_title = '';
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('admin/design');
        if (Yii::$app->request->get('theme_name') == \common\classes\design::page_name(BACKEND_THEME_NAME)) {
            \common\helpers\Acl::check_access(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_HEADING_THEMES', 'BOX_BACKEND_THEME_EDIT']);
        }
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
    /**
     *
     */
    public function action_index()
    {
        return Yii::$app->get_response()->redirect(['design/themes']);
        $request = Yii::$app->request->get();
        if ($request['resource'] && $request['action']) {
            $params = json_decode(file_get_contents('php://input'), true);
            $resource = '\backend\design\data\\' . yii\helpers\Inflector::camelize($request['resource']);
            $action = yii\helpers\Inflector::variablize($request['action']);
            if (!class_exists($resource)) {
                return json_encode(['error' => 'Resource "' . $request['resource'] . '"' . " doesn't exist"]);
            }
            if (!method_exists($resource, $action)) {
                return json_encode(['error' => 'Action "' . $request['action'] . '"' . " doesn't exist"]);
            }
            $response = $resource::$action($params);
            return json_encode($response);
        }
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/themes'), 'title' => BOX_HEADING_THEMES];
        $this->view->heading_title = BOX_HEADING_THEMES;
        Data::add_js_data(['tr' => \common\helpers\Translation::translations_for_js(['TEXT_ADD_THEME'], false)]);
        $this->layout = false;
        return $this->render('designer.tpl');
    }
    public function action_themes()
    {
        $group_id = $request = Yii::$app->request->get('group_id', 0);
        //$this->topButtons[] = '<a href="' . Yii::$app->urlManager->createUrl(['design/theme-add', 'group_id' => $groupId]) . '" class="btn btn-primary btn-add-theme">' . TEXT_ADD_THEME . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design-groups/wizard', 'group_id' => $group_id]) . '" class="btn btn-primary">' . CREATE_THEME . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design/theme-import', 'group_id' => $group_id]) . '" class="btn btn-primary btn-import-theme">' . IMPORT_THEME . '</a>';
        if ($group_id) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('design/themes') . '" class="btn">' . BACK_TO_ROOT . '</a>';
        } else {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('design/add-group') . '" class="btn create-group">' . ADD_THEME_GROUP . '</a>';
        }
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/themes'), 'title' => BOX_HEADING_THEMES];
        $this->view->heading_title = BOX_HEADING_THEMES;
        $themes = Theme::themes_by_group($group_id);
        foreach ($themes as $key => $theme) {
            $theme_image = Themes_Settings::find_one(['theme_name' => $theme['theme_name'], 'setting_group' => 'hide', 'setting_name' => 'theme_image'])->setting_value ?? null;
            if ($theme_image) {
                $themes[$key]['theme_image'] = $theme_image;
            }
            $themes[$key]['platforms'] = \common\models\Platforms_To_Themes::find()->alias('p2t')->select(['p.platform_name', 'p.ssl_enabled', 'p.platform_url'])->left_join(Platforms::table_name() . ' p', 'p.platform_id = p2t.platform_id')->where(['p2t.theme_id' => $theme['id']])->as_array()->all();
        }
        if ($group_id) {
            return $this->render('themes.tpl', ['themes' => $themes, 'group_id' => $group_id, 'designer_mode' => $this->designer_mode]);
        }
        $themes_groups = \common\models\Themes_Groups::find()->as_array()->all();
        foreach ($themes_groups as $group) {
            $group['link'] = Yii::$app->url_manager->create_url(['design/themes', 'group_id' => $group['themes_group_id']]);
            $group['themes'] = Theme::themes_by_group($group['themes_group_id']);
            $themes[] = $group;
        }
        usort($themes, function ($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });
        return $this->render('themes.tpl', ['themes' => $themes, 'group_id' => 0, 'designer_mode' => $this->designer_mode]);
    }
    public function action_save_admin_data()
    {
        $post = Yii::$app->request->post();
        $admin = new Admin();
        $admin->save_additional_data($post);
    }
    public function action_theme_add()
    {
        $themes = [];
        $query = tep_db_query('select id, theme_name, title from ' . TABLE_THEMES . " where install = '1' order by sort_order");
        while ($theme = tep_db_fetch_array($query)) {
            $themes[] = $theme;
        }
        $group_id = Yii::$app->request->get('group_id');
        $this->layout = 'popup.tpl';
        return $this->render('theme-add.tpl', ['themes' => $themes, 'group_id' => $group_id, 'action' => Yii::$app->url_manager->create_url('design/theme-add-action')]);
    }
    public function action_theme_copy()
    {
        $group_id = Yii::$app->request->get('group_id');
        $theme_name = Yii::$app->request->get('theme_name');
        $this->layout = 'popup.tpl';
        return $this->render('theme-copy.tpl', ['group_id' => $group_id, 'theme_name' => $theme_name, 'action' => Yii::$app->url_manager->create_url('design/theme-add-action')]);
    }
    public function action_theme_import()
    {
        $group_id = Yii::$app->request->get('group_id');
        $this->layout = 'popup.tpl';
        return $this->render('theme-import.tpl', ['group_id' => $group_id, 'action' => Yii::$app->url_manager->create_url('design/theme-add-action')]);
    }
    public function action_theme_add_action()
    {
        $params = Yii::$app->request->get();
        $this->layout = false;
        if (!$params['title']) {
            return json_encode(['code' => 1, 'text' => THEME_TITLE_REQUIRED]);
        }
        if (!$params['theme_name']) {
            $params['theme_name'] = \common\classes\design::page_name($params['title']);
        }
        if (!preg_match("/^[a-z0-9_\\-]+\$/", $params['theme_name'])) {
            return json_encode(['code' => 1, 'text' => 'Enter only lowercase letters and numbers for theme name']);
        }
        $theme = tep_db_query('select id from ' . TABLE_THEMES . " where theme_name = '" . tep_db_input($params['theme_name']) . "'");
        if (tep_db_num_rows($theme) > 0) {
            return json_encode(['code' => 1, 'text' => 'Theme with this name already exist']);
        }
        $query = tep_db_query('select id, sort_order from ' . TABLE_THEMES . " where install = '1'");
        while ($theme = tep_db_fetch_array($query)) {
            $sql_data_array = ['sort_order' => $theme['sort_order'] + 1];
            tep_db_perform(TABLE_THEMES, $sql_data_array, 'update', " id = '" . $theme['id'] . "'");
        }
        $sql_data_array = ['theme_name' => $params['theme_name'], 'title' => $params['title'], 'install' => 1, 'is_default' => 0, 'sort_order' => 0, 'themes_group_id' => $params['group_id'], 'parent_theme' => isset($params['parent_theme']) && $params['parent_theme'] && $params['theme_source'] == 'theme' && $params['parent_theme_files'] == 'link' ? $params['parent_theme'] : 0];
        tep_db_perform(TABLE_THEMES, $sql_data_array);
        if (isset($params['parent_theme']) && $params['parent_theme'] && $params['theme_source'] == 'theme') {
            Theme::copy_theme($params['theme_name'], $params['parent_theme'], $params['parent_theme_files']);
            Theme::copy_theme($params['theme_name'] . '-mobile', $params['parent_theme'] . '-mobile', $params['parent_theme_files']);
        }
        if ($params['theme_source'] == 'url' || $params['theme_source'] == 'computer') {
            $path = \Yii::get_alias('@webroot');
            $path .= DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
            $path .= 'themes' . DIRECTORY_SEPARATOR . $params['theme_name'] . DIRECTORY_SEPARATOR;
            if ($params['theme_source'] == 'url') {
                $theme_file = $params['theme_source_url'];
            } else {
                $theme_file = \Yii::get_alias('@webroot');
                $theme_file .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $params['theme_source_computer'];
            }
            if (!\backend\design\Theme::import($params['theme_name'], $theme_file)) {
                return json_encode(['code' => 1, 'text' => 'Wron theme file']);
            }
        }
        Style::create_cache($params['theme_name']);
        Style::create_cache($params['theme_name'] . '-mobile');
        return json_encode(['code' => 2, 'text' => 'Theme added']);
    }
    public function action_theme_remove()
    {
        $params = Yii::$app->request->get();
        Theme::theme_remove($params['theme_name']);
        Theme::theme_remove($params['theme_name'] . '-mobile');
        return Yii::$app->get_response()->redirect(['design/themes']);
    }
    public function action_theme_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $params = Yii::$app->request->get();
        $language_query = tep_db_fetch_array(tep_db_query('select code from ' . TABLE_LANGUAGES . " where languages_id = '" . $languages_id . "' order by sort_order"));
        $language_code = $language_query['code'];
        $this->top_buttons[] = '<span class="redo-buttons"></span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/elements'), 'title' => BOX_HEADING_MAIN_STYLES . ' "' . Theme::get_theme_title($params['theme_name']) . '"'];
        $this->view->heading_title = BOX_HEADING_MAIN_STYLES . ' "' . Theme::get_theme_title($params['theme_name']) . '"';
        $css = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'css' and setting_name = 'css'"));
        $javascript = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'javascript' and setting_name = 'javascript'"));
        return $this->render('theme-edit.tpl', ['menu' => 'theme-edit', 'theme_name' => $params['theme_name'] ? $params['theme_name'] : 'theme-1', 'clear_url' => $params['theme_name'] ? true : false, 'css' => $css['setting_value'] ?? null, 'javascript' => $javascript['setting_value'] ?? null, 'language_code' => $language_code, 'designer_mode' => $this->designer_mode]);
    }
    public function action_css()
    {
        $params = Yii::$app->request->get();
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/css'), 'title' => 'CSS "' . Theme::get_theme_title($params['theme_name']) . '"'];
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->top_buttons[] = '<span class="btn btn-confirm btn-save-css btn-elements ">' . IMAGE_SAVE . '</span><span class="redo-buttons"></span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        Style::change_css_attributes($params['theme_name']);
        $style = Style::get_css($params['theme_name']);
        $css = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'css' and setting_name = 'css'"));
        if ($css['setting_value'] ?? null) {
            $style .= $css['setting_value'];
        }
        $setting = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where setting_name = 'development_mode' and setting_group = 'hide' and theme_name = '" . tep_db_input($params['theme_name']) . "'"));
        $css_status = 0;
        if ($setting['setting_value'] ?? null) {
            $css_status = 1;
        }
        $main_styles = Themes_Styles_Main::find()->where(['theme_name' => $params['theme_name']])->order_by('sort_order')->as_array()->all();
        $group_styles = Themes_Styles_Groups::find()->where(['theme_name' => $params['theme_name']])->order_by('sort_order')->as_array()->all();
        $main_sub_styles = Style::main_styles($params['theme_name']);
        return $this->render('css.tpl', ['menu' => 'css', 'theme_name' => $params['theme_name'] ? $params['theme_name'] : 'theme-1', 'css' => $style, 'css_status' => $css_status, 'widgets_list' => Style::get_css_widgets_list($params['theme_name']), 'designer_mode' => $this->designer_mode, 'mainStyles' => $main_styles, 'mainSubStyles' => $main_sub_styles, 'groupStyles' => $group_styles]);
    }
    public function action_get_css()
    {
        $get = Yii::$app->request->get();
        if ($get['widget'] == 'all') {
            $widget = [];
        } elseif ($get['widget'] == 'main') {
            $widget = [''];
        } else {
            $widget = [$get['widget']];
        }
        $css = Style::get_css($get['theme_name'], $widget);
        if ($get['widget'] != 'all' && $get['widget'] != 'main' && $get['widget'] != 'block_box') {
            $css = str_replace($get['widget'] ? $get['widget'] . ' ' : '', '', $css);
        }
        return $css;
    }
    public function action_js()
    {
        $params = Yii::$app->request->get();
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/js'), 'title' => 'JS "' . Theme::get_theme_title($params['theme_name']) . '"'];
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->top_buttons[] = '<span class="btn btn-confirm btn-save-javascript btn-elements ">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $javascript = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'javascript' and setting_name = 'javascript'"));
        return $this->render('js.tpl', ['menu' => 'js', 'theme_name' => $params['theme_name'] ? $params['theme_name'] : 'theme-1', 'javascript' => $javascript['setting_value'] ?? null, 'designer_mode' => $this->designer_mode]);
    }
    public function action_css_save()
    {
        $params = Yii::$app->request->post();
        $dev_path = DIR_FS_CATALOG . 'themes/' . $params['theme_name'] . '/css/';
        if ($params['widget'] == 'all') {
            \yii\helpers\File_Helper::create_directory($dev_path);
            file_put_contents($dev_path . 'develop.css', $params['css']);
        }
        Theme::save_theme_version($params['theme_name']);
        /*$develop = fopen($devPath . 'develop.css', "w");
          fwrite($develop, $params['css']);
          fclose($develop);*/
        $css_save = Style::css_save($params);
        $this->action_backup_auto($params['theme_name'], $css_save);
    }
    public function action_javascript_save()
    {
        $params = Yii::$app->request->post();
        $total = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'javascript' and setting_group = 'javascript'"));
        $query = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'javascript' and setting_group = 'javascript'");
        $javascript_old = tep_db_fetch_array($query);
        $javascript_old = $javascript_old['setting_value'] ?? null;
        if (tep_db_num_rows($query) == 0) {
            $sql_data_array = ['theme_name' => $params['theme_name'], 'setting_group' => 'javascript', 'setting_name' => 'javascript', 'setting_value' => $params['javascript']];
            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
        } else {
            $sql_data_array = ['setting_value' => $params['javascript']];
            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array, 'update', " theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'javascript' and setting_name = 'javascript'");
        }
        Theme::save_theme_version($params['theme_name']);
        $data = ['theme_name' => $params['theme_name'], 'javascript_old' => $javascript_old, 'javascript' => $params['javascript']];
        Steps::javascript_save($data);
        return '';
    }
    public function action_elements()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->selected_menu = ['design', 'elements'];
        $params = Yii::$app->request->get();
        if (!isset($params['theme_name'])) {
            return Yii::$app->get_response()->redirect(['design/themes']);
        }
        $language_query = tep_db_fetch_array(tep_db_query('select code from ' . TABLE_LANGUAGES . " where languages_id = '" . $languages_id . "' order by sort_order"));
        $language_code = $language_query['code'];
        \backend\design\Data::add_js_data(['languageCode' => $language_code, 'languages' => Language::get_languages()]);
        $this->top_buttons[] = '<span data-href="' . Yii::$app->url_manager->create_url(['design/elements-save']) . '" class="btn btn-confirm btn-save-boxes">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<span class="btn btn-preview-2 btn-primary">' . IMAGE_PREVIEW_POPUP . '</span>';
        $this->top_buttons[] = '<span class="btn btn-preview btn-primary" title="Alt + P">' . IMAGE_PREVIEW . '</span>';
        $this->top_buttons[] = '<span class="btn btn-edit btn-primary" style="display: none" title="Alt + P">' . IMAGE_EDIT . '</span>';
        if ($this->designer_mode) {
            $this->top_buttons[] = '<span class="redo-buttons"></span>';
        }
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/elements'), 'title' => BOX_HEADING_ELEMENTS . ' "' . Theme::get_theme_title($params['theme_name']) . '"'];
        $this->view->heading_title = BOX_HEADING_ELEMENTS . ' "' . Theme::get_theme_title($params['theme_name']) . '"';
        \backend\design\Data::add_js_data(['tr' => Translation::translations_for_js(['TEXT_SELECT_PREVIEW_PLATFORM', 'IMAGE_SAVE', 'IMAGE_CANCEL', 'TEXT_REMOVE', 'TEXT_PAGES', 'TEXT_EDIT_SETTINGS', 'TEXT_COPY_PAGE', 'TEXT_ADD_PAGE', 'COPY_PAGE_CONTENT_FROM', 'COPY_PAGE_CONTENT', 'TEXT_CHOOSE_PAGE', 'TEXT_SEARCH_PAGE', 'TEXT_PAGE_SETTINGS', 'TEXT_REMOVE_THIS_PAGE', 'TEXT_PAGE_NAME', 'TEXT_PAGE_TYPE', 'GO_TO_PAGE_BY_URL', 'TEXT_WIDGETS', 'TEXT_EXPORT', 'TEXT_NAME_THIS_BLOCK', 'SAVE_TO_WIDGET_GROUPS', 'WIDGET_GROUP_CATEGORY', 'NO_CATEGORIZED', 'DOWNLOAD_ON_MY_COMPUTER', 'TEXT_COMMENTS', 'EDIT_WIDGETS', 'EDIT_TEXTS', 'ICON_WARNING', 'DATA_FROM_NETWORK_CHANGED', 'BLOCK_CONTAINS_EXTENSION_WIDGETS', 'ADD_WIDGET', 'EXPORT_BLOCK', 'EDIT_BLOCK', 'MOVE_BLOCK', 'EDIT_WIDGET', 'EDIT_WIDGET_STYLES', 'TEXT_CHANGE', 'EXTENSIONS_YOU_DONT_HAVE', 'WIDGETS_NOT_INSTALLED_EXTENSIONS'], false), 'pages' => Frontend_Structure::get_pages(), 'groups' => Frontend_Structure::get_page_groups(), 'unitedTypes' => Frontend_Structure::get_united_types_group(), 'groupCategories' => Frontend_Structure::get_group_categories(), 'platformSelect' => Frontend_Structure::get_theme_platforms(), 'platformsList' => \common\classes\platform::get_list(false), 'theme_name' => $params['theme_name'] ? $params['theme_name'] : 'theme-1', 'theme_title' => Theme::get_theme_title($params['theme_name']), 'designer_mode' => $this->designer_mode]);
        return $this->render('elements.tpl', ['menu' => 'elements', 'link_save' => Yii::$app->url_manager->create_url(['design/elements-save']), 'link_cancel' => Yii::$app->url_manager->create_url(['design/elements-cancel']), 'theme_name' => $params['theme_name'] ? $params['theme_name'] : 'theme-1', 'landing' => \frontend\design\Info::theme_setting('landing', 'hide', $params['theme_name']) ? 1 : 0, 'designer_mode' => $this->designer_mode]);
    }
    public function action_elements_save()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        Theme::elements_save($get['theme_name']);
        Steps::elements_save($get['theme_name']);
        Design_Boxes_Cache::delete_all(['theme_name' => $get['theme_name']]);
        return '<div class="popup-heading">' . TEXT_NOTIFIC . '</div><div class="popup-content pop-mess-cont">' . MESSAGE_SAVED . '</div>';
    }
    public function action_elements_cancel()
    {
        $theme_name = tep_db_prepare_input(Yii::$app->request->get('theme_name'));
        Steps::elements_cancel($theme_name);
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . ' where box_id in (select id from ' . TABLE_DESIGN_BOXES . " where theme_name = '" . tep_db_input($theme_name) . "')");
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_TMP . " where theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('INSERT INTO ' . TABLE_DESIGN_BOXES_TMP . ' SELECT * FROM ' . TABLE_DESIGN_BOXES . " WHERE theme_name = '" . tep_db_input($theme_name) . "'");
        tep_db_query('INSERT INTO ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . ' SELECT dbs.* FROM ' . TABLE_DESIGN_BOXES_SETTINGS . ' dbs, ' . TABLE_DESIGN_BOXES_TMP . " db WHERE db.theme_name = '" . tep_db_input($theme_name) . "' and dbs.box_id = db.id");
        return '<div class="popup-heading">' . TEXT_NOTIFIC . '</div><div class="popup-content pop-mess-cont">Canceled</div>';
    }
    public function action_blocks_move()
    {
        $params = Yii::$app->request->post();
        $first_box_id = substr($params['id'][0], 4);
        $theme_name = \common\models\Design_Boxes_Tmp::find_one(['id' => $first_box_id])->theme_name;
        if ($theme_name != $params['theme_name']) {
            return json_encode('');
        }
        $i = 1;
        $positions = [];
        $positions_old = [];
        if (is_array($params['id'])) {
            foreach ($params['id'] as $item) {
                $id = substr($item, 4);
                $microtime = Design_Boxes_Tmp::find_one(['id' => $id])->microtime;
                $sql_data_array = ['block_name' => tep_db_prepare_input($params['name']), 'sort_order' => $i];
                $i++;
                $positions[] = array_merge(['id' => $id, 'microtime' => $microtime], $sql_data_array);
                $positions_old[] = tep_db_fetch_array(tep_db_query('select id, block_name, sort_order, microtime from ' . TABLE_DESIGN_BOXES_TMP . " where id='" . (int) $id . "'"));
                tep_db_perform(TABLE_DESIGN_BOXES_TMP, $sql_data_array, 'update', "id = '" . (int) $id . "'");
            }
        }
        $data = ['positions' => $positions, 'positions_old' => $positions_old, 'theme_name' => $params['theme_name']];
        Steps::blocks_move($data);
        $this->action_backup_auto($params['theme_name'], json_encode(''));
    }
    public static function delete_block($id)
    {
        $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = 'block-" . tep_db_input($id) . "' or block_name = 'block-" . tep_db_input($id) . "-2' or block_name = 'block-" . tep_db_input($id) . "-3' or block_name = 'block-" . tep_db_input($id) . "-4' or block_name = 'block-" . tep_db_input($id) . "-5'");
        while ($item = tep_db_fetch_array($query)) {
            tep_db_query('delete from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $item['id'] . "'");
            tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . $item['id'] . "'");
            self::delete_block($item['id']);
        }
    }
    public function action_box_delete()
    {
        $params = tep_db_prepare_input(Yii::$app->request->post());
        $id = substr($params['id'], 4);
        Steps::box_delete(['theme_name' => $params['theme_name'], 'id' => $id]);
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $id . "'");
        tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . (int) $id . "'");
        self::delete_block($id);
        $this->action_backup_auto($params['theme_name'], json_encode(['text' => 'removed']));
    }
    public function action_widgets_list()
    {
        $type = Yii::$app->request->get('type');
        $widgets = \backend\design\Widgets_List::get($type);
        return json_encode($widgets);
    }
    public function action_box_add()
    {
        $params = tep_db_prepare_input(Yii::$app->request->post());
        $params['sort_order'] = Design_Boxes_Tmp::find()->where(['block_name' => $params['block'], 'theme_name' => $params['theme_name']])->max('sort_order') + 1;
        if (substr($params['box'], 0, 6) == 'group-') {
            $id = substr($params['box'], 6);
            $file = \common\models\Design_Boxes_Groups::find_one($id)->file;
            $path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']);
            $params['block_name'] = $params['block'];
            $import_block = Theme::import_block($path . DIRECTORY_SEPARATOR . $file, $params);
            if (is_array($import_block)) {
                [$arr, $box_id] = $import_block;
            } else {
                return $import_block;
            }
            $data = ['idArr' => $box_id, 'theme_name' => $params['theme_name']];
            Steps::import_block($data);
        } else {
            $design_boxes = new Design_Boxes_Tmp();
            $design_boxes->microtime = microtime(true);
            $design_boxes->theme_name = $params['theme_name'];
            $design_boxes->block_name = $params['block'];
            $design_boxes->widget_name = $params['box'];
            $design_boxes->sort_order = $params['sort_order'];
            $design_boxes->save();
            $design_boxes->refresh();
            Steps::box_add($design_boxes->get_attributes());
        }
        $this->action_backup_auto($params['theme_name'], json_encode($params));
    }
    public function action_box_add_sort()
    {
        $params = tep_db_prepare_input(Yii::$app->request->post());
        if (substr($params['box'], 0, 6) == 'group-') {
            $id = substr($params['box'], 6);
            $file = \common\models\Design_Boxes_Groups::find_one($id)->file;
            $path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']);
            $params['sort_order'] = $params['order'];
            $params['block_name'] = $params['block'];
            $import_block = Theme::import_block($path . DIRECTORY_SEPARATOR . $file, $params);
            if (is_array($import_block)) {
                [$arr, $box_id] = $import_block;
            } else {
                return $import_block;
            }
            $data = ['idArr' => $box_id, 'theme_name' => $params['theme_name']];
            Steps::import_block($data);
        } else {
            $design_boxes = new Design_Boxes_Tmp();
            $design_boxes->set_attributes(['microtime' => microtime(true), 'theme_name' => $params['theme_name'], 'block_name' => $params['block'] ?? null, 'widget_name' => $params['box'], 'sort_order' => $params['order']]);
            $design_boxes->save();
            $design_boxes->refresh();
            $box_id = $design_boxes->id;
            $i = 1;
            $sort_arr = [];
            $sort_arr_old = [];
            foreach ($params['id'] as $item) {
                if ($item == 'new') {
                    $id = $box_id;
                } else {
                    $id = (int) substr($item, 4);
                }
                $design_boxes_sibling = Design_Boxes_Tmp::find_one(['id' => $id, 'theme_name' => $params['theme_name']]);
                if ($design_boxes_sibling) {
                    $sort_arr[$design_boxes_sibling->microtime] = $i;
                    $sort_arr_old[$design_boxes_sibling->microtime] = $design_boxes_sibling->sort_order;
                    $design_boxes_sibling->sort_order = $i;
                    $design_boxes_sibling->save();
                }
                $i++;
            }
            Steps::box_add($design_boxes->get_attributes() + ['sort_arr' => $sort_arr, 'sort_arr_old' => $sort_arr_old]);
        }
        $this->action_backup_auto($params['theme_name'], $params['order']);
    }
    public function action_copy_page()
    {
        $theme_name = Yii::$app->request->post('theme_name');
        $page_to = Yii::$app->request->post('page_to');
        $page_from = Yii::$app->request->post('page_from');
        if (!$theme_name || !$page_to || !$page_from) {
            return '';
        }
        $ald_boxes = \common\models\Design_Boxes::find()->where(['theme_name' => $theme_name, 'block_name' => $page_to])->as_array()->all();
        $content_old = [];
        foreach ($ald_boxes as $box) {
            $tree = \backend\design\Theme::blocks_tree($box['id']);
            $content_old[] = $tree;
            tep_db_query('delete from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $box['id'] . "'");
            tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . (int) $box['id'] . "'");
            self::delete_block($box['id']);
        }
        $content = [];
        $boxes = Design_Boxes::find()->where(['block_name' => $page_from, 'theme_name' => $theme_name])->as_array()->all();
        foreach ($boxes as $box) {
            $tree = \backend\design\Theme::blocks_tree($box['id']);
            $content[] = $tree;
            Theme::blocks_tree_import($tree, $theme_name, $page_to);
        }
        $step_data = ['theme_name' => $theme_name, 'page_to' => $page_to, 'page_from' => $page_from, 'content' => $content, 'content_old' => $content_old];
        Steps::copy_page($step_data);
        return '';
    }
    public function action_add_page_action()
    {
        $params = Yii::$app->request->get();
        $theme_name = tep_db_prepare_input($params['theme_name']);
        $page_name = tep_db_prepare_input($params['page_name']);
        $page_type = tep_db_prepare_input($params['page_type']);
        if (!$theme_name) {
            return json_encode(['code' => 1, 'text' => THEME_UNKNOWN]);
        }
        if (!$page_name) {
            return json_encode(['code' => 1, 'text' => ENTER_PAGE_NAME]);
        }
        $sql_data_array = ['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_name' => $page_type, 'setting_value' => $page_name];
        $count = Themes_Settings::find()->where($sql_data_array)->count();
        if ($count > 0) {
            return json_encode(['code' => 1, 'text' => THIS_PAGE_ALREADY_EXIST]);
        }
        $themes_settings = new Themes_Settings();
        $themes_settings->theme_name = $theme_name;
        $themes_settings->setting_group = 'added_page';
        $themes_settings->setting_name = $page_type;
        $themes_settings->setting_value = $page_name;
        $themes_settings->save();
        \backend\design\Theme::save_page_settings($params);
        $boxes = Design_Boxes::find()->where(['block_name' => $page_type == 'inform' ? 'info' : $page_type, 'theme_name' => $theme_name])->as_array()->all();
        $content = [];
        foreach ($boxes as $box) {
            $tree = \backend\design\Theme::blocks_tree($box['id']);
            $content[] = $tree;
            Theme::blocks_tree_import($tree, $theme_name, \common\classes\design::page_name($page_name));
        }
        $sql_data_array['content'] = $content;
        Steps::add_page($sql_data_array);
        return json_encode(['code' => 2, 'text' => PAGE_ADDED]);
    }
    public function action_remove_page_template()
    {
        $params = Yii::$app->request->get();
        $theme_name = tep_db_prepare_input($params['theme_name']);
        $page_title = tep_db_prepare_input($params['page_name']);
        $page_name = \common\classes\design::page_name($page_title);
        if ($theme_name && $page_name) {
            $count = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_group = 'added_page' and setting_value = '" . tep_db_input($page_title) . "'"));
            if ($count['total'] == 1) {
                Steps::remove_page_template(['theme_name' => $theme_name, 'page_title' => $page_title]);
                tep_db_query('
                        delete 
                        from ' . TABLE_THEMES_SETTINGS . " \r\n                        where \r\n                            theme_name = '" . tep_db_input($theme_name) . "' and \r\n                            ((setting_group = 'added_page' and setting_value = '" . tep_db_input($page_title) . "') or\r\n                             (setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($page_title) . "'))\r\n                ");
                $query = tep_db_query('select id from ' . TABLE_DESIGN_BOXES_TMP . " where block_name = '" . tep_db_input($page_name) . "'");
                while ($item = tep_db_fetch_array($query)) {
                    tep_db_query('delete from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $item['id'] . "'");
                    tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . $item['id'] . "'");
                    self::delete_block($item['id']);
                }
                $this->action_backup_auto($params['theme_name'], json_encode(['code' => 2, 'text' => '']));
            }
        }
    }
    public function action_add_page_settings()
    {
        $get = Yii::$app->request->get();
        $query = tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($get['page_name']) . "'");
        $added_page_settings = [];
        while ($item = tep_db_fetch_array($query)) {
            if (strpos($item['setting_value'], ':')) {
                $set_arr = explode(':', $item['setting_value']);
                $added_page_settings[$set_arr[0]] = $set_arr[1];
            } else {
                $added_page_settings[$item['setting_value']] = true;
            }
        }
        $this->layout = 'popup.tpl';
        return $this->render('add-page-settings.tpl', ['short' => $get['short'] ?? null, 'theme_name' => $get['theme_name'], 'page_name' => $get['page_name'], 'page_type' => $get['page_type'], 'added_page_settings' => $added_page_settings, 'action' => Yii::$app->url_manager->create_url('design/add-page-settings-action')]);
    }
    public function action_add_page_settings_action()
    {
        $post = Yii::$app->request->post();
        $theme_name = tep_db_prepare_input($post['theme_name']);
        $page_name = tep_db_prepare_input($post['page_name']);
        $settings_old = [];
        $settings = [];
        $query_settings = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($page_name) . "'");
        while ($item = tep_db_fetch_array($query_settings)) {
            $settings_old[] = $item;
        }
        \backend\design\Theme::save_page_settings($post);
        $query_settings = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($page_name) . "'");
        while ($item = tep_db_fetch_array($query_settings)) {
            $settings[] = $item;
        }
        Steps::add_page_settings(['theme_name' => $theme_name, 'page_name' => $page_name, 'settings_old' => $settings_old, 'settings' => $settings]);
        return json_encode(['code' => 1, 'text' => '']);
    }
    public function action_box_edit()
    {
        $params = tep_db_prepare_input(Yii::$app->request->get());
        $id = substr($params['id'], 4);
        $settings = [];
        $items_query = tep_db_query('select id, widget_name, widget_params, theme_name from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $id . "'");
        $widget_params = [];
        if ($item = tep_db_fetch_array($items_query)) {
            $widget_params = $item['widget_params'];
            $media_query = [];
            $media_query_arr = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($item['theme_name']) . "' and setting_name = 'media_query'");
            while ($item1 = tep_db_fetch_array($media_query_arr)) {
                $width = explode('w', $item1['setting_value']);
                $item1['title'] = ($width[0] ? $width[0] : '0') . ' - ' . ($width[1] ? $width[1] : '<span style="font-size: 1.8em; line-height: 0">&#8734;</span>');
                $media_query[] = $item1;
            }
            usort($media_query, function ($a, $b) {
                return (int) str_replace('w', '', $a['setting_value']) < (int) str_replace('w', '', $b['setting_value']) ? -1 : 1;
            });
            $settings['media_query'] = $media_query;
            $settings['theme_name'] = $item['theme_name'];
        }
        $visibility = [];
        $settings_query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . (int) $id . "'");
        while ($set = tep_db_fetch_array($settings_query)) {
            if (!$set['visibility']) {
                $settings[$set['language_id']][$set['setting_name']] = $set['setting_value'];
            } else if (count(Style::v_arr($set['visibility'])) == 1) {
                $visibility[$set['language_id']][$set['visibility']][$set['setting_name']] = $set['setting_value'];
            }
        }
        $font_added = [];
        $font_added_arr = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($item['theme_name']) . "' and setting_name = 'font_added'");
        while ($item1 = tep_db_fetch_array($font_added_arr)) {
            preg_match('/font-family:[ \'"]+([^\'^"^;^}]+)/', $item1['setting_value'], $val);
            $font_added[] = $val[1];
        }
        $settings['font_added'] = $font_added;
        $settings['theme_name'] = $item['theme_name'];
        $settings['designer_mode'] = $this->designer_mode;
        if (is_file(Yii::get_alias('@app') . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'boxes' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $params['name']) . '.php')) {
            $widget_name = 'backend\design\boxes\\' . str_replace('\\\\', '\\', $params['name']);
            return $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings, 'visibility' => $visibility]);
        } elseif ($ext = \common\helpers\Acl::check_extension($params['name'], 'showTabSettings', true)) {
            $widget_name = 'backend\design\boxes\Def';
            $settings['tabs'] = ['class' => $ext, 'method' => 'showTabSettings'];
            return $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings, 'visibility' => $visibility, 'block_type' => $params['block_type']]);
        } elseif ($ext = \common\helpers\Acl::check_extension($params['name'], 'showSettings', true)) {
            $widget_name = 'backend\design\boxes\Def';
            $settings['class'] = $ext;
            $settings['method'] = 'showSettings';
            return $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings, 'visibility' => $visibility, 'block_type' => $params['block_type']]);
        } else {
            $widget_name = 'backend\design\boxes\Def';
            return $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings, 'visibility' => $visibility, 'block_type' => $params['block_type']]);
        }
    }
    public function save_box_settings($id, $language, $key, $val, $visibility = '', $settings = [])
    {
        if (($val == '' || $val == 'off') && !in_array($key, ['background_image', 'logo', 'poster', 'video', 'image'])) {
            Design_Boxes_Settings_Tmp::delete_all(['box_id' => $id, 'setting_name' => $key, 'language_id' => $language, 'visibility' => $visibility]);
            return null;
        }
        if (in_array($key, ['background_image_upload', 'logo_upload', 'poster_upload', 'video_upload', 'image_upload'])) {
            return null;
        }
        $theme_row = Design_Boxes_Tmp::find()->select('theme_name, microtime')->where(['id' => $id])->as_array()->one();
        if (!$theme_row) {
            return null;
        }
        $theme_name = $theme_row['theme_name'];
        $setting_row = Design_Boxes_Settings_Tmp::find_one(['box_id' => $id, 'setting_name' => $key, 'language_id' => $language, 'visibility' => $visibility]);
        if (!$setting_row) {
            $setting_row = new Design_Boxes_Settings_Tmp();
        }
        if (in_array($key, ['background_image', 'logo', 'poster', 'video', 'image'])) {
            $val = \common\helpers\Image::prepare_saving_image($setting_row->setting_value ?? '', $val, $settings[$key . '_upload'], 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR . 'img', false, true);
            if (!$val) {
                Design_Boxes_Settings_Tmp::delete_all(['box_id' => $id, 'setting_name' => $key, 'language_id' => $language, 'visibility' => $visibility]);
                return null;
            }
        }
        $setting_row->box_id = $id;
        $setting_row->microtime = $theme_row['microtime'];
        $setting_row->theme_name = $theme_name;
        $setting_row->setting_name = $key;
        $setting_row->setting_value = (string) $val;
        $setting_row->language_id = $language;
        $setting_row->visibility = $visibility;
        $setting_row->save(false);
    }
    public function action_box_save()
    {
        $values = Yii::$app->request->post('values');
        $params = Style::params_from_one_input($values);
        //$params = tep_db_prepare_input($params);
        if (isset($params['product_types']) && is_array($params['product_types'])) {
            $tmp = 0;
            //2do jquery.edit-[box|theme].js pass checkbox value/remove from params if unchecked VL
            foreach ($params['product_types'] as $v => $foo) {
                if (!empty($foo)) {
                    $tmp |= $v;
                }
            }
            $params['setting'][0]['product_types'] = $tmp;
        }
        $p = tep_db_fetch_array(tep_db_query('select theme_name, microtime from ' . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $params['id'] . "'"));
        $box_settings_old = [];
        $query = tep_db_query('select setting_name, setting_value, language_id, visibility from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . (int) $params['id'] . "'");
        while ($item = tep_db_fetch_array($query)) {
            $box_settings_old[] = $item;
        }
        if (Array_Helper::get_value($params, 'setting') || Array_Helper::get_value($params, 'visibility')) {
            for ($i = 0; $i < 17; $i++) {
                if ($params['setting'][0]['sort_hide_' . $i] ?? null) {
                    $params['setting'][0]['sort_hide_' . $i] = 0;
                } elseif (isset($params['setting'][0]['sort_hide_' . $i])) {
                    $params['setting'][0]['sort_hide_' . $i] = 1;
                }
            }
            if (Array_Helper::get_value($params, ['setting', 0, 'font_size_dimension']) && !Array_Helper::get_value($params, ['setting', 0, 'font-size'])) {
                $params['setting'][0]['font_size_dimension'] = '';
            }
            $convert_settings = [
                // visibility widgets on various pages
                'visibility_home',
                'visibility_first_view',
                'visibility_more_view',
                'visibility_logged',
                'visibility_not_logged',
                'visibility_product',
                'visibility_catalog',
                'visibility_info',
                'visibility_cart',
                'visibility_checkout',
                'visibility_success',
                'visibility_account',
                'visibility_login',
                'visibility_other',
                //items on listing product
                'show_name',
                'show_image',
                'show_stock',
                'show_description',
                'show_model',
                'show_properties',
                'show_rating',
                'show_rating_counts',
                'show_price',
                'show_buy_button',
                'show_qty_input',
                'show_view_button',
                'show_wishlist_button',
                'show_compare',
                'show_bonus_points',
                'show_attributes',
                'show_paypal_button',
                'show_amazon_button',
                'show_name_rows',
                'show_image_rows',
                'show_stock_rows',
                'show_description_rows',
                'show_model_rows',
                'show_properties_rows',
                'show_rating_rows',
                'show_rating_counts_rows',
                'show_price_rows',
                'show_buy_button_rows',
                'show_qty_input_rows',
                'show_view_button_rows',
                'show_wishlist_button_rows',
                'show_compare_rows',
                'show_bonus_points_rows',
                'show_attributes_rows',
                'show_paypal_button_rows',
                'show_amazon_button_rows',
                'show_name_b2b',
                'show_image_b2b',
                'show_stock_b2b',
                'show_description_b2b',
                'show_model_b2b',
                'show_properties_b2b',
                'show_rating_b2b',
                'show_rating_counts_b2b',
                'show_price_b2b',
                'show_buy_button_b2b',
                'show_qty_input_b2b',
                'show_view_button_b2b',
                'show_wishlist_button_b2b',
                'show_compare_b2b',
                'show_bonus_points_b2b',
                'show_attributes_b2b',
                'show_paypal_button_b2b',
                'show_amazon_button_b2b',
            ];
            foreach ($convert_settings as $setting) {
                if (isset($params['setting'][0][$setting]) && !$params['setting'][0][$setting]) {
                    $params['setting'][0][$setting] = 1;
                } elseif (Array_Helper::get_value($params, ['setting', 0, $setting]) == 1) {
                    $params['setting'][0][$setting] = '';
                }
            }
            if (is_array($params['setting'] ?? null)) {
                foreach ($params['setting'] as $language => $set) {
                    foreach ($set as $key => $val) {
                        if (is_array($val)) {
                            $val = implode(',', $val);
                        }
                        $this->save_box_settings($params['id'], $language, $key, $val, '', $set);
                    }
                }
            }
            if (is_array($params['visibility'] ?? null)) {
                foreach ($params['visibility'] as $language => $set) {
                    foreach ($set as $visibility => $set2) {
                        foreach ($set2 as $key => $val) {
                            if (is_array($val)) {
                                $val = implode(',', $val);
                            }
                            $this->save_box_settings($params['id'], $language, $key, $val, $visibility, $set2);
                        }
                    }
                }
            }
        }
        $old_params = '';
        $box = Design_Boxes_Tmp::find_one(['id' => $params['id']]);
        if ($box) {
            $old_params = $box->widget_params;
        }
        $widget_params = $params['params'] ?? '';
        $sql_data_array = ['widget_params' => tep_db_prepare_input($params['params'] ?? null)];
        tep_db_perform(TABLE_DESIGN_BOXES_TMP, $sql_data_array, 'update', "id = '" . (int) $params['id'] . "'");
        $box_settings = [];
        $query = tep_db_query('select setting_name, setting_value, language_id, visibility, microtime, theme_name from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . (int) $params['id'] . "'");
        while ($item = tep_db_fetch_array($query)) {
            $box_settings[] = $item;
        }
        Style::create_cache($params['theme_name'] ?? null);
        Steps::box_save(['box_id' => $params['id'], 'microtime' => $p['microtime'], 'theme_name' => $p['theme_name'], 'box_settings' => $box_settings, 'box_settings_old' => $box_settings_old, 'widget_params' => $widget_params, 'widget_params_old' => $old_params]);
        $this->action_backup_auto($p['theme_name'], json_encode(''));
    }
    public function action_style_edit()
    {
        $params = tep_db_prepare_input(Yii::$app->request->get());
        $settings = [];
        $styles_query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and selector = '" . tep_db_input($params['data_class']) . "'");
        $visibility = [];
        while ($styles_arr = tep_db_fetch_array($styles_query)) {
            if (!$styles_arr['visibility']) {
                $settings[0][$styles_arr['attribute']] = $styles_arr['value'];
            } else {
                $visibility[0][$styles_arr['visibility']][$styles_arr['attribute']] = $styles_arr['value'];
            }
        }
        $this->layout = 'popup.tpl';
        $media_query = [];
        $media_query_arr = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_name = 'media_query'");
        while ($item1 = tep_db_fetch_array($media_query_arr)) {
            $media_query[] = $item1;
        }
        $settings['media_query'] = $media_query;
        $font_added = [];
        $font_added_arr = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_name = 'font_added'");
        while ($item1 = tep_db_fetch_array($font_added_arr)) {
            preg_match('/font-family:[ \'"]+([^\'^"^;^}]+)/', $item1['setting_value'], $val);
            $font_added[] = $val[1];
        }
        $settings['font_added'] = $font_added;
        $settings['data_class'] = $params['data_class'];
        $settings['theme_name'] = $params['theme_name'];
        $widget_name = 'backend\design\boxes\StyleEdit';
        $this->action_backup_auto($params['theme_name'], $widget_name::widget(['id' => 0, 'params' => '', 'settings' => $settings, 'visibility' => $visibility, 'block_type' => '']));
        /*return $this->render('style-edit.tpl', [
            'data_class' => $params['data_class'],
            'theme_name' => $params['theme_name'],
            'settings' => $styles
          ]);*/
    }
    public function style_save($styles, $params, $visibility = '')
    {
        if (is_array($styles)) {
            foreach ($styles as $key => $val) {
                $accessibility = '';
                if (preg_match('/^(\.w-[0-9a-zA-Z\-\_]+)/', $key, $matches)) {
                    $accessibility = $matches[1];
                }
                $total = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and selector = '" . tep_db_input($params['data_class']) . "' and attribute = '" . tep_db_input($key) . "' and visibility='" . tep_db_input($visibility) . "' and media = ''"));
                if ($val !== '') {
                    if ($key == 'background_image') {
                        $setting_value = tep_db_fetch_array(tep_db_query('select ts.value from ' . TABLE_THEMES_STYLES . " ts where ts.theme_name = '" . tep_db_input($params['theme_name']) . "' and ts.selector = '" . tep_db_input($params['data_class']) . "' and ts.attribute = '" . tep_db_input($key) . "' and visibility='" . tep_db_input($visibility) . "' and media = ''"));
                        if ($setting_value['value'] != $val) {
                            $val_tmp = Uploads::move($val, 'themes/' . $params['theme_name'] . '/img');
                            if ($val_tmp) {
                                $val = $val_tmp;
                            }
                        }
                    }
                    if ($total['total'] == 0) {
                        $sql_data_array = ['theme_name' => $params['theme_name'], 'selector' => $params['data_class'], 'attribute' => $key, 'value' => $val, 'visibility' => $visibility, 'media' => '', 'accessibility' => $accessibility];
                        tep_db_perform(TABLE_THEMES_STYLES, $sql_data_array);
                    } else {
                        $sql_data_array = ['value' => $val];
                        tep_db_perform(TABLE_THEMES_STYLES, $sql_data_array, 'update', "theme_name = '" . tep_db_input($params['theme_name']) . "' and selector = '" . tep_db_input($params['data_class']) . "' and attribute = '" . tep_db_input($key) . "' and visibility='" . tep_db_input($visibility) . "' and media = ''");
                    }
                } else if ($total['total'] > 0) {
                    tep_db_query('delete from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and selector = '" . tep_db_input($params['data_class']) . "' and attribute = '" . tep_db_input($key) . "' and visibility='" . tep_db_input($visibility) . "' and media = ''");
                }
            }
        }
    }
    public function action_style_main_save()
    {
        $styles = Yii::$app->request->post('styles');
        $groups = Yii::$app->request->post('groups');
        $theme_name = Yii::$app->request->post('theme_name');
        $old_styles = Themes_Styles_Main::find()->where(['theme_name' => $theme_name])->as_array()->all();
        $old_groups = Themes_Styles_Groups::find()->where(['theme_name' => $theme_name])->as_array()->all();
        Themes_Styles_Main::delete_all(['theme_name' => $theme_name]);
        if (is_array($styles)) {
            $sort_order = 0;
            foreach ($styles as $key => $style) {
                $themes_styles_main = new Themes_Styles_Main();
                $themes_styles_main->theme_name = $theme_name;
                $themes_styles_main->name = $style['name'];
                $themes_styles_main->value = $style['value'];
                $themes_styles_main->type = $style['type'];
                $themes_styles_main->sort_order = $sort_order;
                $themes_styles_main->group_id = $style['group_id'] ?? '';
                $themes_styles_main->save();
                $styles[$key]['sort_order'] = $sort_order;
                $sort_order++;
            }
        }
        Themes_Styles_Groups::delete_all(['theme_name' => $theme_name]);
        if (is_array($groups)) {
            $sort_order = 0;
            foreach ($groups as $key => $group) {
                if (!$group || !is_array($group)) {
                    continue;
                }
                $themes_styles_groups = new Themes_Styles_Groups();
                $themes_styles_groups->theme_name = $theme_name;
                $themes_styles_groups->group_id = (int) $group['group_id'];
                $themes_styles_groups->group_name = $group['group_name'];
                $themes_styles_groups->sort_order = $sort_order;
                $themes_styles_groups->tab = $group['tab'];
                $themes_styles_groups->save();
                $sort_order++;
            }
        }
        $data = ['theme_name' => $theme_name, 'old_styles' => $old_styles, 'old_groups' => $old_groups, 'new_styles' => $styles, 'new_groups' => $groups];
        Steps::style_save($data);
        return json_encode(['text' => MESSAGE_SAVED]);
    }
    public function action_styles_data()
    {
        $action = Yii::$app->request->get('action');
        $theme_name = Yii::$app->request->get('theme_name');
        $name = Yii::$app->request->get('name');
        switch ($action) {
            case 'count':
                $count = Themes_Styles::find()->where(['theme_name' => $theme_name, 'value' => '$' . $name])->count();
                $count += Design_Boxes_Settings_Tmp::find()->where(['theme_name' => $theme_name, 'setting_value' => '$' . $name])->count();
                return json_encode(['text' => sprintf(THIS_STYLE_PLACED_IN, $count), 'count' => $count]);
        }
    }
    public function action_style_save()
    {
        $values = Yii::$app->request->post('values');
        $post = Yii::$app->request->post();
        $params = Style::params_from_one_input($values);
        $params = tep_db_prepare_input($params);
        $params['data_class'] = $params['data_class'] ?? null;
        $params['theme_name'] = $params['theme_name'] ?? null;
        $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where selector='" . tep_db_input($params['data_class']) . "' and theme_name='" . tep_db_input($params['theme_name']) . "'");
        $styles_old = [];
        while ($item = tep_db_fetch_array($query)) {
            $styles_old[] = $item;
        }
        if (is_array($params['visibility'][0] ?? null)) {
            foreach ($params['visibility'][0] as $key => $item) {
                $this->style_save($item, $post, $key);
            }
        }
        $this->style_save($params['setting'][0] ?? null, $post);
        $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where selector='" . tep_db_input($params['data_class']) . "' and theme_name='" . tep_db_input($params['theme_name']) . "'");
        $styles = [];
        while ($item = tep_db_fetch_array($query)) {
            $styles[] = $item;
        }
        $attributes_changed = [];
        $attributes_delete = [];
        $attributes_new = [];
        foreach ($styles_old as $item) {
            $find = false;
            foreach ($styles as $i => $attr) {
                if ($attr['selector'] == $item['selector'] && $attr['attribute'] == $item['attribute'] && $attr['visibility'] == $item['visibility'] && $attr['media'] == $item['media'] && $attr['accessibility'] == $item['accessibility']) {
                    if ($attr['value'] != $item['value']) {
                        $attributes_changed[] = ['selector' => $attr['selector'], 'attribute' => $attr['attribute'], 'value_old' => $item['value'], 'value' => $attr['value'], 'visibility' => $attr['visibility'], 'media' => $attr['media'], 'accessibility' => $attr['accessibility']];
                    }
                    unset($styles[$i]);
                    $find = true;
                }
            }
            if (!$find) {
                $attributes_delete[] = ['selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']];
            }
        }
        foreach ($styles as $attr) {
            $attributes_new[] = ['theme_name' => $post['theme_name'], 'selector' => $attr['selector'], 'attribute' => $attr['attribute'], 'value' => $attr['value'], 'visibility' => $attr['visibility'], 'media' => $attr['media'], 'accessibility' => $attr['accessibility']];
        }
        Style::create_cache($post['theme_name']);
        $data = ['theme_name' => $post['theme_name'], 'attributes_changed' => $attributes_changed, 'attributes_delete' => $attributes_delete, 'attributes_new' => $attributes_new];
        Steps::css_save($data);
        return '';
    }
    public function action_style_add()
    {
        $theme_name = Yii::$app->request->post('theme_name');
        $name = Yii::$app->request->post('name');
        $value = Yii::$app->request->post('value');
        $type = Yii::$app->request->post('type');
        $group_id = Yii::$app->request->post('group_id');
        if (!$name) {
            return json_encode(['error' => 'Name is empty']);
        }
        if (!$value) {
            return json_encode(['error' => 'Value is empty']);
        }
        if (!$type) {
            return json_encode(['error' => 'Type is empty']);
        }
        if (!$theme_name) {
            return json_encode(['error' => 'theme_name is empty']);
        }
        $style = Themes_Styles_Main::find_one(['theme_name' => $theme_name, 'name' => $name]);
        if ($style) {
            return json_encode(['error' => THIS_STYLE_NAME_EXISTS]);
        }
        $old_styles = Themes_Styles_Main::find()->where(['theme_name' => $theme_name])->as_array()->all();
        $style = new Themes_Styles_Main();
        $style->theme_name = $theme_name;
        $style->name = $name;
        $style->value = $value;
        $style->type = $type;
        $style->group_id = $group_id;
        $style->save();
        $new_styles = array_merge($old_styles, ['theme_name' => $theme_name, 'name' => $name, 'value' => $value, 'type' => $type]);
        if (isset($style->errors) && count($style->errors)) {
            return json_encode(['error' => 'db error']);
        }
        $data = ['theme_name' => $theme_name, 'old_styles' => $old_styles, 'new_styles' => $new_styles];
        Steps::style_save($data);
        return json_encode(['text' => MESSAGE_ADDED]);
    }
    public function action_backups()
    {
        $params = tep_db_prepare_input(Yii::$app->request->get());
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/themes'), 'title' => TEXT_BACKUPS . ' "' . Theme::get_theme_title($params['theme_name']) . '"'];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['design/backup-add', 'theme_name' => $params['theme_name']]) . '" class="create_item">' . NEW_NEW_BACKUP . '</a>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $this->view->heading_title = TEXT_BACKUPS;
        \backend\design\Data::add_js_data(['tr' => ['IMAGE_SAVE' => IMAGE_SAVE, 'IMAGE_CANCEL' => IMAGE_CANCEL, 'TEXT_EXPORT' => TEXT_EXPORT], 'platformSelect' => Frontend_Structure::get_theme_platforms(), 'theme_name' => $params['theme_name'] ? $params['theme_name'] : 'theme-1', 'theme_title' => Theme::get_theme_title($params['theme_name'] ?? null)]);
        return $this->render('backups.tpl', ['menu' => 'backups', 'theme_name' => $params['theme_name'], 'messages' => [], 'designer_mode' => $this->designer_mode]);
    }
    public function action_backups_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $theme_name = tep_db_prepare_input(Yii::$app->request->get('theme_name', 10));
        if ($length == -1) {
            $length = 10000;
        }
        $response_list = [];
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'date_added ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 1:
                    $order_by = 'comments ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                default:
                    $order_by = 'date_added';
                    break;
            }
        } else {
            $order_by = 'date_added';
        }
        $orders_status_query_raw = 'select * from ' . TABLE_DESIGN_BACKUPS . " where theme_name = '" . tep_db_input($theme_name) . "' order by " . $order_by . ' limit ' . (int) $_GET['start'] . ', ' . (int) $length;
        $count = tep_db_num_rows(tep_db_query('select * from ' . TABLE_DESIGN_BACKUPS . " where theme_name = '" . tep_db_input($theme_name) . "' order by " . $order_by));
        $orders_status_query = tep_db_query($orders_status_query_raw);
        $path = DIR_FS_CATALOG . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR;
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            if (!is_file($path . $orders_status['backup_id'] . '.zip')) {
                \common\models\Design_Backups::find_one(['backup_id' => $orders_status['backup_id']])->delete();
                continue;
            }
            $short_desc = $orders_status['comments'];
            $short_desc = preg_replace('/<.*?>/', ' ', $short_desc);
            if (strlen($short_desc) > 128) {
                $short_desc = substr($short_desc, 0, 122) . '...';
            }
            $response_list[] = [\common\helpers\Date::date_long($orders_status['date_added'], '%d %b %Y / %H:%M:%S'), $short_desc . '<input type="hidden" class="backup_id" name="backup_id" value="' . $orders_status['backup_id'] . '">'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $count, 'recordsFiltered' => $count, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_backups_actions()
    {
        $this->layout = false;
        $backup_id = (int) Yii::$app->request->post('backup_id');
        if (!$backup_id) {
            return '';
        }
        $comments = \common\models\Design_Backups::find_one(['backup_id' => $backup_id])->comments;
        echo '<br>
<div style="font-size: 12px">' . str_replace("\n", '<br>', $comments) . '</div>
<div class="btn-toolbar btn-toolbar-order">
    <button class="btn btn-no-margin" onclick="backupRestore(\'' . $backup_id . '\')">' . IMAGE_RESTORE . '</button><button class="btn btn-delete" onclick="translateDelete(\'' . $backup_id . '\')">' . IMAGE_DELETE . '</button>
</div>';
    }
    public function action_backup_add()
    {
        $params = Yii::$app->request->get();
        $this->layout = false;
        return $this->render('add.tpl', ['theme_name' => $params['theme_name']]);
    }
    public function action_backup_auto($theme_name, $return = '')
    {
        ignore_user_abort(true);
        set_time_limit(0);
        ob_start();
        echo $return;
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        ob_flush();
        flush();
        $backup_date = \frontend\design\Info::theme_setting('backup_date', 'hide', $theme_name);
        $backup_hours = \frontend\design\Info::theme_setting('backup_hours', 'main', $theme_name);
        $backup_count = \frontend\design\Info::theme_setting('backup_count', 'main', $theme_name);
        if (!$backup_hours) {
            $backup_hours = 1;
        }
        if (!$backup_count) {
            $backup_count = 10;
        }
        $design_backups = \common\models\Design_Backups::find()->where(['theme_name' => $theme_name, 'comments' => 'Auto saved'])->order_by(['backup_id' => SORT_DESC])->offset($backup_count - 1)->as_array()->all();
        if ($design_backups) {
            foreach ($design_backups as $design_backup) {
                Backups::delete($design_backup['backup_id']);
            }
        }
        if ($backup_date && (int) $backup_date > 1580000000 && $backup_date + 3600 * $backup_hours > time()) {
            return false;
        }
        if ($backup_date) {
            $themes_settings = \common\models\Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => 'hide', 'setting_name' => 'backup_date']);
        } else {
            $themes_settings = new \common\models\Themes_Settings();
        }
        $themes_settings->setting_value = strval(time());
        $themes_settings->save();
        $this->action_backup_submit($theme_name, 'Auto saved');
    }
    public function action_backup_submit($theme_name = '', $comments = '')
    {
        $theme_name = Yii::$app->request->post('theme_name', $theme_name);
        $comments = Yii::$app->request->post('comments', $comments);
        $backup = new \common\models\Design_Backups();
        $backup->attributes = ['date_added' => new \yii\db\Expression('NOW()'), 'theme_name' => $theme_name, 'comments' => $comments];
        $backup->save();
        $backup_id = $backup->get_primary_key();
        Steps::backup_submit(['theme_name' => $theme_name, 'backup_id' => $backup_id, 'comments' => $comments]);
        Backups::create($theme_name, $backup_id);
        return json_encode('');
    }
    public function action_export_popup()
    {
        \common\helpers\Translation::init('admin/banner_manager');
        $theme_name = Yii::$app->request->get('theme_name');
        $menus = \common\models\Design_Boxes::find()->select(['name' => 'widget_params'])->distinct()->where(['widget_name' => 'Menu'])->and_where(['theme_name' => [$theme_name, $theme_name . '-mobile']])->as_array()->all();
        $banners = \common\models\Design_Boxes_Settings::find()->select(['group' => 'setting_value'])->distinct()->where(['setting_name' => 'banners_group', 'theme_name' => [$theme_name, $theme_name . '-mobile']])->as_array()->all();
        $info_pages = \common\models\Design_Boxes_Settings::find()->select(['name' => 'setting_value'])->distinct()->where(['setting_name' => 'info_page', 'theme_name' => [$theme_name, $theme_name . '-mobile']])->as_array()->all();
        if (!$menus && !$banners && !$info_pages) {
            return 'no-additionals';
        }
        //return 'no-additionals';
        $this->layout = false;
        return $this->render('export-popup.tpl', ['theme_name' => $theme_name, 'menus' => $menus, 'banners' => $banners, 'infoPages' => $info_pages]);
    }
    public function action_export()
    {
        $theme_name = Yii::$app->request->get('theme_name');
        if (Yii::$app->request->post()) {
            $_SESSION['exportItems'] = Yii::$app->request->post();
            return 'ok';
        }
        return \backend\design\Theme::export($theme_name);
    }
    public function action_export_data()
    {
        if ($_SESSION['exportItems']['menus']) {
            $menus = [];
            foreach ($_SESSION['exportItems']['menus'] as $menu => $checked) {
                if (!$checked) {
                    continue;
                }
                $menus[$menu] = \common\helpers\Menu_Helper::menu_tree($menu);
            }
        }
    }
    public function action_export_block()
    {
        $params = Yii::$app->request->get();
        if (!isset($params['id']) || !$params['id']) {
            $params = Yii::$app->request->post();
        }
        if (!isset($params['id']) || !$params['id']) {
            return json_encode(['error' => 'Error']);
        }
        if (substr($params['id'], 0, 4) == 'box-') {
            $id = intval(substr($params['id'], 4));
            $type = 'id';
        } else {
            $type = 'block_name';
            $id = $params['id'];
        }
        return \backend\design\Theme::export_block($id, $type, $params);
    }
    public function action_download_block()
    {
        $filename = Yii::$app->request->get('filename');
        $fs_catalog = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']) . DIRECTORY_SEPARATOR;
        header('Cache-Control: none');
        header('Pragma: none');
        header('Content-type: application/x-octet-stream');
        header('Content-disposition: attachment; filename=' . $filename);
        readfile($fs_catalog . $filename);
        if (Yii::$app->request->get('delete')) {
            unlink($fs_catalog . $filename);
        }
        return json_encode(['']);
    }
    public function action_import()
    {
        $params = Yii::$app->request->get();
        if (isset($_FILES['file']) && isset($_FILES['file']['error']) && isset($_FILES['file']['tmp_name']) && $_FILES['file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['file']['tmp_name'])) {
            if (\backend\design\Theme::import($params['theme_name'], $_FILES['file']['tmp_name'])) {
                Theme::save_theme_version($params['theme_name']);
                return 'OK';
            }
        }
        return 'error';
    }
    public function action_import_block()
    {
        $params = Yii::$app->request->get();
        if ($_FILES['file']['error'] != UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            return 'Error: no file';
        }
        $params['box_id'] = substr($params['box_id'], 4);
        $params['sort_order'] = Design_Boxes_Tmp::find_one(['id' => (int) $params['box_id']])->sort_order;
        $import_block = Theme::import_block($_FILES['file']['tmp_name'], $params);
        if (is_array($import_block)) {
            [$_arr, $box_id] = $import_block;
        } else {
            return $import_block;
        }
        Design_Boxes_Tmp::delete_all(['id' => (int) $params['box_id']]);
        Design_Boxes_Settings_Tmp::delete_all(['box_id' => (int) $params['box_id']]);
        $data = ['id_old' => $params['box_id'], 'idArr' => $box_id, 'theme_name' => $params['theme_name']];
        Steps::import_block($data);
        return 'Added';
    }
    public function action_backup_restore()
    {
        $backup_id = (int) Yii::$app->request->post('backup_id');
        $backup = \common\models\Design_Backups::find()->select('theme_name')->where(['backup_id' => $backup_id])->as_array()->one();
        Backups::backup_restore($backup_id, $backup['theme_name']);
        Steps::backup_restore(['theme_name' => $backup['theme_name'], 'backup_id' => $backup_id]);
    }
    public function action_backup_delete()
    {
        Backups::delete((int) Yii::$app->request->post('backup_id'));
    }
    public function action_gallery()
    {
        $directory = Yii::$app->request->get('directory', ['main']);
        $file_types = Yii::$app->request->get('fileTypes', []);
        return json_encode(File_Manager::get_files($directory, $file_types));
    }
    public function action_gallery_thumbnail()
    {
        $file = Yii::$app->request->get('file');
        return json_encode(File_Manager::create_thumbnails($file));
    }
    public function action_settings()
    {
        \common\helpers\Translation::init('admin/js');
        $params = tep_db_prepare_input(Yii::$app->request->get());
        $post = tep_db_prepare_input(Yii::$app->request->post(), false);
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/settings'), 'title' => THEME_SETTINGS . ' "' . Theme::get_theme_title($params['theme_name'] ?? null) . '"'];
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->top_buttons[] = '<span class="redo-buttons"></span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        if (count($post) > 0) {
            foreach ($post['setting'] as $key => $val) {
                if ($key == 'background_image_upload') {
                    continue;
                }
                $styles_row = Themes_Styles::find_one(['theme_name' => $params['theme_name'], 'selector' => 'body', 'attribute' => $key, 'visibility' => '']);
                if (!$styles_row) {
                    $styles_row = new Themes_Styles();
                }
                if (in_array($key, ['background_image'])) {
                    $val = \common\helpers\Image::prepare_saving_image($styles_row->value ?? '', $val, $post['setting']['background_image_upload'], 'themes' . DIRECTORY_SEPARATOR . $params['theme_name'] . DIRECTORY_SEPARATOR . 'img', false, true);
                    if (!$val) {
                        Themes_Styles::delete_all(['theme_name' => $params['theme_name'], 'selector' => 'body', 'attribute' => $key, 'visibility' => '']);
                        continue;
                    }
                }
                $styles_row->theme_name = $params['theme_name'];
                $styles_row->selector = 'body';
                $styles_row->attribute = $key;
                $styles_row->value = $val;
                $styles_row->save();
            }
            $them_settings_old = [];
            $query_s = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and (setting_group = 'main' or setting_group = 'extend' or setting_group = 'hide')");
            while ($item = tep_db_fetch_array($query_s)) {
                $them_settings_old[] = $item;
            }
            /*echo '<pre>';
              var_dump($them_settings_old);
              echo '</pre>';
              echo json_encode($them_settings_old);die;*/
            foreach ($post['settings'] as $setting_name => $setting_value) {
                $sql_data_array = ['theme_name' => $params['theme_name'], 'setting_group' => 'main', 'setting_name' => $setting_name, 'setting_value' => $setting_value];
                $query = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'main' and setting_name = '" . tep_db_input($setting_name) . "'"));
                if ($query['total'] > 0) {
                    tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array, 'update', " theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'main' and setting_name = '" . tep_db_input($setting_name) . "'");
                } else {
                    tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
                }
            }
            if (is_array($post['extend'] ?? null)) {
                foreach ($post['extend'] as $setting_name => $val) {
                    foreach ($val as $id => $setting_value) {
                        $sql_data_array = ['setting_value' => $setting_value];
                        $query = tep_db_fetch_array(tep_db_query('select count(*) as total from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'extend' and setting_name = '" . tep_db_input($setting_name) . "' and id = '" . (int) $id . "'"));
                        if ($query['total'] > 0) {
                            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array, 'update', " theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'extend' and setting_name = '" . tep_db_input($setting_name) . "' and id = '" . (int) $id . "'");
                        }
                    }
                }
            }
            Theme::save_favicon();
            Theme::save_theme_image('logo');
            Theme::save_theme_image('na_category');
            Theme::save_theme_image('na_product');
            $them_settings = [];
            $query_s = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and (setting_group = 'main' or setting_group = 'extend' or setting_group = 'hide')");
            while ($item = tep_db_fetch_array($query_s)) {
                $them_settings[] = $item;
            }
            $data = ['theme_name' => $params['theme_name'], 'them_settings_old' => $them_settings_old, 'them_settings' => $them_settings];
            Steps::settings($data);
        }
        $query = tep_db_query('select setting_name, setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "'");
        $settings = [];
        while ($item = tep_db_fetch_array($query)) {
            $settings[$item['setting_name']] = $item['setting_value'];
        }
        $styles = [];
        $styles_query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and selector = 'body' and visibility=''");
        while ($styles_arr = tep_db_fetch_array($styles_query)) {
            $styles[$styles_arr['attribute']] = $styles_arr['value'];
        }
        $path = \Yii::get_alias('@webroot');
        $path .= DIRECTORY_SEPARATOR;
        $path .= '..';
        $path .= DIRECTORY_SEPARATOR;
        $path .= 'themes';
        $path .= DIRECTORY_SEPARATOR;
        $path .= $_GET['theme_name'];
        $path .= DIRECTORY_SEPARATOR;
        $path .= 'icons';
        $path .= DIRECTORY_SEPARATOR;
        if (is_file($path . 'favicon-16x16.png')) {
            $favicon = '../themes/' . $_GET['theme_name'] . '/icons/favicon-16x16.png';
        } else {
            $favicon = '../themes/basic/icons/favicon-16x16.png';
        }
        $this->action_backup_auto($params['theme_name'], $this->render('settings.tpl', ['favicon' => $favicon, 'menu' => 'settings', 'settings' => $settings, 'setting' => $styles, 'theme_name' => $params['theme_name'], 'action' => Yii::$app->url_manager->create_url(['design/settings', 'theme_name' => $params['theme_name']]), 'is_mobile' => strpos($_GET['theme_name'], '-mobile') ? true : false, 'designer_mode' => $this->designer_mode]));
    }
    public function action_extend()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        if ($get['remove'] ?? null) {
            //Steps::extendRemove(['theme_name' => $get['theme_name'], 'id' => (int)$get['remove']]);
            $data = ['theme_name' => $get['theme_name'], 'them_settings_old' => Themes_Settings::find()->where(['id' => (int) $get['remove']])->as_array()->all(), 'them_settings' => []];
            Steps::settings($data);
            tep_db_query('delete from ' . TABLE_THEMES_SETTINGS . " where id = '" . (int) $get['remove'] . "'");
            tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS . " where visibility = '" . (int) $get['remove'] . "'");
            tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where visibility = '" . (int) $get['remove'] . "'");
            tep_db_query('delete from ' . TABLE_THEMES_STYLES . " where visibility = '" . (int) $get['remove'] . "'");
            //tep_db_query("delete from " . TABLE_THEMES_STYLES_TMP . " where visibility = '" . (int)$get['remove'] . "'");
        }
        if ($get['add'] ?? null) {
            $sql_data_array = ['theme_name' => $get['theme_name'], 'setting_group' => 'extend', 'setting_name' => $get['setting_name'], 'setting_value' => ''];
            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
            $added_id = tep_db_insert_id();
            $sql_data_array['id'] = $added_id;
            //Steps::extendAdd(['theme_name' => $get['theme_name'], 'data' => $sql_data_array]);
        }
        $query = tep_db_query('select id, setting_name, setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and setting_group = 'extend' and setting_name = '" . tep_db_input($get['setting_name']) . "'");
        $arr = [];
        while ($item = tep_db_fetch_array($query)) {
            $arr[] = $item;
        }
        return json_encode($arr);
    }
    public function action_demo_styles()
    {
        $post = tep_db_prepare_input(Yii::$app->request->post());
        $class = str_replace('\\', '', $post['data_class'] ?? null);
        $style = $class . '{' . \frontend\design\Block::styles($post['setting'] ?? null) . '}';
        $key_arr = explode(',', $class);
        for ($i = 1; $i < 5; $i++) {
            $add = '';
            switch ($i) {
                case 1:
                    $add = ':hover';
                    break;
                case 2:
                    $add = '.active';
                    break;
                case 3:
                    $add = ':before';
                    break;
                case 4:
                    $add = ':after';
                    break;
            }
            $selector_arr = [];
            foreach ($key_arr as $item) {
                $selector_arr[] = trim($item) . $add;
            }
            $selector = implode(', ', $selector_arr);
            $params[0] = $post['visibility'][0][$i] ?? null;
            $style .= $selector . '{' . \frontend\design\Block::styles($params) . '}';
        }
        return $style;
    }
    public function action_log()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $get['from'] = $get['from'] ?? null;
        $get['to'] = $get['to'] ?? null;
        $this->top_buttons[] = '<span class="redo-buttons"></span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->view->heading_title = LOG_TEXT . ' "' . Theme::get_theme_title($get['theme_name']) . '"';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/settings'), 'title' => 'Log "' . Theme::get_theme_title($get['theme_name']) . '"'];
        $admins = [];
        $query = tep_db_query('select admin_id, admin_firstname, admin_lastname, admin_email_address from ' . TABLE_ADMIN . '');
        while ($item = tep_db_fetch_array($query)) {
            $admins[$item['admin_id']] = $item;
        }
        $date = [];
        $date['from'] = empty($get['from']) ? null : $get['from'];
        $date['to'] = empty($get['to']) ? null : $get['to'];
        if (Yii::$app->request->is_ajax) {
            $this->layout = 'popup.tpl';
        }
        $updates = Style::get_new_updates($get['parent_theme'] ?? null);
        return $this->render('log.tpl', ['tree' => Steps::log($get['theme_name'], $date), 'admins' => $admins, 'theme_name' => $get['theme_name'], 'menu' => 'log', 'from' => $get['from'], 'to' => $get['to'], 'apple_update' => count($updates) > 0 ? false : true, 'update_buttons' => \backend\components\Information::show_hide_page(), 'designer_mode' => $this->designer_mode]);
    }
    public function action_log_details()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        if (Yii::$app->request->is_ajax) {
            $this->layout = 'popup.tpl';
        }
        return $this->render('log-details.tpl', ['details' => Steps::log_details($get['id'])]);
    }
    public function action_undo()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        Steps::undo($get['theme_name']);
    }
    public function action_redo()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        Steps::redo($get['theme_name'], $get['steps_id']);
    }
    public function action_redo_buttons()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $redo_query = tep_db_query('select sr.steps_id, sr.event, sr.date_added, sr.admin_id from ' . TABLE_THEMES_STEPS . ' sr left join ' . TABLE_THEMES_STEPS . " sa on sr.parent_id = sa.steps_id where sa.active='1' and sr.theme_name='" . tep_db_input($get['theme_name']) . "'");
        $redo = '';
        while ($item = tep_db_fetch_array($redo_query)) {
            $redo .= '<span class="btn btn-redo btn-elements" data-id="' . $item['steps_id'] . '" data-event="' . $item['event'] . '" title="' . Steps::log_names($item['event']) . ' (' . \common\helpers\Date::date_long($item['date_added'], '%d %b %Y / %H:%M:%S') . ')">' . LOG_REDO . '</span>';
        }
        $undo = tep_db_fetch_array(tep_db_query('select steps_id, event, date_added, admin_id from ' . TABLE_THEMES_STEPS . " where active='1' and parent_id!='0' and theme_name='" . tep_db_input($get['theme_name']) . "'"));
        if ($undo['steps_id'] ?? null) {
            $redo .= '<span class="btn btn-undo btn-elements" data-event="' . $undo['event'] . '" title="' . Steps::log_names($undo['event']) . ' (' . \common\helpers\Date::date_long($undo['date_added'], '%d %b %Y / %H:%M:%S') . ')">' . LOG_UNDO . '</span>';
        }
        echo $redo;
    }
    public function action_step_restore()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $text = Steps::restore($get['id']);
        if ($text) {
            $text = '
<div class="popup-box-wrap pop-mess">
    <div class="around-pop-up"></div>
    <div class="popup-box">
        <div class="pop-up-close pop-up-close-alert"></div>
        <div class="pop-up-content">
            <div class="popup-content pop-mess-cont pop-mess-cont-error">
                ' . $text . '
            </div>
        </div>
            <div class="noti-btn">
                    <div></div>
                    <div><span class="btn btn-primary">' . TEXT_BTN_OK . '</span></div>
                </div>
    </div>
<script>
    $(\'body\').scrollTop(0);
    $(\'.pop-mess .pop-up-close-alert, .noti-btn .btn\').click(function () {
        $(this).parents(\'.pop-mess\').remove();
    });
</script>
</div>
';
        }
        return $text;
    }
    public function action_find_selector()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $selectors_query = tep_db_query('
      select DISTINCT selector
      from ' . TABLE_THEMES_STYLES . "\r\n      where theme_name = '" . tep_db_input($get['theme_name']) . "' and\r\n        selector LIKE '%" . tep_db_input($get['selector']) . "%'\r\n");
        $html = '';
        while ($item = tep_db_fetch_array($selectors_query)) {
            $html .= '<div class="item">' . $item['selector'] . '</div>';
        }
        if ($html == '') {
            $html = '<div class="no-selector">Not found selectors.</div>';
        }
        return $html;
    }
    public function action_styles()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        /*$this->topButtons[] = '<span class="redo-buttons"></span>';*/
        /*$this->topButtons[] = '<span data-href="' . Yii::$app->urlManager->createUrl(['design/theme-save', 'theme_name' => $get['theme_name']]) . '" class="btn btn-confirm btn-save-boxes btn-elements">'.IMAGE_SAVE.'</span> <span class="redo-buttons"></span>';*/
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/elements'), 'title' => BOX_HEADING_MAIN_STYLES . ' "' . Theme::get_theme_title($get['theme_name']) . '"'];
        $this->view->heading_title = BOX_HEADING_MAIN_STYLES . ' "' . Theme::get_theme_title($get['theme_name']) . '"';
        $this->top_buttons[] = '<span class="btn btn-confirm btn-save-boxes">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<span class="mode-title">' . $this->designer_mode_title . '</span>';
        $path = \Yii::get_alias('@webroot');
        $path .= DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
        $path .= 'lib' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;
        $path .= 'themes' . DIRECTORY_SEPARATOR . 'basic' . DIRECTORY_SEPARATOR;
        $path .= 'index' . DIRECTORY_SEPARATOR . 'design';
        $files = scandir($path);
        $sf = [];
        foreach ($files as $item) {
            if ($item != '.' && $item != '..') {
                $content = file_get_contents($path . DIRECTORY_SEPARATOR . $item);
                preg_match_all("/Info\\:\\:dataClass\\([\\'\"]([^}]+)[\\'\"]/", $content, $arr);
                $sf = array_merge($sf, $arr[1]);
            }
        }
        $font_colors = [];
        $query = tep_db_query('select value from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and attribute = 'color'");
        while ($item = tep_db_fetch_array($query)) {
            if ($font_colors[$item['value']] ?? null) {
                $font_colors[$item['value']]++;
            } else {
                $font_colors[$item['value']] = 1;
            }
        }
        $query = tep_db_query('select bs.setting_value from ' . TABLE_DESIGN_BOXES_TMP . ' b left join ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs on b.id = bs.box_id where b.theme_name = '" . tep_db_input($get['theme_name']) . "' and bs.setting_name = 'color'");
        while ($item = tep_db_fetch_array($query)) {
            if ($font_colors[$item['setting_value']] ?? null) {
                $font_colors[$item['setting_value']]++;
            } else {
                $font_colors[$item['setting_value']] = 1;
            }
        }
        $background_colors = [];
        $query = tep_db_query('select value from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and attribute = 'background-color'");
        while ($item = tep_db_fetch_array($query)) {
            if ($background_colors[$item['value']] ?? null) {
                $background_colors[$item['value']]++;
            } else {
                $background_colors[$item['value']] = 1;
            }
        }
        $query = tep_db_query('select bs.setting_value from ' . TABLE_DESIGN_BOXES_TMP . ' b left join ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs on b.id = bs.box_id where b.theme_name = '" . tep_db_input($get['theme_name']) . "' and bs.setting_name = 'background-color'");
        while ($item = tep_db_fetch_array($query)) {
            if ($background_colors[$item['setting_value']] ?? null) {
                $background_colors[$item['setting_value']]++;
            } else {
                $background_colors[$item['setting_value']] = 1;
            }
        }
        $border_colors = [];
        $query = tep_db_query('select value from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and attribute in ('border-top-color', 'border-left-color', 'border-right-color', 'border-bottom-color', 'border-color')");
        while ($item = tep_db_fetch_array($query)) {
            if ($border_colors[$item['value']] ?? null) {
                $border_colors[$item['value']]++;
            } else {
                $border_colors[$item['value']] = 1;
            }
        }
        $query = tep_db_query('select bs.setting_value from ' . TABLE_DESIGN_BOXES_TMP . ' b left join ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs on b.id = bs.box_id where b.theme_name = '" . tep_db_input($get['theme_name']) . "' and bs.setting_name in ('border-top-color', 'border-left-color', 'border-right-color', 'border-bottom-color', 'border-color')");
        while ($item = tep_db_fetch_array($query)) {
            if ($border_colors[$item['setting_value']] ?? null) {
                $border_colors[$item['setting_value']]++;
            } else {
                $border_colors[$item['setting_value']] = 1;
            }
        }
        $font_family = [];
        $query = tep_db_query('select value from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and attribute = 'font-family'");
        while ($item = tep_db_fetch_array($query)) {
            if ($item['value'] != 'FontAwesome' && $item['value'] != 'trueloaded') {
                if ($font_family[$item['value']] ?? null) {
                    $font_family[$item['value']]++;
                } else {
                    $font_family[$item['value']] = 1;
                }
            }
        }
        $query = tep_db_query('select bs.setting_value from ' . TABLE_DESIGN_BOXES_TMP . ' b left join ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs on b.id = bs.box_id where b.theme_name = '" . tep_db_input($get['theme_name']) . "' and bs.setting_name = 'font-family'");
        while ($item = tep_db_fetch_array($query)) {
            if ($item['setting_value'] != 'FontAwesome' && $item['setting_value'] != 'trueloaded') {
                if ($font_family[$item['setting_value']] ?? null) {
                    $font_family[$item['setting_value']]++;
                } else {
                    $font_family[$item['setting_value']] = 1;
                }
            }
        }
        $font_added = [];
        $font_added_arr = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and setting_name = 'font_added'");
        while ($item1 = tep_db_fetch_array($font_added_arr)) {
            preg_match('/font-family:[ \'"]+([^\'^"^;^}]+)/', $item1['setting_value'], $val);
            $font_added[] = $val[1];
        }
        $tpl = 'styles-new.tpl';
        if (Yii::$app->request->get('old')) {
            $tpl = 'styles.tpl';
        }
        $main_styles = Themes_Styles_Main::find()->where(['theme_name' => $get['theme_name']])->order_by('sort_order')->as_array()->all();
        $styles_groups = Themes_Styles_Groups::find()->where(['theme_name' => $get['theme_name']])->order_by('sort_order')->as_array()->all();
        $styles_group_tabs = Themes_Styles_Groups::find()->select('tab')->distinct()->where(['theme_name' => $get['theme_name']])->order_by('sort_order')->as_array()->all();
        $counts1 = Design_Boxes_Settings_Tmp::find()->select(['value' => 'setting_value', 'COUNT(*) as count'])->group_by(['setting_value'])->where(['theme_name' => $get['theme_name']])->and_where(['LIKE', 'setting_value', '$%', false])->as_array()->all();
        $counts2 = Themes_Styles::find()->select(['value', 'COUNT(*) as count'])->group_by(['value'])->where(['theme_name' => $get['theme_name']])->and_where(['LIKE', 'value', '$%', false])->as_array()->all();
        $counts = [];
        foreach (array_merge($counts1, $counts2) as $count) {
            $counts[$count['value']] = ($counts[$count['value']] ?? 0) + $count['count'];
        }
        foreach ($main_styles as $key => $style) {
            $main_styles[$key]['count'] = $counts['$' . $style['name']] ?? 0;
            $main_styles[$key]['oldName'] = $style['name'];
        }
        $styles_tree = [];
        foreach ($styles_group_tabs as $tab) {
            $groups = [];
            foreach ($styles_groups as $group) {
                if ($group['tab'] != $tab['tab']) {
                    continue;
                }
                $styles = [];
                foreach ($main_styles as $key => $style) {
                    if ($style['group_id'] == $group['group_id']) {
                        $styles[] = $style;
                    }
                }
                $groups[] = ['group_id' => $group['group_id'], 'group_name' => $group['group_name'], 'styles' => $styles];
            }
            $styles_tree[] = ['tab' => $tab['tab'], 'groups' => $groups];
        }
        return $this->render($tpl, ['theme_name' => $get['theme_name'], 'fontColors' => $font_colors, 'backgroundColors' => $background_colors, 'borderColors' => $border_colors, 'fontFamily' => $font_family, 'fontAdded' => $font_added, 'designer_mode' => $this->designer_mode, 'mainStyles' => $main_styles, 'stylesGroups' => $styles_groups, 'stylesGroupTabs' => $styles_group_tabs, 'stylesTree' => $styles_tree, 'menu' => 'styles']);
    }
    public function action_styles_change()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        Steps::styles_change(['from' => $get['from'], 'to' => $get['to'], 'style' => $get['style'], 'theme_name' => $get['theme_name']]);
        if ($get['style'] == 'border-color') {
            $attribute = " and attribute in ('border-top-color', 'border-left-color', 'border-right-color', 'border-bottom-color', 'border-color')";
        } else {
            $attribute = " and attribute = '" . tep_db_input($get['style']) . "'";
        }
        tep_db_perform(TABLE_THEMES_STYLES, ['value' => $get['to']], 'update', " theme_name = '" . tep_db_input($get['theme_name']) . "'" . $attribute . " and value = '" . tep_db_input($get['from']) . "'");
        if ($get['style'] == 'border-color') {
            $setting_name = " and bs.setting_name in ('border-top-color', 'border-left-color', 'border-right-color', 'border-bottom-color', 'border-color')";
        } else {
            $setting_name = " and bs.setting_name = '" . tep_db_input($get['style']) . "'";
        }
        $query = tep_db_query('select bs.id from ' . TABLE_DESIGN_BOXES_TMP . ' b left join ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs on b.id = bs.box_id where b.theme_name = '" . tep_db_input($get['theme_name']) . "' " . $setting_name . " and bs.setting_value = '" . tep_db_input($get['from']) . "'");
        while ($item = tep_db_fetch_array($query)) {
            tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, ['setting_value' => $get['to']], 'update', " id = '" . $item['id'] . "'");
        }
        Style::create_cache($get['theme_name']);
        return '<div style="padding: 30px;">Changed</div><script type="text/javascript">setTimeout(function(){location.reload()}, 500);</script>';
    }
    public function action_remove_class()
    {
        $theme_name = Yii::$app->request->get('theme_name');
        $css_class = Yii::$app->request->get('class');
        if (!$theme_name || !$css_class) {
            return 'Error';
        }
        Steps::remove_class(['class' => $css_class, 'theme_name' => $theme_name]);
        $attributes_delete = Themes_Styles::find()->where(['theme_name' => $theme_name, 'selector' => $css_class])->as_array()->all();
        $data = ['theme_name' => $theme_name, 'attributes_changed' => [], 'attributes_delete' => $attributes_delete, 'attributes_new' => []];
        Steps::css_save($data);
        Themes_Styles::delete_all(['theme_name' => $theme_name, 'selector' => $css_class]);
        Style::create_cache($theme_name);
        return 'Ok';
    }
    public function action_remove_hidden_boxes()
    {
        $theme_query = tep_db_query('select theme_name from ' . TABLE_THEMES . ' where 1');
        while ($theme = tep_db_fetch_array($theme_query)) {
            $query = tep_db_query('select bs.box_id from ' . TABLE_DESIGN_BOXES_SETTINGS . ' bs left join ' . TABLE_DESIGN_BOXES . " b on b.id = bs.box_id where bs.setting_name = 'display_none' and bs.visibility = '' and b.theme_name = '" . tep_db_input($theme['theme_name']) . "'");
            $removed = '';
            while ($item = tep_db_fetch_array($query)) {
                $id = $item['box_id'];
                $removed .= $id . '<br>';
                /*Steps::boxDelete([
                      'theme_name' => $theme['theme_name'],
                      'id' => $id
                  ]);*/
                //$this->actionBackupAuto($theme['theme_name']);
                //tep_db_query("delete from " . TABLE_DESIGN_BOXES_TMP . " where id = '" . (int) $id . "'");
                //tep_db_query("delete from " . TABLE_DESIGN_BOXES_SETTINGS_TMP . " where box_id = '" . (int) $id . "'");
                self::delete_block($id);
            }
            tep_db_query('DELETE FROM ' . TABLE_THEMES_STYLES . ' WHERE visibility > 10 AND visibility NOT IN (SELECT id FROM ' . TABLE_THEMES_SETTINGS . " WHERE `setting_name` LIKE 'media_query' )");
        }
        return 'Removed:<br>' . $removed;
    }
    public function action_create_update()
    {
        $post = tep_db_prepare_input(Yii::$app->request->post());
        if (!isset($post['theme_name'])) {
            return 'error';
        }
        if (!isset($post['steps']) || !is_array($post['steps'])) {
            return 'error';
        }
        $migration = Steps::create_migration($post['theme_name'], $post['steps']);
        header('Content-Type: application/json');
        header('Content-Transfer-Encoding: utf-8');
        header('Content-disposition: attachment; filename="migration-' . $post['theme_name'] . '.json"');
        return json_encode($migration);
        /*$idArr = [];
                foreach ($post['id_array'] as $item) {
                    $idArr[] = (int)$item;
                }
        
                $query = tep_db_query("
                    select *
                    from " . TABLE_THEMES_STEPS . "
                    where
                        theme_name = '" . tep_db_input($post['theme_name']) . "' and
                        event = 'cssSave' and
                        steps_id in('" . implode("','", $idArr) . "')
                    order by date_added asc");
        
                $themeSteps = [];
        
                while ($item = tep_db_fetch_array($query)) {
                    $themeSteps[] = json_decode($item['data'], true);
                }
        
                $attributes = Style::mergeSteps($themeSteps);
        
                $attributes['attributes_new'] = Style::changeVisibilityFromIdToWidth($attributes['attributes_new']);
                $attributes['attributes_changed'] = Style::changeVisibilityFromIdToWidth($attributes['attributes_changed']);
                $attributes['attributes_delete'] = Style::changeVisibilityFromIdToWidth($attributes['attributes_delete']);
        
        
                $filePath = DIR_FS_CATALOG . 'themes'
                    . DIRECTORY_SEPARATOR . $post['theme_name']
                    . DIRECTORY_SEPARATOR . 'updates'
                    . DIRECTORY_SEPARATOR;
                \yii\helpers\FileHelper::createDirectory($filePath);
                $date = date("U");
                $fileLength = file_put_contents($filePath . $date . '.json', json_encode($attributes));
        
                if ($fileLength) {
                    Style::saveUpdateDate($post['theme_name'], $date);
        
                    return '<div style="padding: 20px; text-align: center">Update created</div>';
                }
        
                return '<div style="padding: 20px; text-align: center">Error: Update not created</div>';*/
    }
    public function action_apply_migration()
    {
        $get = Yii::$app->request->get();
        if ($_FILES['file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $migration = json_decode(file_get_contents($_FILES['file']['tmp_name']), true);
            if ($result = Steps::apply_migration($get['theme_name'], $migration)) {
                Theme::elements_save($get['theme_name']);
                Design_Boxes_Cache::delete_all(['theme_name' => $get['theme_name']]);
                Theme::save_theme_version($get['theme_name']);
                return $result;
            }
        }
        return 'error';
    }
    public function action_apply_update()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $updates = Style::get_new_updates($get['theme_name']);
        $update = Style::merge_steps($updates);
        $update = Style::change_visibility_from_width_to_id($update, $get['theme_name']);
        $update = Style::add_exist_value_from_current_theme($update, $get['theme_name']);
        // and add local_id
        $update = Style::change_selectors_by_visibility($update);
        $attributes_by_media = Style::add_to_array_sorted_by_media_and_selector($update, $get['theme_name']);
        if (Yii::$app->request->is_ajax) {
            $this->layout = 'popup.tpl';
        }
        return $this->render('apply-update.tpl', ['attributes' => $attributes_by_media, 'theme_name' => $get['theme_name']]);
    }
    public function action_apply_update_submit()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $post = tep_db_prepare_input(Yii::$app->request->post());
        $updates = Style::get_new_updates($get['theme_name']);
        $update = Style::merge_steps($updates);
        $update = Style::change_visibility_from_width_to_id($update, $get['theme_name']);
        $update = Style::add_exist_value_from_current_theme($update, $get['theme_name']);
        // and add local_id
        Style::save_update($post, $update, $get['theme_name']);
        $sql_data_array = ['theme_name' => $get['theme_name'], 'setting_group' => 'hide', 'setting_name' => 'theme_update', 'setting_value' => date('U')];
        tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
        return Yii::$app->get_response()->redirect(['design/log', 'theme_name' => $get['theme_name']]);
    }
    public function action_css_status()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $dev_path = DIR_FS_CATALOG . 'themes/' . $get['theme_name'] . '/development/';
        if (!is_file($dev_path)) {
            \yii\helpers\File_Helper::create_directory($dev_path);
        }
        $development_mode = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where setting_name = 'development_mode' and setting_group = 'hide' and theme_name = '" . tep_db_input($get['theme_name']) . "'"));
        tep_db_query('delete from ' . TABLE_THEMES_SETTINGS . " where setting_name = 'development_mode' and setting_group = 'hide' and theme_name = '" . tep_db_input($get['theme_name']) . "'");
        $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES_CACHE . " where theme_name = '" . tep_db_input($get['theme_name']) . "'");
        while ($item = tep_db_fetch_array($query)) {
            if (!$item['accessibility']) {
                $item['accessibility'] = 'main';
            }
            if ($get['status']) {
                file_put_contents($dev_path . 'style' . $item['accessibility'] . '.css', $item['css']);
            } elseif (filemtime($dev_path . 'style' . $item['accessibility'] . '.css') > $development_mode['setting_value']) {
                $css = file_get_contents($dev_path . 'style' . $item['accessibility'] . '.css');
                if ($item['accessibility'] != 'main') {
                    $css = str_replace($item['accessibility'], '', $css);
                }
                $params = ['css' => $css, 'theme_name' => $get['theme_name'], 'widget' => $item['accessibility']];
                Style::css_save($params);
            }
        }
        if ($get['status']) {
            tep_db_perform(TABLE_THEMES_SETTINGS, ['theme_name' => $get['theme_name'], 'setting_name' => 'development_mode', 'setting_group' => 'hide', 'setting_value' => date('U')]);
        }
        return 'ok';
    }
    public function action_style_tab()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        if ($get['box_id']) {
            $query = tep_db_query('
                select setting_name, setting_value
                from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " \r\n                where \r\n                    box_id = '" . (int) $get['box_id'] . "' and \r\n                    visibility = '" . tep_db_input($get['visibility'] ? $get['visibility'] : '') . "' and\r\n                    language_id = '0'\r\n            ");
        } elseif ($get['data_class']) {
            $query = tep_db_query('
                select attribute as setting_name, value as setting_value
                from ' . TABLE_THEMES_STYLES . " \r\n                where theme_name = '" . tep_db_input($get['theme_name']) . "' and\r\n                selector = '" . tep_db_input($get['data_class']) . "' and \r\n                visibility = '" . tep_db_input($get['visibility'] ? $get['visibility'] : '') . "'\r\n            ");
        }
        $value = [];
        while ($item = tep_db_fetch_array($query)) {
            $value[$item['setting_name']] = $item['setting_value'];
        }
        $this->layout = 'popup.tpl';
        $font_added = [];
        $font_added_arr = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($get['theme_name']) . "' and setting_name = 'font_added'");
        while ($item1 = tep_db_fetch_array($font_added_arr)) {
            preg_match('/font-family:[ \'"]+([^\'^"^;^}]+)/', $item1['setting_value'], $val);
            $font_added[] = $val[1];
        }
        return $this->render('/../design/boxes/views/include/style_tab.tpl', ['id' => $get['id'], 'name' => $get['name'], 'theme_name' => $get['theme_name'], 'value' => $value, 'responsive' => $get['visibility'] > 10 ? '1' : '', 'responsive_settings' => json_decode($get['responsive_settings'], true), 'block_view' => $get['block_view'], 'font_added' => $font_added, 'designer_mode' => $this->designer_mode, 'styleHide' => isset($get['data_class']) && $get['data_class'] ? Style::hide($get['data_class']) : []]);
    }
    public function action_choose_view()
    {
        $get = tep_db_prepare_input(Yii::$app->request->get());
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/choose-view'), 'title' => 'Choose View "' . Theme::get_theme_title($get['theme_name']) . '"'];
        $this->selected_menu = ['design_controls', 'design/themes'];
        return $this->render('choose-view.tpl', ['theme_name' => $get['theme_name'], 'theme_name_mobile' => $get['theme_name'] . '-mobile', 'designer_mode' => $this->designer_mode]);
    }
    public function action_create_mobile_theme()
    {
        $theme_name = Yii::$app->request->get('theme_name');
        if (substr($theme_name, -7) !== '-mobile') {
            return WRONG_THEME_NAME;
        }
        $desktop_theme_name = substr($theme_name, 0, -7);
        $theme = tep_db_fetch_array(tep_db_query('select id from ' . TABLE_THEMES . " where theme_name = '" . tep_db_input($desktop_theme_name) . "'"));
        if (!$theme['id']) {
            return WRONG_THEME_NAME;
        }
        Theme::theme_remove($theme_name, false);
        Theme::copy_theme($theme_name, $desktop_theme_name, 'copy');
        //'link', 'copy'
        Style::create_cache($theme_name);
        return TEXT_CREATED;
    }
    public function action_get_component_html()
    {
        $get_request = \Yii::$app->request->get();
        if (!$get_request['name']) {
            return '';
        }
        $platforms_to_themes = \common\models\Platforms_To_Themes::find_one((int) $get_request['platform_id']);
        $themes = \common\models\Themes::find_one($platforms_to_themes['theme_id']);
        $theme_name = $themes->theme_name;
        $get_request['theme_name'] = $theme_name;
        if ($get_request['option'] && $get_request['option_val']) {
            $get_request[$get_request['option']] = $get_request['option_val'];
        }
        define('THEME_NAME', $theme_name);
        $block = \frontend\design\Block::widget(['name' => \common\classes\design::page_name($get_request['name']), 'params' => ['params' => $get_request]]);
        $css = file_get_contents(Info::theme_file('/css/base_3.css', 'fs'));
        $widgets = \frontend\design\Info::get_widgets_names();
        $area_arr[] = '';
        foreach ($widgets as $widget) {
            $area_arr[] = tep_db_input($widget);
        }
        $area = "'" . implode("','", $area_arr) . "'";
        $query = tep_db_query('select css from ' . TABLE_THEMES_STYLES_CACHE . " where theme_name = '" . tep_db_input($theme_name) . "' and accessibility in(" . $area . ')');
        while ($item = tep_db_fetch_array($query)) {
            $css .= $item['css'];
        }
        $css .= \frontend\design\Block::get_styles();
        $css = \frontend\design\Info::minify_css($css);
        $css = '<style type="text/css">' . $css . '</style>';
        return $block . $css;
    }
    public function action_webp()
    {
        $this->selected_menu = ['design_controls', 'design/themes'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('design/themes'), 'title' => 'Create webp images'];
        $this->view->heading_title = TITLE_CREATE_WEBP_IMAGES;
        $button_settings = \common\helpers\Acl::get_extension_create_images_settings();
        return $this->render('webp.tpl', ['imagewebp' => function_exists('imagewebp'), 'buttonSettings' => $button_settings, 'designer_mode' => $this->designer_mode]);
    }
    public function action_create_webp()
    {
        $type = Yii::$app->request->get('type', false);
        $iteration = (int) \Yii::$app->request->get('iteration', 0);
        return \common\classes\Images::create_all_webp_images($type, $iteration);
    }
    public function action_create_pdf_font()
    {
        $font_path = Yii::$app->request->post('font_path');
        if (substr($font_path, 0, 4) == 'http') {
            return \TCPDF_FONTS::add_tt_ffont($font_path);
        } else if (is_file(DIR_FS_CATALOG . $font_path)) {
            return \TCPDF_FONTS::add_tt_ffont(DIR_FS_CATALOG . $font_path);
        } else {
            return false;
        }
    }
    public function action_theme_title()
    {
        $theme_name = Yii::$app->request->post('theme_name');
        $group_id = Yii::$app->request->post('group_id');
        $title = Yii::$app->request->post('title');
        $image = Yii::$app->request->post('image', false);
        $image_upload = Yii::$app->request->post('image_upload');
        $image_delete = Yii::$app->request->post('image_delete');
        if ($theme_name && !\common\models\Themes::find_one(['theme_name' => $theme_name])) {
            return json_encode(['error' => THEME_NAME_DOESNT_EXIST]);
        }
        if (!$title) {
            return json_encode(['error' => TITLE_CANT_BE_BLANK]);
        }
        $response_img = '';
        if (!$theme_name && $group_id && !\common\models\Themes_Groups::find_one(['themes_group_id' => $group_id])) {
            return json_encode(['error' => THEME_NAME_DOESNT_EXIST]);
        } elseif (!$theme_name && $group_id) {
            $theme = \common\models\Themes_Groups::find_one(['themes_group_id' => $group_id]);
            $theme->image = \common\helpers\Image::prepare_saving_image($theme->image ?? '', $image, $image_upload, '', false, true);
            $response_img = $theme->image;
        } else {
            $theme = \common\models\Themes::find_one(['theme_name' => $theme_name]);
            if ($theme) {
                $theme_setting = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => 'hide', 'setting_name' => 'theme_image']);
                $theme_image = '';
                if ($theme_setting) {
                    $theme_image = $theme_setting->setting_value;
                }
                $theme_image = \common\helpers\Image::prepare_saving_image($theme_image, $image, $image_upload, 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR . 'img', false, true);
                if (!$theme_image) {
                    Themes_Settings::delete_all(['theme_name' => $theme_name, 'setting_group' => 'hide', 'setting_name' => 'theme_image']);
                } elseif ($theme_setting) {
                    $theme_setting->setting_value = $theme_image;
                    $theme_setting->save();
                } else {
                    $theme_setting = new Themes_Settings();
                    $theme_setting->theme_name = $theme_name;
                    $theme_setting->setting_group = 'hide';
                    $theme_setting->setting_name = 'theme_image';
                    $theme_setting->setting_value = $theme_image;
                    $theme_setting->save();
                }
                $response_img = $theme_image;
            }
        }
        if (!$theme) {
            return json_encode(['error' => 'db error']);
        }
        $theme->title = $title;
        $theme->save();
        return json_encode(['title' => $theme->title, 'image' => $response_img]);
    }
    public function action_add_group()
    {
        $title = Yii::$app->request->post('title');
        if (!$title) {
            $this->layout = 'popup.tpl';
            return $this->render('add-group.tpl', []);
        }
        $group = new \common\models\Themes_Groups();
        $group->title = $title;
        $group->save();
        return json_encode(['text' => TEXT_GROUP_ADDED]);
    }
    public function action_theme_move()
    {
        $title = Yii::$app->request->post('title');
        $group_id = Yii::$app->request->post('group_id', '');
        if (!$title && $group_id === '') {
            $groups = \common\models\Themes_Groups::find()->as_array()->all();
            $this->layout = 'popup.tpl';
            return $this->render('theme-move.tpl', ['groups' => $groups, 'theme_name' => Yii::$app->request->get('theme_name')]);
        }
        if ($group_id == 'add') {
            if (!$title) {
                return json_encode(['error' => TITLE_CANT_BE_BLANK]);
            }
            $group = new \common\models\Themes_Groups();
            $group->title = $title;
            $group->save();
            $group_id = $group->get_primary_key();
        }
        if (!$group_id && $group_id !== 0 && $group_id !== '0') {
            return json_encode(['error' => 'Group error']);
        }
        $theme_name = Yii::$app->request->post('theme_name');
        $themes = \common\models\Themes::find_one(['theme_name' => $theme_name]);
        if (!$themes) {
            return json_encode(['error' => 'Theme not found']);
        }
        $themes->themes_group_id = (int) $group_id;
        $themes->save(false);
        return json_encode(['text' => TEXT_THEME_MOVED]);
    }
    public function action_group_remove()
    {
        $group_id = Yii::$app->request->get('group_id', 0);
        if ($group_id) {
            $themes = \common\models\Themes::find()->where(['themes_group_id' => $group_id])->as_array()->all();
            $group_title = \common\models\Themes_Groups::find_one(['themes_group_id' => $group_id])->title;
            $this->layout = 'popup.tpl';
            return $this->render('group-remove.tpl', ['themes' => $themes, 'groupTitle' => $group_title, 'group_id' => $group_id]);
        }
        $group_id = Yii::$app->request->post('group_id', 0);
        if (!$group_id) {
            return json_encode(['error' => 'Error']);
        }
        $themes = \common\models\Themes::find()->where(['themes_group_id' => $group_id])->as_array()->all();
        foreach ($themes as $theme) {
            Theme::theme_remove($theme['theme_name']);
            Theme::theme_remove($theme['theme_name'] . '-mobile');
        }
        \common\models\Themes_Groups::delete_all(['themes_group_id' => $group_id]);
        return json_encode(['text' => TEXT_GROUP_REMOVED]);
    }
    public function action_theme_sort()
    {
        $sort = Yii::$app->request->post('sort', 0);
        if (!$sort || !is_array($sort)) {
            return json_encode(['error' => 'Error: no sort array']);
        }
        foreach ($sort as $key => $item) {
            if ($item['theme_name']) {
                $theme = \common\models\Themes::find_one(['theme_name' => $item['theme_name']]);
                $theme->sort_order = $key + 1;
                $theme->save(false);
            } elseif ($item['group_id']) {
                $group = \common\models\Themes_Groups::find_one(['themes_group_id' => $item['group_id']]);
                $group->sort_order = $key + 1;
                $group->save(false);
            }
        }
        return json_encode(['text' => 'Sorted']);
    }
    public function action_content_widget()
    {
        $params = tep_db_prepare_input(Yii::$app->request->get());
        $id = 0;
        $settings = [];
        $widget_params = ['main_content' => true];
        $content = '';
        if (is_file(Yii::get_alias('@app') . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'boxes' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $params['name']) . '.php')) {
            $widget_name = 'backend\design\boxes\\' . str_replace('\\\\', '\\', $params['name']);
            $content = $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings]);
        } elseif ($ext = \common\helpers\Acl::check_extension($params['name'], 'showTabSettings', true)) {
            $widget_name = 'backend\design\boxes\Def';
            $settings['tabs'] = ['class' => $ext, 'method' => 'showTabSettings'];
            $content = $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings]);
        } elseif ($ext = \common\helpers\Acl::check_extension($params['name'], 'showSettings', true)) {
            $widget_name = 'backend\design\boxes\Def';
            $settings['class'] = $ext;
            $settings['method'] = 'showSettings';
            $content = $widget_name::widget(['id' => $id, 'params' => $widget_params, 'settings' => $settings]);
        }
        $this->layout = false;
        return $this->render('content-widget.tpl', ['content' => $content, 'widgetName' => $params['name']]);
    }
    public function action_set_theme_setting()
    {
        $theme_name = Yii::$app->request->post('theme_name', false);
        $setting_group = Yii::$app->request->post('setting_group', false);
        $setting_name = Yii::$app->request->post('setting_name', false);
        $setting_value = Yii::$app->request->post('setting_value', 2);
        if (!$theme_name || !$setting_group || !$setting_name) {
            return json_encode(['error' => 'Error']);
        }
        $theme_setting = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => $setting_group, 'setting_name' => $setting_name]);
        if (!$theme_setting) {
            $theme_setting = new Themes_Settings();
            $theme_setting->theme_name = $theme_name;
            $theme_setting->setting_group = $setting_group;
            $theme_setting->setting_name = $setting_name;
        }
        $theme_setting->setting_value = $setting_value;
        $theme_setting->save();
        if ($theme_setting->errors ?? false) {
            return json_encode(['error' => 'Error']);
        }
        return json_encode(['text' => MESSAGE_SAVED]);
    }
    public function action_export_styles()
    {
        $theme_name = Yii::$app->request->post('theme_name', false);
        $type = Yii::$app->request->post('type', false);
        $save_to_groups = Yii::$app->request->post('save-to-groups', false);
        $name = Yii::$app->request->post('name', 2);
        $comment = Yii::$app->request->post('comment', 2);
        if (!$name) {
            return json_encode(['error' => PLEASE_ENTER_NAME]);
        }
        if (!$theme_name || !$type) {
            return json_encode(['error' => 'Error']);
        }
        $fs_catalog = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'backend', 'design', 'groups']) . DIRECTORY_SEPARATOR;
        $theme_archive = design::page_name($name);
        File_Helper::create_directory($fs_catalog);
        chmod($fs_catalog, 0755);
        $_name = $theme_archive;
        for ($i = 1; $i < 100 && file_exists($fs_catalog . $theme_archive . '.zip'); $i++) {
            $theme_archive = $_name . '-' . $i;
        }
        $zip = new \Zip_Archive();
        if ($zip->open($fs_catalog . $theme_archive . '.zip', \Zip_Archive::CREATE) !== true) {
            return 'Error';
        }
        if (in_array($type, ['color', 'font'])) {
            if ($type == 'color') {
                $type_arr = ['color', 'color-var', 'color-opacity'];
            } elseif ($type == 'font') {
                $type_arr = ['font', 'font-var'];
            }
            $styles = Themes_Styles_Main::find()->where(['theme_name' => $theme_name, 'type' => $type_arr])->as_array()->all();
            $groups = Themes_Styles_Groups::find()->where(['theme_name' => $theme_name])->as_array()->all();
            if (in_array($type, ['font', 'font-var']) && is_array($styles)) {
                foreach ($styles as $key => $style) {
                    $font_setting = Themes_Settings::find()->where(['theme_name' => $theme_name, 'setting_group' => 'extend', 'setting_name' => 'font_added'])->and_where(['like', 'setting_value', $style['value']])->as_array()->one();
                    $styles[$key]['font_settings'] = preg_replace('/themes[\/\\\\]' . $theme_name . '/', 'themes/<theme_name>', $font_setting['setting_value']);
                    $files = [];
                    preg_match_all("/url\\([\\'\"]{0,1}(themes[\\/\\\\]' . {$theme_name} . '[^'^\"^)]+)\\?[^'^\"^)]+[\\'\"]{0,1}\\)/", $font_setting['setting_value'], $files);
                    if (isset($files[1]) && is_array($files[1])) {
                        foreach ($files[1] as $file) {
                            if (is_file(DIR_FS_CATALOG . $file)) {
                                $file_path = explode('/', $file);
                                $file_path = explode('\\', end($file_path));
                                $file_name = end($file_path);
                                $zip->add_file(DIR_FS_CATALOG . $file, $file_name);
                            }
                        }
                    }
                }
            }
            $all_styles['main'] = $styles;
            $all_styles['groups'] = $groups;
        } else {
            $all_styles = Style::get_css_elements($theme_name, $type);
        }
        $json = json_encode($all_styles);
        $zip->add_from_string('data.json', $json);
        $info = ['name' => $name, 'name_title' => $name, 'groupCategory' => $type, 'comment' => $comment, 'page_type' => $type];
        $zip->add_from_string('info.json', json_encode($info));
        $zip->add_from_string('images.json', json_encode([]));
        $zip->close();
        $theme_archive .= '.zip';
        Groups::synchronize();
        if ($save_to_groups) {
            $message = sprintf(SAVED_TO_GROUPS, $name);
        } else {
            $message = sprintf(COMMON_CREATED, $name);
        }
        return json_encode(['text' => $message, 'filename' => $theme_archive]);
    }
    public function action_check_origin_theme()
    {
        $this->layout = false;
        $theme_file = Groups::group_file_path() . DIRECTORY_SEPARATOR . 'origin.zip';
        if (!is_file($theme_file)) {
            return '';
        }
        foreach (['origin', 'new_theme'] as $theme_name) {
            if (Design_Boxes::find_one(['theme_name' => $theme_name])) {
                continue;
            }
            Theme::import($theme_name, $theme_file);
            Style::create_cache($theme_name);
        }
        return '';
    }
}