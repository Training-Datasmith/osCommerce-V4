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

use common\models\Orders_Status_Type;
class Status
{
    public const OST_SUBSCRIPTION = 2;
    public const OST_QUOTATIONS = 3;
    public const OST_SAMPLES = 4;
    public const OST_PURCHASE_ORDERS = 5;
    // order_status_type => extension
    public const OST2EXT = [self::OST_QUOTATIONS => 'Quotations', self::OST_SAMPLES => 'Samples', self::OST_PURCHASE_ORDERS => 'PurchaseOrders'];
    public static function get_status_type_list($with_all = false)
    {
        global $languages_id;
        $orders_status_type = [];
        if ($with_all) {
            $orders_status_type[''] = TEXT_ALL_ORDERS_STATUS_TYPES;
        }
        $orders_status_types_query = tep_db_query('select orders_status_type_id, orders_status_type_name, orders_status_type_color from ' . TABLE_ORDERS_STATUS_TYPE . " where language_id = '" . (int) $languages_id . "'");
        while ($orders_status_types = tep_db_fetch_array($orders_status_types_query)) {
            $orders_status_type[$orders_status_types['orders_status_type_id']] = $orders_status_types['orders_status_type_name'];
        }
        foreach (self::OST2EXT as $id => $name) {
            if (!\common\helpers\Acl::check_extension_allowed($name)) {
                unset($orders_status_type[$id]);
            }
        }
        return $orders_status_type;
    }
    /**
     *
     * @param bool $withAll optional (false) Add " all " option
     * @param int $type type id optional (0 - all types)
     * @return array [type_name][status_groups_id => status_groups_name]
     */
    public static function get_status_groups_list($with_all = false, $type = 0)
    {
        $orders_status_groups = [];
        if ($with_all) {
            $orders_status_groups[''] = TEXT_ALL_ORDERS_STATUS_GROUPS;
        }
        $status_type = Orders_Status_Type::find()->with('groups');
        if ((int) $type > 0) {
            $status_type->and_where(['orders_status_type_id' => (int) $type]);
        }
        $tmp = $status_type->as_array()->all();
        if (is_array($tmp)) {
            foreach ($tmp as $type) {
                if (is_array($type['groups'])) {
                    foreach ($type['groups'] as $group) {
                        $orders_status_groups[$type['orders_status_type_name']][$group['orders_status_groups_id']] = $group['orders_status_groups_name'];
                    }
                }
            }
        }
        /*
                $orders_status_types_query = tep_db_query("select orders_status_type_id, orders_status_type_name, orders_status_type_color from " . TABLE_ORDERS_STATUS_TYPE . " where language_id = '" . (int) $languages_id . "'");
                while ($orders_status_types = tep_db_fetch_array($orders_status_types_query)) {
                    $orders_status_groups_query = tep_db_query("select orders_status_groups_id, orders_status_groups_name, orders_status_groups_color from " . TABLE_ORDERS_STATUS_GROUPS . " where language_id = '" . (int) $languages_id . "' and orders_status_type_id = '" . (int) $orders_status_types['orders_status_type_id'] . "'");
                    while ($orders_status_groups = tep_db_fetch_array($orders_status_groups_query)) {
                        $ordersStatusGroups[$orders_status_types['orders_status_type_name']][$orders_status_groups['orders_status_groups_id']] = $orders_status_groups['orders_status_groups_name'];
                    }
                }
        */
        return $orders_status_groups;
    }
    public static function get_status_list($with_all = false)
    {
        global $languages_id;
        $orders_statuses = [];
        if ($with_all) {
            $orders_statuses[''] = TEXT_ALL_ORDERS_STATUS;
        }
        $orders_status_types_query = tep_db_query('select orders_status_type_id, orders_status_type_name, orders_status_type_color from ' . TABLE_ORDERS_STATUS_TYPE . " where language_id = '" . (int) $languages_id . "'");
        while ($orders_status_types = tep_db_fetch_array($orders_status_types_query)) {
            $orders_status_groups_query = tep_db_query('select orders_status_groups_id, orders_status_groups_name, orders_status_groups_color from ' . TABLE_ORDERS_STATUS_GROUPS . " where language_id = '" . (int) $languages_id . "' and orders_status_type_id = '" . (int) $orders_status_types['orders_status_type_id'] . "'");
            while ($orders_status_groups = tep_db_fetch_array($orders_status_groups_query)) {
                //$orders_status_groups['orders_status_groups_id']
                $orders_status_query = tep_db_query('select orders_status_id, orders_status_name, automated from ' . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "' and orders_status_groups_id = '" . (int) $orders_status_groups['orders_status_groups_id'] . "' ORDER BY orders_status_name ASC");
                while ($orders_status = tep_db_fetch_array($orders_status_query)) {
                    $orders_statuses[$orders_status_types['orders_status_type_name']][$orders_status_groups['orders_status_groups_name']][$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
                }
            }
        }
        return $orders_statuses;
    }
    /**
     * @deprecated
     * @param type $name
     * @param type $selected
     * @return string
     */
    public static function get_status_list_by_type_name($name, $selected = '')
    {
        $status_type = Orders_Status_Type::find()->where(['orders_status_type_name' => $name])->join_with('groups.statuses')->one();
        $statuses = [];
        $statuses[] = ['name' => TEXT_ALL_ORDERS, 'value' => '', 'selected' => ''];
        if (is_array($status_type->groups)) {
            foreach ($status_type->groups as $group) {
                $statuses[] = ['name' => $group->orders_status_groups_name, 'value' => 'group_' . $group->orders_status_groups_id, 'selected' => ''];
                foreach ($group->statuses as $status) {
                    if (!empty($status['hidden'])) {
                        continue;
                    }
                    $statuses[] = ['name' => '&nbsp;&nbsp;&nbsp;&nbsp;' . $status->orders_status_name, 'value' => 'status_' . $status->orders_status_id, 'selected' => ''];
                }
            }
        }
        if ($selected) {
            foreach ($statuses as $key => $value) {
                if ($value['value'] == $selected) {
                    $statuses[$key]['selected'] = 'selected';
                }
            }
        }
        return $statuses;
    }
    public static function is_canceled_group($order_status_id)
    {
        $language_id = (int) \Yii::$app->settings->get('languages_id');
        if ($language_id <= 0) {
            $language_id = \common\helpers\Language::get_default_language_id();
        }
        $order_status = \common\models\Orders_Status::find()->alias('os')->join_with('ordersStatusGroups og', false)->where(['os.orders_status_id' => $order_status_id, 'os.language_id' => $language_id])->select('os.orders_status_id, os.orders_status_name, og.orders_status_groups_id, og.orders_status_groups_name, og.order_group_evaluation_state_id')->as_array()->one();
        return !empty($order_status) && ($order_status['orders_status_groups_id'] == 5 || $order_status['order_group_evaluation_state_id'] == Order::OES_CANCELLED);
    }
}