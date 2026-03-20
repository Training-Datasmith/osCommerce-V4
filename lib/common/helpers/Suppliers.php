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

use common\models\Products_Prices;
use common\models\Suppliers as sModel;
use common\models\Suppliers_Products as spModel;
use yii\db\Expression;
class Suppliers
{
    public static function get_suppliers_count($include_inactive = false)
    {
        $model = S_Model::find();
        if (!$include_inactive) {
            $model->where(['status' => 1]);
        }
        return $model->count();
    }
    public static function get_default_supplier()
    {
        return S_Model::find_one(['is_default' => 1]);
    }
    public static function get_default_supplier_id()
    {
        static $_def_id = null;
        if (is_null($_def_id)) {
            $_def_id = 0;
            $supplier = S_Model::find_one(['is_default' => 1]);
            if ($supplier) {
                $_def_id = $supplier->suppliers_id;
            }
        }
        return $_def_id;
    }
    public static function ordered_ids()
    {
        static $ids;
        if (!is_array($ids)) {
            $ids = [];
            foreach (\common\models\Suppliers::find()->select('suppliers_id')->order_by('is_default DESC, sort_order, suppliers_name')->as_array()->all() as $_tmp) {
                $ids[] = $_tmp['suppliers_id'];
            }
        }
        return $ids;
    }
    public static function ordered_ids_for_product($products_id)
    {
        return \common\models\Suppliers_Products::find()->alias('sp')->select('sp.suppliers_id')->join_with('supplier s')->where(['sp.products_id' => (int) $products_id])->order_by(new \yii\db\Expression('if(sp.sort_order is null, s.sort_order, sp.sort_order)'))->column();
    }
    public static function ordered_active_ids()
    {
        static $ids;
        if (!is_array($ids)) {
            $ids = [];
            foreach (\common\models\Suppliers::find()->select('suppliers_id')->where(['status' => 1])->order_by('is_default DESC, sort_order, suppliers_name')->as_array()->all() as $_tmp) {
                $ids[] = (int) $_tmp['suppliers_id'];
            }
        }
        return $ids;
    }
    /*get all active suppliers*/
    public static function get_suppliers($as_array = false)
    {
        return S_Model::find()->where(['status' => 1])->order_by('is_default DESC, sort_order, suppliers_name')->as_array($as_array)->all();
    }
    /*get supplier product with related supplier */
    public static function get_suppliers_to_uprid($uprid)
    {
        if (strpos($uprid, '{') !== false) {
            $s_models = Sp_Model::get_supplier_uprid_products($uprid)->all();
        } else {
            $s_models = Sp_Model::get_supplier_products($uprid)->all();
        }
        return $s_models;
    }
    /*get suppliers list for dropdown*/
    public static function get_suppliers_list($uprid = null, $as_array = false)
    {
        if (is_null($uprid)) {
            return \yii\helpers\Array_Helper::map(self::get_suppliers($as_array), 'suppliers_id', 'suppliers_name');
        } else {
            $list = [];
            $sp = self::get_suppliers_to_uprid($uprid);
            if ($sp) {
                foreach ($sp as $_sp) {
                    $list[$_sp->suppliers_id] = $_sp->supplier->suppliers_name;
                }
            }
            return $list;
        }
    }
    public static function get_supplier_name($supplier_id)
    {
        $suppliers_name = '';
        $supplier = S_Model::find_one(['suppliers_id' => $supplier_id]);
        if ($supplier) {
            $suppliers_name = $supplier->suppliers_name;
        }
        return $suppliers_name;
    }
    public static function get_supplier_id_by_name($supplier_name)
    {
        $ret = null;
        $supplier = S_Model::find()->where('suppliers_name like :name', [':name' => $supplier_name]);
        if (!is_null($supplier) && $supplier->count() == 1) {
            $ret = $supplier->one()->suppliers_id;
        }
        return $ret;
    }
    public static function remove_uprids($products_id)
    {
        $_uprids = Sp_Model::find()->where(['products_id' => (int) $products_id])->all();
        foreach ($_uprids as $e_product) {
            if (strval($e_product->uprid) != strval($e_product->products_id)) {
                $e_product->delete();
            }
        }
    }
    public static function on_update_price_mode_switch($new_value)
    {
        if ($new_value == 'Auto') {
            // recalculate price
        }
    }
    public static function update_product_price($product)
    {
        $product_query = Products_Prices::find()->where(['products_id' => (int) $product, 'groups_id' => 0]);
        if (SUPPLIER_UPDATE_PRICE_MODE == 'Auto') {
            $product_query->and_where(['OR', ['IS', 'supplier_price_manual', new Expression('NULL')], ['supplier_price_manual' => 0]]);
        } else {
            $product_query->and_where(['OR', ['IS NOT', 'supplier_price_manual', new Expression('NULL')], ['supplier_price_manual' => 0]]);
        }
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True') {
            $product_query->and_where(['currencies_id' => \common\helpers\Currencies::get_currency_id(\common\helpers\Currencies::system_currency_code())]);
        } else {
            $product_query->and_where(['currencies_id' => 0]);
        }
        //echo $productQuery->createCommand()->getRawSql();
        $product_query->all();
    }
    public static function get_default_product_price($product_id, $qty = 0)
    {
        static $cache = [];
        $key = $product_id . '_' . $qty;
        $price = 0;
        if (!isset($cache[$key])) {
            $q = Sp_Model::find()->and_where(['products_id' => \common\helpers\Inventory::get_prid($product_id), 'uprid' => $product_id, 'is_default' => 1]);
            $sp = $q->as_array()->one();
            if ($sp) {
                //?? round?? $currencies = Yii::$container->get('currencies');
                //?? $currencies->format_clear($totalItemCountryPrice, true, $products['currency'], $products['currency_value']);
                //?? \common\helpers\Tax::add_tax_always($products['final_price'], $products['products_tax'])
                $price = $sp['suppliers_price'];
            }
            $cache[$key] = $price;
        }
        return $cache[$key];
    }
    public static function get_discount_values_array($suppliers_price_discount)
    {
        $suppliers_qty_discounts = [];
        foreach (explode(';', $suppliers_price_discount) as $qty_discount) {
            if (!empty($qty_discount)) {
                $arr = explode(':', $qty_discount);
                $qty = $arr[0] ?? null;
                $price = $arr[1] ?? null;
                if ($qty > 0 && $price > 0) {
                    $suppliers_qty_discounts[] = ['qty' => $qty, 'price' => $price];
                }
            }
        }
        return $suppliers_qty_discounts;
    }
    public static function get_discount_values_table($suppliers_price_discount_post)
    {
        $suppliers_price_discount = '';
        if (is_array($suppliers_price_discount_post) && ($suppliers_price_discount_post['status'] ?? false)) {
            $suppliers_price_discount_array = [];
            $discount_qty = $suppliers_price_discount_post['qty'];
            $discount_price = $suppliers_price_discount_post['price'];
            if (is_array($discount_qty) && is_array($discount_price)) {
                foreach ($discount_qty as $key => $val) {
                    if ($discount_qty[$key] > 0 && $discount_price[$key] > 0) {
                        $suppliers_price_discount_array[$discount_qty[$key]] = $discount_price[$key];
                    }
                }
            }
            ksort($suppliers_price_discount_array, SORT_NUMERIC);
            foreach ($suppliers_price_discount_array as $qty => $price) {
                $suppliers_price_discount .= $qty . ':' . $price . ';';
            }
        }
        return $suppliers_price_discount;
    }
}