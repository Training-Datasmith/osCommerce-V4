<?php

declare (strict_types=1);
namespace common\classes\CPC;

/**
 * Cache for CPC
 */
interface Cpc_Cache_Interface
{
    /**
     * Returns cached array [categoryId => productCount] for platform and group if cached. Return null if not cached
     * @param mixed $platformId
     * @param mixed $groupId
     * @return null|array [categoryId => productCount]
     */
    public static function get_cached($platform_id, $group_id = 0);
    /**
     * Invalidated cache for categories related for specified products
     * @param mixed $productIds single productId or array of productsId
     */
    public static function invalidate_products($product_ids): void;
    /**
     * Invalidated cache for specified categories
     * @param mixed|array $categoriesIds single categoryId or array of categoryId
     * @return void
     */
    public static function invalidate_categories($categories_ids): void;
    /**
     * Invalidates cache for specified platforms
     * @return void
     */
    public static function invalidate_platforms($platforms_ids): void;
    /**
     * Invalidates cache for specified groups
     * @return void
     */
    public static function invalidate_groups($groups_ids): void;
    /**
     * Invalidates all cache for products count
     * @return void
     */
    public static function invalidate_all(): void;
}