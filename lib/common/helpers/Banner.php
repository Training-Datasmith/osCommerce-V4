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

use common\models\Banners_Groups;
use common\models\Banners_Groups_Sizes;
use common\models\Banners_To_Platform;
class Banner
{
    public static function group_data($group, $platforms = [], $active_status = false)
    {
        $banners_data = [];
        $banners_query = \common\models\Banners::find()->alias('b')->left_join(Banners_Groups::table_name() . ' bg', 'bg.id = b.group_id')->where(['banners_group' => $group]);
        if (count($platforms)) {
            $banners_query->left_join(Banners_To_Platform::table_name() . ' b2p', 'b2p.banners_id = b.banners_id')->and_where(['in', 'platform_id', $platforms]);
        }
        if ($active_status) {
            $banners_query->and_where(['status' => '1']);
        }
        $banners = $banners_query->as_array()->all();
        foreach ($banners as $banner) {
            $languages = [];
            $banner_languages = \common\models\Banners_Languages::find()->where(['banners_id' => $banner['banners_id']])->as_array()->all();
            foreach ($banner_languages as $language) {
                $groups_images = [];
                $banners_groups_images = \common\models\Banners_Groups_Images::find()->where(['banners_id' => $banner['banners_id'], 'language_id' => $language['language_id']])->as_array()->all();
                foreach ($banners_groups_images as $groups_image) {
                    $groups_images[] = ['image_width' => $groups_image['image_width'], 'image' => $groups_image['image']];
                }
                $language_key = \common\helpers\Language::get_language_code($language['language_id']);
                if (!isset($language_key['code'])) {
                    continue;
                }
                $languages[$language_key['code']] = ['banners_title' => $language['banners_title'], 'banners_url' => $language['banners_url'], 'banners_image' => $language['banners_image'], 'banners_html_text' => $language['banners_html_text'], 'target' => $language['target'], 'banner_display' => $language['banner_display'], 'text_position' => $language['text_position'], 'svg' => $language['svg'], 'groupsImages' => $groups_images];
            }
            $banners_data[] = ['expires_impressions' => $banner['expires_impressions'], 'expires_date' => $banner['expires_date'], 'date_scheduled' => $banner['date_scheduled'], 'status' => $banner['status'], 'sort_order' => $banner['sort_order'], 'banner_type' => $banner['banner_type'], 'languages' => $languages];
        }
        return $banners_data;
    }
    public static function group_settings($group)
    {
        $group_data = [];
        $group_settings = \common\models\Banners_Groups::find()->alias('bg')->inner_join(Banners_Groups_Sizes::table_name() . ' bgs', 'bg.id = bgs.group_id')->where(['banners_group' => $group])->as_array()->all();
        foreach ($group_settings as $group_setting) {
            if (isset($group_setting['image_width']) && $group_setting['image_width']) {
                $group_data[$group_setting['image_width']] = ['width_from' => $group_setting['width_from'] ?? '', 'width_to' => $group_setting['width_to'] ?? ''];
            }
        }
        return $group_data;
    }
    public static function group_images($group_data, $images = [], $images_to_keys = false)
    {
        foreach ($group_data as $banner_key => $banner) {
            foreach ($banner['languages'] as $language_key => $language) {
                $main_img = $group_data[$banner_key]['languages'][$language_key]['banners_image'];
                $main_img_key = self::image_key($main_img, $images);
                $images[$main_img_key] = $main_img;
                if ($images_to_keys) {
                    $group_data[$banner_key]['languages'][$language_key]['banners_image'] = $main_img_key;
                }
                foreach ($group_data[$banner_key]['languages'][$language_key]['groupsImages'] as $group_key => $group) {
                    $group_img = $group['image'];
                    $group_img_key = self::image_key($group_img, $images);
                    $images[$group_img_key] = $group_img;
                    if ($images_to_keys) {
                        $group_data[$banner_key]['languages'][$language_key]['groupsImages'][$group_key]['image'] = $group_img_key;
                    }
                }
            }
        }
        return [$images, $group_data];
    }
    private static function image_key($image_path, $images)
    {
        $img_key = array_pop(explode('/', $image_path));
        if (isset($images[$img_key]) && $images[$img_key] && $image_path != $images[$img_key]) {
            $img_key_arr = explode('.', $img_key);
            $file_name = $img_key_arr[0];
            $ext = $img_key_arr[1];
            $count = 1;
            $img_key = $file_name . '-' . $count . '.' . $ext;
            while ($images[$img_key] && $image_path != $images[$img_key]) {
                $count++;
                $img_key = $file_name . '-' . $count . '.' . $ext;
            }
        }
        $images[$img_key] = $image_path;
        return $img_key;
    }
    public static function setup_banners($banner_data, $image_path, $platform_ids, $force_create = false)
    {
        foreach ($banner_data as $group_name => $group_data) {
            if ($group_name == 'groupSettings') {
                foreach ($group_data as $settings_group_name => $settings_group_data) {
                    $new_group_name = self::setup_group_settings($settings_group_name, $settings_group_data, $force_create);
                    if ($force_create && $new_group_name != $settings_group_name) {
                        $banner_data[$new_group_name] = $banner_data[$settings_group_name];
                        unset($banner_data[$settings_group_name]);
                    }
                }
                break;
            }
        }
        $banners_ids = [];
        foreach ($banner_data as $group_name => $group_data) {
            if ($group_name == 'groupSettings') {
                continue;
            }
            foreach ($group_data as $banner) {
                $banners_ids[] = self::setup_banner($banner, $group_name, $image_path, $platform_ids, $force_create);
            }
        }
        return $banners_ids;
    }
    public static function setup_group_settings($group_name, $group_data, $force_create = false)
    {
        if ($force_create) {
            $_group_name = $group_name;
            for ($i = 1; Banners_Groups::find_one(['banners_group' => $_group_name]) && $i < 100; $i++) {
                $_group_name = $group_name . '-' . $i;
            }
            $group_name = $_group_name;
        }
        $group = Banners_Groups::find_one(['banners_group' => $group_name]);
        if (!$group) {
            $group = new \common\models\Banners_Groups();
            $group->attributes = ['banners_group' => $group_name];
            $group->save();
        }
        $id = $group->get_primary_key();
        foreach ($group_data as $banner) {
            $group_sizes = Banners_Groups_Sizes::find_one(['group_id' => $id, 'image_width' => $banner['image_width'] ?? '']);
            if ($group_sizes || !$banner['image_width']) {
                continue;
            }
            $group_sizes = new Banners_Groups_Sizes();
            $group_sizes->group_id = $id;
            $group_sizes->width_from = $banner['width_from'] ?? 0;
            $group_sizes->width_to = $banner['width_to'] ?? 0;
            $group_sizes->image_width = $banner['image_width'] ?? 0;
            $group_sizes->image_height = $banner['image_height'] ?? 0;
            $group_sizes->save(false);
        }
        return $group_name;
    }
    public static function setup_banner($banner, $group_name, $image_path, $platform_ids = [], $force_create = false)
    {
        if (!$force_create) {
            foreach ($banner['languages'] as $language_key => $language) {
                $language_data = Language::get_language_id($language_key);
                if (!($language_data['languages_id'] ?? false)) {
                    continue;
                }
                $banner_data = \common\models\Banners::find()->alias('b')->select(['b.banners_id'])->left_join(\common\models\Banners_Groups::table_name() . ' bg', 'b.group_id = bg.id')->left_join(\common\models\Banners_Languages::table_name() . ' bl', 'b.banners_id = bl.banners_id')->where(['banners_group' => $group_name, 'banners_title' => $language['banners_title'], 'language_id' => $language_data['languages_id']])->as_array()->one();
                if (isset($banner_data['banners_id']) && $banner_data['banners_id']) {
                    return $banner_data['banners_id'];
                }
            }
        }
        $banners_groups = Banners_Groups::find_one(['banners_group' => $group_name]);
        if (!$banners_groups) {
            $banners_groups = new Banners_Groups();
            $banners_groups->save();
            $group_id = $banners_groups->get_primary_key();
        } else {
            $group_id = $banners_groups->id;
        }
        $banner_model = new \common\models\Banners();
        $banner_model->attributes = ['group_id' => $group_id, 'expires_impressions' => $banner['expires_impressions'] ?? '', 'expires_date' => $banner['expires_date'] ?? '', 'date_scheduled' => $banner['date_scheduled'] ?? '', 'date_added' => new \yii\db\Expression('NOW()'), 'status' => $banner['status'] ?? '', 'sort_order' => $banner['sort_order'] ?? '', 'banner_type' => $banner['banner_type'] ?? ''];
        $banner_model->group_id = $group_id;
        $banner_model->save(false);
        $banner_id = $banner_model->get_primary_key();
        if (!$banner_id) {
            return '';
        }
        foreach ($banner['languages'] as $language_key => $language) {
            $banner_image = \common\classes\Images::move_image($image_path . $language['banners_image'], 'banners' . DIRECTORY_SEPARATOR . $banner_id, false);
            $language_data = Language::get_language_id($language_key);
            if (!($language_data['languages_id'] ?? false)) {
                continue;
            }
            $banners_languages = new \common\models\Banners_Languages();
            $banners_languages->attributes = ['banners_id' => $banner_id, 'banners_title' => $language['banners_title'], 'banners_url' => $language['banners_url'], 'banners_image' => $banner_image, 'banners_html_text' => $language['banners_html_text'], 'language_id' => (int) $language_data['languages_id'], 'target' => $language['target'], 'banner_display' => $language['banner_display'], 'text_position' => $language['text_position'], 'svg' => $language['svg']];
            $banners_languages->save();
            if ($force_create) {
                foreach ($platform_ids as $platform_id) {
                    if (!\common\models\Banners_To_Platform::find_one([])) {
                        $banners_to_platform = new \common\models\Banners_To_Platform();
                        $banners_to_platform->banners_id = $banner_id;
                        $banners_to_platform->platform_id = $platform_id;
                        $banners_to_platform->save();
                    }
                }
            }
            foreach ($language['groupsImages'] as $groups_image) {
                $banner_image_group = \common\classes\Images::move_image($image_path . $groups_image['image'], 'banners' . DIRECTORY_SEPARATOR . $banner_id, false);
                $groups_image_model = new \common\models\Banners_Groups_Images();
                $groups_image_model->attributes = ['banners_id' => $banner_id, 'language_id' => (int) $language_data['languages_id'], 'image_width' => $groups_image['image_width'], 'image' => $banner_image_group];
                $groups_image_model->save();
            }
        }
        return $banner_id;
    }
    public static function add_banner_images($banner, $group_name, $image_path)
    {
        $path = DIR_FS_CATALOG . DIR_WS_IMAGES;
        foreach ($banner['languages'] as $language_key => $language) {
            $image_in_db = \common\models\Banners_Languages::find()->where(['banners_image' => $language['banners_image']])->count();
            if ($image_in_db && !is_file($path . $language['banners_image']) && is_file($image_path . $language['banners_image'])) {
                $destination = substr($language['banners_image'], 0, strrpos($language['banners_image'], '/'));
                \yii\helpers\File_Helper::create_directory($path . $destination, 0777);
                copy($image_path . $language['banners_image'], $path . $language['banners_image']);
            }
            foreach ($language['groupsImages'] as $groups_image) {
                $image_in_db = \common\models\Banners_Groups_Images::find()->where(['image' => $groups_image['image']])->count();
                if ($image_in_db && !is_file($path . $groups_image['image']) && is_file($image_path . $groups_image['image'])) {
                    $destination = substr($groups_image['image'], 0, strrpos($groups_image['image'], '/'));
                    \yii\helpers\File_Helper::create_directory($path . $destination, 0777);
                    copy($image_path . $groups_image['image'], $path . $groups_image['image']);
                }
            }
        }
    }
}