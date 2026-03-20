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

class Order
{
    use Status_Trait;
    public const OES_PENDING = 1;
    public const OES_PROCESSING = 10;
    public const OES_RECEIVED = 20;
    public const OES_DISPATCHED = 30;
    public const OES_DELIVERED = 40;
    public const OES_CANCELLED = 50;
    public const OES_PARTIAL_CANCELLED = 60;
    public static function get_status_type_id()
    {
        return 1;
    }
    public static function is_exist($order_id)
    {
        $_status = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS check_exist FROM ' . TABLE_ORDERS . " WHERE orders_id = '" . (int) $order_id . "'"));
        return !!$_status['check_exist'];
    }
    public static function is_stock_updated($order_id)
    {
        $get_stock_status = tep_db_fetch_array(tep_db_query('SELECT stock_updated FROM ' . TABLE_ORDERS . " WHERE orders_id = '" . (int) $order_id . "'"));
        return !!($get_stock_status['stock_updated'] ?? null);
    }
    public static function restock($order_id)
    {
        if (!self::is_stock_updated($order_id)) {
            return;
        }
        $order_query = tep_db_query('select if(length(uprid), uprid, products_id) as uprid, template_uprid, products_id, products_quantity from ' . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int) $order_id . "'");
        while ($order = tep_db_fetch_array($order_query)) {
            global $login_id;
            tep_db_query('update ' . TABLE_PRODUCTS . ' set products_ordered = products_ordered - ' . $order['products_quantity'] . " where products_id = '" . (int) $order['products_id'] . "'");
            /*
                        \common\helpers\Product::log_stock_history_before_update($order['uprid'], $order['products_quantity'], '+',
                                                                                 ['comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $order_id]);
                        \common\helpers\Product::update_stock($order['uprid'], $order['products_quantity'], 0);
                        \common\helpers\Product::get_allocated_stock_quantity($order['uprid']);
            */
            \common\helpers\Warehouses::update_stock_of_order($order_id, strlen($order['template_uprid']) > 0 ? $order['template_uprid'] : $order['uprid'], 0);
        }
    }
    public static function remove_order($order_id, $restock = false, $reason = '')
    {
        if ($restock == 'on') {
            self::restock($order_id);
        }
        tep_db_query('delete from ' . TABLE_ORDERS . " where orders_id = '" . (int) $order_id . "'");
        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int) $order_id . "'");
        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = '" . (int) $order_id . "'");
        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " where orders_id = '" . (int) $order_id . "'");
        tep_db_query('delete from ' . TABLE_ORDERS_HISTORY . " where orders_id = '" . (int) $order_id . "'");
        tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . " where orders_id = '" . (int) $order_id . "'");
        tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . " where orders_id = '" . (int) $order_id . "'");
        \common\models\Orders_Products_Allocate::delete_all(['orders_id' => (int) $order_id]);
        \common\models\Orders_Splinters::delete_all(['orders_id' => (int) $order_id]);
        \common\models\Orders_Transactions_Children::delete_all(['orders_id' => (int) $order_id]);
        \common\models\Orders_Transactions::delete_all(['orders_id' => (int) $order_id]);
        \common\models\Ecommerce_Tracking::delete_all(['orders_id' => (int) $order_id]);
        \common\models\Orders_Payment::delete_all(['orders_payment_order_id' => (int) $order_id]);
        tep_db_query("delete from tracking_numbers where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tracking_numbers_to_orders_products where orders_id = '" . (int) $order_id . "'");
        foreach (\common\helpers\Hooks::get_list('orders/after-delete') as $filename) {
            include $filename;
        }
        $orders_delete_history = new \common\models\Orders_Delete_History();
        $orders_delete_history->load_default_values();
        $orders_delete_history->orders_id = (int) $order_id;
        $orders_delete_history->comments = 'Deleted ' . ($restock !== false ? 'with' : 'without') . ' restock.' . (!empty($reason) ? ' Reason:' . $reason : '');
        $orders_delete_history->admin_id = \Yii::$app->session->get('login_id');
        $orders_delete_history->date_added = date('Y-m-d H:i:s');
        $orders_delete_history->save(false);
    }
    public static function remove_tmp_order($order_id)
    {
        // 2do TABLE_PRODUCTS . " set products_ordered = products_ordered -
        $t_o = \common\models\Tmp_Orders::find_one((int) $order_id);
        if (!empty($t_o->child_id)) {
            return false;
        }
        tep_db_query("delete from tmp_orders where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tmp_orders_products where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tmp_orders_products_attributes where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tmp_orders_products_download where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tmp_orders_history where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tmp_orders_status_history where orders_id = '" . (int) $order_id . "'");
        tep_db_query("delete from tmp_orders_total where orders_id = '" . (int) $order_id . "'");
    }
    public static function get_order_status_name($order_status_id, $language_id = '')
    {
        global $languages_id;
        if ($order_status_id < 1) {
            if (!defined('TEXT_DEFAULT')) {
                \common\helpers\Translation::get_translation_value('TEXT_DEFAULT', 'admin/main');
            } else {
                $TEXT_DEFAULT = TEXT_DEFAULT;
            }
            return $TEXT_DEFAULT;
        }
        if (!is_numeric($language_id)) {
            $language_id = $languages_id;
        }
        static $status_names = [];
        $key = (int) $order_status_id . '@' . (int) $language_id;
        if (!isset($status_names[$key])) {
            $status_query = tep_db_query('select orders_status_name from ' . TABLE_ORDERS_STATUS . " where orders_status_id = '" . (int) $order_status_id . "' and language_id = '" . (int) $language_id . "'");
            $status = tep_db_fetch_array($status_query);
            $status_names[$key] = $status['orders_status_name'] ?? null;
        }
        return $status_names[$key];
    }
    public static function get_orders_products_status_name($order_products_status_id, $language_id = '', $is_long = true)
    {
        global $languages_id;
        if (!is_numeric($language_id)) {
            $language_id = $languages_id;
        }
        $status = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => $order_products_status_id, 'language_id' => $language_id]);
        return $status ? $is_long == true ? $status->orders_products_status_name_long : $status->orders_products_status_name : '';
    }
    public static function get_orders_products_status_manual_name($order_products_status_manual_id, $language_id = '', $is_long = true)
    {
        global $languages_id;
        if (!is_numeric($language_id)) {
            $language_id = $languages_id;
        }
        $status = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => $order_products_status_manual_id, 'language_id' => $language_id]);
        return $status ? $is_long == true ? $status->orders_products_status_manual_name_long : $status->orders_products_status_manual_name : '';
    }
    public static function get_status($default = '', $show_group = false)
    {
        global $languages_id;
        $status_array = [];
        if (!empty($default)) {
            $status_array[] = ['id' => '', 'text' => $default];
        }
        if ($show_group) {
            $status_query = tep_db_query("select os.orders_status_id, concat(osg.orders_status_groups_name, ' / ', os.orders_status_name) as orders_status_name from " . TABLE_ORDERS_STATUS . ' os left join ' . TABLE_ORDERS_STATUS_GROUPS . " osg on osg.orders_status_groups_id = os.orders_status_groups_id and osg.language_id = '" . $languages_id . "' where os.language_id = '" . $languages_id . "' order by orders_status_name");
        } else {
            $status_query = tep_db_query('select orders_status_id, orders_status_name from ' . TABLE_ORDERS_STATUS . " where language_id = '" . $languages_id . "' order by orders_status_name");
        }
        while ($status = tep_db_fetch_array($status_query)) {
            $status_array[] = ['id' => $status['orders_status_id'], 'text' => $status['orders_status_name']];
        }
        return $status_array;
    }
    public static function get_statuses_grouped($include_automated = false)
    {
        $status = [];
        $list = self::get_statuses(!$include_automated);
        if (!empty($list) && is_array($list)) {
            foreach ($list as $group) {
                if (!empty($group->statuses) && is_array($group->statuses)) {
                    $orders_status_groups = $group->attributes;
                    $status[] = ['text' => $orders_status_groups['orders_status_groups_name'], 'id' => 'group_' . $orders_status_groups['orders_status_groups_id'], 'group_color' => $orders_status_groups['orders_status_groups_color'], 'status_id' => 0, 'group_id' => $orders_status_groups['orders_status_groups_id']];
                    foreach ($group->statuses as $st) {
                        $orders_status = $st->attributes;
                        $status[] = ['text' => '&nbsp;&nbsp;&nbsp;&nbsp;' . $orders_status['orders_status_name'], 'id' => 'status_' . $orders_status['orders_status_id'], 'status_id' => $orders_status['orders_status_id'], 'group_id' => $orders_status_groups['orders_status_groups_id']];
                    }
                }
            }
        }
        return $status;
        /*
               $languages_id = \Yii::$app->settings->get('languages_id');
               $orders_status_groups_query = tep_db_query(
                   "select orders_status_groups_id, orders_status_groups_name, orders_status_groups_color ".
                   "from " . TABLE_ORDERS_STATUS_GROUPS . " ".
                   "where language_id = '" . (int)$languages_id . "' ".
                   " AND orders_status_type_id = '".intval(self::getStatusTypeId())."' ".
                   "order by orders_status_groups_id"
               );
               while ($orders_status_groups = tep_db_fetch_array($orders_status_groups_query)) {
                   $status[] = [
                       'text' => $orders_status_groups['orders_status_groups_name'],
                       'id' => 'group_' . $orders_status_groups['orders_status_groups_id'],
                       'group_color' => $orders_status_groups['orders_status_groups_color'],
                       'status_id' => 0,
                       'group_id' => $orders_status_groups['orders_status_groups_id'],
                   ];
                   $orders_status_query = tep_db_query(
                       "select orders_status_id, orders_status_name ".
                       "from " . TABLE_ORDERS_STATUS . " ".
                       "where language_id = '" . (int)$languages_id . "' and orders_status_groups_id='" . $orders_status_groups['orders_status_groups_id'] . "' ".
                       " ".($includeAutomated?"":"AND automated=0 ")." ".
                       "order by orders_status_name"
                   );
                   if ( tep_db_num_rows($orders_status_query)>0 ) {
                       while ($orders_status = tep_db_fetch_array($orders_status_query)) {
                           $status[] = [
                               'text' => '&nbsp;&nbsp;&nbsp;&nbsp;' . $orders_status['orders_status_name'],
                               'id' => 'status_' . $orders_status['orders_status_id'],
                               'status_id' => $orders_status['orders_status_id'],
                               'group_id' => $orders_status_groups['orders_status_groups_id'],
                           ];
                       }
                   }elseif($status[ count($status)-1 ]['id']=='group_' . $orders_status_groups['orders_status_groups_id']){
                       unset($status[ count($status)-1 ]);
                       $status = array_values($status);
                   }
               }
               return $status;
        */
    }
    public static function extract_statuses($statuses_string)
    {
        $statuses = [];
        foreach (explode(',', $statuses_string) as $check_status) {
            $check_status = trim($check_status);
            if (strpos($check_status, 'group_') === 0) {
                $orders_status_query = tep_db_query('select distinct orders_status_id from ' . TABLE_ORDERS_STATUS . " where orders_status_groups_id='" . intval(str_replace('group_', '', $check_status)) . "' ");
                while ($orders_status = tep_db_fetch_array($orders_status_query)) {
                    $statuses[(int) $orders_status['orders_status_id']] = (int) $orders_status['orders_status_id'];
                }
            } elseif (strpos($check_status, 'status_') === 0) {
                $status_id = intval(str_replace('status_', '', $check_status));
                $statuses[(int) $status_id] = (int) $status_id;
            } elseif ((int) $check_status != 0) {
                $statuses[(int) $check_status] = (int) $check_status;
            }
        }
        return array_values($statuses);
    }
    public static function orders_status_groups_name($orders_status_groups_id, $language_id = '')
    {
        global $languages_id;
        if (!$language_id) {
            $language_id = $languages_id;
        }
        $orders_status_groups_query = tep_db_query('select orders_status_groups_name from ' . TABLE_ORDERS_STATUS_GROUPS . " where orders_status_groups_id = '" . (int) $orders_status_groups_id . "' and language_id = '" . (int) $language_id . "'");
        $orders_status_groups = tep_db_fetch_array($orders_status_groups_query);
        return $orders_status_groups['orders_status_groups_name'] ?? null;
    }
    public static function get_status_name($id_status)
    {
        global $languages_id;
        $id_status = $id_status === '--none--' ? '' : $id_status;
        if (strlen(trim($id_status)) == 0) {
            return TEXT_NO_STATUS;
        } else {
            $status_name = [];
            $status_query = tep_db_query('select orders_status_name from ' . TABLE_ORDERS_STATUS . " where language_id = '" . $languages_id . "' and orders_status_id IN (" . $id_status . ') order by orders_status_name');
            while ($status = tep_db_fetch_array($status_query)) {
                $status_name[] = $status['orders_status_name'];
            }
            return implode(', ', $status_name);
        }
    }
    public static function trunk_orders($prefix = '')
    {
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS);
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS_HISTORY);
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS_PRODUCTS);
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS_PRODUCTS_ATTRIBUTES);
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS_PRODUCTS_DOWNLOAD);
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS_STATUS_HISTORY);
        tep_db_query('TRUNCATE ' . $prefix . TABLE_ORDERS_TOTAL);
        if (empty($prefix)) {
            $schema_check = \Yii::$app->get('db')->schema->get_table_schema('admin_shopping_carts');
            if ($schema_check) {
                tep_db_query('TRUNCATE TABLE admin_shopping_carts');
            }
            \common\models\Orders_Splinters::delete_all();
        }
        foreach (\common\helpers\Hooks::get_list('orders/after-trunk') as $filename) {
            include $filename;
        }
    }
    public static function parse_tracking_number($tracking_number)
    {
        if ($tracking_number instanceof \common\classes\Order_Tracking_Number) {
            return ['number' => $tracking_number->number, 'url' => $tracking_number->tracking_url, 'carrier' => $tracking_number->carrier];
        }
        $tracking_number = trim($tracking_number, " ,\t\n\r\x00\v");
        $carrier = '';
        if (strpos($tracking_number, ',') !== false && strpos($tracking_number, ',') < 10) {
            list($carrier, $tracking_number) = explode(',', $tracking_number, 2);
            $carrier = trim($carrier);
            $tracking_number = trim($tracking_number);
        }
        if (filter_var($tracking_number, FILTER_VALIDATE_URL)) {
            $url_query = parse_url($tracking_number, PHP_URL_QUERY);
            $_url_tracking_number = substr($url_query, ($pos = strrpos($url_query, '=')) > 0 ? $pos + 1 : 0);
            //$_url_tracking_number = urldecode($_url_tracking_number);
            if (strlen($_url_tracking_number) < 1) {
                $url_path = parse_url($tracking_number, PHP_URL_PATH);
                $_url_tracking_number = substr($url_path, ($pos = strrpos($url_path, '/')) > 0 ? $pos + 1 : 0);
            }
            if (strlen($_url_tracking_number) < 1) {
                $url_path = parse_url($tracking_number, PHP_URL_FRAGMENT);
                $_url_tracking_number = substr($url_path, ($pos = strrpos($url_path, '/')) > 0 ? $pos + 1 : 0);
            }
            return ['number' => $_url_tracking_number, 'url' => $tracking_number, 'carrier' => $carrier];
        } else {
            $tracking_url = TRACKING_NUMBER_URL . str_replace(' ', '', $tracking_number);
            if (stripos($tracking_url, '17track') !== false && strtolower($carrier) == 'fedex') {
                $tracking_url .= '&fc=100003';
            }
            if (stripos($tracking_url, '17track') !== false && strtolower($carrier) == 'dhl') {
                $tracking_url .= '&fc=100001';
            }
            if ($carrier && $carrier_record = \common\helpers\Extensions::call_if_allowed('TrackingCarriers', 'getTrackingCarriersRecord', [$carrier])) {
                if ($carrier_record->tracking_carriers_url) {
                    $tracking_url = $carrier_record->tracking_carriers_url . $tracking_number;
                }
                $carrier = $carrier_record->tracking_carriers_name;
            }
            return ['number' => $tracking_number, 'url' => $tracking_url, 'carrier' => $carrier];
        }
    }
    public static function get_used_total_class_list($selected = '')
    {
        if ($selected == '') {
            $selected = 'ot_total';
        }
        $totals = \common\models\Orders_Total::find()->select('class')->distinct()->order_by('class')->all();
        $ret = [];
        if (is_array($totals)) {
            foreach ($totals as $total) {
                $name = \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_' . strtoupper(str_replace('ot_', '', $total->class)) . '_TITLE', 'ordertotal');
                if ($name === false) {
                    $name = ucfirst(str_replace(['ot_', '_'], ['', ' '], $total->class));
                }
                $ret[] = [
                    'name' => $name,
                    //full_name,
                    'value' => $total->class,
                    'selected' => $selected && $selected == $total->class ? 'selected' : '',
                ];
            }
        }
        unset($totals);
        return $ret;
    }
    /**
     * Dispatch Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param boolean $isForced defines should be Order products set as Dispatched even if there is no stock available
     * @param integer $orderStatusPreferred try to search for preferred Order Status binded to Order Evaluation State and use it as Default Order Status
     * @return boolean false on any error, true on success
     */
    public static function do_dispatch($order_record = 0, $is_forced = false, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        $is_forced = (int) $is_forced > 0 ? true : false;
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $return = true;
            foreach (\common\models\Orders_Products::find_all(['orders_id' => (int) $order_record->orders_id]) as $order_product_record) {
                $return = (\common\helpers\Order_Product::do_dispatch($order_product_record, $is_forced) and $return);
            }
            unset($order_product_record);
            self::evaluate($order_record, $order_status_preferred);
        }
        unset($order_status_preferred);
        unset($order_record);
        unset($is_forced);
        return $return;
    }
    /**
     * Deliver Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param boolean $isForced defines should be Order products set as Delivered even if there is quantity awaiting for Dispatch
     * @param integer $orderStatusPreferred try to search for preferred Order Status binded to Order Evaluation State and use it as Default Order Status
     * @return boolean false on any error, true on success
     */
    public static function do_deliver($order_record = 0, $is_forced = false, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        $is_forced = (int) $is_forced > 0 ? true : false;
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $return = true;
            foreach (\common\models\Orders_Products::find_all(['orders_id' => (int) $order_record->orders_id]) as $order_product_record) {
                $return = (\common\helpers\Order_Product::do_deliver($order_product_record, $is_forced) and $return);
            }
            unset($order_product_record);
            self::evaluate($order_record, $order_status_preferred);
        }
        unset($order_status_preferred);
        unset($order_record);
        unset($is_forced);
        return $return;
    }
    /**
     * Cancel Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param boolean $isRestock defines should Dispatched quantity be returned to stock
     * @param integer $orderStatusPreferred try to search for preferred Order Status binded to Order Evaluation State and use it as Default Order Status
     * @return boolean false on any error, true on success
     */
    public static function do_cancel($order_record = 0, $is_restock = false, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        $is_restock = (int) $is_restock > 0 ? true : false;
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $return = true;
            foreach (\common\models\Orders_Products::find_all(['orders_id' => (int) $order_record->orders_id]) as $order_product_record) {
                $return = (\common\helpers\Order_Product::do_cancel($order_product_record, $is_restock) and $return);
            }
            unset($order_product_record);
            self::evaluate($order_record, $order_status_preferred);
        }
        unset($order_status_preferred);
        unset($order_record);
        unset($is_restock);
        return $return;
    }
    /**
     * Pend Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param boolean $isReset defines should Cancelled quantity be reset to 0
     * @param integer $orderStatusPreferred try to search for preferred Order Status binded to Order Evaluation State and use it as Default Order Status
     * @return boolean false on any error, true on success
     */
    public static function do_pendent($order_record = 0, $is_reset = false, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $return = true;
            self::update_allocate_allow($order_record, 0);
            foreach (\common\models\Orders_Products::find_all(['orders_id' => (int) $order_record->orders_id]) as $order_product_record) {
                $return = (\common\helpers\Order_Product::do_quote($order_product_record, $is_reset) and $return);
            }
            unset($order_product_record);
            self::evaluate($order_record, $order_status_preferred);
        }
        unset($order_status_preferred);
        unset($order_record);
        unset($is_reset);
        return $return;
    }
    /**
     * Process Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param null $_null reserved for further use
     * @param integer $orderStatusPreferred try to search for preferred Order Status binded to Order Evaluation State and use it as Default Order Status
     * @return boolean false on any error, true on success
     */
    public static function do_process($order_record = 0, $_null = null, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $return = true;
            self::update_allocate_allow($order_record, 1);
            foreach (\common\models\Orders_Products::find_all(['orders_id' => (int) $order_record->orders_id]) as $order_product_record) {
                $return = (\common\helpers\Order_Product::do_allocate_automatic($order_product_record) and $return);
            }
            unset($order_product_record);
            self::evaluate($order_record, $order_status_preferred);
        }
        unset($order_status_preferred);
        unset($order_record);
        return $return;
    }
    /**
     * Refresh Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param null $_null reserved for further use
     * @param integer $orderStatusPreferred try to search for preferred Order Status binded to Order Evaluation State and use it as Default Order Status
     * @return boolean false on any error, true on success
     */
    public static function do_refresh($order_record = 0, $_null = null, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $return = true;
            foreach (\common\models\Orders_Products::find_all(['orders_id' => (int) $order_record->orders_id]) as $order_product_record) {
                $return = (\common\helpers\Order_Product::do_allocate_automatic($order_product_record, true) and $return);
            }
            unset($order_product_record);
            self::evaluate($order_record, $order_status_preferred);
        }
        unset($order_status_preferred);
        unset($order_record);
        return $return;
    }
    /**
     * Validate and updating Product Allocation records.
     * Updating Dispatched based on Delivered and Received based on Disptached.
     * Deleting orphan allocation records or where Received equals 0.
     * Rule: Received >= Dispatched >= Delivered
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @return boolean false on error, true - if validation is passed
     */
    public static function is_valid_allocated($order_record = 0)
    {
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            $order_product_skip_list = [];
            foreach (self::get_allocated_array($order_record, false) as $product_allocated) {
                if (!isset($order_product_skip_list[$product_allocated->orders_products_id])) {
                    $order_product_skip_list[$product_allocated->orders_products_id] = $product_allocated->orders_products_id;
                    $order_product_record = \common\helpers\Order_Product::get_record($product_allocated->orders_products_id);
                    if ($order_product_record instanceof \common\models\Orders_Products and $order_product_record->orders_id == $order_record->orders_id) {
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
        }
        unset($order_record);
        return true;
    }
    /**
     * Get Order Product Allocation array
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param boolean $asArray switching return type between array of arrays or array of instances of OrdersProductsAllocate
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_allocated_array($order_record = 0, $as_array = true)
    {
        $return = [];
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            foreach (\common\models\Orders_Products_Allocate::find()->where(['orders_id' => (int) $order_record->orders_id])->as_array($as_array)->all() as $op_allocate_record) {
                $return[] = $op_allocate_record;
            }
            unset($op_allocate_record);
        }
        unset($order_record);
        unset($as_array);
        return $return;
    }
    /**
     * Automatically update Order Status based on Order Product statuses
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param int $orderStatusPreferred preferred order status if two or more statuses are bonded to same order evaluation state. Default to current order status
     * @return mixed false on error or current Order Status Id
     */
    public static function evaluate($order_record = 0, $order_status_preferred = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            if (self::is_valid_allocated($order_record) != true) {
                return $return;
            }
            $order_status = (int) $order_record->orders_status;
            $order_status_preferred = (int) ((int) $order_status_preferred <= 0 ? $order_status : $order_status_preferred);
            $order_product_status_array = array_fill_keys(array_keys(\common\helpers\Order_Product::get_status_array()), 0);
            foreach (\common\models\Orders_Products::find_all(['orders_id' => $order_record->orders_id]) as $order_product_record) {
                $order_product_status_array[$order_product_record->orders_products_status] += (int) $order_product_record->products_quantity;
            }
            unset($order_product_record);
            if ($order_product_status_array[\common\helpers\Order_Product::OPS_CANCELLED] > 0) {
                $return = self::OES_CANCELLED;
            }
            if ($order_product_status_array[\common\helpers\Order_Product::OPS_DELIVERED] > 0) {
                $return = self::OES_DELIVERED;
            }
            if ($order_product_status_array[\common\helpers\Order_Product::OPS_DISPATCHED] > 0) {
                $return = self::OES_DISPATCHED;
            }
            if ($order_product_status_array[\common\helpers\Order_Product::OPS_RECEIVED] > 0) {
                $return = self::OES_RECEIVED;
            }
            if ($order_product_status_array[\common\helpers\Order_Product::OPS_STOCK_DEFICIT] > 0 or $order_product_status_array[\common\helpers\Order_Product::OPS_STOCK_ORDERED] > 0) {
                $return = self::OES_PROCESSING;
            }
            if ($order_product_status_array[\common\helpers\Order_Product::OPS_QUOTED] > 0) {
                if ($return == false) {
                    $return = self::OES_PENDING;
                } else {
                    $return = self::OES_PROCESSING;
                }
            }
            unset($order_product_status_array);
            $order_status_record = \common\models\Orders_Status::get_default_by_order_evaluation_state($return, $order_status_preferred);
            if (!$order_status_record instanceof \common\models\Orders_Status and $return == self::OES_DELIVERED) {
                $return = self::OES_DISPATCHED;
                $order_status_record = \common\models\Orders_Status::get_default_by_order_evaluation_state($return, $order_status_preferred);
            }
            if (!$order_status_record instanceof \common\models\Orders_Status and $return == self::OES_DISPATCHED) {
                $return = self::OES_RECEIVED;
                $order_status_record = \common\models\Orders_Status::get_default_by_order_evaluation_state($return, $order_status_preferred);
            }
            if (!$order_status_record instanceof \common\models\Orders_Status and $return == self::OES_RECEIVED) {
                $return = self::OES_PROCESSING;
                $order_status_record = \common\models\Orders_Status::get_default_by_order_evaluation_state($return, $order_status_preferred);
            }
            /* UNCOMMENT IN CASE OF FULLY AUTOMATIC STATUS CHANGE MODE ONLY!
               if (!($orderStatusRecord instanceof \common\models\OrdersStatus) AND $return == self::OES_PROCESSING) {
                   $return = self::OES_PENDING;
                   $orderStatusRecord = \common\models\OrdersStatus::getDefaultByOrderEvaluationState($return, $orderStatusPreferred);
               }
               EOF UNCOMMENT IN CASE OF FULLY AUTOMATIC STATUS CHANGE MODE ONLY! */
            $return = $order_status;
            if ($order_status_record instanceof \common\models\Orders_Status and $order_status_record->orders_status_id != $return) {
                $is_history = false;
                try {
                    $order_record->orders_status = (int) $order_status_record->orders_status_id;
                    $order_record->last_modified = date('Y-m-d H:i:s');
                    $order_record->save();
                    $is_history = true;
                } catch (\Exception $exc) {
                    $order_record->orders_status = $return;
                }
                $return = (int) $order_record->orders_status;
                if ($is_history == true) {
                    \common\models\Orders_Status_History::write($order_record, $return, TEXT_ORDER_STATUS_AUTO_EVALUATE, 0, '');
                }
                unset($is_history);
            }
            unset($order_status_record);
            unset($order_status);
        }
        unset($order_record);
        return $return;
    }
    /**
     * Update allocation allowance status for Order
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param integer $allocateAllow allowance status value or based on Order Status value by default
     * @return mixed allocation allowance status or false on error
     */
    public static function update_allocate_allow($order_record = 0, $allocate_allow = -1)
    {
        $return = false;
        $allocate_allow = (int) $allocate_allow;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            $return = $order_record->orders_allocate_allow;
            if ($allocate_allow < 0) {
                $order_status_record = \common\models\Orders_Status::find_one(['orders_status_id' => $order_record->orders_status]);
                if ($order_status_record instanceof \common\models\Orders_Status) {
                    if ($order_status_record->orders_status_allocate_allow > 0 and $order_record->orders_allocate_allow != $order_status_record->orders_status_allocate_allow) {
                        try {
                            $order_record->orders_allocate_allow = $order_status_record->orders_status_allocate_allow;
                            $order_record->save();
                            $return = $order_record->orders_allocate_allow;
                        } catch (\Exception $exc) {
                        }
                    }
                }
                unset($order_status_record);
            } elseif ($order_record->orders_allocate_allow != $allocate_allow) {
                try {
                    $order_record->orders_allocate_allow = $allocate_allow;
                    $order_record->save();
                    $return = $order_record->orders_allocate_allow;
                } catch (\Exception $exc) {
                }
            }
        }
        unset($allocate_allow);
        unset($order_record);
        return $return;
    }
    /**
     * Check is order stock should be allocated as temporary
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @return boolean allocate as temporary
     */
    public static function is_allocate_temporary($order_record = 0)
    {
        $return = false;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            $order_status_record = \common\models\Orders_Status::find_one(['orders_status_id' => $order_record->orders_status]);
            if ($order_status_record instanceof \common\models\Orders_Status) {
                $order_status_group_record = \common\models\Orders_Status_Groups::find_one(['orders_status_groups_id' => $order_status_record->orders_status_groups_id]);
                if ($order_status_group_record instanceof \common\models\Orders_Status_Groups) {
                    $return = (int) $order_status_group_record->orders_status_groups_store_temporary > 0;
                }
            }
            unset($order_status_record);
        }
        unset($order_record);
        return $return;
    }
    /**
     * Set Order status (triggering binded order evaluation state update).
     * Behaviour $isAlternativeBehaviour:
     * OES_PENDING - defines should Cancelled quantity be reset to 0;
     * OES_PROCESSING - none;
     * OES_CANCELLED - defines should Dispatched quantity be returned to stock;
     * OES_DISPATCHED - defines should be Order products set as Dispatched even if there is no stock available;
     * OES_DELIVERED - defines should be Order products set as Delivered even if there is quantity awaiting for Dispatch
     * @param integer|\common\models\Orders $orderRecord Order Id or instance of Orders model
     * @param integer $orderStatus desired order status
     * @param array $historyArray Order Status History record parameters
     * @param boolean $isIgnoreBindEvaluationState if true - Order Evaluation State event binded to Order Status wouldn't be triggered
     * @param boolean $isAlternativeBehaviour switch order processing behaviour depending on binded order status evaluation state
     * @return mixed false on error or current order status
     */
    public static function set_status($order_record = 0, $order_status = 0, $history_array = [], $is_ignore_bind_evaluation_state = false, $is_alternative_behaviour = false)
    {
        $__orders_id = 0;
        $return = false;
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            $__orders_id = $order_record->orders_id;
            $is_history = false;
            $order_status = (int) $order_status;
            $return = (int) $order_record->orders_status;
            $prev_status = (int) $order_record->orders_status;
            $is_alternative_behaviour = (int) $is_alternative_behaviour > 0;
            $history_array = is_array($history_array) ? $history_array : [];
            $is_ignore_bind_evaluation_state = (int) $is_ignore_bind_evaluation_state > 0;
            $order_status_record = \common\models\Orders_Status::find_one(['orders_status_id' => $order_status]);
            if ($order_status_record instanceof \common\models\Orders_Status) {
                if ($is_ignore_bind_evaluation_state == false) {
                    if ($order_status_record->order_evaluation_state_id == self::OES_PENDING) {
                        self::do_pendent($order_record, $is_alternative_behaviour, $order_status);
                    } elseif ($order_status_record->order_evaluation_state_id == self::OES_PROCESSING) {
                        self::do_process($order_record, $is_alternative_behaviour, $order_status);
                    } elseif ($order_status_record->order_evaluation_state_id == self::OES_CANCELLED) {
                        self::do_cancel($order_record, $is_alternative_behaviour, $order_status);
                    } elseif ($order_status_record->order_evaluation_state_id == self::OES_DISPATCHED) {
                        self::do_dispatch($order_record, $is_alternative_behaviour, $order_status);
                    } elseif ($order_status_record->order_evaluation_state_id == self::OES_DELIVERED) {
                        self::do_deliver($order_record, $is_alternative_behaviour, $order_status);
                    }
                }
                self::do_refresh($order_record, $is_alternative_behaviour, $order_status);
                $return = (int) $order_record->orders_status;
                if ($return != $order_status) {
                    try {
                        $order_record->orders_status = $order_status;
                        $order_record->last_modified = date('Y-m-d H:i:s');
                        $order_record->save();
                        $is_history = true;
                    } catch (\Exception $exc) {
                        $order_record->orders_status = $return;
                        \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string());
                    }
                    $return = (int) $order_record->orders_status;
                }
                if ($order_status_record->orders_status_release_deferred == 1) {
                    try {
                        $order_payment_record_array = \common\models\Orders_Payment::find()->where(['orders_payment_order_id' => $__orders_id])->and_where(['deferred' => 1])->order_by(['orders_payment_date_create' => SORT_DESC, 'orders_payment_id' => SORT_DESC])->all();
                        if (is_array($order_payment_record_array) && count($order_payment_record_array) > 0) {
                            $manager = \common\services\Order_Manager::load_manager();
                            foreach ($order_payment_record_array as $order_payment_record) {
                                $payment = $manager->get_payment_collection($order_payment_record['orders_payment_module'])->get_selected_payment();
                                if (is_object($payment) && method_exists($payment, 'release')) {
                                    $payment->release($order_payment_record['orders_payment_transaction_id'], $order_status);
                                }
                                unset($payment);
                            }
                            unset($order_payment_record);
                            unset($manager);
                        }
                        unset($order_payment_record_array);
                    } catch (\Exception $exc) {
                        \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string());
                    }
                }
            }
            unset($order_status_record);
            $comments = trim(isset($history_array['comments']) ? $history_array['comments'] : '');
            $smscomments = trim(isset($history_array['smscomments']) ? $history_array['smscomments'] : '');
            $date_added = isset($history_array['date_added']) ? $history_array['date_added'] : null;
            $is_notified = isset($history_array['customer_notified']) ? (int) $history_array['customer_notified'] > 0 ? 1 : 0 : 0;
            if ($is_history == true or $comments != '' or $smscomments != '' or $is_notified > 0) {
                \common\models\Orders_Status_History::write($order_record, $return, $comments, $is_notified, $smscomments, $date_added);
            }
            unset($is_notified);
            unset($is_history);
            unset($comments);
            self::do_refresh($order_record, $is_alternative_behaviour, $return);
            try {
                $new_status = \common\models\Orders::find()->select('orders_status')->where(['orders_id' => $__orders_id])->one()->orders_status ?? 0;
                if (!Status::is_canceled_group($prev_status) && Status::is_canceled_group($new_status)) {
                    // Credit amount used on a cancelled order is returned to the customer automatically
                    $credit_amount = \common\models\Orders_Total::find()->where(['orders_id' => $__orders_id, 'class' => 'ot_gv'])->sum('value_inc_tax');
                    if ($credit_amount > 0) {
                        $check = \common\models\Customers_Credit_History::find()->and_where(new \yii\db\Expression('abs(credit_amount - ' . (float) $credit_amount . ') < 0.2'))->and_where(['like', 'comments', $__orders_id])->and_where(['customers_id' => (int) $order_record->customers_id, 'credit_prefix' => '-'])->exists();
                        if ($check) {
                            if ($customer = \common\components\Customer::find_one(['customers_id' => $order_record->customers_id])) {
                                $customer->credit_amount += $credit_amount;
                                $customer->save();
                                $customer->save_credit_history($order_record->customers_id, $credit_amount, '+', $order_record->currency, $order_record->currency_value, 'Cancel Order #' . $__orders_id);
                            }
                        }
                    }
                } elseif (Status::is_canceled_group($prev_status) && !Status::is_canceled_group($new_status)) {
                    // Undo - Credit amount used on a cancelled order is returned to the customer automatically
                    $credit_amount = \common\models\Orders_Total::find()->where(['orders_id' => $__orders_id, 'class' => 'ot_gv'])->sum('value_inc_tax');
                    if ($credit_amount > 0) {
                        $check = \common\models\Customers_Credit_History::find()->and_where(new \yii\db\Expression('abs(credit_amount - ' . (float) $credit_amount . ') < 0.2'))->and_where(['like', 'comments', $__orders_id])->and_where(['customers_id' => (int) $order_record->customers_id, 'credit_prefix' => '+'])->exists();
                        if ($check) {
                            if ($customer = \common\components\Customer::find_one(['customers_id' => $order_record->customers_id])) {
                                $customer->credit_amount -= $credit_amount;
                                $customer->save();
                                $customer->save_credit_history($order_record->customers_id, $credit_amount, '-', $order_record->currency, $order_record->currency_value, 'Undo Cancel Order #' . $__orders_id);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Yii::warning(print_r($e->get_message(), true), 'TLDEBUG');
            }
            foreach (\common\helpers\Hooks::get_list('orders/after-setstatus') as $filename) {
                include $filename;
            }
        }
        unset($is_ignore_bind_evaluation_state);
        unset($is_alternative_behaviour);
        unset($history_array);
        unset($order_status);
        unset($order_record);
        return $return;
    }
    /**
     * Search and cancel expired temporary stock allocation. Order is cancelled too if possible.
     * Cancel expired orders in status from "Temporary allocate order products" flag enabled status groups.
     * Behaviour: ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRED_DURATION >= 1
     * @return boolean always true
     */
    public static function do_cancel_allocated_temporary_expired()
    {
        \common\helpers\Translation::init('admin/main');
        $order_status_expired = (int) \common\helpers\Configuration::get_configuration_key_value('ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRED');
        $order_status_expired_duration_hours = (int) \common\helpers\Configuration::get_configuration_key_value('ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRED_DURATION');
        if ($order_status_expired_duration_hours < 1) {
            $order_status_expired_duration_hours = 1;
        }
        foreach (\common\models\Orders_Products_Allocate::find()->select(['orders_id'])->where(['is_temporary' => 1])->and_where(['<', 'datetime', date('Y-m-d H:i:s', strtotime("-{$order_status_expired_duration_hours} hours"))])->group_by('orders_id')->as_array(true)->all() as $order_id) {
            $order_id = (int) $order_id['orders_id'];
            $is_expired = null;
            $is_temporary = null;
            $opa_record_array = [];
            foreach (\common\models\Orders_Products_Allocate::find()->and_where(['orders_id' => $order_id])->as_array(false)->all() as $opa_record) {
                $is_temporary = is_null($is_temporary) ? true : $is_temporary;
                if ($opa_record->is_temporary <= 0) {
                    $is_temporary = false;
                    continue;
                }
                $is_expired = false;
                if (strtotime($opa_record->datetime) < strtotime("-{$order_status_expired_duration_hours} hours")) {
                    $is_expired = true;
                }
                if ($is_expired === false) {
                    break;
                }
                $opa_record_array[] = $opa_record;
            }
            unset($opa_record);
            if ($is_expired === true) {
                try {
                    foreach ($opa_record_array as $opa_record) {
                        \common\helpers\Order_Product::do_cancel($opa_record->orders_products_id, false);
                    }
                } catch (\Exception $exc) {
                }
                unset($opa_record);
                try {
                    if ($is_temporary === true and $order_status_expired > 0) {
                        self::set_status($order_id, $order_status_expired, [], false, false);
                    } else {
                        self::evaluate($order_id);
                    }
                } catch (\Exception $exc) {
                }
            }
            unset($opa_record_array);
            unset($is_temporary);
            unset($is_expired);
        }
        unset($order_id);
        $temporary_allocate_order_status_id_list = \common\models\Orders_Status_Groups::find()->alias('osg')->left_join(\common\models\Orders_Status::table_name() . ' os', 'os.orders_status_groups_id = osg.orders_status_groups_id AND os.language_id = osg.language_id')->where(['osg.orders_status_groups_store_temporary' => 1])->group_by(['os.orders_status_id'])->as_array(true)->select('os.orders_status_id')->column();
        foreach (\common\models\Orders::find()->where(['in', 'orders_status', $temporary_allocate_order_status_id_list])->and_where(['or', ['and', ['!=', 'last_modified', '0000-00-00 00:00:00'], ['<', 'last_modified', date('Y-m-d H:i:s', strtotime("-{$order_status_expired_duration_hours} hours"))]], ['and', ['last_modified' => '0000-00-00 00:00:00'], ['<', 'date_purchased', date('Y-m-d H:i:s', strtotime("-{$order_status_expired_duration_hours} hours"))]]])->as_array(true)->select('orders_id')->column() as $order_id) {
            try {
                if ($order_status_expired > 0) {
                    self::set_status($order_id, $order_status_expired, [], false, false);
                } else {
                    \common\helpers\Order::do_cancel($order_id, false, 0);
                }
            } catch (\Exception $exc) {
            }
        }
        unset($temporary_allocate_order_status_id_list);
        unset($order_id);
        return true;
    }
    /**
     * Get Order record
     * @param mixed $orderId Order Id or instance of Orders model
     * @return mixed instance of Orders model or null
     */
    public static function get_record($order_id = 0)
    {
        return $order_id instanceof \common\models\Orders ? $order_id : \common\models\Orders::find_one(['orders_id' => (int) $order_id]);
    }
    /**
     * Get Order Product array
     * @param mixed $orderRecord Order Id or instance of Orders model
     * @param boolean $asArray switching return type between array of arrays or array of instances of OrdersProducts
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_product_array($order_record = 0, $as_array = true)
    {
        $return = [];
        $order_record = self::get_record($order_record);
        if ($order_record instanceof \common\models\Orders) {
            foreach (\common\models\Orders_Products::find()->where(['orders_id' => (int) $order_record->orders_id])->as_array($as_array)->all() as $op_record) {
                $return[] = $op_record;
            }
            unset($op_record);
        }
        unset($order_record);
        unset($as_array);
        return $return;
    }
    /**
     * Get configuration array of possible automated evaluation states
     * @return array configuration array of possible automated evaluation states
     */
    public static function get_evaluation_state_array()
    {
        return [self::OES_PENDING => ['long' => 'Pending', 'short' => 'Pndg', 'key' => 'OES_PENDING'], self::OES_PROCESSING => ['long' => 'Processing', 'short' => 'Proc', 'key' => 'OES_PROCESSING'], self::OES_RECEIVED => ['long' => 'Received', 'short' => 'Rcvd', 'key' => 'OES_RECEIVED'], self::OES_DISPATCHED => ['long' => 'Dispatched', 'short' => 'Dspd', 'key' => 'OES_DISPATCHED'], self::OES_DELIVERED => ['long' => 'Delivered', 'short' => 'Dlvd', 'key' => 'OES_DELIVERED'], self::OES_CANCELLED => ['long' => 'Cancelled', 'short' => 'Cnld', 'key' => 'OES_CANCELLED'], self::OES_PARTIAL_CANCELLED => ['long' => 'Partially Cancelled', 'short' => 'PartCnld', 'key' => 'OES_PARTIAL_CANCELLED']];
    }
    public static function get_orders_query(array $fields)
    {
        $c_query = \common\models\Orders::find()->select(array_keys($fields))->where('1=1');
        foreach ($fields as $field => $value) {
            if (is_array($value)) {
                $c_query->and_where(['in', $field, $value]);
            } elseif (is_string($value) && !empty($value)) {
                $c_query->and_where(['like', $field, $value]);
            }
        }
        return $c_query;
    }
    public static function get_purchase_order_id(\common\classes\extended\Order_Abstract $order)
    {
        return !empty($order->info['purchase_order']) ? ' #' . $order->info['purchase_order'] : '';
    }
    public static function get_order_volume_weight(int $order_id)
    {
        $shipment_volume = 0;
        $order_products = \common\models\Orders_Products::find()->where(['orders_id' => $order_id])->as_array()->all();
        foreach ($order_products as $product) {
            $shipment_volume += \common\helpers\Product::get_products_volume((int) $product['products_id'], true) * $product['products_quantity'];
        }
        return $shipment_volume;
    }
    /**
     * query cost and profit amount on order. Ordered product should be allocated (assigned to supplier and its price)
     * @param int|array $orders_ids
     * @return array|null
     */
    public static function get_profit($orders_ids)
    {
        $ret = null;
        if (is_array($orders_ids)) {
            $orders_ids = array_map('intval', $orders_ids);
        }
        if ($orders_ids) {
            $q = (new \yii\db\Query())->select(['sum(opa.allocate_received * opa.suppliers_price) as cost', 'sum(opa.allocate_received * (op.final_price - opa.suppliers_price)) as profit', 'sum(opa.allocate_received * (op.final_price - opa.suppliers_price)) / sum(opa.allocate_received * opa.suppliers_price) * 100 as profit_percent'])->from(['op' => TABLE_ORDERS_PRODUCTS])->left_join(['opa' => 'orders_products_allocate'], 'op.orders_products_id = opa.orders_products_id')->and_where(['op.orders_id' => $orders_ids])->and_where('opa.allocate_received > 0 and opa.suppliers_price > 0');
            if (is_array($orders_ids)) {
                $q->add_select('op.orders_id')->group_by('op.orders_id')->index_by('orders_id');
                $ret = $q->all();
            } else {
                $ret = $q->one();
            }
        }
        return $ret;
    }
    public static function anonimize_order($orders_id, $table = '')
    {
        $removed_id = \common\helpers\Customer::find_create_anonymous_customer();
        $sql_data = [
            'customers_id' => (int) $removed_id,
            'basket_id' => 0,
            'customers_name' => 'removed',
            'customers_firstname' => 'removed',
            'customers_lastname' => 'removed',
            'customers_company' => '',
            'customers_company_vat' => '',
            'customers_customs_number' => '',
            'customers_street_address' => '',
            'customers_suburb' => '',
            'customers_city' => '',
            'customers_postcode' => '',
            //customers_state
            //customers_country
            'customers_telephone' => '',
            'customers_email_address' => 'removed',
            'delivery_gender' => '',
            'delivery_name' => 'removed',
            'delivery_firstname' => 'removed',
            'delivery_lastname' => 'removed',
            'delivery_company' => '',
            'delivery_street_address' => '',
            'delivery_suburb' => '',
            'delivery_city' => '',
            'delivery_postcode' => '',
            //delivery_state
            //delivery_country
            'delivery_address_book_id' => 0,
            'billing_gender' => '',
            'billing_name' => 'removed',
            'billing_firstname' => 'removed',
            'billing_lastname' => 'removed',
            'billing_company' => '',
            'billing_street_address' => '',
            'billing_suburb' => '',
            'billing_city' => '',
            'billing_postcode' => '',
            //billing_state
            //billing_country
            'billing_address_book_id' => 0,
        ];
        $status_check_where = '';
        if (defined('GDPR_CUSTOMER_DELETE_OPEN_ORDER_STATUSES') && !empty(trim(GDPR_CUSTOMER_DELETE_OPEN_ORDER_STATUSES))) {
            $tmp = array_map('intval', explode(',', GDPR_CUSTOMER_DELETE_OPEN_ORDER_STATUSES));
            if (is_array($tmp) && !empty($tmp)) {
                $status_check_where = ' and orders_status not in (' . implode(',', $tmp) . ')';
            }
        }
        if (empty($table)) {
            $table = TABLE_ORDERS;
        } elseif (!in_array($table, [TABLE_ORDERS, 'quote_' . TABLE_ORDERS, 'sample_' . TABLE_ORDERS, 'tmp_' . TABLE_ORDERS, TABLE_SUBSCRIPTION])) {
            $table = false;
        }
        foreach (\common\helpers\Hooks::get_list('orders/order-anonymize') as $filename) {
            include $filename;
        }
    }
    public static function get_statuses_details($type_id = 1)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $ret = \common\models\Orders_Status::find()->alias('os')->left_join(['osg' => \common\models\Orders_Status_Groups::table_name()], 'os.orders_status_groups_id=osg.orders_status_groups_id')->select('os.*, osg.*')->and_where(['orders_status_type_id' => $type_id])->and_where(['os.language_id' => $languages_id])->and_where(['osg.language_id' => $languages_id]);
        //echo $ret ->createCommand()->rawSql; die;
        $ret = $ret->as_array()->index_by('orders_status_id')->all();
        return $ret;
    }
}