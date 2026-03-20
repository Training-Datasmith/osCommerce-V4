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
 * Price for all conditions
 */
namespace common\components;

use common\models\Products;
use Yii;
class Disabled_Settings
{
    /*probably would be some configurations for each case*/
    private $cases = ['promotion' => false, 'qty_discount' => false, 'group_discount' => false, 'apply_coupon' => false, 'sale' => false, 'bundle_discount' => false, 'configurator_discount' => false];
    public function __construct($products_id)
    {
        $status = 0;
        $tmp = Yii::$container->get('products')->get_product((int) $products_id);
        //->getArrayCopy();
        $status = !!($tmp['disable_discount'] ?? null);
        //        if ($tmp && isset($tmp['disable_discount'])) {
        //          $check = $tmp;
        //        } else
        //
        //        $check = Products::find()->select('disable_discount')
        //                ->where('products_id = :id', [':id' => (int)$products_id])
        //                ->asArray()->one();
        //        if ($check){
        //            $status = $check['disable_discount'];
        //        }
        if ($status) {
            $this->cases = ['promotion' => true, 'qty_discount' => true, 'group_discount' => true, 'apply_coupon' => true, 'sale' => true, 'bundle_discount' => true, 'configurator_discount' => true];
        }
        return $this;
    }
    public function apply_promotion()
    {
        return !$this->cases['promotion'];
    }
    public function apply_qty_discount()
    {
        return !$this->cases['qty_discount'];
    }
    public function apply_group_discount()
    {
        return !$this->cases['group_discount'];
    }
    public function apply_coupon()
    {
        return !$this->cases['apply_coupon'];
    }
    public function apply_sale()
    {
        return !$this->cases['sale'];
    }
    public function apply_bundle_discount()
    {
        return !$this->cases['bundle_discount'];
    }
    public function apply_configurator_discount()
    {
        return !$this->cases['configurator_discount'];
    }
}