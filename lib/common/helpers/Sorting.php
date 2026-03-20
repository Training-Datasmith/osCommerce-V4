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

class Sorting
{
    public const SORT_OPTIONS = [[TEXT_BY_MODEL, 'm', 'products_model', TEXT_BY_MODEL_TO_LESS], [TEXT_BY_NAME, 'n', 'products_name', TEXT_BY_NAME_TO_LESS], [TEXT_BY_MANUFACTURER, 'b', 'manufacturers_name', TEXT_BY_MANUFACTURER_TO_LESS], [TEXT_BY_PRICE, 'p', 'products_price', TEXT_BY_PRICE_TO_LESS], [TEXT_BY_QUANTITY, 'q', 'products_quantity', TEXT_BY_QUANTITY_TO_LESS], [TEXT_BY_WEIGHT, 'w', 'products_weight', TEXT_BY_WEIGHT_TO_LESS], [TEXT_BY_DATE, 'd', 'products_date_added', TEXT_BY_DATE_TO_LESS], [TEXT_BY_POPULARITY, 'y', 'products_popularity', TEXT_BY_POPULARITY_TO_LESS]];
    /**
     * default sort order for selected category.
     * @param int $categoryId
     */
    public static function get_default_sort_order($category_id = 0)
    {
        $ret = '';
        $category_id = intval($category_id);
        if ($category_id > 0) {
            $cat = \common\models\Categories::find_one($category_id);
            if (!empty($cat->default_sort_order)) {
                $ret = $cat->default_sort_order;
            } elseif ($cat) {
                $parent = $cat->get_parents()->add_select('default_sort_order, categories_left, categories_id')->and_where('default_sort_order<>""')->order_by('categories_left desc')->one();
                if (!empty($parent->default_sort_order)) {
                    $ret = $parent->default_sort_order;
                }
            }
        }
        if (empty($ret) && defined('PRODUCT_LISTING_DEFAULT_SORT_ORDER') && array_key_exists(PRODUCT_LISTING_DEFAULT_SORT_ORDER, static::get_possible_sort_options())) {
            $ret = PRODUCT_LISTING_DEFAULT_SORT_ORDER;
        }
        return $ret;
    }
    /**
     * list of supported sort orders (for admin configuration)
     * @return array [[ <c>[a|d] => 'title' ] ...]
     */
    public static function get_possible_sort_options($for_cat = 0)
    {
        $ret = [];
        if ($for_cat) {
            $ret[''] = TEXT_NO_SORTING . TEXT_FROM_CONFIGURATION;
        }
        $ret['mark'] = defined('TEXT_MARKETING_SORT_ORDER') ? TEXT_MARKETING_SORT_ORDER : '';
        $ret['gso'] = defined('TEXT_GSO_SORT_ORDER') ? TEXT_GSO_SORT_ORDER : '';
        if (is_array(static::SORT_OPTIONS)) {
            foreach (static::SORT_OPTIONS as $opt) {
                $ret[$opt[1] . 'a'] = $opt[0];
                $ret[$opt[1] . 'd'] = $opt[3];
            }
        }
        return $ret;
    }
    /**
     *
     * @param array $settings
     * @param bool $iso which char-set is used (0/1)
     * @param bool $onlyVisible (for pull-down - true, false - configuration, so all options)
     * @return array
     */
    public static function get_sorting($settings, $iso = false, $only_visible = false)
    {
        if ($iso) {
            $down_char = '&#9650;';
            $up_char = '&#9660;';
        } else {
            $down_char = '&#xe995;';
            $up_char = '&#xe996;';
        }
        $orders = [];
        //get all sort option positions from settings, if something's missed fill in with 0
        for ($i = 0; $i < 17; $i++) {
            $orders['sort_pos_' . $i] = $settings['sort_pos_' . $i] ?? 0 ? $settings['sort_pos_' . $i] : 0;
        }
        // new to top ....
        asort($orders, SORT_NUMERIC);
        //re-number to get unique sort positions (new one have 0)
        $counter = 1;
        foreach ($orders as $key => $none) {
            $orders[$key] = $counter++;
        }
        for ($i = 0; $i < 17; $i++) {
            if ($settings['sort_hide_' . $i] ?? false) {
                $orders['sort_pos_' . $i] = 100 + $i;
            }
        }
        //if (!($settings['sort_hide_0'] ?? false)) {
        $sorting[$orders['sort_pos_0']] = ['title' => TEXT_NO_SORTING, 'hide' => $settings['sort_hide_0'] ?? 1 ? '0' : '1', 'name' => '0', 'id' => '0'];
        //}
        foreach (self::SORT_OPTIONS as $j => $d) {
            $k = 2 * $j + 1;
            if (!$only_visible || !$settings['sort_hide_' . $k]) {
                $sorting[$orders['sort_pos_' . $k]] = ['title' => (empty($settings['hide_icons']) ? ' <span class="ico">' . $down_char . '</span>' : '') . $d[0], 'hide' => $settings['sort_hide_' . $k] ? '0' : '1', 'name' => $k, 'id' => $d[1] . 'a'];
            }
            $k++;
            if (!$only_visible || !$settings['sort_hide_' . $k]) {
                $sorting[$orders['sort_pos_' . $k]] = ['title' => (empty($settings['hide_icons']) ? ' <span class="ico">' . $up_char . '</span>' : '') . $d[3], 'hide' => $settings['sort_hide_' . $k] ? '0' : '1', 'name' => $k, 'id' => $d[1] . 'd'];
            }
        }
        ksort($sorting);
        return $sorting;
    }
    /**
     * @deprecated (PRODUCT_LIST_* constant do not work and will be removed)
     * @return string
     */
    public static function get_sorting_list()
    {
        $sorting = [];
        $sorting[] = ['id' => '0', 'title' => TEXT_NO_SORTING];
        if (PRODUCT_LIST_MODEL) {
            $sorting[] = ['id' => 'ma', 'title' => TEXT_BY_MODEL . ' &darr;'];
            $sorting[] = ['id' => 'md', 'title' => TEXT_BY_MODEL_TO_LESS . ' &uarr;'];
        }
        if (PRODUCT_LIST_NAME) {
            $sorting[] = ['id' => 'na', 'title' => TEXT_BY_NAME . ' &darr;'];
            $sorting[] = ['id' => 'nd', 'title' => TEXT_BY_NAME_TO_LESS . ' &uarr;'];
        }
        if (PRODUCT_LIST_MANUFACTURER) {
            $sorting[] = ['id' => 'ba', 'title' => TEXT_BY_MANUFACTURER . ' &darr;'];
            $sorting[] = ['id' => 'bd', 'title' => TEXT_BY_MANUFACTURER_TO_LESS . ' &uarr;'];
        }
        if (PRODUCT_LIST_PRICE) {
            $sorting[] = ['id' => 'pa', 'title' => TEXT_BY_PRICE . ' &darr;'];
            $sorting[] = ['id' => 'pd', 'title' => TEXT_BY_PRICE_TO_LESS . ' &uarr;'];
        }
        if (PRODUCT_LIST_QUANTITY) {
            $sorting[] = ['id' => 'qa', 'title' => TEXT_BY_QUANTITY . ' &darr;'];
            $sorting[] = ['id' => 'qd', 'title' => TEXT_BY_QUANTITY_TO_LESS . ' &uarr;'];
        }
        if (PRODUCT_LIST_WEIGHT) {
            $sorting[] = ['id' => 'wa', 'title' => TEXT_BY_WEIGHT . ' &darr;'];
            $sorting[] = ['id' => 'wd', 'title' => TEXT_BY_WEIGHT_TO_LESS . ' &uarr;'];
        }
        if (PRODUCT_LIST_POPULARITY) {
            $sorting[] = ['id' => 'ya', 'title' => TEXT_BY_POPULARITY . ' &darr;'];
            $sorting[] = ['id' => 'yd', 'title' => TEXT_BY_POPULARITY_TO_LESS . ' &uarr;'];
        }
        return $sorting;
    }
    /**
     *
     * @param string $shortKey
     * @return array
     */
    public static function get_order_by_array($short_key)
    {
        $short_key = trim($short_key);
        $ret = '';
        if (strlen($short_key) == 1) {
            $short_key .= 'a';
        }
        if (strlen($short_key) == 2) {
            $f = substr($short_key, 0, 1);
            $d = substr($short_key, 1);
            foreach (\common\helpers\Sorting::SORT_OPTIONS as $so) {
                if ($so[1] == $f) {
                    $ret = [$so[2] => $d == 'd' ? SORT_DESC : SORT_ASC];
                }
            }
        } elseif ($short_key == 'gso') {
            $ret = ['gso' => SORT_DESC];
            //direction's ignored
        } elseif ($short_key == 'mark') {
            $ret = ['mark' => SORT_ASC];
            //direction's ignored
        }
        return $ret;
    }
}