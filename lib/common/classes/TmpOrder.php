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

class Tmp_Order extends \common\classes\extended\Order_Abstract
{
    public $table_prefix = 'tmp_';
    public function create_order()
    {
        global $cart;
        $t_model_query = $this->get_ar_model()->where(['orders_id' => $this->order_id]);
        if (!$t_model_query->exists()) {
            return false;
        }
        $t_model = $t_model_query->one();
        if ($t_model->child_id > 0) {
            return false;
        }
        if (!is_object($cart)) {
            $cart = new \common\classes\shopping_cart();
        }
        if (!$this->manager->has_cart()) {
            $this->manager->load_cart($cart);
        }
        if ($this->manager->is_instance()) {
            $order = $this->manager->get_order_instance();
        } else {
            $order = $this->manager->create_order_instance('\common\classes\Order');
        }
        $order->info = $this->info;
        $order->info['order_number'] = '';
        $order->totals = $this->totals;
        $order->products = $this->products;
        $order->customer = $this->customer;
        $order->delivery = $this->delivery;
        $order->billing = $this->billing;
        $order->content_type = $this->content_type;
        $order->tax_address = $this->tax_address;
        ///all online pre-auth marked as paid  $order->update_piad_information(); set in module itself if required.
        if ($order->content_type != 'virtual') {
            $order->with_delivery = true;
        }
        $insert_id = $order->save_order();
        $order->save_details();
        $order->info['order_number'] = $insert_id;
        $order->info['orders_id'] = $insert_id;
        $order->order_id = $insert_id;
        $order->save_products();
        $t_model->child_id = $insert_id;
        $t_model->save(false);
        $this->set_parent($insert_id);
        return $insert_id;
    }
    public static function get_ar_model($new = false)
    {
        if ($new) {
            return parent::get_ar_model_new(new \common\models\Tmp_Orders());
        } else {
            return \common\models\Tmp_Orders::find();
        }
    }
    public function get_products_ar_model()
    {
        return \common\models\Tmp_Orders_Products::find();
    }
    public function get_status_history_ar_model()
    {
        return \common\models\Tmp_Orders_Status_History::find()->order_by('date_added, orders_status_history_id');
    }
    public function get_history_ar_model()
    {
        return \common\models\Tmp_Orders_History::find();
    }
}