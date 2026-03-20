<?php

declare (strict_types=1);
namespace common\classes\CPC;

use common\helpers\Extensions;
/**
 * Base for CCP:
 *  - implements main interface
 *  - incapsulate base query functions
 *  - stubs for cache functions
 */
abstract class Cpc_Base implements Cpc_Get_Interface, Cpc_Cache_Interface, Cpc_Interface
{
    protected static $check_group_price_for_old_projects = false;
    // products_prices.products_group_price <> -1
    /**
     * Returns [categoriesId => count] array
     * @param $platformId
     * @param $groupId
     * @param $categoriesIds
     * @return array An empty array is returned if the query results in nothing.
     */
    protected static function run_query($platform_id, $group_id = 0, $categories_ids = null, $optimize_msg = true): array
    {
        $res = self::get_query($platform_id, $group_id, $categories_ids)->column();
        if (is_array($categories_ids)) {
            foreach ($categories_ids as $cat_id) {
                if (!isset($res[$cat_id])) {
                    $res[$cat_id] = 0;
                }
            }
        }
        return $res;
    }
    protected static function get_query($platform_id, $group_id = 0, $categories_ids = null)
    {
        $platform_id = (int) $platform_id;
        $q = \common\models\Products2Categories::find()->alias('p2c')->select('count(*) as total, c1.categories_id')->filter_where(['c1.categories_id' => $categories_ids])->inner_join_with(['platformsCategories pl2c' => function ($query) use ($platform_id) {
            $query->and_on_condition(['pl2c.platform_id' => $platform_id]);
        }], false)->inner_join_with(['platformsProducts pl2p' => function ($query) use ($platform_id) {
            $query->and_on_condition(['pl2p.platform_id' => $platform_id]);
        }], false)->inner_join_with('categories c', false)->inner_join('categories c1', 'c.categories_left >= c1.categories_left AND c.categories_right <= c1.categories_right AND c.categories_status = 1')->inner_join('products p USE INDEX(idx_id_status)', 'p.products_id = p2c.products_id')->and_where('p.products_status = 1')->group_by('c1.categories_id')->as_array()->index_by('categories_id');
        if ($group_id > 0 && ($g_cat = Extensions::get_model('UserGroupsRestrictions', 'GroupsCategories')) && $g_prod = Extensions::get_model('UserGroupsRestrictions', 'GroupsProducts')) {
            $q = $q->inner_join($g_cat::table_name() . ' g2c', 'g2c.categories_id = c1.categories_id AND g2c.groups_id = :groupId', ['groupId' => $group_id])->inner_join($g_prod::table_name() . ' g2p', 'g2p.products_id = p2c.products_id AND g2p.groups_id = :groupId', ['groupId' => $group_id]);
        }
        if (self::$check_group_price_for_old_projects) {
            $q = $q->join_with(['productsPrices pgp' => function ($query) use ($group_id) {
                $query->and_on_condition(['pgp.currencies_id' => 0, 'pgp.groups_id' => $group_id]);
            }], false)->and_where('COALESCE(pgp.products_group_price,1) != -1');
        }
        return $q;
    }
    protected static function get_query_exists($platform_id, $group_id = 0, $categories_ids = null)
    {
        $platform_id = (int) $platform_id;
        $q = \common\models\Categories::find()->alias('c1')->select('count(*) as total, c1.categories_id')->filter_where(['c1.categories_id' => $categories_ids])->inner_join_with(['platformsCategories pl2c' => function ($query) use ($platform_id) {
            $query->and_on_condition(['pl2c.platform_id' => $platform_id]);
        }], false)->inner_join_with(['platformsProducts pl2p' => function ($query) use ($platform_id) {
            $query->and_on_condition(['pl2p.platform_id' => $platform_id]);
        }], false)->inner_join_with('categories c', false)->and_where('c.categories_left >= c1.categories_left AND c.categories_right <= c1.categories_right AND c.categories_status = 1')->join_with(['products p' => function ($query) use ($platform_id) {
            $query->and_on_condition(['p.products_status' => 1]);
        }], false)->as_array()->index_by('categories_id');
        if ($group_id > 0 && ($g_cat = Extensions::get_model('UserGroupsRestrictions', 'GroupsCategories')) && $g_prod = Extensions::get_model('UserGroupsRestrictions', 'GroupsProducts')) {
            $q = $q->inner_join($g_cat::table_name() . ' g2c', 'g2c.categories_id = c1.categories_id AND g2c.groups_id = :groupId', ['groupId' => $group_id])->inner_join($g_prod::table_name() . ' g2p', 'g2p.products_id = products_id AND g2c.groups_id => :groupId', ['groupId' => $group_id]);
        }
        if (self::$check_group_price_for_old_projects) {
            $q = $q->join_with(['productsPrices pgp' => function ($query) use ($group_id) {
                $query->and_on_condition(['pgp.currencies_id' => 0, 'pgp.groups_id' => $group_id]);
            }], false)->and_where('COALESCE(pgp.products_group_price,1) != -1');
        }
        return $q;
    }
    /**
     * @inheritDoc
     */
    public static function get_all_categories($platform_id, $group_id = 0): array
    {
        $q = \common\models\Categories::find()->alias('c')->select('c.categories_id')->inner_join_with('platforms pl', false)->where(['pl.platform_id' => $platform_id]);
        if ($group_id > 0 && $g_cat = Extensions::get_model('UserGroupsRestrictions', 'GroupsCategories')) {
            $q = $q->inner_join($g_cat::table_name() . ' g2c', 'g2c.categories_id = c.categories_id AND g2c.groups_id = :groupId', ['groupId' => $group_id]);
        }
        return static::get_categories($q->column(), $platform_id, $group_id);
    }
    /** stabs for CPCCacheInterface */
    public static function get_count_and_cache($platform_id, $group_id = 0)
    {
        return static::get_all_categories($platform_id, $group_id);
    }
    public static function get_cached($platform_id, $group_id = 0)
    {
    }
    public static function invalidate_products($product_ids): void
    {
    }
    public static function invalidate_categories($categories_ids): void
    {
    }
    public static function invalidate_platforms($platforms_ids): void
    {
    }
    public static function invalidate_groups($groups_ids): void
    {
    }
    public static function invalidate_all(): void
    {
    }
}