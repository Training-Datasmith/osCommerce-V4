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
namespace backend\services;

use common\helpers\Acl;
use common\helpers\Inventory;
use common\helpers\Product;
use common\helpers\Tax;
use Yii;
class Product_Insulator_Service
{
    public $data = [];
    /** @var \common\services\OrderManager $manager */
    public $manager;
    public $uprid;
    public $result = [];
    private $product;
    public $edit = false;
    public function __construct($uprid, $manager)
    {
        $this->uprid = $uprid;
        if (!$this->uprid) {
            throw new \Exception('Products id is not defined');
        }
        $this->product = \common\models\Products::find()->alias('p')->where(['p.products_id' => (int) $this->uprid])->join_with(['productsDescriptions pd' => function ($query) use ($manager) {
            $query->on_condition(['language_id' => (int) $manager->get('languages_id'), 'platform_id' => [intval(\Yii::$app->get('platform')->config($manager->get_platform_id())->get_platform_to_description()), intval(\common\classes\platform::default_id())]])->order_by(new \yii\db\Expression("FIELD(platform_id, {$manager->get_platform_id()}) desc"));
        }])->one();
        $this->set_manager($manager);
    }
    public function get_product()
    {
        return $this->product;
    }
    public function set_data($post = [])
    {
        $this->data = $post;
        $this->data['uprid_new'] = Inventory::get_uprid(Inventory::get_prid($this->uprid), $this->data['id'] ?? null);
        // if attributes was changed while editing
        $this->data['uprid_changed'] = $this->data['uprid_new'] != $this->uprid;
    }
    /**
     * If attributes were changed due editing, $this->uprid and $this->>data['uprid'] point to old uprid
     * new uprid is calculated in $this->setData
     * @return mixed
     */
    public function get_uprid_actual()
    {
        return $this->is_uprid_changed() ? $this->data['uprid_new'] : $this->uprid;
    }
    public function is_uprid_changed()
    {
        return $this->data['uprid_changed'] ?? false;
    }
    public function set_manager($manager)
    {
        $this->manager = $manager;
    }
    public function get_working_product()
    {
        if ($this->edit) {
            $products = $this->manager->get_cart()->get_products($this->uprid);
            $product = array_shift($products);
            //details from basket
        } else {
            $product = $this->product->get_attributes();
            $product['qty'] = 1;
            $product['units'] = 0;
            $product['packs'] = 0;
            $product['packagings'] = 0;
        }
        $product['products_name'] = $this->product->products_descriptions[0]->get_backend_listing_name();
        return $product;
    }
    public function get_product_main_details($skip_ga = false)
    {
        $product = $this->get_working_product();
        $currencies = Yii::$container->get('currencies');
        $product['is_bundle'] = $this->product->is_bundle;
        $product['products_id'] = $this->uprid;
        $product['image'] = \common\classes\Images::get_image((int) $this->uprid, 'Small');
        $product['image_thumb'] = \common\classes\Images::get_image((int) $this->uprid);
        //check giveaway
        if (!$skip_ga) {
            $product['ga'] = \common\helpers\Gifts::get_give_aways($this->uprid);
        }
        $product['gift_wrap_allowed'] = \common\helpers\Gifts::allow_gift_wrap($this->uprid);
        if ($product['gift_wrap_allowed']) {
            $product['gift_wrap_price'] = \common\helpers\Gifts::get_gift_wrap_price($this->uprid) * $currencies->get_market_price_rate(DEFAULT_CURRENCY, $this->manager->get('currency'));
        } else {
            $product['gift_wrap_price'] = 0;
        }
        if ($ext = Acl::check_extension_allowed('PackUnits', 'allowed')) {
            $product['product_details'] = $ext::quantity_box_frontend($product, ['products_id' => $this->uprid]);
            if ($product['is_pack'] ?? null) {
                $product['pack_unit'] = $product['product_details']['product']['pack_unit'];
                $product['packaging'] = $product['product_details']['product']['packaging'];
            }
        }
        $product['edit'] = $this->edit;
        return $product;
    }
    public function get_overwritten()
    {
        $cart = $this->manager->get_cart();
        $overwritten = $cart->get_owerwritten($this->uprid) ?? [];
        //        if (!isset($overwritten['tax_selected'])) {
        //            $productInfo = $cart->get_products($this->uprid);
        //            if (!empty($productInfo)) {
        //                $taxClassId = $productInfo['tax_class_id'] ?? null;
        //                if (is_null($taxClassId)) {
        //                    $taxClassId = \common\models\Products::findOne(['products_id' => \common\helpers\Inventory::get_prid($this->uprid)])->products_tax_class_id ?? 0;
        //                }
        //                $taxRate = $productInfo['tax_rate'] ?? 0;
        //
        //                $rates = \common\helpers\Tax::getOrderTaxRates($taxClassId);
        //                if (is_array($rates)) {
        //                    foreach ($rates as $key => $rate) {
        //                        if ($rate == $taxRate) {
        //                            $selected = $key;
        //                            break;
        //                        }
        //                    }
        //                }
        //                $this->_setProductsTax($this->uprid, $selected??'', $taxRate, $taxClassId);
        //                $overwritten = $cart->getOwerwritten($this->uprid) ?? [];
        //            }
        //        }
        return $overwritten;
    }
    public function get_product_details()
    {
        $cart = $this->manager->get_cart();
        $products_id = intval($this->uprid);
        $attributes = $this->data['id'] ?? null;
        //VL do not pass first random attributes (by ref) if they're not selected.
        // problem if the random uprid is marked as "non-existen" (inventory)
        $_foo = [];
        $uprid = $products_id;
        if (is_array($attributes)) {
            $uprid = Inventory::get_uprid($products_id, $attributes);
            $uprid = Inventory::normalize_id($uprid);
        } else if (strpos($this->uprid, '{')) {
            $uprid = Inventory::normalize_id($this->uprid, $attributes);
        } elseif (Inventory::product_has_inventory($products_id)) {
            $uprid = Inventory::get_first_invetory($products_id);
            if (is_null($attributes)) {
                $uprid = Inventory::normalize_id($uprid, $_foo);
            } else {
                $uprid = Inventory::normalize_id($uprid, $attributes);
            }
        } elseif (\common\helpers\Attributes::has_product_attributes($products_id, true)) {
            $attribute_m = \common\models\Products_Attributes::find()->where(['products_id' => (int) $products_id])->group_by(['options_id'])->order_by('options_values_price')->all();
            if ($attribute_m) {
                $attributes = \yii\helpers\Array_Helper::map($attribute_m, 'options_id', 'options_values_id');
                $uprid = Inventory::get_uprid($uprid, $attributes);
                $uprid = Inventory::normalize_id($uprid);
            }
        }
        if (!is_array($attributes)) {
            $attributes = [];
        }
        $this->result['attributes_box'] = $this->get_attributes_details($attributes);
        if (isset($this->result['stock_indicator']) && is_array($this->result['stock_indicator'])) {
            $this->result['stock_indicator']['quantity_max'] += $cart->get_reserved_quantity($uprid);
        }
        if ($this->product->is_bundle) {
            $this->result['bundle_box'] = $this->get_bundle_details($attributes);
        }
        $this->result['order_quantity'] = \common\helpers\Product::get_product_order_quantity($products_id);
        $this->result['pakcunit_box'] = $this->get_pack_details();
        if ($this->product->products_pctemplates_id) {
            $this->result['configurator_box'] = $this->get_configurator_details();
        }
        $this->result['dicount_box'] = $this->get_discount_details();
        //$this->result['collection_box'] = $this->getCollectionDetails($products_id);
        return $this->get_product_details_clear();
    }
    protected function get_product_details_clear()
    {
        if ($this->result) {
            $currencies = Yii::$container->get('currencies');
            $this->result['product_info'] = [];
            if (!empty($this->result['attributes_box'])) {
                $this->result['product_info']['html_attributes'] = $this->result['attributes_box']['product_attributes_html'];
                //$this->result['product_info']['attributes_array'] = $this->result['attributes_box']['attributes_array'] ?? $this->result['attributes_box']['inventory_array'];
                $this->result['product_info']['product_qty'] = $this->result['attributes_box']['data']['product_qty'];
                $this->result['product_info']['product_qty_virtual'] = \common\helpers\Product::get_virtual_item_quantity($this->result['attributes_box']['data']['current_uprid'], $this->data['qty'] ?? 1);
                // $this->data['qty'];//
                $this->result['product_info']['product_valid'] = $this->result['attributes_box']['data']['product_valid'];
                $this->result['product_info']['product_unit_price'] = $this->result['attributes_box']['data']['product_unit_price'];
                $this->result['product_info']['special_unit_price'] = (float) $this->result['attributes_box']['data']['special_unit_price'];
                $this->result['product_info']['stock_indicator'] = $this->result['attributes_box']['data']['stock_indicator'];
            }
            if ($this->product->is_bundle) {
                $this->result['product_info']['html_bundles'] = $this->result['bundle_box']['bundles_block'];
                $this->result['product_info']['product_unit_price'] = $this->result['bundle_box']['bundles']['actual_bundle_price_unit'];
                $this->result['product_info']['special_unit_price'] = (float) $this->result['attributes_box']['data']['special_unit_price'];
                $this->result['product_info']['stock_indicator'] = $this->result['bundle_box']['bundles']['stock_indicator'];
                $this->result['product_info']['product_valid'] = $this->result['bundle_box']['bundles']['product_valid'];
            }
            $this->result['product_info']['order_quantity_minimal'] = $this->result['order_quantity']['order_quantity_minimal'];
            $this->result['product_info']['order_quantity_max'] = $this->result['order_quantity']['order_quantity_max'];
            $this->result['product_info']['order_quantity_step'] = $this->result['order_quantity']['order_quantity_step'];
            if ($this->result['pakcunit_box']) {
                if ($this->result['pakcunit_box']['product_details']) {
                    $this->result['product_info']['cartoon_details'] = $this->result['pakcunit_box']['product_details'];
                    $this->result['product_info']['product_unit_price'] = $this->result['pakcunit_box']['product_details']['single_price_data']['single_price_base'];
                    $this->result['product_info']['special_unit_price'] = (float) ($this->result['pakcunit_box']['product_details']['special_unit_price'] ?? null);
                }
            }
            if ($this->product->products_pctemplates_id) {
                if ($this->result['configurator_box']['data']['configurator_elements'] ?? false) {
                    $this->result['product_info']['html_configurator'] = $this->result['configurator_box']['product_configurator_html'];
                    $this->result['product_info']['configurator_price'] = $this->result['configurator_box']['data']['configurator_price'];
                    $this->result['product_info']['configurator_price_unit'] = $this->result['configurator_box']['data']['configurator_price_unit'];
                    //$this->result['product_info']['special_unit_price'] = (float)$this->result['configurator_box']['data']['special_price'];//???
                    $this->result['product_info']['product_valid'] = $this->result['configurator_box']['data']['product_valid'];
                    $this->result['product_info']['stock_indicator'] = $this->result['configurator_box']['data']['stock_indicator'];
                }
            }
            if ($this->result['dicount_box']) {
                $this->result['product_info']['html_discount'] = $this->result['dicount_box']['discount_table_html'];
                $this->result['product_info']['discount_table_data'] = $this->result['dicount_box']['discount_table_data'];
            }
            if (!$this->result['product_info']['stock_indicator']['add_to_cart']) {
                $this->result['product_info']['stock_indicator']['quantity_max'] = 0;
            }
        }
        if ($this->edit) {
            $this->result['edit'] = true;
            $cart = $this->manager->get_cart();
            if (($final_price = $cart->get_owerwritten_key($this->uprid, 'final_price')) !== false && $cart->get_owerwritten_key($this->uprid, 'price_changed')) {
                $this->result['product_info']['final_price'] = $final_price;
            }
            //$product = $this->getWorkingProduct();
        }
        return $this->result;
    }
    public function get_attributes_details($attributes)
    {
        $response['data'] = \common\helpers\Attributes::get_details($this->uprid, $attributes, $this->data);
        $response['product_attributes_html'] = '';
        if ($response['data']['attributes_array']) {
            $attr_text = $this->data['attr_text'] ?? \common\classes\Props_Worker_Attr_Text::get_attr_text_cart($this->manager->get_cart(), $this->uprid);
            $response['product_attributes_html'] = $this->manager->render('Attributes', ['attributes' => $response['data']['attributes_array'], 'attrText' => $attr_text]);
        }
        return $response;
    }
    public function get_pack_details()
    {
        $response = [];
        if ($this->product->pack_unit || $this->product->packaging) {
            if ($ext = Acl::check_extension_allowed('PackUnits', 'allowed')) {
                $params = $this->data;
                $params['isAjax'] = true;
                if (isset($this->data['qty'])) {
                    $params['qty'] = is_array($this->data['qty']) ? $this->data['qty'][0] : $this->data['qty'];
                }
                if (!$params['qty']) {
                    $params['qty'] = 1;
                }
                $response['product_details'] = $ext::quantity_box_frontend($params, ['products_id' => $this->uprid]);
                $data = $ext::get_price_pack($this->uprid, true, $params);
                $response['product_details']['single_price_data'] = $data;
            }
        }
        return $response;
    }
    public function get_bundle_details($attributes)
    {
        $bundles = \common\helpers\Bundles::get_details(['products_id' => $this->uprid, 'id' => $attributes]);
        $response['bundles_block'] = '';
        $response['bundles'] = [];
        if ($bundles) {
            $response['bundles'] = $bundles;
            $response['bundles_block'] = $this->manager->render('Bundle', ['products' => $bundles, 'manager' => $this->manager]);
        }
        return $response;
    }
    public function get_discount_details()
    {
        $response = $discounts = [];
        $d_table = \common\helpers\Product::get_products_discount_table($this->uprid, 0, $this->manager->get('customer_groups_id'));
        if ($d_table && is_array($d_table) && count($d_table)) {
            $discounts[] = ['count' => 1, 'price' => \common\helpers\Product::get_products_price($this->uprid)];
            for ($i = 0, $n = sizeof($d_table); $i < $n; $i = $i + 2) {
                if ($d_table[$i] > 0) {
                    $discounts[] = ['count' => $d_table[$i], 'price' => $d_table[$i + 1]];
                }
            }
            $response['discount_table_data'] = $discounts;
            $response['discount_table_html'] = $this->manager->render('QuantityDiscounts', ['discounts' => $discounts]);
        }
        return $response;
    }
    public function get_configurator_details()
    {
        if (!\common\helpers\Acl::check_extension_allowed('ProductConfigurator')) {
            return null;
        }
        $cart = $this->manager->get_cart();
        $response['data'] = \common\extensions\Product_Configurator\helpers\Configurator::get_details($this->data, $this->result['attributes_box']['data']);
        $response['product_configurator_html'] = '';
        if (isset($response['data']['configurator_elements'])) {
            if ($this->edit) {
                $sproducts = $cart->get_subproducts($this->uprid);
                if (is_array($sproducts)) {
                    foreach ($sproducts as $sproduct) {
                        foreach ($response['data']['configurator_elements'] as &$el) {
                            if (strpos($sproduct, $el['selected_uprid']) !== false && $cart->exist_owerwritten($sproduct)) {
                                $el['overwritten'] = $cart->get_owerwritten($sproduct);
                            }
                        }
                    }
                }
            }
            $overwritten = $cart->get_owerwritten($this->uprid);
            // correct qty: when editing qty is count in one template
            // so, when start editing use dividing, when continue do nothing
            if (is_array($response['data']['configurator_elements'])) {
                foreach ($response['data']['configurator_elements'] as &$element) {
                    if (!isset($this->data['elements_qty']) && ($this->data['qty'] ?? 0)) {
                        $element['elements_qty'] = $element['elements_qty'] / $this->data['qty'];
                    }
                    // correct tax_selected from overwriten
                    $name = 'tax_selected_' . $element['selected_id'];
                    if (isset($overwritten[$name])) {
                        $element['tax_selected'] = $overwritten[$name];
                    }
                }
            }
            $response['product_configurator_html'] = $this->manager->render('Configurator', ['elements' => $response['data']['configurator_elements'], 'pctemplates_id' => $response['data']['pctemplates_id'], 'manager' => $this->manager]);
        }
        return $response;
    }
    public function get_collection_details($products_id)
    {
        $this->data['products_id'] = $products_id;
        $response['product_collection_html'] = '';
        $response['data'] = null;
        if ($collections = \common\helpers\Acl::check_extension_allowed_class('ProductsCollections', 'helpers\Collections')) {
            $response['data'] = $collections::get_details($this->data);
        }
        if ($response['data']) {
            $response['product_collection_html'] = $this->manager->render('Collection', ['collection' => $response['data'], 'manager' => $this->manager]);
        }
    }
    public function add_product($replace_existing_product = true)
    {
        $cart = $this->manager->get_cart();
        $_qty = (int) (is_array($this->data['qty']) ? array_sum($this->data['qty']) : $this->data['qty']);
        $_uprid = Inventory::get_uprid($this->uprid, $this->data['id'] ?? null);
        $_uprid = Inventory::normalize_id($_uprid);
        $uprid_new = Inventory::get_uprid(Inventory::get_prid($this->uprid), $this->data['id'] ?? null);
        // if attributes was changed while editing
        $reserved_qty = $cart->get_reserved_quantity($_uprid);
        //+$_qty;
        if (is_array($this->data['qty_'] ?? null)) {
            $pack_qty = [
                //'qty' => $_qty,
                'unit' => (int) $this->data['qty_'][0],
                'pack_unit' => (int) $this->data['qty_'][1],
                'packaging' => (int) $this->data['qty_'][2],
            ];
            if ($ext = \common\helpers\Acl::check_extension_allowed('PackUnits', 'allowed')) {
                $pack_qty['qty'] = $_qty = $ext::recalc_qauntity(Inventory::get_prid($this->uprid), $pack_qty);
            }
        } else {
            $pack_qty = $_qty;
        }
        if (defined('STOCK_CHECK') && STOCK_CHECK == 'true') {
            $product_qty = \common\helpers\Product::get_products_stock($_uprid);
            $stock_indicator = \common\classes\Stock_Indication::product_info(['products_id' => $_uprid, 'products_quantity' => $product_qty]);
            if ($_qty > $reserved_qty) {
                if ($_qty > $product_qty && !$stock_indicator['allow_out_of_stock_add_to_cart']) {
                    $_qty = $product_qty;
                }
                if ($_qty < 1) {
                    $message_stack = Yii::$container->get('message_stack');
                    $p_desc = \common\models\Products_Description::find()->select('products_name')->where(['products_id' => intval($this->uprid), 'language_id' => $this->manager->get('language_id'), 'platform_id' => $this->manager->get_platform_id()])->one();
                    $message_stack->add_session(($p_desc->products_name ?? '') . ' has not enought quantity', 'edit_order');
                    return false;
                }
            }
            if ($_qty < 1) {
                return false;
            }
        }
        $added = null;
        if (is_array($this->data['collections'] ?? null) && count($this->data['collections']) > 1) {
            foreach ($this->data['collections'] as $products_id) {
                if ($products_id > 0) {
                    if ($this->data['collections_qty'][$products_id] > 0) {
                        $qty = (int) $this->data['collections_qty'][$products_id];
                    } else {
                        $qty = 1;
                    }
                    $added = $cart->add_cart((int) $products_id, $qty, $this->data['collections_attr'][$products_id]);
                }
            }
        } else if (is_array($this->data['elements'] ?? null)) {
            $added = $cart->add_configuration($this->data, true, 'add');
        } elseif (is_array($this->data['custom_bundles'] ?? null)) {
            $added = $cart->add_custom_bundle(true, 'add');
        } else {
            $props = Yii::$app->get('PropsHelper')::params_to_xml($this->data, $this->uprid);
            if ($replace_existing_product) {
                if ($this->uprid != $uprid_new) {
                    // after editing product with old uprid ($this->uprid) was changed to new uprid ($_uprid)
                    $pack_qty += $cart->get_quantity($uprid_new);
                    // so add to exisitng qty
                }
            } else {
                $pack_qty += $cart->get_quantity($_uprid);
            }
            $added = $cart->add_cart(Inventory::get_prid($this->uprid), $pack_qty, $this->data['id'] ?? null, false, 0, $this->data['gift_wrap'] ?? null, $props);
        }
        if (!is_null($added)) {
            if (!is_array($added)) {
                $added = [$added];
            }
        }
        //collect manual changes
        $new_added = null;
        if (is_array($added)) {
            foreach ($added as $key => $_added) {
                if ($_added) {
                    if ($key == 0) {
                        //only for main product
                        $new_added = $_added;
                        $this->set_price($_added);
                        $this->set_name($_added);
                    }
                    $this->set_product_tax($_added);
                }
            }
        }
        $this->clear_modified_products($new_added);
        return $_added;
    }
    private function clear_modified_products($new_added)
    {
        if ($this->edit) {
            if (!is_null($new_added) && $new_added != $this->uprid) {
                $cart = $this->manager->get_cart();
                $cart->remove($this->uprid);
            }
        }
    }
    public function add_give_away($gaw_id = null)
    {
        $cart = $this->manager->get_cart();
        if (is_null($gaw_id) && isset($this->data['giveaway_switch'])) {
            $gaw_id = key($this->data['giveaway_switch']);
        }
        if ($gaw_id && $this->data['products_id'] == $this->uprid) {
            if ($cart->is_valid_product_data($this->data['products_id'], isset($this->data['id']) ? $this->data['id'] : '')) {
                return $cart->add_cart($this->data['products_id'], \common\helpers\Gifts::get_max_quantity($this->data['products_id'], $gaw_id)['qty'], isset($this->data['id']) ? $this->data['id'] : '', false, $gaw_id);
            }
        }
        return false;
    }
    public function set_price($cart_uprids)
    {
        $cart = $this->manager->get_cart();
        $_uprid = is_array($cart_uprids) ? array_shift($cart_uprids) : $cart_uprids;
        $product_final_price = $cart->get_products($_uprid)[0]['final_price'] ?? null;
        $final_price = null;
        if (!is_null($this->data['final_price'] ?? null) && !is_null($product_final_price)) {
            $final_price = (float) $this->data['final_price'] * (float) Yii::$container->get('currencies')->get_market_price_rate($this->manager->get('currency'), DEFAULT_CURRENCY);
            if (round($final_price, 2) == round($product_final_price, 2)) {
                $final_price = null;
            }
        }
        if (!is_null($final_price)) {
            $cart->set_overwrite($_uprid, 'final_price', $final_price);
        } else {
            $cart->clear_overwriten_key($_uprid, 'final_price');
        }
    }
    public function set_name($cart_uprids = null)
    {
        if (is_null($cart_uprids)) {
            $cart_uprids = $this->uprid;
        }
        $cart = $this->manager->get_cart();
        $_uprid = is_array($cart_uprids) ? array_shift($cart_uprids) : $cart_uprids;
        if (!is_null($this->data['name']) && ($this->data['name_changed'] ?? null)) {
            $cart->set_overwrite($_uprid, 'name', $this->data['name']);
        } else {
            $cart->clear_overwriten_key($_uprid, 'name');
        }
    }
    private function get_cart_uprid($_part_uprid, $cart_uprids)
    {
        $_part_uprid = preg_quote($_part_uprid);
        if (is_array($cart_uprids)) {
            foreach ($cart_uprids as $_uprid) {
                if (preg_match("/^{$_part_uprid}/", $_uprid)) {
                    return $_uprid;
                }
            }
        } elseif (is_string($cart_uprids)) {
            return preg_match("/^{$_part_uprid}/", $cart_uprids) ? $cart_uprids : false;
        }
        return false;
    }
    private function _set_products_tax_str($uprid, $tax_selected)
    {
        $ex = explode('_', $tax_selected);
        $tax_value = 0;
        if (count($ex) == 2) {
            if ($ex[1] == 0) {
                // class
                $tax_value = $this->manager->get_order_tax_rates($ex[0]);
            } else {
                $tax_value = \common\helpers\Tax::get_tax_rate_value_edit_order($ex[0], $ex[1]);
            }
            $this->_set_products_tax($uprid, $tax_selected, $tax_value, $ex[0]);
        } else {
            $this->_set_products_tax_zero($uprid);
        }
    }
    private function _set_products_tax($cart_uprid, $selected, $rate, $id)
    {
        $cart = $this->manager->get_cart();
        if ($cart->in_cart($cart_uprid)) {
            $cart->set_overwrite($cart_uprid, 'tax_selected', $selected);
            $cart->set_overwrite($cart_uprid, 'tax_rate', $rate);
            $cart->set_overwrite($cart_uprid, 'tax_class_id', $id);
            //$cart->setOverwrite($_uprid, 'tax_description', \common\helpers\Tax::get_tax_description($ex[0], $order->tax_address['entry_country_id'], $ex[1]));
        }
    }
    private function _set_products_tax_zero($cart_uprid)
    {
        $cart = $this->manager->get_cart();
        if ($cart->in_cart($cart_uprid)) {
            $cart->set_overwrite($cart_uprid, 'tax_selected', 0);
            $cart->set_overwrite($cart_uprid, 'tax_rate', 0);
            $cart->set_overwrite($cart_uprid, 'tax_class_id', 0);
            //$cart->setOverwrite($_uprid, 'tax_description', '');
        }
    }
    public function set_product_tax($cart_uprids)
    {
        if (!is_null($this->data['tax'])) {
            $cart = $this->manager->get_cart();
            if (is_array($this->data['tax'])) {
                foreach ($this->data['tax'] as $_part_uprid => $tax) {
                    if ($this->is_uprid_changed()) {
                        $cart_uprid = $this->get_cart_uprid($_part_uprid, $this->uprid) ? $this->get_uprid_actual() : null;
                    } else {
                        $cart_uprid = $this->get_cart_uprid($_part_uprid, $cart_uprids);
                    }
                    if ($cart_uprid) {
                        $this->_set_products_tax_str($cart_uprid, $tax);
                        break;
                    }
                }
            } else {
                $this->_set_products_tax_str($cart_uprids, $this->data['tax']);
            }
        } else if (is_array($cart_uprids)) {
            foreach ($cart_uprids as $cart_uprid) {
                $this->_set_products_tax_zero($cart_uprid);
            }
        }
        $this->set_configurator_tax();
    }
    private function set_configurator_tax()
    {
        if ($this->product->products_pctemplates_id) {
            if (is_array($this->data['tax'])) {
                $cart = $this->manager->get_cart();
                $tax = $this->data['tax'];
                // remove the master tax, array_shift dont suit
                reset($tax);
                // sets internal array pointer to start
                unset($tax[key($tax)]);
                foreach ($tax as $id => $selected_rate) {
                    if (!empty($selected_rate)) {
                        $cart->set_overwrite($this->get_uprid_actual(), 'tax_selected_' . $id, $selected_rate);
                    }
                }
            }
        }
    }
    public $manual_price_changed = false;
    public function set_extra_charge()
    {
        $cart = $this->manager->get_cart();
        $cart->clear_overwriten_key($this->get_uprid_actual(), 'final_price_formula');
        $cart->clear_overwriten_key($this->get_uprid_actual(), 'final_price_formula_data');
        $product = array_shift($cart->get_products($this->uprid));
        $uprid = $this->uprid;
        if ($product) {
            $virtual_quantity = \common\helpers\Product::get_virtual_item_quantity_value($uprid);
            if ($this->manual_price_changed) {
                $this->data['price'] /= $virtual_quantity;
                if (isset($this->data['price']) && $product['final_price'] != $this->data['price']) {
                    if ($product['final_price'] > $this->data['price']) {
                        $this->data['dis_action_fixed'][$uprid] = '-';
                        $this->data['dis_action_fixed_value'][$uprid] = ($product['final_price'] - $this->data['price']) * $virtual_quantity;
                    } elseif ($product['final_price'] < $this->data['price']) {
                        $this->data['dis_action_fixed'][$uprid] = '+';
                        $this->data['dis_action_fixed_value'][$uprid] = ($this->data['price'] - $product['final_price']) * $virtual_quantity;
                    }
                    $this->data['dis_action_percent'][$uprid] = '-';
                    $this->data['dis_action_percent_value'][$uprid] = 0;
                    $cart->set_overwrite($this->get_uprid_actual(), 'price_changed', true);
                }
            }
            if (($this->data['dis_action_fixed_value'][$uprid] ?? false) || ($this->data['dis_action_percent_value'][$uprid] ?? false)) {
                $this->data['dis_action_fixed_value'][$uprid] /= $virtual_quantity;
                $formula = ['final_price', ['action' => 'extra_charge', 'vars' => ['init_value' => $this->data['final_price'], 'percent_action' => $this->data['dis_action_percent'][$uprid], 'percent_value' => floatval($this->data['dis_action_percent_value'][$uprid]), 'fixed_action' => $this->data['dis_action_fixed'][$uprid], 'fixed_value' => abs(floatval($this->data['dis_action_fixed_value'][$uprid]))], 'formula' => '{init_value}{percent_action}({init_value}*({percent_value}/100)){fixed_action}{fixed_value}']];
                $cart->set_overwrite($this->get_uprid_actual(), 'final_price_formula', ['\common\helpers\PriceFormula', 'calculateExtraOrderPrice']);
                $cart->set_overwrite($this->get_uprid_actual(), 'final_price_formula_data', $formula);
            }
        }
    }
    private function fix_data_if_attr_changed()
    {
        $uprid_new = Inventory::get_uprid(Inventory::get_prid($this->uprid), $this->data['id'] ?? null);
        // if attributes was changed while editing
        if ($uprid_new != $this->uprid) {
            // fix: discount losses if attribute was changed
            foreach (['dis_action_fixed', 'dis_action_fixed_value', 'dis_action_percent', 'dis_action_percent_value'] as $item_name) {
                if (!isset($this->data[$item_name][$uprid_new]) && isset($this->data[$item_name][$this->uprid])) {
                    $this->data[$item_name][$uprid_new] = $this->data[$item_name][$this->uprid];
                    unset($this->data[$item_name][$this->uprid]);
                }
            }
        }
    }
}