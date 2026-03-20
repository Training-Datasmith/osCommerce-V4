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
namespace backend\widgets;

use yii\base\Widget;
class Products extends Widget
{
    public $stats = [];
    public function run()
    {
        $q = \common\models\Products::find()->select('is_listing_product, is_bundle, products_status')->add_select(['isChild' => new \yii\db\Expression('parent_products_id>0'), 'total' => new \yii\db\Expression('count(*)')])->group_by('is_listing_product, is_bundle, products_status, isChild');
        $d = $q->as_array()->all();
        $ap = array_filter($d, function ($e) {
            return $e['products_status'];
        });
        $ip = array_filter($d, function ($e) {
            return !$e['products_status'];
        });
        $p_data = [];
        foreach (['bundle' => 'is_bundle', 'listing' => 'is_listing_product', 'master' => '!is_listing_product', 'child' => 'isChild'] as $key => $value) {
            if (substr($value, 0, 1) == '!') {
                $v = 0;
                $value = substr($value, 1);
            } else {
                $v = 1;
            }
            $p_data[$key]['active'] = array_sum(\yii\helpers\Array_Helper::get_column(array_filter($ap, function ($e) use ($v, $value) {
                return $e[$value] == $v;
            }), 'total'));
            $p_data[$key]['inactive'] = array_sum(\yii\helpers\Array_Helper::get_column(array_filter($ip, function ($e) use ($v, $value) {
                return $e[$value] == $v;
            }), 'total'));
        }
        $this->stats['pData'] = $p_data;
        $manufacturers = tep_db_fetch_array(tep_db_query('select count(*) as count from ' . TABLE_MANUFACTURERS . ' where 1'));
        $this->stats['manufacturers'] = number_format($manufacturers['count']);
        $reviews_confirmed = tep_db_fetch_array(tep_db_query('select count(*) as count from ' . TABLE_REVIEWS . " where status = '1'"));
        $this->stats['reviews_confirmed'] = number_format($reviews_confirmed['count']);
        $reviews_to_confirm = tep_db_fetch_array(tep_db_query('select count(*) as count from ' . TABLE_REVIEWS . " where status = '0'"));
        $this->stats['reviews_to_confirm'] = number_format($reviews_to_confirm['count']);
        return $this->render('Products.tpl', ['stats' => $this->stats]);
    }
}