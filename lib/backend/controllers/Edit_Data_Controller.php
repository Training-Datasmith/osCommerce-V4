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
class Edit_Data_Controller extends Sceleton
{
    public $acl = [];
    public function action_info()
    {
        \common\helpers\Acl::check_access(\frontend\design\Edit_Data::get_access_rule('info'));
        $split_tags = ['information_h2_tag', 'information_h3_tag'];
        $field_name = Yii::$app->request->get('field', false);
        $page_id = Yii::$app->request->get('id', false);
        $platform_id = Yii::$app->request->get('platform_id', false);
        $language_id = Yii::$app->request->get('language_id', false);
        $split = Yii::$app->request->get('split', false);
        if (!$field_name || !$page_id) {
            return '';
        }
        $languages = \common\helpers\Language::get_languages();
        $platforms = \common\classes\platform::get_list(false);
        if (Yii::$app->request->is_post) {
            $fields = Yii::$app->request->post('field');
            foreach ($platforms as $platform) {
                foreach ($languages as $i => $language) {
                    if (!$fields[$platform['id']][$language['id']]) {
                        continue;
                    }
                    $page = \common\models\Information::find_one(['platform_id' => $platform['id'], 'languages_id' => $language['id'], 'information_id' => $page_id]);
                    if (in_array($field_name, $split_tags)) {
                        $arr = explode("\n", $page->attributes[$field_name]);
                        $arr[$split] = $fields[$platform['id']][$language['id']];
                        $value = implode("\n", $arr);
                    } else {
                        $value = $fields[$platform['id']][$language['id']];
                    }
                    $page->attributes = [$field_name => $value];
                    $page->save(false);
                }
            }
        }
        $fields = [];
        foreach ($platforms as $platform) {
            foreach ($languages as $i => $language) {
                $data = \backend\components\Information::read_data($page_id, $language['id'], $platform['id']);
                if (in_array($field_name, $split_tags)) {
                    $arr = explode("\n", $data[$field_name]);
                    $value = $arr[$split];
                } else {
                    $value = $data[$field_name] ?? '';
                }
                $fields[$platform['id']][$language['id']] = $value;
            }
        }
        if (!is_array($fields)) {
            return false;
        }
        $ck_editor = false;
        if ($field_name == 'description') {
            $ck_editor = true;
        }
        $this->layout = 'iframe.tpl';
        return $this->render('index.tpl', ['action' => Yii::$app->url_manager->create_url(['edit-data/info', 'field' => $field_name, 'id' => $page_id]), 'fieldName' => $field_name, 'pageId' => $page_id, 'fields' => $fields, 'platforms' => $platforms, 'languages' => $languages, 'platformId' => $platform_id, 'languageId' => $language_id, 'ckEditor' => $ck_editor]);
    }
    public function action_seo()
    {
        \common\helpers\Acl::check_access(\frontend\design\Edit_Data::get_access_rule('seo'));
        $split_tags = ['HEAD_H2_TAG_DEFAULT', 'HEAD_H3_TAG_DEFAULT'];
        $field_name = Yii::$app->request->get('field', false);
        $page_id = Yii::$app->request->get('id', false);
        $split = Yii::$app->request->get('split', false);
        $platform_id = Yii::$app->request->get('platform_id', false);
        $language_id = Yii::$app->request->get('language_id', false);
        if (!$field_name) {
            return '';
        }
        $languages = \common\helpers\Language::get_languages();
        $platforms = \common\classes\platform::get_list(false);
        if (Yii::$app->request->is_post) {
            $fields = Yii::$app->request->post('field');
            foreach ($platforms as $platform) {
                foreach ($languages as $i => $language) {
                    if (!$fields[$platform['id']][$language['id']]) {
                        continue;
                    }
                    $data = \common\models\Meta_Tags::find_one(['meta_tags_key' => $field_name, 'platform_id' => $platform['id'], 'language_id' => $language['id']]);
                    if (in_array($field_name, $split_tags)) {
                        $arr = explode("\n", $data->meta_tags_value);
                        $arr[$split] = $fields[$platform['id']][$language['id']];
                        $value = implode("\n", $arr);
                    } else {
                        $value = $fields[$platform['id']][$language['id']];
                    }
                    $data->meta_tags_value = $value;
                    $data->save(false);
                }
            }
        }
        $fields = [];
        foreach ($platforms as $platform) {
            foreach ($languages as $i => $language) {
                $data = \common\models\Meta_Tags::find_one(['meta_tags_key' => $field_name, 'platform_id' => $platform['id'], 'language_id' => $language['id']]);
                if (in_array($field_name, $split_tags)) {
                    $arr = explode("\n", $data['meta_tags_value']);
                    $value = $arr[$split];
                } else {
                    $value = $data['meta_tags_value'];
                }
                $fields[$platform['id']][$language['id']] = $value;
            }
        }
        if (!is_array($fields)) {
            return false;
        }
        $this->layout = 'iframe.tpl';
        return $this->render('index.tpl', ['action' => Yii::$app->url_manager->create_url(['edit-data/seo', 'field' => $field_name, 'id' => $page_id, 'split' => $split]), 'fieldName' => $field_name, 'pageId' => $page_id, 'fields' => $fields, 'platforms' => $platforms, 'languages' => $languages, 'platformId' => $platform_id, 'languageId' => $language_id, 'ckEditor' => false, 'input' => true]);
    }
    public function action_menu()
    {
        \common\helpers\Acl::check_access(\frontend\design\Edit_Data::get_access_rule('menu'));
        $field_name = Yii::$app->request->get('field', false);
        $page_id = Yii::$app->request->get('id', false);
        $language_id = Yii::$app->request->get('language_id', false);
        $platform_id = Yii::$app->request->get('platform_id', false);
        $translation_key = Yii::$app->request->get('key', false);
        $translation_entity = Yii::$app->request->get('entity', false);
        $is_guest = Yii::$app->request->get('is_guest', false);
        $languages = \common\helpers\Language::get_languages();
        $fields = [];
        $menu_items = [];
        $menu_item = \common\models\Menu_Items::find()->where(['id' => $page_id])->select(['link_type', 'link_id'])->as_array()->one();
        $link_type = $menu_item['link_type'];
        $link_id = $menu_item['link_id'];
        $link_type_text = '';
        if (Yii::$app->request->is_post) {
            $post_fields = Yii::$app->request->post('field');
            $post_menu_items = Yii::$app->request->post('menu_item');
            foreach ($languages as $i => $language) {
                $menu_title = \common\models\Menu_Titles::find_one(['item_id' => $page_id, 'language_id' => $language['id']]);
                if ($post_menu_items[$language['id']]) {
                    if (!$menu_title) {
                        $menu_title = new \common\models\Menu_Titles();
                        $menu_title->language_id = $language['id'];
                        $menu_title->item_id = $page_id;
                    }
                    $menu_title->title = $post_menu_items[$language['id']];
                    $menu_title->save(false);
                } elseif ($menu_title) {
                    $menu_title->delete();
                }
                switch ($link_type) {
                    case 'info':
                        $page = \common\models\Information::find_one(['platform_id' => $platform_id, 'languages_id' => $language['id'], 'information_id' => $link_id]);
                        if ($page) {
                            if ($page->info_title || !$page->page_title) {
                                $page->info_title = $post_fields[$language['id']];
                            } else {
                                $page->page_title = $post_fields[$language['id']];
                            }
                            $page->save(false);
                        }
                        break;
                    case 'categories':
                        $category = \common\models\Categories_Description::find_one(['categories_id' => $link_id, 'language_id' => $language['id']]);
                        $category->categories_name = $post_fields[$language['id']];
                        $category->save(false);
                        break;
                    case 'all-products':
                    case 'default':
                        $data = \common\models\Translation::find_one(['language_id' => $language['id'], 'translation_key' => $translation_key, 'translation_entity' => $translation_entity]);
                        $data->translation_value = $post_fields[$language['id']];
                        $data->save(false);
                        \common\helpers\Translation::reset_cache();
                        break;
                }
            }
            if ($link_type == 'brands') {
                $post_brand_field = Yii::$app->request->post('brand_field', false);
                $data = \common\models\Manufacturers::find_one($link_id);
                $data->manufacturers_name = $post_brand_field;
                $data->save();
            }
        }
        $hide_field = true;
        $brand_field = false;
        foreach ($languages as $i => $language) {
            $menu_title = \common\models\Menu_Titles::find()->where(['item_id' => $page_id, 'language_id' => $language['id']])->select(['title'])->as_array()->one();
            $menu_items[$language['id']] = $menu_title['title'] ?? '';
            switch ($link_type) {
                case 'info':
                    $data = \backend\components\Information::read_data($link_id, $language['id'], $platform_id);
                    if ($data['info_title']) {
                        $fields[$language['id']] = $data['info_title'];
                    } elseif ($data['page_title']) {
                        $fields[$language['id']] = $data['page_title'];
                    }
                    $link_type_text = 'Information page name';
                    $hide_field = false;
                    break;
                case 'categories':
                    $data = \common\models\Categories_Description::find_one(['categories_id' => $link_id, 'language_id' => $language['id']]);
                    $fields[$language['id']] = $data->categories_name;
                    $link_type_text = 'Category name';
                    $hide_field = false;
                    break;
                case 'default':
                    $link_type_text = 'Edit translation, key: ' . $translation_key . '; entity: ' . $translation_entity;
                    $data = \common\models\Translation::find_one(['language_id' => $language['id'], 'translation_key' => $translation_key, 'translation_entity' => $translation_entity]);
                    $fields[$language['id']] = $data->translation_value;
                    $hide_field = false;
                    break;
            }
        }
        if ($link_type == 'brands') {
            $data = \common\models\Manufacturers::find_one($link_id);
            $brand_field = $data->manufacturers_name;
            $link_type_text = 'Brand name';
        }
        $action_params = ['edit-data/menu', 'field' => $field_name, 'id' => $page_id, 'language_id' => $language_id, 'platform_id' => $platform_id];
        if ($translation_key) {
            $action_params['key'] = $translation_key;
        }
        if ($translation_entity) {
            $action_params['entity'] = $translation_entity;
        }
        $this->layout = 'iframe.tpl';
        return $this->render('menu.tpl', ['action' => Yii::$app->url_manager->create_url($action_params), 'fieldName' => $field_name, 'pageId' => $page_id, 'fields' => $fields, 'menuItems' => $menu_items, 'languages' => $languages, 'languageId' => $language_id, 'linkTypeText' => $link_type_text, 'hideField' => $hide_field, 'brandField' => $brand_field]);
    }
}