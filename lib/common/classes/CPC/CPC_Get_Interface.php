<?php

declare (strict_types=1);
namespace common\classes\CPC;

/**
 * CPC - Counts of products in categories
 */
interface Cpc_Get_Interface
{
    /**
     * Returns array [categoryId => productCount]
     * @param mixed|array $categoriesIds single categoryId or array of categoryId
     * @param mixed $platformId
     * @param mixed $groupId
     * @return array [categoryId => productCount]
     */
    public static function get_categories($categories_ids, $platform_id, $group_id = 0): array;
    public static function get_all_categories($platform_id, $group_id = 0): array;
}