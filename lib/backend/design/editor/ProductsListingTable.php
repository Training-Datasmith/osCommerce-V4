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
namespace backend\design\editor;

use backend\models\Product_Name_Decorator;
use Yii;
use yii\base\Widget;
class Products_Listing_Table extends Widget
{
    /**
     * @var \common\services\OrderManager
     */
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $cart = $this->manager->get_cart();
        $products = $cart->get_products();
        if (is_array($products)) {
            foreach ($products as &$product) {
                if (is_array($product['attributes'])) {
                    $_attributes = [];
                    foreach ($product['attributes'] as $option => $value) {
                        $attributes_query = tep_db_query('select pa.products_attributes_id, popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from ' . TABLE_PRODUCTS_OPTIONS . ' popt, ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' poval, ' . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . (int) $product['id'] . "' and pa.options_id = '" . (int) $option . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int) $value . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int) $this->manager->get('languages_id') . "' and poval.language_id = '" . (int) $this->manager->get('languages_id') . "'");
                        $attributes = tep_db_fetch_array($attributes_query);
                        $_attributes[] = ['option' => $attributes['products_options_name'], 'value' => $attributes['products_options_values_name'], 'option_id' => $option, 'value_id' => $value];
                    }
                    $product['attributes'] = $_attributes;
                }
            }
            if (Product_Name_Decorator::instance()->use_internal_name_for_order()) {
                $products = Product_Name_Decorator::instance()->get_updated_order_products($products, $this->manager->get('languages_id'), $this->manager->get_platform_id());
            }
            return $this->render('product-listing-table', ['products' => $products, 'cart' => $cart, 'giftWrapExist' => $cart->cart_allow_giftwrap(), 'currencies' => Yii::$container->get('currencies'), 'tax_class_array' => \common\helpers\Tax::get_complex_classes_list(), 'tax_address' => $this->manager->get_order_instance()->tax_address, 'products_price_qty_round' => PRODUCTS_PRICE_QTY_ROUND == 'true', 'manager' => $this->manager, 'queryParams' => array_merge(['editor/show-basket'], Yii::$app->request->get_query_params())]);
        }
    }
}