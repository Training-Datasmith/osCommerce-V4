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
namespace common\classes\extended;

use common\classes\events\common\order\Order_Set_Parent_Event;
use common\classes\shipping;
use common\helpers\Address;
use yii\helpers\Array_Helper;
#[\Allow_Dynamic_Properties]
abstract class Order_Shadow_Abstract implements Order_Interface
{
    public function prepare_order_info()
    {
        $cart = $this->manager->get_cart();
        $currencies = \Yii::$container->get('currencies');
        $shipping = $this->manager->get_shipping();
        $delivery_option_inc = 0;
        $delivery_option_ex = 0;
        if (!($shipping['no_cost'] ?? null)) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('DeliveryOptions', 'allowed')) {
                $delivery_option = $ext::calc_cost($this->manager);
                if (is_array($delivery_option)) {
                    $delivery_option_inc = $delivery_option['cost_inc'];
                    $delivery_option_ex = $delivery_option['cost_ex'];
                }
            }
        }
        $payment = $this->manager->get_payment();
        $this->info = ['order_status' => DEFAULT_ORDERS_STATUS_ID, 'platform_id' => $cart->platform_id, 'department_id' => 0, 'currency' => $cart->currency, 'currency_value' => $currencies->currencies[$cart->currency]['value'] ?? null, 'language_id' => $cart->language_id, 'admin_id' => @$cart->admin_id, 'payment_method' => $payment, 'payment_class' => $payment, 'shipping_class' => @$shipping['id'], 'shipping_weight' => $cart->show_weight(), 'shipping_method' => @$shipping['title'], 'shipping_cost' => @$shipping['cost'] + $delivery_option_inc, 'shipping_no_cost' => @$shipping['no_cost'], 'shipping_cost_inc_tax' => @$shipping['cost_inc_tax'] + $delivery_option_inc, 'shipping_cost_exc_tax' => (isset($shipping['cost_exc_tax']) ? $shipping['cost_exc_tax'] : @$shipping['cost']) + $delivery_option_ex, 'subtotal' => 0, 'subtotal_inc_tax' => 0, 'subtotal_exc_tax' => 0, 'total_paid_exc_tax' => 0, 'total_paid_inc_tax' => 0, 'tax' => 0, 'tax_groups' => [], 'comments' => $this->manager->has('comments') ? $this->manager->get('comments') : '', 'greet_card' => $this->manager->get('greet_card'), 'pointto' => $this->manager->get('pointto'), 'delivery_date' => $this->manager->get('order_delivery_date'), 'purchase_order' => isset($_SESSION['purchase_order']) ? $_SESSION['purchase_order'] : '', 'basket_id' => (int) $cart->basket_id];
        $this->set_payment_status($payment);
        if (($new_status = $cart->get_status_after_paid()) !== false) {
            $this->info['order_status'] = $new_status;
        }
    }
    public function set_payment_status($payment)
    {
        if ($this->manager->get_payment_collection()->is_payment_selected()) {
            $p_module = $this->manager->get_payment_collection()->get_selected_payment();
            if (method_exists($p_module, 'getTitle')) {
                $this->info['payment_method'] = $p_module->get_title($payment);
            } else {
                $this->info['payment_method'] = $p_module->title;
            }
            //$this->info['payment_class'] = $pModule->code;
            if ($this->manager->has('admin_edit_order') && $this->manager->get('admin_edit_order') && \frontend\design\Info::is_totally_admin()) {
                // prevent set default payment status to order on save - edit order
            } else if (isset($p_module->order_status) && is_numeric($p_module->order_status) && $p_module->order_status > 0) {
                $this->info['order_status'] = $p_module->order_status;
            }
        }
    }
    public function prepare_products()
    {
        $cart = $this->manager->get_cart();
        $this->manager->define_order_tax_address();
        $currencies = \Yii::$container->get('currencies');
        /*
                if (\common\helpers\Address::isEmpty($this->tax_address) ) {
                    $this->tax_address = $this->manager->getTaxAddress();
                }*/
        $_def_country = false;
        \common\helpers\Php8::null_arr_props($this->tax_address, ['entry_country_id', 'entry_zone_id']);
        $this->tax_address['entry_country_id'] = $this->tax_address['entry_country_id'] ?? null;
        if (!$this->tax_address['entry_country_id']) {
            $this->tax_address['entry_country_id'] = $this->manager->get_tax_country();
            $_def_country = true;
        }
        if (!$this->tax_address['entry_zone_id'] && $_def_country) {
            $this->tax_address['entry_zone_id'] = $this->manager->get_tax_zone();
        }
        $index = 0;
        $normilize_sp = false;
        if ($ext_sp = \common\helpers\Extensions::is_allowed('SupplierPurchase')) {
            $normilize_sp = true;
        }
        $products = $cart->get_products();
        $_products = [];
        foreach ($products as $product) {
            if (isset($product['linked_products']) && is_array($product['linked_products'])) {
                $linked_products = $product['linked_products'];
                unset($product['linked_products']);
                $_products[] = $product;
                foreach ($linked_products as $linked_product) {
                    $_products[] = $linked_product;
                }
            } else {
                $_products[] = $product;
            }
        }
        $products = $_products;
        foreach (\common\helpers\Hooks::get_list('order/prepare-products/before-loop') as $filename) {
            include $filename;
        }
        for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
            $tax_values = $this->get_tax_values($products[$i]['tax_class_id']);
            $this->products[$index] = [
                'qty' => $products[$i]['quantity'],
                'reserved_qty' => $products[$i]['reserved_qty'],
                'name' => $products[$i]['name'],
                'model' => $products[$i]['model'],
                'stock_info' => $products[$i]['stock_info'],
                'products_file' => $products[$i]['products_file'],
                'is_virtual' => isset($products[$i]['is_virtual']) ? intval($products[$i]['is_virtual']) : 0,
                'gv_state' => preg_match('/^GIFT/', $products[$i]['model']) ? 'pending' : 'none',
                'tax' => $tax_values['tax'],
                'tax_class_id' => $products[$i]['tax_class_id'],
                'tax_description' => $tax_values['tax_description'],
                'props' => Array_Helper::get_value($products, [$i, 'props']),
                'propsData' => isset($products[$i]['propsData']) ? $products[$i]['propsData'] : '',
                'ga' => $products[$i]['ga'],
                'price' => $products[$i]['price'],
                'final_price' => $products[$i]['final_price'],
                /* PC configurator addon begin */
                'template_uprid' => $products[$i]['id'],
                'parent_product' => Array_Helper::get_value($products, [$i, 'parent']),
                'sub_products' => Array_Helper::get_value($products, [$i, 'sub_products']),
                'relation_type' => isset($products[$i]['relation_type']) ? $products[$i]['relation_type'] : '',
                'configurator_price' => $cart->configurator_price($products[$i]['id'], $products),
                /* PC configurator addon end */
                'sort_order' => isset($products[$i]['sort_order']) ? $products[$i]['sort_order'] : $index,
                'weight' => $products[$i]['weight'],
                'gift_wrap_price' => $products[$i]['gift_wrap_price'],
                'gift_wrapped' => $products[$i]['gift_wrapped'],
                'gift_wrap_allowed' => $products[$i]['gift_wrap_allowed'] ?? false,
                'virtual_gift_card' => $products[$i]['virtual_gift_card'] ?? false,
                'id' => $normilize_sp ? $ext_sp::get_uprid($products[$i]['id']) : \common\helpers\Inventory::normalize_id($products[$i]['id']),
                'subscription' => $products[$i]['subscription'],
                'subscription_code' => $products[$i]['subscription_code'],
                'promo_id' => $products[$i]['promo_id'] ?? 0,
                'specials_id' => !empty($products[$i]['special_price']) ? $products[$i]['specials_id'] ?? 0 : 0,
                // {{ Bonus Points
                'bonus_points_price' => Array_Helper::get_value($products, [$i, 'bonus_points_price']),
                'bonus_points_cost' => Array_Helper::get_value($products, [$i, 'bonus_points_cost']),
                // }}
                'overwritten' => $products[$i]['overwritten'],
            ];
            if ($ext = \common\helpers\Acl::check_extension_allowed('PackUnits', 'allowed')) {
                $this->products[$index] = array_merge($ext::cart_order_frontend($index, $cart, $products[$i]), $this->products[$index]);
            }
            if (!$products[$i]['ga'] && $cart->exist_owerwritten($this->products[$index]['id'])) {
                $cart->over_write($this->products[$index]['id'], $this->products[$index]);
            }
            $subindex = 0;
            $bundle_prods_options = [];
            $bundle_prods_options_array = [];
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductBundles', 'allowed')) {
                list($bundle_prods_options_array, $bundle_prods_options) = $ext::cart_order($products[$i], $this->manager->has('customer_groups_id') ? $this->manager->get('customer_groups_id') : DEFAULT_USER_GROUP);
            }
            if ($products[$i]['attributes']) {
                reset($products[$i]['attributes']);
                // {{ Virtual Gift Card
                if (Array_Helper::get_value($products, [$i, 'virtual_gift_card']) && $products[$i]['attributes'][0] > 0) {
                    global $languages_id;
                    $virtual_gift_card = tep_db_fetch_array(tep_db_query('select vgcb.products_id, if(length(pd1.products_name), pd1.products_name, pd.products_name) as products_name, p.products_model, p.products_image, p.products_weight, p.products_tax_class_id, vgcb.products_price, vgcb.virtual_gift_card_recipients_name, vgcb.virtual_gift_card_recipients_email, vgcb.virtual_gift_card_message, vgcb.virtual_gift_card_senders_name from ' . TABLE_VIRTUAL_GIFT_CARD_BASKET . ' vgcb, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_PRODUCTS . ' p left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd1 on pd1.products_id = p.products_id and pd1.language_id = '" . (int) $languages_id . "' and pd1.platform_id = '" . intval(\Yii::$app->get('platform')->config()->get_platform_to_description()) . "' where length(vgcb.virtual_gift_card_code) = 0 and vgcb.virtual_gift_card_basket_id = '" . (int) $products[$i]['attributes'][0] . "' and p.products_id = vgcb.products_id and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and pd.products_id = p.products_id and pd.language_id = '" . (int) $languages_id . "' and " . (!\Yii::$app->user->is_guest ? " vgcb.customers_id = '" . (int) \Yii::$app->user->get_id() . "'" : " vgcb.session_id = '" . tep_session_id() . "'")));
                    $products_options_values_name = "\n";
                    if (tep_not_null($virtual_gift_card['virtual_gift_card_recipients_name'])) {
                        $products_options_values_name .= TEXT_GIFT_CARD_RECIPIENTS_NAME . ' ' . $virtual_gift_card['virtual_gift_card_recipients_name'] . "\n";
                    }
                    if (tep_not_null($virtual_gift_card['virtual_gift_card_recipients_email'])) {
                        $products_options_values_name .= TEXT_GIFT_CARD_RECIPIENTS_EMAIL . ' ' . $virtual_gift_card['virtual_gift_card_recipients_email'] . "\n";
                    }
                    if (tep_not_null($virtual_gift_card['virtual_gift_card_message'])) {
                        $products_options_values_name .= TEXT_GIFT_CARD_MESSAGE . ' ' . $virtual_gift_card['virtual_gift_card_message'] . "\n";
                    }
                    if (tep_not_null($virtual_gift_card['virtual_gift_card_senders_name'])) {
                        $products_options_values_name .= TEXT_GIFT_CARD_SENDERS_NAME . ' ' . $virtual_gift_card['virtual_gift_card_senders_name'] . "\n";
                    }
                    $this->products[$index]['attributes'][$subindex] = ['option' => TEXT_GIFT_CARD_DETAILS, 'value' => $products_options_values_name, 'option_id' => 0, 'value_id' => $products[$i]['attributes'][0]];
                } elseif (is_array($products[$i]['attributes'])) {
                    $attr_text = \common\classes\Props_Worker_Attr_Text::get_attr_text($products[$i]['props'] ?? null);
                    foreach ($products[$i]['attributes'] as $option => $value) {
                        // {{ Products Bundle Sets
                        if (in_array((string) $option, $bundle_prods_options)) {
                            continue;
                        }
                        // }}
                        $attributes_query = tep_db_query('select pa.products_attributes_id, popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from ' . TABLE_PRODUCTS_OPTIONS . ' popt, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' poval, ' . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . (int) $products[$i]['id'] . "' and pa.options_id = '" . (int) $option . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int) $value . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int) $this->info['language_id'] . "' and poval.language_id = '" . (int) $this->info['language_id'] . "'");
                        $attributes = tep_db_fetch_array($attributes_query);
                        $attributes['options_values_price'] = \common\helpers\Attributes::get_options_values_price($attributes['products_attributes_id'] ?? null, $products[$i]['quantity'] ?? 0);
                        if (isset($attributes['products_options_name'])) {
                            $this->products[$index]['attributes'][$subindex] = ['option' => $attributes['products_options_name'], 'value' => isset($attr_text[$option]) && !empty($attr_text[$option]) ? $attr_text[$option] : $attributes['products_options_values_name'] ?? null, 'option_id' => $option, 'value_id' => $value, 'prefix' => $attributes['price_prefix'] ?? null, 'price' => $attributes['options_values_price'] ?? null];
                        }
                        $subindex++;
                    }
                }
            }
            // {{ Products Bundle Sets
            foreach ($bundle_prods_options_array as $bundle_prods_option) {
                $this->products[$index]['attributes'][$subindex] = $bundle_prods_option;
                $subindex++;
            }
            // }}
            if ($products[$i]['gift_wrapped']) {
                if (!is_array($this->products[$index])) {
                    $this->products[$index] = [];
                }
                $this->products[$index]['attributes'][] = ['option' => GIFT_WRAP_OPTION, 'value' => GIFT_WRAP_VALUE_YES, 'option_id' => -2, 'value_id' => -2];
            }
            $index++;
        }
        foreach (\common\helpers\Hooks::get_list('order/prepare-products/after-loop') as $filename) {
            include $filename;
        }
        $this->compact_linked_products();
    }
    public function _set_address($address)
    {
        $vat_status = self::get_address_item($address, 'company_vat');
        /** @var \common\extensions\VatOnOrder\VatOnOrder $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('VatOnOrder', 'allowed')) {
            $vat_status = $ext::check_vat_status($address);
            if ($vat_status > 1) {
                $address['entry_company_vat'] = \common\helpers\Validations::sanitize_vat_id(self::get_address_item($address, 'company_vat'));
            }
        }
        $customs_number_status = !empty(self::get_address_item($address, 'customs_number')) || empty(self::get_address_item($address, 'company'));
        if ($ext = \common\helpers\Acl::check_extension_allowed('CustomersMultiEmails', 'allowed')) {
            if ($this->manager->get('is_multi') == 1) {
                $address['entry_firstname'] = $this->manager->get('customer_first_name');
                $address['entry_lastname'] = $this->manager->get('customer_last_name');
            }
        }
        // fix bug: $address may be in 2 formats: ['entry_firstname'] and ['firstname']
        // the second comes from manager->get('sendto'). Example:
        //                $sendto = $this->manager->get('sendto');
        //                if (is_array($sendto)) {
        //                    $address = $sendto;
        //                } else {
        //                    $address = $customer->getAddressBook($sendto, true);
        //                }
        //                if ($address) {
        //                    $this->delivery = $this->_setAddress($address);
        //                }
        //        var1 = [
        //            'address_book_id' => 208801,
        //            'customers_id' => 2215,
        //            'entry_gender' => 'm',
        //            'entry_company' => '',
        //            'entry_firstname' => 'Eee',
        //            'entry_lastname' => 'Hhhhhhhh',
        //            'entry_street_address' => '',
        //            'entry_suburb' => '',
        //            'entry_postcode' => '',
        //            'entry_city' => '',
        //            'entry_state' => 'Illinois',
        //            'entry_country_id' => 150,
        //            'entry_zone_id' => 0,
        //            'entry_company_vat' => '',
        //            'entry_telephone' => '',
        //            '_api_time_modified' => '2023-10-10 15:35:06',
        //            'entry_company_vat_date' => null,
        //            'entry_company_vat_status' => 0,
        //            'entry_customs_number' => null,
        //            'entry_customs_number_status' => 0,
        //            'entry_customs_number_date' => null,
        //            'entry_email_address' => '',
        //            'drop_ship' => 0,
        //            'country' => [
        //                'countries_id' => 150,
        //                'countries_name' => 'Netherlands',
        //                'countries_iso_code_2' => 'NL',
        //                'countries_iso_code_3' => 'NLD',
        //                'address_format_id' => 5,
        //                'language_id' => 1,
        //                'status' => 1,
        //                'sort_order' => 1,
        //                'lat' => 52.2093658,
        //                'lng' => 4.158453,
        //                'zoom' => '8.0000',
        //                'vat_code_type' => 1,
        //                'vat_code_prefix' => 'NL',
        //                'vat_code_chars' => '12',
        //                'dialling_prefix' => '+31',
        //                'currency_code' => '',
        //                'minimum_order_value' => '0.00',
        //            ],
        //        var2 = [
        //            'name' => 'Eee Hhhhhhh',
        //            'gender' => 'm',
        //            'firstname' => 'Eee',
        //            'lastname' => 'Hhhhhhhh',
        //            'company' => '',
        //            'company_vat' => '',
        //            'company_vat_status' => '0',
        //            'customs_number' => '',
        //            'customs_number_status' => 1,
        //            'telephone' => '',
        //            'email_address' => '',
        //            'street_address' => '',
        //            'suburb' => '',
        //            'city' => '',
        //            'postcode' => '',
        //            'state' => 'Illinois',
        //            'country' => [
        //                'id' => '150',
        //                'title' => 'Netherlands',
        //                'iso_code_2' => 'NL',
        //                'iso_code_3' => 'NLD',
        //                'address_format_id' => '5',
        //                'dialling_prefix' => '+31',
        //                'zoom' => '8.0000',
        //                'lng' => '4.1584530',
        //                'lat' => '52.2093658',
        //            ],
        //            'address_book_id' => 208801,
        //            'format_id' => 5,
        //            'zone_id' => 0,
        //            'country_id' => '150',
        //        ]
        $state = trim(self::get_address_item($address, 'state'));
        return ['address_book_id' => $address['address_book_id'] ?? null, 'gender' => self::get_address_item($address, 'gender'), 'firstname' => self::get_address_item($address, 'firstname'), 'lastname' => self::get_address_item($address, 'lastname'), 'telephone' => self::get_address_item($address, 'telephone') ?? $this->customer['telephone'] ?? null, 'email_address' => self::get_address_item($address, 'email_address') ?? $this->customer['email_address'] ?? null, 'company' => self::get_address_item($address, 'company'), 'company_vat' => self::get_address_item($address, 'company_vat'), 'company_vat_status' => $vat_status, 'customs_number' => self::get_address_item($address, 'customs_number'), 'customs_number_status' => $customs_number_status, 'street_address' => self::get_address_item($address, 'street_address'), 'suburb' => self::get_address_item($address, 'suburb'), 'city' => self::get_address_item($address, 'city'), 'postcode' => self::get_address_item($address, 'postcode'), 'state' => empty($state) ? \common\helpers\Zones::get_zone_name(self::get_address_item($address, 'country_id'), self::get_address_item($address, 'zone_id'), '') : $state, 'zone_id' => self::get_address_item($address, 'zone_id'), 'country' => ['id' => $address['country']['countries_id'] ?? $address['country']['id'] ?? null, 'title' => $address['country']['countries_name'] ?? $address['country']['title'] ?? null, 'iso_code_2' => $address['country']['countries_iso_code_2'] ?? $address['country']['iso_code_2'] ?? null, 'iso_code_3' => $address['country']['countries_iso_code_3'] ?? $address['country']['so_code_3'] ?? null], 'country_id' => self::get_address_item($address, 'country_id'), 'format_id' => $address['country']['address_format_id'] ?? null];
    }
    private static function get_address_item($array, $item_key)
    {
        return $array['entry_' . $item_key] ?? $array[$item_key] ?? null;
    }
    public function prepare_order_addresses()
    {
        global $languages_id;
        $this->customer = [];
        $this->delivery = [];
        $this->billing = [];
        if ($this->manager->is_customer_assigned()) {
            $customer = $this->manager->get_customers_identity();
            if ($customer) {
                $this->customer = ['id' => $customer->customers_id, 'customer_id' => $customer->customers_id, 'gender' => $customer->customers_gender, 'name' => $customer->customers_firstname . ' ' . $customer->customers_lastname, 'firstname' => $customer->customers_firstname, 'lastname' => $customer->customers_lastname, 'telephone' => $customer->customers_telephone, 'landline' => $customer->customers_landline, 'email_address' => $customer->customers_email_address];
                if ($this->manager->get('is_multi') == 1) {
                    $this->customer['email_address'] = $this->manager->get('customer_email_address');
                }
                $address = $customer->get_default_address()->as_array()->one();
                if ($address) {
                    $this->customer = array_merge($this->customer, ['address_book_id' => $address['address_book_id'], 'street_address' => $address['entry_street_address'], 'suburb' => $address['entry_suburb'], 'city' => $address['entry_city'], 'postcode' => $address['entry_postcode'], 'state' => tep_not_null($address['entry_state']) ? $address['entry_state'] : \common\helpers\Zones::get_zone_name($address['entry_country_id'], $address['entry_zone_id'], ''), 'zone_id' => $address['entry_zone_id'], 'country' => ['id' => $address['country']['countries_id'], 'title' => $address['country']['countries_name'], 'iso_code_2' => $address['country']['countries_iso_code_2'], 'iso_code_3' => $address['country']['countries_iso_code_3']], 'format_id' => $address['country']['address_format_id'], 'company' => $address['entry_company'], 'company_vat' => $address['entry_company_vat'], 'customs_number' => $address['entry_customs_number']]);
                }
                $sendto = $this->manager->get('sendto');
                if (is_array($sendto)) {
                    $address = $sendto;
                } else {
                    $address = $customer->get_address_book($sendto, true);
                }
                if ($address) {
                    $this->delivery = $this->_set_address($address);
                }
                $billto = $this->manager->get('billto');
                if (is_array($billto)) {
                    $address = $billto;
                } else {
                    $address = $customer->get_address_book($this->manager->get('billto'), true);
                }
                if ($address) {
                    $this->billing = $this->_set_address($address);
                }
            }
        } else {
            if (is_array($this->manager->get('sendto'))) {
                $this->delivery = $this->manager->get('sendto');
                if ($this->delivery['country_iso_code_2'] ?? null) {
                    $_country_info = \common\helpers\Country::get_country_info_by_iso($this->delivery['country_iso_code_2']);
                    if ($_country_info) {
                        $this->delivery['country'] = $_country_info;
                        $this->delivery['format_id'] = $_country_info['address_format_id'];
                    }
                }
            }
            if (is_array($this->manager->get('billto'))) {
                $this->billing = $this->manager->get('billto');
                if ($this->billing['country_iso_code_2'] ?? null) {
                    $_country_info = \common\helpers\Country::get_country_info_by_iso($this->billing['country_iso_code_2']);
                    if ($_country_info) {
                        $this->billing['country'] = $_country_info;
                        $this->billing['format_id'] = $_country_info['address_format_id'];
                    }
                }
            }
            //$this->customer = $this->billing ? $this->billing : $this->delivery;
        }
    }
    /**
     * return cancelled product quantity
     * @param array $product - item from orders products
     * @return int
     */
    protected function get_cancelled_qty($product)
    {
        $cancelled = 0;
        $cart = $this->manager->get_cart();
        if (is_object($cart) && $cart->order_id) {
            //in admin
            $query = $this->get_products_ar_model()->where(['orders_id' => $cart->order_id, 'uprid' => (string) $product['id'], 'products_id' => (int) $product['id']]);
            if ($query->exists()) {
                $p_model = $query->one();
                if ($p_model->has_attribute('qty_cnld')) {
                    $cancelled = $p_model->qty_cnld;
                }
            }
        }
        return $cancelled;
    }
    private static function is_prices_with_tax()
    {
        // right function is Tax::displayTaxable() but old code still used DISPLAY_PRICE_WITH_TAX without checking taxable widget
        // so DISPLAY_PRICE_WITH_TAX here to correct taxable price if widget is turned off and DISPLAY_PRICE_WITH_TAX == true
        return DISPLAY_PRICE_WITH_TAX == 'true' || \common\helpers\Tax::display_taxable();
    }
    public function prepare_order_info_totals()
    {
        if (!$this->products) {
            $this->prepare_products();
        }
        if (is_array($this->products)) {
            $currencies = \Yii::$container->get('currencies');
            $currency = \Yii::$app->settings->get('currency');
            if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
                $currency = DEFAULT_CURRENCY;
                \Yii::$app->settings->set('currency', $currency);
            }
            $_round_to = $currencies->currencies[$currency]['decimal_places'];
            for ($index = 0; $index < count($this->products); $index++) {
                $cancelled_qty = $this->get_cancelled_qty($this->products[$index]);
                // double rounding compensation (1st to 6 digits) in $currencies->calculate_price()
                // fix error when $this->info['subtotal'] was not eq to $this->info['subtotal_exc_tax']
                $_price = round($this->products[$index]['final_price'], 6);
                $_tax = $this->products[$index]['tax'];
                $_qty = $this->products[$index]['qty'] - $cancelled_qty;
                $shown_price = $currencies->calculate_price($_price, $_tax, $_qty);
                $this->info['subtotal'] += $shown_price;
                if (defined('PRICE_WITH_BACK_TAX') && PRICE_WITH_BACK_TAX == 'True') {
                    if ($_tax > 0) {
                        $this->info['subtotal_exc_tax'] += \common\helpers\Tax::reduce_tax_always($_price * $_qty, $_tax);
                        $this->info['subtotal_inc_tax'] += $_price * $_qty;
                    } else {
                        $this->info['subtotal_exc_tax'] += \common\helpers\Tax::reduce_tax_always($_price * $_qty, abs($_tax));
                        $this->info['subtotal_inc_tax'] += \common\helpers\Tax::reduce_tax_always($_price * $_qty, abs($_tax));
                    }
                } else {
                    if (defined('PRODUCTS_PRICE_QTY_ROUND') && PRODUCTS_PRICE_QTY_ROUND == 'true') {
                        $this->info['subtotal_exc_tax'] += round($_price, $_round_to) * $_qty;
                    } else {
                        $this->info['subtotal_exc_tax'] += round($_price * $_qty, $_round_to);
                    }
                    $this->info['subtotal_inc_tax'] += $currencies->calculate_price($_price, $_tax, $_qty, '', true);
                    // add_tax_always
                }
                $products_tax = abs($this->products[$index]['tax']);
                $products_tax_description = $this->products[$index]['tax_description'] ?? '';
                if (self::is_prices_with_tax()) {
                    if ($_tax > 0) {
                        $this->info['tax'] += \common\helpers\Tax::round_tax($shown_price - $shown_price / ($products_tax < 10 ? '1.0' . str_replace('.', '', $products_tax) : '1.' . str_replace('.', '', $products_tax)));
                        if (isset($this->info['tax_groups']["{$products_tax_description}"])) {
                            $this->info['tax_groups']["{$products_tax_description}"] += \common\helpers\Tax::round_tax($shown_price - $shown_price / ($products_tax < 10 ? '1.0' . str_replace('.', '', $products_tax) : '1.' . str_replace('.', '', $products_tax)));
                        } else {
                            $this->info['tax_groups']["{$products_tax_description}"] = \common\helpers\Tax::round_tax($shown_price - $shown_price / ($products_tax < 10 ? '1.0' . str_replace('.', '', $products_tax) : '1.' . str_replace('.', '', $products_tax)));
                        }
                    }
                } else {
                    if (defined('PRODUCTS_PRICE_EXC_ROUND') && PRODUCTS_PRICE_EXC_ROUND == 'true') {
                        $shown_price = $_price * $_qty;
                        // Price excl tax should not be rounded
                    }
                    $this->info['tax'] += \common\helpers\Tax::round_tax(\common\helpers\Tax::calculate_tax($shown_price, $products_tax));
                    if (isset($this->info['tax_groups']["{$products_tax_description}"])) {
                        $this->info['tax_groups']["{$products_tax_description}"] += \common\helpers\Tax::round_tax(\common\helpers\Tax::calculate_tax($shown_price, $products_tax));
                    } else {
                        $this->info['tax_groups']["{$products_tax_description}"] = \common\helpers\Tax::round_tax(\common\helpers\Tax::calculate_tax($shown_price, $products_tax));
                    }
                }
            }
            $this->info['total_inc_tax'] = $this->info['subtotal_inc_tax'] + $this->info['shipping_cost_inc_tax'];
            //$this->info['shipping_cost_exc_tax'];
            $this->info['total_exc_tax'] = $this->info['subtotal_exc_tax'] + $this->info['shipping_cost_exc_tax'];
            /* if (($values = $cart->getTotalKey('ot_paid')) !== false) {
               if (is_array($values)) {
               $this->info['total_paid_exc_tax'] = $values['ex'];
               $this->info['total_paid_inc_tax'] = $values['in'];
               }
               } */
            /*if (PRICE_WITH_BACK_TAX == 'True') {
                  $this->info['total'] = $this->info['subtotal'] + $this->info['shipping_cost'];
              } elseif (self::isPricesWithTax()) {
                  $this->info['total'] = $this->info['subtotal'] + $this->info['shipping_cost'];
              } else {
                  $this->info['total'] = $this->info['subtotal'] + $this->info['tax'] + $this->info['shipping_cost'];
              }*/
            $this->info['total'] = $this->info['total_inc_tax'];
        }
    }
    /**
     * Save parent class type to real Order
     * @param type $order_id
     */
    public function set_parent($order_id)
    {
        if ($order_id) {
            $op_model = new \common\models\Orders_Parent();
            $op_model->orders_id = $order_id;
            $op_model->owner_class = get_called_class();
            if ($op_model->save(false)) {
                \Yii::$container->get('eventDispatcher')->dispatch(new Order_Set_Parent_Event($this, $order_id));
            }
        }
    }
    public function add_legend($comment, $admin_id)
    {
        if ($this->order_id && ($comment || $admin_id)) {
            $sql_data_array = ['orders_id' => $this->order_id, 'comments' => $comment, 'admin_id' => (int) $admin_id, 'date_added' => 'now()'];
            tep_db_perform($this->table_prefix . TABLE_ORDERS_HISTORY, $sql_data_array);
        }
    }
    public function get_tax_values($tax_class_id)
    {
        if (defined('TAX_ADDRESS_OPTION') && (int) TAX_ADDRESS_OPTION == 1) {
            // by shipping address
            if ($this->manager->is_shipping_needed() || $this->with_delivery) {
                $check_delivery = $this->manager->get_delivery_address();
            } else {
                $check_delivery = $this->manager->get_billing_address();
            }
            $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->info['platform_id'], $tax_class_id, Address::extract_country_id($check_delivery), $check_delivery['zone_id'] ?? 0);
        } elseif (defined('TAX_ADDRESS_OPTION') && (int) TAX_ADDRESS_OPTION == 0) {
            // by billing address
            $check_billing = $this->manager->get_billing_address();
            $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->info['platform_id'], $tax_class_id, Address::extract_country_id($check_billing), $check_billing['zone_id'] ?? 0);
        } else {
            // Seems DAA specific - any of (on checkout only)
            $check_delivery = $this->manager->get_delivery_address();
            $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->info['platform_id'], $tax_class_id, Address::extract_country_id($check_delivery), $check_delivery['zone_id'] ?? 0);
            if ($delivery_tax_values['tax'] > 0) {
            } else {
                $check_billing = $this->manager->get_billing_address();
                $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->info['platform_id'], $tax_class_id, Address::extract_country_id($check_billing), $check_billing['zone_id'] ?? 0);
            }
        }
        return $delivery_tax_values;
    }
    public function has_transactions()
    {
        return false;
    }
    public function maintain_splittering()
    {
        return false;
    }
    /**
     * get default values for new (O)rders model
     * @param \yii\db\ActiveRecord $arModel
     * @return \yii\db\ActiveRecord
     */
    public static function get_ar_model_new(\yii\db\Active_Record $ar_model)
    {
        if ($ar_model) {
            if ($ar_model->is_new_record) {
                $ar_model->load_default_values();
            }
        }
        return $ar_model;
    }
}