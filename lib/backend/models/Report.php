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
namespace backend\models;

use backend\models\Report\Daily_Report;
use backend\models\Report\Hourly_Report;
use backend\models\Report\Monthly_Report;
use backend\models\Report\Quarterly_Report;
use backend\models\Report\Weekly_Report;
use backend\models\Report\Yearly_Report;
use Yii;
class Report
{
    private $_precision = 'daily';
    private $data = [];
    private $_report;
    public $manager;
    public function __construct($vars)
    {
        $this->data = $vars;
        if (isset($vars['type'])) {
            $this->set_precision($vars['type']);
        }
        $platform_config = new \common\classes\platform_config(\common\classes\platform::default_id());
        $platform_config->constant_up();
        $this->manager = \common\services\Order_Manager::load_manager();
    }
    public function set_precision($value)
    {
        if (!array_key_exists($value, $this->precision_list())) {
            return;
        }
        $this->_precision = $value;
    }
    public function get_precision()
    {
        return $this->_precision;
    }
    public function precision_list()
    {
        return ['hourly' => STATISTICS_TYPE_HOURLY, 'daily' => STATISTICS_TYPE_DAILY, 'weekly' => STATISTICS_TYPE_WEEKLY, 'monthly' => STATISTICS_TYPE_MONTHLY, 'quarterly' => STATISTICS_TYPE_QUARTERLY, 'yearly' => STATISTICS_TYPE_YEARLY];
    }
    public function get_charts_groups()
    {
        $list = [['orders' => ['label' => TEXT_ORDERS, 'selected' => $this->is_selected_chart('orders'), 'color' => '#005dc3']], ['orders_avg' => ['label' => 'Average number of orders', 'selected' => $this->is_selected_chart('orders_avg', false), 'color' => '#2a6ebe', 'disabled' => $this->is_disabled_status()]], ['ot_tax' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_TAX_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_tax'), 'color' => '#619193']], ['ot_subtotal' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_SUBTOTAL_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_subtotal'), 'color' => '#24b71e'], 'ot_total' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_TOTAL_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_total'), 'color' => '#ed3d05']], ['total_avg' => ['label' => 'Average Total', 'selected' => $this->is_selected_chart('total_avg', false), 'color' => '#2a6ebe', 'disabled' => $this->is_disabled_status()]], ['ot_shipping' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_SHIPPING_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_shipping'), 'color' => '#1aa69b']], ['ot_paid' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_PAID_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_paid'), 'color' => '#24b71e'], 'ot_due' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_DUE_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_due'), 'color' => '#ed3d05'], 'ot_refund' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_REFUND_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_refund'), 'color' => '#1aa69b']], ['ot_gift_wrap' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_GIFT_WRAP_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_gift_wrap'), 'color' => '#fe9f00'], 'ot_coupon' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_COUPON_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_coupon'), 'color' => '#065d60'], 'ot_gv' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_GV_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_gv'), 'color' => '#ed3d05'], 'ot_loworderfee' => ['label' => \common\helpers\Translation::get_translation_value('MODULE_ORDER_TOTAL_LOWORDERFEE_TITLE', 'ordertotal'), 'selected' => $this->is_selected_chart('ot_loworderfee'), 'color' => '#1ab8f9']], ['cost_amount' => ['label' => TEXT_COST, 'selected' => $this->is_selected_chart('cost_amount', false), 'color' => '#2a6ebe'], 'profit_amount' => ['label' => TEXT_PROFIT, 'selected' => $this->is_selected_chart('profit_amount', false), 'color' => '#24b71e'], 'profit_percent' => ['label' => TEXT_PROFIT . ' (%)', 'selected' => $this->is_selected_chart('profit_percent', false), 'color' => '#ed3d05']]];
        $order_total_modules = $this->manager->get_total_collection();
        foreach ($list as $key => $modules) {
            foreach ($modules as $module => $info) {
                if (substr($module, 0, 3) == 'ot_') {
                    if (!$order_total_modules->get($module)) {
                        unset($list[$key][$module]);
                    }
                }
            }
        }
        return $list;
    }
    public function is_selected_chart($chart, $default = true)
    {
        $status = $default;
        if (isset($this->data['chart_group_item']) && is_array($this->data['chart_group_item'])) {
            $status = isset($this->data['chart_group_item'][$chart]);
        }
        return $status;
    }
    public function get_report_model()
    {
        switch ($this->_precision) {
            case 'hourly':
                $this->_report = new Hourly_Report($this->data);
                break;
            case 'weekly':
                $this->_report = new Weekly_Report($this->data);
                break;
            case 'monthly':
                $this->_report = new Monthly_Report($this->data);
                break;
            case 'quarterly':
                $this->_report = new Quarterly_Report($this->data);
                break;
            case 'yearly':
                $this->_report = new Yearly_Report($this->data);
                break;
            default:
            case 'daily':
                $this->_report = new Daily_Report($this->data);
                break;
        }
        return $this->_report;
    }
    public function get_statuses()
    {
        return \common\helpers\Order::get_status_list(false, false);
    }
    public function get_selected_statuses()
    {
        if (isset($this->data['status'])) {
            return $this->data['status'];
        }
        return [];
    }
    public function get_selected_payments()
    {
        if (isset($this->data['payment_methods'])) {
            return $this->data['payment_methods'];
        }
        return [];
    }
    public function get_selected_shippings()
    {
        if (isset($this->data['shipping_methods'])) {
            return $this->data['shipping_methods'];
        }
        return [];
    }
    public function get_selected_platforms()
    {
        if (isset($this->data['platforms'])) {
            return $this->data['platforms'];
        }
        return [];
    }
    public function get_selected_zones()
    {
        if (isset($this->data['zones'])) {
            return $this->data['zones'];
        }
        return [];
    }
    public function get_selected_country()
    {
        if (isset($this->data['country'])) {
            return $this->data['country'];
        }
        return '';
    }
    public function get_selected_state()
    {
        if (isset($this->data['state'])) {
            return $this->data['state'];
        }
        return '';
    }
    public function get_selected_sps()
    {
        if (isset($this->data['sps'])) {
            return $this->data['sps'];
        }
        return '';
    }
    public function get_selected_geo_type()
    {
        if (isset($this->data['geo_type'])) {
            return $this->data['geo_type'];
        }
        return 0;
    }
    public function get_with_products()
    {
        if (isset($this->data['with_products'])) {
            return $this->data['with_products'];
        }
        return 0;
    }
    public function get_shippings()
    {
        $shipping_methods = [];
        $shipping_methods_query = tep_db_query('select distinct shipping_class from ' . TABLE_ORDERS . ' where 1 order by shipping_class');
        if (tep_db_num_rows($shipping_methods_query)) {
            $shipping_modules = $this->manager->get_shipping_collection();
            while ($row = tep_db_fetch_array($shipping_methods_query)) {
                $_shipping = $row['shipping_class'];
                if (empty($_shipping)) {
                    continue;
                }
                $modules = explode('_', $_shipping);
                $module = $shipping_modules->get_module($modules[0]);
                if (is_object($module)) {
                    $shipping_methods[$_shipping] = $module->get_title($_shipping);
                } else {
                    $shipping_methods[$_shipping] = $_shipping;
                }
            }
        }
        return $shipping_methods;
    }
    public function get_payments()
    {
        $payment_methods = [];
        $payment_methods_query = tep_db_query('select distinct payment_class from ' . TABLE_ORDERS . ' where 1 order by payment_class');
        if (tep_db_num_rows($payment_methods_query)) {
            $payment_modules = $this->manager->get_payment_collection();
            while ($row = tep_db_fetch_array($payment_methods_query)) {
                $_payment = $row['payment_class'];
                if (empty($_payment)) {
                    continue;
                }
                $module = $payment_modules->get_module($_payment);
                if (!is_object($module)) {
                    list($pmodule, $method) = explode('_', $_payment);
                    $module = $payment_modules->get_module($pmodule);
                }
                if ($module) {
                    if (method_exists($module, 'getTitle')) {
                        $payment_methods[$_payment] = $module->get_title($_payment);
                    } else {
                        $payment_methods[$_payment] = $module->title;
                    }
                } else {
                    $payment_methods[$_payment] = $_payment;
                }
            }
        }
        return $payment_methods;
    }
    public function get_platforms()
    {
        $_platforms = \common\classes\platform::get_list(true, true);
        $platforms = \yii\helpers\Array_Helper::map($_platforms, 'id', 'text');
        return $platforms;
    }
    public function get_geo_type()
    {
        return ['By Zones', 'By Address'];
    }
    public function get_geo_zones()
    {
        global $languages_id;
        $_zones = [];
        $zone_query = tep_db_query('select gz.geo_zone_id, gz.geo_zone_name, c.countries_name, zgz.zone_country_id from ' . TABLE_GEO_ZONES . ' gz, ' . TABLE_ZONES_TO_GEO_ZONES . ' zgz left join ' . TABLE_COUNTRIES . " c on c.countries_id = zgz.zone_country_id and c.language_id = '" . (int) $languages_id . "' where gz.geo_zone_id = zgz.geo_zone_id order by geo_zone_name, countries_name");
        while ($row = tep_db_fetch_array($zone_query)) {
            $_zones[] = $row;
        }
        $zones = \yii\helpers\Array_Helper::map($_zones, 'zone_country_id', 'countries_name', 'geo_zone_name');
        return $zones;
    }
    public function get_customer_groups($code = '', $empty_string = false)
    {
        $variants = [];
        if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            $variants = \common\helpers\Group::get_customer_groups_list($code, $empty_string);
        }
        return $variants;
    }
    public function get_walk_in_admins()
    {
        if (isset($this->data['walkin']) && is_array($this->data['walkin'])) {
            return array_map('intval', $this->data['walkin']);
        }
        return [];
    }
    public function get_selected_customer_groups()
    {
        if (isset($this->data['customer_groups'])) {
            return $this->data['customer_groups'];
        }
        return [];
    }
    public function get_currencies()
    {
        $variants = [0 => TEXT_ALL];
        $currencies = Yii::$container->get('currencies');
        if (is_array($currencies->currencies)) {
            foreach ($currencies->currencies as $currency) {
                $variants[$currency['id']] = $currency['title'] . ' [' . $currency['code'] . ']';
            }
        }
        return $variants;
    }
    public function get_selected_currency()
    {
        if (isset($this->data['currency'])) {
            return $this->data['currency'];
        }
        return 0;
    }
    public function is_disabled_status()
    {
        if (in_array($this->get_precision(), $this->get_undisabled_charts())) {
            return false;
        }
        return true;
    }
    public function get_undisabled_charts()
    {
        return ['hourly', 'quarterly'];
    }
    public static function get_filters()
    {
        $filters_query = tep_db_query('select sales_filter_vals, sales_filter_name from ' . TABLE_SALES_FILTERS . ' order by sales_filter_name');
        $filters = [];
        while ($d = tep_db_fetch_array($filters_query)) {
            $filters[\yii\helpers\Url::to(['sales_statistics/index']) . '?' . $d['sales_filter_vals']] = $d['sales_filter_name'];
        }
        return $filters;
    }
    public function filter_data($enabled, $data)
    {
        if (is_array($enabled) && count($enabled)) {
            $_temp = [];
            foreach ($data as $block) {
                unset($block['period_full']);
                foreach ($block as $key => $items) {
                    if (!in_array($key, $enabled) && $key != 'period') {
                        unset($block[$key]);
                    }
                }
                $_temp[] = $block;
            }
            $data = $_temp;
        }
        return $data;
    }
    public function export($data, array $params)
    {
        if (!isset($params['type'])) {
            $params['type'] = 'CSV';
        }
        //if (!isset($params['modules']))
        //    $params['modules'] = [];
        //$data = $this->filterData($params['modules'], $data);
        switch ($params['type']) {
            case 'XLS':
            case 'CSV':
            default:
                $this->_export_csv($data, $params);
                break;
        }
    }
    private function _get_filename($type)
    {
        return 'sale_statistics_' . date('dmY_His') . '.' . $type;
    }
    private function _set_headers($filename)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        // always modified
        header('Pragma: public');
        // HTTP/1.0
        header('Cache-Control: cache, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Filename: ' . $filename);
    }
    private function _export_csv($data, $params)
    {
        $filename = $this->_get_filename('csv');
        //$this->_setHeaders($filename);
        $CSV = new \backend\models\EP\Formatter\CSV('write', [], $filename);
        if (is_array($data) && count($data)) {
            $headers = [];
            foreach (array_keys($data[0]) as $key) {
                if ($key == 'period_full') {
                    continue;
                }
                if (strpos($key, 'ot_') !== false) {
                    $key = substr($key, 3);
                }
                $headers[] = $this->_report->convert_column_title($key);
            }
            $CSV->write_array($headers);
            header('Content-Filename: ' . $filename);
            foreach ($data as $row) {
                $products = [];
                if ($row['products']) {
                    $products = $row['products'];
                }
                $row['products'] = null;
                if (!is_null($params['start']) && !is_null($params['end'])) {
                    if (strtotime($row['period_full']) < $params['start'] / 1000 || strtotime($row['period_full']) > $params['end'] / 1000) {
                        continue;
                    }
                }
                unset($row['period_full']);
                $CSV->write_array($row);
                if ($products) {
                    foreach ($products as $product) {
                        $p_row = [$product['products_name'] . ($product['products_model'] ? ' (' . $product['products_model'] . ')' : ''), $product['products_quantity'], $product['final_price']];
                        $CSV->write_array($p_row);
                    }
                }
            }
        }
    }
}