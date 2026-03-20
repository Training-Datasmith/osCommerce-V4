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

use backend\controllers\Design_Controller;
use backend\models\Admin;
use common\classes\design as DesignerHelper;
use common\models\Design_Boxes_Settings_Tmp;
use common\models\Design_Boxes_Tmp;
use common\models\Themes_Settings;
use common\models\Themes_Steps;
use common\models\Themes_Styles;
use common\models\Themes_Styles_Groups;
use common\models\Themes_Styles_Main;
use yii\helpers\Array_Helper;
class Steps
{
    public static $elements_event = ['stepSave', 'boxAdd', 'blocksMove', 'boxSave', 'boxDelete', 'importBlock', 'elementsSave', 'elementsCancel'];
    public static $styles_event = ['styleSave', 'themeSave', 'themeCancel'];
    public static function step_save($event, $data, $theme_name, $change_active = true)
    {
        $before = tep_db_fetch_array(tep_db_query('select steps_id from ' . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($theme_name) . "'"));
        if ($change_active) {
            tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        }
        $admin = new Admin();
        $data['designer_mode'] = $admin->get_additional_data('designer_mode');
        global $_SESSION;
        $sql_data_array = ['parent_id' => $before['steps_id'] ?? 0, 'event' => $event, 'data' => json_encode($data), 'theme_name' => $theme_name, 'date_added' => 'now()', 'active' => $change_active ? '1' : '', 'admin_id' => $_SESSION && $_SESSION['login_id'] ? $_SESSION['login_id'] : 0, 'mode' => $data['designer_mode'] ? $data['designer_mode'] : 'basic'];
        tep_db_perform(TABLE_THEMES_STEPS, $sql_data_array);
    }
    public static function undo($theme_name)
    {
        $step = Themes_Steps::find()->where(['active' => '1', 'theme_name' => $theme_name])->as_array()->one();
        $action = $step['event'] . 'Undo';
        if (!is_array($step['data'])) {
            $step['data'] = json_decode($step['data'], true);
        }
        if (method_exists(self::class, $action)) {
            self::$action($step);
        }
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '1'], 'update', "steps_id = '" . (int) $step['parent_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
        if (!method_exists(self::class, $action)) {
            self::undo($theme_name);
        }
    }
    public static function redo($theme_name, $steps_id)
    {
        $step = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_THEMES_STEPS . " where steps_id='" . (int) $steps_id . "' and theme_name='" . tep_db_input($theme_name) . "'"));
        $action = $step['event'] . 'Redo';
        if (!is_array($step['data'])) {
            $step['data'] = json_decode($step['data'], true);
        }
        self::$action($step);
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '1'], 'update', "steps_id = '" . (int) $steps_id . "' and theme_name='" . tep_db_input($theme_name) . "'");
    }
    public static function create_migration($theme_name, $steps_i_ds)
    {
        if (!isset($theme_name) || !isset($steps_i_ds) || !is_array($steps_i_ds)) {
            return 'error';
        }
        $steps = \common\models\Themes_Steps::find()->where(['IN', 'steps_id', $steps_i_ds])->and_where(['theme_name' => $theme_name])->as_array()->all();
        $migration = [];
        foreach ($steps as $step) {
            $step['data'] = json_decode($step['data'], true);
            $migration[] = $step;
        }
        return $migration;
    }
    public static function apply_migration($theme_name, $migration)
    {
        if (!isset($migration) || !is_array($migration)) {
            return 'migration is empty';
        }
        $count = 0;
        foreach ($migration as $step) {
            if (!in_array($step['event'], ['cssSave', 'boxAdd', 'blocksMove', 'boxSave', 'boxDelete', 'settings', 'javascriptSave', 'addPage', 'removePageTemplate', 'addPageSettings', 'importBlock', 'stylesChange', 'copyPage'])) {
                continue;
            }
            $redo = $step['event'] . 'Redo';
            $step['theme_name'] = $theme_name;
            self::$redo($step, $theme_name);
            $count++;
        }
        //self::stepSave('applyMigration', $migration, $themeName);
        if ($count > 0) {
            return 'applied';
        } else {
            return 'not applied';
        }
    }
    public static function apply_migration_udo()
    {
    }
    public static function apply_migration_redo()
    {
    }
    public static function block_name_to_step($block_name, $theme_name)
    {
        $block = explode('-', $block_name);
        if (count($block) > 1) {
            $design_boxes = Design_Boxes_Tmp::find_one(['id' => $block[1], 'theme_name' => $theme_name]);
            if ($design_boxes) {
                $block_name_step = $design_boxes->microtime;
                if (isset($block[2])) {
                    $block_name_step = $block_name_step . '-' . $block[2];
                }
            } else {
                $block_name_step = $block_name;
            }
        } else {
            $block_name_step = $block_name;
        }
        return $block_name_step;
    }
    public static function block_name_to_db($block_name, $theme_name)
    {
        if (preg_match('/^[0-9]{5}/', $block_name) > 0) {
            $block_split = explode('-', $block_name);
            $_block_name_db = Design_Boxes_Tmp::find_one(['microtime' => $block_split[0], 'theme_name' => $theme_name]);
            if (!$_block_name_db) {
                return '';
            }
            $block_name_db = $_block_name_db->id;
            $block_name_db = 'block-' . $block_name_db;
            if (isset($block_split[1])) {
                $block_name_db = $block_name_db . '-' . $block_split[1];
            }
        } else {
            $block_name_db = $block_name;
        }
        return $block_name_db;
    }
    public static function box_add($data)
    {
        $block_name = self::block_name_to_step($data['block_name'], $data['theme_name']);
        $data_s = ['block_id' => $data['id'], 'microtime' => $data['microtime'], 'block_name' => $block_name, 'page_name' => Theme::get_page_name($data['id']), 'widget_name' => $data['widget_name'], 'sort_order' => $data['sort_order']];
        if (isset($data['sort_arr']) && $data['sort_arr']) {
            $data_s = array_merge($data_s, ['sort_arr' => $data['sort_arr'], 'sort_arr_old' => $data['sort_arr_old']]);
        }
        self::step_save('boxAdd', $data_s, $data['theme_name']);
    }
    public static function box_add_undo($step)
    {
        $data = $step['data'];
        $design_boxes = Design_Boxes_Tmp::find_one(['microtime' => $data['microtime'], 'theme_name' => $step['theme_name']]);
        Design_Boxes_Tmp::delete_all(['microtime' => $data['microtime'], 'theme_name' => $step['theme_name']]);
        Design_Boxes_Settings_Tmp::delete_all(['microtime' => $data['microtime'], 'theme_name' => $step['theme_name']]);
        if ($design_boxes) {
            Design_Controller::delete_block($design_boxes->id);
        }
    }
    public static function box_add_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        if (Design_Boxes_Tmp::find_one(['microtime' => $data['microtime'], 'theme_name' => $theme_name])) {
            return 'box already exist';
        }
        $block_name = self::block_name_to_db($data['block_name'], $theme_name);
        $design_boxes = new Design_Boxes_Tmp();
        $design_boxes->set_attributes(['microtime' => $data['microtime'], 'theme_name' => $theme_name, 'block_name' => $block_name, 'widget_name' => $data['widget_name'], 'sort_order' => $data['sort_order']]);
        $design_boxes->save();
        if (count($design_boxes->errors) > 0) {
            return 'box not added';
        }
        if (!isset($data['sort_arr']) || !is_array($data['sort_arr'])) {
            return 'box added';
        }
        foreach ($data['sort_arr'] as $microtime => $order) {
            $design_boxes_sibling = Design_Boxes_Tmp::find_one(['microtime' => $microtime, 'theme_name' => $theme_name]);
            if (!$design_boxes_sibling) {
                continue;
            }
            $design_boxes_sibling->sort_order = (int) $order;
            $design_boxes_sibling->save();
        }
        return 'box added';
    }
    public static function blocks_move($data)
    {
        $positions = [];
        foreach ($data['positions'] as $position) {
            $position['block_name'] = self::block_name_to_step($position['block_name'], $data['theme_name']);
            $positions[] = $position;
        }
        $positions_old = [];
        foreach ($data['positions_old'] as $position) {
            $position['block_name'] = self::block_name_to_step($position['block_name'], $data['theme_name']);
            $positions_old[] = $position;
        }
        $data_s = ['positions' => $positions, 'positions_old' => $positions_old];
        self::step_save('blocksMove', $data_s, $data['theme_name']);
    }
    public static function blocks_move_undo($step)
    {
        $data = $step['data'];
        if (!isset($data['positions_old']) || !is_array($data['positions_old'])) {
            return '';
        }
        foreach ($data['positions_old'] as $item) {
            $design_boxes = Design_Boxes_Tmp::find_one(['microtime' => $item['microtime'], 'theme_name' => $step['theme_name']]);
            if ($design_boxes) {
                $design_boxes->sort_order = $item['sort_order'];
                $design_boxes->block_name = self::block_name_to_db($item['block_name'], $step['theme_name']);
                $design_boxes->save();
            }
        }
    }
    public static function blocks_move_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        if (!isset($data['positions']) || !is_array($data['positions'])) {
            return '';
        }
        foreach ($data['positions'] as $item) {
            $design_boxes = Design_Boxes_Tmp::find_one(['microtime' => $item['microtime'], 'theme_name' => $theme_name]);
            if ($design_boxes) {
                $design_boxes->sort_order = $item['sort_order'];
                $design_boxes->block_name = self::block_name_to_db($item['block_name'], $theme_name);
                $design_boxes->save();
            }
        }
    }
    public static function setting_visibility_to_step($settings, $theme_name)
    {
        $theme_media = Style::get_theme_media($theme_name);
        foreach ($settings as $key => $setting) {
            if ($setting['visibility'] > 10 && isset($theme_media[$setting['visibility']])) {
                if (!isset($settings[$key])) {
                    $settings[$key] = [];
                }
                $settings[$key]['visibility'] = $theme_media[$setting['visibility']];
            }
        }
        return $settings;
    }
    public static function setting_visibility_to_db($settings, $theme_name)
    {
        $theme_media = Style::get_theme_media($theme_name, false);
        foreach ($settings as $key => $setting) {
            if ($setting['visibility'] && strlen($setting['visibility']) > 1 && !str_contains($setting['visibility'], ',')) {
                if (!$theme_media[$setting['visibility']]) {
                    $themes_setting = new Themes_Settings();
                    $themes_setting->theme_name = $theme_name;
                    $themes_setting->setting_group = 'extend';
                    $themes_setting->setting_name = 'media_query';
                    $themes_setting->setting_value = $setting['visibility'];
                    $themes_setting->save();
                    $settings[$key]['visibility'] = $themes_setting->id;
                } else {
                    $settings[$key]['visibility'] = $theme_media[$setting['visibility']];
                }
            }
        }
        return $settings;
    }
    public static function box_save($data)
    {
        $data_s = ['microtime' => $data['microtime'], 'box_id' => $data['box_id'], 'page_name' => Theme::get_page_name($data['box_id']), 'box_settings' => self::setting_visibility_to_step($data['box_settings'], $data['theme_name']), 'box_settings_old' => self::setting_visibility_to_step($data['box_settings_old'], $data['theme_name']), 'widget_params' => $data['widget_params'] ?? '', 'widget_params_old' => $data['widget_params_old'] ?? ''];
        self::step_save('boxSave', $data_s, $data['theme_name']);
    }
    public static function box_save_undo($step)
    {
        $data = $step['data'];
        if (!isset($data['box_settings_old']) || !is_array($data['box_settings_old'])) {
            return '';
        }
        $theme_name = Array_Helper::get_value($data, ['box_settings_old', 0, 'theme_name'], false);
        if (!$theme_name) {
            return '';
        }
        $design_box = Design_Boxes_Tmp::find_one(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        if (!$design_box) {
            return '';
        }
        $design_box->widget_params = $data['widget_params_old'] ?? '';
        $design_box->save();
        Design_Boxes_Settings_Tmp::delete_all(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        $data['box_settings_old'] = self::setting_visibility_to_db($data['box_settings_old'], $theme_name);
        foreach ($data['box_settings_old'] as $item) {
            $design_boxes_settings = new Design_Boxes_Settings_Tmp();
            $design_boxes_settings->set_attributes(['box_id' => $box_id->id, 'microtime' => $item['microtime'], 'theme_name' => $theme_name, 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value'], 'language_id' => $item['language_id'], 'visibility' => $item['visibility']]);
            $design_boxes_settings->save();
        }
    }
    public static function box_save_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        if (!isset($data['box_settings']) || !is_array($data['box_settings'])) {
            return '';
        }
        $design_box = Design_Boxes_Tmp::find_one(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        if (!$design_box) {
            return '';
        }
        $design_box->widget_params = $data['widget_params'] ?? '';
        $design_box->save();
        Design_Boxes_Settings_Tmp::delete_all(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        $data['box_settings'] = self::setting_visibility_to_db($data['box_settings'], $theme_name);
        foreach ($data['box_settings'] as $item) {
            $design_boxes_settings = new Design_Boxes_Settings_Tmp();
            $design_boxes_settings->set_attributes(['box_id' => $design_box->id, 'microtime' => $item['microtime'], 'theme_name' => $theme_name, 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value'], 'language_id' => $item['language_id'], 'visibility' => $item['visibility']]);
            $design_boxes_settings->save();
        }
    }
    public static function box_delete($data)
    {
        $data_s = \backend\design\Theme::blocks_tree($data['id']);
        $siblings = Design_Boxes_Tmp::find()->where(['block_name' => $data_s['block_name']])->as_array()->all();
        $data_s['block_name'] = self::block_name_to_step($data_s['block_name'], $data['theme_name']);
        $data_s['box_id'] = $data['id'];
        $data_s['siblings'] = $siblings;
        self::step_save('boxDelete', $data_s, $data['theme_name']);
    }
    public static function box_delete_undo($step)
    {
        $data = $step['data'];
        $block_name = self::block_name_to_db($data['block_name'], $step['theme_name']);
        Theme::blocks_tree_import($data, $step['theme_name'], $block_name, $data['sort_order']);
    }
    public static function box_delete_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        $box = Design_Boxes_Tmp::find_one(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        if (!$box) {
            return;
        }
        $box_id = $box->id;
        Design_Boxes_Tmp::delete_all(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        Design_Boxes_Settings_Tmp::delete_all(['microtime' => $data['microtime'], 'theme_name' => $theme_name]);
        Design_Controller::delete_block($box_id);
    }
    public static function remove_page_template($data)
    {
        $page_name = Designer_Helper::page_name($data['page_title']);
        $design_boxes = Design_Boxes_Tmp::find()->where(['block_name' => $page_name, 'theme_name' => $data['theme_name']])->as_array()->all();
        $content = [];
        foreach ($design_boxes as $box) {
            $content[] = \backend\design\Theme::blocks_tree($box['id']);
        }
        $data_s['content'] = $content;
        $themes_settings = tep_db_query('
                select * 
                from ' . TABLE_THEMES_SETTINGS . " \r\n                where \r\n                    theme_name = '" . tep_db_input($data['theme_name']) . "' and \r\n                    ((setting_group = 'added_page' and setting_value = '" . tep_db_input($data['page_title']) . "') or \r\n                     (setting_group = 'added_page_settings' and setting_name = '" . tep_db_input($data['page_title']) . "'))\r\n        ");
        while ($setting = tep_db_fetch_array($themes_settings)) {
            $data_s['themes_settings'][] = $setting;
        }
        $data_s['page_title'] = $data['page_title'];
        self::step_save('removePageTemplate', $data_s, $data['theme_name']);
    }
    public static function remove_page_template_undo($step)
    {
        $data = $step['data'];
        $added_page = Themes_Settings::find_one(['theme_name' => $step['theme_name'], 'setting_group' => 'added_page', 'setting_value' => $data['page_title']]);
        if ($added_page) {
            return '';
        }
        foreach ($data['themes_settings'] as $setting) {
            $added_page = new Themes_Settings();
            $added_page->theme_name = $step['theme_name'];
            $added_page->setting_group = $setting['setting_group'];
            $added_page->setting_name = $setting['setting_name'];
            $added_page->setting_value = $setting['setting_value'];
            $added_page->save();
        }
        foreach ($data['content'] as $box) {
            Theme::blocks_tree_import($box, $step['theme_name'], Designer_Helper::page_name($data['page_title']));
        }
    }
    public static function remove_page_template_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        Themes_Settings::delete_all(['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_value' => $data['page_title']]);
        Themes_Settings::delete_all(['theme_name' => $theme_name, 'setting_group' => 'added_page_settings', 'setting_value' => $data['page_title']]);
        self::delete_page($data['page_title'], $theme_name);
    }
    public static function delete_page($page_name, $theme_name)
    {
        $design_boxes = Design_Boxes_Tmp::find()->where(['block_name' => Designer_Helper::page_name($page_name), 'theme_name' => $theme_name])->as_array()->all();
        if (is_array($design_boxes)) {
            foreach ($design_boxes as $design_box) {
                Design_Boxes_Tmp::delete_all(['microtime' => $design_box['microtime'], 'theme_name' => $theme_name]);
                Design_Boxes_Settings_Tmp::delete_all(['microtime' => $design_box['microtime'], 'theme_name' => $theme_name]);
                Design_Controller::delete_block($design_box['id']);
            }
        }
    }
    public static function import_block($data)
    {
        $content = [];
        $microtime = [];
        $block_name = [];
        if (is_array($data['idArr'])) {
            foreach ($data['idArr'] as $id) {
                $content[] = Theme::blocks_tree($id);
                $new_box = Design_Boxes_Tmp::find_one(['id' => $id]);
                $microtime[] = $new_box->microtime;
                $block_name[] = self::block_name_to_step($new_box->block_name, $data['theme_name']);
                $data['siblings'] = Design_Boxes_Tmp::find()->where(['block_name' => $new_box->block_name, 'theme_name' => $data['theme_name']])->as_array()->all();
            }
        }
        $data['content'] = $content;
        $data['microtime'] = $microtime;
        $data['block_name'] = $block_name;
        self::step_save('importBlock', $data, $data['theme_name']);
    }
    public static function import_block_undo($step)
    {
        $data = $step['data'];
        if ($data['content']['microtime'] ?? false) {
            $content = [$data['content']];
        } else {
            $content = $data['content'];
        }
        foreach ($content as $key => $block) {
            $box = Design_Boxes_Tmp::find_one(['microtime' => $block['microtime'], 'theme_name' => $step['theme_name']]);
            Design_Boxes_Settings_Tmp::delete_all(['microtime' => $block['microtime'], 'theme_name' => $step['theme_name']]);
            if ($key == 0 && $data['id_old']) {
                $box->id = $data['id_old'];
                $box->widget_name = 'Import';
                $box->save();
            } else {
                $box->delete();
            }
            Design_Controller::delete_block($box->id);
        }
    }
    public static function import_block_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        $block_name = 'error';
        if (is_array($data['microtime'])) {
            foreach ($data['microtime'] as $key => $item) {
                $block_name = self::block_name_to_db($data['block_name'][$key], $theme_name);
                Theme::blocks_tree_import($data['content'][$key], $theme_name, $block_name, $data['content'][$key]['sort_order'], false, false);
            }
        } else {
            $block_name = self::block_name_to_db($data['block_name'], $theme_name);
            Theme::blocks_tree_import($data['content'], $theme_name, $block_name, $data['content']['sort_order'], false, false);
        }
        $siblings = Design_Boxes_Tmp::find()->where(['block_name' => $block_name, 'theme_name' => $theme_name])->all();
        if (!$siblings) {
            return '';
        }
        foreach ($siblings as $sibling) {
            if ($sibling->widget_name == 'Import') {
                $sibling->delete();
                continue;
            }
            if (!isset($data['siblings']) || !is_array($data['siblings'])) {
                continue;
            }
            foreach ($data['siblings'] as $data_sibling) {
                if ($sibling->microtime == $data_sibling['microtime']) {
                    $sibling->sort_order = $data_sibling['sort_order'];
                    $sibling->save();
                }
            }
        }
    }
    public static function elements_save($theme_name)
    {
        $data_s = [];
        self::step_save('elementsSave', $data_s, $theme_name);
    }
    public static function elements_save_undo($step)
    {
    }
    public static function elements_save_redo($step)
    {
    }
    public static function elements_cancel($theme_name)
    {
        $current = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($theme_name) . "'"));
        $query = tep_db_fetch_array(tep_db_query('
        select * 
        from ' . TABLE_THEMES_STEPS . " \r\n        where \r\n          event='elementsSave' and \r\n          theme_name='" . tep_db_input($theme_name) . "' and\r\n          date_added < '" . $current['date_added'] . "'\r\n        order by\tdate_added desc limit 1"));
        $data_s = [];
        self::step_save('elementsCancel', $data_s, $theme_name, false);
        $c = 1;
        $parent_id = $current['parent_id'];
        $chain = [];
        $chain[] = $current;
        while ($c) {
            $chain_query = tep_db_query('select * from ' . TABLE_THEMES_STEPS . " where steps_id='" . (int) $parent_id . "' and steps_id != '" . (int) $query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
            $c = tep_db_num_rows($chain_query);
            if ($c) {
                $chain_arr = tep_db_fetch_array($chain_query);
                $parent_id = $chain_arr['parent_id'];
                $chain[] = $chain_arr;
            }
        }
        $chain[] = $query;
        $new_parent = $query['steps_id'];
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '1'], 'update', "steps_id = '" . (int) $query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!in_array($chain[$i]['event'], self::$elements_event)) {
                tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
                tep_db_perform(TABLE_THEMES_STEPS, ['parent_id' => $new_parent, 'event' => $chain[$i]['event'], 'data' => $chain[$i]['data'], 'theme_name' => $chain[$i]['theme_name'], 'date_added' => $chain[$i]['date_added'], 'active' => '1', 'admin_id' => $chain[$i]['admin_id']]);
                $new_parent = tep_db_insert_id();
            }
        }
    }
    public static function elements_cancel_undo($step)
    {
    }
    public static function elements_cancel_redo($step)
    {
    }
    public static function style_save($data)
    {
        $data_s = ['old_styles' => $data['old_styles'], 'new_styles' => $data['new_styles']];
        self::step_save('styleSave', $data_s, $data['theme_name']);
    }
    public static function style_save_undo($step)
    {
        self::style_save_change($step, 'old');
    }
    public static function style_save_redo($step)
    {
        self::style_save_change($step, 'new');
    }
    public static function style_save_change($step, $detraction)
    {
        Themes_Styles_Main::delete_all(['theme_name' => $step['theme_name']]);
        foreach ($step['data'][$detraction . '_styles'] as $style) {
            $themes_styles_main = new Themes_Styles_Main();
            $themes_styles_main->theme_name = $step['theme_name'];
            $themes_styles_main->name = $style['name'];
            $themes_styles_main->value = $style['value'];
            $themes_styles_main->type = $style['type'];
            $themes_styles_main->sort_order = $style['sort_order'];
            $themes_styles_main->group_id = $style['group_id'];
            $themes_styles_main->save();
        }
        Themes_Styles_Groups::delete_all(['theme_name' => $step['theme_name']]);
        foreach ($step['data'][$detraction . '_groups'] as $style) {
            $themes_styles_group = new Themes_Styles_Groups();
            $themes_styles_group->theme_name = $step['theme_name'];
            $themes_styles_group->group_id = $style['group_id'];
            $themes_styles_group->group_name = $style['group_name'];
            $themes_styles_group->sort_order = $style['sort_order'];
            $themes_styles_group->tab = $style['tab'];
            $themes_styles_group->save();
        }
    }
    public static function settings($data)
    {
        if (!isset($data['them_settings']) || !is_array($data['them_settings']) || !isset($data['them_settings_old']) || !is_array($data['them_settings_old'])) {
            return '';
        }
        foreach ($data['them_settings'] as $key => $setting) {
            foreach ($data['them_settings_old'] as $key_old => $setting_old) {
                if ($setting['setting_group'] == $setting_old['setting_group'] && $setting['setting_name'] == $setting_old['setting_name'] && $setting['setting_value'] == $setting_old['setting_value']) {
                    unset($data['them_settings'][$key]);
                    unset($data['them_settings_old'][$key_old]);
                }
            }
        }
        $data_s = ['them_settings_old' => $data['them_settings_old'], 'them_settings' => $data['them_settings']];
        self::step_save('settings', $data_s, $data['theme_name']);
    }
    public static function settings_undo($step)
    {
        $data = $step['data'];
        self::settings_change($data['them_settings_old'], $data['them_settings'], $step['theme_name']);
    }
    public static function settings_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        self::settings_change($data['them_settings'], $data['them_settings_old'], $theme_name);
    }
    public static function settings_change($new, $old, $theme_name)
    {
        foreach ($old as $item) {
            $setting = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => $item['setting_group'], 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value']]);
            if ($setting) {
                $setting->delete();
            }
        }
        foreach ($new as $item) {
            if ($item['setting_group'] == 'extend') {
                $setting = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => $item['setting_group'], 'setting_name' => $item['setting_name'], 'setting_value' => $item['setting_value']]);
                if ($setting) {
                    continue;
                }
            } else {
                Themes_Settings::delete_all(['theme_name' => $theme_name, 'setting_group' => $item['setting_group'], 'setting_name' => $item['setting_name']]);
            }
            $setting = new Themes_Settings();
            $setting->theme_name = $theme_name;
            $setting->setting_group = $item['setting_group'];
            $setting->setting_name = $item['setting_name'];
            $setting->setting_value = $item['setting_value'];
            $setting->save();
        }
    }
    public static function css_save($data)
    {
        $data['attributes_delete'] = self::setting_visibility_to_step($data['attributes_delete'], $data['theme_name']);
        $data['attributes_changed'] = self::setting_visibility_to_step($data['attributes_changed'], $data['theme_name']);
        $data['attributes_new'] = self::setting_visibility_to_step($data['attributes_new'], $data['theme_name']);
        self::step_save('cssSave', $data, $data['theme_name']);
    }
    public static function css_save_undo($step)
    {
        $data = $step['data'];
        $data['attributes_delete'] = self::setting_visibility_to_db($data['attributes_delete'], $step['theme_name']);
        $data['attributes_changed'] = self::setting_visibility_to_db($data['attributes_changed'], $step['theme_name']);
        $data['attributes_new'] = self::setting_visibility_to_db($data['attributes_new'], $step['theme_name']);
        foreach ($data['attributes_changed'] as $item) {
            tep_db_perform(TABLE_THEMES_STYLES, ['value' => $item['value_old']], 'update', "\r\n                theme_name = '" . tep_db_input($data['theme_name']) . "' and\r\n                selector = '" . tep_db_input($item['selector']) . "' and\r\n                attribute = '" . tep_db_input($item['attribute']) . "' and\r\n                visibility = '" . tep_db_input($item['visibility']) . "' and\r\n                media = '" . tep_db_input($item['media']) . "' and\r\n                accessibility = '" . tep_db_input($item['accessibility']) . "'\r\n        ");
        }
        foreach ($data['attributes_delete'] as $item) {
            tep_db_perform(TABLE_THEMES_STYLES, ['theme_name' => $data['theme_name'], 'selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value_old'] ?? '', 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']]);
        }
        foreach ($data['attributes_new'] as $item) {
            tep_db_query('delete from ' . TABLE_THEMES_STYLES . " where\r\n                theme_name = '" . tep_db_input($data['theme_name']) . "' and\r\n                selector = '" . tep_db_input($item['selector']) . "' and\r\n                attribute = '" . tep_db_input($item['attribute']) . "' and\r\n                visibility = '" . tep_db_input($item['visibility']) . "' and\r\n                media = '" . tep_db_input($item['media']) . "' and\r\n                accessibility = '" . tep_db_input($item['accessibility']) . "'\r\n        ");
        }
        Style::create_cache($data['theme_name'], self::get_accessibility($data));
    }
    public static function css_save_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        $data['attributes_delete'] = self::setting_visibility_to_db($data['attributes_delete'], $theme_name);
        $data['attributes_changed'] = self::setting_visibility_to_db($data['attributes_changed'], $theme_name);
        $data['attributes_new'] = self::setting_visibility_to_db($data['attributes_new'], $theme_name);
        self::css_save_attributes($data['attributes_changed'], $theme_name);
        self::css_save_attributes($data['attributes_new'], $theme_name);
        if (isset($data['attributes_delete']) && is_array($data['attributes_delete'])) {
            foreach ($data['attributes_delete'] as $item) {
                Themes_Styles::delete_all(['theme_name' => $theme_name, 'selector' => $item['selector'], 'attribute' => $item['attribute'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']]);
            }
        }
        Style::create_cache($theme_name, self::get_accessibility($data));
    }
    public static function css_save_attributes($data, $theme_name)
    {
        if (isset($data) && is_array($data)) {
            foreach ($data as $item) {
                $style_set = ['theme_name' => $theme_name, 'selector' => $item['selector'], 'attribute' => $item['attribute'], 'visibility' => $item['visibility'] ?? '', 'media' => $item['media'], 'accessibility' => $item['accessibility']];
                $style = Themes_Styles::find_one($style_set);
                if (!$style) {
                    $style = new Themes_Styles();
                    $style->set_attributes($style_set);
                }
                $style->set_attributes(['value' => $item['value']]);
                $style->save();
            }
        }
    }
    public static function get_accessibility($data)
    {
        if (!isset($data) || !is_array($data)) {
            return false;
        }
        foreach (['attributes_delete', 'attributes_changed', 'attributes_new'] as $item) {
            if (!is_array($data[$item]) || !count($data[$item])) {
                continue;
            }
            $first_item = reset($data[$item]);
            return $first_item['accessibility'];
        }
    }
    public static function javascript_save($data)
    {
        $query = tep_db_fetch_array(tep_db_query('select steps_id, data, event, admin_id from ' . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($data['theme_name']) . "'"));
        if ($query['event'] == 'javascriptSave' && $query['admin_id'] == $_SESSION['login_id']) {
            $data_s = json_decode($query['data'], true);
            $data_s['javascript'] = $data['javascript'];
            $sql_data_array = ['data' => json_encode($data_s), 'date_added' => 'now()'];
            tep_db_perform(TABLE_THEMES_STEPS, $sql_data_array, 'update', "steps_id='" . (int) $query['steps_id'] . "'");
        } else {
            $data_s = ['javascript_old' => $data['javascript_old'], 'javascript' => $data['javascript']];
            self::step_save('javascriptSave', $data_s, $data['theme_name']);
        }
    }
    public static function javascript_save_undo($step)
    {
        $data = $step['data'];
        $themes_settings = Themes_Settings::find_one(['theme_name' => $step['theme_name'], 'setting_group' => 'javascript', 'setting_name' => 'javascript']);
        if (!$themes_settings) {
            $themes_settings = new Themes_Settings();
        }
        $themes_settings->setting_value = $data['javascript_old'];
        $themes_settings->save();
    }
    public static function javascript_save_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        $themes_settings = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => 'javascript', 'setting_name' => 'javascript']);
        if (!$themes_settings) {
            $themes_settings = new Themes_Settings();
        }
        $themes_settings->setting_value = $data['javascript'];
        $themes_settings->save();
    }
    public static function backup_submit($data)
    {
        $data_s = ['backup_id' => (int) $data['backup_id']];
        self::step_save('backupSubmit', $data_s, $data['theme_name']);
    }
    public static function backup_submit_undo($step)
    {
    }
    public static function backup_submit_redo($step)
    {
    }
    public static function backup_restore($data)
    {
        $data_s = ['backup_id' => $data['backup_id']];
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($data['theme_name']) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '1'], 'update', "data = '" . tep_db_input(json_encode(['backup_id' => (int) $data['backup_id']])) . "' and theme_name='" . tep_db_input($data['theme_name']) . "'");
        self::step_save('backupRestore', $data_s, $data['theme_name'], false);
    }
    public static function backup_restore_undo($step)
    {
    }
    public static function backup_restore_redo($step)
    {
    }
    public static function theme_save($theme_name)
    {
        $data_s = [];
        self::step_save('themeSave', $data_s, $theme_name);
    }
    public static function theme_save_undo($step)
    {
    }
    public static function theme_save_redo($step)
    {
    }
    public static function theme_cancel($theme_name)
    {
        $current = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_THEMES_STEPS . " where active='1' and theme_name='" . tep_db_input($theme_name) . "'"));
        $query = tep_db_fetch_array(tep_db_query('
        select * 
        from ' . TABLE_THEMES_STEPS . " \r\n        where \r\n          event='themeSave' and \r\n          theme_name='" . tep_db_input($theme_name) . "' and\r\n          date_added < '" . $current['date_added'] . "'\r\n        order by\tdate_added desc limit 1"));
        $data_s = [];
        self::step_save('themeCancel', $data_s, $theme_name, false);
        $c = 1;
        $parent_id = $current['parent_id'];
        $chain = [];
        $chain[] = $current;
        while ($c) {
            $chain_query = tep_db_query('select * from ' . TABLE_THEMES_STEPS . " where steps_id='" . (int) $parent_id . "' and steps_id != '" . (int) $query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
            $c = tep_db_num_rows($chain_query);
            if ($c) {
                $chain_arr = tep_db_fetch_array($chain_query);
                $parent_id = $chain_arr['parent_id'];
                $chain[] = $chain_arr;
            }
        }
        $chain[] = $query;
        $new_parent = $query['steps_id'];
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
        tep_db_perform(TABLE_THEMES_STEPS, ['active' => '1'], 'update', "steps_id = '" . (int) $query['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!in_array($chain[$i]['event'], self::$styles_event)) {
                tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
                tep_db_perform(TABLE_THEMES_STEPS, ['parent_id' => $new_parent, 'event' => $chain[$i]['event'], 'data' => $chain[$i]['data'], 'theme_name' => $chain[$i]['theme_name'], 'date_added' => $chain[$i]['date_added'], 'active' => '1', 'admin_id' => $chain[$i]['admin_id']]);
                $new_parent = tep_db_insert_id();
            }
        }
    }
    public static function theme_cancel_undo($step)
    {
    }
    public static function theme_cancel_redo($step)
    {
    }
    public static function add_page($data)
    {
        $data_s = ['page_type' => $data['setting_name'], 'page_name' => $data['setting_value'], 'content' => $data['content']];
        self::step_save('addPage', $data_s, $data['theme_name']);
    }
    public static function add_page_undo($step)
    {
        $data = $step['data'];
        Themes_Settings::delete_all(['theme_name' => $step['theme_name'], 'setting_group' => 'added_page', 'setting_name' => $data['page_type'], 'setting_value' => $data['page_name']]);
        self::delete_page($data['page_name'], $step['theme_name']);
    }
    public static function add_page_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        $added_page = Themes_Settings::find_one(['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_value' => $data['page_name']]);
        if ($added_page) {
            return '';
        }
        $added_page = new Themes_Settings();
        $added_page->theme_name = $theme_name;
        $added_page->setting_group = 'added_page';
        $added_page->setting_name = $data['page_type'];
        $added_page->setting_value = $data['page_name'];
        $added_page->save();
        foreach ($data['content'] as $box) {
            Theme::blocks_tree_import($box, $theme_name, Designer_Helper::page_name($data['page_name']));
        }
    }
    public static function add_page_settings($data)
    {
        $data_s = ['page_name' => $data['page_name'], 'settings_old' => $data['settings_old'], 'settings' => $data['settings']];
        self::step_save('addPageSettings', $data_s, $data['theme_name']);
    }
    public static function add_page_settings_undo($step)
    {
        $data = $step['data'];
        Themes_Settings::delete_all(['theme_name' => $step['theme_name'], 'setting_group' => 'added_page_settings', 'setting_name' => $data['page_name']]);
        if (!isset($data['settings_old']) || !is_array($data['settings_old'])) {
            return '';
        }
        foreach ($data['settings_old'] as $item) {
            $themes_settings = new Themes_Settings();
            $themes_settings->theme_name = $step['theme_name'];
            $themes_settings->setting_group = $item['setting_group'];
            $themes_settings->setting_name = $item['setting_name'];
            $themes_settings->setting_value = $item['setting_value'];
            $themes_settings->save();
        }
    }
    public static function add_page_settings_redo($step)
    {
        $data = $step['data'];
        $theme_name = $step['theme_name'];
        Themes_Settings::delete_all(['theme_name' => $theme_name, 'setting_group' => 'added_page_settings', 'setting_name' => $data['page_name']]);
        if (!isset($data['settings']) || !is_array($data['settings_old'])) {
            return '';
        }
        foreach ($data['settings'] as $item) {
            $themes_settings = new Themes_Settings();
            $themes_settings->theme_name = $theme_name;
            $themes_settings->setting_group = $item['setting_group'];
            $themes_settings->setting_name = $item['setting_name'];
            $themes_settings->setting_value = $item['setting_value'];
            $themes_settings->save();
        }
    }
    public static function log($theme_name, $output = [])
    {
        $active = tep_db_fetch_array(tep_db_query('select steps_id from ' . TABLE_THEMES_STEPS . " where theme_name='" . tep_db_input($theme_name) . "' and active='1'"));
        $log = [];
        $filter = '';
        if (tep_not_null($output['from'])) {
            $from = tep_db_prepare_input($output['from']);
            $filter .= " and to_days(date_added) >= to_days('" . \common\helpers\Date::prepare_input_date($from) . "')";
        }
        if (tep_not_null($output['to'])) {
            $to = tep_db_prepare_input($output['to']);
            $filter .= " and to_days(date_added) <= to_days('" . \common\helpers\Date::prepare_input_date($to) . "')";
        }
        $limit = '';
        if (!$filter) {
            $limit = ' limit 500';
        }
        $query = tep_db_query('select steps_id, parent_id, event, date_added, admin_id, mode, data from ' . TABLE_THEMES_STEPS . " where theme_name='" . tep_db_input($theme_name) . "'" . $filter . ' order by date_added desc ' . $limit);
        $current = $active['steps_id'];
        $count = 0;
        while ($item = tep_db_fetch_array($query)) {
            if (!$count && $output['to']) {
                $current = $item['steps_id'];
                $count++;
            }
            $mode = '';
            if ($item['mode']) {
                switch ($item['mode']) {
                    case 'advanced':
                        $mode = EDIT_MODE . ': <b>' . ADVANCED_MODE . '</b>';
                        break;
                    case 'expert':
                        $mode = EDIT_MODE . ': <b>' . EXPERT_MODE . '</b>';
                        break;
                    default:
                        $mode = EDIT_MODE . ': <b>' . BASIC_MODE . '</b>';
                }
            }
            $log[$item['steps_id']] = ['steps_id' => $item['steps_id'], 'parent_id' => $item['parent_id'], 'event' => $item['event'], 'date_added' => $item['date_added'], 'admin_id' => $item['admin_id'], 'mode' => $mode, 'warning' => str_contains($item['data'], 'extensionWidgets')];
        }
        $trunk = [];
        $tree = [];
        while (isset($log[$current]) && is_array($log[$current])) {
            $trunk[] = $current;
            $tree[$current] = $log[$current];
            $tree[$current]['branches'] = 1;
            $tree[$current]['branch_id'] = 0;
            $current = $log[$current]['parent_id'];
        }
        $branches = [];
        foreach ($log as $id => $item) {
            if (!in_array($id, $trunk)) {
                $branches[$item['steps_id']] = $item;
            }
        }
        $count_error = 0;
        while (count($branches) > 0) {
            foreach ($branches as $item) {
                if (isset($tree[$item['parent_id']]) && is_array($tree[$item['parent_id']])) {
                    $tree[$item['parent_id']]['branches']++;
                    $tree[$item['steps_id']] = $item;
                    if ($tree[$item['parent_id']]['branches'] == 1) {
                        $tree[$item['steps_id']]['branch_id'] = $tree[$item['parent_id']]['branch_id'];
                    } else {
                        $tree[$item['steps_id']]['branch_id'] = $item['parent_id'];
                    }
                    $tree[$item['steps_id']]['branches'] = 0;
                }
                unset($branches[$item['steps_id']]);
            }
            $count_error++;
            if ($count_error > 1000000) {
                return 'Error, too many steps. 2';
            }
        }
        foreach ($tree as $key => $item) {
            $tree[$key]['text'] = self::log_names($item['event']) . ($item['warning'] ? '<span class="warning">(' . ICON_WARNING . ')</span>' : '');
            $tree[$key]['date_added'] = \common\helpers\Date::date_long($tree[$key]['date_added'], '%d %b %Y / %H:%M:%S');
        }
        return $tree;
    }
    public static function log_details($id)
    {
        $details = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_THEMES_STEPS . " where steps_id = '" . (int) $id . "'"));
        $details['name'] = self::log_names($details['event']);
        $details['date_added'] = \common\helpers\Date::date_long($details['date_added'], '%d %b %Y / %H:%M:%S');
        $admin = tep_db_fetch_array(tep_db_query('
            select admin_id, admin_firstname, admin_lastname, admin_email_address 
            from ' . TABLE_ADMIN . " \r\n            where admin_id = '" . (int) $details['admin_id'] . "'"));
        $data = json_decode($details['data'], true);
        $details['admin'] = $admin['admin_firstname'] . ' ' . $admin['admin_lastname'];
        if (isset($data['designer_mode'])) {
            switch ($data['designer_mode']) {
                case 'advanced':
                    $details['designer_mode'] = ADVANCED_MODE;
                    break;
                case 'expert':
                    $details['designer_mode'] = EXPERT_MODE;
                    break;
                default:
                    $details['designer_mode'] = BASIC_MODE;
            }
        }
        if ($details['event'] == 'boxAdd') {
            $details['widget_name'] = $data['widget_name'];
            $details['page_name'] = $data['page_name'];
        }
        if ($details['event'] == 'boxSave') {
            $details['page_name'] = $data['page_name'];
            $widget = tep_db_fetch_array(tep_db_query('
                select widget_name 
                from ' . TABLE_DESIGN_BOXES_TMP . " \r\n                where id = '" . (int) $data['box_id'] . "'"));
            $details['widget_name'] = $widget['widget_name'];
            $details['widgetSettings'] = [];
            foreach ($data['box_settings'] as $key => $setting) {
                $details['widgetSettings'][$key]['new'] = $setting;
                foreach ($data['box_settings_old'] as $setting_old) {
                    if ($setting['setting_name'] == $setting_old['setting_name'] && $setting['visibility'] == $setting_old['visibility']) {
                        $details['widgetSettings'][$key]['old'] = $setting_old;
                    }
                }
            }
        }
        if ($details['event'] == 'cssSave') {
            $media_sizes_arr = Themes_Settings::find()->where(['theme_name' => $details['theme_name'], 'setting_name' => 'media_query'])->as_array()->all();
            $data['attributes_delete'] = self::setting_visibility_to_db($data['attributes_delete'], $details['theme_name']);
            $data['attributes_changed'] = self::setting_visibility_to_db($data['attributes_changed'], $details['theme_name']);
            $data['attributes_new'] = self::setting_visibility_to_db($data['attributes_new'], $details['theme_name']);
            $details['css']['delete'] = Style::get_create_css($data['attributes_delete'], $media_sizes_arr);
            $details['css']['new'] = Style::get_create_css($data['attributes_new'], $media_sizes_arr);
            $details['css']['changed'] = Style::get_create_css($data['attributes_changed'], $media_sizes_arr);
        }
        if (isset($data['extensionWidgets'])) {
            $details['extensionWidgets'] = $data['extensionWidgets'];
        }
        return $details;
    }
    public static function log_names($event)
    {
        $text = '';
        switch ($event) {
            case 'boxAdd':
                $text = LOG_ADDED_NEW_BLOCK;
                break;
            case 'blocksMove':
                $text = LOG_CHANGED_BLOCK_POSITION;
                break;
            case 'boxSave':
                $text = LOG_CHANGED_BLOCK_SETTINGS;
                break;
            case 'boxDelete':
                $text = LOG_REMOVED_BLOCK;
                break;
            case 'importBlock':
                $text = LOG_IMPORTED_BLOCK;
                break;
            case 'elementsSave':
                $text = LOG_SAVED_EDIT_ELEMENTS_PAGE;
                break;
            case 'elementsCancel':
                $text = LOG_CANCELED_EDIT_ELEMENTS_PAGE;
                break;
            case 'styleSave':
                $text = CHANGED_MAIN_STYLES;
                break;
            case 'settings':
                $text = LOG_CHANGED_THEME_SETTINGS;
                break;
            //case 'extendRemove': $text = LOG_REMOVED_EXTEND_FIELD; break;
            //case 'extendAdd': $text = LOG_ADDED_EXTEND_FIELD; break;
            case 'cssSave':
                $text = LOG_SAVED_CSS;
                break;
            case 'javascriptSave':
                $text = LOG_SAVED_JAVASCRIPT;
                break;
            case 'backupSubmit':
                $text = LOG_DID_BACKU;
                break;
            case 'backupRestore':
                $text = LOG_RESTORED_BACKUP;
                break;
            case 'themeSave':
                $text = LOG_SAVED_CUSTOMIZE_THEME_STYLES;
                break;
            case 'themeCancel':
                $text = LOG_CANCELED_CUSTOMIZE_THEME_STYLES;
                break;
            case 'addPage':
                $text = LOG_ADDED_NEW_PAGE;
                break;
            case 'removePageTemplate':
                $text = REMOVED_PAGE_TEMPLATE;
                break;
            case 'addPageSettings':
                $text = LOG_CHANGED_ADDED_PAGE;
                break;
            case 'stylesChange':
                $text = CHANGED_STYLES;
                break;
            case 'copyPage':
                $text = COPIED_PAGE;
                break;
            case 'importTheme':
                $text = IMPORTED_THEME;
                break;
            case 'applyMigration':
                $text = APPLIED_MIGRATION;
                break;
            case 'setGroup':
                $text = SET_WIDGET_GROUP;
                break;
            case 'setStyles':
                $text = SET_MAIN_THEME_STYLES;
                break;
            case 'setCss':
                $text = 'Set css elements';
                break;
        }
        return $text;
    }
    public static function restore($id)
    {
        $chain = [];
        $event = 1;
        $theme_name = 0;
        while (!$theme_name) {
            while ($event && $event != 'backupSubmit' && $event != 'backupRestore') {
                $item = tep_db_fetch_array(tep_db_query('select steps_id, parent_id, event, data from ' . TABLE_THEMES_STEPS . " where steps_id = '" . (int) $id . "'"));
                $event = $item['event'];
                $chain[] = $item['steps_id'];
                $id = $item['parent_id'];
            }
            if (!$event) {
                return LOG_NO_BACKUPS;
            }
            $data = json_decode($item['data'], true);
            $query = tep_db_fetch_array(tep_db_query('select theme_name from ' . TABLE_DESIGN_BACKUPS . " where backup_id = '" . (int) $data['backup_id'] . "' limit 1"));
            $theme_name = $query['theme_name'];
            if ($theme_name && $data['backup_id']) {
                \backend\design\Backups::backup_restore($data['backup_id'], $theme_name);
                tep_db_perform(TABLE_THEMES_STEPS, ['active' => '0'], 'update', "active = '1' and theme_name='" . tep_db_input($theme_name) . "'");
                tep_db_perform(TABLE_THEMES_STEPS, ['active' => '1'], 'update', "steps_id = '" . $item['steps_id'] . "' and theme_name='" . tep_db_input($theme_name) . "'");
            }
            $event = 1;
        }
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            self::redo($theme_name, $chain[$i]);
        }
        return '';
    }
    public static function styles_change($data)
    {
        if ($data['style'] == 'border_color') {
            $style = ['border_top_color', 'border_left_color', 'border_right_color', 'border_bottom_color'];
        } else {
            $style = $data['style'];
        }
        $themes_styles = Themes_Styles::find()->where(['theme_name' => $data['theme_name'], 'value' => $data['from']])->and_where(['in', 'attribute', $style])->as_array()->all();
        $themes_styles = self::setting_visibility_to_step($themes_styles, $data['theme_name']);
        $design_boxes_settings = Design_Boxes_Settings_Tmp::find()->select(['microtime', 'setting_name', 'visibility'])->where(['theme_name' => $data['theme_name'], 'setting_value' => $data['from']])->and_where(['in', 'setting_name', $style])->as_array()->all();
        $data_s = ['themesStyles' => $themes_styles, 'designBoxesSettings' => $design_boxes_settings, 'from' => $data['from'], 'to' => $data['to'], 'style' => $data['style']];
        self::step_save('stylesChange', $data_s, $data['theme_name']);
    }
    public static function styles_change_undo($step)
    {
        self::styles_change_event($step, false);
    }
    public static function styles_change_redo($step)
    {
        self::styles_change_event($step, true);
    }
    public static function styles_change_event($step, $redo)
    {
        $data = $step['data'];
        $data['themesStyles'] = self::setting_visibility_to_db($data['themesStyles'], $step['theme_name']);
        foreach ($data['themesStyles'] as $themes_style) {
            $themes_styles = Themes_Styles::find_one(['theme_name' => $step['theme_name'], 'selector' => $themes_style['selector'], 'attribute' => $themes_style['attribute'], 'visibility' => $themes_style['visibility'], 'media' => $themes_style['media'], 'accessibility' => $themes_style['accessibility'], 'value' => $data[$redo ? 'from' : 'to']]);
            if (!$themes_styles) {
                continue;
            }
            $themes_styles->value = $data[$redo ? 'to' : 'from'];
            $themes_styles->save();
        }
        foreach ($data['designBoxesSettings'] as $design_boxes_setting) {
            $design_boxes_settings = Design_Boxes_Settings_Tmp::find_one(['theme_name' => $step['theme_name'], 'microtime' => $design_boxes_setting['microtime'], 'setting_name' => $design_boxes_setting['setting_name'], 'visibility' => $design_boxes_setting['visibility'], 'setting_value' => $data[$redo ? 'from' : 'to']]);
            if (!$design_boxes_settings) {
                continue;
            }
            $design_boxes_settings->setting_value = $data[$redo ? 'to' : 'from'];
            $design_boxes_settings->save();
        }
    }
    public static function remove_class($data)
    {
        $styles = [];
        $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($data['theme_name']) . "' and selector = '" . tep_db_input($data['class']) . "'");
        while ($item = tep_db_fetch_array($query)) {
            $styles[] = $item;
        }
        $data_s = ['styles' => $styles, 'class' => $data['class']];
        self::step_save('removeClass', $data_s, $data['theme_name']);
    }
    public static function remove_class_undo($step)
    {
        $data = $step['data'];
        foreach ($data['styles'] as $item) {
            tep_db_perform(TABLE_THEMES_STYLES, $item);
        }
    }
    public static function remove_class_redo($step)
    {
        $data = $step['data'];
        tep_db_query('delete from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($step['theme_name']) . "' and selector = '" . tep_db_input($data['class']) . "'");
    }
    public static function copy_page($data)
    {
        self::step_save('copyPage', $data, $data['theme_name']);
    }
    public static function copy_page_undo($step)
    {
        $data = $step['data'];
        self::delete_page($data['page_to'], $step['theme_name']);
        foreach ($data['content_old'] as $box) {
            Theme::blocks_tree_import($box, $step['theme_name'], Designer_Helper::page_name($data['page_to']));
        }
    }
    public static function copy_page_redo($step)
    {
        $data = $step['data'];
        self::delete_page($data['page_to'], $step['theme_name']);
        foreach ($data['content'] as $box) {
            Theme::blocks_tree_import($box, $step['theme_name'], Designer_Helper::page_name($data['page_to']));
        }
    }
    public static function import_theme($data)
    {
        self::step_save('importTheme', $data, $data['theme_name']);
    }
    public static function import_theme_undo($step)
    {
    }
    public static function import_theme_redo($step)
    {
    }
    public static function set_group($data)
    {
        self::step_save('setGroup', $data, $data['theme_name']);
    }
    public static function set_group_undo($step)
    {
        $boxes = $step['data']['old'];
        foreach ($boxes as $page_name => $block) {
            self::delete_page($page_name, $step['theme_name']);
        }
        foreach ($boxes as $page_name => $blocks) {
            foreach ($blocks as $block) {
                Theme::blocks_tree_import($block, $step['theme_name'], $page_name);
            }
        }
    }
    public static function set_group_redo($step)
    {
        $boxes = $step['data']['new'];
        foreach ($boxes as $page_name => $block) {
            self::delete_page($page_name, $step['theme_name']);
        }
        foreach ($boxes as $page_name => $blocks) {
            foreach ($blocks as $block) {
                Theme::blocks_tree_import($block, $step['theme_name'], $page_name);
            }
        }
    }
    public static function set_styles($data)
    {
        self::step_save('setStyles', $data, $data['theme_name']);
    }
    public static function set_styles_undo($step)
    {
        self::set_styles_event($step, 'old');
    }
    public static function set_styles_redo($step)
    {
        self::set_styles_event($step, 'new');
    }
    public static function set_styles_event($step, $event)
    {
        $styles = $step['data'][$event];
        $type = $step['data']['type'];
        $theme_name = $step['data']['theme_name'];
        Themes_Styles_Main::delete_all(['theme_name' => $theme_name, 'type' => $type]);
        foreach ($styles as $style) {
            $themes_styles = new Themes_Styles_Main();
            $themes_styles->theme_name = $theme_name;
            $themes_styles->name = $style['name'];
            $themes_styles->value = $style['value'];
            $themes_styles->type = $style['type'];
            $themes_styles->sort_order = $style['sort_order'];
            $themes_styles->group_id = $style['group_id'];
            $themes_styles->save();
        }
        $groups = $step['data'][$event . '_groups'];
        Themes_Styles_Groups::delete_all(['theme_name' => $theme_name]);
        foreach ($groups as $group) {
            $themes_styles_group = new Themes_Styles_Groups();
            $themes_styles_group->theme_name = $theme_name;
            $themes_styles_group->group_id = $group['group_id'];
            $themes_styles_group->group_name = $group['group_name'];
            $themes_styles_group->sort_order = $group['sort_order'];
            $themes_styles_group->tab = $group['tab'];
            $themes_styles_group->save();
        }
    }
    public static function set_css($data)
    {
        self::step_save('setCss', $data, $data['theme_name']);
    }
    public static function set_css_undo($step)
    {
        Style::set_css_elements($step['data']['theme_name'], $step['data']['old']);
    }
    public static function set_css_redo($step)
    {
        Style::set_css_elements($step['data']['theme_name'], $step['data']['new']);
    }
}