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

trait Status_Trait
{
    /**
     * for dropDownList statuses
     * @param bool $withAll def false
     * @param bool $wouAutomated def false
     * @param int|array $includeStatus def 0
     * @return array of names: $orders_statuses[$gStatus->orders_status_groups_name][$status->orders_status_id] = $status->orders_status_name;
     */
    public static function get_status_list($with_all = false, $wou_automated = false, $include_status = 0)
    {
        $orders_statuses = [];
        if ($with_all) {
            $orders_statuses[''] = TEXT_ALL_ORDERS_STATUS;
        }
        foreach (self::get_statuses($wou_automated, $include_status) as $g_status) {
            $orders_statuses[$g_status->orders_status_groups_name] = [];
            foreach ($g_status->statuses as $status) {
                $orders_statuses[$g_status->orders_status_groups_name][$status->orders_status_id] = $status->orders_status_name;
            }
        }
        return $orders_statuses;
    }
    /**
     * uses cache.
     * @staticvar array $cache
     * @param type $wouAutomated
     * @param type $includeStatus
     * @return array of objects [group->statuses]
     */
    public static function get_statuses($wou_automated = false, $include_status = 0)
    {
        static $cache = [];
        if (!isset($cache[$wou_automated . $include_status])) {
            $q = \common\models\Orders_Status_Groups::find()->alias('osg')->where(['osg.language_id' => \Yii::$app->settings->get('languages_id'), 'osg.orders_status_type_id' => self::get_status_type_id()])->join_with(['statuses' => function (\yii\db\Active_Query $query) use ($wou_automated, $include_status) {
                $condition = [];
                if ($wou_automated) {
                    $condition[] = ['automated' => 0];
                    if ($include_status) {
                        $condition[] = ['orders_status_id' => $include_status];
                    }
                }
                if ($condition) {
                    array_unshift($condition, 'or');
                    $query->or_on_condition($condition);
                }
                $query->and_on_condition(['hidden' => 0]);
                $query->add_order_by('orders_status_name');
            }]);
            $table = \Yii::$app->db->schema->get_table_schema('orders_status_groups');
            if (isset($table->columns['sort_order'])) {
                $q->add_order_by('sort_order');
            }
            $cache[$wou_automated . $include_status] = $q->add_order_by(['orders_status_groups_id' => SORT_ASC])->all();
        }
        return $cache[$wou_automated . $include_status];
    }
    /**
     * checks whether the status exists (in correct group type)
     * @param int $statusId
     * @return bool
     */
    public static function is_status_exist($status_id)
    {
        return \common\models\Orders_Status::find()->alias('os')->where(['os.orders_status_id' => $status_id])->join('inner join', \common\models\Orders_Status_Groups::table_name() . ' osg', 'osg.orders_status_groups_id=os.orders_status_groups_id')->and_where(['osg.orders_status_type_id' => self::get_status_type_id()])->count() > 0;
    }
}