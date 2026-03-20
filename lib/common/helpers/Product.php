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

use backend\models\Product_Name_Decorator;
use common\classes\platform;
use common\helpers\Inventory as InventoryHelper;
use Yii;
defined('ALLOW_ANY_QUERY_CACHE') or define('ALLOW_ANY_QUERY_CACHE', 'True');
class Product
{
    use Sql_Trait;
    public const PRODUCT_RECORD_CACHE = 1;
    public static function get_temporary_stock_table_name()
    {
        return \common\helpers\Warehouses::get_temporary_stock_table_name();
    }
    public static function is_sub_product_with_price()
    {
        return false;
    }
    public static function price_product_id_column()
    {
        if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True') {
            return 'products_id_price';
        }
        return 'products_id';
    }
    public static function stock_product_id_column()
    {
        if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True') {
            return 'products_id_stock';
        }
        return 'products_id';
    }
    public static function sub_product_main_attributes_share()
    {
        static $table_columns = false;
        if (!is_array($table_columns)) {
            $table_columns = Yii::$app->get_db()->get_table_schema('products')->get_column_names();
            $table_columns = array_flip($table_columns);
            $except_columns = ['products_id', 'products_model', 'products_date_added', 'products_last_modified', 'products_status', 'products_status_bundle', 'manual_control_status', 'products_ordered', 'products_seo_page_name', 'products_old_seo_page_name', 'sort_order', 'previous_status', 'last_xml_import', 'last_xml_export', 'products_ean', 'products_asin', 'products_isbn', 'products_upc', 'products_popularity', 'popularity_simple', 'popularity_bestseller', 'created_by_platform_id', 'is_listing_product', 'parent_products_id', 'products_id_stock', 'products_id_price', 'maps_id'];
            foreach ($except_columns as $except_column) {
                unset($table_columns[$except_column]);
            }
            $table_columns = array_values(array_flip($table_columns));
        }
        return $table_columns;
    }
    public static function is_listing($product_id)
    {
        return !!\common\models\Products::find()->where(['products_id' => $product_id])->select(['is_listing_product'])->scalar();
    }
    public static function child_detach($child_product_id)
    {
        if ($product = \common\models\Products::find_one($child_product_id)) {
            $product->parent_products_id = 0;
            if ($product->save(false)) {
                return true;
            }
        }
        return false;
    }
    public static function child_attach($child_product_id, $parent_product_id)
    {
        if ($product = \common\models\Products::find_one($child_product_id)) {
            $product->parent_products_id = $parent_product_id;
            if ($product->save(false)) {
                \common\helpers\Sub_Product::copy_attributes_from_parent($child_product_id);
                return true;
            }
        }
        return false;
    }
    /**
     * @return \common\components\ProductItem
     */
    public static function item_instance($params)
    {
        /**
         * @var $productContainer \common\components\ProductsContainer
         */
        if (!is_array($params)) {
            $params = ['products_id' => $params];
        }
        $product_container = \Yii::$container->get('products');
        $product_container->load_products($params);
        return $product_container->get_product($params['products_id']);
    }
    public static function get_state($and = false)
    {
        /* @var $ext \common\extensions\ShowInactive\ShowInactive */
        if ($ext = \common\helpers\Extensions::is_allowed('ShowInactive')) {
            return $ext::get_state($and);
        } else {
            return ($and ? ' and ' : ' ') . ' p.products_status = 1 ';
        }
    }
    public static function price_product_id($unified_product_id)
    {
        if (preg_match('/^(\d+)\{/', $unified_product_id, $match)) {
            $unified_product_id = \common\helpers\Product::normalize_price_prid((int) $match[1]) . substr($unified_product_id, strlen($match[1]));
            $unified_product_id = \common\helpers\Inventory::normalize_id($unified_product_id);
        } else {
            $unified_product_id = \common\helpers\Product::normalize_price_prid((int) $unified_product_id);
        }
        return $unified_product_id;
    }
    public static function is_sub_product($products_id)
    {
        $parentage = \common\models\Products::find()->where(['products_id' => (int) $products_id])->select('parent_products_id')->as_array()->one();
        return $parentage['parent_products_id'] > 0;
    }
    public static function normalize_price_prid($products_id)
    {
        static $last_normalized = [];
        if (count($last_normalized) > 50) {
            $last_normalized = [];
        }
        if (!isset($last_normalized[$products_id])) {
            ///2do check in the storage first (* from products)
            $last_normalized[$products_id] = (int) $products_id;
            if (self::price_product_id_column() !== 'products_id') {
                $parentage = static::get_product_columns((int) $products_id, [self::price_product_id_column()]);
                if (is_array($parentage) && $parentage[self::price_product_id_column()] > 0) {
                    $last_normalized[$products_id] = (int) $parentage[self::price_product_id_column()];
                    $last_normalized[(int) $parentage[self::price_product_id_column()]] = (int) $parentage[self::price_product_id_column()];
                }
            }
        }
        return $last_normalized[$products_id];
    }
    public static function normalize_prid($products_id)
    {
        static $last_normalized = [];
        if (count($last_normalized) > 50) {
            $last_normalized = [];
        }
        if (!isset($last_normalized[$products_id])) {
            ///2do check in the storage first (* from products)
            $last_normalized[$products_id] = (int) $products_id;
            if (self::stock_product_id_column() == 'products_id') {
                $last_normalized[(int) $products_id] = (int) $products_id;
            } else {
                $parentage = static::get_product_columns((int) $products_id, [self::stock_product_id_column()]);
                if (is_array($parentage) && isset($parentage[self::stock_product_id_column()]) && $parentage[self::stock_product_id_column()] > 0) {
                    $last_normalized[$products_id] = (int) $parentage[self::stock_product_id_column()];
                    $last_normalized[(int) $parentage[self::stock_product_id_column()]] = (int) $parentage[self::stock_product_id_column()];
                }
            }
        }
        return $last_normalized[$products_id];
    }
    /**
     * check product availability by current platform, status, and "product_restrictions" (generally stock indication)
     * @param int $products_id
     * @param bool $check_status
     * @param bool $view true : to display product
     * @param bool $cart - allow share cart between platform
     * @return int
     */
    public static function check_product($products_id, $check_status = 1, $view = false, $cart = false)
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        $products_join = '';
        if (platform::active_id() && $check_status) {
            if (!$cart || !defined('SHOPPING_CART_SHARE') || SHOPPING_CART_SHARE != 'True') {
                $products_join .= self::sql_products_to_platform();
            }
        }
        $force_skip = false;
        foreach (\common\helpers\Hooks::get_list('product/check-product') as $filename) {
            include $filename;
        }
        if ($force_skip) {
            return false;
        }
        if ($view) {
            $state = self::get_state(true);
        } else {
            $state = ' and p.products_status = 1 ';
        }
        if ($customer_groups_id == 0) {
            $products_check_query = tep_db_query('select p.products_id from ' . TABLE_PRODUCTS . " p {$products_join} " . "  where p.products_id = '" . (int) $products_id . "' " . ($check_status ? $state . self::get_sql_product_restrictions(['p', 'pd', 's', 'sp', 'pp']) . '' : ''));
        } else {
            $products_check_query = tep_db_query('select p.products_id from ' . TABLE_PRODUCTS . " p {$products_join} " . ' left join ' . TABLE_PRODUCTS_PRICES . " pgp on p.products_id = pgp.products_id and pgp.groups_id = '" . (int) $customer_groups_id . "'  where if(pgp.products_group_price is null, 1, pgp.products_group_price != -1 ) and p.products_id = '" . (int) $products_id . "'  " . ($check_status ? $state . self::get_sql_product_restrictions(['p', 'pd', 's', 'sp', 'pp']) . '' : ''));
        }
        return tep_db_num_rows($products_check_query);
    }
    public static function get_product_order_quantity($product_id, $data = null)
    {
        static $fetched = [];
        if (!isset($fetched[(int) $product_id]) && is_array($data) && array_key_exists('order_quantity_minimal', $data) && array_key_exists('order_quantity_max', $data) && array_key_exists('order_quantity_step', $data)) {
            $fetched[(int) $product_id] = ['order_quantity_minimal' => $data['order_quantity_minimal'], 'order_quantity_max' => $data['order_quantity_max'], 'order_quantity_step' => $data['order_quantity_step']];
        }
        if (!isset($fetched[(int) $product_id])) {
            $get_data_r = tep_db_query('SELECT order_quantity_minimal, order_quantity_max, order_quantity_step, pack_unit, packaging FROM ' . TABLE_PRODUCTS . " WHERE products_id='" . (int) $product_id . "'");
            if (tep_db_num_rows($get_data_r) > 0) {
                $fetched[(int) $product_id] = tep_db_fetch_array($get_data_r);
                //                if ( $fetched[(int)$product_id]['pack_unit']>0 || $fetched[(int)$product_id]['packaging']>0 ) {
                //                   $fetched[(int)$product_id]['order_quantity_step'] = 1;
                //                }
            } else {
                $fetched[(int) $product_id] = ['order_quantity_minimal' => 1, 'order_quantity_max' => -1, 'order_quantity_step' => 1];
            }
        }
        $fetched[(int) $product_id]['order_quantity_minimal'] = max(1, $fetched[(int) $product_id]['order_quantity_minimal']);
        $fetched[(int) $product_id]['order_quantity_max'] = $fetched[(int) $product_id]['order_quantity_max'];
        $fetched[(int) $product_id]['order_quantity_step'] = max(1, $fetched[(int) $product_id]['order_quantity_step']);
        //$fetched[(int) $product_id]['order_quantity_minimal'] = max($fetched[(int) $product_id]['order_quantity_minimal'], $fetched[(int) $product_id]['order_quantity_step']);
        $fetched[(int) $product_id]['products_id'] = (int) $product_id;
        return $fetched[(int) $product_id];
    }
    public static function filter_product_order_quantity($product_id, $quantity, $quantity_is_top_bound = false)
    {
        $order_qty_data = self::get_product_order_quantity($product_id);
        if ($order_qty_data['order_quantity_minimal'] > $order_qty_data['order_quantity_step']) {
            $result_quantity = max($order_qty_data['order_quantity_minimal'], $quantity, 1);
            $base_qty = $order_qty_data['order_quantity_minimal'];
        } else {
            $result_quantity = max($order_qty_data['order_quantity_minimal'], $quantity, 1);
            $base_qty = 0;
        }
        if ($result_quantity > $order_qty_data['order_quantity_minimal'] && ($result_quantity - $base_qty) % $order_qty_data['order_quantity_step'] != 0) {
            $result_quantity = $base_qty + (intval(($result_quantity - $base_qty) / $order_qty_data['order_quantity_step']) + 1) * $order_qty_data['order_quantity_step'];
        }
        if ($quantity_is_top_bound && $result_quantity > $quantity) {
            $result_quantity = max($order_qty_data['order_quantity_minimal'], $result_quantity - $order_qty_data['order_quantity_step']);
        }
        return $result_quantity;
    }
    public static function get_product_path($products_id)
    {
        static $last_call_result = [];
        if (!empty($last_call_result['products_id']) && (int) $last_call_result['products_id'] == (int) $products_id) {
            return $last_call_result['cPath'];
        }
        $c_path = '';
        if (!self::check_product($products_id, 1, true)) {
            return '';
        }
        $categories_join = '';
        if (platform::active_id()) {
            $categories_join .= self::sql_categories_to_platform();
        }
        $linked_categories = Yii::$app->get_db()->create_command('select p2c.categories_id ' . 'from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . " p2c {$categories_join}, " . TABLE_CATEGORIES . ' c ' . "where p.products_id = '" . (int) $products_id . "' " . self::get_state(true) . self::get_sql_product_restrictions(['p', 'pd', 's', 'sp', 'pp']) . ' and p.products_id = p2c.products_id and c.categories_id=p2c.categories_id and c.categories_status=1')->query_all();
        $category = false;
        if (count($linked_categories) >= 1) {
            $category = $linked_categories[0];
            if (count($linked_categories) > 1 && strpos(Yii::$app->id, 'frontend') !== false) {
                if (Yii::$app->has('request') && Yii::$app->request instanceof \yii\web\Request) {
                    $ref_path = \parse_url(trim(Yii::$app->request->get_referrer() ?? ''), PHP_URL_PATH);
                    foreach ($linked_categories as $check_category) {
                        $_link = Yii::$app->get_url_manager()->create_absolute_url(['catalog/index', 'cPath' => $check_category['categories_id']]);
                        if (\parse_url($_link, PHP_URL_PATH) == $ref_path) {
                            $category = $check_category;
                            break;
                        }
                    }
                }
            }
        }
        if ($category) {
            $categories = [];
            \common\helpers\Categories::get_parent_categories($categories, $category['categories_id']);
            $categories = array_reverse($categories);
            $c_path = implode('_', $categories);
            if (tep_not_null($c_path)) {
                $c_path .= '_';
            }
            $c_path .= $category['categories_id'];
        }
        $last_call_result = ['products_id' => (int) $products_id, 'cPath' => $c_path];
        return $c_path;
    }
    public static function get_product_weight($uprid, $qty = 1)
    {
        $products_weight = $qty * self::get_products_weight($uprid);
        if (\common\helpers\Extensions::is_allowed('Inventory') && !Inventory_Helper::disabled_on_product($uprid)) {
            $simple_uprid = Inventory_Helper::normalize_id($uprid);
            if (($inventory_weight = Inventory_Helper::get_inventory_weight_by_uprid($simple_uprid)) > 0) {
                $products_weight += $qty * $inventory_weight;
            }
        } else {
            /*if (isset($this->contents[$products_id]['attributes'])) {
                  reset($this->contents[$products_id]['attributes']);
                  if (is_array($this->contents[$products_id]['attributes'])) {
                      foreach ($this->contents[$products_id]['attributes'] as $option => $value) {
                          $option_arr = explode('-', $option);
                          $attribute_price_query = tep_db_query("select products_attributes_id, options_values_price, price_prefix, products_attributes_weight, products_attributes_weight_prefix from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) ($option_arr[1] > 0 ? $option_arr[1] : $prid) . "' and options_id = '" . (int) $option_arr[0] . "' and options_values_id = '" . (int) $value . "'");
                          $attribute_price = tep_db_fetch_array($attribute_price_query);
                          if (tep_not_null($attribute_price['products_attributes_weight'])) {
                              if ($attribute_price['products_attributes_weight_prefix'] == '+' || $attribute_price['products_attributes_weight_prefix'] == '') {
                                  $products_weight += $qty * $attribute_price['products_attributes_weight'];
                              } else {
                                  $products_weight -= $qty * $attribute_price['products_attributes_weight'];
                              }
                          }
                      }
                  }
              }*/
        }
        return $products_weight;
    }
    public static function get_products_weight($products_id)
    {
        $ret = 0;
        $product = tep_db_fetch_array(tep_db_query('select is_bundle, products_id, products_weight, products_file from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'"));
        if (empty($product['products_file'])) {
            // same as in shopping_cart
            if ($product['is_bundle']) {
                if ($ext = \common\helpers\Acl::check_extension_allowed('ProductBundles', 'allowed')) {
                    $ret = $ext::get_weight($product);
                }
            } else {
                $ret = $product['products_weight'];
            }
        }
        return $ret;
    }
    public static function get_manufacturers_name($product_id)
    {
        $manufacturers_query = tep_db_query('select manufacturers_name from ' . TABLE_MANUFACTURERS . ' m, ' . TABLE_PRODUCTS . " p where p.manufacturers_id = m.manufacturers_id and p.products_id='" . (int) $product_id . "'");
        $manufacturers = tep_db_fetch_array($manufacturers_query);
        return $manufacturers['manufacturers_name'];
    }
    public static function get_products_volume(int $products_id, bool $weight = false)
    {
        $product = tep_db_fetch_array(tep_db_query('select is_bundle, products_id, length_cm, width_cm, height_cm, bundle_volume_calc, volume_weight_cm from ' . TABLE_PRODUCTS . " where products_id = '" . $products_id . "'"));
        if ($product['is_bundle']) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductBundles', 'allowed')) {
                return $ext::get_volume($product, $weight);
            }
        }
        $volume = $product['length_cm'] * $product['width_cm'] * $product['height_cm'];
        if ($weight) {
            $product['volume_weight_cm'] = (float) $product['volume_weight_cm'];
            return $product['volume_weight_cm'] > 0.0 ? $product['volume_weight_cm'] : $volume / VOLUME_WEIGHT_COEFFICIENT;
        }
        return $volume;
    }
    public static function convert_kgs_to_lbs($weight)
    {
        return round($weight * 2.20462, 2);
    }
    public static function convert_lbs_to_kgs($weight)
    {
        return round($weight / 2.20462, 3);
    }
    public static function convert_inch_to_cm($size)
    {
        return round($size * 2.54, 1);
    }
    public static function convert_cm_to_inch($size)
    {
        return round($size / 2.54, 2);
    }
    public static function get_product_columns($products_id, $fields)
    {
        $column_values = [];
        static $container;
        if (!is_object($container) && Yii::$container->has('products')) {
            $container = Yii::$container->get('products');
        }
        if (is_object($container) && $container->has((int) $products_id)) {
            $product_item = $container->get_product((int) $products_id);
            foreach ($fields as $idx => $field) {
                if (array_key_exists($field, (array) $product_item)) {
                    $column_values[$field] = $product_item[$field];
                    unset($fields[$idx]);
                }
            }
        }
        if (count($fields) > 0) {
            $missing_values = Yii::$app->get_db()->create_command('select `' . implode('`, `', $fields) . '` ' . 'from ' . TABLE_PRODUCTS . ' ' . "where products_id = '" . (int) $products_id . "'")->query_one();
            $column_values = array_merge($column_values, (array) $missing_values);
        }
        return $column_values;
    }
    public static function get_products_info($products_id, $field)
    {
        $product = static::get_product_columns($products_id, [$field]);
        return $product[$field] ?? null;
    }
    public static function get_backend_products_name($product_id, $language = '', $platform_id = '', $search_terms = [])
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if (empty($language)) {
            $language = $languages_id;
        }
        $_def = \common\classes\platform::default_id();
        $platform_id = (int) ($platform_id ? $platform_id : $_def);
        $product_query = tep_db_query('select ' . Product_Name_Decorator::instance()->listing_query_expression('pd', 'pd1') . ' as products_name from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd.products_id = pd1.products_id and pd1.platform_id = '" . intval($platform_id) . "' and pd1.language_id = '" . (int) $language . "' where pd.products_id = '" . (int) $product_id . "' and pd.language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "' and pd.platform_id = '" . $_def . "'");
        $product = tep_db_fetch_array($product_query);
        if (!isset($product['products_name'])) {
            return '';
        }
        if (sizeof($search_terms) == 0) {
            return $product['products_name'];
        } else if (defined('MSEARCH_HIGHLIGHT_ENABLE') && MSEARCH_HIGHLIGHT_ENABLE == 'true') {
            return \common\helpers\Output::highlight_text($product['products_name'], $search_terms);
        } else {
            return $product['products_name'];
        }
    }
    public static function get_products_name($product_id, $language = '', $platform_id = '', $search_terms = [])
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if (empty($language)) {
            $language = $languages_id;
        }
        $_def = \common\classes\platform::default_id();
        $platform_id = (int) ($platform_id ? $platform_id : $_def);
        $product_query = tep_db_query('select if(length(pd1.products_name) > 0, pd1.products_name, pd.products_name) as products_name from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd.products_id = pd1.products_id and pd1.platform_id = '" . intval($platform_id) . "' and pd1.language_id = '" . (int) $language . "' where pd.products_id = '" . (int) $product_id . "' and pd.language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "' and pd.platform_id = '" . $_def . "'");
        $product = tep_db_fetch_array($product_query);
        if (empty($product['products_name']) && stripos(\Yii::$app->id, 'backend') !== false) {
            $product = Yii::$app->get_db()->create_command('SELECT products_name FROM ' . TABLE_PRODUCTS_DESCRIPTION . ' ' . "WHERE products_id='" . (int) $product_id . "' AND products_name!='' " . 'LIMIT 1')->query_one();
        }
        if (!isset($product['products_name'])) {
            return '';
        }
        if (sizeof($search_terms) == 0) {
            return $product['products_name'];
        } else if (defined('MSEARCH_HIGHLIGHT_ENABLE') && MSEARCH_HIGHLIGHT_ENABLE == 'true') {
            return \common\helpers\Output::highlight_text($product['products_name'], $search_terms);
        } else {
            return $product['products_name'];
        }
    }
    public static function get_products_description($product_id, $language = '', $platform_id = '')
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if (empty($language)) {
            $language = $languages_id;
        }
        $_def = \common\classes\platform::default_id();
        $platform_id = (int) ($platform_id ? $platform_id : $_def);
        $product_query = tep_db_query('select if(length(pd1.products_description) > 0, pd1.products_description, pd.products_description) as products_description from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd.products_id = pd1.products_id and pd1.platform_id = '" . intval($platform_id) . "' and pd1.language_id = '" . (int) $language . "' where pd.products_id = '" . (int) $product_id . "' and pd.language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "' and pd.platform_id = '" . $_def . "'");
        $product = tep_db_fetch_array($product_query);
        if (!isset($product['products_description'])) {
            return '';
        }
        return $product['products_description'];
    }
    public static function get_seo_name($products_id, $language_id, $platform_id = null)
    {
        if (empty($language_id)) {
            $language_id = (int) $GLOBALS['languages_id'];
        }
        if (empty($platform_id)) {
            $platform_id = \common\classes\platform::default_id();
        }
        $_key = (int) $products_id . '^' . (int) $language_id . '^' . $platform_id;
        static $_lookup_product = [];
        if (isset($_lookup_product[$_key])) {
            $product = $_lookup_product[$_key];
        } else {
            /*$product = tep_db_fetch_array(tep_db_query(
                  "select if(length(pd.products_seo_page_name) > 0, pd.products_seo_page_name, p.products_seo_page_name) as products_seo_page_name ".
                  "from " . TABLE_PRODUCTS . " p ".
                  "  left join " . TABLE_PRODUCTS_DESCRIPTION . " pd on p.products_id = pd.products_id and pd.language_id = '" . (int)$language_id . "' ".
                  "where p.products_id = '" . (int)$products_id . "' ".
                  "order by length(if(length(pd.products_seo_page_name) > 0, pd.products_seo_page_name, p.products_seo_page_name)) desc ".
                  "limit 1"
              ));*/
            $product = false;
            $product_r = tep_db_query('select pd.products_seo_page_name as products_seo_page_name ' . 'from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd ' . "where pd.products_id = '" . (int) $products_id . "' and pd.language_id = '" . (int) $language_id . "' AND pd.platform_id ='" . (int) $platform_id . "' ");
            if (tep_db_num_rows($product_r) > 0) {
                $product = tep_db_fetch_array($product_r);
            }
            if (!is_array($product) || empty($product['products_seo_page_name'])) {
                $product_r = tep_db_query('select p.products_seo_page_name as products_seo_page_name ' . 'from ' . TABLE_PRODUCTS . ' p ' . "where p.products_id = '" . (int) $products_id . "' ");
                if (tep_db_num_rows($product_r) > 0) {
                    $product = tep_db_fetch_array($product_r);
                }
            }
            if (count($_lookup_product) > 50) {
                $_lookup_product = [];
            }
            $_lookup_product[$_key] = $product;
        }
        return $product['products_seo_page_name'] ?? null;
    }
    public static function get_products_stock($products_id)
    {
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
            if (!$ext::is_stock_available($products_id)) {
                return 0;
            }
        }
        if (defined('TEMPORARY_STOCK_ENABLE') && TEMPORARY_STOCK_ENABLE == 'true') {
            $customers_temporary_stock_quantity = self::get_customers_temporary_stock_quantity($products_id);
        } else {
            $customers_temporary_stock_quantity = 0;
        }
        if (\common\helpers\Extensions::is_allowed('Inventory') && strpos($products_id, '{') !== false && !\common\helpers\Inventory::disabled_on_product($products_id)) {
            $stock_query = tep_db_query('select products_quantity, suppliers_stock_quantity, stock_control from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($products_id) . "'");
            if (tep_db_num_rows($stock_query)) {
                $stock_values = tep_db_fetch_array($stock_query);
                $stock_values['products_quantity'] = self::get_available($products_id, 0);
                /** @var \common\extensions\StockControl\StockControl $extScl */
                if ($ext_scl = \common\helpers\Extensions::is_allowed('StockControl')) {
                    $ext_scl::update_get_product_stock_inventory($products_id, $stock_values);
                }
                /** @var \common\extensions\ReportFreezeStock\ReportFreezeStock $ext */
                if (($ext = \common\helpers\Extensions::is_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
                    $freeze_model = \common\helpers\Extensions::get_model('ReportFreezeStock', 'FreezeInventory');
                    if (empty($freeze_model)) {
                        $freeze_inventory = null;
                    }
                    $freeze_inventory = $freeze_model::find()->where(['products_id' => $products_id])->as_array()->one();
                    if (is_array($freeze_inventory)) {
                        $stock_values = array_merge($stock_values, $freeze_inventory);
                    }
                }
            } else {
                $products_id = \common\helpers\Inventory::get_prid($products_id);
                $stock_query = tep_db_query('select products_quantity, suppliers_stock_quantity, stock_control from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'");
                $stock_values = tep_db_fetch_array($stock_query);
                $stock_values['products_quantity'] = self::get_available((int) $products_id, 0);
                if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                    $ext_scl::update_get_product_stock_product($products_id, $stock_values);
                }
                if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
                    $freeze_products = \common\extensions\Report_Freeze_Stock\models\Freeze_Products::find()->where(['products_id' => (int) $products_id])->as_array()->one();
                    if (is_array($freeze_products)) {
                        $stock_values = array_merge($stock_values, $freeze_products);
                    }
                }
            }
        } else {
            $products_id = \common\helpers\Inventory::get_prid($products_id);
            $stock_values = static::get_product_columns((int) $products_id, ['products_quantity', 'suppliers_stock_quantity', 'stock_control']);
            $stock_values['products_quantity'] = self::get_available((int) $products_id, 0);
            /** @var \common\extensions\StockControl\StockControl $extScl */
            if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                $ext_scl::update_get_product_stock_product($products_id, $stock_values);
            }
            if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
                $freeze_products = \common\extensions\Report_Freeze_Stock\models\Freeze_Products::find()->where(['products_id' => (int) $products_id])->as_array()->one();
                if (is_array($freeze_products)) {
                    $stock_values = array_merge($stock_values, $freeze_products);
                }
            }
        }
        $stock = ($stock_values['products_quantity'] ?? 0) + ($stock_values['suppliers_stock_quantity'] ?? 0) + $customers_temporary_stock_quantity - self::get_customers_limit_stock_quantity($products_id);
        if ($stock < 0) {
            $stock = 0;
        }
        return $stock;
    }
    public static function get_customers_limit_stock_quantity($products_id)
    {
        $products_id = \common\helpers\Inventory::get_prid($products_id);
        $product_values = static::get_product_columns((int) $products_id, ['stock_limit', 'manufacturers_id']);
        if (($product_values['stock_limit'] ?? null) > -1) {
            return $product_values['stock_limit'];
        }
        $stock_level_limit = 0;
        //check brand
        if (($product_values['manufacturers_id'] ?? null) > 0) {
            $manufacturer_query = tep_db_query('select stock_limit from ' . TABLE_MANUFACTURERS . " where manufacturers_id = '" . (int) $product_values['manufacturers_id'] . "'");
            $manufacturer_values = tep_db_fetch_array($manufacturer_query);
            \common\helpers\Php8::null_arr_props($manufacturer_values, ['stock_limit']);
            if ($manufacturer_values['stock_limit'] > -1) {
                $stock_level_limit = $manufacturer_values['stock_limit'];
            }
        }
        $cat_r = tep_db_query("SELECT c.categories_id, c.stock_limit\r\n                FROM categories c\r\n                INNER JOIN products_to_categories p2c ON (c.categories_id=p2c.categories_id)\r\n                INNER JOIN platforms_categories pc ON (pc.categories_id=p2c.categories_id and pc.platform_id='" . \common\classes\platform::current_id() . "') " . "WHERE p2c.products_id='" . $products_id . "' ");
        if (tep_db_num_rows($cat_r) > 0) {
            while ($cat_r_array = tep_db_fetch_array($cat_r)) {
                if ($cat_r_array['stock_limit'] > $stock_level_limit) {
                    $stock_level_limit = $cat_r_array['stock_limit'];
                }
            }
        }
        if ($stock_level_limit == 0 && defined('ADDITIONAL_STOCK_LIMIT')) {
            $stock_level_limit = (int) ADDITIONAL_STOCK_LIMIT;
        }
        return $stock_level_limit;
    }
    public static function check_stock($products_id, $products_quantity)
    {
        if (defined('TEMPORARY_STOCK_ENABLE') && TEMPORARY_STOCK_ENABLE == 'true') {
            $products_quantity -= self::get_customers_temporary_stock_quantity($products_id);
        }
        $stock_left = self::get_products_stock($products_id) - $products_quantity;
        $out_of_stock = '';
        if ($stock_left < 0) {
            $out_of_stock = '<span class="markProductOutOfStock">' . STOCK_MARK_PRODUCT_OUT_OF_STOCK . '</span>';
        }
        return $out_of_stock;
    }
    public static function get_allocated_stock_quantity($products_id)
    {
        return 0;
        $orders_status_array = [];
        // not Completed and not Cancelled orders
        $orders_status_query = tep_db_query('select distinct orders_status_id from ' . TABLE_ORDERS_STATUS . ' where orders_status_groups_id not in (4,5)');
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            $orders_status_array[] = $orders_status['orders_status_id'];
        }
        if (strpos(\common\helpers\Inventory::normalize_id_excl_virtual($products_id), '{') !== false) {
            $allocated_stock_data = tep_db_fetch_array(tep_db_query('select sum(op.products_quantity) as allocated_stock_quantity from ' . TABLE_INVENTORY . ' i left join ' . TABLE_ORDERS_PRODUCTS . ' op on op.uprid = i.products_id and op.products_id = i.prid left join ' . TABLE_ORDERS . " o on o.orders_id = op.orders_id where i.products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($products_id)) . "' and o.stock_updated = '1' and o.orders_status in ('" . implode("','", $orders_status_array) . "') group by i.products_id"));
            tep_db_query('update ' . TABLE_INVENTORY . " set allocated_stock_quantity = '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "', warehouse_stock_quantity =  products_quantity + '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "' + temporary_stock_quantity where products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($products_id)) . "'");
        } else {
            $allocated_stock_data = tep_db_fetch_array(tep_db_query('select sum(op.products_quantity) as allocated_stock_quantity from ' . TABLE_PRODUCTS . ' p left join ' . TABLE_ORDERS_PRODUCTS . ' op on op.products_id = p.products_id left join ' . TABLE_ORDERS . " o on o.orders_id = op.orders_id where p.products_id = '" . (int) $products_id . "' and o.stock_updated = '1' and o.orders_status in ('" . implode("','", $orders_status_array) . "') group by p.products_id"));
            tep_db_query('update ' . TABLE_PRODUCTS . " set allocated_stock_quantity = '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "', warehouse_stock_quantity =  products_quantity + '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "' + temporary_stock_quantity where products_id = '" . (int) $products_id . "'");
        }
        return (int) $allocated_stock_data['allocated_stock_quantity'];
    }
    public static function get_temporary_stock_quantity($products_id)
    {
        return 0;
        if (strpos(\common\helpers\Inventory::normalize_id_excl_virtual($products_id), '{') !== false) {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where if(length(normalize_id) > 0, normalize_id, products_id) = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($products_id)) . "' group by if(length(normalize_id) > 0, normalize_id, products_id)"));
            tep_db_query('update ' . TABLE_INVENTORY . " set temporary_stock_quantity = '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "', warehouse_stock_quantity =  products_quantity + allocated_stock_quantity + '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "' where products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($products_id)) . "'");
        } else {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where prid = '" . (int) $products_id . "' group by prid"));
            tep_db_query('update ' . TABLE_PRODUCTS . " set temporary_stock_quantity = '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "', warehouse_stock_quantity =  products_quantity + allocated_stock_quantity + '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "' where products_id = '" . (int) $products_id . "'");
        }
        return $temporary_stock_data['temporary_stock_quantity'];
    }
    public static function cleanup_temporary_stock_quantity()
    {
        if (defined('TEMPORARY_STOCK_ENABLE') && defined('TEMPORARY_STOCK_PERIOD') && TEMPORARY_STOCK_ENABLE == 'true' && TEMPORARY_STOCK_PERIOD > 0) {
            $temporary_stock_query = tep_db_query('select * from ' . self::get_temporary_stock_table_name() . ' where temporary_stock_datetime < (now() - interval ' . (int) TEMPORARY_STOCK_PERIOD . ' minute)');
            while ($temporary_stock_data = tep_db_fetch_array($temporary_stock_query)) {
                tep_db_query('delete from ' . self::get_temporary_stock_table_name() . " where temporary_stock_id = '" . (int) $temporary_stock_data['temporary_stock_id'] . "'");
                self::log_stock_history_before_update($temporary_stock_data['normalize_id'], $temporary_stock_data['temporary_stock_quantity'], '+', ['warehouse_id' => $temporary_stock_data['warehouse_id'], 'suppliers_id' => $temporary_stock_data['suppliers_id'], 'comments' => TEXT_TEMPORARY_STOCK_UPDATE, 'is_temporary' => 1]);
                self::update_stock($temporary_stock_data['normalize_id'], $temporary_stock_data['temporary_stock_quantity'], 0, $temporary_stock_data['warehouse_id'], $temporary_stock_data['suppliers_id']);
                \common\helpers\Warehouses::get_temporary_stock_quantity($temporary_stock_data['normalize_id'], $temporary_stock_data['warehouse_id'], $temporary_stock_data['suppliers_id']);
                self::get_temporary_stock_quantity($temporary_stock_data['normalize_id']);
                self::do_cache($temporary_stock_data['normalize_id']);
                self::write_history($temporary_stock_data['normalize_id'], $temporary_stock_data['warehouse_id'], $temporary_stock_data['suppliers_id'], 0, -$temporary_stock_data['temporary_stock_quantity'], ['comments' => TEXT_TEMPORARY_STOCK_UPDATE, 'is_temporary' => 1]);
            }
        }
    }
    public static function get_customers_temporary_stock_quantity_data($products_id, $warehouse_id, $suppliers_id, $original_products_id = '')
    {
        //$the_session_id = tep_session_id();
        if (\Yii::$app->id == 'app-console') {
            $the_session_id = \Yii::$app->storage->get('guid');
        } else {
            $the_session_id = tep_session_id();
        }
        $original_products_id = trim($original_products_id);
        if (!\Yii::$app->user->is_guest) {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select * from ' . self::get_temporary_stock_table_name() . " where (customers_id = '" . (int) \Yii::$app->user->get_id() . "' or (customers_id = '0' and session_id = '" . tep_db_input($the_session_id) . "')) and products_id = '" . tep_db_input($products_id) . "'" . ($original_products_id != '' ? " and child_id = '" . tep_db_input($original_products_id) . "'" : '') . " and warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "'"));
        } else {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select * from ' . self::get_temporary_stock_table_name() . " where session_id = '" . tep_db_input($the_session_id) . "' and products_id = '" . tep_db_input($products_id) . "'" . ($original_products_id != '' ? " and child_id = '" . tep_db_input($original_products_id) . "'" : '') . " and warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "'"));
        }
        return $temporary_stock_data;
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_customers_temporary_stock_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0, $original_products_id = '')
    {
        //$the_session_id = tep_session_id();
        if (\Yii::$app->id == 'app-console') {
            $the_session_id = \Yii::$app->storage->get('guid');
        } else {
            $the_session_id = tep_session_id();
        }
        $original_products_id = trim($original_products_id);
        if (!\Yii::$app->user->is_guest) {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where (customers_id = '" . (int) \Yii::$app->user->get_id() . "' or (customers_id = '0' and session_id = '" . tep_db_input($the_session_id) . "'))" . ($original_products_id != '' ? " and child_id = '" . tep_db_input($original_products_id) . "'" : '') . " and if(length(normalize_id) > 0, normalize_id, products_id) = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($products_id)) . "'" . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "'" : '') . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '')));
        } else {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where session_id = '" . tep_db_input($the_session_id) . "'" . ($original_products_id != '' ? " and child_id = '" . tep_db_input($original_products_id) . "'" : '') . " and if(length(normalize_id) > 0, normalize_id, products_id) = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($products_id)) . "'" . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "'" : '') . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '')));
        }
        return $temporary_stock_data['temporary_stock_quantity'];
    }
    /**
     *
     * @param int $products_id - required
     * @param int $qty - required
     * @param int $warehouse_id = 0
     * @param int $suppliers_id = 0
     * @param int $not_available = false
     * @param int $original_products_id = ''
     * @param string $keepUntil = 'now()' (parsed with strtotime) actually date+TEMPORARY_STOCK_PERIOD minutes
     */
    public static function update_customers_temporary_stock_quantity($products_id, $qty, $warehouse_id = 0, $suppliers_id = 0, $not_available = false, $original_products_id = '', $keep_until = 'now()')
    {
        if (defined('STOCK_LIMITED') && defined('TEMPORARY_STOCK_ENABLE') && STOCK_LIMITED == 'true' && TEMPORARY_STOCK_ENABLE == 'true') {
            if (\Yii::$app->id == 'app-console') {
                $guid = \Yii::$app->storage->get('guid');
            } else {
                $guid = tep_session_id();
            }
            if ($keep_until != 'now()') {
                $tst = strtotime($keep_until);
                if ($tst) {
                    $keep_until = date('Y-m-d H:i:s', $tst);
                } else {
                    $keep_until = 'now()';
                }
            }
            if ($warehouse_id == 0) {
                $warehouse_id = \common\helpers\Warehouses::get_default_warehouse();
            }
            if ($suppliers_id == 0) {
                $suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
            }
            $original_products_id = trim($original_products_id);
            $normalize_id = \common\helpers\Inventory::normalize_id_excl_virtual($products_id);
            $temporary_stock_data = self::get_customers_temporary_stock_quantity_data($products_id, $warehouse_id, $suppliers_id, $original_products_id);
            $sql_data_array = ['warehouse_id' => (int) $warehouse_id, 'suppliers_id' => (int) $suppliers_id, 'session_id' => $guid, 'customers_id' => (int) \Yii::$app->user->get_id(), 'prid' => (int) \common\helpers\Inventory::get_prid($products_id), 'products_id' => $products_id, 'normalize_id' => $normalize_id, 'temporary_stock_quantity' => $qty, 'temporary_stock_datetime' => $keep_until, 'child_id' => $original_products_id];
            if (preg_match('/^.+\{sub\}(\d+)(\|.*)?$/si', $original_products_id, $match)) {
                $sql_data_array['parent_id'] = (int) $match[1];
            }
            unset($match);
            $sql_data_array['specials_id'] = Specials::get_special_id($sql_data_array, $qty);
            if (isset($temporary_stock_data['temporary_stock_id']) && $temporary_stock_data['temporary_stock_id'] > 0) {
                $temporary_stock_quantity = $qty - $temporary_stock_data['temporary_stock_quantity'];
                if ($qty > 0) {
                    tep_db_perform(self::get_temporary_stock_table_name(), $sql_data_array, 'update', "temporary_stock_id = '" . (int) $temporary_stock_data['temporary_stock_id'] . "'");
                    if ($not_available && abs($temporary_stock_quantity) > 0) {
                        if ($temporary_stock_quantity > 0) {
                            tep_db_query('update ' . self::get_temporary_stock_table_name() . ' set not_available_quantity = not_available_quantity + ' . (int) abs($temporary_stock_quantity) . " where temporary_stock_id = '" . (int) $temporary_stock_data['temporary_stock_id'] . "'");
                        } else {
                            tep_db_query('update ' . self::get_temporary_stock_table_name() . ' set not_available_quantity = not_available_quantity - ' . (int) abs($temporary_stock_quantity) . " where temporary_stock_id = '" . (int) $temporary_stock_data['temporary_stock_id'] . "'");
                        }
                    }
                } else {
                    tep_db_query('delete from ' . self::get_temporary_stock_table_name() . " where temporary_stock_id = '" . (int) $temporary_stock_data['temporary_stock_id'] . "'");
                }
            } elseif ($qty > 0) {
                $temporary_stock_quantity = $qty;
                if ($not_available) {
                    $sql_data_array['not_available_quantity'] = $qty;
                }
                tep_db_perform(self::get_temporary_stock_table_name(), $sql_data_array);
            }
            if (abs($temporary_stock_quantity) > 0) {
                if ($temporary_stock_quantity > 0) {
                    self::log_stock_history_before_update($normalize_id, $temporary_stock_quantity, '-', ['warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'comments' => TEXT_TEMPORARY_STOCK_UPDATE, 'is_temporary' => 1]);
                    self::update_stock($normalize_id, 0, $temporary_stock_quantity, $warehouse_id, $suppliers_id);
                } else {
                    self::log_stock_history_before_update($normalize_id, abs($temporary_stock_quantity), '+', ['warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'comments' => TEXT_TEMPORARY_STOCK_UPDATE, 'is_temporary' => 1]);
                    self::update_stock($normalize_id, abs($temporary_stock_quantity), 0, $warehouse_id, $suppliers_id);
                }
                self::do_cache($products_id);
                self::write_history($products_id, $warehouse_id, $suppliers_id, 0, $temporary_stock_quantity, ['comments' => TEXT_TEMPORARY_STOCK_UPDATE, 'is_temporary' => 1]);
            }
            \common\helpers\Warehouses::get_temporary_stock_quantity($normalize_id, $warehouse_id, $suppliers_id);
            self::get_temporary_stock_quantity($normalize_id);
        }
    }
    public static function remove_customers_temporary_stock_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        self::update_customers_temporary_stock_quantity($products_id, 0, $warehouse_id, $suppliers_id);
    }
    public static function log_stock_history_before_update($uprid, $qty, $qty_prefix, $params = [])
    {
        return 0;
        if (strpos(\common\helpers\Inventory::normalize_id_excl_virtual($uprid), '{') !== false) {
            $check = tep_db_fetch_array(tep_db_query('select products_id, prid, products_model, products_quantity from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($uprid)) . "'"));
            $check_warehouse = tep_db_fetch_array(tep_db_query('select sum(warehouse_stock_quantity) as warehouse_stock_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . " where warehouse_id = '" . (int) $params['warehouse_id'] . "' and products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_id_excl_virtual($uprid)) . "' and prid = '" . (int) \common\helpers\Inventory::get_prid($uprid) . "'"));
        } else {
            $check = tep_db_fetch_array(tep_db_query('select products_id, products_id as prid, products_model, products_quantity from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $uprid . "'"));
            $check_warehouse = tep_db_fetch_array(tep_db_query('select sum(warehouse_stock_quantity) as warehouse_stock_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . " where warehouse_id = '" . (int) $params['warehouse_id'] . "' and products_id = '" . (int) $uprid . "' and prid = '" . (int) $uprid . "'"));
        }
        if ($check['prid'] > 0) {
            $sql_data_array = ['products_id' => $check['products_id'], 'prid' => $check['prid'], 'products_model' => $check['products_model'], 'products_quantity_before' => $check['products_quantity'], 'warehouse_quantity_before' => $check_warehouse['warehouse_stock_quantity'], 'products_quantity_update_prefix' => $qty_prefix, 'products_quantity_update' => $qty, 'comments' => $params['comments'], 'orders_id' => $params['orders_id'], 'warehouse_id' => $params['warehouse_id'], 'suppliers_id' => $params['suppliers_id'], 'admin_id' => $params['admin_id'], 'is_temporary' => $params['is_temporary'], 'date_added' => 'now()'];
            tep_db_perform(TABLE_STOCK_HISTORY, $sql_data_array);
        }
    }
    public static function update_stock($uprid, $qty, $old_qty = 0, $warehouse_id = 0, $suppliers_id = 0, $platform_id = 0)
    {
        return 0;
        $prid = \common\helpers\Inventory::get_prid($uprid);
        if (!tep_not_null($prid)) {
            return false;
        }
        if ($warehouse_id == 0) {
            $warehouse_id = \common\helpers\Warehouses::get_default_warehouse();
        }
        if ($suppliers_id == 0) {
            $suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
        }
        if ($platform_id == 0) {
            $platform_id = \common\classes\platform::current_id();
        }
        if (defined('STOCK_LIMITED') && STOCK_LIMITED == 'true') {
            if ($qty > $old_qty) {
                $q = '+' . (int) ($qty - $old_qty) . '';
            } else {
                $q = '-' . (int) ($old_qty - $qty) . '';
            }
            if (defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED == 'true') {
                preg_match_all("/\\{\\d+\\}/", $uprid, $arr);
                $options_id = $arr[0][1];
                preg_match_all("/\\}[^\\{]+/", $uprid, $arr);
                $values_id = $arr[0][1];
                if (is_array($options_id)) {
                    $stock_query_raw = 'SELECT count(*) as total FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad WHERE pa.products_attributes_id=pad.products_attributes_id and pa.products_id = '" . (int) $prid . "' and pad.products_attributes_filename<>'' ";
                    $stock_query_raw .= ' and ( 0 ';
                    for ($k = 0; $k < count($options_id); $k++) {
                        $stock_query_raw .= " OR (pa.options_id = '" . (int) $options_id[$k] . "' AND pa.options_values_id = '" . (int) $values_id[$k] . "')  ";
                    }
                    $stock_query_raw .= ') ';
                    $d = tep_db_fetch_array(tep_db_query($stock_query_raw));
                    if ($d['total'] > 0) {
                        return true;
                    }
                }
                $stock_query_raw = 'SELECT count(*) as total FROM ' . TABLE_PRODUCTS . " WHERE products_id = '" . (int) $prid . "' and products_file <> '' ";
                $d = tep_db_fetch_array(tep_db_query($stock_query_raw));
                if ($d['total'] > 0) {
                    return true;
                }
            }
            /*
                        if (\common\helpers\Acl::checkExtensionAllowed('ProductBundles', 'allowed')) {
                            $vids = array();
                            $attributes_query = tep_db_query("select options_id, options_values_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $prid . "'");
                            while ($attributes = tep_db_fetch_array($attributes_query)) {
                                if (preg_match('/\{' . $attributes['options_id'] . '\}' . $attributes['options_values_id'] . '(\{|$)/', $uprid)) {
                                    $vids[$attributes['options_id']] = $attributes['options_values_id'];
                                }
                            }
                            ksort($vids);
                            $uprid = \common\helpers\Inventory::get_uprid($prid, $vids);
                        }
            */
            /** @var \common\extensions\Inventory\Inventory $ext */
            if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
                $ext::update_stock($prid, $uprid, $q, $warehouse_id, $suppliers_id, $platform_id);
            } else {
                tep_db_query('update ' . TABLE_PRODUCTS . ' set products_quantity = products_quantity  ' . $q . " where products_id = '" . (int) $prid . "'");
                tep_db_query('update ' . TABLE_WAREHOUSES_PRODUCTS . ' set products_quantity = products_quantity  ' . $q . " where warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "' and products_id = '" . (int) $prid . "' and prid = '" . (int) $prid . "'");
                $data_q = tep_db_query('select products_quantity from ' . TABLE_PRODUCTS . " where  products_id = '" . (int) $prid . "'");
                $data = tep_db_fetch_array($data_q);
                if ($data['products_quantity'] < 1 && STOCK_ALLOW_CHECKOUT == 'false') {
                    tep_db_query('update ' . TABLE_PRODUCTS . " set products_status = 0 where products_id = '" . (int) $prid . "'");
                }
            }
        }
    }
    public static function remove_product_image($filename)
    {
        $duplicate_image_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS . " where products_image = '" . tep_db_input($filename) . "' or products_image_med = '" . tep_db_input($filename) . "' or products_image_lrg = '" . tep_db_input($filename) . "' or products_image_xl_1 = '" . tep_db_input($filename) . "' or products_image_sm_1 = '" . tep_db_input($filename) . "' or products_image_xl_2 = '" . tep_db_input($filename) . "' or products_image_sm_2 = '" . tep_db_input($filename) . "' or products_image_xl_3 = '" . tep_db_input($filename) . "' or products_image_sm_3 = '" . tep_db_input($filename) . "' or products_image_xl_4 = '" . tep_db_input($filename) . "' or products_image_sm_4 = '" . tep_db_input($filename) . "' or products_image_xl_5 = '" . tep_db_input($filename) . "' or products_image_sm_5 = '" . tep_db_input($filename) . "' or products_image_xl_6 = '" . tep_db_input($filename) . "' or products_image_sm_6 = '" . tep_db_input($filename) . "'");
        $duplicate_image = tep_db_fetch_array($duplicate_image_query);
        if ($duplicate_image['total'] < 2) {
            if (file_exists(DIR_FS_CATALOG_IMAGES . $filename)) {
                @unlink(DIR_FS_CATALOG_IMAGES . $filename);
            }
        }
    }
    public static function remove_product($product_id)
    {
        $change_pids = [$product_id];
        // {{ remove sub products
        if ((int) $product_id > 0) {
            foreach (\common\models\Products::find()->select('products_id')->where(['parent_products_id' => (int) $product_id])->as_array()->all() as $sub_product) {
                $change_pids[] = (int) $sub_product['products_id'];
                static::remove_product($sub_product['products_id']);
            }
        }
        // }} remove sub products
        \common\components\Categories_Cache::get_cpc()::invalidate_products($change_pids);
        $product_model = \common\models\Products::find_one($product_id);
        \common\classes\Images::remove_product_images($product_id);
        /**
         * Moved to hook
         */
        // {{ put redirect to category
        //        $get_category_r = tep_db_query(
        //            "SELECT c.categories_id ".
        //            "FROM ".TABLE_PRODUCTS_TO_CATEGORIES." p2c ".
        //              "LEFT JOIN ".TABLE_CATEGORIES." c ON c.categories_id=p2c.categories_id ".
        //            "WHERE p2c.products_id='".(int)$product_id."' ".
        //            "ORDER BY IFNULL(c.categories_left,4000000), c.categories_status DESC ".
        //            "LIMIT 1"
        //        );
        //        if ( tep_db_num_rows($get_category_r)>0 ) {
        //            $_category = tep_db_fetch_array($get_category_r);
        //
        //            tep_db_query(
        //                "INSERT INTO seo_redirect (old_url, new_url, platform_id) ".
        //                "SELECT DISTINCT pd.products_seo_page_name, cd.categories_seo_page_name, pd.platform_id ".
        //                "FROM ".TABLE_PRODUCTS_DESCRIPTION." pd ".
        //                " INNER JOIN ".TABLE_CATEGORIES_DESCRIPTION." cd ON cd.categories_id='".$_category['categories_id']."' AND cd.categories_seo_page_name!='' AND cd.language_id=pd.language_id ".
        //                "WHERE pd.products_seo_page_name!='' AND pd.products_id='".(int)$product_id."' "
        //            );
        //        }
        // }}
        //if (USE_MARKET_PRICES == 'True') {
        tep_db_query('delete from ' . TABLE_PRODUCTS_PRICES . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_PLATFORMS_PRODUCTS . " where products_id = '" . (int) $product_id . "'");
        $query = tep_db_query('select specials_id from ' . TABLE_SPECIALS . " where products_id = '" . (int) $product_id . "'");
        while ($data = tep_db_fetch_array($query)) {
            tep_db_query('delete from ' . TABLE_SPECIALS_PRICES . ' where specials_id = ' . $data['specials_id']);
        }
        $query = tep_db_query('select products_attributes_id from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $product_id . "'");
        while ($data = tep_db_fetch_array($query)) {
            tep_db_query('delete from ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES . " where products_attributes_id = '" . (int) $data['products_attributes_id'] . "'");
        }
        //}
        if (defined('PRODUCTS_PROPERTIES') && PRODUCTS_PROPERTIES == 'True') {
            tep_db_query('delete from ' . TABLE_PROPERTIES_TO_PRODUCTS . " where products_id = '" . (int) $product_id . "'");
        }
        tep_db_query('delete from ' . TABLE_SPECIALS . " where products_id = '" . (int) $product_id . "'");
        if ($product_model) {
            $product_model->delete();
        }
        tep_db_query('delete from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_CUSTOMERS_BASKET . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_PRICES . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_PRODUCTS_COMMENTS . " where products_id = '" . $product_id . "'");
        /** @var \common\extensions\Inventory\Inventory $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
            $ext::delete_product((int) $product_id);
        }
        tep_db_query('delete from ' . TABLE_SUPPLIERS_PRODUCTS . " where products_id = '" . (int) $product_id . "'");
        tep_db_query('delete from ' . TABLE_WAREHOUSES_PRODUCTS . " where prid = '" . (int) $product_id . "'");
        $product_reviews_query = tep_db_query('select reviews_id from ' . TABLE_REVIEWS . " where products_id = '" . (int) $product_id . "'");
        while ($product_reviews = tep_db_fetch_array($product_reviews_query)) {
            tep_db_query('delete from ' . TABLE_REVIEWS_DESCRIPTION . " where reviews_id = '" . (int) $product_reviews['reviews_id'] . "'");
        }
        tep_db_query('delete from ' . TABLE_REVIEWS . " where products_id = '" . (int) $product_id . "'");
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductTemplates', 'allowed')) {
            $ext::product_delete($product_id);
        }
        $schema_check = Yii::$app->get('db')->schema->get_table_schema('products_linked_parent');
        if ($schema_check) {
            tep_db_query("DELETE FROM products_linked_parent WHERE product_id='" . $product_id . "'");
        }
        $schema_check = Yii::$app->get('db')->schema->get_table_schema('products_linked_children');
        if ($schema_check) {
            tep_db_query("DELETE FROM products_linked_children WHERE parent_product_id='" . $product_id . "'");
            tep_db_query("DELETE FROM products_linked_children WHERE linked_product_id='" . $product_id . "'");
        }
        foreach (\common\helpers\Hooks::get_list('product/after-delete') as $filename) {
            include $filename;
        }
        if (defined('USE_CACHE') && USE_CACHE == 'true') {
            \common\helpers\System::reset_cache_block('categories');
            \common\helpers\System::reset_cache_block('also_purchased');
        }
    }
    public static function trunk_products()
    {
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_TO_CATEGORIES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_DESCRIPTION);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_ATTRIBUTES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_PRICES);
        tep_db_query('TRUNCATE ' . TABLE_PROPERTIES_TO_PRODUCTS);
        tep_db_query('TRUNCATE ' . TABLE_INVENTORY);
        tep_db_query('TRUNCATE ' . TABLE_INVENTORY_PRICES);
        tep_db_query('TRUNCATE ' . TABLE_SUPPLIERS_PRODUCTS);
        tep_db_query('TRUNCATE ' . TABLE_PLATFORMS_PRODUCTS);
        tep_db_query('TRUNCATE ' . TABLE_REVIEWS);
        tep_db_query('TRUNCATE ' . TABLE_REVIEWS_DESCRIPTION);
        tep_db_query('TRUNCATE ' . TABLE_SPECIALS);
        tep_db_query('TRUNCATE ' . TABLE_SPECIALS_PRICES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_IMAGES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_IMAGES_ATTRIBUTES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_IMAGES_INVENTORY);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_OPTIONS);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_OPTIONS_VALUES);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_COMMENTS);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_VIDEOS);
        tep_db_query('TRUNCATE ' . TABLE_FEATURED);
        tep_db_query('TRUNCATE ' . TABLE_GIVE_AWAY_PRODUCTS);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_NOTIFY);
        tep_db_query('TRUNCATE ' . TABLE_PRODUCTS_NOTIFICATIONS);
        tep_db_query('TRUNCATE ' . TABLE_STOCK_HISTORY);
        tep_db_query('TRUNCATE ' . \common\models\Warehouses_Products::table_name());
        tep_db_query('TRUNCATE ' . TABLE_CUSTOMERS_BASKET);
        tep_db_query('TRUNCATE ' . TABLE_CUSTOMERS_BASKET_ATTRIBUTES);
        if (defined('USE_CACHE') && USE_CACHE == 'true') {
            \common\helpers\System::reset_cache_block('categories');
            \common\helpers\System::reset_cache_block('also_purchased');
        }
        $var_tables = [
            // bundle
            TABLE_SETS_PRODUCTS,
            // GiftWrap
            TABLE_GIFT_WRAP_PRODUCTS,
            TABLE_VIRTUAL_GIFT_CARD_PRICES,
            TABLE_VIRTUAL_GIFT_CARD_BASKET,
            \common\models\Virtual_Gift_Card_Info::table_name(),
            // LinkedProducts
            'products_linked_parent',
            'products_linked_children',
        ];
        foreach ($var_tables as $table) {
            if (\Yii::$app->db->schema->get_table_schema($table)) {
                tep_db_query("TRUNCATE TABLE {$table}");
            }
        }
        foreach (\common\helpers\Hooks::get_list('product/after-trunk') as $filename) {
            include $filename;
        }
    }
    public static function duplicate($products_id, $categories_id, $copy_attributes, $copy_categories = false)
    {
        $copy_attributes = is_bool($copy_attributes) ? $copy_attributes : !empty($copy_attributes) && $copy_attributes == 'yes';
        $origin_product = \common\models\Products::find_one($products_id);
        if (!$origin_product) {
            return false;
        }
        $__data = $origin_product->get_attributes();
        unset($__data['products_id']);
        $__data['products_date_added'] = date('Y-m-d H:i:s');
        $product_model = new \common\models\Products($__data);
        $product_model->load_default_values();
        $product_model->products_status = 0;
        $product_model->products_old_seo_page_name = '';
        $product_model->products_seo_page_name = '';
        $product_model->products_quantity = 0;
        $product_model->allocated_stock_quantity = 0;
        $product_model->temporary_stock_quantity = 0;
        $product_model->warehouse_stock_quantity = 0;
        $product_model->suppliers_stock_quantity = 0;
        $product_model->ordered_stock_quantity = 0;
        $product_model->products_ordered = 0;
        // $productModel->is_bundle = 0; // allow to duplicate bundles
        $product_model->products_popularity = 0;
        $product_model->popularity_simple = 0;
        $product_model->popularity_bestseller = 0;
        $product_model->sub_product_children_count = 0;
        $product_model->parent_products_id = 0;
        $product_model->products_file = '';
        if (!$product_model->save(false)) {
            return false;
        }
        $product_model->refresh();
        $dup_products_id = intval($product_model->products_id);
        if ($copy_categories) {
            tep_db_query('insert ignore into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) select * from (select '" . (int) $dup_products_id . "', categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id='" . (int) $products_id . "') a");
        }
        tep_db_query('insert ignore into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $dup_products_id . "', '" . (int) $categories_id . "')");
        $copy_models = ['\common\models\ProductsDescription' => 'products_id', '\common\models\PlatformsProducts' => 'products_id', '\common\models\ProductsPrices' => 'products_id'];
        if (\common\helpers\Acl::check_extension_allowed('ProductBundles')) {
            $copy_models['\common\models\SetsProducts'] = 'sets_id';
        }
        if (\common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions')) {
            $copy_models['\common\extensions\UserGroupsRestrictions\models\GroupsProducts'] = 'products_id';
        }
        foreach ($copy_models as $copy_model_class => $copy_product_column) {
            if (!class_exists($copy_model_class)) {
                continue;
            }
            call_user_func_array([$copy_model_class, 'deleteAll'], [[$copy_product_column => $dup_products_id]]);
            $source_collection = call_user_func_array([$copy_model_class, 'findAll'], [[$copy_product_column => $origin_product->products_id]]);
            foreach ($source_collection as $origin_model) {
                $__data = $origin_model->get_attributes();
                $__data[$copy_product_column] = $dup_products_id;
                $copy_model = Yii::create_object($copy_model_class);
                if ($copy_model instanceof \yii\db\Active_Record) {
                    if ($copy_model instanceof \common\models\Products_Description) {
                        $__data['products_seo_page_name'] = '';
                    }
                    $copy_model->set_attributes($__data, false);
                    $copy_model->load_default_values(true);
                    $copy_model->save(false);
                }
            }
        }
        // [[ Properties
        if (defined('PRODUCTS_PROPERTIES') && PRODUCTS_PROPERTIES == 'True') {
            tep_db_query('insert into ' . TABLE_PROPERTIES_TO_PRODUCTS . ' (products_id, properties_id, values_id, values_flag, extra_value) select * from (select ' . (int) $dup_products_id . ', properties_id, values_id, values_flag, extra_value from ' . TABLE_PROPERTIES_TO_PRODUCTS . " where products_id = '" . tep_db_input($products_id) . "') a");
            /* outdated table structure .... Unknown column 'language_id' in field list
                $properties_query = tep_db_query("select * from " . TABLE_PROPERTIES_TO_PRODUCTS . " where products_id = '" . tep_db_input($products_id) . "'");
               while ($properties = tep_db_fetch_array($properties_query)) {
                   tep_db_query("insert into " . TABLE_PROPERTIES_TO_PRODUCTS . " (products_id, properties_id, language_id, set_value, additional_info) values ('" . $dup_products_id . "', '" . $properties['properties_id'] . "', '" . $properties['language_id'] . "', '" . $properties['set_value'] . "', '" . $properties['additional_info'] . "')");
               }
                */
        }
        // ]]
        // [[ SUPPLEMENT_STATUS
        if (defined('SUPPLEMENT_STATUS') && SUPPLEMENT_STATUS == 'True' && \common\helpers\Acl::check_extension_allowed('UpSell')) {
            $query = tep_db_query('select * from ' . TABLE_PRODUCTS_UPSELL . " where products_id = '" . (int) $products_id . "'");
            while ($data = tep_db_fetch_array($query)) {
                tep_db_query('insert into ' . TABLE_PRODUCTS_UPSELL . " (products_id, upsell_id, sort_order) values ('" . $dup_products_id . "', '" . $data['upsell_id'] . "', '" . $data['sort_order'] . "')");
            }
            $query = tep_db_query('select * from ' . TABLE_PRODUCTS_XSELL . " where products_id = '" . (int) $products_id . "'");
            while ($data = tep_db_fetch_array($query)) {
                tep_db_query('insert into ' . TABLE_PRODUCTS_XSELL . " (products_id, xsell_id, sort_order) values ('" . $dup_products_id . "', '" . $data['xsell_id'] . "', '" . $data['sort_order'] . "')");
            }
        }
        // ]]
        // BOF: WebMakers.com Added: Attributes Copy on non-linked
        $products_id_from = tep_db_input($products_id);
        $products_id_to = $dup_products_id;
        //$products_id = $dup_products_id;
        if ($copy_attributes) {
            /*$copy_attributes_delete_first = '1';
              $copy_attributes_duplicates_skipped = '1';
              $copy_attributes_duplicates_overwrite = '0';
              ob_start();
              \common\helpers\Attributes::copy_products_attributes($products_id_from, $products_id_to);
              ob_get_clean();*/
            try {
                \common\helpers\Attributes::copy_products_attributes($products_id_from, $products_id_to, true);
            } catch (\Exception $e) {
                \Yii::warning(' #### ' . $e->get_code() . ' ' . print_r($e->get_message(), true), 'TLDEBUG');
            }
        }
        /// images (after attributes and inventory
        \common\helpers\Image::copy_product_images($products_id, $dup_products_id);
        if ($ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed')) {
            $ext::reindex($dup_products_id);
        }
        return $dup_products_id;
    }
    /**
     * common\models\Product\Price()->getProductSpecialPrice
     * @param int $product_id
     * @param int $qty
     * @return float|false
     */
    public static function get_products_special_price($product_id, $qty = 1)
    {
        return \common\models\Product\Price::get_instance($product_id)->get_product_special_price(['qty' => $qty]);
    }
    public static function save_specials_prices($specials_id, $group_id, $currencies_id = 0, $specials_groups_prices = 0)
    {
        $sql_data_array = [];
        $sql_data_array['specials_new_products_price'] = (float) $specials_groups_prices;
        $check = tep_db_fetch_array(tep_db_query('select count(*) as specials_price_exists from ' . TABLE_SPECIALS_PRICES . " where specials_id = '" . (int) $specials_id . "' and groups_id = '" . (int) $group_id . "' and currencies_id = '" . (int) $currencies_id . "'"));
        if ($check['specials_price_exists']) {
            tep_db_perform(TABLE_SPECIALS_PRICES, $sql_data_array, 'update', "specials_id = '" . (int) $specials_id . "' and groups_id = '" . (int) $group_id . "' and currencies_id = '" . (int) $currencies_id . "'");
        } else {
            $sql_data_array['specials_id'] = $specials_id;
            $sql_data_array['groups_id'] = $group_id;
            $sql_data_array['currencies_id'] = $currencies_id;
            tep_db_perform(TABLE_SPECIALS_PRICES, $sql_data_array);
        }
    }
    public static function get_products_price_for_edit($product_id, $currency_id = 0, $group_id = 0, $default = '')
    {
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES != 'True') {
            $currency_id = 0;
        }
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            $group_id = 0;
        }
        if ($currency_id == 0 && $group_id == 0) {
            $product_query = tep_db_query('select products_price from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $product_id . "'");
        } else {
            $product_query = tep_db_query('select products_group_price as products_price from ' . TABLE_PRODUCTS_PRICES . " where  products_id = '" . (int) $product_id . "' and  groups_id = '" . (int) $group_id . "' and  currencies_id = '" . (int) $currency_id . "'");
        }
        $product = tep_db_fetch_array($product_query);
        if (empty($product['products_price']) && $default != '') {
            $product['products_price'] = $default;
        }
        return $product['products_price'];
    }
    /**
     * product price in selected currency for specified group (already q-ty discount)
     *
     * @param int $product_id
     * @param int $currency_id
     * @param int $group_id
     * @param float $default price
     * @return float product price in selected currency for specified group
     */
    public static function get_products_price($products_id, $qty = 1, $price = 0, $curr_id = 0, $group_id = 0)
    {
        return \common\models\Product\Price::get_instance($products_id)->get_product_price(['qty' => $qty, 'curr_id' => $curr_id, 'group_id' => $group_id]);
    }
    /* function tep_get_products_discount_price($product_id, $currency_id = 0, $group_id = 0, $default = ''){ */
    public static function get_products_discount_price($products_id, $qty, $products_price, $curr_id = 0, $group_id = 0)
    {
        return \common\models\Product\Price::get_instance($products_id)->get_products_discount_price([
            'products_price' => $products_price,
            //???
            'qty' => $qty,
            'curr_id' => $curr_id,
            'group_id' => $group_id,
        ]);
    }
    public static function get_products_discount_table($products_id, $curr_id = 0, $group_id = 0)
    {
        $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        if ($curr_id > 0) {
            $_currency_id = $curr_id;
        } else {
            $_currency_id = \Yii::$app->settings->get('currency_id');
        }
        if ($group_id > 0) {
            $_customer_groups_id = $group_id;
        } else {
            $_customer_groups_id = $customer_groups_id;
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
            if ($ext::check_show_price($_customer_groups_id)) {
                return false;
            }
        }
        $apply_discount = false;
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
            $query = tep_db_query('select pp.products_group_discount_price as products_price_discount, pp.products_group_price from ' . TABLE_PRODUCTS_PRICES . " pp where pp.products_id = '" . (int) $products_id . "' and pp.groups_id = '" . (int) $_customer_groups_id . "' and pp.currencies_id = '" . (USE_MARKET_PRICES == 'True' ? $_currency_id : '0') . "'");
            $num_rows = tep_db_num_rows($query);
            $data = tep_db_fetch_array($query);
            if (!$num_rows || $data['products_price_discount'] == '' && $data['products_group_price'] == -2 || $data['products_price_discount'] == -2 || USE_MARKET_PRICES != 'True' && $_customer_groups_id == 0) {
                if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True') {
                    $data = tep_db_fetch_array(tep_db_query('select pp.products_group_discount_price as products_price_discount from ' . TABLE_PRODUCTS_PRICES . " pp where pp.products_id = '" . (int) $products_id . "' and pp.groups_id = '0' and pp.currencies_id = '" . (int) $_currency_id . "'"));
                } else {
                    $data = tep_db_fetch_array(tep_db_query('select products_price_discount from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'"));
                }
                $apply_discount = true;
            }
        } else {
            $data = tep_db_fetch_array(tep_db_query('select products_price_discount from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'"));
        }
        if ($data['products_price_discount'] == '' || $data['products_price_discount'] == -1) {
            return false;
        }
        $ar = preg_split('/[:;]/', preg_replace('/;\s*$/', '', $data['products_price_discount']));
        // remove final separator
        if (!is_array($ar) || count($ar) < 2 || count($ar) % 2 == 1) {
            // incorrect table format - skip
            return false;
        }
        if ($apply_discount) {
            $discount = \common\helpers\Customer::check_customer_groups($_customer_groups_id, 'groups_discount');
            for ($i = 0, $n = sizeof($ar); $i < $n; $i = $i + 2) {
                $ar[$i + 1] = $ar[$i + 1] * (1 - $discount / 100);
            }
        }
        foreach (\common\helpers\Hooks::get_list('product/get-products-discount-table') as $filename) {
            include $filename;
        }
        return $ar;
    }
    public static function is_giveaway($products_id)
    {
        $query = tep_db_query('select * from ' . TABLE_GIVE_AWAY_PRODUCTS . " where products_id = '" . (int) $products_id . "'");
        if (tep_db_num_rows($query) > 0) {
            return true;
        }
        return false;
    }
    public static function draw_products_pull_down($name, $parameters = '', $exclude = '')
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $_params = \Yii::$app->request->get_body_params();
        if (!isset($_params->currencies)) {
            $currencies = Yii::$container->get('currencies');
        } else {
            $currencies = $_params->currencies;
        }
        if ($exclude == '') {
            $exclude = [];
        }
        $select_string = '<select name="' . $name . '"';
        if ($parameters) {
            $select_string .= ' ' . $parameters;
        }
        $select_string .= '>';
        $products_query = tep_db_query('select p.products_id, pd.products_name, p.products_price from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and platform_id = '" . intval(\common\classes\platform::default_id()) . "' and pd.language_id = '" . (int) $languages_id . "' order by products_name");
        while ($products = tep_db_fetch_array($products_query)) {
            if (!in_array($products['products_id'], $exclude)) {
                $select_string .= '<option ' . ($_POST[$name] == $products['products_id'] ? ' selected ' : '') . ' value="' . $products['products_id'] . '">' . $products['products_name'] . ' (' . $currencies->format(\common\helpers\Product::get_products_price($products['products_id'], 1, 0, $currencies->currencies[DEFAULT_CURRENCY]['id']), true, DEFAULT_CURRENCY) . ')</option>';
            }
        }
        $select_string .= '</select>';
        return $select_string;
    }
    /**
     * generally for admin only - get special price value for group/currency
     * @param int $specials_id
     * @param int $currency_id
     * @param int $group_id
     * @param float $default
     * @return float
     */
    public static function get_specials_price($specials_id, $currency_id = 0, $group_id = 0, $default = '')
    {
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES != 'True') {
            $currency_id = 0;
        }
        if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            $group_id = 0;
        }
        if ($currency_id == 0 && $group_id == 0) {
            $specials_query = tep_db_query('select specials_new_products_price from ' . TABLE_SPECIALS . " where specials_id = '" . (int) $specials_id . "'");
        } else {
            $specials_query = tep_db_query('select specials_new_products_price from ' . TABLE_SPECIALS_PRICES . " where  specials_id = '" . (int) $specials_id . "' and  groups_id = '" . (int) $group_id . "' and  currencies_id = '" . (int) $currency_id . "'");
        }
        $specials_data = tep_db_fetch_array($specials_query);
        if ($specials_data['specials_new_products_price'] == '' && $default != '') {
            $specials_data['specials_new_products_price'] = $default;
        }
        return $specials_data['specials_new_products_price'];
    }
    public static function get_sql_product_restrictions($table_prefixes = ['p', 'pd', 's', 'sp', 'pp'], $listing_check = true)
    {
        // " . \common\helpers\Product::get_sql_product_restrictions(array('p'=>'')) . "
        $def = ['p', 'pd', 's', 'sp', 'pp'];
        if (!is_array($table_prefixes)) {
            $table_prefixes['p'] = trim($table_prefixes) != '' ? rtrim($table_prefixes, '.') . '.' : '';
        } else {
            foreach ($table_prefixes as $k => $v) {
                if (is_integer($k)) {
                    $k = $def[$k];
                }
                $table_prefixes[$k] = trim($v) != '' ? rtrim($v, '.') . '.' : '';
            }
        }
        foreach ($def as $k) {
            if (!isset($table_prefixes[$k])) {
                $table_prefixes[$k] = $k . '.';
            }
        }
        $where_str = '';
        static $_cache = [];
        if (!isset($_cache['hidden_stock_indication'])) {
            $_cache['hidden_stock_indication'] = \common\classes\Stock_Indication::get_hidden_ids();
        }
        if (count($_cache['hidden_stock_indication']) > 0 && !\frontend\design\Info::is_totally_admin()) {
            $where_str .= ' and ' . $table_prefixes['p'] . "stock_indication_id not in ('" . implode("','", $_cache['hidden_stock_indication']) . "')";
        }
        if (!$listing_check && defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True' && !\frontend\design\Info::is_totally_admin()) {
            $where_str .= ' and ' . $table_prefixes['p'] . 'is_listing_product=1 ';
        }
        if (\common\helpers\Extensions::is_allowed('Inventory')) {
            if ($groups_inventory = \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsInventory', 'isAllowed')) {
                $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
                $where_str .= ' and ((not exists (select * from ' . TABLE_INVENTORY . ' where ' . $table_prefixes['p'] . 'products_id = prid)) or ((exists (select * from ' . TABLE_INVENTORY . ' where ' . $table_prefixes['p'] . 'products_id = prid)) and (exists (select * from ' . $groups_inventory::table_name() . ' where (' . $table_prefixes['p'] . "products_id = prid) and (groups_id = '" . (int) $customer_groups_id . "')))))";
            }
        }
        return $where_str;
    }
    public static function get_product_images($products_id)
    {
        $image_path = DIR_WS_CATALOG_IMAGES . 'products' . '/' . $products_id . '/';
        $images = [];
        $images_query = tep_db_query('select id.*, i.* from ' . TABLE_PRODUCTS_IMAGES . ' as i left join ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " as id on (i.products_images_id=id.products_images_id and id.language_id=0) where i.products_id = '" . (int) $products_id . "' order by i.sort_order");
        while ($images_data = tep_db_fetch_array($images_query)) {
            $images[] = ['products_images_id' => $images_data['products_images_id'], 'image_name' => empty($images_data['hash_file_name']) ? '' : $image_path . $images_data['products_images_id'] . '/' . $images_data['hash_file_name']];
        }
        return $images;
    }
    public static function parse_qty_discount_array($discount)
    {
        $qty_discounts = [];
        if ($discount != '') {
            foreach (explode(';', $discount) as $qty_discount) {
                $ar = explode(':', $qty_discount);
                if ($ar[0] > 0 && $ar[1] > 0) {
                    $qty_discounts[$ar[0]] = $ar[1];
                }
            }
        }
        ksort($qty_discounts);
        return $qty_discounts;
    }
    public static function products_groups_name($products_groups_id, $language_id = '')
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if (!$language_id) {
            $language_id = $languages_id;
        }
        $products_groups_query = tep_db_query('select products_groups_name from ' . TABLE_PRODUCTS_GROUPS . " where products_groups_id = '" . (int) $products_groups_id . "' and language_id = '" . (int) $language_id . "'");
        $products_groups = tep_db_fetch_array($products_groups_query);
        return $products_groups['products_groups_name'] ?? null;
    }
    public static function set_status($product_id, $status)
    {
        tep_db_query('update ' . TABLE_PRODUCTS . ' ' . "set products_status = '" . ($status ? 1 : 0) . "', " . ' previous_status=NULL, ' . ' products_last_modified = now() ' . "where products_id = '" . (int) $product_id . "'");
        if ((int) $product_id) {
            if ($status) {
                tep_db_query('update ' . TABLE_PRODUCTS . ' ' . 'set products_status = IFNULL(sub_product_prev_status,1), ' . ' sub_product_prev_status = NULL, ' . ' products_last_modified = NOW() ' . "where parent_products_id = '" . (int) $product_id . "'");
            } else {
                tep_db_query('update ' . TABLE_PRODUCTS . ' ' . 'set sub_product_prev_status = products_status, ' . ' products_status=0, ' . ' products_last_modified = NOW() ' . "where parent_products_id = '" . (int) $product_id . "'");
            }
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('AutomaticallyStatus', 'allowed')) {
            $ext::product_auto_switch_off($product_id);
        }
        \common\components\Categories_Cache::get_cpc()::invalidate_products($product_id);
    }
    public static function fill_global_sort($platform_id = 0, $products_id = 0)
    {
        if ($products_id == 0) {
            //clean up: delete if product was removed
            $sql = ' delete gs from ' . TABLE_PRODUCTS_GLOBAL_SORT . ' gs where not exists (select * from ' . TABLE_PRODUCTS . ' p where p.products_id=gs.products_id)';
            if ($platform_id > 0) {
                $sql .= " and platform_id='" . (int) $platform_id . "'";
            }
            tep_db_query($sql);
        }
        // new products to top
        // 2do new products by name
        if ($platform_id == 0) {
            $pl_sql = '';
        } else {
            $pl_sql = " and plp.platform_id='" . (int) $platform_id . "'";
        }
        if ($products_id == 0) {
            $p_sql = '';
        } else {
            $p_sql = " and plp.products_id='" . (int) $products_id . "'";
        }
        $sql = ' insert ignore into ' . TABLE_PRODUCTS_GLOBAL_SORT . ' (products_id, platform_id, sort_order) select * from ' . '(select plp.products_id, plp.platform_id,  @n:=@n+1 from ' . TABLE_PLATFORMS_PRODUCTS . ' plp  left join ' . TABLE_PRODUCTS_GLOBAL_SORT . ' gs1 on plp.products_id=gs1.products_id  and plp.platform_id =gs1.platform_id, (SELECT @n:=ifnull(max(sort_order),0) from ' . TABLE_PRODUCTS_GLOBAL_SORT . ") r  where gs1.products_id is null {$pl_sql} {$p_sql} order by " . 'plp.products_id' . ') s';
        $ret = tep_db_query($sql);
        return $ret;
    }
    public static function global_sort_serial_index($platform_id, $start = 0, $range = [], $pids = [], $exclude = false)
    {
        //update products_global_sort p, (SELECT @n:=@n+1 as cnt, products_id from products_global_sort, (select @n:=0) c where platform_id=1 ORDER BY `sort_order`, `products_id` ) i set sort_order=cnt where platform_id=1 and p.products_id=i.products_id
        if (is_array($range)) {
            if (!empty($range)) {
                $range = array_map('intval', $range);
            }
        } else {
            $range = [];
        }
        if (is_array($pids)) {
            if (!empty($pids)) {
                $pids = array_map('intval', $pids);
            }
        } else {
            $pids = [intval($pids)];
        }
        $sql = ' update ' . TABLE_PRODUCTS_GLOBAL_SORT . ' p, ' . '(select products_id, sort_order, @n:=@n+1 as cnt from ' . TABLE_PRODUCTS_GLOBAL_SORT . ', (SELECT @n:=' . (int) $start . ') c ' . " where platform_id='" . (int) $platform_id . "' " . (!empty($range) ? ' and sort_order>=' . (int) $range[0] . ' and sort_order<=' . (int) $range[1] : '') . (!empty($pids) ? ' and products_id ' . ($exclude ? 'not ' : '') . "in ('" . implode("','", $pids) . "')" : '') . ' order by sort_order, products_id) i ' . "  set p.sort_order=cnt where platform_id='" . (int) $platform_id . "' and p.products_id=i.products_id ";
        $ret = tep_db_query($sql);
        //echo $sql . " <BR>\n";
        //if ($exclude)      die;
        //return $sql . "<BR>";
        return $ret;
    }
    public static function global_sort_reindex_groupped($platform_id)
    {
        $ret = self::global_sort_serial_index($platform_id);
        if ($ret) {
            $first = true;
            $q = (new \yii\db\Query())->select('p.products_groups_id')->add_select(['min' => new \yii\db\Expression('min(gso.sort_order)'), 'max' => new \yii\db\Expression('max(gso.sort_order)'), 'cnt' => new \yii\db\Expression('count(gso.products_id)'), 'ids' => new \yii\db\Expression('group_concat(gso.products_id)')])->from(['gso' => TABLE_PRODUCTS_GLOBAL_SORT, 'p' => TABLE_PRODUCTS])->and_where('p.products_id=gso.products_id and p.products_groups_id>0')->and_where(['gso.platform_id' => (int) $platform_id])->group_by('p.products_groups_id')->having('min(gso.sort_order)+count(gso.products_id)-1!=max(gso.sort_order)')->order_by('max(gso.sort_order) desc');
            //echo $q ->createCommand()->rawSql . "<br>\n";
            foreach ($q->all() as $gdata) {
                $gdata['ids'] = explode(',', $gdata['ids']);
                if (!$first) {
                    // all indexes could change
                    $to_sort = (new \yii\db\Query())->add_select(['min' => new \yii\db\Expression('min(gso.sort_order)'), 'max' => new \yii\db\Expression('max(gso.sort_order)'), 'cnt' => new \yii\db\Expression('count(gso.products_id)')])->from(['gso' => TABLE_PRODUCTS_GLOBAL_SORT])->and_where(['gso.platform_id' => (int) $platform_id, 'gso.products_id' => $gdata['ids']])->one();
                    $to_sort['ids'] = $gdata['ids'];
                } else {
                    $first = false;
                    $to_sort = $gdata;
                }
                $ret = self::global_sort_reindex_group($to_sort, $platform_id);
                if (!$ret) {
                    break;
                }
            }
        }
        return $ret;
    }
    /**
     * sort_order MUST be serial
     * ToDo fill in $groupData if required data is missed
     * @param array $groupData
     * @param int $platform_id
     * @return bool|string true|error message
     */
    public static function global_sort_reindex_group($group_data, $platform_id)
    {
        $ret = self::global_sort_serial_index($platform_id, $group_data['max'] - $group_data['cnt'], [$group_data['min'], $group_data['max']], $group_data['ids']);
        if ($ret) {
            $ret = self::global_sort_serial_index($platform_id, $group_data['min'] - 1, [$group_data['min'], $group_data['max']], $group_data['ids'], true);
        }
        return $ret;
    }
    public static function copy_global_sort($from_platform_id, $to_platform_id)
    {
        $ret = false;
        if ($from_platform_id > 0 && $to_platform_id > 0) {
            $sql = ' delete from ' . TABLE_PRODUCTS_GLOBAL_SORT . " where platform_id='" . (int) $to_platform_id . "'";
            tep_db_query($sql);
            $sql = ' insert ignore into ' . TABLE_PRODUCTS_GLOBAL_SORT . ' (products_id, platform_id, sort_order) select products_id, ' . (int) $to_platform_id . ', @n:=@n+1 from ' . '(select plp.products_id, gs1.sort_order from ' . TABLE_PLATFORMS_PRODUCTS . ' plp  left join ' . TABLE_PRODUCTS_GLOBAL_SORT . " gs1 on plp.products_id=gs1.products_id  and gs1.platform_id='" . (int) $from_platform_id . "' where plp.platform_id='" . (int) $to_platform_id . "' order by gs1.sort_order is null desc, gs1.sort_order, plp.products_id ) s, (SELECT @n:=0) r  ";
            $ret = tep_db_query($sql);
        }
        return $ret;
    }
    /**
     *
     * @param int $categories_id
     * @param int $start
     * @param array $range
     * @param array $pids
     * @param bool $exclude
     * @return type
     */
    public static function in_category_sort_serial_index($categories_id, $start = 0, $range = [], $pids = [], $exclude = false)
    {
        if (is_array($range)) {
            if (!empty($range)) {
                $range = array_map('intval', $range);
            }
        } else {
            $range = [];
        }
        if (is_array($pids)) {
            if (!empty($pids)) {
                $pids = array_map('intval', $pids);
            }
        } else {
            $pids = [intval($pids)];
        }
        $sql = ' update ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p, ' . '(select products_id, sort_order, @n:=@n+1 as cnt from ' . TABLE_PRODUCTS_TO_CATEGORIES . ', (SELECT @n:=' . (int) $start . ') c ' . " where categories_id='" . (int) $categories_id . "' " . (!empty($range) ? ' and sort_order>=' . (int) $range[0] . ' and sort_order<=' . (int) $range[1] : '') . (!empty($pids) ? ' and products_id ' . ($exclude ? 'not ' : '') . "in ('" . implode("','", $pids) . "')" : '') . ' order by sort_order, products_id desc) i ' . "  set p.sort_order=cnt where categories_id='" . (int) $categories_id . "' and p.products_id=i.products_id ";
        $ret = tep_db_query($sql);
        //echo $sql . " <BR>\n";
        //if ($exclude)      die;
        //return $sql . "<BR>";
        return $ret;
    }
    public static function in_category_sort_reindex_groupped($categories_id)
    {
        $ret = self::in_category_sort_serial_index($categories_id);
        if ($ret) {
            $first = true;
            $q = (new \yii\db\Query())->select('p.products_groups_id')->add_select(['min' => new \yii\db\Expression('min(p2c.sort_order)'), 'max' => new \yii\db\Expression('max(p2c.sort_order)'), 'cnt' => new \yii\db\Expression('count(p2c.products_id)'), 'ids' => new \yii\db\Expression('group_concat(p2c.products_id)')])->from(['p2c' => TABLE_PRODUCTS_TO_CATEGORIES, 'p' => TABLE_PRODUCTS])->and_where('p.products_id=p2c.products_id and p.products_groups_id>0')->and_where(['p2c.categories_id' => (int) $categories_id])->group_by('p.products_groups_id')->having('min(p2c.sort_order)+count(p2c.products_id)-1!=max(p2c.sort_order)')->order_by('max(p2c.sort_order) desc');
            foreach ($q->all() as $gdata) {
                $gdata['ids'] = explode(',', $gdata['ids']);
                if (!$first) {
                    // all indexes could change
                    $to_sort = (new \yii\db\Query())->add_select(['min' => new \yii\db\Expression('min(p2c.sort_order)'), 'max' => new \yii\db\Expression('max(p2c.sort_order)'), 'cnt' => new \yii\db\Expression('count(p2c.products_id)')])->from(['p2c' => TABLE_PRODUCTS_TO_CATEGORIES])->and_where(['p2c.categories_id' => (int) $categories_id, 'p2c.products_id' => $gdata['ids']])->one();
                    $to_sort['ids'] = $gdata['ids'];
                } else {
                    $first = false;
                    $to_sort = $gdata;
                }
                if ($to_sort['min'] + $to_sort['cnt'] - 1 != $to_sort['max']) {
                    $ret = self::in_category_sort_reindex_group($to_sort, $categories_id);
                    if (!$ret) {
                        break;
                    }
                }
            }
        }
        return $ret;
    }
    /**
     * sort_order MUST be serial
     * ToDo fill in $groupData if required data is missed
     * @param array $groupData
     * @param int $categories_id
     * @return bool|string true|error message
     */
    public static function in_category_sort_reindex_group($group_data, $categories_id)
    {
        $ret = self::in_category_sort_serial_index($categories_id, $group_data['min'] - 1, [$group_data['min'], $group_data['max']], $group_data['ids']);
        if ($ret) {
            $ret = self::in_category_sort_serial_index($categories_id, $group_data['min'] + $group_data['cnt'] - 1, [$group_data['min'], $group_data['max']], $group_data['ids'], true);
        }
        return $ret;
    }
    /**
     * Write Stock History by Warehouse and Supplier (and Location)
     * @param string $uProductId Product Id or Product Inventory Id
     * @param integer $warehouseId Warehouse Id. Mandatory
     * @param integer $supplierId Supplier Id. Mandatory
     * @param integer $locationId Location Id
     * @param integer $productQuantity product quantity delta
     * @param array $parameterArray array of History values
     * @return boolean true on success, false on error
     */
    public static function write_history($u_product_id = 0, $warehouse_id = 0, $supplier_id = 0, $location_id = 0, $product_quantity = 0, $parameter_array = [])
    {
        $return = false;
        $product_quantity = (int) $product_quantity;
        $warehouse_id = (int) $warehouse_id;
        $supplier_id = (int) $supplier_id;
        $location_id = (int) $location_id;
        if ($product_quantity != 0 and $warehouse_id > 0 and $supplier_id > 0) {
            $parameter_array = is_array($parameter_array) ? $parameter_array : [];
            $layers_id = (int) (isset($parameter_array['layers_id']) ? $parameter_array['layers_id'] : 0);
            $batch_id = (int) (isset($parameter_array['batch_id']) ? $parameter_array['batch_id'] : 0);
            $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
            $product_record = self::get_record($u_product_id);
            if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freeze_product_record($product_record)) {
                $product_record = self::get_record($u_product_id, false, true);
                $return = true;
                $inventory_record = \common\helpers\Inventory::get_record($u_product_id);
                if (!$inventory_record instanceof \common\models\Inventory) {
                    $u_product_id = $product_record->products_id;
                    $inventory_record = $product_record;
                }
                //$productRecord->warehouse_stock_quantity; //22
                //$productRecord->products_quantity; // 18
                //TODO
                /*$warehouseStockQuantity = 0;
                  $warehouseProductQuantity = 0;
                  if (count(self::getChildArray($productRecord)) > 0) {
                      $warehouseStockQuantity += (int)$productRecord->warehouse_stock_quantity;
                      $warehouseProductQuantity += (int)$productRecord->products_quantity;
                  } else {
                      foreach (\common\helpers\Warehouses::getProductArray($uProductId) as $warehouseProductRecord) {
                          if ((int)$warehouseProductRecord['warehouse_id'] == $warehouseId) {
                              $warehouseStockQuantity += (int)$warehouseProductRecord['warehouse_stock_quantity'];
                              $warehouseProductQuantity += (int)$warehouseProductRecord['products_quantity'];
                          }
                      }
                  }*/
                $is_temporary = (int) trim(isset($parameter_array['is_temporary']) ? $parameter_array['is_temporary'] : 0);
                if ($is_temporary > 0) {
                    $product_quantity *= -1;
                }
                $product_quantity_prefix = $product_quantity > 0 ? '+' : '-';
                unset($warehouse_product_record);
                $product_record->warehouse_stock_quantity = $warehouse_stock_quantity = $product_record->warehouse_stock_quantity + $product_quantity;
                $product_record->products_quantity = $warehouse_product_quantity = $product_record->products_quantity + $product_quantity;
                $product_record->save();
                //log to freeze_stock_history
                $stock_history_record = new \common\models\Stock_History();
                $stock_history_record->set_attributes($parameter_array, false);
                try {
                    $stock_history_record->warehouse_id = $warehouse_id;
                    $stock_history_record->suppliers_id = $supplier_id;
                    $stock_history_record->location_id = $location_id;
                    $stock_history_record->layers_id = $layers_id;
                    $stock_history_record->batch_id = $batch_id;
                    $stock_history_record->products_id = $u_product_id;
                    $stock_history_record->prid = (int) $u_product_id;
                    $stock_history_record->products_model = $inventory_record->products_model != '' ? $inventory_record->products_model : $product_record->products_model;
                    $stock_history_record->products_quantity_before = $warehouse_product_quantity - $product_quantity;
                    $stock_history_record->warehouse_quantity_before = $is_temporary == 0 ? $warehouse_stock_quantity - $product_quantity : $warehouse_stock_quantity;
                    $stock_history_record->products_quantity_update_prefix = $product_quantity_prefix;
                    $stock_history_record->products_quantity_update = abs($product_quantity);
                    $stock_history_record->comments = trim(isset($parameter_array['comments']) ? $parameter_array['comments'] : '');
                    $stock_history_record->admin_id = (int) trim(isset($parameter_array['admin_id']) ? $parameter_array['admin_id'] : 0);
                    $stock_history_record->is_temporary = $is_temporary;
                    $stock_history_record->orders_id = (int) trim(isset($parameter_array['orders_id']) ? $parameter_array['orders_id'] : 0);
                    $stock_history_record->date_added = isset($parameter_array['date_added']) ? $parameter_array['date_added'] : date('Y-m-d H:i:s');
                    $stock_history_record->save();
                } catch (\Exception $exc) {
                    $return = false;
                }
                unset($warehouse_product_quantity);
                unset($warehouse_stock_quantity);
                unset($product_quantity_prefix);
                unset($stock_history_record);
                unset($inventory_record);
                unset($is_temporary);
            } elseif ($product_record instanceof \common\models\Products) {
                $return = true;
                $inventory_record = \common\helpers\Inventory::get_record($u_product_id);
                if (!$inventory_record instanceof \common\models\Inventory) {
                    $u_product_id = $product_record->products_id;
                    $inventory_record = $product_record;
                }
                $warehouse_stock_quantity = 0;
                $warehouse_product_quantity = 0;
                if (count(self::get_child_array($product_record)) > 0) {
                    $warehouse_stock_quantity += (int) $product_record->warehouse_stock_quantity;
                    $warehouse_product_quantity += (int) $product_record->products_quantity;
                } else {
                    foreach (\common\helpers\Warehouses::get_product_array($u_product_id) as $warehouse_product_record) {
                        if ((int) $warehouse_product_record['warehouse_id'] == $warehouse_id) {
                            $warehouse_stock_quantity += (int) $warehouse_product_record['warehouse_stock_quantity'];
                            $warehouse_product_quantity += (int) $warehouse_product_record['products_quantity'];
                        }
                    }
                }
                $is_temporary = (int) trim(isset($parameter_array['is_temporary']) ? $parameter_array['is_temporary'] : 0);
                if ($is_temporary > 0) {
                    $product_quantity *= -1;
                }
                $product_quantity_prefix = $product_quantity > 0 ? '+' : '-';
                unset($warehouse_product_record);
                $stock_history_record = new \common\models\Stock_History();
                $stock_history_record->set_attributes($parameter_array, false);
                try {
                    $stock_history_record->warehouse_id = $warehouse_id;
                    $stock_history_record->suppliers_id = $supplier_id;
                    $stock_history_record->location_id = $location_id;
                    $stock_history_record->layers_id = $layers_id;
                    $stock_history_record->batch_id = $batch_id;
                    $stock_history_record->products_id = $u_product_id;
                    $stock_history_record->prid = (int) $u_product_id;
                    $stock_history_record->products_model = $inventory_record->products_model != '' ? $inventory_record->products_model : $product_record->products_model;
                    $stock_history_record->products_quantity_before = $warehouse_product_quantity - $product_quantity;
                    $stock_history_record->warehouse_quantity_before = $is_temporary == 0 ? $warehouse_stock_quantity - $product_quantity : $warehouse_stock_quantity;
                    $stock_history_record->products_quantity_update_prefix = $product_quantity_prefix;
                    $stock_history_record->products_quantity_update = abs($product_quantity);
                    $stock_history_record->comments = trim(isset($parameter_array['comments']) ? $parameter_array['comments'] : '');
                    $stock_history_record->admin_id = (int) trim(isset($parameter_array['admin_id']) ? $parameter_array['admin_id'] : 0);
                    $stock_history_record->is_temporary = $is_temporary;
                    $stock_history_record->orders_id = (int) trim(isset($parameter_array['orders_id']) ? $parameter_array['orders_id'] : 0);
                    $stock_history_record->date_added = isset($parameter_array['date_added']) ? $parameter_array['date_added'] : date('Y-m-d H:i:s');
                    $stock_history_record->save();
                } catch (\Exception $exc) {
                    $return = false;
                }
                unset($warehouse_product_quantity);
                unset($warehouse_stock_quantity);
                unset($product_quantity_prefix);
                unset($stock_history_record);
                unset($inventory_record);
                unset($is_temporary);
            }
            unset($product_record);
        }
        unset($product_quantity);
        unset($parameter_array);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($u_product_id);
        return $return;
    }
    /**
     * Automatically allocating stock for Product.
     * Rules: see \common\helpers\OrderProduct->doAllocateAutomatic
     * @param mixed $productRecord Product Id or Product Inventory Id or instance of Products model
     * @param boolean $doCache defines should Product stock quantities be calculated and cached. Cleaning up invalid DB records
     * @return boolean true on success, false on error
     */
    public static function do_allocate_automatic($product_record = 0, $do_cache = false)
    {
        $return = false;
        $product_record = self::get_record($product_record);
        if ($product_record instanceof \common\models\Products) {
            $return = true;
            $order_product_id_array = \common\models\Orders_Products::find()->select('orders_products_id')->where(['products_id' => $product_record->products_id])->and_where(['IN', 'orders_products_status', [\common\helpers\Order_Product::OPS_QUOTED, \common\helpers\Order_Product::OPS_STOCK_DEFICIT, \common\helpers\Order_Product::OPS_STOCK_ORDERED, \common\helpers\Order_Product::OPS_RECEIVED]])->column();
            foreach ($order_product_id_array as $order_product_id) {
                $return = (\common\helpers\Order_Product::do_allocate_automatic($order_product_id, false) and $return);
            }
            unset($order_product_id_array);
            unset($order_product_id);
            if ((int) $do_cache > 0) {
                $return = self::do_cache($product_record);
            }
        }
        unset($product_record);
        unset($do_cache);
        return $return;
    }
    /**
     * Get product's stock deficit quantity
     * (Stock deficit = Real quantity - Received [Real quantity -> 0])
     * @param mixed $uProductId Product Id or Product Inventory Id
     * @return int product's stock deficit quantity
     */
    public static function get_stock_deficit($u_product_id = 0)
    {
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        return (int) \common\models\Orders_Products::find()->where(['uprid' => $u_product_id])->and_where(['>', '(products_quantity - (qty_cnld + qty_rcvd))', 0])->and_where(['NOT IN', 'orders_products_status', [\common\helpers\Order_Product::OPS_QUOTED]])->sum('products_quantity - (qty_cnld + qty_rcvd)');
    }
    /**
     * Get Product quantity
     * @param string $uProductId Product Id or Product Inventory Id
     * @return integer Product quantity
     */
    public static function get_quantity($u_product_id = 0)
    {
        $return = 0;
        foreach (\common\helpers\Warehouses::get_product_array($u_product_id) as $warehouse_product_record) {
            $return += $warehouse_product_record['warehouse_stock_quantity'];
        }
        unset($warehouse_product_record);
        return $return;
    }
    /**
     * Get Product quantity on supplier(s)
     * @param string $uProductId Product Id or Product Inventory Id
     * @pararm integer $supplierId specific Supplier Id or default for all suppliers
     * @return integer Product quantity on supplier(s)
     */
    public static function get_quantity_supplier($u_product_id = 0, $supplier_id = -1)
    {
        $return = 0;
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $supplier_id = (int) $supplier_id;
        $supplier_query = \common\models\Suppliers_Products::find()->where(['status' => 1, 'products_id' => (int) $u_product_id]);
        if (\common\helpers\Inventory::is_inventory($u_product_id) == true) {
            $supplier_query->and_where(['uprid' => $u_product_id]);
        }
        if ($supplier_id > -1) {
            $supplier_query->and_where(['suppliers_id' => $supplier_id]);
        }
        foreach ($supplier_query->as_array(true)->all() as $supplier_product_record) {
            $return += $supplier_product_record['suppliers_quantity'];
        }
        unset($supplier_product_record);
        unset($supplier_query);
        return $return;
    }
    /**
     * limited use cases only!!!
     * - there isn't any condition on $productRecord->stock_control if platform is specified.
     * - skipped stock_limit on product and manufacturer.
     * Get available Product quantity
     * @param string $uProductId Product Id or Product Inventory Id
     * @param mixed $platformId Platform Id. If false - calculate for all platforms; if equals 0 - front-end mode, depending on Platform Stock Control; if greater than 0 - calculate for specific platform
     * @param mixed $warehouseId Warehouse id. Calculate for specific Warehouse if passed
     * @param mixed $supplierId Supplier id. Calculate for specific Supplier if passed
     * @param mixed $locationId Location id. Calculate for specific Location if passed
     * @param mixed $layersId Layers id. Calculate for specific Layer if passed
     * @param mixed $batchId Batch id. Calculate for specific Batch if passed
     * @return integer available Product quantity
     */
    public static function get_available($u_product_id = 0, $platform_id = false, $warehouse_id = false, $supplier_id = false, $location_id = false, $layers_id = false, $batch_id = false)
    {
        $product_child_array = self::get_child_array($u_product_id);
        if (count($product_child_array) > 0) {
            $child_stock_array = [];
            foreach ($product_child_array as $prid => $item) {
                $child_stock_array[] = self::get_available($prid, $platform_id, $warehouse_id, $supplier_id, $location_id, $layers_id, $batch_id);
            }
            return min($child_stock_array);
        }
        $return = 0;
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $platform_array = [];
        $is_stock_control = false;
        if ($platform_id === false) {
            foreach (\common\models\Platforms::find()->where(['status' => 1])->as_array(true)->all() as $platform_record) {
                $platform_array[] = (int) $platform_record['platform_id'];
            }
            unset($platform_record);
        } else {
            $platform_id = (int) $platform_id;
            if ($platform_id <= 0) {
                if (defined('PLATFORM_ID') and (int) PLATFORM_ID > 0) {
                    $platform_id = PLATFORM_ID;
                } elseif (\common\classes\platform::default_id() > 0) {
                    $platform_id = \common\classes\platform::default_id();
                } else {
                    $platform_id = \common\classes\platform::current_id();
                }
                $platform_id = (int) $platform_id;
                if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                    $is_stock_control = $ext_scl::update_get_available($u_product_id, $platform_id);
                }
            }
            $platform_array[] = (int) $platform_id;
        }
        $warehouse_product_skip_array = [];
        $product_allocated_skip_array = [];
        $product_allocated_temporary_skip_array = [];
        $product_allocated_array = self::get_allocated_array($u_product_id);
        $product_allocated_temporary_array = self::get_allocated_temporary_array($u_product_id);
        foreach ($platform_array as $platform_id) {
            $warehouse_id_array = self::get_warehouse_id_priority_array($u_product_id, 1, $platform_id);
            $supplier_id_array = self::get_supplier_id_priority_array($u_product_id);
            $location_id_array = self::get_location_id_priority_array($u_product_id);
            $layers_id_array = self::get_layers_id_priority_array($u_product_id);
            $batch_id_array = self::get_batch_id_priority_array($u_product_id);
            foreach (\common\helpers\Warehouses::get_product_array($u_product_id, $platform_id) as $warehouse_product_record) {
                if (isset($warehouse_product_skip_array[$warehouse_product_record['warehouse_id']][$warehouse_product_record['suppliers_id']][$warehouse_product_record['location_id']][$warehouse_product_record['layers_id']][$warehouse_product_record['batch_id']])) {
                    continue;
                }
                if ($warehouse_id !== false and $warehouse_id != $warehouse_product_record['warehouse_id']) {
                    continue;
                }
                if ($supplier_id !== false and $supplier_id != $warehouse_product_record['suppliers_id']) {
                    continue;
                }
                if ($location_id !== false and $location_id != $warehouse_product_record['location_id']) {
                    continue;
                }
                if ($layers_id !== false and $layers_id != $warehouse_product_record['layers_id']) {
                    continue;
                }
                if ($batch_id !== false and $batch_id != $warehouse_product_record['batch_id']) {
                    continue;
                }
                if (!in_array($warehouse_product_record['warehouse_id'], $warehouse_id_array)) {
                    continue;
                }
                if (!in_array($warehouse_product_record['suppliers_id'], $supplier_id_array)) {
                    continue;
                }
                if (!in_array($warehouse_product_record['location_id'], $location_id_array)) {
                    continue;
                }
                if (!in_array($warehouse_product_record['layers_id'], $layers_id_array)) {
                    continue;
                }
                if (!in_array($warehouse_product_record['batch_id'], $batch_id_array)) {
                    continue;
                }
                $warehouse_product_skip_array[$warehouse_product_record['warehouse_id']][$warehouse_product_record['suppliers_id']][$warehouse_product_record['location_id']][$warehouse_product_record['layers_id']][$warehouse_product_record['batch_id']] = $warehouse_product_record;
                $return += $warehouse_product_record['warehouse_stock_quantity'];
            }
            unset($warehouse_product_record);
            foreach ($product_allocated_array as $product_allocated_record) {
                if (isset($product_allocated_skip_array[$product_allocated_record['warehouse_id']][$product_allocated_record['suppliers_id']][$product_allocated_record['location_id']][$product_allocated_record['layers_id']][$product_allocated_record['batch_id']][$product_allocated_record['orders_products_id']])) {
                    continue;
                }
                if ($is_stock_control !== false && !in_array((int) $product_allocated_record['platform_id'], $platform_array)) {
                    continue;
                }
                if ($warehouse_id !== false and $warehouse_id != $product_allocated_record['warehouse_id']) {
                    continue;
                }
                if ($supplier_id !== false and $supplier_id != $product_allocated_record['suppliers_id']) {
                    continue;
                }
                if ($location_id !== false and $location_id != $product_allocated_record['location_id']) {
                    continue;
                }
                if ($layers_id !== false and $layers_id != $product_allocated_record['layers_id']) {
                    continue;
                }
                if ($batch_id !== false and $batch_id != $product_allocated_record['batch_id']) {
                    continue;
                }
                if (!in_array($product_allocated_record['warehouse_id'], $warehouse_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_record['suppliers_id'], $supplier_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_record['location_id'], $location_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_record['layers_id'], $layers_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_record['batch_id'], $batch_id_array)) {
                    continue;
                }
                $product_allocated_skip_array[$product_allocated_record['warehouse_id']][$product_allocated_record['suppliers_id']][$product_allocated_record['location_id']][$product_allocated_record['layers_id']][$product_allocated_record['batch_id']][$product_allocated_record['orders_products_id']] = $product_allocated_record;
                $return += $product_allocated_record['allocate_dispatched'] - $product_allocated_record['allocate_received'];
            }
            unset($product_allocated_record);
            foreach ($product_allocated_temporary_array as $product_allocated_temporary_record) {
                if (isset($product_allocated_temporary_skip_array[$product_allocated_temporary_record['warehouse_id']][$product_allocated_temporary_record['suppliers_id']][$product_allocated_temporary_record['location_id']][$product_allocated_temporary_record['layers_id']][$product_allocated_temporary_record['batch_id']][$product_allocated_temporary_record['temporary_stock_id']])) {
                    continue;
                }
                if ($warehouse_id !== false and $warehouse_id != $product_allocated_temporary_record['warehouse_id']) {
                    continue;
                }
                if ($supplier_id !== false and $supplier_id != $product_allocated_temporary_record['suppliers_id']) {
                    continue;
                }
                if ($location_id !== false and $location_id != $product_allocated_temporary_record['location_id']) {
                    continue;
                }
                if ($layers_id !== false and $layers_id != $product_allocated_temporary_record['layers_id']) {
                    continue;
                }
                if ($batch_id !== false and $batch_id != $product_allocated_temporary_record['batch_id']) {
                    continue;
                }
                if (!in_array($product_allocated_temporary_record['warehouse_id'], $warehouse_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_temporary_record['suppliers_id'], $supplier_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_temporary_record['location_id'], $location_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_temporary_record['layers_id'], $layers_id_array)) {
                    continue;
                }
                if (!in_array($product_allocated_temporary_record['batch_id'], $batch_id_array)) {
                    continue;
                }
                $product_allocated_temporary_skip_array[$product_allocated_temporary_record['warehouse_id']][$product_allocated_temporary_record['suppliers_id']][$product_allocated_temporary_record['location_id']][$product_allocated_temporary_record['layers_id']][$product_allocated_temporary_record['batch_id']][$product_allocated_temporary_record['temporary_stock_id']] = $product_allocated_temporary_record;
                $return -= $product_allocated_temporary_record['temporary_stock_quantity'];
            }
            unset($product_allocated_temporary_record);
            unset($warehouse_id_array);
            unset($supplier_id_array);
            unset($location_id_array);
            unset($layers_id_array);
            unset($batch_id_array);
        }
        unset($product_allocated_temporary_skip_array);
        unset($product_allocated_temporary_array);
        unset($warehouse_product_skip_array);
        unset($product_allocated_skip_array);
        unset($product_allocated_array);
        unset($platform_array);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layers_id);
        unset($batch_id);
        unset($platform_id);
        unset($u_product_id);
        if ($is_stock_control !== false) {
            $return = $is_stock_control < $return ? $is_stock_control : $return;
        }
        unset($is_stock_control);
        return $return;
    }
    /**
     * Validate and updating Product Allocation records.
     * Updating Dispatched based on Delivered and Received based on Disptached.
     * Deleting orphan allocation records or where Received equals 0.
     * Rule: Received >= Dispatched >= Delivered
     * @param string $uProductId Product Id or Product Inventory Id
     * @return boolean false on error, true - if validation is passed
     */
    public static function is_valid_allocated($u_product_id = 0)
    {
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $order_product_skip_list = [];
        foreach (self::get_allocated_array($u_product_id, false) as $product_allocated) {
            if (!isset($order_product_skip_list[$product_allocated->orders_products_id])) {
                $order_product_skip_list[$product_allocated->orders_products_id] = $product_allocated->orders_products_id;
                $order_product_record = \common\helpers\Order_Product::get_record($product_allocated->orders_products_id);
                if ($order_product_record instanceof \common\models\Orders_Products) {
                    if (\common\helpers\Order_Product::is_valid_allocated($order_product_record) != true) {
                        unset($order_product_record);
                        return false;
                    }
                } else {
                    $product_allocated->delete();
                }
                unset($order_product_record);
            }
        }
        unset($order_product_skip_list);
        unset($product_allocated);
        unset($u_product_id);
        return true;
    }
    /**
     * Get Product Allocation array
     * @param string $uProductId Product Id or Product Inventory Id
     * @param boolean $asArray switching return type between array of arrays or array of instances of OrdersProductsAllocate
     * @param boolean $returnAwaiting false - return all product allocations, true - return not fully processed allocations
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_allocated_array($u_product_id = 0, $as_array = true, $return_awaiting = true)
    {
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $return = \common\models\Orders_Products_Allocate::find()->and_where(['products_id' => $u_product_id])->and_where(['prid' => (int) $u_product_id])->as_array($as_array);
        if ($return_awaiting > 0) {
            $return->and_where('allocate_dispatched < allocate_received');
        }
        $return = $return->all();
        unset($return_awaiting);
        unset($u_product_id);
        unset($as_array);
        return is_array($return) ? $return : [];
    }
    /**
     * Get Product Allocation quantity
     * @param string $uProductId Product Id or Product Inventory Id
     * @return integer Product Allocation quantity
     */
    public static function get_allocated($u_product_id = 0)
    {
        $return = 0;
        foreach (self::get_allocated_array($u_product_id) as $product_allocated) {
            $return += $product_allocated['allocate_received'] - $product_allocated['allocate_dispatched'];
        }
        unset($product_allocated);
        return $return;
    }
    /**
     * Get Product Temporary Allocation array
     * Behaviour: depending on TEMPORARY_STOCK_ENABLE configuration value
     * @param string $uProductId Product Id or Product Inventory Id
     * @param boolean $asArray switching return type between array of arrays or array of instances
     * @param boolean $isBackend switching return type between Front-end OrdersProductsTemporaryStock if false, and Back-end OrdersProductsAllocate if true
     * @return array array of mixed depending on $asArray and $isBackend parameters
     */
    public static function get_allocated_temporary_array($u_product_id = 0, $as_array = true, $is_backend = false)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        if ((int) $is_backend > 0) {
            $return = \common\models\Orders_Products_Allocate::find()->where(['products_id' => $u_product_id])->and_where(['prid' => (int) $u_product_id])->and_where(['is_temporary' => 1])->and_where(['allocate_dispatched' => 0])->as_array($as_array)->all();
        } else if (defined('TEMPORARY_STOCK_ENABLE') and TEMPORARY_STOCK_ENABLE == 'true') {
            if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
                $return = $ext::get_allocated_temporary_array($u_product_id, $as_array);
            } else {
                $return = \common\models\Orders_Products_Temporary_Stock::find()->where(['normalize_id' => $u_product_id])->and_where(['prid' => (int) $u_product_id])->as_array($as_array)->all();
            }
        }
        unset($u_product_id);
        unset($as_array);
        return is_array($return) ? $return : [];
    }
    /**
     * Get Product Temporary Allocation quantity
     * @param string $uProductId Product Id or Product Inventory Id
     * @param boolean $isBackend switching return value calculation between Front-end OrdersProductsTemporaryStock if false, and Back-end OrdersProductsAllocate if true
     * @return integer Front-end or Back-end Product Temporary Allocation quantity depending on $isBackend parameter
     */
    public static function get_allocated_temporary($u_product_id = 0, $is_backend = false)
    {
        $return = 0;
        if ((int) $is_backend > 0) {
            foreach (self::get_allocated_temporary_array($u_product_id, true, true) as $product_allocated_temporary) {
                $return += (int) $product_allocated_temporary['allocate_received'] - (int) $product_allocated_temporary['allocate_dispatched'];
            }
            unset($product_allocated_temporary);
        } else {
            foreach (self::get_allocated_temporary_array($u_product_id, true, false) as $product_allocated_temporary) {
                $return += (int) $product_allocated_temporary['temporary_stock_quantity'];
            }
            unset($product_allocated_temporary);
        }
        unset($u_product_id);
        return $return;
    }
    /**
     * Get product's stock ordered quantity
     * (Dependent on pending Purchase Orders Products)
     * @param string $uProductId Product Id or Product Inventory Id
     * @return int product's stock ordered quantity
     */
    public static function get_stock_ordered($u_product_id = 0, $is_strict = false)
    {
        $return = 0;
        if (\common\helpers\Acl::check_extension_allowed('PurchaseOrders')) {
            $return = \common\extensions\Purchase_Orders\helpers\Purchase_Order::get_stock_ordered($u_product_id, $is_strict);
        }
        return $return;
    }
    /**
     * Get Warehouse stock allocation priority for Product.
     * Rule: if $platformId === false - get priority for all platforms available for product, else - get for current platform
     * Behaviour: dependent on WarehousePriority extension
     * @param string $uProductId Product Id or Product Inventory Id
     * @param integer $productQuantity Product quantity
     * @param integer $platformId Platform Id
     * @return array array of Warehouse Id
     */
    public static function get_warehouse_id_priority_array($u_product_id = 0, $product_quantity = 1, $platform_id = 0)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $product_quantity = (int) $product_quantity > 0 ? (int) $product_quantity : 1;
        if ($platform_id === false) {
            foreach (\common\models\Platforms_Products::find()->alias('pp')->left_join(\common\models\Platforms::table_name() . ' AS p', 'pp.platform_id = p.platform_id')->where(['p.status' => '1'])->and_where(['pp.products_id' => (int) $u_product_id])->order_by(['p.is_virtual' => SORT_ASC, 'p.is_default' => SORT_DESC])->as_array(true)->all() as $platforms_products_record) {
                foreach (self::get_warehouse_id_priority_array($u_product_id, $product_quantity, (int) $platforms_products_record['platform_id']) as $warehouse_id) {
                    $return[$warehouse_id] = $warehouse_id;
                }
                unset($warehouse_id);
            }
            unset($platforms_products_record);
            return $return;
        }
        $platform_id = (int) $platform_id;
        if ($platform_id <= 0) {
            if (defined('PLATFORM_ID') and (int) PLATFORM_ID > 0) {
                $platform_id = PLATFORM_ID;
            } elseif (\common\classes\platform::default_id() > 0) {
                $platform_id = \common\classes\platform::default_id();
            } else {
                $platform_id = \common\classes\platform::current_id();
            }
            $platform_id = (int) $platform_id;
        }
        $is_stock_control = false;
        if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
            $warehouse_array = $ext_scl::update_get_available($u_product_id, $platform_id);
            if (is_array($warehouse_array)) {
                $return = $warehouse_array;
                $is_stock_control = true;
            }
            unset($warehouse_array);
        }
        if ($is_stock_control == false) {
            $warehouse_query = \common\models\Warehouses::find()->alias('w')->left_join(\common\models\Warehouses_Platforms::table_name() . ' AS wtp', ['and', 'w.warehouse_id = wtp.warehouse_id', ['wtp.platform_id' => $platform_id]])->where(['ifnull(wtp.status, w.status)' => 1])->order_by(['ifnull(wtp.sort_order, w.sort_order)' => SORT_ASC, 'w.warehouse_name' => SORT_ASC])->cache(defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? self::PRODUCT_RECORD_CACHE : -1)->as_array(true);
            foreach ($warehouse_query->all() as $warehouse_record) {
                $return[] = (int) $warehouse_record['warehouse_id'];
            }
            unset($warehouse_record);
            unset($warehouse_query);
            /**
             * @var $extension \common\extensions\WarehousePriority\WarehousePriority
             */
            if ($extension = \common\helpers\Extensions::is_allowed('WarehousePriority')) {
                $rules_array = ['products_id' => $u_product_id, 'products_quantity' => $product_quantity];
                $warehouse_id_priority_array = $extension::get_instance()->get_preferred_warehouse_id($rules_array, $platform_id);
                unset($rules_array);
                if (count($warehouse_id_priority_array) > 0) {
                    $return = $warehouse_id_priority_array;
                }
                unset($warehouse_id_priority_array);
            }
            unset($extension);
        }
        unset($product_quantity);
        unset($is_stock_control);
        unset($platform_id);
        unset($u_product_id);
        return $return;
    }
    /**
     * Get Supplier stock allocation priority for Product.
     * Behaviour: dependent on SupplierPriority extension
     * @param string $uProductId Product Id or Product Inventory Id
     * @return array array of Supplier Id
     */
    public static function get_supplier_id_priority_array($u_product_id = 0)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $product_record = self::get_record($u_product_id);
        if ($product_record instanceof \common\models\Products) {
            $supplier_query = \common\models\Suppliers::find()->alias('s')->left_join(\common\models\Suppliers_Products::table_name() . ' AS sp', 's.suppliers_id = sp.suppliers_id')->where(['sp.products_id' => (int) $u_product_id])->and_where(['sp.uprid' => $u_product_id])->and_where(['s.status' => 1])->and_where(['sp.status' => 1])->cache(defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? self::PRODUCT_RECORD_CACHE : -1)->order_by(['s.sort_order' => SORT_ASC, 's.suppliers_name' => SORT_ASC]);
            foreach ($supplier_query->all() as $supplier_record) {
                $return[] = (int) $supplier_record['suppliers_id'];
            }
            unset($supplier_record);
            unset($supplier_query);
            if ($extension = \common\helpers\Acl::check_extension_allowed('SupplierPriority', 'getInstance')) {
                $rules_array = \common\helpers\Price_Formula::calculate_supplier_products($u_product_id);
                $supplier_id_priority_array = $extension::get_instance()->get_preferred_supplier_id($rules_array);
                unset($rules_array);
                if (count($supplier_id_priority_array) > 0) {
                    $return = $supplier_id_priority_array;
                }
                unset($supplier_id_priority_array);
            }
            unset($extension);
            if (count($return) == 0) {
                $return[] = (int) \common\helpers\Suppliers::get_default_supplier_id();
            }
        }
        unset($product_record);
        unset($u_product_id);
        return $return;
    }
    /**
     * Get Location stock allocation priority for Product.
     * @param string $uProductId Product Id or Product Inventory Id
     * @return array array of Location Id
     */
    public static function get_location_id_priority_array($u_product_id = 0)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $product_record = self::get_record($u_product_id);
        if ($product_record instanceof \common\models\Products) {
            $location_query = \common\models\Warehouses_Products::find()->where(['products_id' => $u_product_id])->and_where(['prid' => $product_record->products_id])->order_by(['SUM(warehouse_stock_quantity)' => SORT_DESC])->group_by(['location_id'])->as_array(true);
            foreach ($location_query->all() as $warehouse_product_record) {
                $return[] = (int) $warehouse_product_record['location_id'];
            }
            unset($warehouse_product_record);
            unset($location_query);
        }
        unset($product_record);
        unset($u_product_id);
        return $return;
    }
    /**
     * Get Layers stock allocation priority for Product.
     * @param string $uProductId Product Id or Product Inventory Id
     * @return array array of Layers Id
     */
    public static function get_layers_id_priority_array($u_product_id = 0)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $product_record = self::get_record($u_product_id);
        if ($product_record instanceof \common\models\Products) {
            $layers_query = \common\models\Warehouses_Products::find()->alias('wp')->left_join(['wpl' => \common\models\Warehouses_Products_Layers::table_name()], 'wp.layers_id = wpl.layers_id')->where(['wp.products_id' => $u_product_id])->and_where(['wp.prid' => $product_record->products_id])->order_by(new \yii\db\Expression('wpl.expiry_date is null asc, wpl.expiry_date asc'))->group_by(['wp.layers_id'])->as_array(true);
            foreach ($layers_query->all() as $warehouse_product_record) {
                $return[] = (int) $warehouse_product_record['layers_id'];
            }
            unset($warehouse_product_record);
            unset($layers_query);
        }
        unset($product_record);
        unset($u_product_id);
        return $return;
    }
    /**
     * Get Batch stock allocation priority for Product.
     * @param string $uProductId Product Id or Product Inventory Id
     * @return array array of Batch Id
     */
    public static function get_batch_id_priority_array($u_product_id = 0)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $product_record = self::get_record($u_product_id);
        if ($product_record instanceof \common\models\Products) {
            $batch_query = \common\models\Warehouses_Products::find()->alias('wp')->left_join(['wpb' => \common\models\Warehouses_Products_Batches::table_name()], 'wp.batch_id = wpb.batch_id')->where(['wp.products_id' => $u_product_id])->and_where(['wp.prid' => $product_record->products_id])->order_by(new \yii\db\Expression('wpb.batch_name is null asc, wpb.batch_name asc'))->group_by(['wp.batch_id'])->as_array(true);
            foreach ($batch_query->all() as $warehouse_product_record) {
                $return[] = (int) $warehouse_product_record['batch_id'];
            }
            unset($warehouse_product_record);
            unset($batch_query);
        }
        unset($product_record);
        unset($u_product_id);
        return $return;
    }
    /**
     * Get array of Product's Children Set
     * @param mixed $productRecord Product Id or Product Inventory Id or instance of Products model
     * @param boolean $asArray switching return type between array of arrays or array of instances of SetsProducts
     * @param integer $recursionBreak current recursion nested level, max = 10
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_child_array($product_record = 0, $as_array = true, $recursion_break = 0)
    {
        $return = [];
        $product_record = self::get_record($product_record);
        if ($product_record instanceof \common\models\Products) {
            if ((int) $product_record->is_bundle > 0) {
                foreach (\common\models\Sets_Products::find()->alias('sp')->left_join(\common\models\Products::table_name() . ' AS p', 'sp.product_id = p.products_id')->where(['sp.sets_id' => (int) $product_record->products_id])->and_where(['p.products_status_bundle' => 1])->as_array($as_array)->all() as $product_set_record) {
                    $u_product_id = trim(is_array($product_set_record) ? $product_set_record['product_id'] : $product_set_record->product_id);
                    if ($recursion_break < 10 and count(self::get_child_array($u_product_id, true, $recursion_break + 1)) == 0) {
                        $return[$u_product_id] = $product_set_record;
                    }
                    unset($u_product_id);
                }
                unset($product_set_record);
            }
        }
        unset($product_record);
        unset($as_array);
        return $return;
    }
    /**
     * Calculate and cache Product stock quantities. Cleaning up invalid DB records
     * @param mixed $productRecord Product Id or Product Inventory Id or instance of Products model
     * @return boolean true on success, false on error
     */
    public static function do_cache($product_record = 0)
    {
        $return = false;
        $product_record = self::get_record($product_record, false);
        if (($ext_freeze = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext_freeze::is_freeze_product_record($product_record)) {
            $product_id = trim($product_record->products_id);
            //warehouse_stock_quantity(tmp) = warehouse_stock_quantity(real) - allocated_stock_quantity(real)
            $warehouse_stock_quantity_real = 0;
            foreach (\common\models\Warehouses_Products::find()->where(['prid' => $product_id])->all() as $warehouse_product_record) {
                $warehouse_stock_quantity_real = $warehouse_product_record->warehouse_stock_quantity;
            }
            unset($warehouse_product_record);
            $allocated_stock_quantity_real = 0;
            foreach (\common\models\Orders_Products_Allocate::find()->where(['prid' => $product_id])->as_array(true)->all() as $product_allocated_record) {
                $allocated_stock_quantity_real += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
            }
            unset($product_allocated_record);
            $real_product_record = self::get_record($product_id, false, true);
            if ($real_product_record instanceof \common\models\Products) {
                $real_product_record->warehouse_stock_quantity = $warehouse_stock_quantity_real;
                $real_product_record->allocated_stock_quantity = $allocated_stock_quantity_real;
                $real_product_record->products_quantity = $real_product_record->warehouse_stock_quantity - ($real_product_record->allocated_stock_quantity + $real_product_record->temporary_stock_quantity);
                $real_product_record->save();
                //---
                $inventory_array = [];
                $inventory_id = 0;
                $warehouse_product_array = [];
                foreach (\common\models\Warehouses_Products::find()->where(['prid' => $product_id])->all() as $warehouse_product_record) {
                    $key = (int) $warehouse_product_record->warehouse_id . '_' . (int) $warehouse_product_record->suppliers_id . '_' . (int) $warehouse_product_record->location_id . '_' . (int) $warehouse_product_record->layers_id . '_' . (int) $warehouse_product_record->batch_id;
                    $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity;
                    $warehouse_product_record->allocated_stock_quantity = 0;
                    $warehouse_product_record->temporary_stock_quantity = 0;
                    $warehouse_product_array[$key][$warehouse_product_record->products_id] = $warehouse_product_record;
                    unset($key);
                }
                unset($warehouse_product_record);
                $product_allocated_temporary_array = [];
                if (defined('TEMPORARY_STOCK_ENABLE') and TEMPORARY_STOCK_ENABLE == 'true') {
                    foreach (\common\models\Orders_Products_Temporary_Stock::find()->where(['prid' => $product_id])->as_array(true)->all() as $product_allocated_temporary_record) {
                        $key = (int) $product_allocated_temporary_record['warehouse_id'] . '_' . (int) $product_allocated_temporary_record['suppliers_id'] . '_' . (int) $product_allocated_temporary_record['location_id'] . '_' . (int) $product_allocated_temporary_record['layers_id'] . '_' . (int) $product_allocated_temporary_record['batch_id'];
                        $product_allocated_temporary_array[$key][] = $product_allocated_temporary_record;
                        unset($key);
                    }
                    unset($product_allocated_temporary_record);
                }
                $product_allocated_array = [];
                foreach (\common\models\Orders_Products_Allocate::find()->where(['prid' => $product_id])->and_where('allocate_dispatched < allocate_received')->as_array(true)->all() as $product_allocated_record) {
                    $key = (int) $product_allocated_record['warehouse_id'] . '_' . (int) $product_allocated_record['suppliers_id'] . '_' . (int) $product_allocated_record['location_id'] . '_' . (int) $product_allocated_record['layers_id'] . '_' . (int) $product_allocated_record['batch_id'];
                    $product_allocated_array[$key][] = $product_allocated_record;
                    unset($key);
                }
                unset($product_allocated_record);
                foreach ($product_allocated_array as $key => $order_product_allocated_array) {
                    foreach ($order_product_allocated_array as $product_allocated_record) {
                        if (isset($warehouse_product_array[$key][$product_allocated_record['products_id']])) {
                            $warehouse_product_record = $warehouse_product_array[$key][$product_allocated_record['products_id']];
                        } else {
                            $warehouse_product_record = new \common\models\Warehouses_Products();
                            $warehouse_product_record->prid = $product_allocated_record['prid'];
                            $warehouse_product_record->products_id = $product_allocated_record['products_id'];
                            $warehouse_product_record->warehouse_id = $product_allocated_record['warehouse_id'];
                            $warehouse_product_record->suppliers_id = $product_allocated_record['suppliers_id'];
                            $warehouse_product_record->location_id = $product_allocated_record['location_id'];
                            $warehouse_product_record->layers_id = $product_allocated_record['layers_id'];
                            $warehouse_product_record->batch_id = $product_allocated_record['batch_id'];
                        }
                        $warehouse_product_record->allocated_stock_quantity += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
                        $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity - $warehouse_product_record->allocated_stock_quantity;
                        $warehouse_product_array[$key][$product_allocated_record['products_id']] = $warehouse_product_record;
                    }
                    unset($product_allocated_record);
                }
                unset($order_product_allocated_array);
                unset($product_allocated_array);
                unset($key);
                foreach ($product_allocated_temporary_array as $key => $order_product_allocated_temporary_array) {
                    foreach ($order_product_allocated_temporary_array as $product_allocated_temporary_record) {
                        if (isset($warehouse_product_array[$key][$product_allocated_temporary_record['normalize_id']])) {
                            $warehouse_product_record = $warehouse_product_array[$key][$product_allocated_temporary_record['normalize_id']];
                        } else {
                            $warehouse_product_record = new \common\models\Warehouses_Products();
                            $warehouse_product_record->prid = $product_allocated_temporary_record['prid'];
                            $warehouse_product_record->products_id = $product_allocated_temporary_record['normalize_id'];
                            $warehouse_product_record->warehouse_id = $product_allocated_temporary_record['warehouse_id'];
                            $warehouse_product_record->suppliers_id = $product_allocated_temporary_record['suppliers_id'];
                            $warehouse_product_record->location_id = $product_allocated_temporary_record['location_id'];
                            $warehouse_product_record->layers_id = $product_allocated_temporary_record['layers_id'];
                            $warehouse_product_record->batch_id = $product_allocated_temporary_record['batch_id'];
                        }
                        $warehouse_product_record->temporary_stock_quantity += $product_allocated_temporary_record['temporary_stock_quantity'];
                        $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity - ($warehouse_product_record->allocated_stock_quantity + $warehouse_product_record->temporary_stock_quantity);
                        $warehouse_product_array[$key][$product_allocated_temporary_record['normalize_id']] = $warehouse_product_record;
                        unset($warehouse_product_record);
                    }
                    unset($product_allocated_temporary_record);
                }
                unset($order_product_allocated_temporary_array);
                unset($product_allocated_temporary_array);
                unset($key);
                foreach ($warehouse_product_array as $warehouse_product_record_array) {
                    if ($inventory_id > 0) {
                        $warehouse_product_collection_record = new \common\models\Warehouses_Products();
                    }
                    foreach ($warehouse_product_record_array as $warehouse_product_record) {
                        try {
                            $warehouse_product_record->save();
                            $warehouse_id_array = self::get_warehouse_id_priority_array($warehouse_product_record->products_id, 1, false);
                            $supplier_id_array = self::get_supplier_id_priority_array($warehouse_product_record->products_id);
                            if (!in_array((int) $warehouse_product_record->warehouse_id, $warehouse_id_array) or !in_array((int) $warehouse_product_record->suppliers_id, $supplier_id_array)) {
                                $warehouse_product_record->warehouse_stock_quantity = 0;
                                $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity - ($warehouse_product_record->allocated_stock_quantity + $warehouse_product_record->temporary_stock_quantity);
                            }
                            unset($warehouse_id_array);
                            unset($supplier_id_array);
                            if (isset($warehouse_product_collection_record)) {
                                $warehouse_product_collection_record->prid = $warehouse_product_record->prid;
                                $warehouse_product_collection_record->products_id = $warehouse_product_record->prid;
                                $warehouse_product_collection_record->warehouse_id = $warehouse_product_record->warehouse_id;
                                $warehouse_product_collection_record->suppliers_id = $warehouse_product_record->suppliers_id;
                                $warehouse_product_collection_record->location_id = $warehouse_product_record->location_id;
                                $warehouse_product_collection_record->layers_id = $warehouse_product_record->layers_id;
                                $warehouse_product_collection_record->batch_id = $warehouse_product_record->batch_id;
                                $warehouse_product_collection_record->warehouse_stock_quantity += $warehouse_product_record->warehouse_stock_quantity;
                                $warehouse_product_collection_record->allocated_stock_quantity += $warehouse_product_record->allocated_stock_quantity;
                                $warehouse_product_collection_record->temporary_stock_quantity += $warehouse_product_record->temporary_stock_quantity;
                                $warehouse_product_collection_record->products_quantity += $warehouse_product_record->products_quantity;
                            }
                            if ($inventory_id > 0 and isset($inventory_array[$warehouse_product_record->products_id])) {
                                $inventory_array[$warehouse_product_record->products_id]->warehouse_stock_quantity += $warehouse_product_record->warehouse_stock_quantity;
                                $inventory_array[$warehouse_product_record->products_id]->allocated_stock_quantity += $warehouse_product_record->allocated_stock_quantity;
                                $inventory_array[$warehouse_product_record->products_id]->temporary_stock_quantity += $warehouse_product_record->temporary_stock_quantity;
                                $inventory_array[$warehouse_product_record->products_id]->products_quantity += $warehouse_product_record->products_quantity;
                            }
                            //$productRecord->warehouse_stock_quantity += $warehouseProductRecord->warehouse_stock_quantity;
                            //$productRecord->allocated_stock_quantity += $warehouseProductRecord->allocated_stock_quantity;
                            //$productRecord->temporary_stock_quantity += $warehouseProductRecord->temporary_stock_quantity;
                            //$productRecord->products_quantity += $warehouseProductRecord->products_quantity;
                        } catch (\Exception $exc) {
                            $return = false;
                        }
                    }
                    if (isset($warehouse_product_collection_record)) {
                        try {
                            $warehouse_product_collection_record->save();
                        } catch (\Exception $exc) {
                            $return = false;
                        }
                    }
                    unset($warehouse_product_collection_record);
                    unset($warehouse_product_record);
                }
                unset($warehouse_product_record_array);
                unset($warehouse_product_array);
                //---
            }
            $product_record->warehouse_stock_quantity = $warehouse_stock_quantity_real - $allocated_stock_quantity_real;
            $allocated_stock_quantity = $ext_freeze::allocated_stock_quantity($product_id);
            $product_record->allocated_stock_quantity = $allocated_stock_quantity;
            $temporary_stock_quantity = $ext_freeze::temporary_stock_quantity($product_id);
            $product_record->temporary_stock_quantity = $temporary_stock_quantity;
            $product_record->products_quantity = $product_record->warehouse_stock_quantity - ($product_record->allocated_stock_quantity + $product_record->temporary_stock_quantity);
            /*$return = true;
              try {
                  $productRecord->save();
              } catch (\Exception $exc) {
                  $return = false;
              }*/
            //$productRecord = self::getRecord($productId, false, true);
        }
        if ($product_record instanceof \common\models\Products) {
            if (self::do_cache_parent($product_record) == true) {
                return true;
            }
            $product_id = trim($product_record->products_id);
            $product_record->warehouse_stock_quantity = 0;
            $product_record->allocated_stock_quantity = 0;
            $product_record->temporary_stock_quantity = 0;
            $product_record->products_quantity = 0;
            $inventory_array = [];
            $inventory_id = 0;
            if (\common\helpers\Extensions::is_allowed('Inventory')) {
                foreach (\common\models\Inventory::find()->where(['prid' => $product_record->products_id])->all() as $inventory_record) {
                    if (\common\helpers\Inventory::is_inventory($inventory_record->products_id) != true) {
                        continue;
                    }
                    $inventory_record->warehouse_stock_quantity = 0;
                    $inventory_record->allocated_stock_quantity = 0;
                    $inventory_record->temporary_stock_quantity = 0;
                    $inventory_record->products_quantity = 0;
                    $inventory_array[$inventory_record->products_id] = $inventory_record;
                }
                unset($inventory_record);
            }
            if (count($inventory_array) == 0) {
                $inventory_array[$product_id] = $product_id;
            } else {
                $inventory_id = $product_id;
                \common\models\Warehouses_Products::delete_all(['AND', ['prid' => $product_id], ['NOT IN', 'products_id', array_map('strval', array_keys($inventory_array))]]);
            }
            if ((int) $product_record->manual_stock_unlimited > 0) {
                if ($ext = \common\helpers\Acl::check_extension_allowed('SupplierPurchase', 'allowed')) {
                    \common\models\Warehouses_Products::delete_all(['prid' => $product_id, 'suppliers_id' => \common\helpers\Suppliers::get_default_supplier_id()]);
                } else {
                    \common\models\Warehouses_Products::delete_all(['prid' => $product_id]);
                }
                foreach ($inventory_array as $i_id => $i_record) {
                    $warehouse_id = self::get_warehouse_id_priority_array($i_id, 1, false);
                    reset($warehouse_id);
                    $warehouse_id = (int) current($warehouse_id);
                    $supplier_id = self::get_supplier_id_priority_array($i_id);
                    reset($supplier_id);
                    $supplier_id = (int) current($supplier_id);
                    $location_id = 0;
                    $layers_id = 0;
                    $batch_id = 0;
                    $warehouse_product_record = new \common\models\Warehouses_Products();
                    $warehouse_product_record->warehouse_id = $warehouse_id;
                    $warehouse_product_record->suppliers_id = $supplier_id;
                    $warehouse_product_record->location_id = $location_id;
                    $warehouse_product_record->layers_id = $layers_id;
                    $warehouse_product_record->batch_id = $batch_id;
                    $warehouse_product_record->products_id = $i_id;
                    $warehouse_product_record->prid = (int) $i_id;
                    $warehouse_product_record->products_model = $product_record->products_model;
                    if ($i_record instanceof \common\models\Inventory and $i_record->products_model != '') {
                        $warehouse_product_record->products_model = $i_record->products_model;
                    }
                    $warehouse_product_record->warehouse_stock_quantity = 9999;
                    try {
                        $warehouse_product_record->save();
                    } catch (\Exception $exc) {
                    }
                    unset($warehouse_product_record);
                    unset($warehouse_id);
                    unset($supplier_id);
                    unset($location_id);
                }
                unset($i_record);
                unset($i_id);
            }
            $warehouse_product_array = [];
            foreach (\common\models\Warehouses_Products::find()->where(['prid' => $product_id])->and_where(['IN', 'products_id', array_map('strval', array_keys($inventory_array))])->all() as $warehouse_product_record) {
                $key = (int) $warehouse_product_record->warehouse_id . '_' . (int) $warehouse_product_record->suppliers_id . '_' . (int) $warehouse_product_record->location_id . '_' . (int) $warehouse_product_record->layers_id . '_' . (int) $warehouse_product_record->batch_id;
                $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity;
                $warehouse_product_record->allocated_stock_quantity = 0;
                $warehouse_product_record->temporary_stock_quantity = 0;
                $warehouse_product_array[$key][$warehouse_product_record->products_id] = $warehouse_product_record;
                unset($key);
            }
            unset($warehouse_product_record);
            $product_allocated_array = [];
            foreach (\common\models\Orders_Products_Allocate::find()->where(['prid' => $product_id])->and_where('allocate_dispatched < allocate_received')->and_where(['IN', 'products_id', array_map('strval', array_keys($inventory_array))])->as_array(true)->all() as $product_allocated_record) {
                $key = (int) $product_allocated_record['warehouse_id'] . '_' . (int) $product_allocated_record['suppliers_id'] . '_' . (int) $product_allocated_record['location_id'] . '_' . (int) $product_allocated_record['layers_id'] . '_' . (int) $product_allocated_record['batch_id'];
                $product_allocated_array[$key][] = $product_allocated_record;
                unset($key);
            }
            unset($product_allocated_record);
            $product_allocated_temporary_array = [];
            if (defined('TEMPORARY_STOCK_ENABLE') and TEMPORARY_STOCK_ENABLE == 'true') {
                foreach (\common\models\Orders_Products_Temporary_Stock::find()->where(['prid' => $product_id])->and_where(['IN', 'normalize_id', array_map('strval', array_keys($inventory_array))])->as_array(true)->all() as $product_allocated_temporary_record) {
                    $key = (int) $product_allocated_temporary_record['warehouse_id'] . '_' . (int) $product_allocated_temporary_record['suppliers_id'] . '_' . (int) $product_allocated_temporary_record['location_id'] . '_' . (int) $product_allocated_temporary_record['layers_id'] . '_' . (int) $product_allocated_temporary_record['batch_id'];
                    $product_allocated_temporary_array[$key][] = $product_allocated_temporary_record;
                    unset($key);
                }
                unset($product_allocated_temporary_record);
            }
            unset($product_id);
            foreach ($product_allocated_array as $key => $order_product_allocated_array) {
                foreach ($order_product_allocated_array as $product_allocated_record) {
                    if (isset($warehouse_product_array[$key][$product_allocated_record['products_id']])) {
                        $warehouse_product_record = $warehouse_product_array[$key][$product_allocated_record['products_id']];
                    } else {
                        $warehouse_product_record = new \common\models\Warehouses_Products();
                        $warehouse_product_record->prid = $product_allocated_record['prid'];
                        $warehouse_product_record->products_id = $product_allocated_record['products_id'];
                        $warehouse_product_record->warehouse_id = $product_allocated_record['warehouse_id'];
                        $warehouse_product_record->suppliers_id = $product_allocated_record['suppliers_id'];
                        $warehouse_product_record->location_id = $product_allocated_record['location_id'];
                        $warehouse_product_record->layers_id = $product_allocated_record['layers_id'];
                        $warehouse_product_record->batch_id = $product_allocated_record['batch_id'];
                    }
                    $warehouse_product_record->allocated_stock_quantity += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
                    $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity - $warehouse_product_record->allocated_stock_quantity;
                    $warehouse_product_array[$key][$product_allocated_record['products_id']] = $warehouse_product_record;
                }
                unset($product_allocated_record);
            }
            unset($order_product_allocated_array);
            unset($product_allocated_array);
            unset($key);
            foreach ($product_allocated_temporary_array as $key => $order_product_allocated_temporary_array) {
                foreach ($order_product_allocated_temporary_array as $product_allocated_temporary_record) {
                    if (isset($warehouse_product_array[$key][$product_allocated_temporary_record['normalize_id']])) {
                        $warehouse_product_record = $warehouse_product_array[$key][$product_allocated_temporary_record['normalize_id']];
                    } else {
                        $warehouse_product_record = new \common\models\Warehouses_Products();
                        $warehouse_product_record->prid = $product_allocated_temporary_record['prid'];
                        $warehouse_product_record->products_id = $product_allocated_temporary_record['normalize_id'];
                        $warehouse_product_record->warehouse_id = $product_allocated_temporary_record['warehouse_id'];
                        $warehouse_product_record->suppliers_id = $product_allocated_temporary_record['suppliers_id'];
                        $warehouse_product_record->location_id = $product_allocated_temporary_record['location_id'];
                        $warehouse_product_record->layers_id = $product_allocated_temporary_record['layers_id'];
                        $warehouse_product_record->batch_id = $product_allocated_temporary_record['batch_id'];
                    }
                    $warehouse_product_record->temporary_stock_quantity += $product_allocated_temporary_record['temporary_stock_quantity'];
                    $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity - ($warehouse_product_record->allocated_stock_quantity + $warehouse_product_record->temporary_stock_quantity);
                    $warehouse_product_array[$key][$product_allocated_temporary_record['normalize_id']] = $warehouse_product_record;
                    unset($warehouse_product_record);
                }
                unset($product_allocated_temporary_record);
            }
            unset($order_product_allocated_temporary_array);
            unset($product_allocated_temporary_array);
            unset($key);
            $return = true;
            foreach ($warehouse_product_array as $warehouse_product_record_array) {
                if ($inventory_id > 0) {
                    $warehouse_product_collection_record = new \common\models\Warehouses_Products();
                }
                foreach ($warehouse_product_record_array as $warehouse_product_record) {
                    try {
                        $warehouse_product_record->save();
                        $warehouse_id_array = self::get_warehouse_id_priority_array($warehouse_product_record->products_id, 1, false);
                        $supplier_id_array = self::get_supplier_id_priority_array($warehouse_product_record->products_id);
                        if (!in_array((int) $warehouse_product_record->warehouse_id, $warehouse_id_array) or !in_array((int) $warehouse_product_record->suppliers_id, $supplier_id_array)) {
                            $warehouse_product_record->warehouse_stock_quantity = 0;
                            $warehouse_product_record->products_quantity = $warehouse_product_record->warehouse_stock_quantity - ($warehouse_product_record->allocated_stock_quantity + $warehouse_product_record->temporary_stock_quantity);
                        }
                        unset($warehouse_id_array);
                        unset($supplier_id_array);
                        if (isset($warehouse_product_collection_record)) {
                            $warehouse_product_collection_record->prid = $warehouse_product_record->prid;
                            $warehouse_product_collection_record->products_id = $warehouse_product_record->prid;
                            $warehouse_product_collection_record->warehouse_id = $warehouse_product_record->warehouse_id;
                            $warehouse_product_collection_record->suppliers_id = $warehouse_product_record->suppliers_id;
                            $warehouse_product_collection_record->location_id = $warehouse_product_record->location_id;
                            $warehouse_product_collection_record->layers_id = $warehouse_product_record->layers_id;
                            $warehouse_product_collection_record->batch_id = $warehouse_product_record->batch_id;
                            $warehouse_product_collection_record->warehouse_stock_quantity += $warehouse_product_record->warehouse_stock_quantity;
                            $warehouse_product_collection_record->allocated_stock_quantity += $warehouse_product_record->allocated_stock_quantity;
                            $warehouse_product_collection_record->temporary_stock_quantity += $warehouse_product_record->temporary_stock_quantity;
                            $warehouse_product_collection_record->products_quantity += $warehouse_product_record->products_quantity;
                        }
                        if ($inventory_id > 0 and isset($inventory_array[$warehouse_product_record->products_id])) {
                            $inventory_array[$warehouse_product_record->products_id]->warehouse_stock_quantity += $warehouse_product_record->warehouse_stock_quantity;
                            $inventory_array[$warehouse_product_record->products_id]->allocated_stock_quantity += $warehouse_product_record->allocated_stock_quantity;
                            $inventory_array[$warehouse_product_record->products_id]->temporary_stock_quantity += $warehouse_product_record->temporary_stock_quantity;
                            $inventory_array[$warehouse_product_record->products_id]->products_quantity += $warehouse_product_record->products_quantity;
                        }
                        $product_record->warehouse_stock_quantity += $warehouse_product_record->warehouse_stock_quantity;
                        $product_record->allocated_stock_quantity += $warehouse_product_record->allocated_stock_quantity;
                        $product_record->temporary_stock_quantity += $warehouse_product_record->temporary_stock_quantity;
                        $product_record->products_quantity += $warehouse_product_record->products_quantity;
                    } catch (\Exception $exc) {
                        $return = false;
                    }
                }
                if (isset($warehouse_product_collection_record)) {
                    try {
                        $warehouse_product_collection_record->save();
                    } catch (\Exception $exc) {
                        $return = false;
                    }
                }
                unset($warehouse_product_collection_record);
                unset($warehouse_product_record);
            }
            unset($warehouse_product_record_array);
            unset($warehouse_product_array);
            if ($inventory_id > 0) {
                foreach ($inventory_array as $inventory_record) {
                    try {
                        $inventory_record->suppliers_stock_quantity = self::get_quantity_supplier($inventory_record->products_id);
                        $inventory_record->save();
                    } catch (\Exception $exc) {
                        $return = false;
                    }
                }
                unset($inventory_record);
            }
            unset($inventory_array);
            unset($inventory_id);
            try {
                $product_record->suppliers_stock_quantity = self::get_quantity_supplier($product_record->products_id);
                // {{ switch off EOL
                if ($product_record->stock_indication_id && $product_record->products_quantity <= 0 && in_array((int) $product_record->stock_indication_id, \common\classes\Stock_Indication::product_disable_by_stock_ids())) {
                    if ($product_record->products_quantity + $product_record->temporary_stock_quantity <= 0) {
                        $product_record->products_status = 0;
                    }
                }
                // }} switch off EOL
                // {{ reset to default
                if ($product_record->stock_indication_id && $product_record->products_quantity <= 0 && in_array((int) $product_record->stock_indication_id, \common\classes\Stock_Indication::product_reset_to_default_stock_ids())) {
                    if ($product_record->products_quantity + $product_record->temporary_stock_quantity <= 0) {
                        $product_record->stock_indication_id = 0;
                        $product_record->stock_delivery_terms_id = 0;
                    }
                }
                // }} reset to default
                $product_record->save(false);
            } catch (\Exception $exc) {
                $return = false;
            }
            \common\components\Categories_Cache::get_cpc()::invalidate_products((int) $product_record->products_id);
        }
        unset($product_record);
        return $return;
    }
    private static function do_cache_parent(\common\models\Products $product_record)
    {
        $return = false;
        $product_child_array = self::get_child_array($product_record);
        if (count($product_child_array) > 0) {
            \common\models\Warehouses_Products::delete_all(['prid' => (int) $product_record->products_id]);
            $bp_warehouse_stock = -1;
            $bp_allocated_stock = -1;
            $bp_temporary_stock = -1;
            $bp_available_stock = -1;
            foreach ($product_child_array as $product_child_id => $product_child_data) {
                $product_child_quantity = (int) $product_child_data['num_product'];
                $bc_warehouse_stock = 0;
                $bc_allocated_stock = 0;
                $bc_temporary_stock = 0;
                if (count(self::get_child_array($product_child_id)) > 0) {
                    $pc_record = self::get_record($product_child_id);
                    $bc_warehouse_stock += (int) $pc_record->warehouse_stock_quantity;
                    $bc_allocated_stock += (int) $pc_record->allocated_stock_quantity;
                    $bc_temporary_stock += (int) $pc_record->temporary_stock_quantity;
                    unset($pc_record);
                } else {
                    foreach (\common\helpers\Warehouses::get_product_array($product_child_id) as $wp_record) {
                        $bc_warehouse_stock += (int) $wp_record['warehouse_stock_quantity'];
                        $bc_allocated_stock += (int) $wp_record['allocated_stock_quantity'];
                        $bc_temporary_stock += (int) $wp_record['temporary_stock_quantity'];
                    }
                    unset($wp_record);
                }
                $bc_warehouse_stock -= $bc_allocated_stock + $bc_temporary_stock;
                $bc_allocated_stock = 0;
                $bc_temporary_stock = 0;
                foreach (\common\models\Orders_Products::find()->where(['products_id' => $product_child_id])->and_where('`template_uprid` LIKE "' . $product_child_id . '%{sub}' . (int) $product_record->products_id . '"')->and_where(['IN', 'orders_products_status', [\common\helpers\Order_Product::OPS_QUOTED, \common\helpers\Order_Product::OPS_STOCK_DEFICIT, \common\helpers\Order_Product::OPS_STOCK_ORDERED, \common\helpers\Order_Product::OPS_RECEIVED]])->as_array(true)->all() as $op_record) {
                    $bc_allocated_stock += (int) $op_record['qty_rcvd'] - (int) $op_record['qty_dspd'];
                    $bc_warehouse_stock += (int) $op_record['qty_rcvd'] - (int) $op_record['qty_dspd'];
                }
                unset($op_record);
                foreach (\common\models\Orders_Products_Temporary_Stock::find()->where(['prid' => $product_child_id])->and_where(['parent_id' => (int) $product_record->products_id])->as_array(true)->all() as $opts_record) {
                    $bc_temporary_stock += (int) $opts_record['temporary_stock_quantity'];
                    $bc_warehouse_stock += (int) $opts_record['temporary_stock_quantity'];
                }
                unset($opts_record);
                $bc_warehouse_stock = (int) floor($bc_warehouse_stock / $product_child_quantity);
                $bc_allocated_stock = (int) ceil($bc_allocated_stock / $product_child_quantity);
                $bc_temporary_stock = (int) ceil($bc_temporary_stock / $product_child_quantity);
                unset($product_child_quantity);
                $bc_available_stock = $bc_warehouse_stock - ($bc_allocated_stock + $bc_temporary_stock);
                if ($bp_available_stock < 0 or $bp_available_stock > $bc_available_stock) {
                    $bp_warehouse_stock = $bc_warehouse_stock;
                    $bp_allocated_stock = $bc_allocated_stock;
                    $bp_temporary_stock = $bc_temporary_stock;
                    $bp_available_stock = $bc_available_stock;
                }
                unset($bc_warehouse_stock);
                unset($bc_allocated_stock);
                unset($bc_temporary_stock);
                unset($bc_available_stock);
            }
            unset($bp_available_stock);
            unset($product_child_data);
            unset($product_child_id);
            $bp_warehouse_stock = $bp_warehouse_stock < 0 ? 0 : $bp_warehouse_stock;
            $bp_allocated_stock = $bp_allocated_stock < 0 ? 0 : $bp_allocated_stock;
            $bp_temporary_stock = $bp_temporary_stock < 0 ? 0 : $bp_temporary_stock;
            $product_record->warehouse_stock_quantity = $bp_warehouse_stock;
            $product_record->allocated_stock_quantity = $bp_allocated_stock;
            $product_record->temporary_stock_quantity = $bp_temporary_stock;
            $product_record->products_quantity = $bp_warehouse_stock - ($bp_allocated_stock + $bp_temporary_stock);
            try {
                $product_record->save();
                $return = true;
            } catch (\Exception $exc) {
            }
            unset($bp_warehouse_stock);
            unset($bp_allocated_stock);
            unset($bp_temporary_stock);
        }
        unset($product_child_array);
        unset($product_record);
        return $return;
    }
    /**
     * Get Product record
     * @param mixed $uProductId Product Id or Product Inventory Id or instance of Products model
     * @param boolean $doCache defines should Product stock quantities be calculated and cached. Cleaning up invalid DB records
     * @return mixed instance of Products model or null
     */
    public static function get_record($u_product_id = 0, $do_cache = false, $ignore_freeze = false)
    {
        if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed() && !$ignore_freeze) {
            if (!$ext::is_freeze_product_record($u_product_id)) {
                if ($u_product_id instanceof \common\models\Products) {
                    $u_product_id = $u_product_id->products_id;
                }
                $u_product_id = \common\extensions\Report_Freeze_Stock\models\Freeze_Products::find()->and_where(['products_id' => (int) $u_product_id])->cache(!$do_cache && defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? self::PRODUCT_RECORD_CACHE : -1)->one();
            }
            if ((int) $do_cache > 0 and $ext::is_freeze_product_record($u_product_id)) {
                if (self::do_cache($u_product_id) != true) {
                    $u_product_id = null;
                }
            }
        } else {
            if (!$u_product_id instanceof \common\models\Products) {
                $u_product_id = \common\models\Products::find()->and_where(['products_id' => (int) $u_product_id])->cache(!$do_cache && defined('ALLOW_ANY_QUERY_CACHE') && ALLOW_ANY_QUERY_CACHE == 'True' ? self::PRODUCT_RECORD_CACHE : -1)->one();
            }
            if ((int) $do_cache > 0 and $u_product_id instanceof \common\models\Products) {
                if (self::do_cache($u_product_id) != true) {
                    $u_product_id = null;
                }
            }
        }
        unset($do_cache);
        return $u_product_id;
    }
    public static function get_virtual_item_quantity_value($u_product_id = 0)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderQuantityStep', 'allowed')) {
            return $ext::get_virtual_item_quantity_value($u_product_id);
        }
        return 1;
    }
    public static function get_virtual_item_quantity($u_product_id = 0, $real_quantity = 0)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderQuantityStep', 'allowed')) {
            $real_quantity = $ext::get_virtual_item_quantity($u_product_id, $real_quantity);
        }
        return $real_quantity;
    }
    public static function get_virtual_item_step($u_product_id = 0, $check_array = false)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderQuantityStep', 'allowed')) {
            return $ext::get_virtual_item_step($u_product_id, $check_array);
        }
        return [1];
    }
    /**
     * check if product has any asset
     * @param int $products_id
     * @return boolean
     */
    public static function has_assets($products_id)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            return $ext::has_assets($products_id);
        }
        return false;
    }
    /**
     *
     * @param string $uprid
     * @param array $params
     * @return product asset or null
     */
    public static function get_assets($uprid, array $params = [])
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            return $ext::get_assets($uprid, $params);
        }
        return null;
    }
    public static function get_asset($asset_id)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            return $ext::get_asset($asset_id);
        }
        return null;
    }
    public static function get_settings($products_id)
    {
        $settings = \common\models\Products_Settings::find()->where(['products_id' => intval($products_id)])->one();
        if (!$settings) {
            $settings = new \common\models\Products_Settings();
            $settings->load_default_values();
        }
        return $settings;
    }
    /**
     * Url of parent category if product exists or false
     * @param integer $products_id
     * @return string|false
     */
    public static function get302redirect($products_id)
    {
        // parent category if product exists
        $new_url = false;
        $product = \common\models\Products::find_one($products_id);
        if ($product->products_id) {
            $leaf = false;
            $check = $product->get_categories()->active()->limit(1)->one();
            if (!$check) {
                $check = $product->get_categories()->limit(1)->one();
            } elseif ($check->categories_status) {
                // useless if? active() above
                $leaf = $check->categories_id;
            }
            if ($check) {
                $check = $check->get_visible_parents()->as_array()->all();
            }
            //$check = $check ->category[0]->getVisibleParents()->asArray()->one();
            if (!empty($check)) {
                $path = \yii\helpers\Array_Helper::map($check, 'categories_id', 'categories_id');
                if ($leaf) {
                    $path[$leaf] = $leaf;
                }
                $new_url = Yii::$app->url_manager->create_url(['catalog', 'cPath' => implode('_', $path)]);
            } elseif ($leaf) {
                $new_url = Yii::$app->url_manager->create_url(['catalog', 'cPath' => $leaf]);
            } else {
                $new_url = Yii::$app->url_manager->create_url('index');
            }
        }
        return $new_url;
    }
    /**
     * check whether the product is visible and redirects to to parent category if product is not visible
     * @param integer $productsId
     */
    public static function redirect_if_inactive($products_id)
    {
        $new_url = false;
        $check_status = 1;
        if (\frontend\design\Info::is_admin()) {
            $check_status = 0;
        }
        if (!self::check_product($products_id, $check_status, true)) {
            $new_url = self::get302redirect($products_id);
        }
        if ($new_url && !empty($new_url)) {
            header('HTTP/1.1 302 Found');
            header('Location: ' . $new_url);
            exit;
        }
    }
    /**
     *
     * @param string $string to cleanup
     * @param int $checkLength <1 | 1 | >1  do not apply | by conf | by DB full-text settings
     * @return type
     */
    public static function cleanup_search($string, $check_length = 2)
    {
        $string = str_replace('<', ' <', $string);
        $string = preg_replace('/\s+/', ' ', strip_tags($string));
        if ($check_length) {
            $min_len = 0;
            if (defined('MSEARCH_WORD_LENGTH') && (int) MSEARCH_WORD_LENGTH > 1) {
                $min_len = (int) MSEARCH_WORD_LENGTH;
            }
            if ($check_length == 2 && defined('MSEARCH_ENABLE') && MSEARCH_ENABLE == 'fulltext') {
                /* @var $ext \common\extensions\PlainProductsDescription\PlainProductsDescription */
                if ($ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed')) {
                    $min_len = max($min_len, $ext::get_min_token_length());
                }
            }
            if ($min_len > 1) {
                $words = explode(' ', $string);
                //$words = array_map(function ($word) { return preg_replace(['/^\W+/', '/\W+$/'], '', $word); },  $words);
                $words = array_map(function ($word) {
                    return trim($word, '., -_!?:\'"');
                }, $words);
                $words = array_filter($words, function ($__word) use ($min_len) {
                    return strlen($__word) >= $min_len;
                });
                $string = implode(' ', $words);
            }
        }
        return $string;
    }
    /**
     * mmm gets ... random first categories id of product
     * @staticvar array $_cache
     * @param int $productId
     * @return int
     */
    public static function get_categories($product_id)
    {
        static $_cache = [];
        $product_id = intval($product_id);
        $ret = [];
        if ($product_id > 0) {
            if (!isset($_cache[$product_id])) {
                $model = \common\models\Products::find()->and_where(['products_id' => $product_id])->with('listingCategories')->as_array()->one();
                if ($model) {
                    $ret = $_cache[$product_id] = $model['listingCategories'][0];
                }
            } else {
                $ret = $_cache[$product_id];
            }
        }
        return $ret;
    }
    /**
     * gets list of properties and values of the product
     * @staticvar array $_cache
     * @param int $productId
     * @return array
     */
    public static function get_properties_short($product_id)
    {
        static $_cache = [];
        $product_id = intval($product_id);
        $ret = [];
        if ($product_id > 0) {
            if (!isset($_cache[$product_id])) {
                $model = \common\models\Products::find()->and_where(['products_id' => $product_id])->with('properties')->as_array()->one();
                if ($model) {
                    $ret = $_cache[$product_id] = $model['properties'];
                }
            } else {
                $ret = $_cache[$product_id];
            }
        }
        return $ret;
    }
    public static function remove_order_sub_products(array $products)
    {
        return array_filter($products, static function (array $item) {
            if (empty($item['parent_product'])) {
                return true;
            }
            return false;
        });
    }
    public static function reduce_order_products(array $products)
    {
        $parent_products = [];
        $products = array_filter($products, static function (array $item) use (&$parent_products) {
            if (empty($item['parent_product'])) {
                $id = $item['template_uprid'] ?: $item['id'];
                $parent_products[$id] = $item;
                return false;
            }
            return true;
        });
        array_walk($products, static function ($item) use (&$parent_products) {
            if (!empty($item['parent_product']) && isset($parent_products[$item['parent_product']])) {
                $id = $item['template_uprid'] ?: $item['id'];
                $parent_products[$item['parent_product']]['subProducts'][$id] = $item;
            }
        });
        return $parent_products;
    }
    public static function get_categories_id_list_with_parents($product_id)
    {
        $ret = [];
        if ((int) $product_id > 0) {
            $ret = \common\models\Products2Categories::find()->alias('p2c')->and_where(['products_id' => $product_id])->inner_join(TABLE_CATEGORIES . ' c1', 'c1.categories_id=p2c.categories_id')->inner_join(TABLE_CATEGORIES . ' c2', 'c1.categories_left >= c2.categories_left and c1.categories_right <= c2.categories_right')->select('c2.categories_id')->as_array()->distinct()->column();
            if (!$ret) {
                $ret = [];
            }
        }
        return $ret;
    }
    /**
     *
     * @param array|int $productsIds
     * @param array $details
     * @param int|false $limit
     * @return string
     */
    public static function get_admin_details_list($products_ids, $details = ['name', 'price', 'status', 'model'], $limit = false)
    {
        if (!is_array($products_ids)) {
            if (is_numeric($products_ids)) {
                $products_ids = [$products_ids];
            } else {
                $products_ids = array_map('intval', preg_split('/,/', $products_ids, -1, PREG_SPLIT_NO_EMPTY));
            }
        }
        $p_q = \common\models\Products::find()->alias('p')->join_with('backendDescription')->add_select('p.products_id, p.products_model, p.products_price, p.products_status')->and_where(['p.products_id' => array_map('intval', $products_ids)]);
        if (false && \backend\models\Product_Name_Decorator::instance()->use_internal_name_for_listing()) {
            //$pQ->addSelect(['products_name' => new \yii\db\Expression("IF(LENGTH(products_internal_name), products_internal_name, products_name)")]);
            $order_by = new \yii\db\Expression('IF(LENGTH(products_internal_name), products_internal_name, products_name)');
        } else {
            $p_q->add_select('products_name');
            $order_by = 'products_name';
        }
        $p_q->order_by($order_by);
        if ($limit && (int) $limit > 0) {
            $p_q->limit((int) $limit);
        }
        $data = $p_q->as_array()->all();
        $ret = '';
        if (!empty($data)) {
            /** @var \common\classes\Currencies $currencies */
            $currencies = Yii::$container->get('currencies');
            foreach ($data as $d) {
                $ret .= '<div class="row col-md-12 prod-row ' . (!$d['products_status'] ? 'dis_module' : '') . '">';
                if (in_array('name', $details)) {
                    $ret .= '<span class="col-md-8 prod-name">' . $d['products_name'] . '</span>';
                }
                if (in_array('model', $details)) {
                    $ret .= '<span class="col-md-2 prod-model">' . $d['products_model'] . '</span>';
                }
                if (in_array('price', $details)) {
                    $ret .= '<span class="col-md-2 prod-price">' . $currencies->format($d['products_price']) . '</span>';
                }
                $ret .= '</div>';
            }
            //$ret = '<div class="row col-md-12 container">' . $ret . '</div>';
        }
        return $ret;
    }
    /**
     * from product price widget - to check
     * 2do fill in all details to 'clear' array
     * @param array $product product details from storage
     * @param int $qty def 1
     * @param int|false $customer_groups_id from storage if false
     * @return array
     */
    public static function get_pice_details($product, $qty = 1, $customer_groups_id = false)
    {
        if (!$customer_groups_id) {
            $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
        }
        $ret = $clear = [];
        $special_ex = $old_ex = $current_ex = '';
        /** @var \common\classes\Currencies $currencies */
        $currencies = \Yii::$container->get('currencies');
        $special_clear = $special_ex_clear = $special_one = $old_one = $special_ex_one = $old_ex_one = $current_ex_one = 0;
        $special_promo_str = $special_promo_value = $special_promo_ex_value = $special_promo_ex_str = $special_promo_one_value = $special_promo_one_str = $special_promo_ex_one_value = $special_promo_ex_one_str = 0;
        if ($product['is_bundle']) {
            $details = \common\helpers\Bundles::get_details(['products_id' => $product['products_id']]);
            if ($details['full_bundle_price_clear'] > $details['actual_bundle_price_clear']) {
                $special = $details['actual_bundle_price'];
                if (!empty($details['actual_bundle_price_ex'])) {
                    $special_ex = $details['actual_bundle_price_ex'];
                }
                $old = $details['full_bundle_price'];
                if (!empty($details['full_bundle_price_ex'])) {
                    $old_ex = $details['full_bundle_price_ex'];
                }
                $current = '';
            } else {
                $special_value = 0;
                $special = '';
                $old = '';
                $current = $details['actual_bundle_price'];
                if (!empty($details['actual_bundle_price_ex'])) {
                    $current_ex = $details['actual_bundle_price_ex'];
                }
            }
            $special_clear = $details['full_bundle_price_clear'] > $details['actual_bundle_price_clear'] ? $details['actual_bundle_price_clear'] : false;
            $old_clear = $details['full_bundle_price_clear'] > $details['actual_bundle_price_clear'] ? $details['full_bundle_price_clear'] : false;
            $special_ex_clear = $details['full_bundle_price_clear'] > $details['actual_bundle_price_clear'] ? $details['actual_bundle_price_clear_ex'] : false;
            $old_ex_clear = $details['full_bundle_price_clear'] > $details['actual_bundle_price_clear'] ? $details['full_bundle_price_clear_ex'] : false;
            if ($details['full_bundle_price_clear'] > $details['actual_bundle_price_clear']) {
                $product['special_promote_type'] = 0;
                if (defined('SALES_DEFAULT_PROMO_TYPE') && SALES_DEFAULT_PROMO_TYPE != 'None') {
                    switch (SALES_DEFAULT_PROMO_TYPE) {
                        case 'Percent':
                            $product['special_promote_type'] = 1;
                            break;
                        case 'Fixed':
                            $product['special_promote_type'] = 2;
                            break;
                    }
                }
            }
            $clear = ['special' => $special_clear, 'old' => $old_clear, 'current' => $details['actual_bundle_price_clear'], 'special_ex' => $special_ex_clear, 'old_ex' => $old_ex_clear, 'current_ex' => $details['actual_bundle_price_clear_ex'], 'discount' => $details['full_bundle_price_clear'] > $details['actual_bundle_price_clear'] ? $details['full_bundle_price_clear'] - $details['actual_bundle_price_clear'] : false, 'percent' => $details['full_bundle_price_clear'] > $details['actual_bundle_price_clear'] && $details['full_bundle_price_clear'] ? round(($details['full_bundle_price_clear'] - $details['actual_bundle_price_clear']) / $details['full_bundle_price_clear'] * 100) . '%' : false, 'special_total_qty' => $product['special_total_qty'] ?? 0, 'special_max_per_order' => $product['special_max_per_order'] ?? 0];
            $json_price = $details['actual_bundle_price_clear'];
        } else {
            $price_instance = \common\models\Product\Price::get_instance($product['products_id']);
            $product['products_price'] = $price_instance->get_inventory_price(['qty' => $qty]);
            $product['special_price'] = $price_instance->get_inventory_special_price(['qty' => $qty]);
            // for 1 and q-ty could be different prices. so 1 first
            if (isset($product['special_price']) && $product['special_price'] !== false) {
                $special_one_clear = $currencies->display_price_clear($product['special_price'], $product['tax_rate'], 1);
                $special_one = $currencies->format($special_one_clear, false, '', '', true, true);
                $old_one_clear = $currencies->display_price_clear($product['products_price'], $product['tax_rate'], 1);
                $old_one = $currencies->format($old_one_clear, false);
                if (defined('DISPLAY_BOTH_PRICES') && DISPLAY_BOTH_PRICES == 'True') {
                    //&& (!\Yii::$app->storage->has('taxable') || (\Yii::$app->storage->has('taxable') && \Yii::$app->storage->get('taxable')))  - switcher from box and account ...
                    $special_ex_one_clear = $currencies->display_price_clear($product['special_price'], 0, 1);
                    $special_ex_one = $currencies->format($special_ex_one_clear, false);
                    $old_ex_one_clear = $currencies->display_price_clear($product['products_price'], 0, 1);
                    $old_ex_one = $currencies->format($old_ex_one_clear, false);
                }
            } else {
                $current_one = $currencies->display_price($product['products_price'], $product['tax_rate'], 1, true, true);
                if (defined('DISPLAY_BOTH_PRICES') && DISPLAY_BOTH_PRICES == 'True') {
                    $current_ex_one = $currencies->display_price($product['products_price'], 0, 1, false, false);
                }
                if (\common\helpers\Extensions::is_customer_groups_allowed() && \common\helpers\Customer::check_customer_groups($customer_groups_id, 'groups_price_as_special') && !isset($product['products_price_main'])) {
                    $_p = \common\models\Products::find()->select(['products_price_main' => 'products_price', 'products_id'])->where('products_id=:products_id', [':products_id' => (int) $product['products_id']])->as_array()->one();
                    if (!empty($_p)) {
                        \Yii::$container->get('products')->load_products($_p);
                        $product['products_price_main'] = $_p['products_price_main'];
                    }
                }
                if (\common\helpers\Customer::check_customer_groups($customer_groups_id, 'groups_price_as_special') && $product['products_price_main'] > $product['products_price']) {
                    $special_one = $current_one;
                    $old_one_clear = $currencies->display_price_clear($product['products_price_main'], $product['tax_rate'], 1);
                    $old_one = $currencies->format($old_one_clear, false);
                    $special_one_clear = $currencies->display_price_clear($product['products_price'], $product['tax_rate'], 1);
                    if (defined('DISPLAY_BOTH_PRICES') && DISPLAY_BOTH_PRICES == 'True') {
                        $old_ex_one_clear = $currencies->display_price_clear($product['products_price_main'], 0, 1, false, false);
                        $old_ex_one = $currencies->format($old_ex_one_clear, false);
                        $special_ex_one_clear = $currencies->display_price_clear($product['products_price'], 0, 1, false, false);
                    }
                    $current = '';
                }
            }
            /*
                        if ($qty != 1) {
                          $product['products_price'] = $priceInstance->getInventoryPrice(['qty' => $qty]);
                          $product['special_price'] = $priceInstance->getInventorySpecialPrice(['qty' => $qty]);
                        }
            */
            if (isset($product['special_price']) && $product['special_price'] !== false) {
                $special_value = $product['special_price'];
                $special_clear = $currencies->display_price_clear($product['special_price'], $product['tax_rate'], $qty);
                $special = $currencies->format($special_clear, false, '', '', true, true);
                $old_clear = $currencies->display_price_clear($product['products_price'], $product['tax_rate'], $qty);
                $old = $currencies->format($old_clear, false);
                if (defined('DISPLAY_BOTH_PRICES') && DISPLAY_BOTH_PRICES == 'True') {
                    //&& (!\Yii::$app->storage->has('taxable') || (\Yii::$app->storage->has('taxable') && \Yii::$app->storage->get('taxable')))  - switcher from box and account ...
                    $special_ex_clear = $currencies->display_price_clear($product['special_price'], 0, $qty);
                    $special_ex = $currencies->format($special_ex_clear, false);
                    $old_ex_clear = $currencies->display_price_clear($product['products_price'], 0, $qty);
                    $old_ex = $currencies->format($old_ex_clear, false);
                }
                $current = $current_ex = '';
                $json_price = $currencies->display_price_clear($product['special_price'], $product['tax_rate'], 1);
            } else {
                $special_value = 0;
                $special = '';
                $old = '';
                $current = $currencies->display_price($product['products_price'], $product['tax_rate'], $qty, true, true);
                $json_price = $currencies->display_price_clear($product['products_price'], $product['tax_rate'], 1);
                if (defined('DISPLAY_BOTH_PRICES') && DISPLAY_BOTH_PRICES == 'True') {
                    $current_ex = $currencies->display_price($product['products_price'], 0, $qty, false, false);
                }
                if (\common\helpers\Extensions::is_customer_groups_allowed() && \common\helpers\Customer::check_customer_groups($customer_groups_id, 'groups_price_as_special') && !isset($product['products_price_main'])) {
                    $_p = \common\models\Products::find()->select(['products_price_main' => 'products_price', 'products_id'])->where('products_id=:products_id', [':products_id' => (int) $product['products_id']])->as_array()->one();
                    if (!empty($_p)) {
                        \Yii::$container->get('products')->load_products($_p);
                        $product['products_price_main'] = $_p['products_price_main'];
                    }
                }
                if (\common\helpers\Customer::check_customer_groups($customer_groups_id, 'groups_price_as_special') && $product['products_price_main'] > $product['products_price']) {
                    $special_value = $product['products_price'];
                    $special = $current;
                    $old_clear = $currencies->display_price_clear($product['products_price_main'], $product['tax_rate'], $qty);
                    $old = $currencies->format($old_clear, false);
                    $special_clear = $currencies->display_price_clear($product['products_price'], $product['tax_rate'], $qty);
                    $special_one_clear = $currencies->display_price_clear($product['products_price'], $product['tax_rate'], 1);
                    if (defined('DISPLAY_BOTH_PRICES') && DISPLAY_BOTH_PRICES == 'True') {
                        $special_ex = $current_ex;
                        $old_ex_clear = $currencies->display_price($product['products_price_main'], 0, $qty);
                        $old_ex = $currencies->format($old_ex_clear, false);
                        $current_ex = '';
                        $special_ex_clear = $currencies->display_price_clear($product['products_price'], 0, $qty);
                    }
                    $current = '';
                }
            }
            $clear = [
                'special' => $special_clear ? $special_clear : false,
                //'old' => ((isset($product['special_price']) && $product['special_price'] !== false)?$old_clear:false),
                'old' => !empty($old_clear) ? $old_clear : false,
                'current' => $currencies->display_price_clear(isset($product['special_price']) && $product['special_price'] !== false ? $product['special_price'] : $product['products_price'], $product['tax_rate']),
                'special_ex' => $special_ex_clear ? $special_ex_clear : false,
                'old_ex' => isset($product['special_price']) && $product['special_price'] !== false ? $product['products_price'] : false,
                'current_ex' => $special_ex_clear ? $special_ex_clear : $product['products_price'],
                'special_total_qty' => $product['special_total_qty'] ?? 0,
                'special_max_per_order' => $product['special_max_per_order'] ?? 0,
            ];
            $clear['discount'] = isset($product['special_price']) && $product['special_price'] !== false ? $clear['old'] - $clear['special'] : false;
            if (abs($clear['discount']) < 0.01) {
                $clear['discount'] = false;
            } else {
                $clear['percent'] = isset($product['special_price']) && $product['special_price'] !== false && $clear['old'] > 0 ? round(($clear['old'] - $clear['special']) / $clear['old'] * 100) : false;
                if (abs($clear['percent']) < 1) {
                    $clear['percent'] = false;
                } else {
                    $clear['percent'] .= '%';
                }
            }
        }
        if (!empty($product['special_promote_type']) && !empty($clear['discount'])) {
            if ($product['special_promote_type'] == 1) {
                //percent
                if ($old_clear > 0) {
                    $special_promo_value = round(($old_clear - $special_clear) / $old_clear * 100);
                } else {
                    $special_promo_value = 100;
                }
                $special_promo_str = $special_promo_value . '%';
                if ($old_ex_clear > 0) {
                    $special_promo_ex_value = round(($old_ex_clear - $special_ex_clear) / $old_ex_clear * 100);
                } else {
                    $special_promo_ex_value = 100;
                }
                $special_promo_ex_str = $special_promo_ex_value . '%';
                if (isset($old_one_clear)) {
                    if ($old_one_clear > 0) {
                        $special_promo_one_value = round(($old_one_clear - $special_one_clear) / $old_one_clear * 100);
                    } else {
                        $special_promo_one_value = 100;
                    }
                    $special_promo_one_str = $special_promo_one_value . '%';
                }
                if (isset($old_ex_one_clear)) {
                    if ($old_ex_one_clear > 0) {
                        $special_promo_ex_one_value = round(($old_ex_one_clear - $special_ex_one_clear) / $old_ex_one_clear * 100);
                    } else {
                        $special_promo_ex_one_value = 100;
                    }
                    $special_promo_ex_one_str = $special_promo_ex_one_value . '%';
                }
            } elseif ($product['special_promote_type'] == 2) {
                //fixed
                $special_promo_value = $currencies->format_clear($old_clear - $special_clear, false);
                $special_promo_str = $currencies->format($old_clear - $special_clear, false);
                $special_promo_ex_value = $currencies->format_clear($old_ex_clear - $special_ex_clear, false);
                $special_promo_ex_str = $currencies->format($old_ex_clear - $special_ex_clear, false);
                if (isset($special_one_clear)) {
                    $special_promo_one_value = $currencies->format_clear($old_one_clear - $special_one_clear, false);
                    $special_promo_one_str = $currencies->format($old_one_clear - $special_one_clear, false);
                }
                if (isset($special_ex_one_clear)) {
                    $special_promo_ex_one_value = $currencies->format_clear($old_ex_one_clear - $special_ex_one_clear, false);
                    $special_promo_ex_one_str = $currencies->format($old_ex_one_clear - $special_ex_one_clear, false);
                }
            }
        }
        $taxable = DISPLAY_PRICE_WITH_TAX == 'true' && $product['tax_rate'] > 0;
        /*if (\Yii::$app->storage->has('taxable')){
            $taxable = $taxable && \Yii::$app->storage->get('taxable');
          }*/
        $taxable = $taxable && \common\helpers\Tax::display_taxable();
        $ret = ['formatted' => ['special' => $special ?? null, 'old' => $old ?? null, 'current' => $current ?? null, 'special_ex' => $special_ex ?? null, 'old_ex' => $old_ex ?? null, 'current_ex' => $current_ex ?? null, 'special_one' => $special_one ?? null, 'old_one' => $old_one ?? null, 'current_one' => $current_one ?? null, 'special_ex_one' => $special_ex_one ?? null, 'old_ex_one' => $old_ex_one ?? null, 'current_ex_one' => $current_ex_one ?? null, 'tax_rate' => $taxable ?? null, 'special_promo_str' => $special_promo_str ?? null, 'special_promo_value' => $special_promo_value ?? null, 'special_promo_ex_value' => $special_promo_ex_value ?? null, 'special_promo_ex_str' => $special_promo_ex_str ?? null, 'special_promo_one_value' => $special_promo_one_value ?? null, 'special_promo_one_str' => $special_promo_one_str ?? null, 'special_promo_ex_one_value' => $special_promo_ex_one_value ?? null, 'special_promo_ex_one_str' => $special_promo_ex_one_str ?? null, 'special_promote_type' => isset($product['special_promote_type']) ? $product['special_promote_type'] : '', 'special_total_qty' => (isset($product['special_total_qty']) ? $product['special_total_qty'] : 0) ?? 0, 'special_max_per_order' => (isset($product['special_max_per_order']) ? $product['special_max_per_order'] : 0) ?? 0], 'clear' => $clear ?? null, 'jsonPrice' => $json_price ?? null, 'special_value' => $special_value ?? null];
        return $ret;
    }
    /**
     * could be added to cart...... inc. pre-order etc
     * @param string $uProductId
     * @param int $platformId
     * @param int $warehouseId
     * @param int $supplierId
     * @return bool
     */
    public static function is_available_for_sale($u_product_id, $platform_id = false, $warehouse_id = false, $supplier_id = false)
    {
        $product_id = \common\helpers\Inventory::get_prid($u_product_id);
        $cart_button = isset(\common\models\Products::find_one($product_id)->cart_button) ? \common\models\Products::find_one($product_id)->cart_button : 1;
        if ($cart_button) {
            //$product_qty = self::get_products_stock($uProductId);
            $product_qty = self::get_available($u_product_id, $platform_id > 0 ? $platform_id : false, $warehouse_id > 0 ? $warehouse_id : false, $supplier_id > 0 ? $supplier_id : false);
            $stock_info = \common\classes\Stock_Indication::product_info(['products_id' => $u_product_id, 'products_quantity' => $product_qty]);
            return $stock_info['flags']['add_to_cart'];
        }
    }
    /**
     * could be bought and delivered
     * @param string $uProductId
     * @param int $platformId
     * @param int $warehouseId
     * @param int $supplierId
     * @return bool
     */
    public static function is_available_for_sale_now($u_product_id, $platform_id = false, $warehouse_id = false, $supplier_id = false)
    {
        $product_id = \common\helpers\Inventory::get_prid($u_product_id);
        $cart_button = isset(\common\models\Products::find_one($product_id)->cart_button) ? \common\models\Products::find_one($product_id)->cart_button : 1;
        if ($cart_button) {
            $product_qty = self::get_products_stock($u_product_id);
            //$product_qty = self::getAvailable($uProductId, ($platformId > 0 ? $platformId : false), ($warehouseId > 0 ? $warehouseId : false), ($supplierId > 0 ? $supplierId : false));
            $stock_info = \common\classes\Stock_Indication::product_info(['products_id' => $u_product_id, 'products_quantity' => $product_qty]);
            return $stock_info['flags']['add_to_cart'] && $product_qty > 0 && empty($stock_info['flags']['notify_instock']);
        }
    }
    /**
     * Return product unit label array if no values are passed or string if search mode is activated.
     * If $isSearch is passed - check is label key $productUnitLabelKey is existing. Return is depending on $productUnitLabelReturnValue.
     * If $productUnitLabelReturnValue = true - return label translated value, if = false - return label key.
     * If key doesn't exists - return empty string.
     * @param boolean $isSearch - is search mode is activated
     * @param string $productUnitLabelKey
     * @param boolean $productUnitLabelReturnValue
     * @return mixed product unit label array or product unit label value or key
     */
    public static function get_unit_label_list($is_search = false, $product_unit_label_key = '', $product_unit_label_return_value = true)
    {
        $return = [];
        $is_search = (int) $is_search > 0 ? true : false;
        $product_unit_label_key = trim($product_unit_label_key);
        $product_unit_label_return_value = (int) $product_unit_label_return_value > 0 ? true : false;
        \common\helpers\Translation::init('product_unit_label');
        foreach (\common\models\Translation::find()->where(['translation_entity' => 'product_unit_label'])->group_by(['translation_key'])->as_array(true)->all() as $label_record) {
            $return[$label_record['translation_key']] = defined($label_record['translation_key']) ? constant($label_record['translation_key']) : $label_record['translation_key'];
        }
        if ($is_search == true) {
            return ($product_unit_label_key != '' and isset($return[$product_unit_label_key])) ? $product_unit_label_return_value == true ? $return[$product_unit_label_key] : $product_unit_label_key : '';
        }
        asort($return, SORT_STRING);
        return $return;
    }
    public static function get_product_id_by_model(string $model)
    {
        $product_id = null;
        if (\common\helpers\Extensions::is_inventory_allowed()) {
            $product_id = \common\models\Inventory::find_one(['products_model' => $model])->products_id ?? null;
        }
        if (is_null($product_id)) {
            $product_id = \common\models\Products::find_one(['products_model' => $model])->products_id ?? null;
        }
        return $product_id;
    }
    public static function get_product_types($product_array)
    {
        $res = [];
        if (($product_array['is_bundle'] ?? null) && \common\helpers\Extensions::is_allowed('ProductBundles')) {
            $res = ['bundle'];
        } elseif (($product_array['products_pctemplates_id'] ?? null) && \common\helpers\Extensions::is_allowed('ProductConfigurator')) {
            $res = ['configurator'];
        } elseif ($product_array['attr_exists'] ?? null) {
            $res = ['attributes'];
            if (!($product_array['without_inventory'] ?? null) && \common\helpers\Extensions::is_allowed('Inventory')) {
                $res[] = 'inventory';
            }
        }
        return $res;
    }
    public static function get_product_price_and_tax($uprid, $group_id = null, $qty = 1)
    {
        $prid = \common\helpers\Inventory::get_prid($uprid);
        if (is_null($group_id)) {
            $group_id = \Yii::$app->storage->has('customer_groups_id') ? (int) \Yii::$app->storage->get('customer_groups_id') : 0;
        }
        $price_instance = \common\models\Product\Price::get_instance(\common\helpers\Inventory::normalize_id($uprid));
        $params = ['qty' => $qty];
        if (\common\helpers\Acl::check_extension_allowed('ProductBundles') && \common\helpers\Product::get_products_info($prid, 'is_bundle')) {
            $params['products_id'] = $uprid;
            $keep_customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
            $customer_groups_id = $group_id;
            \Yii::$app->storage->set('customer_groups_id', $customer_groups_id);
            \Yii::$app->params['reset_static_product_prices_cache'] = true;
            $details = \common\helpers\Bundles::get_details($params, [], true);
            $price = $details['actual_bundle_price_unit'];
            $customer_groups_id = $keep_customer_groups_id;
            \Yii::$app->storage->set('customer_groups_id', $customer_groups_id);
        } else {
            $params['group_id'] = $group_id;
            $price = $price_instance->get_inventory_special_price($params);
            if ($price === false) {
                $price = $price_instance->get_inventory_price($params);
            }
        }
        $tax_rate = \common\helpers\Tax::get_product_tax_rate($prid, null, $group_id);
        $currencies = \Yii::$container->get('currencies');
        $final_price = $currencies->calculate_price($price, $tax_rate, 1, '', true);
        return ['price_exc_tax' => $price, 'tax_rate' => $tax_rate, 'tax_value' => $final_price - $price, 'price_inc_tax' => $final_price];
    }
}