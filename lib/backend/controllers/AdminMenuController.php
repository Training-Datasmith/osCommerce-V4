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

use Yii;
/**
 * default controller to handle user requests.
 */
class Admin_Menu_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_MENU'];
    private function build_tree($parent_id, $query_response)
    {
        $tree = [];
        foreach ($query_response as $response) {
            if ($response['parent_id'] == $parent_id) {
                if ($response['box_type'] == 1) {
                    $response['child'] = $this->build_tree($response['box_id'], $query_response);
                }
                if (defined($response['title'])) {
                    eval('$currentName =  ' . $response['title'] . ';');
                } else {
                    $current_name = $response['title'];
                }
                $response['title'] = $current_name;
                $tree[] = $response;
            }
        }
        return $tree;
    }
    public function action_index()
    {
        //$this->selectedMenu = array('administrator', 'admin-menu');
        $this->view->heading_title = BOX_ADMINISTRATOR_MENU;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('admin-menu/'), 'title' => BOX_ADMINISTRATOR_MENU];
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="return save();">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('admin-menu/reset') . '" onclick="return confirm(\'' . TEXT_RESET_TO_DEFAULT . '\')" class="btn btn-primary"><i class="icon-refresh"></i>' . TEXT_RESET . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('admin-menu/export-menu') . '" class="btn btn-primary backup"><i class="icon-file-text"></i>' . TEXT_EXPORT . '</a>';
        $this->top_buttons[] = '<a href="javascript:void(0)" class="btn-import btn btn-primary backup"><i class="icon-file-text"></i>' . TEXT_IMPORT . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['admin-menu/add']) . '" class="btn btn-primary create_item_popup">' . IMAGE_ADD . '</a>';
        $current_menu = [];
        $query_response = \common\models\Admin_Boxes::find()->order_by(['sort_order' => SORT_ASC])->as_array()->all();
        $current_menu = $this->build_tree(0, $query_response);
        return $this->render('index', ['currentMenu' => $current_menu]);
    }
    private function update_tree($data, $parent = 0)
    {
        $sort_order = 0;
        foreach ($data as $item) {
            $object = \common\models\Admin_Boxes::find_one($item->id);
            if (is_object($object)) {
                $object->parent_id = $parent;
                $object->sort_order = $sort_order;
                $object->save();
                //--- update acl
                $acl = \common\models\Access_Control_List::find_one(['access_control_list_key' => $object->title]);
                if (is_object($acl)) {
                    if ($parent == 0) {
                        $acl->parent_id = $parent;
                    } else {
                        $parent_object = \common\models\Admin_Boxes::find_one($parent);
                        if (is_object($parent_object)) {
                            $parent_acl = \common\models\Access_Control_List::find_one(['access_control_list_key' => $parent_object->title]);
                            if (is_object($parent_acl)) {
                                $acl->parent_id = $parent_acl->access_control_list_id;
                            }
                        }
                    }
                    $acl->sort_order = $sort_order;
                    $acl->save();
                }
                //--- update acl
                if (isset($item->children) && is_array($item->children) && count($item->children) > 0) {
                    $this->update_tree($item->children, $item->id);
                }
            }
            $sort_order++;
        }
    }
    public function action_save()
    {
        $post_data = json_decode(\Yii::$app->request->post('post_data'));
        $this->update_tree($post_data);
        echo json_encode(['status' => 'ok']);
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $id = \Yii::$app->request->get('id');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $languages = \common\helpers\Language::get_languages();
        $lang = [];
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            $lang[] = $languages[$i];
        }
        $query_response = \common\models\Admin_Boxes::find_one($id);
        $query_response2 = \common\models\Admin_Boxes::find()->where(['box_id' => $id])->as_array()->all();
        $query_response1 = \common\models\Translation::find()->where(['translation_key' => $query_response->title, 'translation_entity' => 'admin/main'])->as_array()->all();
        return $this->render('edit.tpl', ['languages' => $lang, 'id' => $id, 'languages_id' => $languages_id, 'icons' => $query_response2, 'currentMenu' => $query_response1]);
    }
    public function action_save_popup()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $post_data = json_decode(\Yii::$app->request->post('post_data'), true);
        $languages = \common\helpers\Language::get_languages();
        $post_data = \backend\design\Style::params_from_one_input(\Yii::$app->request->post('post_data'));
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            if (isset($post_data['menu'][$languages[$i]['id']])) {
                foreach ($post_data['menu'][$languages[$i]['id']] as $key => $value) {
                    \common\helpers\Translation::set_translation_value($key, 'admin/main', $languages[$i]['id'], $value);
                    if ($languages[$i]['id'] == $languages_id) {
                        $lang_post = $value;
                    }
                }
            }
        }
        if ($post_data['icon']) {
            $query_response = \common\models\Admin_Boxes::find_one($post_data['id']);
            if (is_object($query_response)) {
                $query_response->filename = $post_data['icon'];
                $query_response->save();
            }
        }
        echo json_encode(['status' => 'ok', 'val' => $lang_post ?? null, 'message' => TEXT_MESSEAGE_SUCCESS]);
    }
    public function action_add()
    {
        $this->layout = false;
        return $this->render('add.tpl');
    }
    public function action_add_submit()
    {
        $object = new \common\models\Admin_Boxes();
        $object->parent_id = 0;
        $object->sort_order = 0;
        $object->box_type = (int) \Yii::$app->request->post('box_type');
        $object->acl_check = '';
        $object->config_check = '';
        $object->path = \Yii::$app->request->post('path');
        $object->title = \Yii::$app->request->post('title');
        $object->filename = '';
        $object->save();
        return $this->redirect(Yii::$app->url_manager->create_url('admin-menu/'));
    }
    public function action_delete()
    {
        $this->layout = false;
        $id = (int) \Yii::$app->request->get('id');
        return $this->render('delete.tpl', ['id' => $id]);
    }
    private function delete_xml_tree($id)
    {
        $object = \common\models\Admin_Boxes::find_one($id);
        if (is_object($object)) {
            $childs = \common\models\Admin_Boxes::find_all(['parent_id' => $id]);
            foreach ($childs as $child) {
                $this->delete_xml_tree($child->box_id);
            }
            $object->delete();
        }
    }
    public function action_delete_submit()
    {
        $id = (int) \Yii::$app->request->post('id');
        $this->delete_xml_tree($id);
        return $this->redirect(Yii::$app->url_manager->create_url('admin-menu/'));
    }
    public function action_import_menu()
    {
        if (isset($_FILES['file']['tmp_name'])) {
            $xmlfile = file_get_contents($_FILES['file']['tmp_name']);
            $ob = simplexml_load_string($xmlfile);
            if (isset($ob)) {
                tep_db_query('TRUNCATE TABLE admin_boxes;');
                \common\helpers\Menu_Helper::import_admin_tree($ob);
            }
            unlink($_FILES['file']['tmp_name']);
        }
    }
    private function build_xml_tree($parent_id, $query_response)
    {
        $tree = [];
        foreach ($query_response as $response) {
            if ($response['parent_id'] == $parent_id) {
                if ($response['box_type'] == 1) {
                    $response['child'] = $this->build_xml_tree($response['box_id'], $query_response);
                }
                unset($response['box_id']);
                unset($response['parent_id']);
                $tree[] = $response;
            }
        }
        return $tree;
    }
    public function action_export_menu()
    {
        $this->layout = false;
        $xml = new \yii\web\Xml_Response_Formatter();
        $xml->root_tag = 'Menu';
        Yii::$app->response->format = 'custom_xml';
        Yii::$app->response->formatters['custom_xml'] = $xml;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'text/xml; charset=utf-8');
        $headers->add('Content-Disposition', 'attachment; filename="admin-menu.xml"');
        $headers->add('Pragma', 'no-cache');
        $query_response = \common\models\Admin_Boxes::find()->order_by(['sort_order' => SORT_ASC])->as_array()->all();
        $current_menu = $this->build_xml_tree(0, $query_response, []);
        return $current_menu;
    }
    public function action_reset()
    {
        \common\helpers\Menu_Helper::reset_admin_menu();
        return $this->redirect(Yii::$app->url_manager->create_url('admin-menu/'));
    }
}