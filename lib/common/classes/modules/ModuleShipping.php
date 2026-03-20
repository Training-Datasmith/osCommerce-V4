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

abstract class Module_Shipping extends Module
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
     * @return bool
     */
    public function shipment_exists(int $order_id)
    {
        $check_order = \common\models\Orders::find()->select(['orders_id', 'shipping_class', 'tracking_number', 'parcel_label_pdf'])->and_where(['orders_id' => $order_id])->as_array()->one();
        list($module, $method) = explode('_', $check_order['shipping_class']);
        if ($module == $this->code) {
            if ($check_order['parcel_label_pdf'] != '') {
                return true;
            }
        } else {
            return false;
        }
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
        //$this->checkLabels(); //??!! use hasLabelModule
    }
    public function get_preferred_labels()
    {
        $visibility_access = [];
        $modules_labels = \common\models\Modules_Labels::find_one(['platform_id' => $this->platform_id, 'code' => $this->code]);
        if (!is_null($modules_labels) && !empty($modules_labels->labels_list)) {
            $visibility_access = explode(',', $modules_labels->labels_list);
        }
        return $visibility_access;
    }
    public function check_labels()
    {
        $modules_labels = \common\models\Modules_Labels::find_one(['platform_id' => $this->platform_id, 'code' => $this->code]);
        if (!is_null($modules_labels) && !empty($modules_labels->labels_list)) {
            $this->tracking = true;
        }
    }
    public function has_label_module()
    {
        $labels = \common\helpers\Modules::get_labels_list($this->platform_id);
        if (count($labels) > 0) {
            return true;
        }
        return false;
        /*
        $modulesLabels = \common\models\ModulesLabels::findOne(['platform_id' => $this->platform_id, 'code' => $this->code]);
        if (!is_null($modulesLabels) && !empty($modulesLabels->labels_list)) {
            return true;
        }
        return false;
        */
    }
    public function get_labels($platform_id)
    {
        if ((int) $platform_id == 0) {
            return '';
        }
        $labels = \common\helpers\Modules::get_labels_list($platform_id);
        $modules_labels = \common\models\Modules_Labels::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        $visibility_access = [];
        if (!is_null($modules_labels) && !empty($modules_labels->labels_list)) {
            $visibility_access = explode(',', $modules_labels->labels_list);
        }
        $response = '<br><br><table width="50%" id="module_labels_restriction" style="max-height:350px"><thead><tr><th>' . MODULE_SHIPPING_TRACKING_STATUS . ' ' . tep_draw_checkbox_field('enable_tracking', '1', !is_null($modules_labels), '', 'onchange="return updateLabels(this);" class="uniform" ') . '</th></thead><tbody>';
        foreach ($labels as $id => $name) {
            $response .= '<tr><td>';
            $params = 'class="uniform" ';
            if (is_null($modules_labels)) {
                $params .= 'disabled';
            }
            $response .= '<label>';
            $response .= tep_draw_checkbox_field('labels[]', $id, in_array($id, $visibility_access), '', $params);
            $response .= $name;
            $response .= '</label>';
            $response .= '</td></tr>';
        }
        $response .= '</tbody></table>';
        $response .= '<script type="text/javascript">function updateLabels(obj) { if ( $(obj).is(":checked") ) { $("input[name^=\'labels\']").prop("disabled", false); $("#module_labels_restriction div.checker").length && $("#module_labels_restriction div.checker.disabled").removeClass("disabled"); } else { $("input[name^=\'labels\']").prop("disabled", true); $("#module_labels_restriction div.checker").length && $("#module_labels_restriction tbody div.checker").addClass("disabled"); } }</script>';
        return $response;
    }
    public function set_labels()
    {
        $platform_id = (int) \Yii::$app->request->post('platform_id');
        if ((int) $platform_id == 0) {
            return false;
        }
        $modules_labels = \common\models\Modules_Labels::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        $enable_tracking = (int) \Yii::$app->request->post('enable_tracking');
        if ($enable_tracking == 1) {
            $labels = \Yii::$app->request->post('labels', []);
            if (is_null($modules_labels)) {
                $modules_labels = new \common\models\Modules_Labels();
                $modules_labels->platform_id = $platform_id;
                $modules_labels->code = $this->code;
            }
            $modules_labels->labels_list = implode(',', $labels);
            $modules_labels->save();
        } else if (!is_null($modules_labels)) {
            $modules_labels->delete();
        }
    }
    /**
     * used in shipping labels
     * @return bool
     */
    public function need_delivery_date()
    {
        return false;
    }
    /**
     * @param string $method
     * @param null|array|object $data
     * @return bool|array
     */
    public function validate(string $method = '', $data = null)
    {
        return true;
    }
    /**
     * shipping to collect
     * return false or \common\classes\VO\CollectAddress with address warehouse
     * @see \common\modules\orderShipping\np
     * @param string $method
     * @return bool|\common\classes\VO\CollectAddress
     */
    public function to_collect(string $method = '')
    {
        return false;
    }
    public function get_extra_disabled_days()
    {
        return false;
    }
    public function is_online()
    {
        return false;
    }
}