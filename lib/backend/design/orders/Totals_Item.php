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
namespace backend\design\orders;

use yii\base\Widget;
class Totals_Item extends Widget
{
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $p_data = false;
        if (\common\helpers\Acl::check_extension_allowed('CollectionPoints') && $this->order->info['pointto'] > 0) {
            $p_data = \common\extensions\Collection_Points\models\Collection_Points::find_one($this->order->info['pointto']);
        }
        $parent = false;
        if ($this->order && method_exists($this->order, 'getParent')) {
            $parent = $this->order->get_parent();
        }
        $total_item = 0;
        foreach ($this->order->products as $op_record) {
            $total_item += \common\helpers\Product::get_virtual_item_quantity($op_record['id'], $op_record['qty']);
        }
        return $this->render('totals-item', ['items' => $total_item, 'order' => $this->order, 'shipping_weight' => $this->order->info['shipping_weight'], 'parent' => $parent, 'pData' => $p_data]);
    }
}