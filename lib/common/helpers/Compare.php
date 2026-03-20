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

class Compare
{
    public static function get_category_id($product_id)
    {
        $category_id = \common\models\Products2Categories::find()->where(['products_id' => $product_id])->and_where(['!=', 'categories_id', 0])->one()->categories_id ?? null;
        return self::get_category_id_by_category($category_id);
    }
    public static function get_category_id_by_category($category_id)
    {
        $parent_id = true;
        $cutout = 20;
        while ($category_id && $parent_id && $cutout) {
            $parent_id = \common\models\Categories::find_one(['categories_id' => $category_id])->parent_id ?? null;
            if ($parent_id) {
                $category_id = $parent_id;
            }
            $cutout--;
        }
        return $category_id;
    }
}