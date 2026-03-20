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

use common\classes\Images;
use common\classes\platform as Platform;
use common\models\Banners_Languages;
use common\models\Categories;
use common\models\Categories_Description;
use common\models\Products2Categories;
use common\models\Products_Description;
use common\models\Products_Images;
use common\models\Products_Images_Description;
use common\models\Themes;
use yii\helpers\File_Helper;
class File_Manager
{
    public static $valid_file_types = ['image', 'video', 'pdf', 'txt', 'svg', 'svg+xml'];
    public static $valid_directories = ['main', 'themes', 'banners', 'products', 'categories', 'pages'];
    public static $file_types = [];
    public static function get_files($directory = ['main'], $file_types = '')
    {
        self::$file_types = self::convert_file_types($file_types);
        if (isset($directory[1]['name']) && in_array($directory[1]['name'], self::$valid_directories) && method_exists(self::class, $directory[1]['name'])) {
            [$all_files, $directories] = call_user_func([self::class, $directory[1]['name']], $directory);
        } else {
            [$all_files, $directories] = self::main();
        }
        foreach (\common\helpers\Hooks::get_list('design/file-manager') as $filename) {
            include $filename;
        }
        $filtered_files = [];
        $counter = 0;
        foreach ($all_files as $file) {
            $type = self::get_file_type(is_array($file) ? $file['fileHash'] : $file);
            if (!in_array($type, self::$file_types)) {
                continue;
            }
            $counter++;
            $filtered_files[] = ['file' => is_array($file) ? $file['file'] : $file, 'fileHash' => is_array($file) ? $file['fileHash'] : '', 'fileName' => is_array($file) ? $file['fileName'] : pathinfo($file, PATHINFO_BASENAME), 'type' => $type];
        }
        return ['allCount' => $counter, 'files' => $filtered_files, 'directories' => $directories];
    }
    public static function main()
    {
        $all_files = scandir(Images::get_fs_catalog_images_path());
        $directories = [['name' => 'banners', 'title' => 'Banners'], ['name' => 'products', 'title' => 'Products'], ['name' => 'categories', 'title' => 'Categories'], ['name' => 'pages', 'title' => 'Info Pages']];
        $themes_images = false;
        foreach (Themes::find()->as_array()->all() as $theme) {
            if (is_dir(Images::get_fs_catalog_images_path() . '../themes/' . $theme['theme_name'] . '/img')) {
                $themes_images = true;
                break;
            }
        }
        if ($themes_images) {
            $directories[] = ['name' => 'themes', 'title' => 'Themes'];
        }
        return [$all_files, $directories];
    }
    public static function themes($directory)
    {
        $all_files = [];
        $directories = [];
        if (isset($directory[2]['name'])) {
            if (is_dir(Images::get_fs_catalog_images_path() . '../themes/' . $directory[2]['name'] . '/img')) {
                $files = scandir(Images::get_fs_catalog_images_path() . '../themes/' . $directory[2]['name'] . '/img');
                foreach ($files as $file) {
                    $all_files[] = '../themes/' . $directory[2]['name'] . '/img/' . $file;
                }
            }
        } else {
            foreach (Themes::find()->as_array()->all() as $theme) {
                if (is_dir(Images::get_fs_catalog_images_path() . '../themes/' . $theme['theme_name'] . '/img')) {
                    $directories[] = ['name' => $theme['theme_name'], 'title' => $theme['title']];
                }
            }
        }
        return [$all_files, $directories];
    }
    public static function banners($directory)
    {
        $language_id = \Yii::$app->settings->get('languages_id');
        $all_files = [];
        $directories = [];
        if (isset($directory[2]['name'])) {
            if (is_dir(Images::get_fs_catalog_images_path() . 'banners/' . $directory[2]['name'])) {
                $files = scandir(Images::get_fs_catalog_images_path() . 'banners/' . $directory[2]['name']);
                foreach ($files as $file) {
                    $all_files[] = 'banners/' . $directory[2]['name'] . '/' . $file;
                }
            }
        } else {
            $banners = Banners_Languages::find()->where(['language_id' => $language_id])->as_array()->all();
            foreach ($banners as $banner) {
                if (is_dir(Images::get_fs_catalog_images_path() . 'banners/' . $banner['banners_id'])) {
                    $directories[] = ['name' => $banner['banners_id'], 'title' => $banner['banners_title']];
                }
            }
        }
        return [$all_files, $directories];
    }
    public static function pages($directory)
    {
        $all_files = [];
        $directories = [];
        if (is_dir(Images::get_fs_catalog_images_path() . 'information')) {
            $files = scandir(Images::get_fs_catalog_images_path() . 'information/');
            foreach ($files as $file) {
                $all_files[] = 'information/' . $file;
            }
        }
        return [$all_files, $directories];
    }
    public static function categories($directory)
    {
        $language_id = \Yii::$app->settings->get('languages_id');
        $all_files = [];
        $directories = [];
        $directory_end = end($directory);
        if (isset($directory_end['name'])) {
            foreach (['', '/hero', '/gallery', '/homepage', '/menu'] as $folder) {
                if (is_dir(Images::get_fs_catalog_images_path() . 'categories/' . $directory_end['name'] . $folder)) {
                    $files = scandir(Images::get_fs_catalog_images_path() . 'categories/' . $directory_end['name'] . $folder);
                    foreach ($files as $file) {
                        $all_files[] = 'categories/' . $directory_end['name'] . $folder . '/' . $file;
                    }
                }
            }
        }
        $category_id = 0;
        if (isset($directory[2]) && isset($directory_end['name'])) {
            $category_id = $directory_end['name'];
        }
        $categories = Categories::find()->alias('c')->select(['title' => 'cd.categories_name', 'name' => 'c.categories_id'])->left_join(Categories_Description::table_name() . ' cd', "c.categories_id = cd.categories_id and cd.language_id = '" . $language_id . "'")->and_where(['c.parent_id' => $category_id])->as_array()->all();
        foreach ($categories as $category) {
            if (self::has_category_images($category['name'])) {
                $directories[] = $category;
            }
        }
        return [$all_files, $directories];
    }
    public static function products($directory)
    {
        $language_id = \Yii::$app->settings->get('languages_id');
        $all_files = [];
        $directories = [];
        $directory_end = end($directory);
        $split = explode('-', $directory_end['name']);
        if ($split[0] == 'product') {
            if (isset($split[1])) {
                $products_images = Products_Images_Description::find()->alias('pid')->select('pid.*, pi.products_id')->left_join(Products_Images::table_name() . ' pi', 'pi.products_images_id = pid.products_images_id')->and_where(['pi.products_id' => $split[1]])->and_where(['not', ['pid.hash_file_name' => '']])->as_array()->all();
                foreach ($products_images as $image) {
                    $all_files[] = ['file' => 'products/' . $image['products_id'] . '/' . $image['products_images_id'] . '/' . $image['orig_file_name'], 'fileHash' => 'products/' . $image['products_id'] . '/' . $image['products_images_id'] . '/' . $image['hash_file_name'], 'fileName' => $image['orig_file_name']];
                }
            }
        } else {
            $category_id = 0;
            if (isset($directory[2]) && isset($directory_end['name'])) {
                $category_id = $directory_end['name'];
            }
            $categories = Categories::find()->alias('c')->select(['title' => 'cd.categories_name', 'name' => 'c.categories_id'])->left_join(Categories_Description::table_name() . ' cd', "c.categories_id = cd.categories_id and cd.language_id = '" . $language_id . "'")->and_where(['c.parent_id' => $category_id])->as_array()->all();
            foreach ($categories as $category) {
                $directories[] = $category;
            }
            $products = Products2Categories::find()->alias('p2c')->select(['pd.products_name', 'pd.products_id'])->left_join(Products_Description::table_name() . ' pd', "p2c.products_id = pd.products_id and pd.language_id = '" . $language_id . "' and pd.platform_id = '" . Platform::default_id() . "'")->and_where(['p2c.categories_id' => $category_id])->as_array()->all();
            foreach ($products as $product) {
                $directories[] = ['name' => 'product-' . $product['products_id'], 'title' => $product['products_name']];
            }
        }
        return [$all_files, $directories];
    }
    public static function has_category_images($category_id)
    {
        foreach (['', '/hero', '/gallery', '/homepage', '/menu'] as $folder) {
            if (is_dir(Images::get_fs_catalog_images_path() . 'categories/' . $category_id . $folder)) {
                $files = scandir(Images::get_fs_catalog_images_path() . 'categories/' . $category_id . $folder);
                foreach ($files as $file) {
                    if (!is_dir(Images::get_fs_catalog_images_path() . 'categories/' . $category_id . $folder . '/' . $file)) {
                        return true;
                    }
                }
            }
        }
        $categories = Categories::find()->select(['categories_id'])->where(['parent_id' => $category_id])->as_array()->all();
        foreach ($categories as $category) {
            if (self::has_category_images($category['categories_id'])) {
                return true;
            }
        }
        return false;
    }
    public static function create_thumbnails($file)
    {
        $thumbnail_img = 'thumbnails/' . $file;
        $thumbnail = Images::get_fs_catalog_images_path() . $thumbnail_img;
        File_Helper::create_directory(dirname($thumbnail));
        if (!is_file($thumbnail)) {
            if (!is_file(Images::get_fs_catalog_images_path() . $file)) {
                $split_file = explode('/', $file);
                if ($split_file[0] == 'products') {
                    $file_name = end($split_file);
                    $img = Products_Images_Description::find()->where(['orig_file_name' => $file_name, 'products_images_id' => $split_file[2]])->as_array()->one();
                    $hash = isset($img['hash_file_name']) ? $img['hash_file_name'] : $file_name;
                    $split_file[count($split_file) - 1] = $hash;
                    $file = implode('/', $split_file);
                }
            }
            if (is_file(Images::get_fs_catalog_images_path() . $file)) {
                Images::tep_image_resize(Images::get_fs_catalog_images_path() . $file, $thumbnail, 150, 110);
            }
        }
        return ['thumbnail' => $thumbnail_img];
    }
    public static function validate_file_types($file_types)
    {
        if (!$file_types || !is_array($file_types)) {
            return self::$valid_file_types;
        }
        $types = [];
        foreach ($file_types as $type) {
            if (in_array($type, self::$valid_file_types)) {
                $types[] = $type;
            }
        }
        return $types;
    }
    public static function get_file_type($file)
    {
        if (!is_file(Images::get_fs_catalog_images_path() . $file)) {
            return '';
        }
        $full_type = explode('/', mime_content_type(Images::get_fs_catalog_images_path() . $file));
        if (in_array($full_type[0], ['application', 'text'])) {
            return $full_type[1];
        }
        if (isset($full_type[1]) && in_array($full_type[1], ['svg', 'svg+xml'])) {
            return $full_type[1];
        }
        return $full_type[0];
    }
    public static function convert_file_types($file_types)
    {
        if (!$file_types) {
            return self::$valid_file_types;
        }
        $types = [];
        $types_arr = explode(',', $file_types);
        foreach ($types_arr as $type) {
            $type = trim($type, ' .');
            $chunks = explode('/', $type);
            if (in_array($chunks[0], ['application', 'text'])) {
                $types[] = $chunks[1];
            } elseif (isset($chunks[0]) && isset($chunks[1]) && $chunks[0] == 'image' && $chunks[1] == '*') {
                $types[] = 'image';
                $types[] = 'svg';
                $types[] = 'svg+xml';
            } elseif (isset($chunks[1]) && in_array($chunks[1], ['svg', 'svg+xml'])) {
                $types[] = $chunks[1];
            } else {
                $types[] = $chunks[0];
            }
        }
        return self::validate_file_types($types);
    }
}