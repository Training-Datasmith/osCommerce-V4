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
namespace backend\models;

use frontend\design\Info;
class Product_Name_Decorator
{
    protected $config = [];
    private function __construct()
    {
    }
    /**
     * @return null|self
     */
    public static function instance()
    {
        static $obj = null;
        if (!is_object($obj)) {
            $obj = new static();
            if (defined('BACKEND_PRODUCT_NAME_FORMAT')) {
                $obj->config = preg_split('/,\s?/', strval(BACKEND_PRODUCT_NAME_FORMAT), -1, PREG_SPLIT_NO_EMPTY);
            }
        }
        return $obj;
    }
    public static function get_internal_name($products_id, $language_id, $platform_id = null)
    {
        if (empty($platform_id)) {
            $platform_id = \common\classes\platform::default_id();
        }
        $description_platform_id = intval(\Yii::$app->get('platform')->get_config($platform_id)->get_platform_to_description());
        if (empty($language_id)) {
            $language_id = \common\classes\language::get_id(\Yii::$app->get('platform')->get_config($platform_id)->get_default_language());
        }
        $products_internal_name_query = tep_db_query('select if(length(pd1.products_internal_name), pd1.products_internal_name, pd.products_internal_name) as products_internal_name, ' . ' if(length(pd1.products_name), pd1.products_name, pd.products_name) as products_name ' . 'from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ' . '  left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd1.products_id = pd.products_id and pd1.language_id='" . (int) $language_id . "' and pd1.platform_id = '" . (int) $description_platform_id . "' " . "where pd.products_id = '" . (int) $products_id . "' and pd.platform_id = '" . (int) $platform_id . "' and pd.language_id = '" . (int) $language_id . "'");
        if (tep_db_num_rows($products_internal_name_query) > 0) {
            $products_internal_name = tep_db_fetch_array($products_internal_name_query);
            if (!empty($products_internal_name['products_internal_name'])) {
                return $products_internal_name['products_internal_name'];
            }
            //return $products_internal_name['products_name'];
        }
        return false;
    }
    public function use_internal_name_for_listing()
    {
        return in_array('Listing', $this->config);
    }
    public function use_internal_name_for_order()
    {
        return in_array('Orders', $this->config);
    }
    public function use_internal_name_for_packing_slip()
    {
        return in_array('PackingSlip', $this->config);
    }
    public function use_internal_name_for_invoice()
    {
        return in_array('Invoice', $this->config);
    }
    public function get_updated_order_products($products, $language_id, $platform_id)
    {
        foreach ($products as $idx => $order_product) {
            $products[$idx]['_name'] = $products[$idx]['name'];
            $internal_name = static::get_internal_name($order_product['id'], $language_id, $platform_id);
            if (!empty($internal_name)) {
                $products[$idx]['name'] = $internal_name;
            }
        }
        return $products;
    }
    public function listing_query_expression($main_table_alias = 'pd', $extra_table_alias = 'pd1')
    {
        if (Info::is_totally_admin() && $this->use_internal_name_for_listing()) {
            $internal_column = "IF(LENGTH({$extra_table_alias}.products_internal_name), {$extra_table_alias}.products_internal_name, {$main_table_alias}.products_internal_name)";
            $main_column = "IF(LENGTH({$extra_table_alias}.products_name), {$extra_table_alias}.products_name, {$main_table_alias}.products_name)";
            if (empty($extra_table_alias)) {
                if (!empty($main_table_alias)) {
                    $main_table_alias = $main_table_alias . '.';
                }
                $internal_column = $main_table_alias . 'products_internal_name';
                $main_column = $main_table_alias . 'products_name';
            }
            return "IF(LENGTH({$internal_column}), {$internal_column}, {$main_column})";
        } else {
            $main_column = "IF(LENGTH({$extra_table_alias}.products_name), {$extra_table_alias}.products_name, {$main_table_alias}.products_name)";
            if (empty($extra_table_alias)) {
                if (!empty($main_table_alias)) {
                    $main_table_alias = $main_table_alias . '.';
                }
                $main_column = $main_table_alias . 'products_name';
            }
            return $main_column;
        }
    }
    public static function description_expr($alias = 'pd')
    {
        $alias = is_null($alias) ? \common\models\Products_Description::table_name() : $alias;
        return new \yii\db\Expression(self::instance()->listing_query_expression($alias, ''));
    }
}