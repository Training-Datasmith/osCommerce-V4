<?php

declare (strict_types=1);
namespace common\classes\CPC;

use yii\caching\Tag_Dependency;
class Cpc_File_Cache extends Cpc_Base implements Cpc_Cache_Interface
{
    public static $cache;
    /**
     * @inheritDoc
     */
    public static function get_categories($categories_ids, $platform_id, $group_id = 0): array
    {
        $res = [];
        $not_cached_ids = self::add_result_from_cache($res, $categories_ids, $platform_id, $group_id);
        if (!empty($not_cached_ids)) {
            self::update_cache_with_new_cats($not_cached_ids, $platform_id, $group_id);
            self::add_result_from_cache($res, $not_cached_ids, $platform_id, $group_id);
        }
        return $res;
    }
    /**
     * @inheritDoc
     */
    public static function get_cached($platform_id, $group_id = 0)
    {
        $cache_id = self::get_cache_name($platform_id, $group_id);
        if (!isset(self::$cache[$cache_id]) && $arr = \Yii::$app->get_cache()->get($cache_id)) {
            self::$cache[$cache_id] = $arr;
        }
        return self::$cache[$cache_id] ?? null;
    }
    /**
     * @inheritDoc
     */
    public static function invalidate_products($product_ids): void
    {
        $categories_ids = \common\models\Products2Categories::find()->select('categories_id')->where(['products_id' => $product_ids])->as_array()->column();
        self::invalidate_categories($categories_ids);
    }
    /**
     * @inheritDoc
     */
    public static function invalidate_categories($categories_ids): void
    {
        if (empty($categories_ids)) {
            return;
        }
        if (!is_array($categories_ids)) {
            $categories_ids = [$categories_ids];
        }
        // include all parents
        $categories_ids = \common\models\Categories::find()->alias('c')->with_nested_categories()->select('c.categories_id')->filter_where(['c1.categories_id' => $categories_ids])->as_array()->column();
        $platforms_ids = \common\models\Platforms_Categories::find()->select('platform_id')->where(['categories_id' => $categories_ids])->distinct()->column();
        if (\common\helpers\Extensions::is_allowed('UserGroupsRestrictions')) {
            $groups_ids = \common\models\Groups::find()->select('groups_id')->column();
            if (count($groups_ids) * count($platforms_ids) > 10) {
                // clear cache, too expensive to modify the cache in a large number of files
                self::invalidate_platforms($platforms_ids);
            } else {
                foreach ($platforms_ids as $platforms_id) {
                    foreach ($groups_ids as $groups_id) {
                        self::update_cache_by_removing_cats($categories_ids, $platforms_id, $groups_id);
                    }
                }
            }
        } else {
            foreach ($platforms_ids as $platforms_id) {
                self::update_cache_by_removing_cats($categories_ids, $platforms_id);
            }
        }
    }
    /**
     * @inheritDoc
     */
    public static function invalidate_all(): void
    {
        self::$cache = null;
        Tag_Dependency::invalidate(\Yii::$app->cache, 'cpcc_all');
    }
    /**
     * @inheritDoc
     */
    public static function invalidate_groups($groups_ids): void
    {
        $groups_ids = is_array($groups_ids) ? $groups_ids : [(int) $groups_ids];
        foreach ($groups_ids as $groups_id) {
            Tag_Dependency::invalidate(\Yii::$app->cache, 'cpcc_group' . $groups_id);
        }
    }
    /**
     * @inheritDoc
     */
    public static function invalidate_platforms($platforms_ids): void
    {
        if (!is_array($platforms_ids)) {
            $platforms_ids = [$platforms_ids];
        }
        if (!empty($platforms_ids)) {
            self::$cache = null;
            foreach ($platforms_ids as $platform_id) {
                Tag_Dependency::invalidate(\Yii::$app->cache, 'cpcc_platform' . $platform_id);
            }
        }
    }
    /**
     * @param $res result array [ categoryId => productsCount ]
     * @param int|array $categoriesIds categoryId/Ids
     * @param $platformId
     * @param $groupId
     * @return array|mixed non cached Ids
     */
    private static function add_result_from_cache(&$res, $categories_ids, $platform_id, $group_id)
    {
        $not_cached_ids = [];
        if (!is_array($categories_ids)) {
            $categories_ids = [$categories_ids];
        }
        $cached = self::get_cached($platform_id, $group_id);
        if (empty($cached)) {
            $not_cached_ids = $categories_ids;
        } else {
            foreach ($categories_ids as $category_id) {
                if (isset($cached[$category_id])) {
                    $res[$category_id] = $cached[$category_id];
                } else {
                    $not_cached_ids[] = $category_id;
                }
            }
        }
        return $not_cached_ids;
    }
    private static function update_cache_with_new_cats(array $categories_ids, $platform_id, $group_id = 0)
    {
        $res = parent::run_query($platform_id, $group_id, $categories_ids);
        foreach ($categories_ids as $categoriy_id) {
            if (!isset($res[$categoriy_id])) {
                $res[$categoriy_id] = 0;
            }
        }
        $res = array_replace(self::get_cached($platform_id, $group_id) ?? [], $res);
        self::set_cache($platform_id, $group_id, $res);
    }
    private static function update_cache_by_removing_cats(array $categories_ids, $platform_id, $group_id = 0)
    {
        $res = self::get_cached($platform_id, $group_id);
        if (!empty($res)) {
            foreach ($categories_ids as $categories_id) {
                unset($res[$categories_id]);
            }
            self::set_cache($platform_id, $group_id, $res);
        }
    }
    private static function set_cache($platform_id, $group_id, array $res)
    {
        $cache_id = self::get_cache_name($platform_id, $group_id);
        \Yii::$app->get_cache()->set($cache_id, $res, 0, new Tag_Dependency(['tags' => ['cpcc_all', 'cpcc_platform' . $platform_id, 'cpcc_group' . $group_id]]));
        self::$cache[$cache_id] = $res;
    }
    private static function get_cache_name($platform_id, $group_id = 0)
    {
        return sprintf('CategoriesProductCountCache%d-%d', (int) $platform_id, (int) $group_id);
    }
}