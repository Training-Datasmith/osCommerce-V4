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

use common\services\Splitter_Manager;
class Splinter extends Order
{
    public $modified;
    public function __construct($order_id = null)
    {
        $this->modified = false;
    }
    public $splitter;
    public function set_splitter(\common\services\Splitter_Manager $splitter_manager)
    {
        $this->splitter = $splitter_manager;
    }
    public function get_splitter()
    {
        return $this->splitter;
    }
    public function create_product($order_id, $uprid, $qty, $price_ex, $price_in, $data = [])
    {
        if (is_array($data) && count($data)) {
            $product = $data;
        } else {
            $orders_product = $this->get_products_ar_model()->select(['*', 'if(length(uprid),uprid, products_id) as products_id'])->where(['orders_id' => $order_id, 'template_uprid' => $uprid])->as_array()->order_by('sort_order, orders_products_id')->one();
            if (!$orders_product) {
                $name = \common\helpers\Inventory::get_inventory_name_by_uprid(\common\helpers\Inventory::normalize_id($uprid));
                if (empty($name)) {
                    $name = \common\helpers\Product::get_products_name(intval($uprid));
                }
                $orders_product = ['products_name' => $name, 'products_model' => \common\helpers\Product::get_products_info($uprid, 'products_model'), 'sort_order' => 0];
            }
            $product = ['qty' => $qty ? $qty : 1, 'id' => \common\helpers\Inventory::normalize_id($uprid), 'name' => $orders_product['products_name'], 'model' => $orders_product['products_model'], 'tax' => ($price_in / ($price_ex ? $price_ex : 1) - 1) * 100, 'final_price' => $price_ex, 'template_uprid' => $uprid, 'sort_order' => $orders_product['sort_order']];
        }
        $this->products[] = $product;
    }
    public function query($order_id)
    {
    }
    public static function get_ar_model($new = false)
    {
        //return \common\models\Orders::find();
    }
    public function get_status_history_ar_model()
    {
        //return \common\models\OrdersStatusHistory::find();
    }
    public function get_history_ar_model()
    {
        //return \common\models\OrdersHistory::find();
    }
    public function remove_order($restock = false)
    {
        return false;
    }
    public function get_parent()
    {
        return false;
    }
    public function has_transactions()
    {
        return false;
    }
    public $status;
    public function is_invoice()
    {
        return $this->status == Splitter_Manager::STATUS_PAYED && $this->order_id;
    }
    public function is_credit_note()
    {
        return $this->status == Splitter_Manager::STATUS_RETURNED && $this->order_id;
    }
    protected $splinters = [];
    /*@params $splinters - rows for creating splinter instance */
    public function set_splinters(array $splinters)
    {
        $this->splinters = $splinters;
    }
    public function get_splinters()
    {
        return $this->splinters;
    }
    public $mixed = [];
    public function create_mixed_type($owner, $amount, $data)
    {
        $this->mixed[] = ['qty' => 1, 'value_exc_vat' => $amount, 'value_inc_tax' => $amount, 'owner' => $owner, 'data' => $data];
    }
    public function get_returning_credit_note_amount()
    {
        $amount = 0;
        if ($this->is_credit_note()) {
        }
        return $amount;
    }
}