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

/*refactoring: $GLOBALS => $this->include_modules */
use yii\helpers\Array_Helper;
class shipping extends modules\Module_Collection
{
    public $modules;
    protected $include_modules = [];
    private $manager;
    // class constructor
    public function __construct($module, \common\services\Order_Manager $manager)
    {
        global $PHP_SELF;
        if (defined('MODULE_SHIPPING_INSTALLED') && tep_not_null(MODULE_SHIPPING_INSTALLED)) {
            $this->modules = explode(';', MODULE_SHIPPING_INSTALLED);
            $include_modules = [];
            $this->manager = $manager;
            if (tep_not_null($module) && in_array(substr($module['id'], 0, strpos($module['id'], '_')) . '.' . substr($PHP_SELF, strrpos($PHP_SELF, '.') + 1), $this->modules)) {
                $include_modules[] = ['class' => substr($module['id'], 0, strpos($module['id'], '_')), 'file' => substr($module['id'], 0, strpos($module['id'], '_')) . '.' . substr($PHP_SELF, strrpos($PHP_SELF, '.') + 1)];
            } else if (false && defined('MODULE_SHIPPING_FREESHIPPER_STATUS') && (MODULE_SHIPPING_FREESHIPPER_STATUS == '1' || MODULE_SHIPPING_FREESHIPPER_STATUS == 'True') and $manager->get_cart()->show_weight() == 0) {
                $include_modules[] = ['class' => 'freeshipper', 'file' => 'freeshipper.php'];
            } else if (is_array($this->modules)) {
                foreach ($this->modules as $value) {
                    //$value = basename(str_replace('\\', '/', $value));
                    $class = substr($value, 0, strrpos($value, '.'));
                    // Don't show Free Shipping Module
                    if (true || $class != 'freeshipper') {
                        $include_modules[] = ['class' => $class, 'file' => $value];
                    }
                }
            }
            \common\helpers\Translation::init('shipping');
            //$this->include_modules = $include_modules;
            $builder = new \common\classes\modules\Module_Builder($manager);
            foreach ($include_modules as $include_module) {
                $class = $include_module['class'];
                $module = "\\common\\modules\\orderShipping\\{$class}";
                if (!class_exists($module)) {
                    continue;
                }
                $this->include_modules[$class] = $builder(['class' => $module]);
                if (!is_null($manager)) {
                    $this->include_modules[$class]->set_platform($manager->get('platform_id') ?? \Yii::$app->get('platform')->config()->get_id());
                }
            }
        }
        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER') && defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') {
            $this->set_free_shipping_over(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER);
        }
    }
    public function get_included_modules()
    {
        return $this->include_modules;
    }
    public function get_enabled_modules()
    {
        static $enabled = null;
        if (is_null($enabled)) {
            /** @var \common\extensions\CustomerModules\CustomerModules $CustomerModules */
            //$CustomerModules = \common\helpers\Acl::checkExtensionAllowed('CustomerModules', 'allowed');
            $enabled = [];
            foreach ($this->include_modules as $class => $module) {
                /*$forceCustomer = false;
                                if ($CustomerModules && !\Yii::$app->user->isGuest) {
                                  if ($CustomerModules::checkForceAllowed(\common\classes\platform::currentId(), \Yii::$app->user->getId(), $class)) {
                                    $forceCustomer = true;
                                  }
                                }
                */
                if ($module->enabled) {
                    $enabled[$class] = $module;
                }
            }
        }
        return $enabled;
    }
    public function get($class)
    {
        return $this->get_enabled_modules()[$class] ?? false;
    }
    public function has($class)
    {
        return isset($this->include_modules[$class]) ? $this->include_modules[$class] : false;
    }
    protected $surcharge = null;
    public $shipping_weight;
    public $shipping_quoted = '';
    public $shipping_num_boxes = 1;
    public function claculate_shipping_elements()
    {
        $this->shipping_weight = $this->manager->get('total_weight');
        if (SHIPPING_BOX_WEIGHT >= $this->shipping_weight * SHIPPING_BOX_PADDING / 100) {
            $this->shipping_weight += SHIPPING_BOX_WEIGHT;
        } else {
            $this->shipping_weight += $this->shipping_weight * SHIPPING_BOX_PADDING / 100;
        }
        if ($this->shipping_weight > SHIPPING_MAX_WEIGHT) {
            // Split into many boxes
            $this->shipping_num_boxes = ceil($this->shipping_weight / max(SHIPPING_MAX_WEIGHT, 1));
            $this->shipping_weight /= $this->shipping_num_boxes;
        }
    }
    public function get_surcharge()
    {
        static $per_warehouse_additional_charge;
        if (!is_array($per_warehouse_additional_charge)) {
            $per_warehouse_additional_charge = Array_Helper::map(\common\models\Warehouses::find()->where(['>', 'shipping_additional_charge', 0])->select(['warehouse_id', 'shipping_additional_charge'])->as_array()->all(), 'warehouse_id', 'shipping_additional_charge');
        }
        if (is_null($this->surcharge)) {
            $this->surcharge = 0;
            $additional_warehouse_charge = 0;
            $products = $this->manager->get_cart()->get_products();
            $products_ids = array_map('intval', Array_Helper::get_column($products, 'id'));
            $currencies = \Yii::$container->get('currencies');
            $groups_id = 0;
            if (!\Yii::$app->user->is_guest) {
                $groups_id = \Yii::$app->user->get_identity()->groups_id;
            } elseif (\Yii::$app->user->is_guest && defined('DEFAULT_USER_GROUP')) {
                $groups_id = (int) DEFAULT_USER_GROUP;
            }
            if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
                $q = \common\models\Products_Prices::find()->select('shipping_surcharge_price, products_id')->where(['products_id' => $products_ids, 'groups_id' => $groups_id, 'currencies_id' => defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True' ? (int) \Yii::$app->settings->get('currency_id') : 0]);
                if (defined('SHIPPING_SURCHARGE_ONE_TIME_CART') && SHIPPING_SURCHARGE_ONE_TIME_CART == 'True') {
                    $q->order_by('shipping_surcharge_price desc')->limit(1);
                }
            } else {
                $q = \common\models\Products::find()->select('shipping_surcharge_price, products_id')->where(['products_id' => $products_ids]);
                if (defined('SHIPPING_SURCHARGE_ONE_TIME_CART') && SHIPPING_SURCHARGE_ONE_TIME_CART == 'True') {
                    $q->order_by('shipping_surcharge_price desc')->limit(1);
                }
            }
            $m_product = $q->as_array()->index_by('products_id')->column();
            foreach ($products as $product) {
                if (!empty($m_product[(int) $product['id']])) {
                    if (defined('SHIPPING_SURCHARGE_ONE_TIME') && SHIPPING_SURCHARGE_ONE_TIME == 'True') {
                        $this->surcharge += $m_product[(int) $product['id']];
                        unset($m_product[(int) $product['id']]);
                    } elseif (defined('SHIPPING_SURCHARGE_ONE_TIME_VARIATION') && SHIPPING_SURCHARGE_ONE_TIME_VARIATION == 'True') {
                        $this->surcharge += $m_product[(int) $product['id']];
                    } else {
                        $this->surcharge += $m_product[(int) $product['id']] * $product['quantity'];
                    }
                }
                if (count($per_warehouse_additional_charge) > 0) {
                    foreach ($per_warehouse_additional_charge as $check_warehouse_id => $additional_charge) {
                        if (\common\helpers\Warehouses::get_products_quantity($product['id'], $check_warehouse_id) > 0) {
                            $additional_warehouse_charge = max($additional_warehouse_charge, $additional_charge);
                        }
                    }
                }
            }
            $this->surcharge += $additional_warehouse_charge;
        }
        return $this->surcharge;
    }
    public $pickup_quotes = [];
    public $delivery_quotes = [];
    public $delivery_methods_count = 0;
    public $pickup_methods_count = 0;
    public $all_methods_count = 0;
    private $free_shipping_over;
    public function set_free_shipping_over($amount)
    {
        $this->free_shipping_over = (float) $amount;
    }
    public function get_free_shipping_over()
    {
        return $this->free_shipping_over;
    }
    public function quote($method = '', $module = '', $visibility = ['shop_order', 'shop_quote', 'shop_sample', 'admin', 'pos'], $groups_id = 0)
    {
        $visibility = \common\helpers\Extensions::get_visibility_variants($visibility);
        if ($groups_id == 0 && !\Yii::$app->user->is_guest) {
            $groups_id = \Yii::$app->user->get_identity()->groups_id;
        } elseif (empty($groups_id) && \Yii::$app->user->is_guest && defined('DEFAULT_USER_GROUP')) {
            $groups_id = (int) DEFAULT_USER_GROUP;
        }
        $quotes_array = [];
        $this->surcharge = null;
        $this->delivery_quotes = [];
        $this->pickup_quotes = [];
        if (\common\helpers\Acl::check_extension_allowed('FraudAddress', 'allowed') && is_object($this->manager)) {
            $fraud_checker = \common\extensions\Fraud_Address\Fraud_Address::checkout_checker($this->manager);
            if ($fraud_checker && $fraud_checker->is_suspect()) {
                \Yii::info('Detected fraud checkout ' . var_export($fraud_checker->suspect_details(), true), 'events');
                if (!\common\extensions\Fraud_Address\Fraud_Address::allow_fraud_checkout()) {
                    $this->delivery_quotes = \common\extensions\Fraud_Address\Fraud_Address::fraud_shipping_quotes();
                    return $this->delivery_quotes;
                }
            }
        }
        if ($method == 'free' && $module == 'free') {
            $currencies = \Yii::$container->get('currencies');
            return [['id' => 'free', 'module' => FREE_SHIPPING_TITLE, 'methods' => [['id' => 'free', 'selected' => true, 'title' => sprintf(FREE_SHIPPING_DESCRIPTION, $currencies->format($this->get_free_shipping_over())), 'code' => 'free_free', 'cost_f' => '&nbsp;', 'cost' => 0]]]];
        }
        if (is_array($this->modules)) {
            /** @var \common\extensions\CustomerModules\CustomerModules $CustomerModules */
            $customer_modules = \common\helpers\Acl::check_extension_allowed('CustomerModules', 'allowed');
            $this->claculate_shipping_elements();
            $include_quotes = [];
            foreach ($this->include_modules as $_module) {
                if ($_module->get_group_visibily(\common\classes\platform::current_id(), $groups_id) || !empty($customer_modules) && $customer_modules::check_allowed(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $_module->code, 'shipping')) {
                    $force_customer = false;
                    if ($customer_modules && !\Yii::$app->user->is_guest) {
                        if ($customer_modules::check_force_allowed(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $_module->code, 'shipping')) {
                            $force_customer = true;
                        }
                    }
                    if (tep_not_null($module)) {
                        if ($module == $_module->code && ($_module->enabled || $force_customer) && $_module->get_visibily(\common\classes\platform::current_id(), $visibility)) {
                            $include_quotes[] = $_module;
                        }
                    } elseif (($_module->enabled || $force_customer) && $_module->get_visibily(\common\classes\platform::current_id(), $visibility)) {
                        $include_quotes[] = $_module;
                    }
                }
            }
            if ($include_quotes) {
                foreach ($include_quotes as $_module) {
                    $_module->set_weight($this->shipping_weight);
                    $_module->set_num_boxes($this->shipping_num_boxes);
                    $quotes = $_module->quote($method, '', $visibility);
                    if (property_exists($_module, 'tax_class') && $_module->use_delivery()) {
                        if ($_module->tax_class > 0) {
                            $response = $_module->get_tax_values($_module->tax_class);
                            if (is_array($quotes) and is_array($response)) {
                                $quotes['tax'] = $response['tax'];
                            }
                        }
                    }
                    if (is_array($quotes)) {
                        $module_ignored = false;
                        foreach (\common\helpers\Hooks::get_list('shipping/check-ignored') as $filename) {
                            $module_ignored = include $filename;
                            if ($module_ignored === true) {
                                break;
                            }
                        }
                        if ($module_ignored === true) {
                            continue;
                        }
                        if ($customer_modules && !\Yii::$app->user->is_guest) {
                            if (is_array($quotes['methods'])) {
                                foreach ($quotes['methods'] as $key => $value) {
                                    if (!$customer_modules::check_available(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $quotes['id'], 'shipping', $value['id'])) {
                                        unset($quotes['methods'][$key]);
                                    }
                                }
                                if (count($quotes['methods']) == 0) {
                                    continue;
                                }
                            } else if (!$customer_modules::check_available(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $quotes['id'], 'shipping')) {
                                continue;
                            }
                        }
                        if ($this->get_surcharge() > 0 && $quotes['id'] != 'freeshipper' && is_array($quotes['methods'])) {
                            foreach ($quotes['methods'] as $key => $value) {
                                if ($value['cost'] > 0 || !defined('SHIPPING_SURCHARGE_FREE_METHODS') || SHIPPING_SURCHARGE_FREE_METHODS == 'True') {
                                    $quotes['methods'][$key]['cost'] = $value['cost'] + $this->get_surcharge();
                                }
                            }
                        }
                        if (method_exists($_module, 'widget')) {
                            $quotes['widget'] = $_module->widget();
                        }
                        foreach (\common\helpers\Hooks::get_list('shipping/after-quote') as $filename) {
                            include $filename;
                        }
                        if ($_module->use_delivery()) {
                            $this->delivery_quotes[] = $quotes;
                            $this->delivery_methods_count += is_array($quotes['methods'] ?? null) ? count($quotes['methods']) : 0;
                        } else {
                            $this->pickup_quotes[] = $quotes;
                            $this->pickup_methods_count += is_array($quotes['methods'] ?? null) ? count($quotes['methods']) : 0;
                        }
                        $this->all_methods_count += is_array($quotes['methods'] ?? null) ? count($quotes['methods']) : 0;
                    }
                }
                $this->delivery_quotes = $this->limit_modules_result($this->delivery_quotes);
            }
        }
        return array_merge($this->delivery_quotes, $this->pickup_quotes);
    }
    public function get_delivery_quotes()
    {
        return $this->delivery_quotes;
    }
    public function get_pickup_quotes()
    {
        return $this->pickup_quotes;
    }
    protected function limit_modules_result($module_quotes)
    {
        $result_quotes = [];
        if (!defined('MODULE_ORDER_TOTAL_SHIPPING_RESULT_COUNT') || !is_numeric(MODULE_ORDER_TOTAL_SHIPPING_RESULT_COUNT)) {
            return $module_quotes;
        }
        $shipping_cost = [];
        $shipping_order = [];
        $shipping_ref = [];
        foreach ($module_quotes as $module_idx => $module_info) {
            if (isset($module_info['methods']) && is_array($module_info['methods']) && count($module_info['methods']) > 0) {
                foreach ($module_info['methods'] as $method_idx => $module_method) {
                    if (!empty($module_info['error']) || !empty($module_method['error']) || !isset($module_method['cost'])) {
                        $shipping_cost[] = 1000000;
                        $shipping_order[] = count($shipping_order);
                        $shipping_ref[] = [$module_idx, $method_idx];
                    } else {
                        $cost = \common\helpers\Tax::add_tax_always($module_method['cost'], $module_info['tax']);
                        $shipping_cost[] = floatval($cost);
                        $shipping_order[] = count($shipping_order);
                        $shipping_ref[] = [$module_idx, $method_idx];
                    }
                }
            } else {
                $shipping_cost[] = 1000000;
                $shipping_order[] = count($shipping_order);
                $shipping_ref[] = [$module_idx, -1];
            }
        }
        if (!defined('MODULE_ORDER_TOTAL_SHIPPING_LIMIT_RESULT_SORT') || MODULE_ORDER_TOTAL_SHIPPING_LIMIT_RESULT_SORT == 'Cheapest first') {
            array_multisort($shipping_cost, SORT_NUMERIC, $shipping_order, SORT_NUMERIC, $shipping_ref);
        } else {
            //array_multisort($shippingOrder, SORT_NUMERIC, $shippingRef);
        }
        $added_methods = 0;
        foreach ($shipping_ref as $ref) {
            $module_idx = $ref[0];
            $method_idx = $ref[1];
            if (!isset($result_quotes[$module_idx])) {
                $result_quotes[$module_idx] = $module_quotes[$module_idx];
                $result_quotes[$module_idx]['methods'] = [];
            }
            if ($method_idx == -1) {
                unset($result_quotes[$module_idx]['methods']);
            } else {
                $result_quotes[$module_idx]['methods'][] = $module_quotes[$module_idx]['methods'][$method_idx];
            }
            if (!$result_quotes[$module_idx]['hide_row'] && !$module_quotes[$module_idx]['methods'][$method_idx]['hide_row']) {
                $added_methods++;
            }
            if ($added_methods >= (int) MODULE_ORDER_TOTAL_SHIPPING_RESULT_COUNT) {
                break;
            }
        }
        return array_values($result_quotes);
    }
    public function get_first_quote_module($class, $visibility = ['shop_order', 'admin', 'pos'])
    {
        if (is_object($this->include_modules[$class]) && $this->include_modules[$class]->enabled) {
            $quotes = $this->include_modules[$class]->quote('', '', $visibility);
            if (is_array($quotes['methods'])) {
                return [['id' => $quotes['id'] . '_' . $quotes['methods'][0]['id'], 'title' => $quotes['module'] . ' (' . $quotes['methods'][0]['title'] . ')', 'cost' => $quotes['methods'][0]['cost']]];
            }
        }
        return false;
    }
    public $cheapest = null;
    public const CHEAPEST_DELIVERY = 1;
    public const CHEAPEST_PICKUP = 2;
    public function use_delivery_cheapest()
    {
        $this->cheapest = self::CHEAPEST_DELIVERY;
    }
    public function use_pickup_cheapest()
    {
        $this->cheapest = self::CHEAPEST_PICKUP;
    }
    /*var $type: pickup, delivery or empty(all)*/
    public function cheapest($type = '')
    {
        //global $select_shipping;
        $cheapest = false;
        if (is_array($this->modules)) {
            $rates = [];
            $_quotes = null;
            if (!is_null($this->cheapest) && empty($type)) {
                if ($this->cheapest == self::CHEAPEST_DELIVERY && $this->delivery_quotes) {
                    $_quotes = $this->delivery_quotes;
                } elseif ($this->cheapest == self::CHEAPEST_PICKUP && $this->pickup_quotes) {
                    $_quotes = $this->pickup_quotes;
                }
            }
            if (is_null($_quotes)) {
                $_quotes = $type == 'pickup' ? $this->pickup_quotes : ($type == 'delivery' ? $this->delivery_quotes : array_merge($this->delivery_quotes, $this->pickup_quotes));
            }
            foreach ($_quotes as $quotes) {
                if (isset($quotes['hide_row']) && $quotes['hide_row']) {
                    continue;
                }
                if (!isset($quotes['error'])) {
                    if (is_array($quotes['methods'])) {
                        for ($i = 0, $n = sizeof($quotes['methods']); $i < $n; $i++) {
                            if (isset($quotes['methods'][$i]['cost']) && is_numeric($quotes['methods'][$i]['cost'])) {
                                $rates[] = ['module' => $quotes['id'], 'id' => $quotes['id'] . '_' . $quotes['methods'][$i]['id'], 'title' => $quotes['module'] . ' (' . $quotes['methods'][$i]['title'] . ')', 'cost' => $quotes['methods'][$i]['cost'], 'no_cost' => isset($quotes['methods'][$i]['no_cost']) ? $quotes['methods'][$i]['no_cost'] : false, 'cost_inc_tax' => defined('PRICE_WITH_BACK_TAX') && PRICE_WITH_BACK_TAX == 'True' ? $quotes['methods'][$i]['cost'] : \common\helpers\Tax::add_tax_always($quotes['methods'][$i]['cost'], isset($quotes['tax']) ? $quotes['tax'] : 0), 'cost_exc_tax' => defined('PRICE_WITH_BACK_TAX') && PRICE_WITH_BACK_TAX == 'True' ? \common\helpers\Tax::reduce_tax_always($quotes['methods'][$i]['cost'], isset($quotes['tax']) ? $quotes['tax'] : 0) : $quotes['methods'][$i]['cost']];
                            }
                        }
                    }
                }
            }
            for ($i = 0, $n = sizeof($rates); $i < $n; $i++) {
                if ($i == 0 || !defined('DEFAULT_SHIPPING_BY_SORT_ORDER') || DEFAULT_SHIPPING_BY_SORT_ORDER != 'True') {
                    if (is_array($cheapest)) {
                        if ($rates[$i]['cost'] < $cheapest['cost']) {
                            $cheapest = $rates[$i];
                        }
                    } else {
                        $cheapest = $rates[$i];
                    }
                }
            }
        }
        return $cheapest;
    }
    public static function module($module, $front = false)
    {
        $file = $front ? DIR_WS_MODULES . 'shipping/' . $module . '.php' : DIR_FS_DOCUMENT_ROOT . '/includes/modules/shipping/' . $module . '.php';
        if (!is_null($module) && file_exists($file)) {
            include_once $file;
            if (class_exists($module)) {
                return new $module();
            }
        }
        return null;
    }
}