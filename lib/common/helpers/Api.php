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

class Api
{
    public static function generate_api_key()
    {
        $__server_part = tep_db_fetch_array(tep_db_query('SELECT UUID() AS server_part'));
        return strtolower(str_replace('-', '', $__server_part['server_part']) . \common\helpers\Password::create_random_value(16));
    }
    public static function get_department_server_key_value($department_id, $key_name)
    {
        $value = null;
        $get_kv_r = tep_db_query('SELECT key_value ' . 'FROM ' . TABLE_EP_HOLBI_SOAP_SERVER_KV_STORAGE . ' ' . "WHERE departments_id='" . (int) $department_id . "' " . " AND key_name='" . tep_db_input($key_name) . "'");
        if (tep_db_num_rows($get_kv_r) > 0) {
            $get_kv = tep_db_fetch_array($get_kv_r);
            $value = $get_kv['key_value'];
            tep_db_free_result($get_kv_r);
        }
        return $value;
    }
    public static function set_department_server_key_value($department_id, $key_name, $value)
    {
        tep_db_query('INSERT INTO ep_holbi_soap_server_kv_storage (departments_id, key_name, key_value) ' . "VALUES ('" . (int) $department_id . "', '" . tep_db_input($key_name) . "','" . tep_db_input($value) . "') " . "ON DUPLICATE KEY UPDATE key_value='" . tep_db_input($value) . "'");
    }
    public static function update_customer_modify_time($customer_id = null)
    {
        tep_db_query('UPDATE ' . TABLE_CUSTOMERS . ' c ' . '  INNER JOIN ' . TABLE_ADDRESS_BOOK . ' ab ON ab.customers_id=c.customers_id ' . '  SET c._api_time_modified = GREATEST(c._api_time_modified,IFNULL(ab._api_time_modified,0)) ' . 'WHERE 1 ' . (is_numeric($customer_id) ? " AND c.customers_id='" . (int) $customer_id . "' " : ''));
    }
    public static function allow_api_create_category($department_id)
    {
        $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_DEPARTMENTS . ' ' . "WHERE departments_id='" . (int) $department_id . "' AND api_categories_allow_create !=0 "));
        $allow = $check['c'] > 0;
        return $allow;
    }
    public static function allow_api_update_category($department_id)
    {
        $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_DEPARTMENTS . ' ' . "WHERE departments_id='" . (int) $department_id . "' AND api_categories_allow_update !=0 "));
        $allow = $check['c'] > 0;
        return $allow;
    }
    public static function allow_api_create_product($department_id)
    {
        $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_DEPARTMENTS . ' ' . "WHERE departments_id='" . (int) $department_id . "' AND api_products_allow_create !=0 "));
        $allow = $check['c'] > 0;
        return $allow;
    }
    public static function allow_api_update_product($department_id)
    {
        $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_DEPARTMENTS . ' ' . "WHERE departments_id='" . (int) $department_id . "' AND api_products_allow_update !=0 "));
        $allow = $check['c'] > 0;
        return $allow;
    }
    public static function allow_api_remove_product($department_id)
    {
        $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_DEPARTMENTS . ' ' . "WHERE departments_id='" . (int) $department_id . "' AND api_products_allow_remove_owned !=0 "));
        $allow = $check['c'] > 0;
        return $allow;
    }
    public static function product_flags()
    {
        return [['label' => 'Name and description', 'server' => 'description_server', 'server_own' => 'description_server_own', 'client' => 'description_client'], ['label' => 'SEO', 'server' => 'seo_server', 'server_own' => 'seo_server_own', 'client' => 'seo_client'], ['label' => 'Prices', 'server' => 'prices_server', 'server_disable' => true, 'server_own' => 'prices_server_own', 'client' => 'prices_client'], ['label' => 'Stock', 'server' => 'stock_server', 'server_own' => 'stock_server_own', 'client' => 'stock_client'], ['label' => 'Attributes and inventory', 'server' => 'attr_server', 'server_own' => 'attr_server_own', 'client' => 'attr_client'], ['label' => 'Product identifiers', 'server' => 'identifiers_server', 'server_own' => 'identifiers_server_own', 'client' => 'identifiers_client'], ['label' => 'Images', 'server' => 'images_server', 'server_own' => 'images_server_own', 'client' => 'images_client'], ['label' => 'Size and Dimensions', 'server' => 'dimensions_server', 'server_own' => 'dimensions_server_own', 'client' => 'dimensions_client'], ['label' => 'Properties', 'server' => 'properties_server', 'server_own' => 'properties_server_own', 'client' => 'properties_client']];
    }
    public static function apply_department_outgoing_price_formula($price, $disable_calculate = null)
    {
        $params = \Yii::$app->get('department')->get_api_outgoing_price_params();
        $params['price'] = $price;
        $formula = \Yii::$app->get('department')->get_api_outgoing_price_formula();
        $product_formula = \common\classes\Api_Department::get()->get_current_response_product_price_formula_data();
        if (is_array($product_formula) && is_array($product_formula['formula'])) {
            $formula = $product_formula['formula'];
            $params['discount'] = $product_formula['discount'];
            $params['surcharge'] = $product_formula['surcharge'];
            $params['margin'] = $product_formula['margin'];
        }
        $result = \common\helpers\Price_Formula::apply($formula, $params);
        if (is_numeric($result)) {
            return $result;
        }
        return $price;
    }
}