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
namespace backend\controllers;

use backend\models\Report;
use Yii;
class Sales_statistics_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_REPORTS', 'BOX_REPORTS_SALES'];
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('shipping');
        \common\helpers\Translation::init('ordertotal');
        \common\helpers\Translation::init('admin/sales_statistics');
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        $this->selected_menu = ['reports', 'sales_statistics'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('sales_statistics/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        \common\helpers\Translation::init('admin/orders');
        $this->view->filter = new \stdClass();
        $report = new Report($_GET);
        $this->view->filter->precision = $report->precision_list();
        $this->view->filter->precision_selected = $report->get_precision();
        $this->view->filter->statuses = $report->get_statuses();
        $this->view->filter->payment_methods = $report->get_payments();
        $this->view->filter->shipping_methods = $report->get_shippings();
        $this->view->filter->platforms = $report->get_platforms();
        $this->view->filter->customer_groups = $report->get_customer_groups('', true);
        $this->view->filter->currencies = $report->get_currencies();
        $this->view->filter->walkin = Yii::$app->request->get('walkin') ?? false;
        $this->view->filter->admin = [];
        foreach (\common\helpers\Admin::get_admins_with_walkin_orders() as $admin) {
            $this->view->filter->admin[$admin->admin_id] = $admin->admin_firstname . ' ' . $admin->admin_lastname;
        }
        $this->view->filter->charts = $report->get_charts_groups();
        $model = $report->get_report_model();
        $m_titles = $model->get_ot_modules();
        $data = $model->load_purchases();
        $columns = [];
        if (is_array($data) && count($data)) {
            $_columns = array_keys($data[0]);
            foreach ($_columns as $v) {
                $columns[] = ['class' => $v];
            }
            $m_titles = \yii\helpers\Array_Helper::map($m_titles, 'class', 'title');
            foreach ($columns as $k => $c) {
                if (isset($m_titles[$c['class']])) {
                    $columns[$k]['title'] = $m_titles[$c['class']];
                } else {
                    $columns[$k]['title'] = $model->convert_column_title($columns[$k]['class']);
                }
            }
        }
        $ph = $report->get_selected_platforms();
        if (empty($ph)) {
            $ph = [];
            foreach (\common\classes\platform::get_list(true, true) as $p) {
                $ph[] = $p['id'];
            }
        }
        $params = ['options' => $model->get_range_list(), 'data' => $data, 'columns' => $columns, 'range' => $this->render_ajax('range', ['range' => $model->get_range()]), 'holidays' => \common\helpers\Date::get_holidays($ph, 'm/d/Y H:i:s', $model->get_data_year($data)), 'rows' => $model->get_rows_count(), 'table_title' => $model->get_table_title(), 'filters' => $report->get_filters(), 'selected_filter' => \yii\helpers\Url::to(['sales_statistics/index']) . '?' . $_SERVER['QUERY_STRING'], 'selected_statuses' => $report->get_selected_statuses(), 'selected_payments' => $report->get_selected_payments(), 'selected_shippings' => $report->get_selected_shippings(), 'selected_platforms' => $report->get_selected_platforms(), 'selected_customer_groups' => $report->get_selected_customer_groups(), 'selected_currency' => $report->get_selected_currency(), 'undisabled' => $report->get_undisabled_charts(), 'class_range' => array_keys($model->get_class_range()), 'geo_details' => $this->get_geo_details($report), 'with_products' => $report->get_with_products(), 'walkin' => $report->get_walk_in_admins(), 'currencies' => Yii::$container->get('currencies')];
        //echo '<pre>';print_r($params);die;
        if (Yii::$app->request->is_ajax) {
            echo json_encode($params);
            exit;
        } else {
            return $this->render('index', $params);
        }
    }
    public function action_load_range()
    {
        $type = Yii::$app->request->get('type');
        $range = '';
        $undisabled = [];
        if ($type) {
            $report = new Report(['type' => $type]);
            $range = $report->get_report_model()->get_range_list();
            //$undisabled = $report->getUndisabledCharts();
        }
        echo json_encode(['range' => $range, 'undisabled' => $undisabled]);
    }
    public function action_load_options()
    {
        $type = Yii::$app->request->get('type');
        $range = Yii::$app->request->get('range');
        $options = '';
        $undisabled = [];
        if ($type) {
            $report = new Report(Yii::$app->request->get());
            $options = $report->get_report_model()->get_options($range);
            if ($range == 'custom') {
                $undisabled = $report->get_undisabled_charts();
            }
        }
        echo json_encode(['options' => $options, 'undisabled' => $undisabled]);
    }
    public function get_geo_details($report, $is_ajax = false)
    {
        return $this->render_ajax('geo_details', ['selected_geo_type' => $report->get_selected_geo_type(), 'geo_type' => $report->get_geo_type(), 'selected_zones' => $report->get_selected_zones(), 'zones' => $report->get_geo_zones(), 'ajax' => $is_ajax, 'country' => $report->get_selected_country(), 'state' => $report->get_selected_state(), 'sps' => $report->get_selected_sps()]);
    }
    public function action_get_geo()
    {
        $geo_type = Yii::$app->request->get('geo_type', 0);
        $s_action = Yii::$app->request->get('action', '');
        switch ($s_action) {
            case 'country':
                $term = Yii::$app->request->get('term', '');
                $delivery_countries = \common\helpers\Order::get_orders_query(['delivery_country' => $term])->group_by('delivery_country')->order_by('delivery_country')->all();
                $response = \yii\helpers\Array_Helper::get_column($delivery_countries, 'delivery_country');
                break;
            case 'state':
                $term = Yii::$app->request->get('term', '');
                $country = Yii::$app->request->get('country');
                if (!empty($country)) {
                    $country = \common\models\Countries::find()->select('countries_name')->where(['countries_id' => explode(',', $country)])->column();
                }
                $delivery_states = \common\helpers\Order::get_orders_query(['delivery_state' => $term, 'delivery_country' => $country])->group_by('delivery_state')->order_by('delivery_state')->all();
                $response = \yii\helpers\Array_Helper::get_column($delivery_states, 'delivery_state');
                break;
            default:
                $report = new Report($_GET);
                $response = ['selectors' => $this->get_geo_details($report, Yii::$app->request->is_ajax)];
                break;
        }
        echo json_encode($response);
        exit;
    }
    public function action_save_filter()
    {
        $params = Yii::$app->request->get_body_params();
        $message = '';
        //$params['options'] = urldecode($params['options']);
        if (is_array($params)) {
            if (isset($params['filter_name']) && !empty($params['filter_name']) && isset($params['options']) && !empty($params['options'])) {
                tep_db_query('insert into ' . TABLE_SALES_FILTERS . " set sales_filter_name = '" . tep_db_input($params['filter_name']) . "', sales_filter_vals = '" . tep_db_input($params['options']) . "'");
                $message = TEXT_MESSEAGE_SUCCESS;
            } else {
                $message = TEXT_MESSAGE_ERROR;
            }
        } else {
            $message = TEXT_MESSAGE_ERROR;
        }
        echo json_encode(['message' => $message]);
        exit;
    }
    public function action_delete_filter()
    {
        $params = Yii::$app->request->get_body_params();
        $message = '';
        if (is_array($params)) {
            $params['filter_vals'] = str_replace(\yii\helpers\Url::to(['sales_statistics/index']) . '?', '', $params['filter_vals']);
            if (isset($params['filter_vals']) && !empty($params['filter_vals'])) {
                tep_db_query('delete from ' . TABLE_SALES_FILTERS . " where sales_filter_vals = '" . tep_db_input($params['filter_vals']) . "'");
                $message = TEXT_MESSEAGE_SUCCESS;
            } else {
                $message = TEXT_MESSAGE_ERROR;
            }
        } else {
            $message = TEXT_MESSAGE_ERROR;
        }
        echo json_encode(['message' => $message]);
        exit;
    }
    public function action_map_show()
    {
        $orig_place = [0, 0, 2];
        $country_info = tep_db_fetch_array(tep_db_query('select ab.entry_country_id from ' . TABLE_PLATFORMS_ADDRESS_BOOK . ' ab inner join ' . TABLE_PLATFORMS . ' p on p.is_default = 1 and p.platform_id = ab.platform_id where ab.is_default = 1'));
        $_country = (int) STORE_COUNTRY;
        if ($country_info) {
            $_country = $country_info['entry_country_id'];
        }
        if (defined('STORE_COUNTRY') && (int) STORE_COUNTRY > 0) {
            $orig_place = tep_db_fetch_array(tep_db_query('select lat, lng, zoom from ' . TABLE_COUNTRIES . " where countries_id = '" . (int) $_country . "'"));
        }
        return $this->render_ajax('map', ['mapskey' => \common\components\Google_Tools::instance()->get_map_provider()->get_maps_key(), 'origPlace' => $orig_place]);
    }
    public function action_map()
    {
        $report = new Report($_GET);
        $model = $report->get_report_model();
        $data = $model->load_purchases(true);
        echo json_encode(['data' => $data]);
        exit;
    }
    public function action_export()
    {
        $report = new Report($_GET);
        $model = $report->get_report_model();
        $data = $model->load_purchases(false);
        $start = Yii::$app->request->get('start', null);
        $end = Yii::$app->request->get('end', null);
        $ex_type = Yii::$app->request->get('ex_type');
        $ex_data = explode('|', Yii::$app->request->get('ex_data'));
        $report->export($data, ['modules' => $ex_data, 'type' => $ex_type, 'start' => $start, 'end' => $end]);
        exit;
    }
}