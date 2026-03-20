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
namespace common\classes;

use frontend\design\Info;
use yii\helpers\Console;
use yii\helpers\File_Helper;
use yii\helpers\Html;
/**
 * Product Images
 *
 * @property array $data
 */
class Images
{
    public const IMAGETYPES_CACHE_LIFETIME = 15;
    public const watermarkPrefix = ['top_left_', 'top_', 'top_right_', 'left_', '', 'right_', 'bottom_left_', 'bottom_', 'bottom_right_'];
    public static function get_fs_catalog_images_path()
    {
        if (defined('DIR_FS_CATALOG_IMAGES')) {
            return DIR_FS_CATALOG_IMAGES;
        }
        return DIR_FS_CATALOG . DIR_WS_IMAGES;
    }
    public static function get_ws_catalog_images_path($use_cdn = false)
    {
        if (defined('DIR_WS_CATALOG_IMAGES')) {
            return DIR_WS_CATALOG_IMAGES;
        }
        if ($use_cdn) {
            $platform_config = \Yii::$app->get('platform')->config();
            $cdn_server = $platform_config->get_images_cdn_url();
            if (!empty($cdn_server)) {
                return $cdn_server;
            }
        }
        return DIR_WS_IMAGES;
    }
    public function __construct()
    {
        $path = self::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR;
        $this->create_folder($path);
    }
    public static function check_attribute($products_images_id = 0, $products_options_id = 0, $products_options_values_id = 0)
    {
        $images_query = tep_db_query('select * from ' . TABLE_PRODUCTS_IMAGES_ATTRIBUTES . " where products_images_id = '" . (int) $products_images_id . "' and products_options_id = '" . (int) $products_options_id . "' and products_options_values_id = '" . (int) $products_options_values_id . "'");
        if (tep_db_num_rows($images_query) > 0) {
            return true;
        }
        return false;
    }
    public static function get_query($products_id, $limit = '')
    {
        static $_dummy_fetch = null;
        if (is_null($_dummy_fetch)) {
            $_dummy_fetch = tep_db_query('select * from ' . TABLE_PRODUCTS_IMAGES . " where products_id = '-1'");
        }
        $images_query = $_dummy_fetch;
        /** @var \common\extensions\InventoryImages\InventoryImages $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('InventoryImages')) {
            $images_query = $ext::get_query($products_id, $limit);
        }
        /** @var \common\extensions\AttributesImages\AttributesImages $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('AttributesImages')) {
            $images_query = $ext::get_query($images_query, $products_id, $limit);
        }
        /** @var \common\extensions\ProductImagesByPlatform\ProductImagesByPlatform $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('ProductImagesByPlatform')) {
            $images_query = $ext::get_query($images_query, $products_id, $limit);
        }
        if (tep_db_num_rows($images_query) == 0) {
            $images_query = tep_db_query('select * from ' . TABLE_PRODUCTS_IMAGES . " where image_status = 1 and products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "' order by default_image desc, sort_order " . $limit);
        }
        return $images_query;
    }
    public static function get_image_exists($products_id = 0, $type_name = 'Thumbnail', $language_id = 0, $image_id = 0)
    {
        $image_path = self::get_image($products_id, $type_name, $language_id, $image_id);
        if (empty($image_path)) {
            return false;
        }
    }
    public static function get_image_types($type_name = false, $all_types = false)
    {
        static $types = false;
        if (!is_array($types)) {
            $types = [];
            $image_types_query = tep_db_query('select * from ' . TABLE_IMAGE_TYPES . ($all_types ? ' where 1' : " where parent_id = '0'"));
            while ($image_types = tep_db_fetch_array($image_types_query)) {
                $image_types['folder_name'] = $image_types['image_types_x'] . 'x' . $image_types['image_types_y'];
                $types[] = $image_types;
            }
        }
        if ($type_name !== false) {
            foreach ($types as $type) {
                if (strtolower($type['image_types_name']) == strtolower($type_name)) {
                    return $type;
                }
            }
            return false;
        }
        return $types;
    }
    private static function image_description_fetch($product_id, $image_id, $language_id)
    {
        static $cache = [];
        if (!isset($cache[(int) $product_id])) {
            $cache = [(int) $product_id => []];
            $fetch_data_r = tep_db_query('SELECT pid.* ' . 'FROM ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' pid ' . 'INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' pi ON pid.products_images_id=pi.products_images_id ' . "WHERE pi.products_id='" . (int) $product_id . "'");
            if (tep_db_num_rows($fetch_data_r) > 0) {
                while ($data = tep_db_fetch_array($fetch_data_r)) {
                    $cache[(int) $product_id][(int) $data['products_images_id'] . '@' . (int) $data['language_id']] = $data;
                }
            }
        }
        $_key = (int) $image_id . '@' . (int) $language_id;
        return isset($cache[(int) $product_id][$_key]) ? $cache[(int) $product_id][$_key] : false;
    }
    public static function get_image_list($products_id = 0, $language_id = -1, $get_path = false, $in_webp = true)
    {
        if ($language_id < 0) {
            $language_id = (int) \Yii::$app->settings->get('languages_id');
        }
        $images = [];
        $images_query = self::get_query($products_id);
        while ($images_data = tep_db_fetch_array($images_query)) {
            $item = [];
            foreach (self::get_image_types() as $image_types) {
                $image = self::get_image_url($products_id, $image_types['image_types_name'], $language_id, $images_data['products_images_id'], $get_path, $in_webp);
                if (!empty($image)) {
                    $item[$image_types['image_types_name']] = ['url' => $image, 'type' => $image_types['image_types_name'], 'x' => $image_types['image_types_x'], 'y' => $image_types['image_types_y']];
                }
            }
            $images_tags = self::get_image_tags($products_id, $images_data['products_images_id'], $language_id);
            if (count($item) > 0) {
                $images[$images_data['products_images_id']] = ['image' => $item, 'alt' => $images_tags['alt_tag'], 'title' => $images_tags['title_tag'], 'default' => $images_data['default_image'], 'sort_order' => 0, 'link_video_id' => $images_tags['link_video_id']];
            }
        }
        return $images;
    }
    public static function get_image_tags($products_id, $image_id = 0, $language_id = -1)
    {
        if ($language_id < 0) {
            $language_id = (int) \Yii::$app->settings->get('languages_id');
        }
        $products = \Yii::$container->get('products');
        $result_tags = [];
        if ($image_id > 0) {
            $product_image = tep_db_fetch_array(tep_db_query('select pi.products_images_id, if(length(pid1.image_alt) > 0, pid1.image_alt, pid.image_alt) as image_alt, if(length(pid1.image_title) > 0, pid1.image_title, pid.image_title) as image_title, pid.link_video_id from ' . TABLE_PRODUCTS_IMAGES . ' pi, ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' pid left join ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " pid1 on pid.products_images_id = pid1.products_images_id and pid1.language_id = '" . (int) $language_id . "' where pi.products_id = '" . (int) $products_id . "' and pi.products_images_id = '" . (int) $image_id . "' and pi.products_images_id = pid.products_images_id and pid.language_id = '0'"));
        } else {
            $product_image = tep_db_fetch_array(tep_db_query('select pi.products_images_id, if(length(pid1.image_alt) > 0, pid1.image_alt, pid.image_alt) as image_alt, if(length(pid1.image_title) > 0, pid1.image_title, pid.image_title) as image_title, pid.link_video_id from ' . TABLE_PRODUCTS_IMAGES . ' pi, ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' pid left join ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " pid1 on pid.products_images_id = pid1.products_images_id and pid1.language_id = '" . (int) $language_id . "' where pi.products_id = '" . (int) $products_id . "' and pi.default_image = '1' and pi.products_images_id = pid.products_images_id and pid.language_id = '0'"));
        }
        $result_tags['alt_tag'] = isset($product_image['image_alt']) ? $product_image['image_alt'] : '';
        $result_tags['title_tag'] = isset($product_image['image_title']) ? $product_image['image_title'] : '';
        $result_tags['link_video_id'] = isset($product_image['link_video_id']) ? $product_image['link_video_id'] : '';
        static $_product_cached = [];
        $product_cache_key = (int) $products_id . '@' . (int) $language_id;
        if (!isset($_product_cached[$product_cache_key])) {
            if (count($_product_cached) > 20) {
                $_product_cached = [];
            }
            $_product_cached[$product_cache_key] = $products->get_product($products_id);
            if (!$_product_cached[$product_cache_key]) {
                $_product_cached[$product_cache_key] = tep_db_fetch_array(tep_db_query('select p.products_id, p.products_isbn, p.products_ean, p.products_asin, p.products_upc, p.manufacturers_id, if(length(pd1.products_name) > 0, pd1.products_name, pd.products_name) as products_name, if(length(pd1.products_image_alt_tag_mask) > 0, pd1.products_image_alt_tag_mask, pd.products_image_alt_tag_mask) as products_image_alt_tag_mask, if(length(pd1.products_image_title_tag_mask) > 0, pd1.products_image_title_tag_mask, pd.products_image_title_tag_mask) as products_image_title_tag_mask from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd.products_id = pd1.products_id and pd1.platform_id = '" . intval(\Yii::$app->get('platform')->config()->get_platform_to_description()) . "' and pd1.language_id = '" . (int) $language_id . "' where p.products_id = '" . (int) $products_id . "' and p.products_id = pd.products_id and pd.language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "' and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "'"));
            }
        }
        $product = $_product_cached[$product_cache_key];
        if (empty($result_tags['alt_tag']) && isset($product['products_image_alt_tag_mask'])) {
            $result_tags['alt_tag'] = $product['products_image_alt_tag_mask'];
        }
        if (empty($result_tags['title_tag']) && isset($product['products_image_title_tag_mask'])) {
            $result_tags['title_tag'] = $product['products_image_title_tag_mask'];
        }
        static $categories_info = [];
        if (empty($result_tags['alt_tag']) || empty($result_tags['title_tag'])) {
            $categories_array = array_reverse(explode('_', \common\helpers\Product::get_product_path($products_id)));
            foreach ($categories_array as $categories_id) {
                $key = (int) $categories_id . '@' . (int) $language_id;
                if (!isset($categories_info[$key])) {
                    if (count($categories_info) > 20) {
                        $categories_info = [];
                    }
                    $categories_info[$key] = tep_db_fetch_array(tep_db_query('select if(length(cd1.categories_name) > 0, cd1.categories_name, cd.categories_name) as categories_name, if(length(cd1.categories_image_alt_tag_mask) > 0, cd1.categories_image_alt_tag_mask, cd.categories_image_alt_tag_mask) as categories_image_alt_tag_mask, if(length(cd1.categories_image_title_tag_mask) > 0, cd1.categories_image_title_tag_mask, cd.categories_image_title_tag_mask) as categories_image_title_tag_mask from ' . TABLE_CATEGORIES_DESCRIPTION . ' cd left join ' . TABLE_CATEGORIES_DESCRIPTION . " cd1 on cd.categories_id = cd1.categories_id and cd1.affiliate_id = '0' and cd1.language_id = '" . (int) $language_id . "' where cd.categories_id = '" . (int) $categories_id . "' and cd.language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "' and cd.affiliate_id = '0'"));
                }
                $category = $categories_info[$key];
                if (empty($result_tags['alt_tag'])) {
                    $result_tags['alt_tag'] = $category['categories_image_alt_tag_mask'] ?? null;
                }
                if (empty($result_tags['title_tag'])) {
                    $result_tags['title_tag'] = $category['categories_image_title_tag_mask'] ?? null;
                }
                if (!empty($result_tags['alt_tag']) && !empty($result_tags['title_tag'])) {
                    break;
                }
            }
        }
        if (empty($result_tags['alt_tag'])) {
            $result_tags['alt_tag'] = defined('IMAGE_ALT_TAG_MASK_PRODUCT_INFO') && tep_not_null(IMAGE_ALT_TAG_MASK_PRODUCT_INFO) ? IMAGE_ALT_TAG_MASK_PRODUCT_INFO : $product['products_name'];
        }
        if (empty($result_tags['title_tag'])) {
            $result_tags['title_tag'] = defined('IMAGE_TITLE_TAG_MASK_PRODUCT_INFO') && tep_not_null(IMAGE_TITLE_TAG_MASK_PRODUCT_INFO) ? IMAGE_TITLE_TAG_MASK_PRODUCT_INFO : $product['products_name'];
        }
        if (strstr($result_tags['alt_tag'], '##CATEGORY_NAME##') || strstr($result_tags['title_tag'], '##CATEGORY_NAME##')) {
            $categories_array = array_reverse(explode('_', \common\helpers\Product::get_product_path($products_id)));
            $category_name = \common\helpers\Categories::get_categories_name($categories_array[0], $language_id);
            if (strstr($result_tags['alt_tag'], '##CATEGORY_NAME##')) {
                $result_tags['alt_tag'] = str_replace('##CATEGORY_NAME##', $category_name, $result_tags['alt_tag']);
            }
            if (strstr($result_tags['title_tag'], '##CATEGORY_NAME##')) {
                $result_tags['title_tag'] = str_replace('##CATEGORY_NAME##', $category_name, $result_tags['title_tag']);
            }
        }
        if (strstr($result_tags['alt_tag'], '##BRAND_NAME##') || strstr($result_tags['title_tag'], '##BRAND_NAME##')) {
            $brand_name = \common\helpers\Manufacturers::get_manufacturer_info('manufacturers_name', $product['manufacturers_id']);
            if (strstr($result_tags['alt_tag'], '##BRAND_NAME##')) {
                $result_tags['alt_tag'] = str_replace('##BRAND_NAME##', $brand_name, $result_tags['alt_tag']);
            }
            if (strstr($result_tags['title_tag'], '##BRAND_NAME##')) {
                $result_tags['title_tag'] = str_replace('##BRAND_NAME##', $brand_name, $result_tags['title_tag']);
            }
        }
        if (strstr($result_tags['alt_tag'], '##PRODUCT_NAME##')) {
            $result_tags['alt_tag'] = str_replace('##PRODUCT_NAME##', $product['products_name'], $result_tags['alt_tag']);
        }
        if (strstr($result_tags['title_tag'], '##PRODUCT_NAME##')) {
            $result_tags['title_tag'] = str_replace('##PRODUCT_NAME##', $product['products_name'], $result_tags['title_tag']);
        }
        if (strstr($result_tags['alt_tag'], '##EAN##')) {
            $result_tags['alt_tag'] = str_replace('##EAN##', $product['products_ean'], $result_tags['alt_tag']);
        }
        if (strstr($result_tags['title_tag'], '##EAN##')) {
            $result_tags['title_tag'] = str_replace('##EAN##', $product['products_ean'], $result_tags['title_tag']);
        }
        if (strstr($result_tags['alt_tag'], '##ASIN##')) {
            $result_tags['alt_tag'] = str_replace('##ASIN##', $product['products_asin'], $result_tags['alt_tag']);
        }
        if (strstr($result_tags['title_tag'], '##ASIN##')) {
            $result_tags['title_tag'] = str_replace('##ASIN##', $product['products_asin'], $result_tags['title_tag']);
        }
        if (strstr($result_tags['alt_tag'], '##ISBN##')) {
            $result_tags['alt_tag'] = str_replace('##ISBN##', $product['products_isbn'], $result_tags['alt_tag']);
        }
        if (strstr($result_tags['title_tag'], '##ISBN##')) {
            $result_tags['title_tag'] = str_replace('##ISBN##', $product['products_isbn'], $result_tags['title_tag']);
        }
        if (strstr($result_tags['alt_tag'], '##UPC##')) {
            $result_tags['alt_tag'] = str_replace('##UPC##', $product['products_upc'], $result_tags['alt_tag']);
        }
        if (strstr($result_tags['title_tag'], '##UPC##')) {
            $result_tags['title_tag'] = str_replace('##UPC##', $product['products_upc'], $result_tags['title_tag']);
        }
        $result_tags['alt_tag'] = strip_tags(str_replace(["\n", "\r", "\r\n", "\n\r"], ' ', $result_tags['alt_tag']));
        $result_tags['alt_tag'] = preg_replace('/##[A-Z_]+##/', '', $result_tags['alt_tag']);
        $result_tags['alt_tag'] = preg_replace('/\s{2,}/', ' ', $result_tags['alt_tag']);
        $result_tags['alt_tag'] = trim($result_tags['alt_tag']);
        $result_tags['title_tag'] = strip_tags(str_replace(["\n", "\r", "\r\n", "\n\r"], ' ', $result_tags['title_tag']));
        $result_tags['title_tag'] = preg_replace('/##[A-Z_]+##/', '', $result_tags['title_tag']);
        $result_tags['title_tag'] = preg_replace('/\s{2,}/', ' ', $result_tags['title_tag']);
        $result_tags['title_tag'] = trim($result_tags['title_tag']);
        return $result_tags;
    }
    /**
     *
     * @param int $productsId
     * @param string, array $typeName
     * @param int $languageId - if language id set to -1 then find by priority main->1->2->...
     * @param integer $imageId - if image id not set then use default image
     * @param integer $getPath - if need return url and server path
     * @return array or string
     */
    public static function get_image_url($products_id = 0, $type_name = 'Thumbnail', $language_id = -1, $image_id = 0, $get_path = false, $in_webp = true)
    {
        if (is_array($type_name)) {
            $image_types = $type_name;
            $na_image = false;
        } else {
            $image_types = self::get_image_types($type_name);
            if (Info::is_totally_admin()) {
                $na_image = 'images/na.png';
            } else {
                $na_image = Info::theme_setting('na_product', 'hide');
            }
        }
        if (!$image_types) {
            return $na_image;
        }
        if ($language_id < 0) {
            $language_id = (int) \Yii::$app->settings->get('languages_id');
        }
        if ($image_id == 0) {
            $image_id = self::get_image_id($products_id);
        }
        if (!$image_id) {
            return $na_image;
        }
        $products_id = \common\helpers\Inventory::get_prid($products_id);
        $images_description = static::image_description((int) $products_id, (int) $image_id, (int) $language_id);
        if ($images_description === false) {
            return $na_image;
        }
        if ($images_description['use_external_images'] && is_string($type_name)) {
            $get_external_image_r = tep_db_query('SELECT image_url FROM ' . TABLE_PRODUCTS_IMAGES_EXTERNAL_URL . ' ' . "WHERE products_images_id = '" . (int) $images_description['products_images_id'] . "' " . " AND language_id='" . (int) $images_description['language_id'] . "' " . " AND image_types_id='" . (int) $image_types['image_types_id'] . "' ");
            if (tep_db_num_rows($get_external_image_r) > 0) {
                $_external_image = tep_db_fetch_array($get_external_image_r);
                if ($_external_image['image_url']) {
                    return $_external_image['image_url'];
                }
            }
        }
        $language = '';
        if ($images_description['language_id']) {
            $language = \common\classes\language::get_code($images_description['language_id']);
        }
        $path = self::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR;
        $product_image_location = $path . $products_id . DIRECTORY_SEPARATOR . $image_id . DIRECTORY_SEPARATOR;
        $image_location = $product_image_location . $image_types['folder_name'] . DIRECTORY_SEPARATOR;
        if (!empty($language)) {
            $image_location .= $language . DIRECTORY_SEPARATOR;
        }
        $image_name = $images_description['file_name'];
        if (false && !empty($images_description['alt_file_name'])) {
            $image_name = $images_description['alt_file_name'];
        }
        if (!file_exists($image_location . $image_name) && file_exists($image_location . $images_description['hash_file_name'])) {
            $image_name = $images_description['hash_file_name'];
        }
        if (file_exists($image_location . $image_name)) {
            $target_image_info = getimagesize($image_location . $image_name);
            $platform_config = \Yii::$app->get('platform')->config();
            $public_filename_prefix = '';
            $product_ref = 'products/' . (int) $products_id . '/';
            if (!defined('SEO_IMAGE_URL_PARTS_NAME') && SEO_IMAGE_URL_PARTS_NAME == 'True') {
                $product_ref = \common\helpers\Product::get_seo_name($products_id, $images_description['language_id']) . '/';
                if ($product_ref == '/') {
                    $product_ref = $products_id . '/';
                }
            }
            $public_filename_prefix .= $product_ref;
            $public_file_name = $public_filename_prefix . $image_id . '/' . $image_types['folder_name'] . '/' . (!empty($language) ? $language . '/' : '') . rawurlencode($image_name);
            if ($images_description['no_watermark'] == 0 && self::use_water_mark($target_image_info[0], $target_image_info[1])) {
                $watermark_image = self::get_watermark_image($platform_config->get_id(), $target_image_info[0]);
                if (is_array($watermark_image)) {
                    $watermark_mtime = 0;
                    foreach ($watermark_image as $watermark_image_path) {
                        $watermark_mtime = max($watermark_mtime, filemtime($watermark_image_path));
                    }
                } else {
                    $watermark_mtime = 0;
                }
                $public_file_name = \common\helpers\Seo::make_slug($platform_config->const_value('STORE_NAME')) . '/' . $public_file_name;
                if (is_file(self::get_fs_catalog_images_path() . $public_file_name)) {
                    $watermarked_image_mtime_must_be = max(filemtime($image_location . $image_name), $watermark_mtime);
                    if (filemtime(self::get_fs_catalog_images_path() . $public_file_name) != $watermarked_image_mtime_must_be) {
                        @unlink(self::get_fs_catalog_images_path() . $public_file_name);
                    }
                }
            }
            if ($in_webp) {
                $public_file_name = self::get_webp($public_file_name);
            }
            $image_url = \common\helpers\Media::get_alias('@webCatalogImages/' . $public_file_name);
            if ($get_path) {
                return [$image_url, $image_location . $image_name];
            } else {
                return $image_url;
            }
        }
        return $na_image;
    }
    public static function image_description($products_id, $image_id, $language_id)
    {
        $images_description = static::image_description_fetch((int) $products_id, (int) $image_id, (int) $language_id);
        $images_description_default = static::image_description_fetch((int) $products_id, (int) $image_id, 0);
        if (is_array($images_description_default) && (!empty($images_description_default['file_name']) or $images_description_default['use_external_images'] != 0)) {
            if ($images_description == false) {
                $images_description = $images_description_default;
            } else {
                foreach ($images_description_default as $_key => $default_value) {
                    if ($_key == 'language_id' && (empty($images_description['file_name']) && empty($images_description['alt_file_name']))) {
                        $images_description[$_key] = $default_value;
                    } elseif (empty($images_description[$_key]) && !empty($default_value)) {
                        $images_description[$_key] = $default_value;
                    }
                }
            }
        }
        return $images_description;
    }
    public static function get_image_id($products_id = 0)
    {
        static $_images = [];
        $uprid = \common\helpers\Inventory::normalize_id($products_id);
        if (isset($_images[$uprid])) {
            return isset($_images[$uprid]['products_images_id']) ? $_images[$uprid]['products_images_id'] : 0;
        }
        $images_query = self::get_query($uprid, ' LIMIT 1');
        $images = tep_db_fetch_array($images_query);
        $_images[$uprid] = $images;
        return isset($images['products_images_id']) ? $images['products_images_id'] : 0;
    }
    public static function get_image($products_id = 0, $type_name = 'Thumbnail', $language_id = -1, $image_id = 0, $attributes = [], $lazy_load = false)
    {
        $url = self::get_image_url($products_id, $type_name, $language_id, $image_id);
        if (!$url) {
            return false;
        }
        if ($image_id == 0) {
            $image_id = self::get_image_id($products_id);
        }
        if ($image_id) {
            $images_tags = self::get_image_tags($products_id, $image_id, $language_id);
        } else {
            $images_tags = [];
        }
        $srcset_sizes = self::get_image_srcset_sizes($products_id, $type_name, $language_id, $image_id);
        if ($lazy_load) {
            if (is_array($attributes['class'])) {
                $attributes['class'][] = 'lazy';
            } else {
                $attributes['class'] .= ' lazy';
            }
            if ($srcset_sizes['srcset']) {
                $attributes['data-srcset'] = $srcset_sizes['srcset'];
            }
            if ($srcset_sizes['sizes']) {
                $attributes['data-sizes'] = $srcset_sizes['sizes'];
            }
            $attributes['data-src'] = $url;
            return \yii\helpers\Html::tag('img', '', $attributes);
        }
        return \yii\helpers\Html::img($url, array_merge(['alt' => $images_tags['alt_tag'] ?? '', 'title' => $images_tags['title_tag'] ?? '', 'srcset' => $srcset_sizes['srcset'] ?? '', 'sizes' => $srcset_sizes['sizes'] ?? ''], $attributes));
    }
    public static function get_image_srcset_sizes($products_id = 0, $type_name = 'Thumbnail', $language_id = -1, $image_id = 0)
    {
        static $_resolutions = [];
        if (isset($_resolutions[$type_name])) {
            $resolutions = $_resolutions[$type_name];
        } else {
            $resolutions = \common\models\Image_Types::find()->where(['image_types_name' => $type_name])->as_array()->cache(self::IMAGETYPES_CACHE_LIFETIME)->all();
            $_resolutions[$type_name] = $resolutions;
        }
        if (!$resolutions || count($resolutions) < 2) {
            return ['srcset' => '', 'sizes' => '', 'sources' => []];
        }
        $srcset = '';
        $sizes = '';
        $sources = [];
        foreach ($resolutions as $resolution) {
            $resolution['folder_name'] = $resolution['image_types_x'] . 'x' . $resolution['image_types_y'];
            list($image_url, $image_path) = self::get_image_url($products_id, $resolution, $language_id, $image_id, true);
            if (!$image_url) {
                continue;
            }
            $size = @get_image_size($image_path);
            if (!$size[0]) {
                continue;
            }
            if ($srcset) {
                $srcset .= ', ';
            }
            $srcset .= $image_url . ' ' . $size[0] . 'w';
            if (!$resolution['width_from'] && !$resolution['width_to']) {
                continue;
            }
            if ($sizes) {
                $sizes .= ', ';
            }
            $sizes .= '(';
            $media = '';
            if ($resolution['width_from']) {
                $sizes .= 'min-width: ' . $resolution['width_from'] . 'px';
                $media .= '(min-width: ' . $resolution['width_from'] . 'px)';
            }
            if ($resolution['width_from'] && $resolution['width_to']) {
                $sizes .= ' and ';
                $media .= ' and ';
            }
            if ($resolution['width_to']) {
                $sizes .= 'max-width: ' . $resolution['width_to'] . 'px';
                $media .= '(max-width: ' . $resolution['width_to'] . 'px)';
            }
            $sizes .= ') ' . $size[0] . 'px';
            $sources[] = ['srcset' => $image_url, 'media' => $media];
        }
        return ['srcset' => $srcset, 'sizes' => $sizes, 'sources' => $sources];
    }
    /**
     * @depricated
     * @param $params
     * @return string
     */
    private static function allocate_cache_key($params)
    {
        $platform_id = (int) $params['platform_id'];
        if (is_array($params['watermark_image'])) {
            $watermark_image = serialize($params['watermark_image']);
        } else {
            $watermark_image = '';
        }
        $key_data = ['platform_id' => $platform_id, 'image_size' => isset($params['image_size']) ? $params['image_size'] : '', 'watermark_image' => $watermark_image, 'watermark_mtime' => isset($params['watermark_mtime']) ? $params['watermark_mtime'] : ''];
        $params = array_diff_key($params, $key_data);
        ksort($params);
        $key_data['extra_params'] = count($params) > 0 ? base64_encode(serialize($params)) : '';
        $internal_key = md5(implode('/', $key_data));
        static $lookup = [];
        if (!isset($lookup[$internal_key])) {
            $get_external_key_r = tep_db_query('SELECT external_key ' . 'FROM ' . TABLE_IMAGE_CACHE_KEYS . ' ' . "WHERE internal_key='{$internal_key}' AND is_valid=1 AND platform_id='{$platform_id}'");
            if (tep_db_num_rows($get_external_key_r) > 0) {
                $_external_key = tep_db_fetch_array($get_external_key_r);
                $lookup[$internal_key] = $_external_key['external_key'];
            } else {
                do {
                    $external_key = strtoupper(uniqid());
                    $check_key = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_IMAGE_CACHE_KEYS . " WHERE external_key='" . $external_key . "' "));
                } while ($check_key['c'] == 1);
                $key_data['external_key'] = $external_key;
                $key_data['internal_key'] = $internal_key;
                tep_db_perform(TABLE_IMAGE_CACHE_KEYS, $key_data);
                $lookup[$internal_key] = $external_key;
            }
        }
        $external_key = $lookup[$internal_key];
        return $external_key . '/';
    }
    /**
     * @depricated
     * @param $watermark_name
     * @param int $platform_id
     */
    public static function cache_key_invalidate_by_watermark($watermark_name, $platform_id = 0)
    {
        if (!empty($watermark_name)) {
            tep_db_query('UPDATE ' . TABLE_IMAGE_CACHE_KEYS . ' ' . 'SET is_valid=0 ' . "WHERE watermark_image='" . tep_db_input($watermark_name) . "' " . ($platform_id > 0 ? "AND platform_id='" . (int) $platform_id . "' " : ''));
        }
    }
    /**
     * @depricated
     * @param $platform_id
     */
    public static function cache_key_invalidate_by_platform_id($platform_id)
    {
        if (!empty($watermark_name)) {
            tep_db_query('UPDATE ' . TABLE_IMAGE_CACHE_KEYS . " SET is_valid=0 WHERE platform_id='" . (int) $platform_id . "'");
        }
    }
    /**
     * @depricated
     * @param bool $deep_check
     */
    public static function cache_flush($deep_check = false)
    {
        if ($deep_check) {
            tep_db_query('UPDATE ' . TABLE_IMAGE_CACHE_KEYS . ' SET is_valid=0');
        }
        /*if ( $deep_check ) {
                  $get_valid_keys_r = tep_db_query(
                    "SELECT * ".
                    "FROM ".TABLE_IMAGE_CACHE_KEYS." ".
                    "WHERE is_valid=1"
                  );
                  if ( tep_db_num_rows($get_valid_keys_r)>0 ) {
                    while ($cache_data = tep_db_fetch_array($get_valid_keys_r)) {
        
                    }
                  }
                }*/
        $get_invalid_keys_r = tep_db_query('SELECT external_key FROM ' . TABLE_IMAGE_CACHE_KEYS . ' WHERE is_valid=0');
        if (tep_db_num_rows($get_invalid_keys_r) > 0) {
            while ($invalid_key = tep_db_fetch_array($get_invalid_keys_r)) {
                $flush_dir = self::get_fs_catalog_images_path() . 'cached/' . $invalid_key['external_key'];
                \yii\helpers\File_Helper::remove_directory($flush_dir);
                if (!is_dir($flush_dir)) {
                    tep_db_query('DELETE FROM ' . TABLE_IMAGE_CACHE_KEYS . " WHERE external_key='" . tep_db_input($invalid_key['external_key']) . "'");
                }
            }
        }
    }
    public static function get_type_from_file($file_name)
    {
        $extension = '';
        if (is_file($file_name) && $image_info = @getimagesize($file_name)) {
            switch ($image_info[2]) {
                case IMAGETYPE_GIF:
                    $extension = 'gif';
                    break;
                case IMAGETYPE_JPEG:
                    $extension = 'jpg';
                    break;
                case IMAGETYPE_PNG:
                    $extension = 'png';
                    break;
                case IMAGETYPE_BMP:
                    $extension = 'bmp';
                    break;
            }
        }
        return $extension;
    }
    // (file_exists(DIR_FS_CATALOG_IMAGES . $products['products_image']) ? '<span class="prodImgC">' . \common\helpers\Image::info_image($products['products_image'], $products['products_name'], 50, 50) . '</span>' : '<span class="cubic"></span>')
    public function create_images($products_id, $image_id, $hash_name, $image_name, $language = '')
    {
        $path = self::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR;
        $product_image_location = $path . $products_id . DIRECTORY_SEPARATOR;
        $this->create_folder($product_image_location);
        $product_image_location .= $image_id . DIRECTORY_SEPARATOR;
        $this->create_folder($product_image_location);
        $image_types_query = tep_db_query('select * from ' . TABLE_IMAGE_TYPES . ' order by image_types_id');
        while ($image_types = tep_db_fetch_array($image_types_query)) {
            $image_location = $product_image_location . $image_types['image_types_x'] . 'x' . $image_types['image_types_y'] . DIRECTORY_SEPARATOR;
            $this->create_folder($image_location);
            if (!empty($language)) {
                $image_location .= $language . DIRECTORY_SEPARATOR;
                $this->create_folder($image_location);
            }
            $this->create_image($product_image_location . $hash_name, $image_location . $image_name, $image_types['image_types_x'], $image_types['image_types_y'], PRODUCT_IMAGE_FIELD_COLOR);
        }
    }
    /**
     * Create image
     * @param string $path
     * @param integer $width
     * @param integer $height
     * @param fields_color
     */
    public function create_image($source_image, $destination_image, $width, $height, $fields_color = false)
    {
        return self::tep_image_resize($source_image, $destination_image, $width, $height, $fields_color);
    }
    /**
     * Create folder
     * @param string $path
     */
    public function create_folder($path)
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            @chmod($path, 0777);
        }
    }
    public static function tep_image_resize($image, $t_location, $thumbnail_width, $thumbnail_height, $fields_color = false)
    {
        if (!$thumbnail_width || !$thumbnail_height) {
            return false;
        }
        $image = str_replace('/./', '/', str_replace('//', '/', $image));
        $t_location = str_replace('/./', '/', str_replace('//', '/', $t_location));
        $size = @get_image_size($image);
        if ($thumbnail_width >= $size[0] && $thumbnail_height >= $size[1]) {
            if ($image != $t_location) {
                @copy($image, $t_location);
                @chmod($t_location, 0666);
            }
            return true;
        }
        if (IMAGE_RESIZE != 'GD' && IMAGE_RESIZE != 'ImageMagick') {
            return false;
        }
        if (IMAGE_RESIZE == 'ImageMagick') {
            if (is_executable(CONVERT_UTILITY)) {
                @\common\helpers\Php::exec(CONVERT_UTILITY . ' -thumbnail ' . $thumbnail_width . 'x' . $thumbnail_height . ' ' . $image . ' ' . $t_location);
                @chmod($t_location, 0666);
                return true;
            }
            return false;
        } elseif (IMAGE_RESIZE == 'GD') {
            if (function_exists('gd_info')) {
                $scale = @min($thumbnail_width / $size[0], $thumbnail_height / $size[1]);
                $x = $size[0] * $scale;
                $y = $size[1] * $scale;
                if ($fields_color) {
                    $new_width = $thumbnail_width;
                    $new_height = $thumbnail_height;
                    if ($thumbnail_width - $x < $thumbnail_height - $y) {
                        $new_top = ($thumbnail_height - $y) / 2;
                        $new_left = 0;
                    } else {
                        $new_top = 0;
                        $new_left = ($thumbnail_width - $x) / 2;
                    }
                    $fields_color = str_replace(' ', '', $fields_color);
                    if (preg_match("/^rgb\\(([0-9]{1,3})\\,([0-9]{1,3})\\,([0-9]{1,3})\\)\$/i", $fields_color, $matches)) {
                        $bg_color_r = $matches[1];
                        $bg_color_g = $matches[2];
                        $bg_color_b = $matches[3];
                    } elseif (preg_match("/^rgba\\(([0-9]{1,3})\\,([0-9]{1,3})\\,([0-9]{1,3}),([0-9\\.]{1,5})\\)\$/i", $fields_color, $matches)) {
                        $bg_color_r = $matches[1];
                        $bg_color_g = $matches[2];
                        $bg_color_b = $matches[3];
                    } elseif (preg_match("/^\\#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})\$/i", $fields_color, $matches)) {
                        $bg_color_r = hexdec($matches[1]);
                        $bg_color_g = hexdec($matches[2]);
                        $bg_color_b = hexdec($matches[3]);
                    } elseif (preg_match("/^\\#([0-9a-f])([0-9a-f])([0-9a-f])\$/i", $fields_color, $matches)) {
                        $bg_color_r = hexdec($matches[1] . $matches[1]);
                        $bg_color_g = hexdec($matches[2] . $matches[2]);
                        $bg_color_b = hexdec($matches[3] . $matches[3]);
                    } else {
                        $bg_color_r = 255;
                        $bg_color_g = 255;
                        $bg_color_b = 255;
                    }
                } else {
                    $new_width = $x;
                    $new_height = $y;
                    $new_top = 0;
                    $new_left = 0;
                    $bg_color_r = 255;
                    $bg_color_g = 255;
                    $bg_color_b = 255;
                }
                $bg_color_a = 0;
                switch ($size[2]) {
                    case 18:
                        $im = @imagecreatefromwebp($image);
                        break;
                    case 1:
                        // GIF
                        $im = @image_create_from_gif($image);
                        break;
                    case 3:
                        // PNG
                        $im = @image_create_from_png($image);
                        if ($im) {
                            if (function_exists('imageAntiAlias')) {
                                @image_anti_alias($im, true);
                            }
                            @image_alpha_blending($im, true);
                            @image_save_alpha($im, true);
                        }
                        break;
                    case 2:
                        // JPEG
                        $im = @image_create_from_jpeg($image);
                        break;
                    case 8:
                        // webp
                        $im = @imagecreatefromwebp($image);
                        break;
                    default:
                        return false;
                }
                if (!$im) {
                    return false;
                }
                if (function_exists('exif_read_data') && $size[2] == 2) {
                    $exif = @exif_read_data($image);
                    if (!empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 8:
                                $im = imagerotate($im, 90, 0);
                                break;
                            case 3:
                                $im = imagerotate($im, 180, 0);
                                break;
                            case 6:
                                $im = imagerotate($im, -90, 0);
                                break;
                        }
                    }
                }
                $im_pic = 0;
                if (function_exists('ImageCreateTrueColor')) {
                    $im_pic = @image_create_true_color($new_width, $new_height);
                }
                if ($im_pic == 0) {
                    $im_pic = @image_create($new_width, $new_height);
                }
                if ($im_pic != 0) {
                    @image_interlace($im_pic, 1);
                    if (function_exists('imageAntiAlias')) {
                        @image_anti_alias($im_pic, true);
                    }
                    @imagealphablending($im_pic, false);
                    @imagesavealpha($im_pic, true);
                    $transparent = @imagecolorallocatealpha($im_pic, $bg_color_r, $bg_color_g, $bg_color_b, $bg_color_a);
                    for ($i = 0; $i < $new_width; $i++) {
                        for ($j = 0; $j < $new_height; $j++) {
                            @image_set_pixel($im_pic, $i, $j, $transparent);
                        }
                    }
                    if (function_exists('ImageCopyResampled')) {
                        $resized = @image_copy_resampled($im_pic, $im, $new_left, $new_top, 0, 0, $x, $y, $size[0], $size[1]);
                    }
                    if (!$resized) {
                        @image_copy_resized($im_pic, $im, $new_left, $new_top, 0, 0, $x, $y, $size[0], $size[1]);
                    }
                } else {
                    return false;
                }
                if ($size[2] == 3) {
                    @image_png($im_pic, $t_location, 9);
                } else {
                    @image_jpeg($im_pic, $t_location, 85);
                }
                if (is_file($t_location)) {
                    chmod($t_location, 0666);
                    return true;
                }
            }
        }
        return false;
    }
    public static function get_platform_watermarks($platform_id = false)
    {
        if (DEMO_STORE == 'true') {
            return ['watermark300' => 'demo300.png', 'watermark170' => 'demo170.png', 'watermark30' => 'demo30.png'];
        }
        static $cached = [];
        $platform_id = (int) $platform_id > 0 ? (int) $platform_id : (int) PLATFORM_ID;
        if (!isset($cached[$platform_id])) {
            $cached[$platform_id] = false;
            $check_watermark_query = tep_db_query('SELECT * FROM ' . TABLE_PLATFORMS_WATERMARK . " WHERE status=1 AND platform_id='{$platform_id}'");
            if (tep_db_num_rows($check_watermark_query) > 0) {
                $cached[$platform_id] = tep_db_fetch_array($check_watermark_query);
            }
        }
        return $cached[$platform_id];
    }
    public static function get_watermark_image($platform_id, $base_width = 0)
    {
        $watermark_data = self::get_platform_watermarks(PLATFORM_ID);
        if ($watermark_data === false) {
            return false;
        }
        if ($base_width > 299) {
            $watermark_name = 'watermark300';
        } elseif ($base_width > 169) {
            $watermark_name = 'watermark170';
        } else {
            $watermark_name = 'watermark30';
        }
        $watermark_filenames = [];
        foreach (self::watermarkPrefix as $prefix) {
            if (isset($watermark_data[$prefix . $watermark_name]) && !empty($watermark_data[$prefix . $watermark_name])) {
                $watermark_filename = self::get_fs_catalog_images_path() . 'stamp' . DIRECTORY_SEPARATOR . $watermark_data[$prefix . $watermark_name];
                if (is_file($watermark_filename)) {
                    $watermark_filenames[$prefix] = $watermark_filename;
                }
            }
        }
        if (count($watermark_filenames) > 0) {
            return $watermark_filenames;
        }
        return false;
    }
    public static function use_water_mark($base_width = 0, $base_height = 0)
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        // {{
        if (method_exists(\Yii::$app->request, 'get') and \Yii::$app->request->get('nowatermark') == 1) {
            return false;
        }
        // }}
        if (!defined('PLATFORM_ID')) {
            return false;
        }
        if (DEMO_STORE == 'true') {
            return true;
        }
        $watermark_image = self::get_watermark_image(PLATFORM_ID, $base_width);
        if ($watermark_image === false) {
            return false;
        }
        if ($customer_groups_id > 0) {
            static $group_wm_status = [];
            if (!isset($group_wm_status[(int) $customer_groups_id])) {
                $group_wm_status[(int) $customer_groups_id] = true;
                $groups_check = tep_db_fetch_array(tep_db_query('select count(*) as wm_status from ' . TABLE_GROUPS . " where groups_id = '" . (int) $customer_groups_id . "' AND disable_watermark=1"));
                if ($groups_check['wm_status'] > 0) {
                    $group_wm_status[(int) $customer_groups_id] = false;
                }
            }
            if (!$group_wm_status[(int) $customer_groups_id]) {
                return false;
            }
        }
        /*if (CONFIG_IMAGE_WATERMARK != 'true') {
              return false;
          }*/
        return true;
    }
    public static function apply_watermark($source_image, $watermark_image, $output_file = null)
    {
        $size = @get_image_size($source_image);
        $output_as = 'png';
        switch ($size[2]) {
            case 1:
                // GIF
                $im = @image_create_from_gif($source_image);
                break;
            case 3:
                // PNG
                $im = @image_create_from_png($source_image);
                if ($im) {
                    if (function_exists('imageAntiAlias')) {
                        @image_anti_alias($im, true);
                    }
                    @image_alpha_blending($im, true);
                    @image_save_alpha($im, true);
                }
                break;
            case 2:
                // JPEG
                $im = @image_create_from_jpeg($source_image);
                $output_as = 'jpg';
                break;
            default:
                return false;
        }
        if (is_array($watermark_image)) {
            foreach ($watermark_image as $watermark_position => $watermark_image) {
                $stamp = @imagecreatefrompng($watermark_image);
                if ($stamp) {
                    switch ($watermark_position) {
                        case 'top_left_':
                            imagecopy($im, $stamp, 0, 0, 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'top_':
                            imagecopy($im, $stamp, (imagesx($im) - imagesx($stamp)) / 2, 0, 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'top_right_':
                            imagecopy($im, $stamp, imagesx($im) - imagesx($stamp), 0, 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'left_':
                            imagecopy($im, $stamp, 0, (imagesy($im) - imagesy($stamp)) / 2, 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case '':
                            imagecopy($im, $stamp, (imagesx($im) - imagesx($stamp)) / 2, (imagesy($im) - imagesy($stamp)) / 2, 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'right_':
                            imagecopy($im, $stamp, imagesx($im) - imagesx($stamp), (imagesy($im) - imagesy($stamp)) / 2, 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'bottom_left_':
                            imagecopy($im, $stamp, 0, imagesy($im) - imagesy($stamp), 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'bottom_':
                            imagecopy($im, $stamp, (imagesx($im) - imagesx($stamp)) / 2, imagesy($im) - imagesy($stamp), 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        case 'bottom_right_':
                            imagecopy($im, $stamp, imagesx($im) - imagesx($stamp), imagesy($im) - imagesy($stamp), 0, 0, imagesx($stamp), imagesy($stamp));
                            break;
                        default:
                            break;
                    }
                }
            }
        } elseif (!empty($watermark_image)) {
            $stamp = @imagecreatefrompng($watermark_image);
            if ($stamp) {
                imagecopy($im, $stamp, (imagesx($im) - imagesx($stamp)) / 2, (imagesy($im) - imagesy($stamp)) / 2, 0, 0, imagesx($stamp), imagesy($stamp));
                //imagecopymerge($im, $stamp, (imagesx($im) - imagesx($stamp))/2, (imagesy($im) - imagesy($stamp))/2, 0, 0, imagesx($stamp), imagesy($stamp), 10);
            }
        }
        if (is_null($output_file)) {
            header('Content-type: ' . $size['mime']);
            //image/png
        }
        if ($output_file === 'string') {
            ob_start();
            if ($output_as == 'jpg') {
                imagejpeg($im, null, 85);
            } else {
                imagepng($im, null, 9);
            }
            imagedestroy($im);
            return ob_get_clean();
        } else {
            if ($output_as == 'jpg') {
                imagejpeg($im, $output_file, 85);
            } else {
                imagepng($im, $output_file, 9);
            }
            imagedestroy($im);
        }
    }
    public static function water_mark($image = '')
    {
        $image = str_replace('/./', '/', str_replace('//', '/', $image));
        $image = DIR_FS_CATALOG . $image;
        if (!file_exists($image)) {
            return false;
        }
        $size = @get_image_size($image);
        $watermark_image = false;
        if (self::use_water_mark($size[0])) {
            $watermark_image = self::get_watermark_image(PLATFORM_ID, $size[0]);
        }
        self::apply_watermark($image, $watermark_image, 'direct');
        die;
    }
    public static function encode_image_name($image_file_name)
    {
        $encode_chars = ['%' => '%25', '/' => '%2F', '\\' => '%5C', '&' => '%26', '+' => '%2B', '#' => '%23', ':' => '%3A', '>' => '%3E', '<' => '%3C', '"' => '%22'];
        $image_file_name = str_replace(array_keys($encode_chars), array_values($encode_chars), $image_file_name);
        return $image_file_name;
    }
    /**
     * Create right names and resized copies from original file
     *
     * @param $productsId
     * @param $imageId
     */
    public static function normalize_image_files($products_id, $image_id)
    {
        if (empty($products_id) || empty($image_id)) {
            return;
        }
        $count = 0;
        $images_directory = self::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR . (int) $products_id . DIRECTORY_SEPARATOR . (int) $image_id . DIRECTORY_SEPARATOR;
        $registered_directory_images = [];
        $get_images_r = tep_db_query('SELECT pi_d.products_images_id, pi_d.language_id, ' . '  pi_d.file_name, pi_d.use_origin_image_name, ' . '  pi_d.hash_file_name, pi_d.alt_file_name, pi_d.orig_file_name, ' . '  p.products_model, pd.products_seo_page_name ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' pi ON pi.products_id=p.products_id ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' pi_d ON pi.products_images_id=pi_d.products_images_id ' . '  LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id=pd.products_id AND pd.language_id=IF(pi_d.language_id=0,' . \common\classes\language::get_id(DEFAULT_LANGUAGE) . ",pi_d.language_id) and pd.platform_id='" . intval(\common\classes\platform::default_id()) . "'" . "WHERE p.products_id='" . (int) $products_id . "' AND pi.products_images_id='" . (int) $image_id . "' " . "  AND (pi_d.hash_file_name!='' OR pi_d.alt_file_name!='') " . 'ORDER BY pi_d.language_id ');
        if (tep_db_num_rows($get_images_r) > 0) {
            $main_image = false;
            // 0 language_id
            while ($image_data = tep_db_fetch_array($get_images_r)) {
                if (!empty($image_data['hash_file_name']) && is_file($images_directory . $image_data['hash_file_name'])) {
                    $registered_directory_images[] = $image_data['hash_file_name'];
                    foreach (self::get_image_types(false, true) as $image_type) {
                        $image_filename_resized = $images_directory . $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_data['hash_file_name'];
                        $registered_directory_images[] = $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_data['hash_file_name'];
                        if (!is_file($image_filename_resized)) {
                            try {
                                File_Helper::create_directory(dirname($image_filename_resized), 0777);
                            } catch (\Exception $ex) {
                            }
                            self::tep_image_resize($images_directory . $image_data['hash_file_name'], $image_filename_resized, $image_type['image_types_x'], $image_type['image_types_y'], PRODUCT_IMAGE_FIELD_COLOR);
                        }
                    }
                }
                if ($image_data['language_id'] == 0 && $main_image === false) {
                    $main_image = $image_data;
                }
                $source_file = !empty($image_data['hash_file_name']) ? $image_data['hash_file_name'] : $main_image['hash_file_name'];
                if (empty($source_file) || !is_file($images_directory . $source_file)) {
                    continue;
                }
                $image_extension = self::get_type_from_file($images_directory . $source_file);
                /* filename?
                   if alternative file name existent - use it:
                   else if SKU Existent - use it:
                   else if SEO name Existent - use it
                   else use Origin image name
                   */
                $image_file_name = $image_data['orig_file_name'];
                if (!$image_data['use_origin_image_name']) {
                    if (!empty($image_data['alt_file_name'])) {
                        $image_file_name = $image_data['alt_file_name'];
                    } elseif (!empty($image_data['products_model'])) {
                        $image_file_name = $image_data['products_model'] . (empty($image_extension) ? '' : '.' . $image_extension);
                    } elseif (!empty($image_data['products_seo_page_name'])) {
                        $image_file_name = $image_data['products_seo_page_name'] . (empty($image_extension) ? '' : '.' . $image_extension);
                    }
                }
                $image_file_name = self::encode_image_name($image_file_name);
                if ($image_data['file_name'] != $image_file_name) {
                    tep_db_query('UPDATE ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' ' . "SET file_name='" . tep_db_input($image_file_name) . "' " . "WHERE products_images_id='" . (int) $image_data['products_images_id'] . "' AND language_id='" . $image_data['language_id'] . "' ");
                } else {
                    $image_file_name = $image_data['file_name'];
                }
                if ($image_data['language_id']) {
                    $image_file_name = \common\classes\language::get_code($image_data['language_id']) . DIRECTORY_SEPARATOR . $image_file_name;
                }
                if (!empty($source_file)) {
                    foreach (self::get_image_types(false, true) as $image_type) {
                        $public_filename = $images_directory . $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_file_name;
                        $registered_directory_images[] = $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_file_name;
                        $pos = strripos($image_file_name, '.');
                        $file_name = substr($image_file_name, 0, $pos);
                        $registered_directory_images[] = $image_type['folder_name'] . DIRECTORY_SEPARATOR . $file_name . '.webp';
                        if (is_link($public_filename)) {
                            $existing_link_point_to = readlink($public_filename);
                            if (basename($existing_link_point_to) != $source_file) {
                                unlink($public_filename);
                                \common\helpers\System::symlink($images_directory . $image_type['folder_name'] . DIRECTORY_SEPARATOR . $source_file, $public_filename);
                            }
                        } elseif (is_file($public_filename)) {
                        } else {
                            \common\helpers\System::symlink($images_directory . $image_type['folder_name'] . DIRECTORY_SEPARATOR . $source_file, $public_filename);
                        }
                    }
                }
                $img = false;
                foreach (self::get_image_types(false, true) as $image_type) {
                    $im_name = 'products' . DIRECTORY_SEPARATOR . (int) $products_id . DIRECTORY_SEPARATOR . (int) $image_id . DIRECTORY_SEPARATOR . $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_file_name;
                    $webp = self::create_webp($im_name);
                    if ($webp) {
                        $img = true;
                    }
                }
                if ($img) {
                    $count++;
                }
            }
        }
        self::clean_product_image_directory($products_id, $image_id, $registered_directory_images);
        return $count;
    }
    /**
     *
     * @param $productsId
     * @param $imageId
     * @param bool $registeredDirectoryImages
     * @return array
     */
    public static function clean_product_image_directory($products_id, $image_id, $registered_directory_images = false)
    {
        $removed_files = [];
        $images_directory = self::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR . (int) $products_id . DIRECTORY_SEPARATOR . (int) $image_id . DIRECTORY_SEPARATOR;
        if (!is_dir($images_directory)) {
            return $removed_files;
        }
        if (!is_array($registered_directory_images)) {
            $registered_directory_images = [];
            $get_images_r = tep_db_query('SELECT pi_d.products_images_id, pi_d.language_id, ' . '  pi_d.file_name, pi_d.use_origin_image_name, ' . '  pi_d.hash_file_name, pi_d.alt_file_name, pi_d.orig_file_name, ' . '  p.products_model, pd.products_seo_page_name ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' pi ON pi.products_id=p.products_id ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' pi_d ON pi.products_images_id=pi_d.products_images_id ' . '  LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ON p.products_id=pd.products_id AND pd.language_id=IF(pi_d.language_id=0,' . \common\classes\language::get_id(DEFAULT_LANGUAGE) . ",pi_d.language_id) and platform_id='" . intval(\common\classes\platform::default_id()) . "' " . "WHERE p.products_id='" . (int) $products_id . "' AND pi.products_images_id='" . (int) $image_id . "' " . "  AND (pi_d.hash_file_name!='' OR pi_d.alt_file_name!='') " . 'ORDER BY pi_d.language_id ');
            if (tep_db_num_rows($get_images_r) > 0) {
                while ($image_data = tep_db_fetch_array($get_images_r)) {
                    $registered_directory_images[] = $image_data['hash_file_name'];
                    foreach (self::get_image_types(false, true) as $image_type) {
                        $image_file_name = $image_data['file_name'];
                        if ($image_data['language_id']) {
                            $image_file_name = \common\classes\language::get_code($image_data['language_id']) . DIRECTORY_SEPARATOR . $image_file_name;
                        }
                        $registered_directory_images[] = $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_file_name;
                        $registered_directory_images[] = $image_type['folder_name'] . DIRECTORY_SEPARATOR . $image_data['hash_file_name'];
                        $pos = strripos($image_file_name, '.');
                        $file_name = substr($image_file_name, 0, $pos);
                        $registered_directory_images[] = $image_type['folder_name'] . DIRECTORY_SEPARATOR . $file_name . '.webp';
                    }
                }
            }
        }
        $registered_directory_images = array_flip($registered_directory_images);
        foreach (File_Helper::find_files($images_directory) as $file_in_dir) {
            $check_file = ltrim(substr($file_in_dir, strlen($images_directory)), '/');
            if (!isset($registered_directory_images[$check_file])) {
                $removed_files[] = $file_in_dir;
                @unlink($file_in_dir);
            }
        }
        return $removed_files;
    }
    public static function remove_product_images($product_id)
    {
        $product_images_dir = self::get_fs_catalog_images_path() . 'products/' . (int) $product_id;
        if (!empty($product_id) && is_dir($product_images_dir)) {
            try {
                File_Helper::remove_directory($product_images_dir);
            } catch (\Exception $ex) {
            }
        }
        $schema_check = \Yii::$app->get_db()->schema->get_table_schema(TABLE_PRODUCTS_IMAGES_EXTERNAL_URL);
        if ($schema_check) {
            tep_db_query('DELETE image_depend FROM ' . TABLE_PRODUCTS_IMAGES_EXTERNAL_URL . ' image_depend ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' image_main ON image_main.products_images_id=image_depend.products_images_id ' . "WHERE image_main.products_id='" . (int) $product_id . "'");
        }
        tep_db_query('DELETE image_depend FROM ' . TABLE_PRODUCTS_IMAGES_ATTRIBUTES . ' image_depend ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' image_main ON image_main.products_images_id=image_depend.products_images_id ' . "WHERE image_main.products_id='" . (int) $product_id . "'");
        tep_db_query('DELETE image_depend FROM ' . TABLE_PRODUCTS_IMAGES_INVENTORY . ' image_depend ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' image_main ON image_main.products_images_id=image_depend.products_images_id ' . "WHERE image_main.products_id='" . (int) $product_id . "'");
        tep_db_query('DELETE image_depend FROM ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . ' image_depend ' . '  INNER JOIN ' . TABLE_PRODUCTS_IMAGES . ' image_main ON image_main.products_images_id=image_depend.products_images_id ' . "WHERE image_main.products_id='" . (int) $product_id . "'");
        tep_db_query('DELETE FROM ' . TABLE_PRODUCTS_IMAGES . ' ' . "WHERE products_id='" . (int) $product_id . "'");
        self::clean_product_image_reference($product_id);
    }
    public static function remove_product_image($product_id, $image_id)
    {
        $product_images_dir = self::get_fs_catalog_images_path() . 'products/' . (int) $product_id . '/' . (int) $image_id;
        try {
            File_Helper::remove_directory($product_images_dir);
        } catch (\Exception $ex) {
        }
        $check_remove_default = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PRODUCTS_IMAGES . ' ' . "WHERE products_images_id = '" . (int) $image_id . "' AND default_image=1 "));
        if ($check_remove_default['c']) {
            tep_db_query('UPDATE ' . TABLE_PRODUCTS_IMAGES . ' ' . 'SET default_image=1 ' . "WHERE products_id='" . (int) $product_id . "' AND products_images_id != '" . (int) $image_id . "' " . 'ORDER BY sort_order, products_images_id ' . 'LIMIT 1');
        }
        tep_db_query('delete from ' . TABLE_PRODUCTS_IMAGES_EXTERNAL_URL . " where products_images_id = '" . (int) $image_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " where products_images_id = '" . (int) $image_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_IMAGES . " where products_images_id = '" . (int) $image_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_IMAGES_ATTRIBUTES . " where products_images_id = '" . (int) $image_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_IMAGES_INVENTORY . " where products_images_id = '" . (int) $image_id . "'");
        self::clean_product_image_reference($product_id, $image_id);
    }
    public static function remove_missing_attributes_link()
    {
        \Yii::$app->get_db()->create_command('DELETE pia FROM ' . TABLE_PRODUCTS_IMAGES_ATTRIBUTES . ' pia ' . '  LEFT JOIN ' . TABLE_PRODUCTS_IMAGES . ' pi ON pi.products_images_id=pia.products_images_id ' . '  LEFT JOIN ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa on pa.products_id=pi.products_id AND pa.options_id=pia.products_options_id AND pa.options_values_id = pia.products_options_values_id ' . 'WHERE pa.products_attributes_id IS NULL')->execute();
    }
    public static function collect_garbage()
    {
        $echo_messages = \Yii::$app instanceof \yii\console\Application;
        $product_images_dir = self::get_fs_catalog_images_path() . 'products';
        $handle = opendir($product_images_dir);
        if ($handle) {
            while (($product_directory = readdir($handle)) !== false) {
                if (!is_numeric($product_directory) || intval($product_directory) != $product_directory) {
                    continue;
                }
                $path = $product_images_dir . DIRECTORY_SEPARATOR . $product_directory;
                $remove_directories = [];
                $get_product_images_ids_r = tep_db_query('SELECT p.products_id, pi.products_images_id ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . '  LEFT JOIN ' . TABLE_PRODUCTS_IMAGES . ' pi ON p.products_id=pi.products_id ' . "WHERE p.products_id='" . (int) $product_directory . "'");
                if (tep_db_num_rows($get_product_images_ids_r) > 0) {
                    $current_directories = [];
                    $sub_dir_handle = opendir($path);
                    if (!$sub_dir_handle) {
                        continue;
                    }
                    while (($product_image_directory = readdir($sub_dir_handle)) !== false) {
                        if (!is_numeric($product_image_directory) || intval($product_image_directory) != $product_image_directory) {
                            continue;
                        }
                        $current_directories[(int) $product_image_directory] = $path . DIRECTORY_SEPARATOR . $product_image_directory;
                    }
                    closedir($sub_dir_handle);
                    while ($_product_images_id = tep_db_fetch_array($get_product_images_ids_r)) {
                        if (isset($current_directories[$_product_images_id['products_images_id']])) {
                            unset($current_directories[$_product_images_id['products_images_id']]);
                        }
                    }
                    $remove_directories = array_merge($remove_directories, array_values($current_directories));
                } else {
                    // product not found - remove all
                    $remove_directories[] = $path;
                }
                if (count($remove_directories) > 0) {
                    foreach ($remove_directories as $remove_directory_path) {
                        try {
                            File_Helper::remove_directory($remove_directory_path);
                        } catch (\Exception $ex) {
                        }
                        if ($echo_messages) {
                            echo ' [' . (is_file($remove_directory_path) ? Console::ansi_format('FAIL', [Console::FG_RED]) : Console::ansi_format('OK', [Console::FG_GREEN])) . "] {$remove_directory_path}\n";
                        }
                    }
                }
            }
            closedir($handle);
        }
        $page_size = 1000;
        $page = 0;
        do {
            $page++;
            $get_images_page_r = tep_db_query('SELECT products_id, products_images_id ' . 'FROM ' . TABLE_PRODUCTS_IMAGES . ' ' . 'ORDER BY products_id, products_images_id ' . 'LIMIT ' . $page_size * -($page - 1) . ",{$page_size}");
            if (tep_db_num_rows($get_images_page_r) > 0) {
                while ($image = tep_db_fetch_array($get_images_page_r)) {
                    $removed_list = self::clean_product_image_directory($image['products_id'], $image['products_images_id']);
                }
            } else {
                break;
            }
        } while (true);
    }
    protected static function remove_reference_filename($filename)
    {
        if (empty($filename)) {
            return;
        }
        $remove_filename = \common\classes\Images::get_fs_catalog_images_path() . $filename;
        if (is_file($remove_filename) || is_link($remove_filename)) {
            unlink($remove_filename);
            clearstatcache();
        }
        $check_empty_directory = dirname($filename);
        if ($check_empty_directory != '.' && $check_empty_directory != 'products') {
            while ($check_empty_directory) {
                $remove_directory = \common\classes\Images::get_fs_catalog_images_path() . $check_empty_directory;
                if (is_dir($remove_directory)) {
                    $files_in_dir = File_Helper::find_files($remove_directory);
                    if (count($files_in_dir) > 0) {
                        break;
                    } else {
                        File_Helper::remove_directory($remove_directory);
                    }
                }
                $check_empty_directory = dirname($check_empty_directory);
                if ($check_empty_directory == '.' || $check_empty_directory == 'products') {
                    break;
                }
            }
        }
    }
    public static function clean_image_reference()
    {
        $is_console = \Yii::$app instanceof \yii\console\Application;
        $images_count = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS total FROM ' . TABLE_IMAGE_COPY_REFERENCE));
        if ($images_count['total'] == 0) {
            return;
        }
        if ($is_console) {
            Console::start_progress(0, $images_count['total']);
        }
        $processed_count = 0;
        $page_size = 5;
        $page = 0;
        do {
            $page++;
            tep_db_query('UPDATE ' . TABLE_IMAGE_COPY_REFERENCE . ' ' . 'SET clean_flag=1 ' . 'ORDER BY date_added ' . "LIMIT {$page_size}");
            $get_images_page_r = tep_db_query('SELECT * ' . 'FROM ' . TABLE_IMAGE_COPY_REFERENCE . ' ' . 'WHERE clean_flag=1 ');
            if (tep_db_num_rows($get_images_page_r) > 0) {
                while ($image = tep_db_fetch_array($get_images_page_r)) {
                    self::remove_reference_filename($image['filename']);
                    if ($is_console) {
                        Console::update_progress(++$processed_count, $images_count['total']);
                    }
                }
                tep_db_query('DELETE FROM ' . TABLE_IMAGE_COPY_REFERENCE . ' ' . 'WHERE clean_flag=1 ');
            } else {
                break;
            }
        } while (true);
        tep_db_query('OPTIMIZE TABLE ' . TABLE_IMAGE_COPY_REFERENCE . ' ');
        if ($is_console) {
            Console::end_progress(true);
            echo "Done.\n";
        }
    }
    public static function clean_product_image_reference($product_id = 0, $products_image_id = 0)
    {
        $where = '';
        if ($product_id) {
            $where .= "AND products_id='" . (int) $product_id . "' ";
        }
        if ($products_image_id) {
            $where .= "AND products_image_id='" . (int) $products_image_id . "' ";
        }
        if ($where) {
            tep_db_query('UPDATE ' . TABLE_IMAGE_COPY_REFERENCE . ' ' . ' SET clean_flag=1 ' . "WHERE 1 {$where}");
            $get_reference_r = tep_db_query('SELECT DISTINCT filename ' . 'FROM ' . TABLE_IMAGE_COPY_REFERENCE . ' ' . "WHERE clean_flag=1 {$where}");
            if (tep_db_num_rows($get_reference_r) > 0) {
                while ($reference = tep_db_fetch_array($get_reference_r)) {
                    self::remove_reference_filename($reference['filename']);
                }
            }
            tep_db_query('DELETE FROM ' . TABLE_IMAGE_COPY_REFERENCE . ' ' . "WHERE clean_flag=1 {$where}");
        }
    }
    public static function send_image_to_browser($image_file_name, $mime_type = '')
    {
        if (empty($mime_type)) {
            $mime_type = 'image/png';
            $image_info = @get_image_size($image_file_name);
            if ($image_info && $image_info['mime']) {
                $mime_type = $image_info['mime'];
            }
        }
        header('Content-type: ' . $mime_type);
        readfile($image_file_name);
    }
    public static function calculate_image_size($img_width, $img_height, $box_width, $box_height, $fit = 'inside')
    {
        if (is_null($box_width) && is_null($box_height) || (int) $img_width == 0) {
            return ['width' => (int) $img_width, 'height' => (int) $img_height];
        }
        if (!empty($box_width)) {
            $rx = $img_width / $box_width;
        } else {
            $rx = null;
        }
        if (!empty($box_height)) {
            $ry = $img_height / $box_height;
        } else {
            $ry = null;
        }
        $width = $box_width;
        $height = $box_height;
        if ($rx === null && $ry !== null) {
            $rx = $ry;
            $width = round($img_width / $rx);
        }
        if ($ry === null && $rx !== null) {
            $ry = $rx;
            $height = round($img_height / $ry);
        }
        if ($width === 0 || $height === 0) {
            return ['width' => 0, 'height' => 0];
        }
        $dim = [];
        if ($fit == 'fill') {
            $dim['width'] = $width;
            $dim['height'] = $height;
        } else {
            $ratio = $rx > $ry ? $rx : $ry;
            if ($fit == 'outside') {
                $ratio = $rx < $ry ? $rx : $ry;
            }
            $dim['width'] = round($img_width / $ratio);
            $dim['height'] = round($img_height / $ratio);
        }
        return $dim;
    }
    public static function create_webp($source_image, $rewrite = false, $catalog = false)
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        if ($catalog === false) {
            $catalog = DIR_WS_IMAGES;
        }
        $path = DIR_FS_CATALOG;
        if (!is_file($path . $source_image)) {
            return false;
        }
        $pos = strripos($source_image, '.');
        $ext = strtolower(substr($source_image, $pos + 1));
        $name = substr($source_image, 0, $pos);
        $webp_name = $name . '.webp';
        if (!$rewrite && is_file($path . $webp_name)) {
            return false;
        }
        if ($ext == 'jpg' || $ext == 'jpeg') {
            $image = @imagecreatefromjpeg($path . $source_image);
        } elseif ($ext == 'png') {
            $image = @imagecreatefrompng($path . $source_image);
        } elseif ($ext == 'gif') {
            $image = @imagecreatefromgif($path . $source_image);
        } else {
            return false;
        }
        if (!$image) {
            return false;
        }
        imagepalettetotruecolor($image);
        $result = imagewebp($image, $path . $webp_name);
        imagedestroy($image);
        return $result;
    }
    public static function remove_webp($source_image)
    {
        $path = self::get_fs_catalog_images_path();
        $pos = strripos($source_image, '.');
        $file_name = substr($source_image, 0, $pos);
        $webp_name = $file_name . '.webp';
        if (is_file($path . $webp_name)) {
            @unlink($path . $webp_name);
        }
    }
    public static function get_webp($source_image, $catalog = false)
    {
        if (stripos($_SERVER['HTTP_USER_AGENT'], 'Chrome') === false && stripos($_SERVER['HTTP_USER_AGENT'], 'Firefox') === false) {
            return $source_image;
        }
        if ($catalog === false) {
            $catalog = DIR_WS_IMAGES;
        }
        $path = \Yii::get_alias('@webroot') . DIRECTORY_SEPARATOR . $catalog;
        if (defined('DIR_WS_HTTP_ADMIN_CATALOG')) {
            $path = str_replace(DIR_WS_HTTP_ADMIN_CATALOG, '', $path);
        }
        $pos = strripos($source_image, '.');
        $file_name = substr($source_image, 0, $pos);
        $webp_name = $file_name . '.webp';
        if (is_file($path . rawurldecode($webp_name))) {
            return $webp_name;
        }
        return $source_image;
    }
    /**
     * create images in sizes from image_types table
     * @param string   $sourceImage  image name with path from image folder
     * @param string   $imageType    image type from image_types_name field of image_types table if empty create all sizes
     * @param boolean  $rewrite      rewrite image if exist
     * @return boolean
     */
    public static function create_resize_images($source_image, $image_type = '', $rewrite = false)
    {
        $path = self::get_fs_catalog_images_path();
        if (!is_file($path . $source_image)) {
            return false;
        }
        $file_name_full = strtolower($source_image);
        $pos = strripos($file_name_full, '.');
        $ext = strtolower(substr($file_name_full, $pos + 1));
        $file_name = substr($file_name_full, 0, $pos);
        if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
            return false;
        }
        $conditions = [];
        if ($image_type) {
            $conditions['image_types_name'] = $image_type;
        }
        $image_types = \common\models\Image_Types::find()->where($conditions)->as_array()->all();
        foreach ($image_types as $_image_type) {
            $width = $_image_type['image_types_x'];
            $new_img = $file_name . '-' . $width . '.' . $ext;
            if ($rewrite || !is_file($path . $new_img)) {
                $size = @get_image_size($path . $source_image);
                $height = $size[1] * $width / $size[0];
                self::tep_image_resize($path . $source_image, $path . $new_img, $width, $height);
            }
            self::create_webp(DIR_WS_IMAGES . $new_img);
        }
        return true;
    }
    /**
     * remove images in sizes from image_types table
     * @param string   $sourceImage  image name with path from image folder
     * @return boolean
     */
    public static function remove_resize_images($source_image)
    {
        $path = self::get_fs_catalog_images_path();
        $file_name_full = strtolower($source_image);
        $pos = strripos($file_name_full, '.');
        $ext = strtolower(substr($file_name_full, $pos + 1));
        $file_name = substr($file_name_full, 0, $pos);
        $image_types = \common\models\Image_Types::find()->as_array()->all();
        foreach ($image_types as $image_type) {
            $width = $image_type['image_types_x'];
            $remove_image = $path . $file_name . '-' . $width . '.' . $ext;
            if (is_file($remove_image)) {
                @unlink($remove_image);
                self::remove_webp($file_name . '-' . $width . '.' . $ext);
            }
        }
        return true;
    }
    /**
     * @param string   $sourceImage  image name with path from image folder
     * @param string   $imageType   image type from image_types_name field of image_types table if empty create all sizes
     * @param array    $attributes  additional attributes for teg
     * @param string   $naImage
     * @param boolean  $lazy_load
     * @param array  $responsiveImages list of images by image_types_id
     * @return string img html teg with srcset and sizes attributes
     */
    public static function get_image_set($source_image, $image_type = '', $attributes = [], $na_image = '', $lazy_load = false, $responsive_images = [])
    {
        $url_path = \common\helpers\Media::get_alias('@webCatalogImages/');
        $path = \Yii::get_alias('@webroot') . DIRECTORY_SEPARATOR . DIR_WS_IMAGES;
        if (defined('DIR_WS_HTTP_ADMIN_CATALOG')) {
            $path = str_replace(DIR_WS_HTTP_ADMIN_CATALOG, '', $path);
        }
        if (!$na_image && $na_image !== false) {
            $na_image = Info::theme_file('/img/na.png');
        }
        if (!is_file($path . $source_image)) {
            if ($na_image === false) {
                return false;
            }
            return Html::tag('picture', Html::img($na_image, $attributes));
        }
        $pos = strripos($source_image, DIRECTORY_SEPARATOR);
        $file_name_full = strtolower(substr($source_image, $pos + 1));
        $file_path = substr($source_image, 0, $pos);
        $pos = strripos($file_name_full, '.');
        $ext = strtolower(substr($file_name_full, $pos + 1));
        $file_name = substr($file_name_full, 0, $pos);
        $conditions = [];
        if ($image_type) {
            $conditions['image_types_name'] = $image_type;
        }
        $image_types = \common\models\Image_Types::find()->where($conditions)->as_array()->all();
        $sources = '';
        foreach ($image_types as $image_type) {
            $width = $image_type['image_types_x'];
            if ($responsive_images[$image_type['image_types_id']]['image'] ?? false) {
                $image_path = $path . $responsive_images[$image_type['image_types_id']]['image'];
                $image_url = $url_path . $responsive_images[$image_type['image_types_id']]['image'];
            } else {
                $image_path = $path . $file_path . DIRECTORY_SEPARATOR . $file_name . '-' . $width . '.' . $ext;
                $image_url = $url_path . self::get_webp($file_path . DIRECTORY_SEPARATOR . rawurlencode($file_name) . '-' . $width . '.' . $ext);
            }
            $media = '';
            if ($image_type['width_from']) {
                $media .= '(min-width: ' . $image_type['width_from'] . 'px)';
            }
            if ($image_type['width_from'] && $image_type['width_to']) {
                $media .= ' and ';
            }
            if ($image_type['width_to']) {
                $media .= '(max-width: ' . $image_type['width_to'] . 'px)';
            }
            if ($attributes['id'] ?? false) {
                $css = '';
                if ($media) {
                    $css .= '@media ' . $media . '{';
                }
                if ($image_type['image_types_y'] && $image_type['image_types_x']) {
                    $height_per = round($image_type['image_types_y'] * 100 / $image_type['image_types_x'], 4);
                    $css .= 'picture#' . $attributes['id'] . ' {padding-top: ' . $height_per . '%;position: relative}';
                    if ($responsive_images[$image_type['image_types_id']]['fit'] ?? false) {
                        $fit = $responsive_images[$image_type['image_types_id']]['fit'];
                    } else {
                        $fit = 'cover';
                    }
                    $css .= 'picture#' . $attributes['id'] . ' img {object-fit: ' . $fit . ';';
                    if ($responsive_images[$image_type['image_types_id']]['position'] ?? false) {
                        $position = $responsive_images[$image_type['image_types_id']]['position'];
                        $css .= 'object-position: ' . $position . '%;';
                    }
                    $css .= 'position:absolute;left:0;top:0;width:100%;height:100%}';
                } else {
                    $css .= 'picture#' . $attributes['id'] . ' img {position: static;}';
                }
                if ($media) {
                    $css .= '}';
                }
                Info::set_script_css($css);
            }
            if (!is_file($image_path)) {
                continue;
            }
            $size = @get_image_size($image_path);
            if (!$size[0]) {
                continue;
            }
            if (!$image_type['width_from'] && !$image_type['width_to']) {
                continue;
            }
            $sources_attr = ['srcset' => $image_url, 'media' => $media];
            if ($lazy_load) {
                $sources_attr['data-srcset'] = $image_url;
                $sources_attr['srcset'] = $na_image;
            }
            $sources .= Html::tag('source', '', $sources_attr);
        }
        $src = $url_path . self::get_webp($source_image);
        if ($lazy_load) {
            $attributes['data-src'] = $src;
            $src = $na_image;
        }
        $id = 0;
        if ($attributes['id']) {
            $id = $attributes['id'];
            unset($attributes['id']);
        }
        $img = Html::img($src, $attributes);
        $html = Html::tag('picture', $sources . $img, $id ? ['id' => $id] : []);
        return $html;
    }
    /**
     * @param  string    $type  which type of image need to create,
     *                          can be '', 'products', 'categories', 'banners',
     *                          if '' create all types
     * @param  integer   $iteration  if iteration == -1 create all images in one frame
     * @param  integer   $frameSize  count images of categories or products in one frame
     * @return string json [
     *                        'iteration', // iteration for next request
     *                        'products_count', // all product images in db
     *                        'product', // processed products
     *                        'product_images', // created product images
     *                        'product_images_all', // processed product images
     *                        'categories_count', // all category images in db
     *                        'categories', // processed categories
     *                        'category_images', // created category images
     *                        'category_images_all', // processed category images
     *                        'banners', //  processed banners
     *                        'banner_images', // created banner images
     *                        'banner_images_all', // processed banner images
     *                     ]
     */
    public static function create_all_webp_images($type = '', $iteration = 0, $frame_size = 10)
    {
        $result = [];
        if (!$type || $type == 'products') {
            $products_id = 0;
            $count = \common\models\Products_Images::find()->count();
            $result['products_count'] = $count;
            if ($iteration == -1) {
                $products_images = \common\models\Products_Images::find()->select(['products_id', 'products_images_id'])->order_by('products_id', 'products_images_id')->as_array()->all();
            } else {
                if ($count > $frame_size * ($iteration + 1)) {
                    $result['iteration'] = $iteration + 1;
                }
                $products_images = \common\models\Products_Images::find()->select(['products_id', 'products_images_id'])->order_by('products_id', 'products_images_id')->limit($frame_size)->offset($frame_size * $iteration)->as_array()->all();
            }
            foreach ($products_images as $image) {
                if ($image['products_id'] != $products_id) {
                    $products_id = $image['products_id'];
                    $result['product']++;
                }
                $count = \common\classes\Images::normalize_image_files($image['products_id'], $image['products_images_id']);
                $result['product_images'] += $count;
                $result['product_images_all']++;
            }
        }
        if (!$type || $type == 'categories') {
            $result['category_images'] = 0;
            $categories_count = \common\models\Categories::find()->count();
            $result['categories_count'] = $categories_count;
            if ($iteration == -1) {
                $categories = \common\models\Categories::find()->as_array()->all();
            } else {
                $categories = \common\models\Categories::find()->limit($frame_size)->offset($frame_size * $iteration)->as_array()->all();
            }
            foreach ($categories as $category) {
                $result['category_images_all']++;
                $result['categories']++;
                $sql_data_array = [];
                foreach (['gallery' => '', 'hero' => '_2', 'homepage' => '_3'] as $image_type => $mod) {
                    $sql_data_array['categories_image' . $mod] = self::move_image($category['categories_image' . $mod], 'categories' . DIRECTORY_SEPARATOR . $category['categories_id'] . DIRECTORY_SEPARATOR . $image_type);
                    if (!$sql_data_array['categories_image' . $mod]) {
                        continue;
                    }
                    $webp = Images::create_webp($sql_data_array['categories_image' . $mod]);
                    Images::create_resize_images($sql_data_array['categories_image' . $mod], 'Category ' . $image_type);
                    if ($webp) {
                        $result['category_images']++;
                    }
                }
                $category_mod = \common\models\Categories::find_one($category['categories_id']);
                $category_mod->attributes = $sql_data_array;
                $category_mod->save();
            }
            $categories_ps_count = \common\models\Categories_Platform_Settings::find()->count();
            $result['categories_count'] = $result['categories_count'] + $categories_ps_count;
            if ($iteration == -1) {
                $categories = \common\models\Categories_Platform_Settings::find()->as_array()->all();
            } else if ($categories_count > $frame_size * $iteration) {
                $categories = [];
                $result['iteration'] = $iteration + 1;
            } else {
                $iteration2 = $iteration - ceil($categories_count / $frame_size);
                if ($categories_ps_count > $frame_size * ($iteration2 + 1)) {
                    $result['iteration'] = $iteration + 1;
                }
                $categories = \common\models\Categories_Platform_Settings::find()->limit($frame_size)->offset($frame_size * $iteration2)->as_array()->all();
            }
            foreach ($categories as $category) {
                $result['category_images_all']++;
                $sql_data_array = [];
                foreach (['gallery' => '', 'hero' => '_2', 'homepage' => '_3'] as $image_type => $mod) {
                    $sql_data_array['categories_image' . $mod] = self::move_image($category['categories_image' . $mod], 'categories' . DIRECTORY_SEPARATOR . $category['categories_id'] . DIRECTORY_SEPARATOR . $image_type);
                    if (!$sql_data_array['categories_image' . $mod]) {
                        continue;
                    }
                    $webp = Images::create_webp($sql_data_array['categories_image' . $mod]);
                    Images::create_resize_images($sql_data_array['categories_image' . $mod], 'Category ' . $image_type);
                    if ($webp) {
                        $result['category_images']++;
                    }
                }
                $category_mod = \common\models\Categories_Platform_Settings::find_one(['categories_id' => $category['categories_id'], 'platform_id' => $category['platform_id']]);
                $category_mod->attributes = $sql_data_array;
                $category_mod->save();
            }
        }
        if (!$type || $type == 'banners') {
            $main_images = \common\models\Banners_Languages::find()->order_by('banners_id')->all();
            $languages = \common\helpers\Language::get_languages();
            $result['banner_images'] = 0;
            $result['banner_images_all'] = 0;
            $result['banners'] = 0;
            $banners_id = 0;
            foreach ($main_images as $main_image) {
                $img_path = 'banners' . DIRECTORY_SEPARATOR . $main_image->banners_id;
                if ($main_image->banners_image) {
                    $main_image->banners_image = self::move_image($main_image->banners_image, $img_path);
                    $main_image->save();
                    $webp = self::create_webp($main_image->banners_image);
                    if ($webp) {
                        $result['banner_images']++;
                    }
                    $result['banner_images_all']++;
                }
                if ($banners_id == $main_image->banners_id) {
                    continue;
                }
                $banners_id = $main_image->banners_id;
                $result['banners']++;
                $banner = \common\models\Banners::find()->where(['banners_id' => $banners_id])->as_array()->one();
                $group_sizes = \common\models\Banners_Groups::find()->where(['banners_group' => $banner['banners_group']])->as_array()->all();
                foreach ($languages as $language) {
                    $banners_languages = \common\models\Banners_Languages::find()->where(['banners_id' => $banners_id, 'language_id' => $language['id']])->as_array()->one();
                    $main_image = $banners_languages['banners_image'];
                    foreach ($group_sizes as $group_size) {
                        $img_path = 'banners' . DIRECTORY_SEPARATOR . $banners_id;
                        $img = \common\models\Banners_Groups_Images::find_one(['banners_id' => $banners_id, 'language_id' => $language['id'], 'image_width' => $group_size['image_width']]);
                        if (!$img || !$img->image || !is_file(DIR_FS_CATALOG_IMAGES . $img->image)) {
                            if (!$main_image || !is_file(DIR_FS_CATALOG_IMAGES . $main_image)) {
                                continue;
                            }
                            $img_explode = explode('/', $main_image);
                            $img_name = end($img_explode);
                            $pos = strrpos($img_name, '.');
                            $name = substr($img_name, 0, $pos);
                            $ext = substr($img_name, $pos);
                            $new_img = $img_path . DIRECTORY_SEPARATOR . $name . '[' . $group_size['image_width'] . ']' . $ext;
                            if (!is_file(DIR_FS_CATALOG_IMAGES . $new_img)) {
                                $size = @get_image_size(DIR_FS_CATALOG_IMAGES . $main_image);
                                $height = $size[1] * $group_size['image_width'] / $size[0];
                                self::tep_image_resize(DIR_FS_CATALOG_IMAGES . $main_image, DIR_FS_CATALOG_IMAGES . $new_img, $group_size['image_width'], $height);
                            }
                        } elseif ($img && $img->image) {
                            $new_img = $img->image;
                        }
                        if (!$img) {
                            $img = new \common\models\Banners_Groups_Images();
                        }
                        $img->attributes = ['banners_id' => (int) $banners_id, 'language_id' => (int) $language['id'], 'image_width' => (int) $group_size['image_width'], 'image' => $new_img];
                        $img->save();
                        $webp = self::create_webp($new_img);
                        if ($webp) {
                            //$result['banner_images']++;
                        }
                    }
                }
            }
        }
        foreach (\common\helpers\Acl::get_extension_create_images_settings() as $create_images_settings) {
            if (!$type || $type == $create_images_settings['type']) {
                if ($_w = \common\helpers\Acl::check_extension($create_images_settings['extension'], 'createImages')) {
                    $_response = $_w::create_images($iteration, $frame_size);
                    $result = array_merge($result, $_response);
                }
            }
        }
        return json_encode($result);
    }
    /**
     * if image is't in $destination folder copy it to this folder and returns whole filename
     * @param string $sourceImage
     * @param string $destination
     * @return string
     */
    public static function move_image($source_image, $destination, $default_image_path = true)
    {
        if (stripos($source_image, $destination) !== false) {
            return $source_image;
        }
        $path = \Yii::get_alias('@webroot') . '/' . DIR_WS_IMAGES;
        // don't use DIRECTORY_SEPARATOR here
        if (defined('DIR_WS_HTTP_ADMIN_CATALOG')) {
            $path = str_replace('/' . trim(DIR_WS_HTTP_ADMIN_CATALOG, '/') . '/', '/', $path);
        }
        $path_destination = $path;
        if (!$default_image_path) {
            $path = '';
        }
        if (!is_file($path . $source_image)) {
            return '';
        }
        \yii\helpers\File_Helper::create_directory($path_destination . $destination, 0777);
        if (str_contains($source_image, '/')) {
            $pos = strripos($source_image, '/');
            $file_name = strtolower(substr($source_image, $pos + 1));
        } elseif (str_contains($source_image, '\\')) {
            $pos = strripos($source_image, '\\');
            $file_name = strtolower(substr($source_image, $pos + 1));
        } else {
            $file_name = $source_image;
        }
        @copy($path . $source_image, $path_destination . $destination . DIRECTORY_SEPARATOR . $file_name);
        return $destination . DIRECTORY_SEPARATOR . $file_name;
    }
    /**
     * @param array $settings[
     *      'src', //image source - required
     *      'top', // crop - required
     *      'left', // crop - required
     *      'width', // crop - required
     *      'height', // crop - required
     *      'destination', // image destination - required
     *      'imgWidth', // required image width - not required
     *      'imgHeight', // required image height - not required
     *      'color', // side space color - not required
     * ]
     * @return mixed false if error, image destination (string)
     */
    public static function crop_image($settings)
    {
        if (IMAGE_RESIZE != 'GD' || !function_exists('gd_info')) {
            return false;
        }
        $image = $settings['src'];
        $size = @get_image_size($image);
        $origin_width = $size[0];
        $origin_height = $size[1];
        $new_width = $settings['imgWidth'];
        $new_height = $settings['imgHeight'];
        if (!$new_width && !$new_height) {
            $new_width = $settings['width'];
            $new_height = $settings['height'];
        } elseif ($new_width && !$new_height) {
            $new_height = $new_width * $settings['height'] / $settings['width'];
        } elseif (!$new_width && $new_height) {
            $new_width = $new_height * $settings['width'] / $settings['height'];
        }
        switch ($size[2]) {
            case 18:
                $im = @imagecreatefromwebp($image);
                break;
            case 1:
                // GIF
                $im = @image_create_from_gif($image);
                break;
            case 3:
                // PNG
                $im = @image_create_from_png($image);
                if ($im) {
                    if (function_exists('imageAntiAlias')) {
                        @image_anti_alias($im, true);
                    }
                    @image_alpha_blending($im, true);
                    @image_save_alpha($im, true);
                }
                break;
            case 2:
                // JPEG
                $im = @image_create_from_jpeg($image);
                break;
            default:
                return false;
        }
        if (!$im) {
            return false;
        }
        $im_pic = 0;
        if (function_exists('ImageCreateTrueColor')) {
            $im_pic = @image_create_true_color($new_width, $new_height);
        }
        if ($im_pic == 0) {
            $im_pic = @image_create($new_width, $new_height);
        }
        if ($im_pic == 0) {
            return false;
        }
        if ($settings['color']) {
            $settings['color'] = str_replace(' ', '', $settings['color']);
            if (preg_match("/^rgb\\(([0-9]{1,3})\\,([0-9]{1,3})\\,([0-9]{1,3})\\)\$/i", $settings['color'], $matches)) {
                $bg_color_r = $matches[1];
                $bg_color_g = $matches[2];
                $bg_color_b = $matches[3];
            } elseif (preg_match("/^rgba\\(([0-9]{1,3})\\,([0-9]{1,3})\\,([0-9]{1,3}),([0-9\\.]{1,5})\\)\$/i", $settings['color'], $matches)) {
                $bg_color_r = $matches[1];
                $bg_color_g = $matches[2];
                $bg_color_b = $matches[3];
            } elseif (preg_match("/^\\#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})\$/i", $settings['color'], $matches)) {
                $bg_color_r = hexdec($matches[1]);
                $bg_color_g = hexdec($matches[2]);
                $bg_color_b = hexdec($matches[3]);
            } elseif (preg_match("/^\\#([0-9a-f])([0-9a-f])([0-9a-f])\$/i", $settings['color'], $matches)) {
                $bg_color_r = hexdec($matches[1] . $matches[1]);
                $bg_color_g = hexdec($matches[2] . $matches[2]);
                $bg_color_b = hexdec($matches[3] . $matches[3]);
            } else {
                $bg_color_r = 255;
                $bg_color_g = 255;
                $bg_color_b = 255;
            }
        } else {
            $bg_color_r = 255;
            $bg_color_g = 255;
            $bg_color_b = 255;
        }
        $bg_color_a = 0;
        @image_interlace($im_pic, 1);
        if (function_exists('imageAntiAlias')) {
            @image_anti_alias($im_pic, true);
        }
        @imagealphablending($im_pic, false);
        @imagesavealpha($im_pic, true);
        $transparent = @imagecolorallocatealpha($im_pic, $bg_color_r, $bg_color_g, $bg_color_b, $bg_color_a);
        for ($i = 0; $i < $new_width; $i++) {
            for ($j = 0; $j < $new_height; $j++) {
                @image_set_pixel($im_pic, $i, $j, $transparent);
            }
        }
        $scale = $new_width / $settings['width'];
        $left = $settings['left'];
        $top = $settings['top'];
        $width = $settings['width'];
        $height = $settings['height'];
        $img_left = 0;
        $img_top = 0;
        $img_width = $new_width;
        $img_height = $new_height;
        if ($settings['top'] < 0) {
            $top = 0;
            $height = $settings['height'] + $settings['top'];
            $img_top = -$settings['top'] * $scale;
            $img_height = $height * $scale;
        }
        if ($settings['left'] < 0) {
            $left = 0;
            $width = $settings['width'] + $settings['left'];
            $img_left = -$settings['left'] * $scale;
            $img_width = $width * $scale;
        }
        if ($height + $top > $origin_height) {
            $height = $height - ($height + $top - $origin_height);
            $img_height = $height * $scale;
        }
        if ($width + $left > $origin_width) {
            $width = $width - ($width + $left - $origin_width);
            $img_width = $width * $scale;
        }
        if (function_exists('ImageCopyResampled')) {
            $resized = @image_copy_resampled($im_pic, $im, $img_left, $img_top, $left, $top, $img_width, $img_height, $width, $height);
        }
        if (!$resized) {
            @image_copy_resized($im_pic, $im, $img_left, $img_top, $left, $top, $img_width, $img_height, $width, $height);
        }
        if ($size[2] == 3) {
            @image_png($im_pic, $settings['destination'], 9);
        } elseif ($size[2] == 18) {
            @imagewebp($im_pic, $settings['destination']);
        } else {
            @image_jpeg($im_pic, $settings['destination'], 85);
        }
        if (is_file($settings['destination'])) {
            chmod($settings['destination'], 0666);
            return $settings['destination'];
        }
        return false;
    }
}