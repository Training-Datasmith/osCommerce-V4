<?php

declare (strict_types=1);
namespace common\classes\CPC;

class Cpc_Without_Cache extends Cpc_Base
{
    /**
     * @inheritDoc
     */
    public static function get_categories($categories_ids, $platform_id, $group_id = 0): array
    {
        return static::run_query($platform_id, $group_id, $categories_ids);
    }
}