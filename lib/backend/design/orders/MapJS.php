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
class Map_Js extends Widget
{
    public $addresses = [];
    public $order;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $adds = [];
        if (is_array($this->addresses)) {
            $zoom = 8;
            $a_warehouse = null;
            [$class, $method] = explode('_', $this->order->info['shipping_class']);
            /** @var \common\classes\modules\ModuleShipping $shipping */
            $shipping = $this->order->manager->get_shipping_collection()->get($class);
            if (is_object($shipping)) {
                $collect = $shipping->to_collect($method);
                if ($collect !== false) {
                    /** @var \common\classes\VO\CollectAddress $aWarehouse */
                    $a_warehouse = $collect;
                }
            }
            foreach ($this->addresses as $_address) {
                $address = $_address['address'];
                if (isset($address['country']['zoom'])) {
                    $zoom = max((int) $address['country']['zoom'], 8);
                }
                $adds[] = ['add1' => $a_warehouse !== null ? trim(sprintf('%s, %s, %s', $a_warehouse->get_street_address(), $a_warehouse->get_city(), $a_warehouse->get_country_name())) : $address['street_address'] . ' ' . $address['city'] . ' ' . ($address['country']['title'] ?? ''), 'add2' => $a_warehouse !== null ? $a_warehouse->get_postcode() : $address['postcode'], 'marker' => $_address['marker'], 'zoom' => $zoom];
            }
            return $this->render('map-js', ['key' => \common\components\Google_Tools::instance()->get_map_provider()->get_maps_key(), 'adds' => $adds]);
        }
    }
}