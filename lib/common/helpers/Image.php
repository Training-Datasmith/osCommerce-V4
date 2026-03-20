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

use common\classes\Images;
use common\models\Image_Types;
use common\models\Products_Images;
class Image
{
    public static function copy_product_images($from_product_id, $to_product_id)
    {
        $imgs = Products_Images::find()->and_where(['products_id' => $from_product_id])->as_array()->all();
        if (is_array($imgs)) {
            $copy_models = ['\common\models\ProductsImagesAttributes' => 'products_images_id', '\common\models\ProductsImagesDescription' => 'products_images_id', '\common\models\ProductsImagesExternalUrl' => 'products_images_id'];
            $base_path = \common\classes\Images::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR;
            //$productsId.'/' .$imageId
            foreach ($imgs as $img) {
                $tmp = $img;
                foreach (['description', 'attributes', 'externalUrl', 'inventory', 'products_images_id'] as $key) {
                    unset($tmp[$key]);
                }
                $tmp['products_id'] = $to_product_id;
                try {
                    $copy_model = new Products_Images();
                    $copy_model->set_attributes($tmp, false);
                    $copy_model->load_default_values(true);
                    $copy_model->save(false);
                    $new_image_id = $copy_model->products_images_id;
                    \yii\helpers\Base_File_Helper::copy_directory($base_path . $from_product_id . DIRECTORY_SEPARATOR . $img['products_images_id'], $base_path . $to_product_id . DIRECTORY_SEPARATOR . $new_image_id);
                    foreach ($copy_models as $copy_model_class => $copy_product_column) {
                        if (!class_exists($copy_model_class)) {
                            continue;
                        }
                        call_user_func_array([$copy_model_class, 'deleteAll'], [[$copy_product_column => $new_image_id]]);
                        $source_collection = call_user_func_array([$copy_model_class, 'findAll'], [[$copy_product_column => $img['products_images_id']]]);
                        foreach ($source_collection as $origin_model) {
                            $__data = $origin_model->get_attributes();
                            $__data[$copy_product_column] = $new_image_id;
                            $copy_model = \Yii::create_object($copy_model_class);
                            if ($copy_model instanceof \yii\db\Active_Record) {
                                $copy_model->set_attributes($__data, false);
                                $copy_model->load_default_values(true);
                                $copy_model->save(false);
                            }
                        }
                    }
                } catch (\Exception $ex) {
                    \Yii::error($ex->get_message());
                }
            }
        }
    }
    public static function get_new_size($pic, $req_w, $req_h)
    {
        $size = @get_image_size($pic);
        if (!is_array($size)) {
            $size = [0, 0];
        }
        if ($size[0] == 0 || $size[1] == 0) {
            $newsize[0] = $req_w;
            $newsize[1] = $req_h;
            return $newsize;
        }
        $scale = @min(intval($req_w) / intval($size[0]), intval($req_h) / intval($size[1]));
        $newsize[0] = $size[0] * $scale;
        $newsize[1] = $size[1] * $scale;
        return $newsize;
    }
    public static function info_image($image, $alt, $width = '', $height = '')
    {
        if (tep_not_null($image) && file_exists(DIR_FS_CATALOG_IMAGES . $image)) {
            if ($width != '' && $height != '') {
                $size = @get_image_size(DIR_FS_CATALOG_IMAGES . $image);
                if (!($size[0] <= $width && $size[1] <= $height)) {
                    $newsize = self::get_new_size(DIR_FS_CATALOG_IMAGES . $image, $width, $height);
                    $width = $newsize[0];
                    $height = $newsize[1];
                } else {
                    $width = $size[0];
                    $height = $size[1];
                }
            }
            $image = tep_image(DIR_WS_CATALOG_IMAGES . $image, $alt, $width, $height);
        } else {
            $image = TEXT_IMAGE_NONEXISTENT;
        }
        return $image;
    }
    public static function get_categories_additional_images($categories_id)
    {
        $image_types_id = Image_Types::find_one(['image_types_name' => 'Category gallery add'])->image_types_id ?? 0;
        $where['categories_id'] = $categories_id;
        $where['image_types_id'] = $image_types_id;
        $categories_images = \common\models\Categories_Images::find()->where($where)->as_array()->order_by('sort_order')->all();
        $c_images = [];
        foreach ($categories_images as $categories_image) {
            $catalog = defined('DIR_WS_CATALOG_IMAGES') ? DIR_WS_CATALOG_IMAGES : DIR_WS_IMAGES;
            $categories_image['image_url'] = $catalog . $categories_image['image'];
            $c_images[$categories_image['platform_id']][] = $categories_image;
        }
        return $c_images;
    }
    public static function save_categories_additional_images($images, $categories_id)
    {
        $image_types_id = Image_Types::find_one(['image_types_name' => 'Category gallery add'])->image_types_id ?? 0;
        foreach (array_merge([['id' => 0]], \common\classes\platform::get_list(false)) as $platform) {
            $categories_images = \common\models\Categories_Images::find()->where(['categories_id' => $categories_id, 'platform_id' => $platform['id'], 'image_types_id' => $image_types_id])->all();
            foreach ($categories_images as $c_image) {
                if (!is_array($images['image_id'][$platform['id']]) || !in_array($c_image->categories_images_id, $images['image_id'][$platform['id']])) {
                    $image_location = DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG_IMAGES . $c_image->image;
                    if (file_exists($image_location)) {
                        @unlink($image_location);
                    }
                    Images::remove_resize_images($c_image->image);
                    Images::remove_webp($c_image->image);
                    $c_image->delete();
                }
            }
            if (!isset($images['image_id'][$platform['id']]) || !is_array($images['image_id'][$platform['id']])) {
                continue;
            }
            foreach ($images['image_id'][$platform['id']] as $key => $image_id) {
                if ($image_id) {
                    $categories_images = \common\models\Categories_Images::find_one($image_id);
                } else {
                    $categories_images = new \common\models\Categories_Images();
                    $img_path = DIR_WS_IMAGES . 'categories' . DIRECTORY_SEPARATOR . $categories_id . DIRECTORY_SEPARATOR;
                    $val = \backend\design\Uploads::move($images['image'][$platform['id']][$key], $img_path, true);
                    $val = str_replace(DIR_WS_IMAGES, '', $val);
                    $categories_images->image = $val;
                    Images::create_webp($val, true);
                    Images::create_resize_images($val, 'Category gallery add', true);
                    $categories_images->categories_id = $categories_id;
                    $categories_images->platform_id = $platform['id'];
                    $categories_images->image_types_id = $image_types_id;
                }
                $categories_images->sort_order = $key;
                $categories_images->save(false);
            }
        }
    }
    public static function info_image_if_exists($image, $alt, $width = '', $height = '')
    {
        return str_replace(TEXT_IMAGE_NONEXISTENT, '', self::info_image($image, $alt, $width, $height));
    }
    /**
     * Prepare image for saving: move, remove, resize image
     *
     * @param string $oldValue - value from db
     * @param string $newValue - value from form
     * @param string $upload - upload value from form, image with path from root or admin/uploads folder which has to be moved to the $path
     * @param string $path - path to image destination, starts from image dir if $themes==false and from root if $themes==true
     * @param boolean $remove - has to remove $oldImage
     * @param boolean $themes - is it theme image
     * @param array $resize - [width, height, fit] - settings for resize image
     * @return string - filename with path for saving in db
     */
    public static function prepare_saving_image($old_value, $new_value, $upload = '', $path = '', $remove = false, $themes = false, $resize = [])
    {
        $image = $old_value;
        if (!$new_value && !$upload && !$remove && !($resize['parentImage'] ?? false)) {
            return '';
        }
        if (($resize['parentImage'] ?? false) && ($resize['parentOldImage'] ?? false) && $resize['parentImage'] != $resize['parentOldImage'] && !$upload) {
            $parent_old_image = pathinfo($resize['parentOldImage'], PATHINFO_FILENAME);
            $old_image = pathinfo($old_value, PATHINFO_FILENAME);
            if (strpos($old_image, $parent_old_image) === 0 && pathinfo($old_value, PATHINFO_EXTENSION) == pathinfo($resize['parentOldImage'], PATHINFO_EXTENSION)) {
                $remove = true;
                $upload = DIR_WS_IMAGES . $resize['parentImage'];
            }
        } elseif (($resize['parentImage'] ?? false) && !$upload && !$new_value) {
            $upload = DIR_WS_IMAGES . $resize['parentImage'];
        }
        if ($themes) {
            $fs_path = DIR_FS_CATALOG;
        } else {
            $fs_path = Images::get_fs_catalog_images_path();
            $path = DIR_WS_IMAGES . $path;
        }
        if ($remove && $old_value) {
            if (is_file($fs_path . $old_value)) {
                unlink($fs_path . $old_value);
            }
            $pos = strripos($old_value, '.');
            $name = substr($old_value, 0, $pos + 1) . 'webp';
            if (is_file($fs_path . $name)) {
                unlink($fs_path . $name);
            }
        }
        if (!$new_value && !$upload) {
            return '';
        }
        if ($old_value == $new_value && !$upload && !$remove) {
            return $old_value;
        }
        if ($themes && dirname($old_value) == dirname($new_value) && !$upload) {
            return $new_value;
        }
        if (in_array(substr($upload, 0, 7), ['images/', 'themes/'])) {
            $file_from = DIR_FS_CATALOG . $upload;
        } else {
            $file_from = DIR_FS_CATALOG . 'uploads/' . $upload;
        }
        $type = false;
        if (is_file($file_from)) {
            $type = explode('/', mime_content_type($file_from));
        }
        if (($resize['width'] ?? false) && $upload && $type && $type[0] == 'image' && substr($upload, -3) != 'svg') {
            $img_explode = explode('/', str_replace('\\', '/', $upload));
            $img_name = end($img_explode);
            $pos = strrpos($img_name, '.');
            $name = substr($img_name, 0, $pos);
            $ext = substr($img_name, $pos);
            $new_img = $path . '/' . $name . '[' . $resize['width'] . ']' . $ext;
            if (!is_file(DIR_FS_CATALOG . $new_img) && $ext !== 'svg') {
                $size = @get_image_size($file_from);
                if (isset($size[0]) && $size[0]) {
                    if ($resize['width'] && $resize['height'] && ($resize['fit'] == 'cover' || !$resize['fit'])) {
                        $scale = @max($resize['width'] / $size[0], $resize['height'] / $size[1]);
                        $width = $size[0] * $scale;
                        $height = $size[1] * $scale;
                    } elseif (!$resize['width'] && $resize['height']) {
                        $width = $size[0] * $resize['height'] / $size[1];
                        $height = $resize['height'];
                    } else {
                        $width = $resize['width'];
                        $height = $size[1] * $resize['width'] / $size[0];
                    }
                    Images::tep_image_resize($file_from, DIR_FS_CATALOG . $new_img, $width, $height, false);
                    Images::create_webp($new_img, true);
                    $image = str_replace(DIR_WS_IMAGES, '', str_replace('\\', '/', $new_img));
                }
            } elseif (is_file(DIR_FS_CATALOG . $new_img)) {
                $image = str_replace(DIR_WS_IMAGES, '', str_replace('\\', '/', $new_img));
            }
        } elseif ($upload) {
            $img = str_replace('uploads' . DIRECTORY_SEPARATOR, '', $upload);
            $val = \backend\design\Uploads::move($img, $path);
            $image = str_replace(DIR_WS_IMAGES, '', str_replace('\\', '/', $val));
        }
        return $image;
    }
}