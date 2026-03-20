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
namespace backend\controllers;

//use backend\models\EP\DataSources;
use backend\components\Location_Search_Trait;
use backend\design\editor\Formatter;
use backend\models\Admin_Carts;
use common\classes\platform;
use common\classes\platform_config;
use common\components\Customer;
use common\helpers\Acl;
use common\helpers\Status;
use common\models\Address_Book;
use Yii;
use yii\helpers\Array_Helper;
use yii\helpers\Url;
/**
 * default controller to handle user requests.
 */
class Editor_Controller extends Sceleton
{
    use Location_Search_Trait;
    public $acl = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS'];
    /**
     * Index action is the default action in a controller.
     */
    /** @prop \common\services\OrderManager $manager */
    public $manager;
    /** @prop \common\classes\Currencies $currencies */
    public $currencies;
    /** @prop \backend\models\AdminCarts $admin */
    public $admin;
    protected $storage;
    public function __construct($id, $module = '')
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
            $ext::check_customer_groups();
        }
        defined('GROUPS_IS_SHOW_PRICE') or define('GROUPS_IS_SHOW_PRICE', true);
        defined('GROUPS_DISABLE_CHECKOUT') or define('GROUPS_DISABLE_CHECKOUT', false);
        defined('GROUPS_DISABLE_CART') or define('GROUPS_DISABLE_CART', false);
        defined('SHOW_OUT_OF_STOCK') or define('SHOW_OUT_OF_STOCK', 1);
        \common\helpers\Translation::init('ordertotal');
        \common\helpers\Translation::init('admin/orders');
        \common\helpers\Translation::init('admin/orders/create');
        \common\helpers\Translation::init('admin/orders/order-edit');
        $this->storage = Yii::$app->get('storage');
        $this->manager = new \common\services\Order_Manager($this->storage);
        $this->manager->set_modules_visibility(['admin']);
        $this->manager->combine_shippings = true;
        $this->currencies = Yii::$container->get('currencies');
        $this->admin = new Admin_Carts();
        parent::__construct($id, $module);
        $this->page_settings();
        $this->manager->set_render_path('\backend\design\editor\\');
    }
    protected function check_order_owner($cart)
    {
        if (!$this->admin->check_cart_owner_clear($cart)) {
            header('HTTP/1.0 406 Not Acceptable');
            die;
        }
    }
    protected function add_log($comment)
    {
        global $login_id;
        $log = $this->storage->has('log') ? $this->storage->get('log') : [];
        $log = is_array($log) ? $log : [];
        $log[] = ['comment' => $comment, 'admin_id' => $login_id];
        $this->storage->set('log', $log);
    }
    protected function save_log()
    {
        $order = $this->manager->get_order_instance();
        if ($order && $order->order_id) {
            $log = $this->storage->has('log') ? $this->storage->get('log') : [];
            foreach ($log as $row) {
                $order->add_legend($row['comment'], $row['admin_id']);
            }
            $this->storage->remove('log');
        }
    }
    protected function get_pi_name($uprid)
    {
        if (\common\helpers\Inventory::is_inventory($uprid)) {
            $name = \common\helpers\Inventory::get_inventory_name_by_uprid($uprid);
        } else {
            $name = \common\helpers\Product::get_products_name($uprid);
        }
        return $name;
    }
    public function page_settings()
    {
        $this->top_buttons[] = '';
        $this->view->heading_title = HEADING_TITLE;
        if (isset($_GET['new'])) {
            $this->view->new_order = true;
        } else {
            $this->view->new_order = false;
        }
        $this->view->back_option_true = \Yii::$app->request->get('back');
        if (isset($_GET['back'])) {
            $this->view->back_option = $_GET['back'];
        } else {
            $this->view->back_option = 'orders';
        }
        $this->selected_menu = ['customers', 'orders'];
    }
    public function obtain_customer_cart($cart_insatnce_type, \yii\db\Active_Record $order = null, $current_cart = '', $customers_id = null)
    {
        if (tep_not_null($current_cart)) {
            $cart = $this->admin->get_cart_by_id($current_cart);
            if (!$cart) {
                if ($details = \common\helpers\Cart::decode_id($current_cart)) {
                    $customers_id = $details['customers_id'] != 0 ? $details['customers_id'] : $customers_id;
                    $cart = $this->admin->create_cart($cart_insatnce_type, $order, $details['basket_id'], $customers_id);
                }
            } else {
                $this->admin->set_current_cart_id($current_cart, $order->orders_id ?? null ? false : true);
            }
        } else {
            $cart = $this->admin->create_cart($cart_insatnce_type, $order, 0, $customers_id);
        }
        return $cart;
    }
    //!!use only after current cart id is defined
    public function load_checkout_details($order_id = null)
    {
        //we have to know witch source have to be used as main
        //let use "checkout" as point to start
        //if checkout is not defined try to upload data from admin carts or order instance
        //remove "checkout" during cancelling data
        if (!$this->manager->has('checkout')) {
            $loadded = false;
            if ($this->admin->get_current_cart_id()) {
                if ($this->admin->has_checkout_details()) {
                    foreach ($this->admin->get_checkout_details() as $name => $value) {
                        if ($name == 'customer_id') {
                            $cid_exist = true;
                            $cart = $this->manager->get('cart');
                            if ($cart) {
                                $cart->set_customer($value);
                            }
                        }
                        $this->manager->set($name, $value);
                    }
                    if ($this->manager->has('customer')) {
                        $_customer = $this->manager->get('customer');
                        if ($_customer instanceof \common\components\Customer && $_customer->get('fromOrder')) {
                            $this->manager->remove('customer_id');
                            $cart = $this->manager->get('cart');
                            if ($cart) {
                                $cart->set_customer(0);
                            }
                        }
                    }
                    $loadded = true;
                }
            }
            if (!$loadded) {
                if (!is_null($order_id)) {
                    $this->manager->predefine_order_details();
                }
            }
            $this->manager->set('checkout', 1);
        }
    }
    public function load_platform_consts()
    {
        //ask platform/language/currency if not defined!!!!
        if (!$this->manager->has('platform_id')) {
            $this->manager->set('platform_id', \common\classes\platform::default_id());
        }
        $__platform = Yii::$app->get('platform');
        $platform_id = $this->manager->get_platform_id();
        $platform_config = $__platform->config($platform_id);
        if ($platform_config->is_virtual() || $platform_config->is_market_place()) {
            $_detected = false;
            if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
                if ($_plid = $ext::get_virtual_sattelit_id($platform_id)) {
                    $platform_config = $__platform->config($_plid);
                    $_detected = true;
                }
            }
            if (!$_detected) {
                $platform_config = $__platform->config(\common\classes\platform::default_id());
            }
        }
        $platform_config->constant_up();
        defined('PLATFORM_ID') or define('PLATFORM_ID', $platform_id);
        $theme_id = \common\models\Platforms_To_Themes::find_one(['platform_id' => $platform_id])->theme_id;
        $theme_name = \common\models\Themes::find_one(['id' => $theme_id])->theme_name;
        defined('THEME_NAME') or define('THEME_NAME', $theme_name);
    }
    public function action_cart_worker()
    {
        $currencies = \Yii::$container->get('currencies');
        $data = Yii::$app->request->post();
        $this->storage->set_pointer($data['currentCart']);
        //!!importnat to get current data
        /** @var \common\classes\shopping_cart $cart */
        $cart = $this->manager->get('cart');
        //working_cart
        $this->check_order_owner($cart);
        $response = [];
        if ($cart) {
            $this->load_platform_consts();
            $this->manager->load_cart($cart);
            $this->manager->create_order_instance($this->manager->get('order_instance'));
            $this->manager->define_order_tax_address();
            $cart->clear_total_key('ot_tax');
            $cart->clear_total_key('ot_gift_wrap');
            if (is_array($data['uprid'] ?? null)) {
                $uprid = $data['uprid'];
                foreach ($uprid as &$item) {
                    $item = urldecode($item);
                }
            } else {
                $uprid = urldecode($data['uprid'] ?? null);
            }
            if (isset($data['action'])) {
                switch ($data['action']) {
                    case 'change_qty':
                        $attributes = [];
                        $_uprid = \common\helpers\Inventory::normalize_id($uprid, $attributes);
                        //uprid will be modified for bundles
                        if (is_array($data['qty_'] ?? null)) {
                            $cart->clear_overwriten_key($uprid, 'final_price_formula');
                            $cart->clear_overwriten_key($uprid, 'final_price_formula_data');
                            $cart->clear_overwriten_key($uprid, 'final_price');
                            $pack_qty = ['unit' => (int) $data['qty_'][0], 'pack_unit' => (int) $data['qty_'][1], 'packaging' => (int) $data['qty_'][2]];
                            if ($ext = \common\helpers\Acl::check_extension_allowed('PackUnits', 'allowed')) {
                                $pack_qty['qty'] = $ext::recalc_qauntity(\common\helpers\Inventory::get_prid($_uprid), $pack_qty);
                            }
                        } else {
                            $pack_qty = $data['qty'];
                        }
                        if (strpos($uprid, '{tpl}') !== false) {
                            $cart->add_cart_cfg($uprid, $data['qty'], $attributes);
                        } else {
                            $cart->add_cart(\common\helpers\Inventory::get_prid($_uprid), $pack_qty, $attributes, false, 0, ($data['gift_wrap'] ?? null) == 'true');
                        }
                        $this->add_log($this->get_pi_name($_uprid) . ' changed qty to ' . (is_scalar($pack_qty) ? $pack_qty : $pack_qty['qty']));
                        break;
                    case 'remove_product':
                        if (!is_array($uprid)) {
                            $uprid = [$uprid];
                        }
                        foreach ($uprid as $pid) {
                            $cart->remove($pid);
                            $this->add_log($this->get_pi_name($pid) . ' removed ');
                        }
                        break;
                    case 'remove_giveaway':
                        $cart->remove_giveaway($uprid);
                        $this->add_log($this->get_pi_name($uprid) . ' removed as giveaway');
                        break;
                    case 'change_tax':
                        if (!is_null($uprid)) {
                            $insulator = new \backend\services\Product_Insulator_Service($uprid, $this->manager);
                            $insulator->set_data($data);
                            $insulator->set_product_tax($uprid);
                            $products = $cart->get_products($uprid);
                            $product = array_shift($products);
                            $this->add_log($this->get_pi_name($uprid) . ' tax changed to ' . $cart->get_owerwritten_key($uprid, 'tax_rate'));
                        }
                        break;
                    case 'extra_charge':
                    case 'change_price':
                        if (!is_null($uprid)) {
                            $insulator = new \backend\services\Product_Insulator_Service($uprid, $this->manager);
                            $insulator->set_data($data);
                            if ($data['action'] == 'change_price') {
                                $insulator->manual_price_changed = true;
                            }
                            $insulator->set_extra_charge();
                            $products = $cart->get_products($uprid);
                            $product = array_shift($products);
                            $this->add_log($product['name'] . ' manual price changed to ' . $product['final_price']);
                        }
                        break;
                    case 'reset_cart':
                        $this->manager->remove('cart');
                        //working_cart
                        return json_encode(['ok' => true]);
                        break;
                    case 'save_cart':
                        if ($this->admin->save_customer_basket($cart)) {
                            echo json_encode(['message' => 'Cart Saved', 'type' => 'success']);
                        } else {
                            echo json_encode(['message' => 'Cart Not Saved', 'type' => 'wanring']);
                        }
                        exit;
                        break;
                    case 'delete_cart':
                        if (isset($data['deleteCart'])) {
                            $index = $data['currentCart'];
                            $this->admin->remove_cart($data['deleteCart']);
                            if ($data['deleteCart'] == $index) {
                                //not current cart
                                $goto = $this->get_redirect($cart, true);
                                $this->manager->clear_storage();
                                echo json_encode(['goto' => Yii::$app->url_manager->create_url([$goto, 'orders_id' => Yii::$app->request->get('orders_id')])]);
                            } else {
                                $this->storage->set_pointer($data['deleteCart']);
                                $this->manager->clear_storage();
                                echo json_encode(['reload' => 1]);
                            }
                        }
                        exit;
                        break;
                    case 'save_settings':
                        $this->manager->set('platform_id', Yii::$app->request->post('platform_id'));
                        $this->manager->set('currency', Yii::$app->request->post('currency'));
                        $this->manager->set('languages_id', Yii::$app->request->post('language_id'));
                        $this->manager->remove('shipping');
                        $cart->clear_totals();
                        $this->manager->set('cart', $cart);
                        echo json_encode(['reload' => true]);
                        exit;
                        break;
                }
            }
            $this->manager->set('cart', $cart);
            //working_cart
            $this->manager->checkout_order_with_addresses();
            $this->manager->update_shipping_cost();
            $response['products_listing'] = $this->manager->render('ProductsListing', ['manager' => $this->manager]);
            $response['order_totals'] = $this->manager->render('OrderTotals', ['manager' => $this->manager]);
            $response['order_shipping'] = $this->manager->render('Shipping', ['manager' => $this->manager]);
            $response['order_payment'] = $this->manager->render('Payment', ['manager' => $this->manager]);
            $response['order_state'] = $this->get_order_state_array();
        }
        echo json_encode($response);
        exit;
    }
    /* processing with products in basket */
    public function action_show_basket()
    {
        $_get = Yii::$app->request->get();
        $this->storage->set_pointer($_get['currentCart']);
        //!!importnat to set pointer before using stored data
        $this->load_platform_consts();
        $cart = $this->manager->get('cart');
        //working_cart
        $this->check_order_owner($cart);
        $this->manager->load_cart($cart);
        $this->manager->create_order_instance($this->manager->get('order_instance'));
        $this->manager->define_order_tax_address();
        if (Yii::$app->request->is_post) {
            switch (Yii::$app->request->post('action')) {
                case 'load_categories':
                    if (\Yii::$app->request->post('products_id')) {
                        $response = [];
                        $products_id = \Yii::$app->request->post('products_id');
                        $product_categories = \common\helpers\Categories::generate_category_path($products_id, 'product');
                        $product_categories_string = '';
                        for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
                            $category_path = '';
                            for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++) {
                                $category_path .= '<span class="category_path__location">' . $product_categories[$i][$j]['text'] . '</span>&nbsp;&gt;&nbsp;';
                            }
                            $category_path = substr($category_path, 0, -16);
                            $product_categories_string .= '<li class="category_path">' . $category_path . '</li>';
                        }
                        $product_categories_string = $product_categories_string != '' ? '<ul class="category_path_list">' . $product_categories_string . '</ul>' : '';
                        $response['categories'] = $product_categories_string;
                        echo json_encode($response);
                    }
                    exit;
                    break;
                case 'load_product':
                    if (Yii::$app->request->post('products_id')) {
                        $response = [];
                        $_id = (int) Yii::$app->request->post('products_id');
                        $response['products_id'] = $_id;
                        $insulator = new \backend\services\Product_Insulator_Service(Yii::$app->request->post('products_id'), $this->manager);
                        $product_details = $insulator->get_product_main_details();
                        $product = $insulator->get_product();
                        $response['isComplex'] = \common\helpers\Attributes::has_product_attributes($_id) || $product->is_bundle || $product->products_pctemplates_id;
                        $response['product'] = $product_details;
                        $attr = $insulator->get_product_details();
                        if ($attr['attributes_box']['data']['attributes_array'] ?? false) {
                            $response['attributes_array'] = $attr['attributes_box']['data']['attributes_array'];
                        }
                        $response['content'] = $this->manager->render('Product', ['product' => $product_details, 'manager' => $this->manager]);
                        echo json_encode($response);
                    }
                    exit;
                    break;
                case 'get_details_ex':
                    $post = Yii::$app->request->post('product_info');
                    $response = 'wrong product id';
                    if (is_array($post)) {
                        $response = [];
                        foreach ($post as $key => $data) {
                            if (isset($data['products_id'])) {
                                $pid = $data['products_id'];
                                if (is_array($pid)) {
                                    // sometime sh
                                    $pid = reset($pid);
                                    $data['products_id'] = $pid;
                                }
                                $insulator = new \backend\services\Product_Insulator_Service($pid, $this->manager);
                                $insulator->set_data($data);
                                $result = $insulator->get_product_details();
                                $overwritten = $insulator->get_overwritten();
                                $result['product_info']['overwritten'] = $overwritten;
                                $current_price = round((float) $result['product_info']['product_unit_price'], 2);
                                $origin_price = $overwritten['origin_price'] ?? $main_details['price'] ?? $current_price;
                                $result['product_info']['origin_price'] = $origin_price;
                                $result['product_info']['final_price'] = $overwritten['final_price_formula_data'][1]['vars']['init_value'] ?? $origin_price;
                                // i.e. final_init_value_price
                                $result['product_info']['current_product_price'] = $current_price;
                                $response[] = $result;
                            }
                        }
                    }
                    echo json_encode(array_pop($response));
                    exit;
                    break;
                case 'get_details':
                    $post = Yii::$app->request->post('product_info');
                    if (is_array($post)) {
                        $response = [];
                        foreach ($post as $key => $data) {
                            if (isset($data['products_id'])) {
                                $insulator = new \backend\services\Product_Insulator_Service($data['products_id'], $this->manager);
                                $insulator->set_data($data);
                                if (Yii::$app->request->post('edit')) {
                                    $insulator->edit = true;
                                }
                                $product_detail = $insulator->get_product_details();
                                if (\common\helpers\Product::get_virtual_item_quantity_value($data['products_id']) > 1) {
                                    $product_detail['product_info']['virtual_item_qty'] = \common\helpers\Product::get_virtual_item_quantity_value($data['products_id']);
                                    $product_detail['product_info']['virtual_item_step'] = \common\helpers\Product::get_virtual_item_step($data['products_id']);
                                }
                                $response[] = $product_detail;
                            }
                        }
                    }
                    echo json_encode(array_pop($response));
                    exit;
                    break;
                case 'add_products':
                    $post = Yii::$app->request->post();
                    //if add gift wrap, clear gift wrap totals
                    $added = true;
                    $err_products = '';
                    if (is_array($post['product_info'])) {
                        foreach ($post['product_info'] as $product_data) {
                            $insulator = new \backend\services\Product_Insulator_Service($product_data['products_id'], $this->manager);
                            $insulator->edit = Yii::$app->request->get('action') == 'edit_product';
                            $product_data['pconfig_dont_inc_qty'] = true;
                            $insulator->set_data($product_data);
                            $insulator->set_extra_charge();
                            $just_added = $insulator->add_product($post['replace_same_product'] ?? true);
                            $added = $just_added && $added;
                            if (!$just_added) {
                                $product_name = !empty($product_data['name']) ? $product_data['name'] : 'Product with empty name and id=' . $product_data['products_id'];
                                $err_products .= $product_name . '<br>';
                            }
                        }
                    }
                    $this->manager->set('cart', $this->manager->get_cart());
                    //working_cart
                    $this->admin->save_customer_basket($this->manager->get_cart());
                    if ($added) {
                        echo json_encode(['status' => 'ok']);
                    } else {
                        echo json_encode(['status' => 'bad', 'message' => 'Not All products were been added:<br>' . $err_products]);
                    }
                    exit;
                    break;
                case 'add_giveaway':
                    $post = Yii::$app->request->post();
                    $added = false;
                    if (isset($post['giveaways'])) {
                        foreach ($post['giveaways'] as $gaw_id => $giveaways) {
                            $insulator = new \backend\services\Product_Insulator_Service($giveaways['products_id'], $this->manager);
                            if (isset($post['giveaway_switch'][$gaw_id])) {
                                $giveaways['giveaway_switch'] = $gaw_id;
                            }
                            $insulator->set_data($giveaways);
                            $added = $added || $insulator->add_give_away($gaw_id);
                        }
                    }
                    $this->manager->set('cart', $this->manager->get_cart());
                    //working_cart
                    $this->admin->save_customer_basket($this->manager->get_cart());
                    if ($added) {
                        echo json_encode(['status' => 'ok']);
                    } else {
                        echo json_encode(['status' => 'bad', 'message' => 'Not All products were been added']);
                    }
                    break;
            }
        } else if (Yii::$app->request->get('action') == 'show_giveaways') {
            return $this->manager->render('GiveAway', ['manager' => $this->manager]);
        } elseif (Yii::$app->request->get('action') == 'edit_product') {
            $uprid = Yii::$app->request->get('uprid');
            return $this->manager->render('EditProduct', ['manager' => $this->manager, 'uprid' => $uprid]);
        } else {
            $this->manager->checkout_order_with_addresses();
            $this->manager->total_pre_confirmation_check();
            $this->manager->update_shipping_cost();
            return $this->manager->render('ProductsBox', ['cart' => $cart, 'manager' => $this->manager, 'post' => \Yii::$app->request->get()]);
        }
    }
    public function get_redirect($cart, $to_process = false)
    {
        $goto = '';
        if ($cart->table_prefix == 'sample_') {
            $goto = 'samples/';
            if ($to_process && $cart->order_id) {
                $goto .= 'process-samples';
            }
        } elseif ($cart->table_prefix == 'quote_') {
            $goto = 'quotation/';
            if ($to_process && $cart->order_id) {
                $goto .= 'process-quotation';
            }
        } else {
            $goto = 'orders/';
            if ($to_process && $cart->order_id) {
                $goto .= 'process-order';
            }
        }
        return $goto;
    }
    /* is used for customer/shipping/paymens/order totals preocessing */
    public function action_checkout()
    {
        $_get = Yii::$app->request->get();
        //!!importnat to set pointer before using stored data
        $this->storage->set_pointer($_get['currentCart']);
        $this->admin->set_current_cart_id($_get['currentCart']);
        /** @var \common\classes\shopping_cart $cart */
        $cart = $this->manager->get('cart');
        //working_cart
        $this->check_order_owner($cart);
        if (!$this->manager->has('admin_edit_order')) {
            $this->manager->set('admin_edit_order', 1);
        }
        $data = Yii::$app->request->post();
        $data['type'] = $data['type'] ?? null;
        $response = [];
        if ($cart) {
            $currencies = Yii::$container->get('currencies');
            $this->load_platform_consts();
            $this->manager->load_cart($cart);
            $this->manager->create_order_instance($this->manager->get('order_instance'));
            //$this->manager->defineOrderTaxAddress();
            //VL 20191220 uncomment??
            if (Yii::$app->request->is_post) {
                if (isset($data['action'])) {
                    switch ($data['action']) {
                        case 'recalculate_totals':
                            if (isset($data['update_totals'])) {
                                foreach ($data['update_totals'] as $module => $values) {
                                    if (is_array($values)) {
                                        foreach ($values as &$value) {
                                            $value = floatval($value);
                                            $value *= $currencies->get_market_price_rate($cart->currency, DEFAULT_CURRENCY);
                                        }
                                    }
                                    $cart->set_total_key($module, $values);
                                }
                            }
                            break;
                        case 'update_amount':
                            if (isset($data['paid_amount'])) {
                                $value = (float) $data['paid_amount'] * $currencies->get_market_price_rate($cart->currency, DEFAULT_CURRENCY);
                                $_value = $data['paid_prefix'] == '-' ? -$value : $value;
                                $comment = $currencies->format($_value) . ' ' . $data['comment'] . ' (' . \common\helpers\Date::format_date_time(new \yii\db\Expression('now')) . '), ' . $this->admin->get_info('admin_firstname') . ' ' . $this->admin->get_info('admin_lastname');
                                $cart->set_total_paid($value, $data['paid_prefix'], $comment);
                                $this->add_log('Paid amount changed to ' . $value);
                            }
                            break;
                        case 'reset_totals':
                            $cart->clear_totals(false);
                            $cart->clear_hidden_modules();
                            $cart->restore_totals();
                            $cart->clear_total_key('ot_shipping');
                            $this->manager->update_shipping_cost();
                            break;
                        case 'reset_checkout':
                            $this->manager->remove('estimate_ship');
                            $this->manager->remove('estimate_bill');
                            $this->manager->remove('checkout');
                            return json_encode(['ok' => true]);
                            exit;
                            break;
                        case 'save_checkout':
                        case 'save_order':
                            //validate forms before
                            if ($this->manager->is_shipping_needed()) {
                                //need to do something))
                            }
                            $ship_as_bill = $data['ship_as_bill'] ?? null;
                            $valid = $this->manager->validate_shipping(\Yii::$app->request->post());
                            $__guest_order_edit = false;
                            if ($this->manager->is_customer_assigned() || $this->manager->get_customers_identity()->get('fromOrder')) {
                                if (isset($data['checkout']['email_address']) && !isset($data['checkout']['firstname'])) {
                                    if (is_array($data['Shipping_address'])) {
                                        $data['checkout']['firstname'] = $data['Shipping_address']['firstname'];
                                        $data['checkout']['lastname'] = $data['Shipping_address']['lastname'];
                                    } elseif (is_array($data['Billing_address'])) {
                                        $data['checkout']['firstname'] = $data['Billing_address']['firstname'];
                                        $data['checkout']['lastname'] = $data['Billing_address']['lastname'];
                                    }
                                }
                            } else if (isset($data['checkout']['email_address']) && !isset($data['checkout']['firstname'])) {
                                if (is_array($data['Shipping_address'])) {
                                    $data['checkout']['firstname'] = $data['Shipping_address']['firstname'];
                                    $data['checkout']['lastname'] = $data['Shipping_address']['lastname'];
                                } elseif (is_array($data['Billing_address'])) {
                                    $data['checkout']['firstname'] = $data['Billing_address']['firstname'];
                                    $data['checkout']['lastname'] = $data['Billing_address']['lastname'];
                                }
                                $__guest_order_edit = true;
                            }
                            $valid = $this->manager->validate_contact_form($data, true) && $valid;
                            $valid = $this->manager->validate_address_forms($data, '', $ship_as_bill, true) && $valid;
                            if ($ext = \common\helpers\Acl::check_extension_allowed('DelayedDespatch', 'allowed')) {
                                $valid = !$ext::prepare_delivery_date(true, $this->manager) && $valid;
                            }
                            if ($valid) {
                                $customer = $this->manager->get_customers_identity();
                                if ($__guest_order_edit || $customer->get('fromOrder')) {
                                    $customer->fill_customer_fields($this->manager->get_customer_contact_form(false));
                                    $this->manager->set('customer', $customer);
                                }
                                if ($data['action'] == 'save_order') {
                                    if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory') && Yii::$app->request->get('orders_id')) {
                                        $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                                        $before_object = new \common\api\Classes\Order();
                                        $before_object->load(Yii::$app->request->get('orders_id'));
                                        $logger->set_before_object($before_object);
                                        unset($before_object);
                                    }
                                    $this->manager->collect_post_data();
                                    if (isset($_POST['status'])) {
                                        $this->manager->get_cart()->set_order_status((int) $_POST['status']);
                                    }
                                    $this->manager->get_order_instance()->with_delivery = true;
                                    $this->manager->set_selected_payment_module($this->manager->get_payment());
                                    $this->manager->checkout_order_with_addresses();
                                    /** @var \common\classes\extended\OrderAbstract $order */
                                    $order = $this->manager->get_order_instance();
                                    if ($__guest_order_edit || $customer->get('fromOrder')) {
                                        $order->customer = [];
                                        foreach ($customer->get_attributes() as $field => $value) {
                                            $order->customer[preg_replace('/customers_/', '', $field)] = $value;
                                        }
                                        $order->customer = array_merge($order->customer, $customer->get_all());
                                    }
                                    $output = $this->manager->get_total_output(false);
                                    if ($ext = \common\helpers\Acl::check_extension_allowed('UpdateAndPay', 'allowed')) {
                                        $ext::check_status($this->manager);
                                    }
                                    // {{ WA: prevent set order status as paid when select payment
                                    if (!isset($_POST['status'])) {
                                        $order->info['order_status'] = (int) DEFAULT_ORDERS_STATUS_ID;
                                        if (false && Yii::$app->request->get('orders_id')) {
                                            $_order_status = $order->get_ar_model()->where(['orders_id' => Yii::$app->request->get('orders_id')])->select('orders_status')->scalar();
                                            if ($_order_status) {
                                                $order->info['order_status'] = $_order_status;
                                            }
                                        }
                                    }
                                    // }} WA: prevent set order status as paid when select payment
                                    if (empty($order->info['order_status']) || !$order->info['order_status']) {
                                        $order->info['order_status'] = (int) DEFAULT_ORDERS_STATUS_ID;
                                    }
                                    if (\Yii::$app->request->post('purchase_order', false) !== false) {
                                        $order->info['purchase_order'] = tep_db_prepare_input(\Yii::$app->request->post('purchase_order'));
                                    }
                                    if ($order->maintain_splittering()) {
                                        $this->manager->get_order_splitter()->make_splinters(Yii::$app->request->get('orders_id'));
                                    }
                                    $old_model = null;
                                    if (Yii::$app->request->get('orders_id')) {
                                        $old_model = $order->get_ar_model()->where(['orders_id' => Yii::$app->request->get('orders_id')])->one();
                                        $order->save_order(Yii::$app->request->get('orders_id'));
                                    } else {
                                        $order->save_order();
                                    }
                                    if ($order->maintain_splittering()) {
                                        $this->manager->get_order_splitter()->update_splinter_order_id($order->order_id);
                                    }
                                    $cart->order_id = $order->order_id;
                                    /*if ($ext = \common\helpers\Acl::checkExtensionAllowed('UpdateAndPay', 'allowed')) {
                                          $ext::checkRefund($this->manager, $data['difference']);
                                      }*/
                                    $_notify = false;
                                    // email on each save order request :( It's better to send it separately
                                    $order->save_details($_notify);
                                    $order->save_products($_notify);
                                    // email on each save order request :( It's better to send it separately $data['type'] != 'send_request');
                                    /** @var \common\extensions\UpdateAndPay\UpdateAndPay $ext */
                                    if ($ext = \common\helpers\Acl::check_extension_allowed('UpdateAndPay', 'allowed')) {
                                        $ext::save_order($this->manager, $data['type'], $data['difference'] ?? null);
                                    }
                                    if ($old_model && $this->manager->is_customer_assigned()) {
                                        \common\helpers\Customer::update_basket_id($this->manager->get_customer_assigned(), $old_model->basket_id ?? null, $cart->basket_id ?? null);
                                    }
                                    $this->save_log();
                                    if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory') && isset($logger) && Yii::$app->request->get('orders_id')) {
                                        $after_object = new \common\api\Classes\Order();
                                        $after_object->load(Yii::$app->request->get('orders_id'));
                                        $logger->set_after_object($after_object);
                                        unset($after_object);
                                        $logger->run();
                                    }
                                    $response['message'] = 'Order saved';
                                    $response['prompt'] = true;
                                    $response['order_id'] = $order->order_id;
                                }
                                if ($this->admin->save_checkout_details($cart, $this->storage)) {
                                    $response['message'] = 'All changes saved';
                                    $response['type'] = 'success';
                                    $new_cart_id = $this->admin->get_current_cart_id();
                                    if ($_get['currentCart'] != $new_cart_id) {
                                        $params = $this->storage->get_all();
                                        $this->storage->remove_all();
                                        $this->storage->set_pointer($new_cart_id);
                                        foreach ($params as $key => $value) {
                                            $this->manager->set($key, $value);
                                        }
                                    }
                                    $this->manager->set('cart', $cart);
                                    $params = Yii::$app->request->get_referrer();
                                    $params = parse_url($params);
                                    $q_params = Yii::$app->request->get_query_params();
                                    $q_params['currentCart'] = $new_cart_id;
                                    if ($cart->order_id) {
                                        $q_params['orders_id'] = $cart->order_id;
                                    }
                                    $response['urlCheckout'] = Yii::$app->url_manager->create_absolute_url(array_merge(['editor/checkout'], $q_params)) . '#tab_contact';
                                    $response['redirect'] = Yii::$app->url_manager->create_url(array_merge(['editor/' . basename($params['path'])], $q_params)) . '#tab_contact';
                                    /*} else {
                                          $response['reload'] = true;
                                      }*/
                                    $this->layout = false;
                                    if (isset($data['get_fresh_aup']) && $data['get_fresh_aup']) {
                                        $c_info = \common\models\Customers::find()->where(['customers_id' => $order->customer['id']])->one();
                                        $aup = \common\helpers\Password::encrypt_auth_user_param($order->customer['id'], $order->customer['email_address'], 'payment', $c_info->auth_key ?? '');
                                        $response['frontend_aup_raw'] = $order->customer['id'] . "\t" . $order->customer['email_address'];
                                        $response['frontend_aup'] = $aup;
                                    }
                                } else {
                                    $response = ['message' => 'Changes Not Saved', 'type' => 'wanring'];
                                }
                            } else {
                                $message_stack = \Yii::$container->get('message_stack');
                                if ($message_stack->size('one_page_checkout') > 0) {
                                    $message = $message_stack->output('one_page_checkout');
                                }
                                $response = ['message' => $message, 'type' => 'wanring'];
                            }
                            echo json_encode($response);
                            exit;
                            break;
                        case 'remove_cart':
                            $index = $this->admin->get_current_cart_id();
                            $goto = $this->get_redirect($cart, true);
                            $order_id = $cart->order_id;
                            $this->admin->remove_cart($index);
                            $this->manager->clear_storage();
                            echo json_encode(['redirect' => Yii::$app->url_manager->create_url([$goto, 'orders_id' => $order_id])]);
                            exit;
                            break;
                        case 'delete_order':
                            $orders_id = Yii::$app->request->get('orders_id');
                            $goto = $this->get_redirect($cart);
                            if ($orders_id) {
                                $order = $this->manager->get_order_instance();
                                $order->order_id = $orders_id;
                                $order->remove_order($data['restock'] == 'true' ? true : false);
                                $index = $this->admin->get_current_cart_id();
                                $this->admin->remove_cart($index);
                                $this->manager->clear_storage();
                            }
                            echo json_encode(['redirect' => Yii::$app->url_manager->create_url([$goto])]);
                            exit;
                            break;
                        case 'search_customer':
                            $c_rep = new \common\models\repositories\Customers_Repository();
                            $customers = [];
                            foreach ($c_rep->search($data['search'])->limit(200)->all() as $customer) {
                                $customers[] = ['id' => $customer->customers_id, 'text' => \common\helpers\Output::output_string_protected($customer->customers_firstname . ' ' . $customer->customers_lastname . ' (' . $customer->customers_email_address . ')')];
                            }
                            if (empty($customers)) {
                                $customers[] = ['text' => TEXT_NOTHING_FOUND];
                            }
                            echo json_encode($customers);
                            exit;
                            break;
                        case 'reassign_customer':
                            $customrs_id = $data['customers_id'];
                            $c_rep = new \common\models\repositories\Customers_Repository();
                            $customer = $c_rep->get_by_id($customrs_id);
                            if ($customer) {
                                $old = null;
                                if ($cart->customer_id) {
                                    $old = $c_rep->get_by_id($cart->customer_id);
                                }
                                $this->manager->remove('estimate_ship');
                                $this->manager->remove('estimate_bill');
                                $this->manager->remove('cot_gv');
                                $cart->restore_totals();
                                $cart->clear_total_key('ot_coupon');
                                $cart->clear_total_key('ot_gv');
                                if (!$this->manager->is_customer_assigned() || $this->manager->is_customer_assigned() && $customer->customers_id != $this->manager->get_customer_assigned()) {
                                    $this->manager->predefine_customer_details($customer->customers_id, true);
                                    $cart->set_customer($customer->customers_id);
                                    if ($this->manager->get_customers_identity()->get('fromOrder')) {
                                        $this->manager->remove('customer');
                                    }
                                    $this->add_log('New customer assigned ' . $customer->customers_firstname . ' ' . $customer->customers_lastname . ' (id:' . $customer->customers_id . ') from ' . ($old ? $old->customers_firstname . ' ' . $old->customers_lastname . ' (id:' . $old->customers_id . ')' : ''));
                                }
                                $this->manager->set('cart', $cart);
                                $this->admin->save_checkout_details($cart, $this->storage);
                            }
                            $params = Yii::$app->request->get_referrer();
                            $params = parse_url($params);
                            $q_params = Yii::$app->request->get_query_params();
                            $q_params['currentCart'] = $this->admin->get_current_cart_id();
                            $url = Yii::$app->url_manager->create_absolute_url(array_merge(['editor/' . basename($params['path'])], $q_params)) . '#tab_contact';
                            return json_encode(['ok' => true, 'url' => $url]);
                            exit;
                            break;
                        case 'change_address_list':
                            $type = $data['type'];
                            $value = $data['value'];
                            if ($value) {
                                $this->manager->change_customer_address_selection($type, $value);
                                /* if($type == 'shipping'){
                                   $this->manager->set('shipping', false);
                                   } */
                            }
                            $this->manager->get_shipping_quotes_by_choice();
                            $response['shipping_address'] = $this->manager->render('ShippingAddress', ['manager' => $this->manager]);
                            $response['billing_address'] = $this->manager->render('BillingAddress', ['manager' => $this->manager]);
                            break;
                        case 'save_address':
                            $post = Yii::$app->request->post();
                            $customer = $this->manager->get_customers_identity();
                            $customer->get('fromOrder');
                            if ($post['type'] == 'shipping') {
                                $form = $this->manager->get_shipping_form();
                            } else {
                                $form = $this->manager->get_billing_form();
                            }
                            $form->load(Yii::$app->request->post());
                            $this->manager->set('customer_id', $cart->customer_id);
                            $attributes = $customer->get_address_from_model($form);
                            $attributes['customers_id'] = $cart->customer_id;
                            $a_book = $customer->update_address($form->address_book_id, $attributes);
                            $this->manager->change_customer_address_selection($post['type'], $a_book->address_book_id);
                            $response['shipping_address'] = $this->manager->render('ShippingAddress', ['manager' => $this->manager]);
                            $response['billing_address'] = $this->manager->render('BillingAddress', ['manager' => $this->manager]);
                            break;
                        case 'set_bill_as_ship':
                            $_sendto = $this->manager->get('sendto');
                            if ($_sendto) {
                                $this->manager->change_customer_address_selection('billing', $_sendto);
                            }
                            $response['billing_address'] = $this->manager->render('BillingAddress', ['manager' => $this->manager]);
                            $response['payments'] = $this->manager->render('Payment', ['manager' => $this->manager], 'json');
                            break;
                        case 'check_vat':
                            $model_name = $data['checked_model'];
                            if ($model_name == 'Shipping_address') {
                                $address = $this->manager->get_shipping_form();
                            } else {
                                $address = $this->manager->get_billing_form();
                            }
                            $address->preload($data[$model_name]);
                            $company_vat_status = 0;
                            $customer_company_vat_status = '';
                            if ($ext = \common\helpers\Acl::check_extension_allowed('VatOnOrder', 'allowed')) {
                                list($company_vat_status, $customer_company_vat_status) = $ext::update_vat_status($address);
                            }
                            $response['vat_status'] = $customer_company_vat_status;
                            $response['field'] = \yii\helpers\Html::get_input_id($address, 'company_vat');
                            break;
                        case 'recalculation':
                            $response = [];
                            $s_address = $this->manager->get_shipping_form(null, false);
                            $s_address->load($data);
                            $pre_selected_shipping = false;
                            if ($s_address->not_empty(true)) {
                                $pre_selected_shipping = $this->manager->get_selected_shipping();
                                $this->manager->remove('shipping');
                                $this->manager->set('estimate_ship', ['country_id' => $s_address->country, 'postcode' => $s_address->postcode, 'zone' => $s_address->state, 'company_vat' => $s_address->company_vat, 'company_vat_date' => $s_address->company_vat_date, 'company_vat_status' => $s_address->company_vat_status]);
                                $this->manager->reset_delivery_address();
                            }
                            $b_address = $this->manager->get_billing_form(null, false);
                            $b_address->load($data);
                            if ($b_address->not_empty(true)) {
                                $this->manager->set('estimate_bill', ['country_id' => $b_address->country, 'postcode' => $b_address->postcode, 'zone' => $b_address->state, 'company_vat' => $b_address->company_vat, 'company_vat_date' => $b_address->company_vat_date, 'company_vat_status' => $b_address->company_vat_status]);
                                $this->manager->reset_billing_address();
                            }
                            if (isset($data['shipping']) && !empty($data['shipping'])) {
                                $this->manager->set_selected_shipping($data['shipping']);
                                $response['__ship1'] = $this->manager->get_selected_shipping();
                                $cart->clear_total_key('ot_shipping');
                                $this->manager->get_shipping_quotes_by_choice(true);
                            } else {
                                $collection = $this->manager->get_shipping_collection(is_array($pre_selected_shipping) ? $pre_selected_shipping : '');
                                //if ($cheapest = $collection->getFirstQuoteModule('no_shipping')) {
                                //}else{
                                $cheapest = $collection->cheapest();
                                //}
                                if (!$this->manager->get_shipping() && $cheapest) {
                                    $this->manager->set_selected_shipping($cheapest['id']);
                                    $cart->clear_total_key('ot_shipping');
                                    $this->manager->get_shipping_quotes_by_choice(true);
                                }
                            }
                            $_shipping = $this->manager->get_shipping();
                            if ($_shipping) {
                                $module = $this->manager->get_shipping_collection()->get($_shipping['module']);
                                if (is_object($module) && method_exists($module, 'setAdditionalParams')) {
                                    $module->set_additional_params($data);
                                } else {
                                    $this->manager->remove('shippingparam');
                                }
                            }
                            $this->manager->get_shipping_quotes_by_choice(true);
                            $this->manager->checkout_order_with_addresses();
                            if ($s_address->not_empty(true)) {
                                $response['shipping'] = $this->manager->render('Shipping', ['manager' => $this->manager]);
                            }
                            $response['payments'] = $this->manager->render('Payment', ['manager' => $this->manager]);
                            $response['order_totals'] = $this->manager->render('OrderTotals', ['manager' => $this->manager]);
                            if ($this->manager->is_customer_assigned()) {
                                $this->manager->remove('estimate_ship');
                                $this->manager->remove('estimate_bill');
                            }
                            echo json_encode($response);
                            exit;
                            break;
                        case 'shipping_changed':
                            $_prev_shipping = $this->manager->get_shipping();
                            $shipping = $data['shipping'];
                            if ($shipping) {
                                if (preg_match('/^collect/', $shipping)) {
                                    if (empty($this->manager->get_billto())) {
                                        $this->manager->set('billto', $this->manager->get_sendto());
                                    }
                                    $this->manager->set('sendto', false);
                                } elseif (is_array($_prev_shipping) && $_prev_shipping['module'] == 'collect') {
                                    if (empty($this->manager->get_sendto()) && !empty($this->manager->get_billto())) {
                                        $this->manager->set('sendto', $this->manager->get_billto());
                                    }
                                }
                                $this->manager->set_selected_shipping($shipping);
                                $cart->clear_total_key('ot_shipping');
                            }
                            $response['_isBillAsShip'] = $this->manager->is_bill_as_ship();
                            $this->manager->checkout_order();
                            $_shipping = $this->manager->get_shipping();
                            $response['_shipping'] = $_shipping;
                            if ($_shipping) {
                                $module = $this->manager->get_shipping_collection()->get($_shipping['module']);
                                if (is_object($module) && method_exists($module, 'setAdditionalParams')) {
                                    $module->set_additional_params($data);
                                } else {
                                    $this->manager->remove('shippingparam');
                                }
                                $this->add_log('Shipping changed to ' . $_shipping['module']);
                                $response['_set_to'] = $shipping;
                                $switch_to_collect = is_array($_prev_shipping) && $_prev_shipping['module'] == 'collect' && !preg_match('/^collect/', $shipping);
                                $switch_from_collect = is_array($_prev_shipping) && $_prev_shipping['module'] != 'collect' && preg_match('/^collect/', $shipping);
                                if ($switch_to_collect || $switch_from_collect) {
                                    $response['shipping_address'] = $this->manager->render('ShippingAddress', ['manager' => $this->manager]);
                                    $response['billing_address'] = $this->manager->render('BillingAddress', ['manager' => $this->manager]);
                                }
                            }
                            $response['payments'] = $this->manager->render('Payment', ['manager' => $this->manager]);
                            break;
                        case 'payment_changed':
                            $payment = $data['payment'];
                            if ($payment) {
                                $this->manager->set_selected_payment($payment);
                                $this->add_log('Payment changed to ' . $payment);
                            }
                            //$this->manager->checkoutOrder();
                            //$response['order_totals'] = $this->manager->render('Totals', ['manager' => $this->manager]);
                            break;
                        case 'remove_coupon':
                            $cart->remove_cc_item($data['coupon']);
                            break;
                        case 'remove_module':
                            $cart->add_hidden_module($data['module']);
                            $cart->clear_total_key($data['module']);
                            break;
                        case 'credit_class':
                            $this->manager->remove('cot_gv');
                            $this->manager->remove('cc_id');
                            $this->manager->remove('cc_code');
                            if ($data['coupon_apply'] != 'y' || isset($data['cot_gv_amount'])) {
                                break;
                            }
                            if (!$this->manager->is_customer_assigned()) {
                                break;
                            }
                            $credit_amount = \common\helpers\Customer::get_credit_amount($cart->customer_id);
                            if ($credit_amount <= 0) {
                                break;
                            }
                            $this->manager->checkout_order();
                            $output = $this->manager->get_total_output(false);
                            $ot_due = \common\helpers\Php::array_get_sub_array_by_sub_value($output, 'code', 'ot_due')['value'] ?? 0;
                            if ($ot_due <= 0) {
                                break;
                            }
                            $next_credit = min($ot_due, $credit_amount);
                            $data['cot_gv_amount'] = $next_credit;
                            $this->manager->get_total_collection()->clear_total_cache();
                            break;
                        case 'detect_code':
                            if (!empty($data['coupons'])) {
                                $cart->add_cc_item($data['coupons']);
                                //$data['gv_redeem_code'] = $data['coupons'];
                            }
                            break;
                        case 'check_refund':
                            $order = $this->manager->get_order_instance();
                            $orders_id = Yii::$app->request->get('orders_id');
                            if ($orders_id) {
                                $order->order_id = $orders_id;
                                if ($order->has_transactions()) {
                                    $tm = $this->manager->get_transaction_manager();
                                    if ($tm->is_ready()) {
                                        if ($tm->get_transactions_count() > 1) {
                                            return json_encode(['message' => 'There are some transactions. To refund do it manualy..', 'value' => 'to_credit']);
                                        } else {
                                            $transaction = $tm->get_transactions()[0];
                                            $payment = $this->manager->get_payment_collection()->get($transaction->payment_class);
                                            if ($payment) {
                                                $tm->use_payment($payment);
                                                if ($tm->can_payment_refund($transaction->transaction_id)) {
                                                    return json_encode(['value' => 'refund', 'text' => TEXT_MAKE_REFUND]);
                                                } elseif ($tm->can_payment_void($transaction->transaction_id)) {
                                                    return json_encode(['value' => 'void', 'text' => TEXT_VOID_PAYMENT]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            return json_encode([]);
                            break;
                    }
                }
            } else {
                $data = Yii::$app->request->get();
                switch ($data['action'] ?? null) {
                    case 'get_address_list':
                        $type = $data['type'];
                        return $this->manager->render('AddressesList', ['manager' => $this->manager, 'mode' => 'select', 'type' => $type], 'html');
                        break;
                    case 'edit_address':
                        $type = $data['type'];
                        return $this->manager->render('AddressesList', ['manager' => $this->manager, 'mode' => 'edit', 'type' => $type, 'ab_id' => $data['ad_id'] ?? ''], 'html');
                        break;
                    case 'show_statuses':
                        return $this->manager->render('OrderStatusesList', ['manager' => $this->manager], 'html');
                        break;
                    case 'show_delete':
                        return $this->manager->render('DeleteOrderConfirm', ['manager' => $this->manager], 'html');
                        break;
                }
            }
            $this->manager->set('cart', $cart);
            //working_cart
            $this->manager->checkout_order_with_addresses();
            if ($data) {
                $response['credit_modules'] = $this->manager->total_collect_posts($data);
                $response['payments'] = $this->manager->render('Payment', ['manager' => $this->manager]);
            }
            $this->manager->total_pre_confirmation_check();
            $this->manager->update_shipping_cost();
            $response['order_totals'] = $this->manager->render('OrderTotals', ['manager' => $this->manager]);
            $response['order_state'] = $this->get_order_state_array();
        }
        foreach (\common\helpers\Hooks::get_list('editor/checkout/after') as $filename) {
            include $filename;
        }
        echo json_encode($response);
        exit;
    }
    public function action_save_address()
    {
        $post = Yii::$app->request->post();
        $customer = $this->manager->get_customers_identity();
        $this->manager->create_order_instance($this->manager->get('order_instance'));
        $customer->get('fromOrder');
        if ($post['type'] == 'shipping') {
            $form = $this->manager->get_shipping_form();
        } else {
            $form = $this->manager->get_billing_form();
        }
        $form->load(Yii::$app->request->post());
        $ab = Address_Book::find_one(['address_book_id' => $form->address_book_id]);
        $this->manager->set('customer_id', $ab->customers_id);
        $attributes = $customer->get_address_from_model($form);
        $attributes['customers_id'] = $ab->customers_id;
        $a_book = $customer->update_address($form->address_book_id, $attributes);
        if ($post['type'] == 'shipping') {
            $this->manager->set('sendto', $a_book->address_book_id);
        } else {
            $this->manager->set('billto', $a_book->address_book_id);
        }
        $this->manager->change_customer_address_selection($post['type'], $a_book->address_book_id);
        $response['shipping_address'] = $this->manager->render('ShippingAddress', ['manager' => $this->manager]);
        $response['billing_address'] = $this->manager->render('BillingAddress', ['manager' => $this->manager]);
        return json_encode($response);
    }
    public function action_create_account()
    {
        \common\helpers\Translation::init('admin/customers');
        $this->manager->create_account = true;
        $contact_form = $this->manager->get_customer_contact_form();
        $contact_form->preload_customers_data();
        $shipping_form = null;
        if ($this->manager->is_shipping_needed()) {
            $shipping_form = $this->manager->get_shipping_form();
        }
        $billing_form = $this->manager->get_billing_form();
        $message_stack = \Yii::$container->get('message_stack');
        if (Yii::$app->request->is_post) {
            $billing_valid = $shipping_valid = true;
            $contact_valid = $contact_form->load(Yii::$app->request->post()) && $contact_form->validate();
            $ship_as_bill = Yii::$app->request->post('ship_as_bill');
            if ($this->manager->is_shipping_needed()) {
                $shipping_valid = $shipping_form->load(Yii::$app->request->post()) && $shipping_form->validate();
                if (!$ship_as_bill) {
                    $billing_valid = $billing_form->load(Yii::$app->request->post()) && $billing_form->validate();
                }
            } else {
                $billing_valid = $billing_form->load(Yii::$app->request->post()) && $billing_form->validate();
            }
            $error = false;
            if ($billing_valid && $shipping_valid && $contact_valid) {
                $_get = Yii::$app->request->get();
                $this->storage->set_pointer($_get['currentCart']);
                $this->admin->set_current_cart_id($_get['currentCart']);
                $cart = $this->manager->get('cart');
                $this->manager->remove('estimate_ship');
                $this->manager->remove('estimate_bill');
                $customer = new \common\components\Customer();
                $customer->register_customer($contact_form, false);
                $this->manager->predefine_customer_details($customer->customers_id);
                if ($cart) {
                    $cart->set_customer($customer->customers_id);
                    $this->manager->set('cart', $cart);
                }
                //switch pointer && cart id
                if ($this->manager->is_shipping_needed()) {
                    if ($ship_as_bill) {
                        //add only
                        $attributes = $customer->get_address_from_model($shipping_form);
                        $a_book = $customer->update_address($customer->customers_default_address_id, $attributes);
                        if ($a_book) {
                            $this->manager->set('sendto', $a_book->address_book_id);
                            $this->manager->set('billto', $a_book->address_book_id);
                        }
                    } else {
                        $different = false;
                        if ($shipping_form->not_empty() && $billing_form->not_empty()) {
                            foreach ($shipping_form->get_active_attributes() as $name => $value) {
                                if ($billing_form->{$name} != $value) {
                                    $different = true;
                                }
                            }
                            if ($different) {
                                $attributes = $customer->get_address_from_model($billing_form);
                                $a_book = $customer->update_address($customer->customers_default_address_id, $attributes);
                                if ($a_book) {
                                    $this->manager->set('billto', $a_book->address_book_id);
                                }
                                $attributes = $customer->get_address_from_model($shipping_form);
                                $a_book = $customer->add_address($attributes);
                                if ($a_book) {
                                    $this->manager->set('sendto', $a_book->address_book_id);
                                }
                            }
                        }
                    }
                } else {
                    //ne address from billing form
                    $attributes = $customer->get_address_from_model($billing_form);
                    $a_book = $customer->update_address($customer->customers_default_address_id, $attributes);
                    if ($a_book) {
                        $this->manager->set('billto', $a_book->address_book_id);
                    }
                }
                echo json_encode(['success' => true, 'messages' => SUCCESS_CUSTOMERUPDATED]);
                exit;
            } else {
                $error = true;
                foreach ($contact_form->get_errors() as $error) {
                    $message_stack->add(is_array($error) ? implode('<br>', $error) : $error, 'one_page_checkout');
                }
                if ($this->manager->is_shipping_needed()) {
                    foreach ($shipping_form->get_errors() as $error) {
                        $message_stack->add(is_array($error) ? implode('<br>', $error) : $error, 'one_page_checkout');
                    }
                }
                if (!$ship_as_bill) {
                    foreach ($billing_form->get_errors() as $error) {
                        $message_stack->add(is_array($error) ? implode('<br>', $error) : $error, 'one_page_checkout');
                    }
                }
                if ($message_stack->size('one_page_checkout') > 0) {
                    $messages = $message_stack->output('one_page_checkout');
                }
                echo json_encode(['error' => true, 'messages' => $messages]);
                exit;
            }
        }
        return $this->manager->render('Account', ['manager' => $this->manager, 'contactForm' => $contact_form, 'shippingForm' => $shipping_form, 'billingForm' => $billing_form]);
    }
    public function action_load_tree()
    {
        \common\helpers\Translation::init('admin/platforms');
        $this->layout = false;
        $_get = Yii::$app->request->get();
        $this->storage->set_pointer($_get['currentCart']);
        $post = Yii::$app->request->post();
        $post['platform_id'] = $this->manager->get_platform_id();
        return $this->manager->render('ProductsBox', ['manager' => $this->manager, 'post' => $post], 'json');
    }
    protected function tep_get_category_children(&$children, $platform_id, $categories_id)
    {
        if (!is_array($children)) {
            $children = [];
        }
        foreach ($this->load_tree_slice($platform_id, $categories_id) as $item) {
            $key = $item['key'];
            $children[] = $key;
            if ($item['folder']) {
                $this->tep_get_category_children($children, $platform_id, intval(substr($item['key'], 1)));
            }
        }
    }
    public function action_settings()
    {
        $this->layout = false;
        $show_full = true;
        $current_current = Yii::$app->request->get('currentCurrent', null);
        $currency = $language_id = null;
        if ($current_current) {
            $this->storage->set_pointer($current_current);
            //!!importnat to set pointer before using stored data
            $platform_id = $this->storage->get('platform_id');
            $currency = $this->storage->get('currency');
            $language_id = $this->storage->get('languages_id');
        } else {
            $platform_id = Yii::$app->request->post('platform_id') ?? 0;
            $show_full = $platform_id ? false : true;
        }
        $entry = new \stdClass();
        $this->load_platform_details($entry, $platform_id, $currency, $language_id);
        return $this->render_ajax('settings', ['entry' => $entry, 'cl' => !$show_full, 'currentCurrent' => $current_current, 'back' => \Yii::$app->request->get('back')]);
    }
    public function process($cart)
    {
        $current_cart = $this->admin->get_current_cart_id();
        if ($cart) {
            if ($this->manager->has('platform_id')) {
                $cart->set_platform($this->manager->get('platform_id'));
            }
            if ($this->manager->has('currency')) {
                $cart->set_currency($this->manager->get('currency'));
            }
            if ($this->manager->has('languages_id')) {
                $cart->set_language($this->manager->get('languages_id'));
            }
            $this->load_platform_consts();
            $this->manager->show_admin_owner_notification = false;
            if (!$this->admin->check_cart_owner_clear($cart)) {
                $this->manager->show_admin_owner_notification = true;
            }
            $this->manager->load_cart($cart);
            $this->manager->create_order_instance($this->manager->get('order_instance'));
            $this->load_checkout_details($cart->order_id);
            if ($cart->customer_id && (!$this->manager->is_customer_assigned() || $cart->customer_id != $this->manager->get_customer_assigned())) {
                $this->manager->predefine_customer_details($cart->customer_id);
            }
            $this->manager->set('cart', $cart);
            if (!($this->manager->has('platform_id') && $this->manager->has('currency') && $this->manager->has('languages_id'))) {
                $this->manager->show_settings = true && (Yii::$app->request->get('currentCart') ?? false);
                if ($this->manager->show_settings) {
                    $this->manager->show_settings = $this->minify_platforms();
                }
            }
            if (!$this->manager->has('shipping')) {
                $this->manager->get_shipping_quotes_by_choice();
                $collection = $this->manager->get_shipping_collection($this->manager->get_shipping());
                // {{ set shipping on open
                //if ($cheapest = $collection->getFirstQuoteModule('no_shipping')) {
                //}else{
                $cheapest = $collection->cheapest();
                //}
                if (is_array($cheapest) && !$this->manager->get_shipping()) {
                    $this->manager->set_shipping($cheapest);
                    $this->manager->get_shipping_quotes_by_choice(true);
                }
                // }} set shipping on open
            } else {
                $this->manager->update_shipping_cost();
            }
            $this->manager->checkout_order_with_addresses();
            return $this->render('edit', ['currentCart' => $current_cart, 'manager' => $this->manager, 'admin' => $this->admin, 'order_id' => $this->manager->get_order_instance()->order_id, 'paramOrderId' => Yii::$app->request->get('orders_id'), 'order_state' => $this->get_order_state_array()]);
        } else {
            throw new \Exception('cart not created');
        }
    }
    public function minify_platforms()
    {
        if (\common\classes\platform::is_multi(false, true)) {
            return true;
        } else {
            $entry = new \stdClass();
            $this->load_platform_details($entry, \common\classes\platform::default_id());
            if ($entry->default_platform) {
                $this->manager->set('platform_id', $entry->default_platform);
            }
            if ($entry->defualt_platform_currency) {
                $this->manager->set('currency', $entry->defualt_platform_currency);
            }
            if ($entry->defualt_platform_language) {
                $this->manager->set('languages_id', $entry->defualt_platform_language);
            }
            if ($this->manager->has('platform_id') && $this->manager->has('currency') && $this->manager->has('languages_id')) {
                return false;
            }
        }
        return true;
    }
    public function action_order_edit()
    {
        $this->admin->load_customers_baskets('cart');
        \common\helpers\Translation::init('admin/customers');
        $order = null;
        $o_id = Yii::$app->request->get('orders_id');
        if (tep_not_null($o_id) && $o_id) {
            $order = \common\models\Orders::find_one(['orders_id' => $o_id]);
            if (!$order) {
                return $this->redirect(['orders/']);
            }
            $this->manager->set('platform_id', $order->platform_id);
            $this->manager->set('currency', $order->currency);
            $this->manager->set('languages_id', $order->language_id);
        }
        $cart = $this->obtain_customer_cart('\common\classes\shopping_cart', $order, Yii::$app->request->get('currentCart', ''));
        $current_cart = $this->admin->get_current_cart_id();
        $this->storage->set_pointer($current_cart);
        if ($this->admin->new_cart_created) {
            $this->storage->remove_all();
            if ($order) {
                $this->manager->set('platform_id', $order->platform_id);
                $this->manager->set('currency', $order->currency);
                $this->manager->set('languages_id', $order->language_id);
            }
        }
        if (!Yii::$app->request->get('currentCart') && $current_cart) {
            $q_params = Yii::$app->request->get_query_params();
            $q_params['currentCart'] = $current_cart;
            return $this->redirect(array_merge(['order-edit'], $q_params));
        }
        $title_id = empty($o_id) ? TEXT_CREATE_NEW_OREDER : TEXT_ORDER_ID . ' #' . $o_id;
        $title_dt = empty($order->date_purchased) ? '' : ' <div class="head-or-time">' . TEXT_DATE_AND_TIME . ' ' . $order->date_purchased . '</div>';
        $title_platform = $this->manager->has('platform_id') ? ' <div class="order-platform">' . TABLE_HEADING_PLATFORM . ':' . \common\classes\platform::name($this->manager->get('platform_id')) . '</div>' : '';
        $this->navigation[] = ['title' => $title_id . $title_dt . $title_platform];
        //set currency before
        if ($this->manager->has('cart')) {
            $_cart = $this->manager->get('cart');
            if ($_cart->basket_id != $cart->basket_id) {
                //different stored form working cart
                $this->manager->set('cart', $cart);
            } else {
                $cart = $_cart;
            }
        }
        $this->manager->set('order_instance', '\common\classes\Order');
        return $this->process($cart);
    }
    public function action_quote_edit()
    {
        if (!\common\helpers\Acl::check_extension_allowed('Quotations')) {
            return '';
        }
        $this->admin->load_customers_baskets('quote');
        $o_id = Yii::$app->request->get('orders_id');
        if (tep_not_null($o_id) && $o_id) {
            $order = \common\extensions\Quotations\models\Quote_Orders::find_one(['orders_id' => $o_id]);
            if (!$order) {
                return $this->redirect(['quotation/']);
            }
        }
        $cart = $this->obtain_customer_cart('\common\extensions\Quotations\QuoteCart', $order, Yii::$app->request->get('currentCart', ''));
        $current_cart = $this->admin->get_current_cart_id();
        $this->storage->set_pointer($current_cart);
        if ($this->admin->new_cart_created) {
            $this->storage->remove_all();
        }
        $this->navigation[] = ['title' => (tep_not_null($o_id) ? TEXT_QUOTATION : TEXT_CREATE_NEW_QUOTATION) . (tep_not_null($o_id) ? ' #' . $o_id . ' <div class="head-or-time">' . TEXT_DATE_AND_TIME . ' ' . $order->date_purchased . '</div>' : '') . ($this->manager->has('platform_id') ? ' <div class="order-platform">' . TABLE_HEADING_PLATFORM . ':' . \common\classes\platform::name($this->manager->get('platform_id')) . '</div>' : '')];
        //set currency before
        if ($this->manager->has('cart')) {
            $_cart = $this->manager->get('cart');
            if ($_cart->basket_id != $cart->basket_id) {
                $this->manager->set('cart', $cart);
            } else {
                $cart = $_cart;
            }
        }
        $this->manager->set('order_instance', '\common\extensions\Quotations\Quotation');
        return $this->process($cart);
    }
    public function action_deletecart()
    {
        $id = Yii::$app->request->post('deleteCart');
        $admin = new Admin_Carts();
        $_cb = explode('-', $id);
        if ($admin->delete_cart_by_bc($_cb[0], $_cb[1])) {
            $ids = $admin->get_virtual_cart_i_ds();
            if ($ids) {
                $_last = $admin->get_last_virtual_id();
                if (!in_array($_last, $ids)) {
                    // last was deleted
                    echo json_encode(['goto' => Url::to(['orders/order-edit', 'currentCart' => $ids[0]])]);
                    exit;
                }
            } else {
                echo json_encode(['goto' => Url::to(['orders/'])]);
                exit;
            }
        }
        echo json_encode(['reload' => true]);
        exit;
    }
    public function load_platform_details($entry, $platform = 0, $currency = null, $language_id = null)
    {
        $entry->platforms = \yii\helpers\Array_Helper::map(platform::get_list(false, true), 'id', 'text');
        if (!$platform) {
            $platform = platform::default_id();
        }
        $entry->default_platform = $platform;
        $platform_config = new platform_config($entry->default_platform);
        //currency
        $platform_currencies = $platform_config->get_allowed_currencies();
        if ($platform_currencies) {
            $_tmp = [];
            foreach ($platform_currencies as $pc) {
                $_tmp[$pc] = $pc;
            }
            $entry->platform_currencies = $_tmp;
        } else {
            $entry->platform_currencies[DEFAULT_CURRENCY] = DEFAULT_CURRENCY;
        }
        if ($c = $platform_config->get_default_currency()) {
            $entry->defualt_platform_currency = $c;
        } else {
            $entry->defualt_platform_currency = DEFAULT_CURRENCY;
        }
        if (!is_null($currency)) {
            $entry->defualt_platform_currency = $currency;
        }
        //language
        global $lng;
        $platform_languages = $platform_config->get_allowed_languages();
        if ($platform_languages) {
            $_tmp = [];
            foreach ($platform_languages as $pl) {
                $_tmp[$lng->catalog_languages[$pl]['id']] = $lng->catalog_languages[$pl]['name'];
            }
            $entry->platform_languages = $_tmp;
        } else {
            $entry->platform_languages[$lng->catalog_languages[DEFAULT_LANGUAGE]['id']] = $lng->catalog_languages[DEFAULT_LANGUAGE]['name'];
        }
        if ($c = $platform_config->get_default_language()) {
            $entry->defualt_platform_language = $lng->catalog_languages[$c]['id'];
        } else {
            $entry->defualt_platform_language = $lng->catalog_languages[DEFAULT_LANGUAGE]['id'];
        }
        if (!is_null($language_id)) {
            $entry->defualt_platform_language = $language_id;
        }
    }
    public function action_updatepay()
    {
        $currencies = Yii::$container->get('currencies');
        //$session = new \yii\web\Session;
        \common\helpers\Translation::init('admin/main');
        \common\helpers\Translation::init('admin/orders/order-edit');
        $this->view->heading_title = HEADING_TITLE;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders/index'), 'title' => HEADING_TITLE];
        $this->layout = false;
        $data = Yii::$app->request->post();
        $this->storage->set_pointer($data['currentCart']);
        //!!importnat to get current data
        $this->admin->set_current_cart_id($data['currentCart']);
        $cart = $this->manager->get('cart');
        //working_cart
        $this->check_order_owner($cart);
        $this->load_platform_consts();
        if ($cart) {
            $this->manager->load_cart($cart);
            $this->manager->create_order_instance($this->manager->get('order_instance'));
            $this->manager->checkout_order_with_addresses();
            /*if ($cart->order_id){
                  $this->manager->getOrderInstanceWithId($this->manager->get('order_instance'), $cart->order_id);
              }*/
            $ot_total = $data['ot_total'] ?? 0;
            $ot_paid = $data['ot_paid'] ?? 0;
            $new_ot_total = $ot_total;
            $ot_paid = (float) $ot_paid;
            $old_ot_total = $ot_paid;
            /** @var \common\extensions\UpdateAndPay\UpdateAndPay $ext */
            if ($ext = \common\helpers\Acl::check_extension_allowed('UpdateAndPay', 'allowed')) {
                return $ext::get_actions($old_ot_total, $new_ot_total, $this->manager);
            }
            $difference_ot_total = $old_ot_total - $new_ot_total;
            $difference = $difference_ot_total >= 0 ? true : false;
            $admin_payment_link = false;
            if (extension_loaded('openssl')) {
                $admin_payment_link = true;
            }
            $currency_value = $currencies->currencies[$cart->currency]['value'];
            return $this->render('updatepay', ['new_ot_total' => Formatter::price($new_ot_total, 0, 1, $cart->currency, $currency_value), 'old_ot_total' => Formatter::price($old_ot_total, 0, 1, $cart->currency, $currency_value), 'difference_ot_total' => $currencies->format($difference_ot_total, true, $cart->currency, $currency_value), 'pay_difference' => $difference_ot_total, 'difference' => $difference, 'adminPaymentLink' => $admin_payment_link, 'difference_desc' => $difference ? CREDIT_AMOUNT : TEXT_AMOUNT_DUE, 'manager' => $this->manager]);
        }
    }
    public function action_create_order()
    {
        $customers_id = Yii::$app->request->get('customers_id');
        $basket_id = Yii::$app->request->get('basket_id', false);
        if ($customers_id) {
            $customer = \common\components\Customer::find_one($customers_id);
            if ($customer) {
                $cart = $this->obtain_customer_cart('\common\classes\shopping_cart', null, '', $customer->customers_id);
                $current_cart = $this->admin->get_current_cart_id();
                $this->storage->set_pointer($current_cart);
                if ($this->admin->new_cart_created) {
                    $this->storage->remove_all();
                }
                if (!$this->manager->is_customer_assigned()) {
                    $this->manager->predefine_customer_details($customer->customers_id, true);
                    $cart->set_customer($customer->customers_id);
                }
                if (Yii::$app->request->get('convert')) {
                    if ($ext = Acl::check_extension_allowed('RecoverShoppingCart', 'allowed')) {
                        $ext::convert_cart($cart, true, $basket_id, $customers_id);
                    }
                }
                $this->manager->set('cart', $cart);
                return $this->redirect(['editor/order-edit', 'currentCart' => $current_cart]);
            }
        }
        return $this->redirect([$_GET['back'] . '/index', 'customers_id' => $customers_id]);
    }
    public function action_owner()
    {
        $current_current = Yii::$app->request->get('currentCurrent', null);
        $response = ['reload' => true];
        if ($current_current) {
            $this->storage->set_pointer($current_current);
            $cart = $this->manager->get('cart');
            if ($cart) {
                $name = $this->admin->get_admin_by_cart($cart);
                if (Yii::$app->request->is_post) {
                    if (Yii::$app->request->post('action') == 'confirm') {
                        if ($this->admin->reassign_cart($cart)) {
                            $order = $this->manager->get_order_instance_with_id($this->manager->get('order_instance'), $cart->order_id);
                            $order->add_legend("Order sucessfully reassigned from {$name}", Yii::$app->session->get('login_id'));
                        }
                    }
                    if (Yii::$app->request->post('action') == 'discard') {
                        $order_id = $cart->order_id;
                        $this->admin->delete_cart_by_order($order_id);
                        $order = $this->manager->get_order_instance_with_id($this->manager->get('order_instance'), $order_id);
                        $order->add_legend("Order sucessfully unassigned from {$name}", Yii::$app->session->get('login_id'));
                    }
                } else {
                    $changes_list = [];
                    //----------------------------------------------------------
                    $admin = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_ADMIN_SHOPPING_CARTS . " where customers_id ='" . (int) $cart->customer_id . "' and order_id = '" . (int) $cart->order_id . "' and cart_type='" . $this->admin->get_cart_type($cart) . "'"));
                    $obj1 = $cart;
                    $obj2 = unserialize(base64_decode($admin['customer_basket']));
                    if (is_array($obj1->contents)) {
                        foreach ($obj1->contents as $key => $value) {
                            $productname = 'Unknown';
                            if (isset($obj1->overwrite[$key]['name'])) {
                                $productname = $obj1->overwrite[$key]['name'];
                            } else {
                                $productname = \common\helpers\Product::get_products_name($key);
                            }
                            $qty = $value['qty'];
                            if (isset($obj2->contents[$key]['qty'])) {
                                $qty2 = $obj2->contents[$key]['qty'];
                                if ($qty > $qty2) {
                                    $changes_list[] = '<font color="red">' . $productname . ': ' . $qty . ' > ' . $qty2 . '</font>';
                                    //red
                                } elseif ($qty2 > $qty) {
                                    $changes_list[] = '<font color="green">' . $productname . ': ' . $qty . ' > ' . $qty2 . '</font>';
                                    //green
                                } else {
                                    $changes_list[] = $productname . ': ' . $qty2;
                                }
                            } else {
                                $changes_list[] = '<font color="red">' . $productname . ': DELETED</font>';
                                //red
                            }
                        }
                    }
                    if (is_array($obj2->contents)) {
                        foreach ($obj2->contents as $key => $value) {
                            if (!isset($obj1->contents[$key]['qty'])) {
                                $productname = 'Unknown';
                                if (isset($obj2->overwrite[$key]['name'])) {
                                    $productname = $obj2->overwrite[$key]['name'];
                                } else {
                                    $productname = \common\helpers\Product::get_products_name($key);
                                }
                                $changes_list[] = '<font color="green">' . $productname . ': ADDED</font>';
                                //red
                            }
                        }
                    }
                    $obj1Total = $obj1->show_total();
                    $obj2Total = $obj2->show_total();
                    if ($obj1Total > $obj2Total) {
                        $changes_list[] = '<font color="red">Total: ' . $obj1Total . ' > ' . $obj2Total . '</font>';
                    } elseif ($obj2Total > $obj1Total) {
                        $changes_list[] = '<font color="green">Total: ' . $obj1Total . ' > ' . $obj2Total . '</font>';
                    } else {
                        $changes_list[] = 'Total: ' . $obj2Total;
                    }
                    //----------------------------------------------------------
                    //draw
                    $goto = $this->get_redirect($cart, true);
                    $order_id = $cart->order_id;
                    return $this->manager->render('Owner', ['changesList' => $changes_list, 'currentCurrent' => $current_current, 'name' => $name, 'manager' => $this->manager, 'cancel' => \Yii::$app->url_manager->create_absolute_url([$goto, 'orders_id' => $order_id]), 'redirect' => \Yii::$app->url_manager->create_absolute_url(['editor/order-edit', 'orders_id' => $order_id])]);
                }
            }
        }
        return json_encode($response);
        exit;
    }
    public function action_order_edit_products()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        if ($length == -1) {
            $length = 10000;
        }
        $records_total = 0;
        $records_filtered = 0;
        $response_list = [];
        $currencies = \Yii::$container->get('currencies');
        $data = Yii::$app->request->get('1');
        if (isset($data['currentCart'])) {
            $this->storage->set_pointer($data['currentCart']);
            //!!importnat to get current data
            $cart = $this->manager->get('cart');
            //working_cart
            if ($cart) {
                $tax_class_array = \common\helpers\Tax::get_complex_classes_list();
                $this->load_platform_consts();
                $this->manager->load_cart($cart);
                $this->manager->create_order_instance($this->manager->get('order_instance'));
                $this->manager->define_order_tax_address();
                $order = $this->manager->get_order_instance($this->manager->get('order_instance'));
                $tax_address = $order->tax_address;
                $customer_groups_id = $this->manager->get('customer_groups_id');
                $saved_products = [];
                if ($data['orders_id'] ?? false) {
                    $saved_order = new \common\classes\Order($data['orders_id']);
                    if (isset($saved_order->products) && is_array($saved_order->products)) {
                        foreach ($saved_order->products as $product) {
                            $saved_products[] = $product['id'];
                        }
                    }
                }
                $products = $cart->get_products();
                if (is_array($products)) {
                    foreach ($products as $index => $product) {
                        $records_total++;
                        if ($index >= $start && $index < $start + $length) {
                            if (is_array($product['attributes'])) {
                                $attr_text = \common\classes\Props_Worker_Attr_Text::get_attr_text($product['props']);
                                $_attributes = [];
                                foreach ($product['attributes'] as $option => $value) {
                                    $attributes_query = tep_db_query('select pa.products_attributes_id, popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from ' . TABLE_PRODUCTS_OPTIONS . ' popt, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' poval, ' . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . (int) $product['id'] . "' and pa.options_id = '" . (int) $option . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int) $value . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int) $this->manager->get('languages_id') . "' and poval.language_id = '" . (int) $this->manager->get('languages_id') . "'");
                                    $attributes = tep_db_fetch_array($attributes_query);
                                    if (isset($attributes['products_options_name'])) {
                                        $_attributes[] = ['option' => $attributes['products_options_name'], 'value' => $attributes['products_options_values_name'], 'option_id' => $option, 'value_id' => $value];
                                    }
                                }
                                $product['attributes'] = $_attributes;
                            }
                            $response_item = [];
                            $qty_column = '';
                            if ($product['parent'] == '') {
                                if (!$product['ga']) {
                                    $qty_column .= $this->manager->render('Qty', ['product' => $product, 'manager' => $this->manager, 'isPack' => $product['is_pack'] ?? null]);
                                } else {
                                    $qty_column .= '<div class="box_al_center">' . $product['quantity'] . '</div>';
                                }
                            } else {
                                $qty_column .= $product['quantity'];
                            }
                            $qty_column .= tep_draw_hidden_field('uprid', $product['id']);
                            $name_column = '<table class="table no-border"><tr><td width="25%" class="order-product-image">';
                            $name_column .= '<div>' . \common\classes\Images::get_image($product['id'], 'Small') . '</div>';
                            $name_column .= '</td><td style="text-align:left;vertical-align:middle;">';
                            if (false) {
                                //$isEditInGrid
                                $name_column .= \yii\helpers\Html::input('text', 'name', $product['name'], ['class' => 'form-control name']);
                            } else {
                                $name_column .= '<label class="product-name">' . $product['name'] . '</label>';
                            }
                            if (!$product['ga'] && $pu = \common\helpers\Acl::check_extension_allowed('PackUnits', 'allowed')) {
                                $name_column .= $pu::query_order_process_admin($products, $index);
                            }
                            if (is_array($product['attributes']) && count($product['attributes']) > 0) {
                                foreach ($product['attributes'] as $option => $value) {
                                    $name_column .= '<div class="prop-tab-det-inp"><small>&ndash; ';
                                    $name_column .= $value['option'] . ' : ';
                                    $name_column .= $attr_text[$value['option_id']] ?? $value['value'];
                                    $name_column .= '</small></div>';
                                }
                            }
                            $name_column .= '<div><strong>' . TABLE_HEADING_PRODUCTS_MODEL . ': </strong>' . $product['model'] . '</div>';
                            if ($cart->cart_allow_giftwrap()) {
                                $gift_wrap = '';
                                if ($product['parent'] == '' && $product['gift_wrap_allowed']) {
                                    $gift_wrap = '<div class="gift-wrap"><strong>' . TEXT_GIFT_WRAP . ': </strong><label>+' . $currencies->display_price($product['gift_wrap_price'], $product['tax']) . ' ' . \yii\helpers\Html::checkbox('gift_wrap[' . $product['id'] . ']', $product['gift_wrapped'], ['class' => 'check_on_off gift_wrap', 'onchange' => "order.updateProductInRow(this, 'change_qty')"]) . '</label></div>';
                                }
                                $name_column .= $gift_wrap;
                            }
                            $name_column .= '</td></tr></table>';
                            $response_item[] = $name_column;
                            $tax_column = '';
                            if (!$product['ga'] && $product['final_price']) {
                                if (Array_Helper::get_value($product, ['overwritten', 'tax_selected']) != '') {
                                    $zone_id = $product['overwritten']['tax_selected'];
                                } else {
                                    if (isset($product['products_tax_class_id'])) {
                                        $class_id = $product['products_tax_class_id'];
                                    } else {
                                        $class_id = $product['tax_class_id'];
                                    }
                                    $zone = \common\helpers\Tax::get_zone_id($class_id, $tax_address['entry_country_id'], $tax_address['entry_zone_id']);
                                    $zone_id = $class_id . '_' . $zone;
                                }
                                if (\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_EDIT_PRODUCT'])) {
                                    //$taxColumn = $this->manager->render('Tax', ['manager' => $this->manager, 'product' => $product, 'tax_address' => $tax_address, 'tax_class_array' => $tax_class_array, 'onchange' => "order.updateProductInRow(this, 'change_tax')" ]);
                                    $tax_column = isset($tax_class_array[$zone_id]) ? '<center>' . $tax_class_array[$zone_id] . '</center>' : '';
                                } else {
                                    $tax_column = isset($tax_class_array[$zone_id]) ? '<center>' . $tax_class_array[$zone_id] . '</center>' : '';
                                }
                            }
                            // UnitPrice
                            $price_column = '';
                            if (\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_EDIT_PRODUCT'])) {
                                if (!$product['ga'] && $product['parent'] == '') {
                                    /*$priceColumn = '<div class="dropdown" data-id="' . $product['id'] . '"><div data-bs-toggle="dropdown" data-bs-auto-close="outside" class="price-edit" data-bs-offset="-150,5">';*/
                                    $price_column .= $this->manager->render('ExtraCharge', ['product' => $product, 'manager' => $this->manager, 'edit' => false]);
                                    $price_column .= $this->manager->render('Price', ['field' => 'result_price', 'price' => $product['final_price'], 'tax' => 0, 'qty' => \common\helpers\Product::get_virtual_item_quantity_value($product['id']), 'currency' => $cart->currency, 'isEditInGrid' => false, 'classname' => 'result-price']);
                                    /*$priceColumn .= '</div><div class="dropdown-menu"><div class="price-editing">';
                                    
                                                                        $priceColumn .= $this->manager->render('ExtraCharge', ['product' => $product, 'manager' => $this->manager]);
                                    
                                                                        $priceColumn .= '</div></div></div>';*/
                                }
                            }
                            $response_item[] = $price_column;
                            // TotalPrice exc vat
                            $response_item[] = '<div class="qtyWrap">' . $qty_column . '</div>';
                            $exc_vat_table = $this->manager->render('Price', ['field' => 'final_price_total_exc_tax', 'price' => $product['final_price'], 'tax' => 0, 'qty' => $product['quantity'], 'currency' => $cart->currency]);
                            $tax_table = $tax_column;
                            $response_item[] = $exc_vat_table . ($tax_table ? '<div><center><strong>' . TABLE_HEADING_TAX . '</strong></center></div>' . $tax_table : '');
                            //vat on order
                            $_rate = $product['tax_rate'];
                            if ($_rate > 0 && $vat_on_order = \common\helpers\Acl::check_extension_allowed('VatOnOrder', 'allowed')) {
                                if ($vat_on_order::check_vat_status($tax_address)) {
                                    $_rate = 0;
                                }
                            }
                            /** @var \common\extensions\BusinessToBusiness\BusinessToBusiness $ext */
                            if ($_rate > 0 && $ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
                                if ($ext::check_tax_rate($customer_groups_id)) {
                                    $_rate = 0;
                                }
                            }
                            // TotalPrice inc vat
                            $response_item[] = $this->manager->render('Price', ['field' => 'final_price_total_inc_tax', 'price' => $product['final_price'], 'tax' => $_rate, 'qty' => $product['quantity'], 'currency' => $cart->currency]);
                            $query_params = array_merge(['editor/show-basket'], $data);
                            $actions_column = '';
                            if ($product['parent'] == '') {
                                if ($product['ga']) {
                                    $actions_column .= '<div class="order-product-edit">';
                                    $actions_column .= \yii\helpers\Html::a('<i class="icon-pencil"></i>', Yii::$app->url_manager->create_url(array_merge($query_params, ['action' => 'show_giveaways', 'edit' => true])), ['class' => 'popup', 'data-class' => 'add-product']);
                                    $actions_column .= '</div>';
                                    $actions_column .= '<div class="del-pt" onclick="deleteOrderGiveaway(this);">';
                                } else {
                                    $actions_column .= '<div class="order-product-edit">';
                                    $actions_column .= \yii\helpers\Html::a('<i class="icon-pencil"></i>', Yii::$app->url_manager->create_url(array_merge($query_params, ['uprid' => $product['id'], 'action' => 'edit_product'])), ['class' => 'popup', 'data-class' => 'edit-product']);
                                    $actions_column .= '</div>';
                                    $actions_column .= '<div class="del-pt" onclick="deleteOrderProduct(this);">';
                                }
                            }
                            $response_item[] = $actions_column;
                            $row_class = '';
                            if (!in_array($product['id'], $saved_products)) {
                                $row_class = ' new-product';
                            }
                            if ($product['parent'] ?? '') {
                                $row_class = ' child-product';
                            }
                            $response_item['DT_RowClass'] = ' dataTableRow product_info' . $row_class;
                            $response_list[] = $response_item;
                        }
                        $records_filtered++;
                    }
                }
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered, 'data' => $response_list];
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    private function get_order_state_array()
    {
        $reason = '';
        return ['UpdateAndPay_available' => $this->manager->is_payment_allowed_ex(true, $reason) && $this->manager->is_payment_selected($reason), 'UpdateAndPay_available_reason' => $reason];
    }
}