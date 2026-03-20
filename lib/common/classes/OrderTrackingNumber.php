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
namespace common\classes;

use common\models\Tracking_Numbers;
use common\models\Tracking_Numbers_To_Orders_Products;
class Order_Tracking_Number extends Tracking_Numbers
{
    public $tracking_url;
    public $number;
    public $carrier;
    public $products = [];
    public $products_quantity = 0;
    protected $modified_products = false;
    public function getproducts_quantity()
    {
        return array_sum($this->products);
    }
    public static function instance_from_string($tracking, $order_id)
    {
        $obj = new self();
        $obj->orders_id = $order_id;
        $obj->tracking_number = trim($tracking);
        $obj->data_loaded();
        return $obj;
    }
    public function data_loaded()
    {
        $parsed = \common\helpers\Order::parse_tracking_number($this->tracking_number);
        $this->number = $parsed['number'];
        $this->tracking_url = $parsed['url'];
        if (!empty($parsed['carrier'])) {
            $this->carrier = $parsed['carrier'];
            /** @var \common\extensions\TrackingCarriers\TrackingCarriers $ext */
            if ($tracking_carriers_id = \common\helpers\Extensions::call_if_allowed('TrackingCarriers', 'getTrackingCarriersId', [$this->carrier])) {
                $this->tracking_carriers_id = $tracking_carriers_id;
            } else {
                $this->tracking_carriers_id = 0;
            }
        }
        if (empty($this->tracking_url)) {
            /** @var \common\extensions\TrackingCarriers\TrackingCarriers $ext */
            if ($record = \common\helpers\Extensions::call_if_allowed('TrackingCarriers', 'getTrackingCarriersRecord', [$this->tracking_carriers_id])) {
                $this->carrier = $record->tracking_carriers_name;
                $this->tracking_url = $record->tracking_carriers_url . $this->number;
            } else {
                $this->carrier = '';
                $this->tracking_url = TRACKING_NUMBER_URL . str_replace(' ', '', $this->number);
            }
        }
    }
    public static function get_tracking_from_table($order_id)
    {
        $tracking_table = static::find()->where(['orders_id' => $order_id])->order_by(['tracking_numbers_id' => SORT_ASC])->all();
        foreach ($tracking_table as $tracking) {
            /**
             * @var $tracking OrderTrackingNumber
             */
            $tracking->data_loaded();
            foreach (Tracking_Numbers_To_Orders_Products::find()->where(['tracking_numbers_id' => $tracking->tracking_numbers_id])->and_where(['orders_id' => $tracking->orders_id])->order_by(['orders_products_id' => SORT_ASC])->all() as $order_product_track) {
                $tracking->products[$order_product_track->orders_products_id] = $order_product_track->products_quantity;
            }
        }
        return $tracking_table;
    }
    public function is_products_modified()
    {
        return $this->modified_products;
    }
    public function set_order_products($products)
    {
        if (is_array($products)) {
            $this->products = $products;
            $this->modified_products = true;
            //TODO: need data check for modified array
        }
    }
    public function save_products()
    {
        $process_products = $this->products;
        $current_collection = Tracking_Numbers_To_Orders_Products::find()->where(['tracking_numbers_id' => $this->tracking_numbers_id])->and_where(['orders_id' => $this->orders_id])->all();
        foreach ($current_collection as $current_product) {
            if (isset($process_products[$current_product->orders_products_id])) {
                $qty_delta = (int) $process_products[$current_product->orders_products_id] - (int) $current_product->products_quantity;
                if ($qty_delta > 0) {
                    if (\common\helpers\Order_Product::do_dispatch_specific($current_product->orders_products_id, $qty_delta) == true and $qty_delta > 0) {
                        $current_product->products_quantity += $qty_delta;
                        try {
                            $current_product->save();
                        } catch (\Exception $exc) {
                        }
                    }
                }
                unset($process_products[$current_product->orders_products_id]);
            }
        }
        foreach ($process_products as $orders_products_id => $products_quantity) {
            $obj = new Tracking_Numbers_To_Orders_Products();
            $obj->tracking_numbers_id = $this->tracking_numbers_id;
            $obj->orders_id = $this->orders_id;
            $obj->orders_products_id = $orders_products_id;
            if (\common\helpers\Order_Product::do_dispatch_specific($orders_products_id, $products_quantity) == true and $products_quantity > 0) {
                $obj->products_quantity = $products_quantity;
                try {
                    $obj->save(false);
                } catch (\Exception $exc) {
                }
            }
        }
        \common\helpers\Order::evaluate($this->orders_id);
        $this->modified_products = false;
    }
    public function after_refresh()
    {
        parent::after_refresh();
        $this->data_loaded();
    }
    public function __toString()
    {
        if (!empty($this->carrier)) {
            return (string) $this->carrier . ',' . (string) $this->number;
        }
        return (string) $this->number;
    }
}