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

use common\models\Image_Types;
use Yii;
class Image_Settings_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_IMAGE_SETTINGS'];
    public $banner_extension;
    public $dir_ok = false;
    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);
        \common\helpers\Translation::init('admin/main');
        \common\helpers\Translation::init('admin/banner_manager');
    }
    public function action_index()
    {
        $this->selected_menu = ['marketing', 'banner_manager'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('image-settings'), 'title' => BOX_IMAGE_SETTINGS];
        $this->view->heading_title = BOX_IMAGE_SETTINGS;
        return $this->render('index.tpl', []);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $length = Yii::$app->request->get('length', 25);
        $search = Yii::$app->request->get('search');
        $start = Yii::$app->request->get('start', 0);
        $order = Yii::$app->request->get('order', 0);
        if ($length == -1) {
            $length = 10000;
        }
        if (!$search['value']) {
            $search['value'] = '';
        }
        $types = Image_Types::find()->select(['image_types_name', 'image_types_x', 'image_types_y', 'image_types_id'])->where(['or', ['like', 'image_types_name', $search['value']], ['like', 'image_types_x', $search['value']], ['like', 'image_types_y', $search['value']]])->and_where(['parent_id' => 0])->limit($length)->offset($start)->order_by(['image_types_id' => SORT_ASC])->all();
        $response_list = [];
        foreach ($types as $type) {
            $response_list[] = ['<div class="type" data-type-id="' . $type->image_types_id . '">' . $type->image_types_name . '</div>', $type->image_types_x, $type->image_types_y];
        }
        $count_types = Image_Types::find()->select('image_types_name')->count();
        $response = ['draw' => $draw, 'recordsTotal' => $count_types, 'recordsFiltered' => $count_types, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_edit()
    {
        $type_id = Yii::$app->request->get('image_types_id', '');
        $type = Image_Types::find()->where(['image_types_id' => $type_id])->as_array()->one();
        $this->selected_menu = ['marketing', 'banner_manager'];
        $this->top_buttons[] = '<span class="btn btn-confirm save-type">' . IMAGE_SAVE . '</span>';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('image-settings'), 'title' => 'Image type: ' . $type['image_types_name']];
        $this->view->heading_title = 'Image type: ' . $type['image_types_name'];
        $type_sizes = [];
        if ($type_id) {
            $type_sizes = Image_Types::find()->where(['or', ['image_types_id' => $type_id], ['parent_id' => $type_id]])->as_array()->all();
        }
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
        }
        return $this->render('edit.tpl', ['typeId' => $type_id, 'image_types_name' => $type['image_types_name'], 'typeSizes' => $type_sizes]);
    }
    public function action_save()
    {
        $post = Yii::$app->request->post();
        $ids = [];
        if ($post['parent_id']) {
            $image_sizes = Image_Types::find()->select('image_types_id')->where(['parent_id' => $post['parent_id']])->as_array()->all();
            foreach ($image_sizes as $item) {
                $ids[$item['image_types_id']] = $item['image_types_id'];
            }
        }
        $count = 0;
        foreach ($post['image_types_id'] as $id) {
            $image_types = false;
            if ($id != -1) {
                $image_types = Image_Types::find_one($id);
            }
            if (!$image_types) {
                $image_types = new Image_Types();
            }
            $image_types->attributes = ['image_types_id' => $id, 'image_types_name' => $post['image_types_name'], 'image_types_x' => (int) $post['image_types_x'][$count], 'image_types_y' => (int) $post['image_types_y'][$count], 'width_from' => (int) $post['width_from'][$count], 'width_to' => (int) $post['width_to'][$count], 'parent_id' => (int) ($id != $post['parent_id'] ? $post['parent_id'] : 0)];
            $image_types->save();
            if ($ids[$id] ?? false) {
                unset($ids[$id]);
            }
            $count++;
        }
        if (is_array($ids) && count($ids) > 0) {
            foreach ($ids as $id) {
                if ($id != 0) {
                    $image_types = Image_Types::find_one($id);
                    $image_types->delete();
                }
            }
        }
        return MESSAGE_SAVED;
    }
    public function action_bar()
    {
        $types_id = Yii::$app->request->get('image_types_id', 0);
        if (!$types_id) {
            return '';
        }
        $type = Image_Types::find()->where(['image_types_id' => $types_id])->as_array()->one();
        $this->layout = false;
        return $this->render('bar.tpl', ['data' => $type]);
    }
}