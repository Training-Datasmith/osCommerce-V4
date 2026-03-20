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

use common\classes\Seo_Meta_Format_Interface;
use common\models\Products_Description;
use yii\db\Active_Record;
use yii\db\Expression;
use yii\helpers\Array_Helper;
class Seo
{
    public static function get_seo_page_path($id, $platform_id)
    {
        global $languages_id;
        $info_query = tep_db_query('select seo_page_name from ' . TABLE_INFORMATION . " where languages_id = '" . (int) $languages_id . "' and information_id = '" . (int) $id . "' and platform_id = '" . (int) $platform_id . "' and affiliate_id = 0");
        $info = tep_db_fetch_array($info_query);
        return $info['seo_page_name'];
    }
    public static function transliterate($input)
    {
        $gost = ['Є' => 'YE', 'І' => 'I', 'Ѓ' => 'G', 'і' => 'i', '№' => '-', 'є' => 'ye', 'ѓ' => 'g', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'X', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SHH', 'Ъ' => "'", 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', ' ' => '-', '—' => '-', ',' => '-', '!' => '-', '@' => '-', '#' => '-', '$' => '', '%' => '', '^' => '', '&' => '', '*' => '', '(' => '', ')' => '', '+' => '', '=' => '', ';' => '', ':' => '', "'" => '', '"' => '', '~' => '', '`' => '', '?' => '', '/' => '', '\\' => '', '[' => '', ']' => '', '{' => '', '}' => '', '|' => '', '.' => '-', 'Ä' => 'A', 'ä' => 'a', 'Ǟ' => 'A', 'ǟ' => 'a', 'Ë' => 'E', 'ë' => 'e', 'Ḧ' => 'H', 'ḧ' => 'h', 'Ï' => 'I', 'ï' => 'i', 'Ḯ' => 'I', 'ḯ' => 'i', 'Ö' => 'O', 'ö' => 'o', 'Ȫ' => 'O', 'ȫ' => 'o', 'Ṏ' => 'O', 'ṏ' => 'o', 'ẗ' => 't', 'Ü' => 'U', 'ü' => 'u', 'Ǖ' => 'U', 'ǖ' => 'u', 'Ǘ' => 'U', 'ǘ' => 'u', 'Ǚ' => 'U', 'ǚ' => 'u', 'Ǜ' => 'U', 'ǜ' => 'u', 'Ṳ' => 'U', 'ṳ' => 'u', 'Ṻ' => 'U', 'ṻ' => 'u', 'Ẅ' => 'W', 'ẅ' => 'w', 'Ẍ' => 'X', 'ẍ' => 'x', 'Ÿ' => 'Y', 'ÿ' => 'y', '–' => '-', '«' => '', '»' => ''];
        $input = strtr($input, $gost);
        $input = preg_replace('/(-){1,}/', '-', $input);
        if (substr($input, -1) == '-') {
            $input = substr($input, 0, -1);
        }
        $input = \yii\helpers\Inflector::slug($input);
        return $input;
    }
    public static function make_slug($string)
    {
        $seo_name = preg_replace("/(%[\\da-f]{2}|\\+|_)/i", '-', urlencode(self::transliterate($string)));
        $seo_name = preg_replace('/-{2,}/', '-', $seo_name);
        return strtolower($seo_name);
    }
    public static function make_product_slug($description_data, $product_data)
    {
        if (is_object($description_data) && $description_data instanceof Active_Record) {
            $description = $description_data->get_attributes(['products_seo_page_name', 'products_name']);
        } else {
            $description = ['products_seo_page_name' => $description_data['products_seo_page_name'], 'products_name' => $description_data['products_name']];
        }
        if (is_object($product_data) && $product_data instanceof Active_Record) {
            $product = $product_data->get_attributes(['products_id', 'products_model']);
        } elseif (!is_array($product_data)) {
            $product = \common\models\Products::find()->where(['products_id' => (int) $product_data])->select(['products_id', 'products_model'])->as_array()->one();
        } else {
            $product = ['products_id' => $product_data['products_id'], 'products_model' => $product_data['products_model']];
        }
        $products_seo_page_name = $description['products_seo_page_name'];
        if (empty($products_seo_page_name) || static::is_product_seo_page_duplicated($products_seo_page_name, $product['products_id'])) {
            $slug_variants = [];
            if (empty($description['products_name'])) {
                $description['products_name'] = \common\helpers\Product::get_products_name($product['products_id']);
            }
            if (!empty($description['products_name'])) {
                $slug_variants[] = static::make_slug($description['products_name']);
                if (!empty($product['products_model'])) {
                    $slug_variants[] = static::make_slug($product['products_model'] . '-' . $description['products_name']);
                    $slug_variants[] = static::make_slug($product['products_model'] . '-' . $description['products_name'] . '-' . $product['products_id']);
                }
                $slug_variants[] = static::make_slug($description['products_name'] . '-' . $product['products_id']);
            } elseif (!empty($product['products_model'])) {
                $slug_variants[] = static::make_slug($product['products_model']);
                $slug_variants[] = static::make_slug($product['products_model'] . '-' . $product['products_id']);
            }
            $slug_variants[] = static::make_slug($product['products_id']);
            $used_variants = Array_Helper::map(Products_Description::find()->where(['!=', 'products_id', (int) $product['products_id']])->and_where(['IN', 'products_seo_page_name', $slug_variants])->select(['products_seo_page_name', new Expression('COUNT(*) AS use_count')])->group_by(['products_seo_page_name'])->as_array()->all(), 'products_seo_page_name', 'use_count');
            foreach ($slug_variants as $slug_variant) {
                if (!isset($used_variants[$slug_variant])) {
                    $products_seo_page_name = $slug_variant;
                    break;
                }
            }
        }
        return $products_seo_page_name;
    }
    protected static function is_product_seo_page_duplicated($seo_page_name, $exclude_product_id)
    {
        $matched_seo_count = Products_Description::find()->where(['!=', 'products_id', (int) $exclude_product_id])->and_where(['products_seo_page_name' => $seo_page_name])->count();
        return $matched_seo_count > 0;
    }
    /**
     * @param $propertyData
     * @return string
     */
    public static function make_property_slug($property_data)
    {
        $prop_name = static::make_slug($property_data['properties_name']);
        $exist_count = \common\models\Properties_Description::find()->where(['properties_seo_page_name' => $prop_name])->and_where(['!=', 'properties_id', $property_data['properties_id']])->count('distinct properties_id');
        if ($exist_count > 0) {
            $prop_name = static::make_slug($property_data['properties_name'] . '-' . (int) $exist_count);
            $exist_count = \common\models\Properties_Description::find()->where(['properties_seo_page_name' => $prop_name])->and_where(['!=', 'properties_id', $property_data['properties_id']])->count('distinct properties_id');
            if ($exist_count > 0) {
                $prop_name = static::make_slug($property_data['properties_name'] . '-' . (int) $property_data['properties_id']);
            }
        }
        return $prop_name;
    }
    /**
     * @param $propertyData
     * @return string
     */
    public static function make_property_value_slug($property_value_data)
    {
        $prop_value_slug = static::make_slug($property_value_data['values_text']);
        $exist_count = \common\models\Properties_Values::find()->where(['values_seo_page_name' => $prop_value_slug])->and_filter_where(['!=', 'values_id', $property_value_data['values_id'] ?? null])->and_filter_where(['properties_id' => $property_value_data['properties_id'] ?? null])->and_filter_where(['language_id' => $property_value_data['language_id'] ?? null])->count('distinct values_id');
        if ($exist_count > 0) {
            $prop_value_slug = static::make_slug($property_value_data['values_text'] . '-' . (int) $exist_count);
            $exist_count = \common\models\Properties_Values::find()->where(['values_seo_page_name' => $prop_value_slug])->and_filter_where(['!=', 'values_id', $property_value_data['values_id'] ?? null])->and_filter_where(['properties_id' => $property_value_data['properties_id'] ?? null])->and_filter_where(['language_id' => $property_value_data['language_id'] ?? null])->count('distinct values_id');
            if ($exist_count > 0) {
                $prop_value_slug = static::make_slug($property_value_data['values_text'] . '-' . (int) ($property_value_data['values_id'] ?? 99));
            }
        }
        return $prop_value_slug;
    }
    /*public static function getSeoUrlsByRoute($route)
        {
            $urls = [];
            switch ($route) {
                case 'catalog/index':
                    global $current_category_id;
                    $query = tep_db_query("select categories_seo_page_name, language_id from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" .(int)$current_category_id . "' and platform_id = 0");
                    if (tep_db_num_rows($query)){
                        while($row = tep_db_fetch_array($query)){
                            $urls[$row['language_id']] = $row['categories_seo_page_name'];
                        }
                    }
                    break;
                case 'catalog/product':
                    $query = tep_db_query("select products_seo_page_name, language_id from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" .(int)$_GET['products_id'] . "' and platform_id = 0");
                    if (tep_db_num_rows($query)){
                        while($row = tep_db_fetch_array($query)){
                            $urls[$row['language_id']] = $row['products_seo_page_name'];
                        }
                    }
                    break;
                case 'info/custom':
    
                    break;
            }
            return $urls;
        }*/
    public static function set_page_meta_title($title_const, Seo_Meta_Format_Interface $formatter)
    {
        $title = $formatter->own_meta_title();
        if (!empty($title)) {
            \Yii::$app->get_view()->title = $title;
        } else {
            if (!is_array($title_const)) {
                $title_const = [$title_const];
            }
            foreach ($title_const as $title_const_single) {
                if (defined($title_const_single) && constant($title_const_single) != '') {
                    $meta_key = constant($title_const_single);
                    if (strpos($meta_key, '##') !== false && preg_match_all('/(##([a-z_]+)##)/i', $meta_key, $match)) {
                        foreach ($match[1] as $idx => $replace_str) {
                            if (defined($match[2][$idx])) {
                                $meta_key = str_replace($replace_str, constant($match[2][$idx]), $meta_key);
                            } else {
                                $meta_key = str_replace($replace_str, $formatter->get_meta_format_key($match[2][$idx]), $meta_key);
                            }
                        }
                    }
                    \Yii::$app->get_view()->title = $meta_key;
                    break;
                }
            }
        }
    }
    public static function set_page_meta_description($description_const, Seo_Meta_Format_Interface $formatter)
    {
        $meta_desc = $formatter->own_meta_description();
        if (!empty($meta_desc)) {
            \Yii::$app->get_view()->register_meta_tag(['name' => 'Description', 'content' => $meta_desc], 'Description');
        } else {
            if (!is_array($description_const)) {
                $description_const = [$description_const];
            }
            foreach ($description_const as $single_const) {
                if (defined($single_const) && constant($single_const) != '') {
                    $meta_desc = constant($single_const);
                    if (strpos($meta_desc, '##') !== false && preg_match_all('/(##([a-z_]+)##)/i', $meta_desc, $match)) {
                        foreach ($match[1] as $idx => $replace_str) {
                            if (defined($match[2][$idx])) {
                                $meta_desc = str_replace($replace_str, constant($match[2][$idx]), $meta_desc);
                            } else {
                                $meta_desc = str_replace($replace_str, $formatter->get_meta_format_key($match[2][$idx]), $meta_desc);
                            }
                        }
                    }
                    \Yii::$app->get_view()->register_meta_tag(['name' => 'Description', 'content' => $meta_desc], 'Description');
                    break;
                }
            }
        }
    }
    public static function set_page_meta($title_const, $description_const, Seo_Meta_Format_Interface $formatter)
    {
        static::set_page_meta_title($title_const, $formatter);
        static::set_page_meta_description($description_const, $formatter);
    }
    public static function get_noindex_tag($noindex_option = 0, $nofollow_option = 0)
    {
        if ($noindex_option == 1) {
            $text = 'NOINDEX';
        } else {
            $text = 'INDEX';
        }
        $text .= ', ';
        if ($nofollow_option == 1) {
            $text .= 'NOFOLLOW';
        } else {
            $text .= 'FOLLOW';
        }
        return $text;
    }
    public static function show_noindex_meta_tag($noindex_option = 0, $nofollow_option = 0)
    {
        $content = self::get_noindex_tag($noindex_option, $nofollow_option);
        \Yii::$app->get_view()->register_meta_tag(['name' => 'Robots', 'content' => $content], 'Robots');
    }
    public static function get_meta_default_breadcrumb($action)
    {
        $meta_tag_constant_name = 'DEFAULT_BREADCRUMB_' . strtoupper(preg_replace('/[-\/]/', '_', $action));
        if (defined($meta_tag_constant_name)) {
            return constant($meta_tag_constant_name);
        }
        return '';
    }
}