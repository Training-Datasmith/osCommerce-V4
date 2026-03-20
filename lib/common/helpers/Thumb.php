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

use Yii;
class Thumb
{
    public static function set_product_pagination(\yii\web\Session $storage, $view, $current_products_id)
    {
        $view->product_next = $view->product_prev = 0;
        if ($storage->has('products_query_raw')) {
            $products_query_raw_real = $storage->get('products_query_raw');
            if (strpos($products_query_raw_real, 'limit')) {
                $products_query_raw_real = preg_replace('/(.*)limit(.*)/', '$1 limit 100', $products_query_raw_real);
            }
            $products_query_raw_real = preg_replace("/\\*/", 'p.products_id', $products_query_raw_real);
            $products_query_raw_current = "select (@i:=@i+1) as iter, cur.products_id from ({$products_query_raw_real}) cur";
            $view->product_prev = $view->product_next = 0;
            Yii::$app->db->create_command('set @i:=-1;')->execute();
            $all_prods = \yii\helpers\Array_Helper::map(Yii::$app->db->create_command($products_query_raw_current)->query_all(), 'iter', 'products_id');
            $all_prods_rev = array_flip($all_prods);
            $all_prods_iter = new \ArrayIterator($all_prods);
            $cur_key = $all_prods_rev[(int) $current_products_id] ?? null;
            try {
                $all_prods_iter->seek($cur_key);
                if ($all_prods_iter->offsetExists($cur_key - 1)) {
                    $all_prods_iter->seek($cur_key - 1);
                    $view->product_prev = $all_prods_iter->current();
                }
                $all_prods_iter->seek($cur_key);
                if ($all_prods_iter->offsetExists($cur_key + 1)) {
                    $all_prods_iter->seek($cur_key + 1);
                    $view->product_next = $all_prods_iter->current();
                }
            } catch (\Exception $ex) {
                Yii::info('Incorrect product thumbing', 'thumb');
            }
            if ($view->product_next) {
                $view->product_next_name = \common\helpers\Product::get_backend_products_name($view->product_next);
            }
            if ($view->product_prev) {
                $view->product_prev_name = \common\helpers\Product::get_backend_products_name($view->product_prev);
            }
        }
    }
}