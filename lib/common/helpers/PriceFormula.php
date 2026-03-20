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
namespace common\helpers;

class Price_Formula
{
    public static function default_formula()
    {
        static $price_formula;
        if (!is_array($price_formula)) {
            $price_formula = json_decode('{"text":"((PRICE-DISCOUNT)+MARGIN)+SURCHARGE","formula":[[["()M",["PRICE","-","DISCOUNT"]],"+","SURCHARGE"]]}', true);
        }
        return $price_formula;
    }
    public static function get_supplier_formula($supplier_id)
    {
        static $cached = [];
        if (!isset($cached[intval($supplier_id)])) {
            $cached[intval($supplier_id)] = static::default_formula();
            /*            $get_suppliers_data_r = tep_db_query("SELECT price_formula FROM suppliers WHERE suppliers_id='" . (int) $supplierId . "'");
                          if (tep_db_num_rows($get_suppliers_data_r) > 0) {
                              $suppliers_data = tep_db_fetch_array($get_suppliers_data_r);
                              if (!empty($suppliers_data['price_formula'])) {
                                  $supplierFormula = json_decode($suppliers_data['price_formula'], true);
                                  if (is_array($supplierFormula) && isset($supplierFormula['formula'])) {
                                      $cached[intval($supplierId)] = $supplierFormula;
                                  }
                              }
                          }*/
        }
        return $cached[intval($supplier_id)];
    }
    protected static function normalize_params($params_in)
    {
        $params = [];
        if (is_array($params_in)) {
            foreach ($params_in as $k => $v) {
                $k = strtoupper($k);
                if (in_array($k, ['PRICE', 'MARGIN', 'SURCHARGE', 'DISCOUNT', 'TAX_RATE'])) {
                    $v = (float) $v;
                }
                $params[$k] = $v;
            }
        }
        return $params;
    }
    protected static function replace_param($formula_array, $params, $for_php = true)
    {
        foreach ($formula_array as $idx => $value) {
            if (is_array($value)) {
                $formula_array[$idx] = static::replace_param($value, $params, $for_php);
            } elseif ($value == '()M' && isset($params['MARGIN'])) {
                $formula_array[$idx] = '(' . $params['MARGIN'] . ' + 1) *';
            } elseif (isset($params[$value])) {
                $formula_array[$idx] = $params[$value];
            } elseif (substr($value, -1) == '%') {
                if (isset($params['PRICE'])) {
                    if ($for_php) {
                        $formula_array[$idx] = $params['PRICE'] * substr($value, 0, -1) / 100;
                    } else {
                        $formula_array[$idx] = $params['PRICE'] . '*' . substr($value, 0, -1) . '/100';
                    }
                } else {
                    $formula_array[$idx] = 0;
                }
            }
        }
        return $formula_array;
    }
    public static function array_to_flat_php($formula_array)
    {
        $implode_parts = [];
        foreach ($formula_array as $formula_chunk) {
            if (is_array($formula_chunk)) {
                $implode_parts[] = '(' . static::array_to_flat_php($formula_chunk) . ')';
            } else {
                $implode_parts[] = $formula_chunk;
            }
        }
        return implode(' ', $implode_parts);
    }
    public static function calculate_php($formula_array, $params)
    {
        $formula_array = static::replace_param($formula_array, $params);
        $eval_code = static::array_to_flat_php($formula_array);
        eval("\$result={$eval_code};");
        if (!isset($result) || !is_numeric($result) || $result < 0) {
            $result = false;
        }
        return $result;
    }
    public static function get_js($formula_array, $params)
    {
        $params = static::normalize_params($params);
        if (isset($formula_array['formula'])) {
            $formula_array = $formula_array['formula'];
        }
        if (!is_array($formula_array) || count($formula_array) == 0) {
            return false;
        }
        if (isset($params['DISCOUNT']) && isset($params['PRICE'])) {
            $params['DISCOUNT'] = '(' . $params['DISCOUNT'] . '/100)*' . $params['PRICE'];
        }
        if (isset($params['MARGIN'])) {
            $params['MARGIN'] = '(' . $params['MARGIN'] . '/100)';
        }
        $formula_array = static::replace_param($formula_array, $params, false);
        $eval_code = static::array_to_flat_php($formula_array);
        if (empty($eval_code)) {
            return false;
        }
        return $eval_code;
    }
    public static function get_product_edit_js($params)
    {
        $params = static::normalize_params($params);
        $js = '';
        foreach (\common\models\Suppliers::find()->all() as $supplier) {
            $price_formula = json_decode($supplier->price_formula, true);
            if (!is_array($price_formula)) {
                $price_formula = static::default_formula();
            }
            $price_rules = $supplier->get_supplier_price_rules()->order_by(['supplier_price_from' => SORT_ASC])->all();
            if (count($price_rules) > 0) {
                $rules_js = '';
                foreach ($price_rules as $price_rule) {
                    if (empty($price_rule->rule_condition)) {
                        if (!is_null($price_rule->supplier_discount)) {
                            //$params['DISCOUNT'] = $priceRule->supplier_discount;
                        }
                        if (!is_null($price_rule->surcharge_amount)) {
                            //$params['SURCHARGE'] = $priceRule->surcharge_amount;
                        }
                        if (!is_null($price_rule->margin_percentage)) {
                            //$params['MARGIN'] = $priceRule->margin_percentage;
                        }
                        if (!empty($price_rule->price_formula)) {
                            $price_formula = json_decode($price_rule->price_formula, true);
                        }
                        $rules_js = static::get_js($price_formula, $params);
                    } else {
                        $rule_condition = ',' . $price_rule->rule_condition . ',';
                        if (strpos($rule_condition, ',fromTo,') !== false) {
                            if (!is_null($price_rule->supplier_discount)) {
                                //$params['DISCOUNT'] = $priceRule->supplier_discount;
                            }
                            if (!is_null($price_rule->surcharge_amount)) {
                                //$params['SURCHARGE'] = $priceRule->surcharge_amount;
                            }
                            if (!is_null($price_rule->margin_percentage)) {
                                //$params['MARGIN'] = $priceRule->margin_percentage;
                            }
                            if (!empty($price_rule->price_formula)) {
                                $price_formula = json_decode($price_rule->price_formula, true);
                            }
                            $supplier_formula = static::get_js($price_formula, $params);
                            if (!empty($rules_js)) {
                                $rules_js .= 'else ';
                            }
                            $low_limit = '';
                            $top_limit = '';
                            if (!is_null($price_rule->supplier_price_from)) {
                                $low_limit = $params['PRICE'] . '>=' . number_format(floatval($price_rule->supplier_price_from), 2, '.', '');
                            }
                            if (!is_null($price_rule->supplier_price_to)) {
                                $top_limit = $params['PRICE'] . '<=' . number_format(floatval($price_rule->supplier_price_to), 2, '.', '');
                            }
                            if (!empty($low_limit) && !empty($top_limit)) {
                                $rules_js .= 'if (' . $low_limit . ' && ' . $top_limit . '){ return ' . $supplier_formula . '; }';
                            } elseif (!empty($low_limit) && empty($top_limit)) {
                                $rules_js .= 'if (' . $low_limit . '){ return ' . $supplier_formula . '; }';
                            } elseif (empty($low_limit) && !empty($top_limit)) {
                                $rules_js .= 'if (' . $top_limit . '){ return ' . $supplier_formula . '; }';
                            }
                        }
                    }
                }
                $supplier_formula = $rules_js;
            } else {
                $supplier_formula = static::get_js($price_formula, $params);
            }
            if (empty($supplier_formula)) {
                continue;
            }
            if (!empty($js)) {
                $js .= 'else ';
            }
            $js .= "if (id=={$supplier->suppliers_id}){\n";
            $js .= ' calcNetPrice = (function(){ ' . (strpos($supplier_formula, 'if') === 0 ? '' : 'return ') . $supplier_formula . "; })();\n";
            $js .= '}';
        }
        return $js;
    }
    public static function apply($formula_array, $params)
    {
        $params = static::normalize_params($params);
        if (isset($formula_array['formula'])) {
            $formula_array = $formula_array['formula'];
        }
        if (!is_array($formula_array) || count($formula_array) == 0) {
            return false;
        }
        if (isset($params['DISCOUNT']) && isset($params['PRICE'])) {
            $params['DISCOUNT'] = (float) $params['DISCOUNT'] / 100 * (float) $params['PRICE'];
        }
        if (isset($params['MARGIN'])) {
            $params['MARGIN'] = (float) $params['MARGIN'] / 100;
        }
        $result = static::calculate_php($formula_array, $params);
        return $result;
        /*
         PRICE
         DISCOUNT
         SURCHARGE
         MARGIN
         %
         +
         -
        *
         /
         ()
        */
    }
    public static function calculate_supplier_products($product_id)
    {
        $currencies = \Yii::$container->get('currencies');
        $per_supplier = [];
        if (strpos($product_id, '{') !== false) {
            $get_product_info_r = tep_db_query('SELECT sp.suppliers_id, ' . '  sp.suppliers_quantity, sp.is_default, sp.status, ' . '  sp.suppliers_price, sp.currencies_id, ' . '  sp.supplier_discount, sp.suppliers_surcharge_amount, sp.suppliers_margin_percentage, ' . '  sp.tax_rate, sp.price_with_tax, ' . '  i.products_id, p.manufacturers_id, ' . "  GROUP_CONCAT(DISTINCT p2c.categories_id SEPARATOR ',') AS assigned_categories " . 'FROM ' . TABLE_PRODUCTS . ' p ' . '  INNER JOIN ' . TABLE_INVENTORY . ' i ON i.prid=p.products_id ' . '  INNER JOIN ' . TABLE_SUPPLIERS_PRODUCTS . ' sp ON sp.products_id=p.products_id AND sp.uprid=i.products_id AND sp.suppliers_price>0 ' . '  LEFT JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p.products_id=p2c.products_id ' . "WHERE p.products_id='" . (int) $product_id . "' AND i.products_id='" . tep_db_input($product_id) . "' " . 'GROUP BY i.products_id, sp.suppliers_id ' . 'ORDER BY IF(sp.suppliers_quantity>0,0,1), IF(sp.is_default=1,0,1) ');
        } else {
            $get_product_info_r = tep_db_query('SELECT sp.suppliers_id, ' . '  sp.suppliers_quantity, sp.is_default, sp.status, ' . '  sp.suppliers_price, sp.currencies_id, ' . '  sp.supplier_discount, sp.suppliers_surcharge_amount, sp.suppliers_margin_percentage, ' . '  sp.tax_rate, sp.price_with_tax, ' . '  p.products_id, p.manufacturers_id, ' . "  GROUP_CONCAT(DISTINCT p2c.categories_id SEPARATOR ',') AS assigned_categories " . 'FROM ' . TABLE_PRODUCTS . ' p ' . '  INNER JOIN ' . TABLE_SUPPLIERS_PRODUCTS . " sp ON sp.products_id=p.products_id AND sp.uprid=CONCAT('',p.products_id) AND sp.suppliers_price>=0 AND status=1" . '  LEFT JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ON p.products_id=p2c.products_id ' . '  LEFT JOIN ' . TABLE_SUPPLIERS . ' s ON s.suppliers_id=sp.suppliers_id ' . "WHERE p.products_id='" . (int) $product_id . "' " . 'GROUP BY p.products_id, sp.suppliers_id ' . 'ORDER BY IF(sp.sort_order IS NULL,s.sort_order,sp.sort_order)');
        }
        if (tep_db_num_rows($get_product_info_r) > 0) {
            while ($product_data = tep_db_fetch_array($get_product_info_r)) {
                $params = [
                    'products_id' => $product_data['products_id'],
                    'categories_id' => preg_split('/,/', $product_data['assigned_categories'], -1, PREG_SPLIT_NO_EMPTY),
                    'manufacturers_id' => $product_data['manufacturers_id'],
                    'currencies_id' => $product_data['currencies_id'],
                    'PRICE' => $product_data['suppliers_price'],
                    // * $currencies->get_market_price_rate(\common\helpers\Currencies::getCurrencyCode($product_data['currencies_id']), \common\helpers\Currencies::systemCurrencyCode()),
                    'DISCOUNT' => $product_data['supplier_discount'],
                    'SURCHARGE' => $product_data['suppliers_surcharge_amount'],
                    'MARGIN' => $product_data['suppliers_margin_percentage'],
                    'tax_rate' => $product_data['tax_rate'],
                    'price_with_tax' => $product_data['price_with_tax'],
                    'product' => ['suppliers_id' => $product_data['suppliers_id'], 'qty' => $product_data['suppliers_quantity'], 'status' => $product_data['status'], 'is_default' => $product_data['is_default']],
                ];
                $applied_rules = self::apply_rules($params, $product_data['suppliers_id']);
                $per_supplier[$product_data['suppliers_id']] = $applied_rules;
            }
        }
        return $per_supplier;
    }
    private static function auto_select_supplier($product_id)
    {
        if (!defined('SUPPLIER_PRICE_SELECTION') || SUPPLIER_PRICE_SELECTION == 'Disabled') {
            return;
        }
        $product_price = false;
        $selected_supplier_id = false;
        $calculated_prices = self::calculate_supplier_products($product_id);
        if (SUPPLIER_PRICE_SELECTION == 'Cheapest, In stock' || SUPPLIER_PRICE_SELECTION == 'Supplier order') {
            // filter in stock with price
            $in_stock_sort = [];
            foreach ($calculated_prices as $_supplier_id => $calculated_price) {
                if (!is_array($calculated_price['product'])) {
                    continue;
                }
                if ($calculated_price['resultPrice'] > 0) {
                    $in_stock_sort[$_supplier_id] = (float) $calculated_price['resultPrice'];
                }
            }
            if (count($in_stock_sort) > 0) {
                if (SUPPLIER_PRICE_SELECTION == 'Supplier order') {
                    foreach (\common\helpers\Suppliers::ordered_ids_for_product($product_id) as $ordered_supplier_id) {
                        if (isset($in_stock_sort[$ordered_supplier_id])) {
                            $selected_supplier_id = (int) $ordered_supplier_id;
                            $product_price = $calculated_prices[$ordered_supplier_id]['resultPrice'];
                            break;
                        }
                    }
                } else {
                    asort($in_stock_sort, SORT_NUMERIC);
                    $selected_supplier_id = key($in_stock_sort);
                    $product_price = $calculated_prices[$selected_supplier_id]['resultPrice'];
                }
            }
        } elseif (SUPPLIER_PRICE_SELECTION == 'Based on priority rules') {
            if (count($calculated_prices) > 0) {
                if ($ext = \common\helpers\Acl::check_extension_allowed('SupplierPriority', 'getInstance')) {
                    $calculated_prices = $ext::get_instance()->arrange_variants($calculated_prices);
                    foreach ($calculated_prices as $_supplier_id => $calculated_price) {
                        if ($calculated_price['priority'] && $calculated_price['priority']['is_preferred']) {
                            $selected_supplier_id = $_supplier_id;
                            $product_price = $calculated_price['resultPrice'];
                            break;
                        }
                    }
                } else {
                    self::log_auto_update_product($product_id, "Error: extension SupplierPriority is needed to use '" . SUPPLIER_PRICE_SELECTION . "'");
                }
            }
        }
        return ['product_price' => $product_price, 'selected_supplier_id' => $selected_supplier_id ?? null, 'calculatedPrice' => $calculated_price ?? null];
    }
    private static function add_auto_update_where($product_query)
    {
        if (SUPPLIER_UPDATE_PRICE_MODE == 'Auto') {
            $product_query->and_where(['OR', ['IS', 'supplier_price_manual', new \yii\db\Expression('NULL')], ['supplier_price_manual' => 0]]);
        } else {
            $product_query->and_where(['supplier_price_manual' => 0]);
        }
        return $product_query;
    }
    /**
     * Checks if product ready for auto update
     * @param $productId
     * @return \common\models\Products|\yii\db\ActiveRecord|null
     */
    public static function get_product_model_for_auto_update($product_id)
    {
        $product_query = \common\models\Products::find()->select(['products_price', 'products_id', 'supplier_price_manual', 'products_price_full'])->where(['products_id' => (int) $product_id, 'products_id_price' => [(int) $product_id, 0]]);
        return self::add_auto_update_where($product_query)->one();
    }
    private static function log_auto_update($msg, $echo_for_console = true)
    {
        \Yii::info($msg, 'suppliers/auto-update-price');
        if (\common\helpers\System::is_console() && $echo_for_console) {
            echo $msg . "\n";
        }
    }
    private static function log_auto_update_product($product_id, $msg)
    {
        self::log_auto_update("Autoupdate price for product #{$product_id}: #{$msg}", false);
    }
    public static function apply_db($product_id, $check_auto_field = true)
    {
        if ($check_auto_field) {
            $product_model = self::get_product_model_for_auto_update($product_id);
            if (empty($product_model)) {
                self::log_auto_update_product($product_id, 'Canceled - product does not meet auto the update conditions');
                return;
            }
        } else {
            $product_model = \common\models\Products::find_one($product_id);
            if (empty($product_model)) {
                self::log_auto_update_product($product_id, 'Product does not exists');
                return;
            }
        }
        extract(self::auto_select_supplier($product_id));
        if ($product_price === false) {
            self::log_auto_update_product($product_id, 'Canceled - supplier price is empty');
            return;
        }
        $log_string = "result_price={$product_price}; SupplierId={$selected_supplier_id}; config [" . SUPPLIER_PRICE_SELECTION . ']; ';
        if (is_array($calculated_price)) {
            $log_string .= "Applied {$calculated_price['label']} " . \json_encode($calculated_price['applyParams']);
            $log_string .= ' DATA=' . \json_encode($calculated_price);
        }
        self::log_auto_update_product($product_id, $log_string);
        return self::update_product_price_by_model($product_model, $product_price, $selected_supplier_id);
    }
    public static function batch_product_auto_calc_price_by_supplier($LIMIT_RECORDS = 1000, $LIMIT_TIME = 3000)
    {
        self::log_auto_update('Batch update started for ' . (int) $LIMIT_RECORDS . ' products');
        $product_query = \common\models\Products::find()->alias('p')->select('products_id')->where("auto_price_modified IS NULL OR auto_price_modified < COALESCE(last_xml_import, '1000-01-01 00:00:00') OR auto_price_modified < COALESCE(products_last_modified, '1000-01-01 00:00:00')");
        if ($LIMIT_RECORDS > 0) {
            $product_query->limit($LIMIT_RECORDS);
        }
        $start_time = microtime(true);
        $count = $updated = 0;
        foreach (self::add_auto_update_where($product_query)->column() as $pid) {
            $updated += self::apply_db($pid, false) ? 1 : 0;
            $count++;
            if ($LIMIT_TIME > 0 && microtime(true) - $start_time > $LIMIT_TIME) {
                self::log_auto_update('Batch update is interrupted due time limit');
                break;
            }
        }
        $elapsed_time = microtime(true) - $start_time;
        self::log_auto_update("Batch update finished. Products reviewed: {$count} updated: {$updated}. Elapsed time: {$elapsed_time}");
    }
    public static function get_supplier_rules_collection($supplier_id)
    {
        $supplier = \common\models\Suppliers::find_one($supplier_id);
        $rules = ['category' => [], 'brand' => [], 'supplier' => []];
        $all_rules = \common\models\Suppliers_Catalog_Price_Rules::find()->where(['suppliers_id' => $supplier->suppliers_id])->order_by(['currencies_id' => SORT_DESC]);
        foreach ($all_rules->all() as $rule) {
            $formula = is_null($rule->price_formula) ? false : json_decode($rule->price_formula, true);
            if (!is_array($formula)) {
                $formula = static::default_formula();
            }
            $rule_array = ['category_id' => $rule->category_id, 'manufacturer_id' => $rule->manufacturer_id, 'currencies_id' => $rule->currencies_id, 'rule_condition' => $rule->rule_condition, 'cost_from' => $rule->supplier_price_from, 'cost_to' => $rule->supplier_price_to, 'result_price_not_below' => $rule->supplier_price_not_below, 'DISCOUNT' => is_null($rule->supplier_discount) ? 0.0 : $rule->supplier_discount, 'SURCHARGE' => is_null($rule->surcharge_amount) ? 0.0 : $rule->surcharge_amount, 'MARGIN' => is_null($rule->margin_percentage) ? 0.0 : $rule->margin_percentage, 'tax_rate' => $supplier->tax_rate, 'price_with_tax' => $supplier->supplier_prices_with_tax, 'formula' => $formula];
            if (!empty($rule->category_id)) {
                if (!is_array($rules['category'][$rule->category_id] ?? null)) {
                    $rules['category'][$rule->category_id] = [];
                }
                $rule_array['appliedToCategories'] = [];
                $subcategories_query = \common\models\Categories::find()->select([\common\models\Categories::table_name() . '.categories_id', \common\models\Categories::table_name() . '.categories_level'])->inner_join(\common\models\Categories::table_name() . ' cc', 'cc.categories_id=:catId AND ' . \common\models\Categories::table_name() . '.categories_left>=cc.categories_left AND ' . \common\models\Categories::table_name() . '.categories_right<=cc.categories_right', ['catId' => $rule->category_id])->order_by([\common\models\Categories::table_name() . '.categories_left' => SORT_ASC]);
                foreach ($subcategories_query->all() as $cat) {
                    $rule_array['appliedToCategories'][(int) $cat['categories_id']] = (int) $cat['categories_level'];
                }
                $rule_array['label'] = 'Category "' . \common\helpers\Categories::output_generated_category_path($rule->category_id) . '" rule';
                $rules['category'][$rule->category_id][] = $rule_array;
            } elseif (!empty($rule->manufacturer_id)) {
                if (!is_array($rules['brand'][$rule->manufacturer_id])) {
                    $rules['brand'][$rule->manufacturer_id] = [];
                }
                $rules['brand'][$rule->manufacturer_id][] = $rule_array;
            } else {
                $rule_array['label'] = 'Supplier rule';
                $rules['supplier'][] = $rule_array;
            }
        }
        if (count($rules['supplier']) == 0) {
            $formula = static::default_formula();
            $rules['supplier'][] = [
                'currencies_id' => 0,
                // any currency for default formula
                'label' => 'Default supplier rule',
                'DISCOUNT' => 0.0,
                'SURCHARGE' => 0.0,
                'MARGIN' => 0.0,
                'tax_rate' => $supplier->tax_rate,
                'price_with_tax' => $supplier->supplier_prices_with_tax,
                'formula' => $formula,
            ];
        }
        return $rules;
    }
    public static function correct_supplier_value_by_currency_risks($suppliers_id, $currency_id, $value)
    {
        $s_currency = \common\models\Suppliers_Currencies::find()->alias('s')->where(['suppliers_id' => $suppliers_id, 's.currencies_id' => $currency_id])->join_with('currencies c')->one();
        if ($s_currency) {
            if ($s_currency['use_custom_currency_value']) {
                $value /= $s_currency['currency_value'];
            } else {
                $value /= $s_currency->currencies->value;
            }
            if ($s_currency['margin_value']) {
                if ($s_currency['margin_type'] == '%') {
                    $value += $s_currency['margin_value'] / 100 * $value;
                } else {
                    $value += $s_currency['margin_value'];
                }
            }
        }
        return $value;
    }
    protected static function correct_supplier_price_by_currency_risks($suppliers_id, $data)
    {
        return self::correct_supplier_value_by_currency_risks($suppliers_id, $data['currencies_id'], $data['PRICE']);
    }
    protected static function add_tax_rate($amount, $tax_rate = 0)
    {
        return round($amount * ((100 + $tax_rate) / 100), 6);
    }
    protected static function apply_supplier_rule($price_rule, $data, $only_supplier_id)
    {
        $result_cost = false;
        if (isset($price_rule['currencies_id']) && $price_rule['currencies_id'] != 0 && $price_rule['currencies_id'] != $data['currencies_id']) {
            return false;
        }
        $params = ['PRICE' => static::correct_supplier_price_by_currency_risks($only_supplier_id, $data), 'MARGIN' => isset($data['MARGIN']) ? $data['MARGIN'] : $price_rule['MARGIN'], 'SURCHARGE' => isset($data['SURCHARGE']) ? $data['SURCHARGE'] : $price_rule['SURCHARGE'], 'DISCOUNT' => isset($data['DISCOUNT']) ? $data['DISCOUNT'] : $price_rule['DISCOUNT'], 'tax_rate' => isset($data['tax_rate']) ? $data['tax_rate'] : $price_rule['tax_rate'], 'price_with_tax' => isset($data['price_with_tax']) ? $data['price_with_tax'] : $price_rule['price_with_tax']];
        if (isset($params['tax_rate']) && !$params['price_with_tax']) {
            $params['PRICE'] = static::add_tax_rate($params['PRICE'], $params['tax_rate']);
        }
        // check restrict
        if (!empty($price_rule['category_id'])) {
            // category not match
            $matched_category_level = -1;
            foreach ($data['categories_id'] as $check_assigned_id) {
                if (isset($price_rule['appliedToCategories'][$check_assigned_id])) {
                    $matched_category_level = max($matched_category_level, $price_rule['appliedToCategories'][$check_assigned_id]);
                }
            }
            if ($matched_category_level == -1) {
                return false;
            }
            $price_rule['categoryLevel'] = $matched_category_level;
        }
        // limited rule
        if (!empty($price_rule['rule_condition']) && strpos(",{$price_rule['rule_condition']},", ',fromTo,') !== false) {
            $pass_lo = null;
            $pass_hi = null;
            if (!is_null($price_rule['cost_from'])) {
                $pass_lo = $params['PRICE'] >= number_format(floatval($price_rule['cost_from']), 2, '.', '');
            }
            if (!is_null($price_rule['cost_to'])) {
                $pass_hi = $params['PRICE'] <= number_format(floatval($price_rule['cost_to']), 2, '.', '');
            }
            if (!is_null($pass_lo) && is_null($pass_hi)) {
                // only low limit
                if (!$pass_lo) {
                    return false;
                }
            } elseif (is_null($pass_lo) && !is_null($pass_hi)) {
                // only high limit
                if (!$pass_hi) {
                    return false;
                }
            } else if ($pass_lo !== true && $pass_hi !== true) {
                return false;
            }
        }
        $result_cost = \common\helpers\Price_Formula::apply($price_rule['formula'], $params);
        if ($result_cost !== false && !empty($price_rule['rule_condition']) && strpos(",{$price_rule['rule_condition']},", ',notBelow,') !== false) {
            // result price must be greater then not_below
            if ($result_cost < ($price_rule['result_price_not_below'] ?? 0)) {
                $result_cost = false;
            }
        }
        if ($result_cost !== false) {
            $price_rule['resultPrice'] = $result_cost;
            $price_rule['applyParams'] = $params;
            if (isset($data['product'])) {
                $price_rule['product'] = $data['product'];
            }
            return $price_rule;
        }
        return false;
    }
    /**
    * @param $data
    * $apply = [
       'products_id' => 0,
       'categories_id' => [276],
       'manufacturers_id' => 0,
       'currencies_id' => 15,
       'PRICE' => 100.01,
       'MARGIN' => null,
       'SURCHARGE' => 5,
       ];
    * @param null $onlySupplierId
    */
    public static function apply_rules($data, $only_supplier_id)
    {
        $supplier_rules = \common\helpers\Price_Formula::get_supplier_rules_collection($only_supplier_id);
        $rules_priority = ['Category', 'Brand', 'Supplier'];
        if (defined('SUPPLIER_PRICE_RULE_PRIORITY') && SUPPLIER_PRICE_RULE_PRIORITY != '') {
            $rules_priority = explode(',', SUPPLIER_PRICE_RULE_PRIORITY);
        }
        $apply_result = false;
        foreach ($rules_priority as $rule_process) {
            if ($rule_process == 'Category') {
                $applied_categories_group = [];
                foreach ($supplier_rules['category'] as $category_rules) {
                    foreach ($category_rules as $category_rule) {
                        $apply_result = static::apply_supplier_rule($category_rule, $data, $only_supplier_id);
                        if ($apply_result !== false) {
                            $applied_categories_group[] = $apply_result;
                        }
                    }
                }
                if ($apply_result !== false && count($applied_categories_group) > 1) {
                    foreach ($applied_categories_group as $applied_in_group_item) {
                        if ($applied_in_group_item['categoryLevel'] > $apply_result['categoryLevel']) {
                            $apply_result = $applied_in_group_item;
                        } elseif ($applied_in_group_item['categoryLevel'] == $apply_result['categoryLevel'] && $applied_in_group_item['resultPrice'] > $apply_result['resultPrice']) {
                            $apply_result = $applied_in_group_item;
                        }
                    }
                }
            } elseif ($rule_process == 'Brand') {
                if (!empty($data['manufacturers_id']) && isset($supplier_rules['brand'][$data['manufacturers_id']])) {
                    $brand_rules = $supplier_rules['brand'][$data['manufacturers_id']];
                    //foreach ($supplierRules['brand'] as $brandId => $brandRules) {
                    foreach ($brand_rules as $brand_rule) {
                        $apply_result = static::apply_supplier_rule($brand_rule, $data, $only_supplier_id);
                        if (is_array($apply_result)) {
                            break;
                        }
                    }
                    //}
                }
            } elseif ($rule_process == 'Supplier') {
                foreach ($supplier_rules['supplier'] as $supplier_rule) {
                    $apply_result = static::apply_supplier_rule($supplier_rule, $data, $only_supplier_id);
                    if (is_array($apply_result)) {
                        break;
                    }
                }
            }
            if ($apply_result !== false) {
                break;
            }
        }
        return $apply_result;
    }
    public static function is_valid_formula($formula)
    {
        $formula = json_decode($formula, true);
        $valid = true;
        if (is_array($formula) && is_array($formula['formula'])) {
            foreach ($formula['formula'] as $item) {
                $valid = is_array($item) && count($item) && $valid;
            }
        }
        return $valid;
    }
    public static function calculate_extra_order_price()
    {
        $args = func_get_args();
        if (isset($args[0]) && isset($args[1])) {
            // 0=> fields, 1=> obtained data
            $field = $args[0];
            if (is_array($args[1])) {
                $params = $args[1];
                if (isset($params['action'])) {
                    switch ($params['action']) {
                        case 'extra_charge':
                            $response = str_replace(array_map(function ($i) {
                                return '{' . $i . '}';
                            }, array_keys($params['vars'])), array_values($params['vars']), $params['formula']);
                            $response = str_replace('--', '+', $response);
                            try {
                                eval("\$result={$response};");
                            } catch (\Exception $ex) {
                            }
                            if (is_scalar($result)) {
                                return $result;
                            } else {
                                return $params['vars']['init_value'];
                            }
                            break;
                    }
                }
            }
        }
    }
    /**
     * @param \common\models\Products $productModel
     * @param $product_price
     * @param $supplierId don't used at this moment
     * @return true
     * @throws \yii\db\Exception
     */
    public static function update_product_price_by_model($product_model, $product_price, $supplier_id = null): bool
    {
        $product_id = $product_model->products_id;
        $currency_id = (int) (USE_MARKET_PRICES == 'True' ? \common\helpers\currencies::get_currency_id(DEFAULT_CURRENCY) : 0);
        if (strpos($product_id, '{') !== false) {
            $_main_price = $product_model->get_attributes(['products_price', 'products_price_full']);
            //inventory_group_price
            //inventory_full_price
            $update_inventory = "price_prefix = '+', inventory_full_price='" . tep_db_input($product_price) . "'";
            $update_inventory_prices = "price_prefix = '+', inventory_full_price='" . tep_db_input($product_price) . "'";
            if (!$_main_price['products_price_full']) {
                $_prefix = $product_price < $_main_price['products_price'] ? '-' : '+';
                $_price_delta = abs($_main_price['products_price'] - $product_price);
                $update_inventory = "price_prefix = '{$_prefix}', inventory_full_price='" . tep_db_input($_price_delta) . "'";
                $update_inventory_prices = "price_prefix = '{$_prefix}', inventory_full_price='" . tep_db_input($_price_delta) . "'";
            }
            \Yii::$app->db->create_command('UPDATE ' . TABLE_INVENTORY_PRICES . ' ' . "SET {$update_inventory_prices} " . "WHERE prid='" . (int) $product_id . "' AND products_id='" . tep_db_input($product_id) . "' " . " AND groups_id='0' AND currencies_id='" . $currency_id . ' AND products_group_price!=-1 ')->execute();
            //inventory_full_price
            //inventory_price
            \Yii::$app->db->create_command('UPDATE ' . TABLE_INVENTORY . ' ' . "SET {$update_inventory} " . "WHERE prid='" . (int) $product_id . "' AND products_id='" . tep_db_input($product_id) . "'")->execute();
        } else {
            \common\models\Products_Prices::update_all(['products_group_price' => $product_price], "products_id='" . (int) $product_id . "' AND groups_id=0 AND currencies_id='" . $currency_id . "' AND products_group_price!=-1");
            $product_model->products_price = $product_price;
            $product_model->auto_price_modified = (new \DateTime())->format(\DateTime::ATOM);
            $product_model->save(false);
            /** @var $extP \common\extensions\ProductPriceIndex\ProductPriceIndex */
            if ($ext_p = \common\helpers\Extensions::is_allowed('ProductPriceIndex')) {
                $ext_p::reindex((int) $product_id);
            }
        }
        self::log_auto_update_product($product_id, "Price changed: price={$product_price}; supplierId={$supplier_id}");
        return true;
    }
    public static function update_product_price_by_id($product_id, $product_price, $supplier_id = null): bool
    {
        if ($product_id > 0) {
            $product_model = \common\models\Products::find_one($product_id);
            if (!empty($product_model)) {
                return self::update_product_price_by_model($product_model, $product_price, $supplier_id);
            }
        }
        return false;
    }
}