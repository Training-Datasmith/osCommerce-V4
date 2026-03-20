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
namespace backend\components;

use yii\base\Widget;
class Navigation extends Widget
{
    public $box_files_list = [];
    public $selected_menu = [];
    public $no_html = false;
    /**
     * @return mixed[]
     */
    public function build_tree($parent_id, $query_response, $rule = []): array
    {
        $tree = [];
        if ($parent_id == 0) {
            if (\common\helpers\Acl::rule(['TEXT_DASHBOARD'])) {
                $tree[] = ['box_type' => 0, 'path' => 'index', 'title' => TEXT_DASHBOARD, 'filename' => 'dashboard', 'acl' => ''];
            }
            if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
                $tree[] = ['box_type' => 1, 'path' => 'departments', 'title' => BOX_HEADING_DEPARTMENTS, 'acl' => 'BOX_HEADING_DEPARTMENTS', 'filename' => 'cubes', 'child' => [['box_type' => 0, 'path' => 'departments', 'title' => BOX_HEADING_DEPARTMENTS, 'acl' => 'BOX_HEADING_DEPARTMENTS', 'filename' => ''], ['box_type' => 0, 'path' => 'departments-adminmembers', 'title' => BOX_DEPARTMENTS_MEMBERS, 'acl' => 'BOX_DEPARTMENTS_MEMBERS', 'filename' => ''], ['box_type' => 0, 'path' => 'departments-adminfiles', 'title' => BOX_DEPARTMENTS_BOXES, 'acl' => 'BOX_DEPARTMENTS_BOXES', 'filename' => '']]];
            }
        }
        foreach ($query_response as $response) {
            if ($response['parent_id'] == $parent_id) {
                $rule_tmp = $rule;
                $rule_tmp[] = $response['title'];
                if (\common\helpers\Acl::rule($rule_tmp)) {
                    // enabled
                    if ($response['box_type'] == 1) {
                        $response['child'] = $this->build_tree($response['box_id'], $query_response, $rule_tmp);
                        if ($response['title'] == 'BOX_HEADING_CONFIGURATION' && defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
                            $response['child'][] = ['box_type' => 0, 'path' => 'configuration/index?groupid=BOX_CONFIGURATION_PANEL', 'title' => 'Control panel', 'filename' => ''];
                        }
                    }
                    if (defined($response['title'])) {
                        eval('$currentName =  ' . $response['title'] . ';');
                    } else {
                        $current_name = $response['title'];
                    }
                    $response['acl'] = $response['title'];
                    $response['title'] = $current_name;
                    $response['dis_module'] = false;
                    $response['disabled'] = false;
                    if (!empty($response['acl_check'])) {
                        [$module_name, $action_name] = explode(',', (string) $response['acl_check']);
                        if (false === \common\helpers\Acl::check_extension_allowed($module_name, $action_name)) {
                            $response['disabled'] = true;
                            $response['dis_module'] = true;
                        } elseif (false === \common\helpers\Acl::check_extension_allowed($module_name, $action_name)) {
                            $response['dis_module'] = true;
                        }
                    }
                    if (!empty($response['config_check'])) {
                        [$config_key, $config_value] = explode(',', (string) $response['config_check']);
                        if (!defined($config_key) || constant($config_key) != $config_value) {
                            $response['dis_module'] = true;
                        }
                    }
                    // hide if no child
                    if ($response['box_type'] == 1) {
                        $response['dis_module'] = true;
                        if (is_array($response['child'] ?? null)) {
                            foreach ($response['child'] as $child) {
                                if ($child['dis_module'] === false) {
                                    $response['dis_module'] = false;
                                    break;
                                }
                            }
                        }
                    }
                    if ($response['dis_module'] === false) {
                        // hide disabled menu items
                        $tree[] = $response;
                    }
                }
            }
        }
        return $tree;
    }
    private function parse_menu(): bool
    {
        $total_records = \common\models\Admin_Boxes::find()->count();
        if ($total_records > 0) {
            return false;
        }
        $path = \Yii::get_alias('@webroot');
        $filename = $path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'default_menu.xml';
        $xmlfile = file_get_contents($filename);
        $ob = simplexml_load_string($xmlfile);
        $ex_items = \common\helpers\Menu_Helper::get_extensions_tree_items();
        $ob_prepared = \common\helpers\Menu_Helper::prepare_admin_tree($ob, $ex_items);
        tep_db_query('TRUNCATE TABLE admin_boxes;');
        \common\helpers\Menu_Helper::import_admin_tree($ob_prepared);
        return true;
    }
    public function run()
    {
        $this->parse_menu();
        if (isset(\Yii::$app->controller->acl)) {
            $this->selected_menu = \Yii::$app->controller->acl;
        } else {
            $this->selected_menu = ['index'];
        }
        $query_response = \common\models\Admin_Boxes::find()->order_by(['sort_order' => SORT_ASC])->as_array()->all();
        $current_menu = $this->build_tree(0, $query_response, []);
        if ($this->no_html) {
            return json_encode(['menu' => $current_menu, 'selectedMenu' => $this->selected_menu]);
        }
        $auto_hide_menu = false;
        if (count($current_menu) < 2) {
            $auto_hide_menu = true;
        }
        return $this->render('Navigation', ['context' => $this, 'currentMenu' => $current_menu, 'autoHideMenu' => $auto_hide_menu]);
    }
}