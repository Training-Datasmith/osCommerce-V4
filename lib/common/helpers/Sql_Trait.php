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

use common\classes\platform;
trait Sql_Trait
{
    public static function sql_categories_to_platform($platform_id = 0)
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        $sql = ' inner join ' . TABLE_PLATFORMS_CATEGORIES . " plc on p2c.categories_id = plc.categories_id  and plc.platform_id = '" . ($platform_id ? $platform_id : platform::current_id()) . "' ";
        if (\common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
            $sql .= " INNER JOIN groups_categories as gc ON gc.categories_id = p2c.categories_id and gc.groups_id = '{$customer_groups_id}' ";
        }
        return $sql;
    }
    public static function sql_products_to_customer($customer_id)
    {
        return " left join personal_catalog pc on p.products_id = pc.products_id  and pc.customers_id = '" . $customer_id . "' \n        LEFT JOIN inventory AS inv ON inv.products_id = pc.uprid ";
    }
    public static function sql_products_to_platform($platform_id = 0)
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        $sql = ' inner join ' . TABLE_PLATFORMS_PRODUCTS . " plp on p.products_id = plp.products_id  and plp.platform_id = '" . ($platform_id ? $platform_id : platform::current_id()) . "' ";
        if (\common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
            $sql .= " INNER JOIN groups_products as gp ON gp.products_id = p.products_id and gp.groups_id = '{$customer_groups_id}' ";
        }
        return $sql;
    }
    public function sql_products_model_to_platform($platform_id = 0)
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        $this->inner_join('platforms_products plp', "p.products_id = plp.products_id  and plp.platform_id = '" . ($platform_id ? $platform_id : platform::current_id()) . "'");
        if (\common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
            $this->inner_join('groups_products gp', "gp.products_id = p.products_id and gp.groups_id = '{$customer_groups_id}'");
        }
    }
    public static function sql_products_to_platform_categories()
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        $sql = ' inner join ' . TABLE_PLATFORMS_PRODUCTS . " plp on p.products_id = plp.products_id  and plp.platform_id = '" . platform::current_id() . "' " . ' inner join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p2c.products_id=p.products_id ' . ' inner join ' . TABLE_PLATFORMS_CATEGORIES . " plc ON plc.categories_id=p2c.categories_id AND plc.platform_id = '" . platform::current_id() . "' ";
        if (\common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
            $sql .= " INNER JOIN groups_products as gp ON gp.products_id = p.products_id and gp.groups_id = '{$customer_groups_id}' ";
            $sql .= " INNER JOIN groups_categories as gc ON gc.categories_id = p2c.categories_id and gc.groups_id = '{$customer_groups_id}' ";
        }
        return $sql;
    }
    public static function sql_products_to_pref_platform_categories($platform_id)
    {
        return ' inner join ' . TABLE_PLATFORMS_PRODUCTS . " plp on p.products_id = plp.products_id  and plp.platform_id = '" . $platform_id . "' " . ' inner join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p2c.products_id=p.products_id ' . ' inner join ' . TABLE_PLATFORMS_CATEGORIES . " plc ON plc.categories_id=p2c.categories_id AND plc.platform_id = '" . $platform_id . "' ";
    }
}