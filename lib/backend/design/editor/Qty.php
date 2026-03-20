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

use yii\base\Widget;
class Qty extends Widget
{
    public $manager;
    public $product;
    public $is_pack = false;
    public $max;
    public $min = "data-min='1'";
    public $step = "data-step='1'";
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $this->max = $this->product['stock_info']['max_qty'] + $this->product['reserved_qty'];
        if (!isset($this->product['stock_limits'])) {
            $this->product['stock_limits'] = \common\helpers\Product::get_product_order_quantity($this->product['id']);
        }
        if (\common\helpers\Acl::check_extension_allowed('MinimumOrderQty', 'allowed')) {
            $this->min = \common\extensions\Minimum_Order_Qty\Minimum_Order_Qty::set_limit($this->product['stock_limits']);
        }
        if ($oqs = \common\helpers\Extensions::is_allowed('OrderQuantityStep')) {
            $this->step = $oqs::set_limit($this->product['stock_limits']);
        }
        if ($this->is_pack) {
            $insulator = new \backend\services\Product_Insulator_Service($this->product['id'], $this->manager);
            $_product = $insulator->get_product();
            if ($_product) {
                $this->product['data'] = $_product->get_attributes();
                $m = [$this->max, floor($this->product['data']['pack_unit'] ? $this->max / $this->product['data']['pack_unit'] : 0), floor($this->product['data']['packaging'] ? $this->max / $this->product['data']['packaging'] : 0)];
                $this->max = $m;
            }
            $this->min = "data-min='0'";
            //thais is becuase very cool extension!!!
        }
        return $this->render('qty', ['product' => $this->product, 'isPack' => $this->is_pack, 'max' => $this->max, 'min' => $this->min, 'step' => $this->step]);
    }
}