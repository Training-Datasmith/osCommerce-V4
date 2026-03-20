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

use common\helpers\Price_Formula;
use common\models\Categories;
use common\models\Manufacturers;
use common\models\Suppliers;
class Suppliers_Rules
{
    public function view_object_for_supplier()
    {
        //....
    }
    protected function fill_supplier_data($discount_collection, $rules_collection, $skip_ro_fill = false)
    {
        $supplier_data = [];
        foreach ($discount_collection as $supplier_discount) {
            if (!is_array($supplier_data[$supplier_discount->suppliers_id])) {
                $supplier_data[$supplier_discount->suppliers_id] = [];
            }
            $supplier_data[$supplier_discount->suppliers_id]['discount_table'][] = $supplier_discount->get_attributes();
        }
        foreach ($rules_collection as $supplier_price_rule) {
            if (!is_array($supplier_data[$supplier_price_rule->suppliers_id] ?? null)) {
                $supplier_data[$supplier_price_rule->suppliers_id] = [];
            }
            $rule_data = $supplier_price_rule->get_attributes();
            $arr_price_formula = [];
            if (!empty($rule_data['price_formula'])) {
                $arr_price_formula = json_decode($rule_data['price_formula'], true);
                if (is_array($arr_price_formula) && isset($arr_price_formula['formula'])) {
                } else {
                    $arr_price_formula = Price_Formula::get_supplier_formula($supplier_price_rule->suppliers_id);
                }
            } else {
                $arr_price_formula = Price_Formula::get_supplier_formula($supplier_price_rule->suppliers_id);
            }
            $rule_data['price_formula'] = json_encode($arr_price_formula);
            $rule_data['price_formula_text'] = $arr_price_formula['text'];
            $supplier_data[$supplier_price_rule->suppliers_id]['price_rule'][] = $rule_data;
            $supplier_data[$supplier_price_rule->suppliers_id]['rule_condition'] = $supplier_price_rule->rule_condition;
        }
        if (count($supplier_data) > 0) {
            $supplier_infos = \common\models\Suppliers::find()->where(['suppliers_id' => array_keys($supplier_data)])->all();
            foreach ($supplier_infos as $supplier_info) {
                $supplier_data[$supplier_info->suppliers_id]['info'] = $supplier_info->get_attributes();
                if (!isset($supplier_data[$supplier_info->suppliers_id]['discount_table'])) {
                    $supplier_data[$supplier_info->suppliers_id]['discount_table'] = [];
                }
                if (!isset($supplier_data[$supplier_info->suppliers_id]['rule_condition'])) {
                    $supplier_data[$supplier_info->suppliers_id]['rule_condition'] = '';
                }
                if (!isset($supplier_data[$supplier_info->suppliers_id]['price_rule'])) {
                    $supplier_data[$supplier_info->suppliers_id]['price_rule'] = [];
                }
                $supplier_data[$supplier_info->suppliers_id]['currenciesVariants'] = $this->fill_currencies_variants($supplier_info);
                $formula = Price_Formula::get_supplier_formula($supplier_info->suppliers_id);
                $supplier_data[$supplier_info->suppliers_id]['default_rule'] = ['price_formula_text' => $formula['text'], 'price_formula' => json_encode($formula), 'supplier_discount' => '0.00', 'surcharge_amount' => '0.00', 'margin_percentage' => '0.00'];
            }
        }
        if (!$skip_ro_fill) {
            $supplier_infos = \common\models\Suppliers::find()->all();
            foreach ($supplier_infos as $supplier_info) {
                if (isset($supplier_data[$supplier_info->suppliers_id])) {
                    continue;
                }
                $supplier_data[$supplier_info->suppliers_id] = ['info' => $supplier_info->get_attributes(), 'supplierRO' => 1, 'discountRO' => 1, 'rulesRO' => 1, 'discount_table' => [], 'rule_condition' => '', 'price_rule' => [], 'currenciesVariants' => $this->fill_currencies_variants($supplier_info)];
                foreach ($supplier_info->get_supplier_discounts()->order_by(['suppliers_id' => SORT_ASC, 'quantity_from' => SORT_ASC])->all() as $supplier_discount) {
                    $supplier_data[$supplier_discount->suppliers_id]['discount_table'][] = $supplier_discount->get_attributes();
                }
                $currencies = \Yii::$container->get('currencies');
                foreach ($supplier_info->get_supplier_price_rules()->order_by(['suppliers_id' => SORT_ASC, 'currencies_id' => SORT_DESC])->all() as $supplier_price_rule) {
                    $rule_data = $supplier_price_rule->get_attributes();
                    $arr_price_formula = [];
                    if (!empty($rule_data['price_formula'])) {
                        $arr_price_formula = json_decode($rule_data['price_formula'], true);
                        if (is_array($arr_price_formula) && isset($arr_price_formula['formula'])) {
                        } else {
                            $arr_price_formula = Price_Formula::get_supplier_formula($supplier_price_rule->suppliers_id);
                        }
                    } else {
                        $arr_price_formula = Price_Formula::get_supplier_formula($supplier_price_rule->suppliers_id);
                    }
                    $rule_data['price_formula'] = json_encode($arr_price_formula);
                    $rule_data['price_formula_text'] = $arr_price_formula['text'];
                    if (is_numeric($rule_data['supplier_price_from'])) {
                        $rule_data['supplier_price_from'] = $currencies->format($rule_data['supplier_price_from'], true, \common\helpers\Currencies::get_currency_code($rule_data['currencies_id']));
                    }
                    if (is_numeric($rule_data['supplier_price_to'])) {
                        $rule_data['supplier_price_to'] = $currencies->format($rule_data['supplier_price_to'], true, \common\helpers\Currencies::get_currency_code($rule_data['currencies_id']));
                    }
                    if (is_numeric($rule_data['supplier_price_not_below'])) {
                        $rule_data['supplier_price_not_below'] = $currencies->format($rule_data['supplier_price_not_below'], true, \common\helpers\Currencies::get_currency_code($rule_data['currencies_id']));
                    }
                    if (is_numeric($rule_data['surcharge_amount'])) {
                        $rule_data['surcharge_amount'] = $currencies->format($rule_data['surcharge_amount'], true, \common\helpers\Currencies::get_currency_code($rule_data['currencies_id']));
                    }
                    $supplier_data[$supplier_price_rule->suppliers_id]['price_rule'][] = $rule_data;
                    $supplier_data[$supplier_price_rule->suppliers_id]['rule_condition'] = $supplier_price_rule->rule_condition;
                }
                $formula = Price_Formula::get_supplier_formula($supplier_info->suppliers_id);
                $supplier_data[$supplier_info->suppliers_id]['default_rule'] = ['currencies_id' => $supplier_info->currencies_id, 'price_formula_text' => $formula['text'], 'price_formula' => json_encode($formula), 'supplier_discount' => '0.00', 'surcharge_amount' => '0.00', 'surcharge_amount_formatted' => $currencies->format(0.0, true, \common\helpers\Currencies::get_currency_code($supplier_info->currencies_id)), 'margin_percentage' => '0.00'];
                if (count($supplier_data[$supplier_info->suppliers_id]['price_rule']) == 0) {
                    $supplier_data[$supplier_info->suppliers_id]['price_rule'][] = $supplier_data[$supplier_info->suppliers_id]['default_rule'];
                }
            }
        }
        if (count($supplier_data) > 1) {
            $_supplier_data = [];
            foreach (\common\helpers\Suppliers::ordered_ids() as $ordered_id) {
                if (isset($supplier_data[$ordered_id])) {
                    $_supplier_data[$ordered_id] = $supplier_data[$ordered_id];
                }
            }
            $supplier_data = $_supplier_data;
        }
        return $supplier_data;
    }
    protected function fill_currencies_variants($parent_obj = null)
    {
        $currencies = [0 => defined('TEXT_ANY') ? TEXT_ANY : 'Any'];
        if ($parent_obj instanceof Suppliers) {
            foreach ($parent_obj->get_allowed_currencies()->as_array()->all() as $_currency) {
                $currencies[$_currency['currencies_id']] = $_currency['currencies']['code'];
            }
        } else {
            foreach (\common\helpers\Currencies::get_currencies() as $_currency) {
                if ($_currency['currencies_id']) {
                    $currencies[$_currency['currencies_id']] = $_currency['code'];
                }
            }
        }
        return $currencies;
    }
    public function get_category_data(Categories $parent_obj, $view_object)
    {
        $supplier_data = [];
        if ($parent_obj->categories_id) {
            $supplier_data = $this->fill_supplier_data($parent_obj->get_supplier_discounts()->order_by(['suppliers_id' => SORT_ASC, 'quantity_from' => SORT_ASC])->all(), $parent_obj->get_supplier_price_rules()->order_by(['suppliers_id' => SORT_ASC, 'currencies_id' => SORT_DESC])->all());
        }
        $view_object->supplier_data = $supplier_data;
        //$viewObject->supplierCurrenciesVariants = $this->fillCurrenciesVariants();
    }
    public function save_category_data(Categories $category_obj, $data)
    {
        $categories_id = $category_obj->categories_id;
        \common\models\Suppliers_Catalog_Price_Rules::delete_all(['category_id' => $categories_id]);
        \common\models\Suppliers_Catalog_Discount::delete_all(['category_id' => $categories_id]);
        foreach ($data as $supplier_id => $_data) {
            if (isset($_data['price_rule']) && is_array($_data['price_rule'])) {
                foreach ($_data['price_rule'] as $rule) {
                    $__model = new \common\models\Suppliers_Catalog_Price_Rules();
                    $__model->set_attributes($rule, false);
                    $__model->suppliers_id = $supplier_id;
                    $__model->category_id = $categories_id;
                    $__model->rule_condition = isset($_data['rule_condition']) ? $_data['rule_condition'] : '';
                    if (!empty($__model->price_formula)) {
                        $def_formula = json_encode(Price_Formula::get_supplier_formula($supplier_id));
                        if ($def_formula == $__model->price_formula) {
                            $__model->set_attribute('price_formula', null);
                        }
                    }
                    if (is_null($__model->price_formula) && is_null($__model->surcharge_amount) && is_null($__model->margin_percentage) && is_null($__model->supplier_discount)) {
                        continue;
                    }
                    $__model->save();
                    //if ( empty($_data['rule_condition']) ) break;
                }
            }
            if (isset($_data['has_discount_table']) && $_data['has_discount_table'] && isset($_data['discount_table']) && is_array($_data['discount_table'])) {
                foreach ($_data['discount_table'] as $table) {
                    $__model = new \common\models\Suppliers_Catalog_Discount();
                    if (strlen($table['quantity_from']) == 0) {
                        unset($table['quantity_from']);
                    }
                    if (strlen($table['quantity_to']) == 0) {
                        unset($table['quantity_to']);
                    }
                    if (!isset($table['quantity_from']) && !isset($table['quantity_to'])) {
                        continue;
                    }
                    $__model->set_attributes($table, false);
                    $__model->suppliers_id = $supplier_id;
                    $__model->category_id = $categories_id;
                    $__model->save();
                }
            }
        }
    }
    public function get_manufacturer_data(Manufacturers $parent_obj, $view_object)
    {
        $supplier_data = [];
        if ($parent_obj->manufacturers_id) {
            $supplier_data = $this->fill_supplier_data($parent_obj->get_supplier_discounts()->order_by(['suppliers_id' => SORT_ASC, 'quantity_from' => SORT_ASC])->all(), $parent_obj->get_supplier_price_rules()->order_by(['suppliers_id' => SORT_ASC, 'currencies_id' => SORT_DESC])->all());
        }
        $view_object->supplier_data = $supplier_data;
        //$viewObject->supplierCurrenciesVariants = $this->fillCurrenciesVariants();
    }
    public function save_manufacturers_data(Manufacturers $parent_obj, $data)
    {
        $manufacturers_id = $parent_obj->manufacturers_id;
        \common\models\Suppliers_Catalog_Price_Rules::delete_all(['manufacturer_id' => $manufacturers_id]);
        \common\models\Suppliers_Catalog_Discount::delete_all(['manufacturer_id' => $manufacturers_id]);
        foreach ($data as $supplier_id => $_data) {
            if (isset($_data['price_rule']) && is_array($_data['price_rule'])) {
                foreach ($_data['price_rule'] as $rule) {
                    $__model = new \common\models\Suppliers_Catalog_Price_Rules();
                    $__model->set_attributes($rule, false);
                    $__model->suppliers_id = $supplier_id;
                    $__model->manufacturer_id = $manufacturers_id;
                    $__model->rule_condition = isset($_data['rule_condition']) ? $_data['rule_condition'] : '';
                    if (!empty($__model->price_formula)) {
                        $def_formula = json_encode(Price_Formula::get_supplier_formula($supplier_id));
                        if ($def_formula == $__model->price_formula) {
                            $__model->set_attribute('price_formula', null);
                        }
                    }
                    if (is_null($__model->price_formula) && is_null($__model->surcharge_amount) && is_null($__model->margin_percentage) && is_null($__model->supplier_discount)) {
                        continue;
                    }
                    $__model->save();
                    //if ( empty($_data['rule_condition']) ) break;
                }
            }
            if (isset($_data['has_discount_table']) && $_data['has_discount_table'] && isset($_data['discount_table']) && is_array($_data['discount_table'])) {
                foreach ($_data['discount_table'] as $table) {
                    if (strlen($table['quantity_from']) == 0) {
                        unset($table['quantity_from']);
                    }
                    if (strlen($table['quantity_to']) == 0) {
                        unset($table['quantity_to']);
                    }
                    if (!isset($table['quantity_from']) && !isset($table['quantity_to'])) {
                        continue;
                    }
                    $__model = new \common\models\Suppliers_Catalog_Discount();
                    $__model->set_attributes($table, false);
                    $__model->suppliers_id = $supplier_id;
                    $__model->manufacturer_id = $manufacturers_id;
                    $__model->save();
                }
            }
        }
    }
    public function get_suppliers_data(Suppliers $parent_obj, $view_object)
    {
        $supplier_data = [];
        if ($parent_obj->suppliers_id) {
            $supplier_data = $this->fill_supplier_data($parent_obj->get_supplier_discounts()->order_by(['suppliers_id' => SORT_ASC, 'quantity_from' => SORT_ASC])->all(), $parent_obj->get_supplier_price_rules()->order_by(['suppliers_id' => SORT_ASC, 'currencies_id' => SORT_DESC])->all(), true);
            if (empty($supplier_data)) {
                $default_formula = Price_Formula::default_formula();
                $supplier_data[$parent_obj->suppliers_id] = ['info' => $parent_obj->get_attributes(), 'price_rule' => [['currencies_id' => 0, 'price_formula_text' => $default_formula['text'], 'price_formula' => json_encode($default_formula), 'supplier_discount' => '0.00', 'surcharge_amount' => '0.00', 'margin_percentage' => '0.00']], 'discount_table' => [], 'default_rule' => ['currencies_id' => 0, 'price_formula_text' => $default_formula['text'], 'price_formula' => json_encode($default_formula), 'supplier_discount' => '0.00', 'surcharge_amount' => '0.00', 'margin_percentage' => '0.00'], 'currenciesVariants' => $this->fill_currencies_variants($parent_obj)];
            }
        }
        $view_object->supplier_data = $supplier_data;
        //$viewObject->supplierCurrenciesVariants = $this->fillCurrenciesVariants();
    }
    public function save_supplier_data(Suppliers $parent_obj, $data)
    {
        $suppliers_id = $parent_obj->suppliers_id;
        \common\models\Suppliers_Catalog_Price_Rules::delete_all(['suppliers_id' => $suppliers_id, 'category_id' => 0, 'manufacturer_id' => 0]);
        \common\models\Suppliers_Catalog_Discount::delete_all(['suppliers_id' => $suppliers_id, 'category_id' => 0, 'manufacturer_id' => 0]);
        if (isset($data[$suppliers_id])) {
            $supplier_id = $suppliers_id;
            $_data = $data[$suppliers_id];
            if (isset($_data['price_rule']) && is_array($_data['price_rule'])) {
                foreach ($_data['price_rule'] as $rule) {
                    $__model = new \common\models\Suppliers_Catalog_Price_Rules();
                    $__model->set_attributes($rule, false);
                    $__model->suppliers_id = $supplier_id;
                    $__model->manufacturer_id = 0;
                    $__model->category_id = 0;
                    $__model->rule_condition = isset($_data['rule_condition']) ? $_data['rule_condition'] : '';
                    if (!empty($__model->price_formula)) {
                        $def_formula = json_encode(Price_Formula::get_supplier_formula($supplier_id));
                        if ($def_formula == $__model->price_formula) {
                            $__model->set_attribute('price_formula', null);
                        }
                    }
                    if (is_null($__model->price_formula) && is_null($__model->surcharge_amount) && is_null($__model->margin_percentage) && is_null($__model->supplier_discount)) {
                        continue;
                    }
                    $__model->save();
                    //if ( empty($_data['rule_condition']) ) break; // currencies
                }
            }
            if (isset($_data['has_discount_table']) && $_data['has_discount_table'] && isset($_data['discount_table']) && is_array($_data['discount_table'])) {
                foreach ($_data['discount_table'] as $table) {
                    if (strlen($table['quantity_from']) == 0) {
                        unset($table['quantity_from']);
                    }
                    if (strlen($table['quantity_to']) == 0) {
                        unset($table['quantity_to']);
                    }
                    if (!isset($table['quantity_from']) && !isset($table['quantity_to'])) {
                        continue;
                    }
                    $__model = new \common\models\Suppliers_Catalog_Discount();
                    $__model->set_attributes($table, false);
                    $__model->suppliers_id = $supplier_id;
                    $__model->category_id = 0;
                    $__model->manufacturer_id = 0;
                    $__model->save();
                }
            }
            static::apply_rules(['suppliers_id' => $supplier_id]);
        }
    }
    public static function apply_rules($filter)
    {
        $get_rules = \common\models\Suppliers_Catalog_Price_Rules::find()->where($filter)->order_by(['supplier_price_from' => SORT_ASC])->all();
        //        echo '<pre>'; var_dump($getRules); echo '</pre>';
        //        die;
    }
}