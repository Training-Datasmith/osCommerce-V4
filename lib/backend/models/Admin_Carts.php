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
namespace backend\models;

use common\models\Admin_Shopping_Carts;
use Yii;
class Admin_Carts extends Admin
{
    protected $info;
    private $carts = [];
    private $current_cart = null;
    public $new_cart_created = false;
    public function __construct($id = 0)
    {
        parent::__construct($id);
    }
    public function load_customers_baskets_short($type = 'cart')
    {
        return $this->load_customers_baskets($type, false);
    }
    public function create_cart($instance_type, $order, $basket_id, $customers_id = null)
    {
        try {
            $orders_id = $order->orders_id ?? null;
            $cart = new $instance_type($orders_id);
            if ($orders_id) {
                $cart_type = $this->get_cart_type($cart);
                $a_cart = Admin_Shopping_Carts::find()->where(['admin_id' => $this->info['admin_id'], 'cart_type' => $cart_type, 'order_id' => $orders_id])->one();
                if ($a_cart) {
                    $index = $cart_type . '|' . (int) $a_cart->customers_id . '-' . (int) $a_cart->basket_id;
                    $cart = $this->get_cart_by_id($index);
                    if ($cart) {
                        if (!$cart->admin_id) {
                            $cart->set_admin($this->info['admin_id']);
                        }
                        $this->set_current_cart_id($index, $orders_id ? false : true);
                        return $cart;
                    }
                }
            }
            if ($order) {
                $customer = $order->get_customer()->one();
                if ($customer) {
                    $cart->set_customer($customer->customers_id);
                }
            } elseif (!empty($customers_id) && !empty(\common\models\Customers::find_one($customers_id))) {
                $cart->set_customer($customers_id);
            }
            $cart->set_basket_id($basket_id);
            $cart->set_admin($this->info['admin_id']);
            $index = $this->get_cart_type($cart) . '|' . (int) $cart->customer_id . '-' . (int) $cart->basket_id;
            $this->carts[$index] = ['customers_id' => $cart->customers_id ?? null, 'basket_id' => $cart->basket_id ?? null, 'order_id' => (int) $orders_id, 'updated_at' => '', 'cart_details' => $cart, 'checkout_details' => '', 'status' => 1];
            $this->set_current_cart_id($index, $orders_id ? false : true);
            if ($basket_id != $cart->basket_id) {
                $this->new_cart_created = true;
            }
        } catch (Exception $ex) {
            throw new \Exception('incorrent class instance');
        }
        return $cart;
    }
    private function _get_cart_by_id($cart_id)
    {
        if ($details = \common\helpers\Cart::decode_id($cart_id)) {
            $details['admin_id'] = $this->info['admin_id'];
            return Admin_Shopping_Carts::find()->where($details)->one();
        }
        return false;
    }
    public function get_cart_by_id($cart_id)
    {
        if (isset($this->carts[$cart_id])) {
            if ($this->carts[$cart_id]['cart_details']) {
                return $this->carts[$cart_id]['cart_details'];
            }
        }
        $cart = $this->_get_cart_by_id($cart_id);
        if ($cart) {
            $this->carts[$cart_id] = ['customers_id' => $cart->customers_id, 'basket_id' => $cart->basket_id, 'order_id' => $cart->order_id, 'updated_at' => $cart->updated_at, 'cart_details' => unserialize(base64_decode($cart->customer_basket)), 'checkout_details' => unserialize(base64_decode($cart->checkout_details)), 'status' => $cart->status];
            return $this->carts[$cart_id]['cart_details'];
        }
        return false;
    }
    public function has_checkout_details()
    {
        return isset($this->carts[$this->current_cart]['checkout_details']) && !empty($this->carts[$this->current_cart]['checkout_details']);
    }
    public function get_checkout_details()
    {
        return $this->carts[$this->current_cart]['checkout_details'];
    }
    public function load_customers_baskets($type = 'cart', $full = true, $super_user_platform_id = false)
    {
        $this->carts = [];
        $carts_query = Admin_Shopping_Carts::find()->where(['cart_type' => $type])->order_by('updated_at DESC');
        if (!empty($super_user_platform_id)) {
            $carts_query->and_where(['platform_id' => $super_user_platform_id]);
        } else {
            $carts_query->and_where(['admin_id' => $this->info['admin_id']]);
        }
        foreach ($carts_query->all() as $cart) {
            $this->carts[$type . '|' . $cart->customers_id . '-' . $cart->basket_id] = ['admin_id' => $cart->admin_id, 'my_id' => $this->info['admin_id'], 'platform_id' => $cart->platform_id, 'customers_id' => $cart->customers_id, 'basket_id' => $cart->basket_id, 'order_id' => $cart->order_id, 'updated_at' => $cart->updated_at, 'cart_details' => [], 'checkout_details' => [], 'status' => $cart->status];
            if ($full) {
                $this->carts[$type . '|' . $cart->customers_id . '-' . $cart->basket_id]['cart_details'] = unserialize(base64_decode($cart->customer_basket));
                $this->carts[$type . '|' . $cart->customers_id . '-' . $cart->basket_id]['checkout_details'] = unserialize(base64_decode($cart->checkout_details));
            }
        }
        return $this;
    }
    public function check_cart_owner_clear($cart)
    {
        $check = Admin_Shopping_Carts::find()->where(['and', ['<>', 'admin_id', $this->info['admin_id']], ['customers_id' => $cart->customer_id ?? null], ['order_id' => $cart->order_id ?? null], ['cart_type' => $this->get_cart_type($cart)]])->one();
        if ($check && $check->status) {
            return false;
        }
        return true;
    }
    public function get_cart_type($cart)
    {
        $cart_prefix = $cart->table_prefix ?? null;
        if ($cart_prefix == 'sample_') {
            return 'sample';
        } elseif ($cart_prefix == 'quote_') {
            return 'quote';
        } else {
            return 'cart';
        }
    }
    public function update_customers_basket($cart)
    {
        if (!isset($this->carts[$cart->customer_id . '-' . $cart->basket_id])) {
            if (!$this->check_cart_owner_clear($cart)) {
                return false;
            }
            $this->save_customer_basket($cart);
            $this->set_current_cart_id($cart->customer_id . '-' . $cart->basket_id);
        } else {
            $this->set_current_cart_id($cart->customer_id . '-' . $cart->basket_id);
        }
    }
    public function save_customer_basket($cart)
    {
        $ad_cart = $this->find_cart($cart);
        $ad_cart->customer_basket = base64_encode(serialize($cart));
        $ad_cart->status = 1;
        $ad_cart->order_id = (int) $cart->order_id;
        return $ad_cart->save(false);
    }
    public function save_checkout_details($cart, \common\services\storages\Storage_Interface $storage)
    {
        $ad_cart = $this->_get_cart_by_id($this->get_current_cart_id());
        if (!$ad_cart) {
            $ad_cart = Admin_Shopping_Carts::find()->where(['admin_id' => $this->info['admin_id'], 'basket_id' => $cart->basket_id, 'customers_id' => (int) $cart->customer_id])->one();
        }
        if (!$ad_cart) {
            $ad_cart = new Admin_Shopping_Carts();
            $ad_cart->admin_id = $this->info['admin_id'];
            $ad_cart->platform_id = (int) $cart->platform_id;
            $ad_cart->customers_id = (int) $cart->customer_id;
            $ad_cart->basket_id = $cart->basket_id;
            $ad_cart->cart_type = $this->get_cart_type($cart);
        }
        if ($ad_cart) {
            $data = $storage->get_all();
            unset($data['cart']);
            $ad_cart->checkout_details = base64_encode(serialize($data));
            $ad_cart->status = 1;
            $ad_cart->customers_id = (int) $cart->customer_id;
            $ad_cart->order_id = (int) $cart->order_id;
            $index = $this->get_cart_type($cart) . '|' . (int) $cart->customer_id . '-' . (int) $cart->basket_id;
            $this->set_current_cart_id($index);
            if ($ad_cart->save(false)) {
                return $this->save_customer_basket($cart);
            }
        }
        return false;
    }
    private function find_cart($cart)
    {
        $ad_cart = Admin_Shopping_Carts::find()->where(['admin_id' => $this->info['admin_id'], 'customers_id' => (int) $cart->customer_id, 'basket_id' => $cart->basket_id, 'cart_type' => $this->get_cart_type($cart)])->one();
        if (!$ad_cart) {
            $ad_cart = new Admin_Shopping_Carts();
            $ad_cart->admin_id = $this->info['admin_id'];
            $ad_cart->platform_id = (int) $cart->platform_id;
            $ad_cart->customers_id = (int) $cart->customer_id;
            $ad_cart->basket_id = $cart->basket_id;
            $ad_cart->cart_type = $this->get_cart_type($cart);
        }
        return $ad_cart;
    }
    public function remove_cart($cart_id)
    {
        $ad_cart = $this->_get_cart_by_id($cart_id);
        if ($ad_cart) {
            $ad_cart->delete();
        }
    }
    public function set_current_cart_id($cart_id, $is_virtual = false)
    {
        $this->current_cart = $cart_id;
        if ($is_virtual) {
            $this->set_last_virtual_id($cart_id);
        }
    }
    public function set_last_virtual_id($cart_id)
    {
        Yii::$app->session->set('lastVirtual', $cart_id);
    }
    public function get_last_virtual_id($set_main = false)
    {
        if ($set_main) {
            $this->current_cart = Yii::$app->session->get('lastVirtual');
        }
        return Yii::$app->session->get('lastVirtual');
    }
    public function get_current_cart_id()
    {
        return $this->current_cart;
    }
    public function get_virtual_cart_i_ds()
    {
        $ids = [];
        if (is_array($this->carts)) {
            $ids = array_keys($this->carts);
            //foreach ($this->carts as $_id => $_cart) {
            //    $ids[] = $_id;
            /* if (!$_cart['order_id'] || $_cart['order_id'] <= 0) {
               $ids[] = $_id;
               } */
            //}
            return count($ids) ? $ids : false;
        }
        return false;
    }
    public function load_current_cart()
    {
        /* global $cart, $quote, $payment, $shipping, $select_shipping, $adress_details, $sendto, $billto, $cot_gv, $cc_id;
        
                  if (!is_null($this->currentCart)) {
                  if (is_array($this->carts[$this->currentCart]['cart_details'])) {
                  foreach ($this->carts[$this->currentCart]['cart_details'] as $item => $value) {
                  if (!tep_session_is_registered($item))
                  tep_session_register($item);
                  unset($GLOBALS[$item]);
                  $_SESSION[$item] = $value;
                  $GLOBALS[$item] = &$_SESSION[$item];
                  }
                  }
                  } */
    }
    public function get_admin_by_cart($cart)
    {
        $name = '';
        $type = $this->get_cart_type($cart);
        $admin = tep_db_fetch_array(tep_db_query('select admin_id from ' . TABLE_ADMIN_SHOPPING_CARTS . " where customers_id ='" . (int) $cart->customer_id . "' and order_id = '" . (int) $cart->order_id . "' and cart_type='{$type}'"));
        if ($admin) {
            $_admin = new Admin($admin['admin_id']);
            $name = $_admin->get_info('admin_firstname') . ' ' . $_admin->get_info('admin_lastname');
        }
        return $name;
    }
    public function relocate_cart($basket_id, $customer_id, $type = 'cart')
    {
        if ($basket_id && $customer_id) {
            tep_db_query('update ' . TABLE_ADMIN_SHOPPING_CARTS . " set admin_id = '" . (int) $this->info['admin_id'] . "' where customers_id ='" . (int) $customer_id . "' and basket_id = '" . (int) $basket_id . "' and cart_type = '{$type}'");
        }
    }
    public function reassign_me($basket_id, $customer_id, $type = 'cart')
    {
        tep_db_query('update ' . TABLE_ADMIN_SHOPPING_CARTS . " set admin_id = '" . (int) $this->info['admin_id'] . "' where customers_id ='" . (int) $customer_id . "' and basket_id = '" . (int) $basket_id . "' and cart_type = '{$type}'");
    }
    public function reassign_cart($cart)
    {
        if ($cart) {
            tep_db_query('update ' . TABLE_ADMIN_SHOPPING_CARTS . " set admin_id = '" . (int) $this->info['admin_id'] . "' where customers_id ='" . (int) $cart->customer_id . "' and order_id = '" . (int) $cart->order_id . "' and cart_type = '{$this->get_cart_type($cart)}'");
            return true;
        }
        return false;
    }
    public function delete_cart_by_order($orders_id)
    {
        tep_db_query('delete from ' . TABLE_ADMIN_SHOPPING_CARTS . " where order_id = '" . (int) $orders_id . "'");
    }
    public function delete_cart_by_bc($customer_id, $basket_id)
    {
        tep_db_query('delete from ' . TABLE_ADMIN_SHOPPING_CARTS . " where basket_id = '" . (int) $basket_id . "' and customers_id = '" . (int) $customer_id . "'");
        $this->load_customers_baskets();
        return true;
    }
    public function get_carts()
    {
        return $this->carts;
    }
    public function is_cart_saved($cart_id)
    {
        if (preg_match("/(.*)\\|([\\d]*)\\-([\\d]*)/", $cart_id, $mas)) {
            if (is_array($mas) && isset($mas[1])) {
                $cart = Admin_Shopping_Carts::find()->where(['cart_type' => $mas[1], 'customers_id' => $mas[2], 'basket_id' => $mas[3]])->one();
                if ($cart) {
                    return true;
                }
            }
        }
        return false;
    }
}