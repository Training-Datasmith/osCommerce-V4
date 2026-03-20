<?php

declare (strict_types=1);
namespace common\classes;

if (!defined('MAX_CART_QTY')) {
    define('MAX_CART_QTY', 99999);
}
class Stock_Indication
{
    /**
     * virtual: 0 both 1 only virtual 2 only physical
     * @staticvar array $fetched
     * @param bool $with_empty
     * @return array
     */
    public static $stock_code_priorities = ['out-stock' => -10, 'pre-order' => -20, 'in-stock' => -30];
    /**
     * used in Query()->cache(nn);
     *
     * <0 to switch off, 0 - never expire, true - delay from config
     */
    public const STOCK_INDICATION_CACHE_LIFETIME = 2;
    public static function get_variants($with_empty = true)
    {
        $lang_id = (int) \Yii::$app->settings->get('languages_id');
        $key = $lang_id . '^' . ($with_empty ? '1' : '0');
        static $fetched = [];
        if (!isset($fetched[$key])) {
            $fetched[$key] = [];
            $get_variants_r = tep_db_query('SELECT s.stock_indication_id AS id, st.stock_indication_text AS text, s.stock_code, ' . ' IF(LENGTH(st.stock_indication_short_text)>0,st.stock_indication_short_text,st.stock_indication_text) AS text_short, ' . ' s.* ' . 'FROM ' . TABLE_PRODUCTS_STOCK_INDICATION . ' s ' . ' LEFT JOIN ' . TABLE_PRODUCTS_STOCK_INDICATION_TEXT . " st ON st.stock_indication_id=s.stock_indication_id AND st.language_id='{$lang_id}' " . 'ORDER BY s.sort_order');
            $pa_ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed');
            if (tep_db_num_rows($get_variants_r) > 0) {
                if ($with_empty) {
                    $fetched[$key][] = ['id' => '', 'text' => STOCK_INDICATION_BY_QTY_IN_STOCK, 'params' => 'class="stock-indication-p"', 'display_virtual_options' => 2];
                }
                $bool_list = ['is_default', 'allow_out_of_stock_checkout', 'allow_out_of_stock_add_to_cart', 'allow_in_stock_notify', 'disable_product_on_oos', 'request_for_quote'];
                while ($_variant = tep_db_fetch_array($get_variants_r)) {
                    if ($pa_ext && $pa_ext::skip_indicator($_variant)) {
                        continue;
                    }
                    foreach ($bool_list as $bool_field) {
                        $_variant[$bool_field] = !!$_variant[$bool_field];
                    }
                    if ($_variant['display_virtual_options'] > 0) {
                        $_variant['params'] = 'class="stock-indication-' . ($_variant['display_virtual_options'] == 1 ? 'v' : 'p') . '"';
                    }
                    $fetched[$key][] = $_variant;
                }
            }
        }
        return $fetched[$key];
    }
    /**
     * remove 'virtual'/'physical' variants from array
     * @param bool $with_empty (0 - by q-ty in stock)
     * @param string $hide 'virtual' or 'physical'
     * @return array
     */
    public static function get_filtered_variants($with_empty = true, $hide = 'virtual')
    {
        $ret = self::get_variants($with_empty);
        if (is_array($ret)) {
            foreach ($ret as $key => $value) {
                if ($value['display_virtual_options'] > 0) {
                    if ($value['display_virtual_options'] == 1 && $hide == 'virtual') {
                        unset($ret[$key]);
                        continue;
                        //$value['params'] .= ' style="display:none"';
                    } elseif ($value['display_virtual_options'] == 2 && $hide == 'physical') {
                        unset($ret[$key]);
                        continue;
                        //$value['params'] .= ' style="display:none"';
                    }
                    // $ret[$key] = $value;
                }
            }
        }
        return array_values($ret);
    }
    public static function get_hidden_ids()
    {
        static $_cache = null;
        if (!is_array($_cache)) {
            $_cache = \common\models\Products_Stock_Indication::find()->where(['is_hidden' => 1])->select('stock_indication_id')->as_array()->column();
            $_cache = array_map('intval', $_cache);
        }
        return $_cache;
    }
    public static function sort_stock_flags(array $ids)
    {
        /*
           (
              [add_to_cart] => 1
              [ask_sample] => 0
              [can_add_to_cart] => 1
              [request_for_quote] => 0
              [display_price_options] => 0
              [quantity_max] => 36
              [stock_code] => in-stock
              [text_stock_code] => in-stock
              [stock_indicator_text] => In stock
          )
        */
        usort($ids, ['self', '_cmpFlags']);
        return $ids;
    }
    private static function _cmp_stock_codes($a, $b)
    {
        if (empty($a) || !isset(self::$stock_code_priorities[$a])) {
            $w_a = 0;
        } else {
            $w_a = self::$stock_code_priorities[$a];
        }
        if (empty($b) || !isset(self::$stock_code_priorities[$b])) {
            $w_b = 0;
        } else {
            $w_b = self::$stock_code_priorities[$b];
        }
        return $w_a - $w_b;
    }
    private static function _cmp_flags($a, $b)
    {
        // compare by stock_code text_stock_code can_add_to_cart add_to_cart request_for_quote
        $tmp = 0;
        foreach (['stock_code', 'text_stock_code', 'can_add_to_cart', 'add_to_cart', 'request_for_quote'] as $k) {
            $tmp = 0;
            if (in_array($k, ['stock_code', 'text_stock_code'])) {
                $tmp = self::_cmp_stock_codes(isset($a[$k]) ? $a[$k] : null, isset($b[$k]) ? $b[$k] : null);
            } else {
                $w_a = -1 * intval(isset($a[$k]) ? $a[$k] : 0);
                $w_b = -1 * intval(isset($b[$k]) ? $b[$k] : 0);
                $tmp = $w_a - $w_b;
            }
            if ($tmp != 0) {
                break;
            }
        }
        return $tmp;
    }
    public static function sort_stock_indicators(array $ids)
    {
        $_def_stock_id = 0;
        $stock_ids = [];
        foreach (self::get_variants(false) as $_stock_variant) {
            $stock_ids[] = (int) $_stock_variant['id'];
            if ($_stock_variant['is_default']) {
                $_def_stock_id = (int) $_stock_variant['id'];
            }
        }
        usort($ids, function ($a, $b) use ($stock_ids, $_def_stock_id) {
            if (empty($a)) {
                $a = $_def_stock_id;
            }
            if (empty($b)) {
                $b = $_def_stock_id;
            }
            $index_a = array_search((int) $a, $stock_ids);
            $index_b = array_search((int) $b, $stock_ids);
            if ($index_a === false || $index_b === false) {
                if ($index_a === $index_b) {
                    return 0;
                } elseif ($index_a === false) {
                    return -1;
                }
                return 1;
            } else {
                return $index_a - $index_b;
            }
        });
        return $ids;
    }
    public static function sort_delivery_terms(array $ids)
    {
        $_def_stock_id = 0;
        $stock_ids = [];
        foreach (self::get_delivery_terms(false) as $_stock_variant) {
            $stock_ids[] = (int) $_stock_variant['id'];
            if ($_stock_variant['is_default']) {
                $_def_stock_id = (int) $_stock_variant['id'];
            }
        }
        usort($ids, function ($a, $b) use ($stock_ids, $_def_stock_id) {
            if (empty($a)) {
                $a = $_def_stock_id;
            }
            if (empty($b)) {
                $b = $_def_stock_id;
            }
            $index_a = array_search((int) $a, $stock_ids);
            $index_b = array_search((int) $b, $stock_ids);
            if ($index_a === false || $index_b === false) {
                if ($index_a === $index_b) {
                    return 0;
                } elseif ($index_a === false) {
                    return -1;
                }
                return 1;
            } else {
                return $index_a - $index_b;
            }
        });
        return $ids;
    }
    /**
     * stock indication info according params
     * @param array $data_array specify products_id, products_quantity, is_virtual, stock_indication_id, stock_delivery_terms_id, cart_qty, cart_class
     * @return array
     */
    public static function product_info($data_array)
    {
        $back_order_qty = 0;
        // expected qty from purchase orders
        $cart_qty = isset($data_array['cart_qty']) ? (int) $data_array['cart_qty'] : 0;
        $on_cart_page = isset($data_array['cart_class']) && $data_array['cart_class'];
        $stock_indication_id = isset($data_array['stock_indication_id']) ? intval($data_array['stock_indication_id']) : 0;
        $stock_delivery_terms_id = isset($data_array['stock_delivery_terms_id']) ? intval($data_array['stock_delivery_terms_id']) : 0;
        $is_virtual = isset($data_array['is_virtual']) ? intval($data_array['is_virtual']) : null;
        $products_date_available = !empty($data_array['products_date_available']) ? $data_array['products_date_available'] : null;
        /**
         * @var \common\components\ProductItem $productItem
         */
        $product_item = \Yii::$container->get('products')->get_product((int) $data_array['products_id']);
        $products_status = empty($data_array['products_id']) ? null : $product_item['products_status'];
        if (strpos($data_array['products_id'], '{') !== false) {
            $uprid = \common\helpers\Inventory::normalize_inventory_id($data_array['products_id']);
        } else {
            $uprid = $data_array['products_id'];
        }
        if ($ext = \common\helpers\Extensions::is_allowed('LinkedProducts')) {
            $data_array = $ext::filter_stock_indication($data_array);
        }
        $action_on_out_of_stock = 1;
        if ($product_item->out_stock_action ?? null) {
            $action_on_out_of_stock = $product_item->out_stock_action;
        } elseif (defined('ACTION_ON_OUT_OF_STOCK') && ACTION_ON_OUT_OF_STOCK == 'Contact form') {
            $action_on_out_of_stock = 2;
        }
        if (empty($stock_indication_id) || empty($stock_delivery_terms_id)) {
            if (strpos($data_array['products_id'], '{') !== false) {
                //$get_from_inventory_r = tep_db_query("SELECT  FROM " . TABLE_INVENTORY . " WHERE prid='" . (int)$uprid . "' AND products_id='" . tep_db_input($uprid) . "'");
                $_from_inventory = \common\models\Inventory::find()->select('stock_indication_id, stock_delivery_terms_id, products_date_available')->and_where("prid='" . (int) $uprid . "' AND products_id='" . tep_db_input($uprid) . "'")->cache(self::STOCK_INDICATION_CACHE_LIFETIME)->as_array()->one();
                if (is_array($_from_inventory)) {
                    //$_from_inventory = tep_db_fetch_array($get_from_inventory_r);
                    $stock_indication_id = $_from_inventory['stock_indication_id'];
                    $stock_delivery_terms_id = $_from_inventory['stock_delivery_terms_id'];
                    $products_date_available = $_from_inventory['products_date_available'];
                }
            }
        }
        if ((empty($stock_indication_id) || empty($stock_delivery_terms_id) || is_null($is_virtual)) && !empty($data_array['products_id'])) {
            if (empty($stock_indication_id)) {
                $stock_indication_id = $product_item['stock_indication_id'];
            }
            if (empty($stock_delivery_terms_id)) {
                $stock_delivery_terms_id = $product_item['stock_delivery_terms_id'];
            }
            $is_virtual = $product_item['is_virtual'];
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductStockFlagsByPlatform', 'allowed')) {
            if ($platform_stock_flags = $ext::get_stock_flags($uprid)) {
                if (isset($platform_stock_flags['stock_indication_id'])) {
                    $stock_indication_id = $platform_stock_flags['stock_indication_id'];
                }
                if (isset($platform_stock_flags['stock_delivery_terms_id'])) {
                    $stock_delivery_terms_id = $platform_stock_flags['stock_delivery_terms_id'];
                }
            }
        }
        if (empty($products_date_available)) {
            if (strpos($data_array['products_id'], '{') !== false) {
                $_from_inventory = \common\models\Inventory::find()->select('stock_indication_id, stock_delivery_terms_id, products_date_available')->and_where("prid='" . (int) $uprid . "' AND products_id='" . tep_db_input($uprid) . "'")->cache(self::STOCK_INDICATION_CACHE_LIFETIME)->as_array()->one();
                if (is_array($_from_inventory)) {
                    $products_date_available = $_from_inventory['products_date_available'];
                }
            }
            if (empty($products_date_available) && defined('INVENTORY_DATE_AVAILABLE_BY_PRODUCT') && INVENTORY_DATE_AVAILABLE_BY_PRODUCT == 'True' && !empty($product_item['products_date_available'])) {
                $products_date_available = $product_item['products_date_available'];
            }
        }
        $data_array['stock_indication_id'] = $stock_indication_id;
        $data_array['stock_delivery_terms_id'] = $stock_delivery_terms_id;
        if ($products_date_available < date('Y-m-d 24')) {
            $data_array['products_date_available'] = $products_date_available = null;
        } else {
            $data_array['products_date_available'] = $products_date_available;
        }
        $invert = false;
        /**
         * ??? default state details by "show inactive" switcher at frontend..... O_O
         */
        /** @var \common\extensions\ShowInactive\ShowInactive $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('ShowInactive')) {
            if (!$products_status) {
                $invert = $ext::get_flag();
            }
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            if ($data_array['products_id']) {
                if ($ext::get_control_instance($data_array['products_id'], true)->need_stock_control()) {
                    $is_virtual = false;
                    $stock_indication_id = 0;
                    $data_array['stock_indication_id'] = 0;
                }
            }
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'isAllowed')) {
            if ($data_array['products_id'] && !($data_array['ignore_user_groups_restrictions'] ?? false)) {
                if (!$ext::is_stock_available(!empty($data_array['uprid_mask']) ? $data_array['uprid_mask'] : $data_array['products_id'])) {
                    // Don't overwrite stock_indication_id if Pre Order
                    $pre_stock_indication = \common\models\Products_Stock_Indication::find_one(['stock_code' => 'pre-order']);
                    if ($stock_indication_id != ($pre_stock_indication->stock_indication_id ?? false)) {
                        $stock_indication_id = 0;
                        $data_array['stock_indication_id'] = 0;
                    }
                    //$data_array['stock_delivery_terms_id'] = 0; // Don't overwrite stock_delivery_terms_id (e.g. Coming Soon)
                    $data_array['products_quantity'] = 0;
                }
            }
        }
        if ($is_virtual) {
            $add_to_cart = $add_to_cart = $product_item->cart_button;
            if (!$invert) {
                return ['stock_code' => 'in-stock', 'max_qty' => MAX_CART_QTY, 'products_quantity' => MAX_CART_QTY, 'stock_indicator_text' => TEXT_IN_STOCK, 'stock_indicator_text_short' => TEXT_IN_STOCK, 'allow_out_of_stock_checkout' => true, 'allow_out_of_stock_add_to_cart' => true, 'order_instock_bound' => false, 'flags' => ['out_stock_action' => $action_on_out_of_stock, 'notify_instock' => false, 'add_to_cart' => $add_to_cart, 'can_add_to_cart' => $add_to_cart, 'request_for_quote' => false, 'ask_sample' => false, 'display_price_options' => 0]];
            } else {
                return ['stock_code' => 'out-stock', 'max_qty' => 0, 'products_quantity' => 0, 'stock_indicator_text' => TEXT_IS_NOT_AVIABLE, 'stock_indicator_text_short' => TEXT_IS_NOT_AVIABLE, 'allow_out_of_stock_checkout' => false, 'allow_out_of_stock_add_to_cart' => false, 'order_instock_bound' => false, 'flags' => ['out_stock_action' => $action_on_out_of_stock, 'notify_instock' => false, 'add_to_cart' => !$add_to_cart, 'can_add_to_cart' => !$add_to_cart, 'request_for_quote' => false, 'ask_sample' => false, 'display_price_options' => 0]];
            }
        }
        if (!$invert) {
            $stock_info = ['stock_code' => 'out-stock', 'text_stock_code' => 'out-stock', 'max_qty' => MAX_CART_QTY, 'stock_indicator_text' => TEXT_OUT_STOCK, 'stock_indicator_text_short' => TEXT_OUT_STOCK, 'allow_out_of_stock_add_to_cart' => true, 'allow_out_of_stock_checkout' => defined('STOCK_CHECK') && STOCK_CHECK != 'true' || defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'true', 'order_instock_bound' => false, 'products_quantity' => $data_array['products_quantity'], 'flags' => ['out_stock_action' => $action_on_out_of_stock, 'notify_instock' => false, 'add_to_cart' => true, 'can_add_to_cart' => true, 'request_for_quote' => false, 'ask_sample' => false, 'display_price_options' => 0]];
        } else {
            return ['stock_code' => 'out-stock', 'text_stock_code' => 'out-stock', 'max_qty' => 0, 'stock_indicator_text' => TEXT_IS_NOT_AVIABLE, 'stock_indicator_text_short' => TEXT_IS_NOT_AVIABLE, 'allow_out_of_stock_add_to_cart' => false, 'allow_out_of_stock_checkout' => 0, 'order_instock_bound' => false, 'products_quantity' => 0, 'flags' => ['out_stock_action' => $action_on_out_of_stock, 'notify_instock' => false, 'add_to_cart' => false, 'can_add_to_cart' => false, 'request_for_quote' => false, 'ask_sample' => false, 'display_price_options' => 0]];
        }
        $stock_info_pre_lookup = false;
        foreach (self::get_variants(false) as $variant) {
            if ($variant['is_default']) {
                $stock_info_pre_lookup = $variant;
            }
            if ($data_array['stock_indication_id'] == $variant['id']) {
                $stock_info_pre_lookup = $variant;
                break;
            }
        }
        $stock_info_min_comming_soon = false;
        foreach (self::get_variants(false) as $variant) {
            if ($variant['stock_code'] == 'pre-order') {
                $stock_info_min_comming_soon = $variant;
                break;
            }
        }
        $delivery_terms_pre_lookup = false;
        foreach (self::get_delivery_terms(false) as $variant) {
            if ($variant['is_default']) {
                $delivery_terms_pre_lookup = $variant;
                $delivery_terms_pre_lookup['stock_code'] = $variant['stock_code'] ? $variant['stock_code'] : 'out-stock';
                $delivery_terms_pre_lookup['text_stock_code'] = $variant['text_stock_code'] ? $variant['text_stock_code'] : 'out-stock';
            }
            if ($data_array['stock_delivery_terms_id'] == $variant['id']) {
                $delivery_terms_pre_lookup = $variant;
                $delivery_terms_pre_lookup['stock_code'] = $variant['stock_code'] ? $variant['stock_code'] : 'out-stock';
                $delivery_terms_pre_lookup['text_stock_code'] = $variant['text_stock_code'] ? $variant['text_stock_code'] : 'out-stock';
                break;
            }
        }
        $delivery_terms_min_comming_soon = false;
        foreach (self::get_delivery_terms(false) as $variant) {
            if ($variant['stock_code'] == 'pre-order') {
                $delivery_terms_min_comming_soon = $variant;
                break;
            }
        }
        $o_product = $product_item;
        if ($o_product && !isset($o_product->order_quantity_max)) {
            $tmp = \common\helpers\Product::get_product_order_quantity($o_product->products_id);
            $o_product->order_quantity_max = $tmp['order_quantity_max'];
        }
        $order_quantity_max = !empty($o_product->order_quantity_max) && $o_product->order_quantity_max > 0 ? $o_product->order_quantity_max : MAX_CART_QTY;
        \common\helpers\Php8::null_obj_props($o_product, ['request_quote', 'request_quote_out_stock', 'ask_sample', 'allow_backorder', 'cart_button']);
        // product flags
        // RFQ only if product.request_quote eq 1 and
        // 1) stock >0
        // 2) stock <=0 and request_quote_out_stock eq 1
        $request_for_quote = 0;
        if ($o_product->request_quote == 1) {
            if ($data_array['products_quantity'] > 0) {
                $request_for_quote = 1;
            } elseif ($data_array['products_quantity'] <= 0 && $o_product->request_quote_out_stock == 1) {
                $request_for_quote = 1;
            }
        }
        $stock_info_pre_lookup['request_for_quote'] = $request_for_quote;
        /**
         * 'display_price_options'
         * 0 - display
         * 1 - hide
         * 2 - hide if zero
         */
        //add purchased q-ty details
        $back_order_info = $back_order_first = [];
        if (\common\helpers\Acl::check_extension_allowed('PurchaseOrders') && ($o_product->allow_backorder == 1 || $o_product->allow_backorder == 0 && strtolower(\common\helpers\Platform_Config::get_val('STOCK_ALLOW_BACKORDER_BY_DEFAULT')) == 'true')) {
            $back_order_info = \common\extensions\Purchase_Orders\helpers\Purchase_Order::get_ordered_product($uprid);
            if (count($back_order_info)) {
                $in_qty = min(0, \common\helpers\Product::get_available($uprid, 0));
                //suppose it could be negative
                /*reset($backOrderInfo);
                  $first_key = key($backOrderInfo);*/
                foreach ($back_order_info as $fk => $val) {
                    $in_qty += $val;
                    if ($in_qty > 0) {
                        break;
                    }
                }
                if ($in_qty > 0) {
                    $back_order_first = ['date' => $fk, 'qty' => $in_qty];
                }
            }
        }
        $_check_min_buy_qty = 0;
        if (!empty($o_product->order_quantity_minimal)) {
            $_check_min_buy_qty = max(0, intval($o_product->order_quantity_minimal) - 1);
        }
        ///allow_out_of_stock_add_to_cart - out_of_stock should mean nothing in flags :( - in backend they're "allow add to cart", "allow checkout"
        if ($stock_indication_id > 0 && $stock_info_pre_lookup['allow_out_of_stock_add_to_cart'] != 0 || $data_array['products_quantity'] > $_check_min_buy_qty && $stock_indication_id == 0) {
            ///indication allow add to cart with any stock level
            // or product is in stock and add to cart by stock level
            $add_to_cart = $o_product->cart_button;
        } elseif ($data_array['products_quantity'] <= $_check_min_buy_qty || $stock_info_pre_lookup['allow_out_of_stock_add_to_cart'] == 0) {
            // often default is out of stock so allow_out_of_stock_add_to_cart == 0
            if (\common\helpers\Acl::check_extension_allowed('PurchaseOrders') && ($o_product->allow_backorder == 1 || $o_product->allow_backorder == 0 && strtolower(\common\helpers\Platform_Config::get_val('STOCK_ALLOW_BACKORDER_BY_DEFAULT')) == 'true')) {
                //check purchased stock (in PO, but not received yet)
                $back_order_qty = array_sum($back_order_info);
                if ($back_order_qty > 0) {
                    $add_to_cart = $o_product->cart_button;
                    $stock_info['preorder_only'] = true;
                    $stock_info['max_qty_instant'] = min($order_quantity_max, (int) $data_array['products_quantity']);
                } else {
                    $add_to_cart = $stock_info_pre_lookup['allow_out_of_stock_add_to_cart'] && $o_product->cart_button;
                    //0;
                }
            } else {
                $add_to_cart = $stock_info_pre_lookup['allow_out_of_stock_add_to_cart'] && $o_product->cart_button;
                //0;
            }
        } else {
            $add_to_cart = $o_product->cart_button;
        }
        if ($add_to_cart > 0 && $stock_info_pre_lookup['display_price_options'] == 1) {
            $add_to_cart = 0;
        }
        $instock_condition = $cart_qty > 0 ? $data_array['products_quantity'] - $cart_qty >= 0 : $data_array['products_quantity'] > $_check_min_buy_qty;
        $instock_condition = $instock_condition ? $instock_condition : $back_order_qty > 0;
        //if ( ($instock_condition || ($on_cart_page && $data_array['products_quantity']>0)) && !$stock_info_pre_lookup['request_for_quote'] ) {
        if ($stock_indication_id == 0 && ($instock_condition || $on_cart_page && $data_array['products_quantity'] > 0)) {
            //$stock_info['stock_code'] = 'in-stock';
            $stock_info['max_qty'] = min($order_quantity_max, max((int) $data_array['products_quantity'], (int) $back_order_qty));
            $stock_info['backorderFirst'] = $back_order_first;
            $stock_info['backorderInfo'] = $back_order_info;
            //$stock_info['stock_indicator_text'] = TEXT_IN_STOCK;
            //$stock_info['stock_indicator_text_short'] = TEXT_IN_STOCK;
            $stock_info['allow_out_of_stock_checkout'] = true;
            if (!$instock_condition) {
                $stock_info['order_instock_bound'] = true;
            }
            // add new buttons behavior
            $stock_info['flags'] = ['add_to_cart' => $add_to_cart, 'ask_sample' => $o_product->ask_sample, 'can_add_to_cart' => $add_to_cart || $stock_info_pre_lookup['allow_out_of_stock_add_to_cart'], 'request_for_quote' => $stock_info_pre_lookup['request_for_quote'], 'display_price_options' => $stock_info_pre_lookup['display_price_options']];
        } else {
            $stock_info = $stock_info_pre_lookup;
            if ($stock_info_pre_lookup['stock_code'] == 'pre-order' && !$instock_condition) {
                $stock_info['preorder_only'] = true;
            }
            //            if ( $stock_indication_id==0 && $stock_info['is_default'] && $stock_info['allow_out_of_stock_checkout'] ) {
            //                $stock_info['allow_out_of_stock_checkout'] = (defined('STOCK_CHECK') && STOCK_CHECK != 'true') || (defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'true');
            //            }
            //$stock_info['stock_indicator_text'] = $stock_info['text'];
            //$stock_info['stock_indicator_text_short'] = $stock_info['text_short'];
            unset($stock_info['text']);
            unset($stock_info['text_short']);
            $stock_info['flags'] = ['out_stock_action' => $action_on_out_of_stock, 'notify_instock' => $stock_info['allow_in_stock_notify'], 'add_to_cart' => $add_to_cart, 'can_add_to_cart' => $stock_info['allow_out_of_stock_add_to_cart'], 'request_for_quote' => $stock_info['request_for_quote'], 'display_price_options' => $stock_info['display_price_options']];
            if (!$stock_info['allow_out_of_stock_add_to_cart']) {
                $stock_info['max_qty'] = 0;
            } else {
                if ($stock_info['limit_cart_qty_by_stock'] ?? null) {
                    $order_quantity_max = (int) $data_array['products_quantity'];
                }
                $stock_info['max_qty'] = min($order_quantity_max, MAX_CART_QTY);
            }
            if ($stock_info['stock_code'] == 'eol') {
                //if ($data_array['products_quantity']>0){
                if ($instock_condition) {
                    $stock_info['allow_out_of_stock_checkout'] = true;
                    $stock_info['flags']['can_add_to_cart'] = true;
                    $stock_info['max_qty'] = max(0, (int) $data_array['products_quantity']);
                } else {
                    // don't allow purchase eol products even if default stock indication is "in stock"
                    $stock_info['allow_out_of_stock_checkout'] = false;
                    $stock_info['flags']['can_add_to_cart'] = false;
                    $stock_info['flags']['add_to_cart'] = false;
                    $stock_info['preorder_only'] = false;
                    $stock_info['order_instock_bound'] = true;
                }
                $stock_info['eol'] = true;
            }
        }
        if ($cart_qty > 0 && !$stock_info['flags']) {
            $stock_info['max_qty'] = min($order_quantity_max, max((int) $data_array['products_quantity'], (int) $back_order_qty));
            $stock_info['backorderFirst'] = $back_order_first;
            $stock_info['backorderInfo'] = $back_order_info;
            if ($back_order_qty > 0) {
                $stock_info['max_qty_instant'] = min($order_quantity_max, (int) $data_array['products_quantity']);
            }
        }
        //if ( ($instock_condition || ($on_cart_page && $data_array['products_quantity']>0)) && !$stock_info_pre_lookup['request_for_quote'] ) {
        if ($stock_delivery_terms_id == 0 && ($instock_condition || $on_cart_page && $data_array['products_quantity'] > 0)) {
            if ($back_order_qty > 0) {
                // really not in stock but in purchase order
                $stock_info['id'] = $stock_info_min_comming_soon['id'];
                $stock_info['stock_delivery_terms_id'] = $delivery_terms_min_comming_soon['id'];
                $stock_info['text_stock_code'] = $delivery_terms_min_comming_soon['text_stock_code'];
                $stock_info['stock_code'] = $delivery_terms_min_comming_soon['stock_code'];
                $stock_info['stock_indicator_text'] = $delivery_terms_min_comming_soon['text'];
                $stock_info['stock_indicator_text_short'] = $delivery_terms_min_comming_soon['text_short'];
                if (!empty($products_date_available)) {
                    $stock_info['products_date_available'] = $products_date_available;
                }
            } else {
                $stock_info['stock_code'] = 'in-stock';
                $stock_info['text_stock_code'] = 'in-stock';
                $stock_info['stock_indicator_text'] = TEXT_IN_STOCK;
                $stock_info['stock_indicator_text_short'] = TEXT_IN_STOCK;
            }
        } else {
            if ($stock_delivery_terms_id == 0 && $stock_info['id'] > 0) {
                // default delivery term from indication first if term id is 0
                $tmp = \common\models\Products_Stock_Statuses_Cross_Link::find()->alias('l')->select('*')->and_where(['stock_indication_id' => $stock_info['id']])->and_where('l.is_default_term=1')->cache(10)->as_array()->one();
                if ($tmp) {
                    $new_terms = array_filter(self::get_delivery_terms(false), function ($el) use ($tmp) {
                        return $tmp['stock_delivery_terms_id'] == $el['id'];
                    });
                    if (!empty($new_terms)) {
                        $delivery_terms_pre_lookup = array_shift($new_terms);
                    }
                }
            }
            $stock_info['stock_delivery_terms_id'] = $delivery_terms_pre_lookup['id'];
            $stock_info['stock_indicator_text'] = $delivery_terms_pre_lookup['text'];
            $stock_info['stock_indicator_text_short'] = $delivery_terms_pre_lookup['text_short'];
            $stock_info['stock_code'] = $delivery_terms_pre_lookup['stock_code'];
            $stock_info['text_stock_code'] = $delivery_terms_pre_lookup['text_stock_code'];
            if ($data_array['products_quantity'] <= $_check_min_buy_qty && !empty($products_date_available)) {
                $stock_info['products_date_available'] = $products_date_available;
            }
        }
        //echo '<BR><PRE>';print_r($delivery_terms_pre_lookup);
        if ($invert) {
            if ($stock_info['stock_code'] == 'in-stock') {
            }
        }
        foreach (\common\helpers\Hooks::get_list('stock-indication/product-info') as $filename) {
            include $filename;
        }
        return $stock_info;
    }
    public static function product_reset_to_default_stock_ids()
    {
        $ids = [];
        foreach (self::get_variants(false) as $variant) {
            if (!$variant['reset_status_on_oos']) {
                continue;
            }
            $ids[(int) $variant['id']] = (int) $variant['id'];
        }
        return $ids;
    }
    public static function product_disable_by_stock_ids()
    {
        $ids = [];
        foreach (self::get_variants(false) as $variant) {
            if (!$variant['disable_product_on_oos']) {
                continue;
            }
            $ids[(int) $variant['id']] = (int) $variant['id'];
        }
        return $ids;
    }
    public static function get_delivery_terms($with_empty = true)
    {
        $lang_id = (int) \Yii::$app->settings->get('languages_id');
        $key = $lang_id . '^' . ($with_empty ? '1' : '0');
        static $fetched = [];
        if (!isset($fetched[$key])) {
            $fetched[$key] = [];
            $get_variants_r = tep_db_query('SELECT dt.stock_delivery_terms_id AS id, dtt.stock_delivery_terms_text AS text, ' . ' IF(LENGTH(dtt.stock_delivery_terms_short_text)>0,dtt.stock_delivery_terms_short_text,dtt.stock_delivery_terms_text) AS text_short, ' . ' dt.* ' . 'FROM ' . TABLE_PRODUCTS_STOCK_DELIVERY_TERMS . ' dt ' . ' LEFT JOIN ' . TABLE_PRODUCTS_STOCK_DELIVERY_TERMS_TEXT . " dtt ON dtt.stock_delivery_terms_id=dt.stock_delivery_terms_id AND dtt.language_id='{$lang_id}' " . 'ORDER BY dt.sort_order');
            if (tep_db_num_rows($get_variants_r) > 0) {
                if ($with_empty) {
                    $fetched[$key][] = ['id' => '', 'text' => TEXT_DEFAULT_VALUE];
                }
                $bool_list = ['is_default'];
                while ($_variant = tep_db_fetch_array($get_variants_r)) {
                    foreach ($bool_list as $bool_field) {
                        $_variant[$bool_field] = !!$_variant[$bool_field];
                    }
                    $fetched[$key][] = $_variant;
                }
            }
        }
        return $fetched[$key];
    }
    public static function term_to_indication_map()
    {
        $map = [];
        foreach (\common\models\Products_Stock_Statuses_Cross_Link::find()->alias('l')->join('left join', \common\models\Products_Stock_Delivery_Terms::table_name() . ' t', 't.stock_delivery_terms_id=l.stock_delivery_terms_id')->select(['l.stock_indication_id', 'l.stock_delivery_terms_id', 'l.is_default_term'])->order_by(['l.stock_indication_id' => SORT_ASC, 'l.is_default_term' => SORT_ASC])->as_array()->all() as $row) {
            if (!isset($map[$row['stock_indication_id']])) {
                $map[$row['stock_indication_id']] = [];
            }
            $map[$row['stock_indication_id']][$row['stock_delivery_terms_id']] = ['term_id' => $row['stock_delivery_terms_id'], 'default' => !!$row['is_default_term']];
        }
        return $map;
    }
}