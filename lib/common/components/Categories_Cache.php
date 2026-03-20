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
namespace common\components;

use yii\caching\Chained_Dependency;
use yii\caching\Db_Query_Dependency;
use yii\caching\Tag_Dependency;
use yii\db\Query;
class Categories_Cache
{
    protected $cached_seo = [];
    protected $products_count;
    public static function get_instance()
    {
        static $instance;
        if (!$instance) {
            $instance = new static();
        }
        return $instance;
    }
    protected function get_cache_page_size()
    {
        return 200;
    }
    public function get_seo_name($category_id, $language_id)
    {
        $page_size = $this->get_cache_page_size();
        $cache_page = ceil((int) $category_id / $page_size);
        $cache_key = 'categories_seo_name_page_' . $cache_page;
        if (!isset($this->cached_seo[$cache_key][$language_id])) {
            $invalidate_dependency = new Chained_Dependency(['dependencies' => [new Tag_Dependency(['tags' => ['categories_seo_name', $cache_key]]), new Db_Query_Dependency(['query' => (new Query())->from(TABLE_CATEGORIES)->where(['AND', ['>=', 'categories_id', (int) (($cache_page - 1) * $page_size + 1)], ['<=', 'categories_id', (int) ($cache_page * $page_size)]])->select(new \yii\db\Expression('MAX(IFNULL(last_modified, date_added))'))])]]);
            $all_lang_set = \Yii::$app->get_cache()->get_or_set($cache_key, function () use ($cache_page, $page_size) {
                $categories_seo = \Yii::$app->get_db()->create_command('select cd.categories_id, cd.language_id, if(length(cd.categories_seo_page_name) > 0, cd.categories_seo_page_name, c.categories_seo_page_name) as seo_page_name ' . 'from ' . TABLE_CATEGORIES . ' c ' . '  left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on c.categories_id = cd.categories_id ' . "where c.categories_id >= '" . (int) (($cache_page - 1) * $page_size + 1) . "' and c.categories_id <= '" . (int) ($cache_page * $page_size) . "' ")->query_all();
                $seo_map = [];
                foreach ($categories_seo as $item) {
                    if (!isset($seo_map[$item['language_id']])) {
                        $seo_map[$item['language_id']] = [];
                    }
                    $seo_map[$item['language_id']][$item['categories_id']] = $item['seo_page_name'];
                }
                return $seo_map;
            }, 0, $invalidate_dependency);
            $this->cached_seo[$cache_key][$language_id] = $all_lang_set[$language_id] ?? null;
        }
        return isset($this->cached_seo[$cache_key][$language_id][$category_id]) ? $this->cached_seo[$cache_key][$language_id][$category_id] : false;
    }
    protected function init_product_counter()
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        $currency_id = \Yii::$app->settings->get('currency_id');
        $cache_key = 'category_products_counter_' . (int) $customer_groups_id . '_' . (int) $currency_id;
        $this->products_count = \Yii::$app->get_cache()->get_or_set($cache_key, function () {
            $query = \common\components\Products_Query::count_products_in_categories_query(0, false);
            //echo $query->buildQuery()->getQuery()->createCommand()->getRawSql();
            $map = $query->build_query()->get_query()->as_array()->all();
            $counters = [];
            foreach ($map as $i) {
                $counters[$i['categories_id']] = $i['products_count'];
            }
            return $counters;
        }, 600);
    }
    public function get_subcategories($category_id)
    {
        $cache_key = 'active_categories_children_' . (int) $category_id;
        $children_pool = \Yii::$app->get_cache()->get_or_set($cache_key, function () use ($category_id) {
            return \Yii::$app->get_db()->create_command('SELECT c.categories_id, c.parent_id, c.categories_status ' . 'FROM categories c ' . " INNER JOIN categories cf ON cf.categories_id='" . (int) $category_id . "' " . 'WHERE cf.categories_left < c.categories_left AND cf.categories_right > c.categories_right ' . 'ORDER BY c.categories_left')->query_all();
        }, rand(600, 700), new Tag_Dependency(['tags' => ['categories', $cache_key]]));
        $active_sub_categories = [];
        $inactive_ids = [];
        foreach ($children_pool as $cat) {
            if (isset($inactive_ids[$cat['parent_id']])) {
                $inactive_ids[$cat['categories_id']] = $cat['categories_id'];
                continue;
            }
            if ($cat['categories_status']) {
                $active_sub_categories[] = $cat['categories_id'];
            } else {
                $inactive_ids[$cat['categories_id']] = $cat['categories_id'];
            }
        }
        return $active_sub_categories;
    }
    /*
    public function productCount($category_id, $include_inactive=false)
    {
        if ( !is_array($this->products_count) ){
            $this->initProductCounter();
        }
    }
    */
    public function not_empty($category_id, $include_inactive = false)
    {
        if (!is_array($this->products_count)) {
            $this->init_product_counter();
        }
        if (isset($this->products_count[$category_id])) {
            return 1;
        } else {
            /*
            $sub_categories = [];
            \common\helpers\Categories::get_subcategories($sub_categories, $category_id, false);
            */
            $sub_categories = $this->get_subcategories($category_id);
            foreach ($sub_categories as $sub_category_id) {
                if (isset($this->products_count[$sub_category_id])) {
                    return 1;
                }
            }
        }
        return 0;
    }
    private static $cpc_interface = null;
    /**
     * Returns active CPCInterface (Categories Product Count).
     * Default is CPCFileCache, but it maybe slow for a lot of products or for UserGroupsRestrictions ext + a lot of groups => Then use ext CategoriesCache
     * @return \common\classes\CPC\CPCInterface actually name of class that supports CPCInterface
     * @throws \yii\base\InvalidConfigException
     */
    public static function get_cpc()
    {
        if (is_null(self::$cpc_interface)) {
            if (\Yii::$app->has('CategoriesProductCache')) {
                self::$cpc_interface = \Yii::$app->get('CategoriesProductCache');
            }
            if (!empty(self::$cpc_interface) && class_exists(self::$cpc_interface)) {
                $interfaces = class_implements(self::$cpc_interface);
                if ($interfaces === false || !isset($interfaces['common\classes\CPC\CPCInterface'])) {
                    \Yii::warning('Class ' . self::$cpc_interface . ' does not implement CPCInterface');
                    self::$cpc_interface = null;
                }
            }
            if (is_null(self::$cpc_interface)) {
                self::$cpc_interface = \common\classes\CPC\Cpc_File_Cache::class;
                //self::$CPCInterface = \common\classes\CPC\CPCWithoutCache::class; // for testing
            }
        }
        return self::$cpc_interface;
    }
}