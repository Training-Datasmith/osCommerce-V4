<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\helpers;

use common\models\Ecommerce_Tracking;
/**
 * EcommerceTracking function for GA e commerce tracking
 *
 * @author vlad
 */
class Ecommerce_Tracking_Helper
{
    public static function import($date = '')
    {
        if (empty($date)) {
            $date_start = strtotime('2 day ago');
        } else {
            $date_start = strtotime($date);
        }
        $year_ago = strtotime('1 year ago');
        if ($date_start < $year_ago) {
            $date_start = $year_ago;
        }
        $gess = new \common\components\google\Google_Ecommerce_Ss();
        foreach (\common\classes\platform::get_list(false) as $platform) {
            $gess->set_platform_id($platform['id']);
            $orders = $gess->get_transactions_report([date('Y-m-d', $date_start)]);
            if (is_array($orders)) {
                $oids = Orders::find()->select('orders_id')->where(['orders_id' => array_map('intval', array_keys($orders))])->as_array()->column();
                foreach ($orders as $orders_id => $order) {
                    if (in_array($orders_id, $oids)) {
                        \common\helpers\Ecommerce_Tracking::save_et($orders_id, $order);
                    }
                }
            }
        }
    }
    /**
     *
     * @param int $orders_id
     * @param array $order GA metrics
     */
    public static function save_et($orders_id, $order)
    {
        $et = Ecommerce_Tracking::find_one(['orders_id' => $orders_id]);
        if ($et) {
            Ecommerce_Tracking::update_all(['verified' => new \yii\db\Expression('now()'), 'verified_amount' => (float) $order['ga:transactionRevenue']], ['orders_id' => $orders_id]);
        } else {
            $et = new Ecommerce_Tracking();
            $et->set_attributes(['orders_id' => (int) $orders_id, 'via' => 'report', 'date_added' => new \yii\db\Expression('now()'), 'verified' => new \yii\db\Expression('now()'), 'verified_amount' => (float) $order['ga:transactionRevenue']], false);
            $et->save(false);
        }
    }
    public static function update_stat($oid = false)
    {
        if ($oid) {
            $order_only = new \common\classes\Order($oid);
            $date_start = strtotime($order_only->info['date_purchased']);
        } else {
            $q = \common\models\Ecommerce_Tracking::find()->select('date_added')->where(['is', 'verified', null])->order_by('date_added')->limit(1)->as_array()->one();
            if ($q) {
                $date_start = strtotime($q['date_added']);
            }
        }
        $year_ago = strtotime('1 year ago');
        if ($date_start < $year_ago) {
            $date_start = $year_ago;
        }
        $gess = new \common\components\google\Google_Ecommerce_Ss();
        //2do add for all platforms
        foreach (\common\classes\platform::get_list(false) as $platform) {
            if ($order_only && $platform['id'] != $order_only->info['platform_id']) {
                continue;
            }
            $gess->set_platform_id($platform['id']);
            $orders = $gess->get_transactions_report([date('Y-m-d', $date_start)]);
            if (is_array($orders)) {
                foreach ($orders as $orders_id => $order) {
                    if ($order_only && $orders_id != $oid) {
                        //do not update other as there coud be incorrect data (total)
                        continue;
                    }
                    Ecommerce_Tracking::update_all(['verified' => new \yii\db\Expression('now()'), 'verified_amount' => (float) $order['ga:transactionRevenue']], ['orders_id' => $orders_id]);
                }
            }
        }
    }
}