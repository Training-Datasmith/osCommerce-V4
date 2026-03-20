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
namespace common\classes\modules;

use common\models\Orders_Label;
abstract class Module_Label extends Module
{
    public $shipping_weight;
    public $shipping_num_boxes;
    public $platform_id;
    public $tracking = false;
    public function possible_methods()
    {
        return [];
    }
    public function quote($method = '')
    {
    }
    /**
     * @param int $order_id
     * @param int $orders_label_id
     * @return bool
     */
    public function shipment_exists(int $order_id, int $orders_label_id)
    {
        $check_label = \common\models\Orders_Label::find()->select(['orders_id', 'label_class', 'tracking_number', 'parcel_label_pdf'])->and_where(['orders_id' => $order_id, 'orders_label_id' => $orders_label_id])->as_array()->one();
        list($module, $method) = explode('_', $check_label['label_class']);
        if ($module == $this->code) {
            if ($check_label['parcel_label_pdf'] != '') {
                return true;
            }
        } else {
            return false;
        }
    }
    /**
     * @param int $order_id
     * @param int $orders_label_id
     * @return float
     */
    public function shipment_total(int $order_id, int $orders_label_id)
    {
        $shipment_total = 0;
        $o_label = \common\models\Orders_Label::find_one(['orders_id' => $order_id, 'orders_label_id' => $orders_label_id]);
        foreach ($o_label->get_orders_label_products() as $orders_products_id => $qty) {
            $o_product = \common\models\Orders_Products::find_one(['orders_id' => $order_id, 'orders_products_id' => $orders_products_id]);
            if ($o_product->final_price > 0) {
                $shipment_total += \common\helpers\Tax::add_tax_always($o_product->final_price, $o_product->products_tax) * $qty;
            }
        }
        return $shipment_total;
    }
    /**
     * @param int $order_id
     * @param int $orders_label_id
     * @return float
     */
    public function shipment_weight(int $order_id, int $orders_label_id)
    {
        $shipment_weight = 0;
        $o_label = \common\models\Orders_Label::find_one(['orders_id' => $order_id, 'orders_label_id' => $orders_label_id]);
        foreach ($o_label->get_orders_label_products() as $orders_products_id => $qty) {
            $o_product = \common\models\Orders_Products::find_one(['orders_id' => $order_id, 'orders_products_id' => $orders_products_id]);
            if ($o_product->products_weight > 0) {
                $shipment_weight += $o_product->products_weight * $qty;
            } else {
                $shipment_weight += \common\helpers\Product::get_products_weight($o_product->products_id) * $qty;
            }
        }
        return $shipment_weight;
    }
    /**
     * @param int $order_id
     * @param int $orders_label_id
     * @return float|int
     */
    public function shipment_volume_weight(int $order_id, int $orders_label_id)
    {
        $shipment_volume = 0;
        $o_label = \common\models\Orders_Label::find_one(['orders_id' => $order_id, 'orders_label_id' => $orders_label_id]);
        foreach ($o_label->get_orders_label_products() as $orders_products_id => $qty) {
            $o_product = \common\models\Orders_Products::find_one(['orders_id' => $order_id, 'orders_products_id' => $orders_products_id]);
            $shipment_volume += \common\helpers\Product::get_products_volume($o_product->products_id, true) * $qty;
        }
        return $shipment_volume;
    }
    /**
     * check delivery date
     * @param type $delivery_date
     * @return boolean
     */
    public function check_delivery_date($delivery_date)
    {
        if (tep_not_null($delivery_date) && $delivery_date != '0000-00-00') {
            return true;
        }
        return false;
    }
    /*if used physical delivery*/
    public function use_delivery()
    {
        return true;
    }
    public function set_weight($weight)
    {
        $this->shipping_weight = $weight;
    }
    public function set_num_boxes($num_boxes)
    {
        $this->shipping_num_boxes = $num_boxes;
    }
    public function set_platform(int $platform_id)
    {
        $this->platform_id = $platform_id;
    }
    public function get_group_restriction($platform_id)
    {
        return '';
    }
    public function get_restriction($platform_id, $languages_id, $ignore_visibility = false)
    {
        return parent::get_restriction($platform_id, $languages_id, true);
    }
    public function without_settings(Orders_Label $orders_label): bool
    {
        return false;
    }
    /**
     * Allows to show extra params for label
     * @param $order_label_id
     * @return string html
     *
     * Supports simplified method getExtraParamsArray like this:
     *
     * public function getExtraParamsArray($order_label_id)
     * {
     *    return [
     *      'param1' => [
     *          'label' => 'Param1',
     *          'type'  => 'edit',
     *          'value' => 'sample text',
     *      ],
     *      'param2' => [
     *          'label' => 'Param2',
     *          'type'  => 'checkbox',
     *          'value' => true,
     *      ],
     *      'param3' => [
     *          'label' => 'Param3',
     *          'type'  => 'dropdown',
     *          'dropdown' => ['one' => 'Name1', 'two' => 'Name2'],
     *          'value' => 'one',
     *      ],
     *  ];
     * }
     *
     */
    public function get_extra_params($order_label_id)
    {
        $html = '';
        if (method_exists($this, 'getExtraParamsArray') && !empty($extra_params = $this->get_extra_params_array($order_label_id))) {
            $module_code = static::get_module_code();
            $html .= '<div class="label-params">';
            foreach ($extra_params as $name => $info) {
                $param_name = $module_code . '_' . $name;
                $html .= '<div class="label-param">';
                try {
                    if (!empty($info['label']) && $info['type'] != 'checkbox') {
                        $html .= sprintf('<label for="%s">%s</label>', $param_name, $info['label']);
                    }
                    switch ($info['type']) {
                        case 'edit':
                            $html .= \common\helpers\Html::text_input($param_name, $info['value'] ?? null, ['id' => $param_name]);
                            break;
                        case 'checkbox':
                            $html .= \common\helpers\Html::checkbox($param_name, $info['value'] ?? false, ['id' => $param_name]);
                            if (!empty($info['label'])) {
                                $html .= sprintf('<label for="%s">%s</label>', $param_name, $info['label']);
                            }
                            break;
                        case 'dropdown':
                            $html .= \common\helpers\Html::drop_down_list($param_name, $info['value'] ?? null, $info['dropdown'], ['id' => $param_name]);
                            break;
                    }
                } catch (\Throwable $e) {
                    \common\helpers\Php::handle_error_prod($e, __FUNCTION__, "label/{$module_code}");
                }
                $html .= '</div>';
            }
            $html .= '</div';
        }
        return $html;
    }
    public function extract_extra_params_values($params)
    {
        $module_code = static::get_module_code();
        if (method_exists($this, 'getExtraParamsArray') && !empty($extra_params = $this->get_extra_params_array())) {
            $res = [];
            foreach ($extra_params as $name => $info) {
                $param_name = $module_code . '_' . $name;
                if (isset($params[$param_name])) {
                    $res[$name] = $params[$param_name];
                }
            }
            return $res;
        }
    }
    public function load_extra_params($order_label_id)
    {
        $rec = Orders_Label::find_one(['orders_label_id' => $order_label_id]);
        if (!empty($rec) && !empty($rec->extra_params)) {
            return @json_decode($rec->extra_params, true);
        }
    }
    public function is_extra_params_available()
    {
        return method_exists($this, 'getExtraParamsArray') && !empty($this->get_extra_params_array());
    }
}