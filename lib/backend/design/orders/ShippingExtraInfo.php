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
class Shipping_Extra_Info extends Widget
{
    public $manager;
    public $order;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $extra = '';
        if (!empty($this->order->info['shipping_class'])) {
            if (strpos($this->order->info['shipping_class'], 'collect') !== false) {
                $shipping = $this->manager->get_shipping_collection()->get('collect');
                if ($shipping) {
                    $extra .= $shipping->get_collect_address($this->order->info['shipping_class']);
                }
            } else {
                $module_name = explode('_', $this->order->info['shipping_class']);
                $shipping = $this->manager->get_shipping_collection()->get($module_name[0]);
                if (is_object($shipping) && method_exists($shipping, 'getAdditionalOrderParams')) {
                    $extra .= $shipping->get_additional_order_params([], $this->order->order_id, $this->order->table_prefix);
                }
            }
            return $this->render('shipping-extra-info', ['extra' => $extra]);
        }
    }
}