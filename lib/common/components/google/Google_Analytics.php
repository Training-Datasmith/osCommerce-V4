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
namespace common\components\google;

/**
 * @note Google namespaces changed since library update
 * @see lib/vendor/google/apiclient/src/aliases.php
 */
use Google_Client;
/*Google\*/
/*Google\*/
/*Google\*/
use Google_Service_Analytics;
use Google_service_analytics_Reporting;
use Google_service_analytics_Reporting_date_Range;
use Google_service_analytics_Reporting_dimension;
use Google_service_analytics_Reporting_dimension_Filter_Clause;
use Google_service_analytics_Reporting_get_Reports_Request;
/*Google\*/
use Google_service_analytics_Reporting_metric;
/*Google\*/
use Google_service_analytics_Reporting_report_Request;
class Google_Analytics
{
    private $client;
    private $reporting;
    private $config = [];
    private static $scopes = ['https://www.googleapis.com/auth/analytics.readonly'];
    public function __construct($config_file, $view_id)
    {
        if (empty($config_file)) {
            // avoid fatal on file_get_contents
            throw new \Exception('Configuration file is not set');
        }
        $content = @file_get_contents($config_file);
        if (!$content) {
            throw new \Exception('Configuration file ' . basename($config_file) . ' could not be loaded: ' . error_get_last()['message']);
        }
        try {
            $this->config['privacy'] = json_decode($content, true);
        } catch (\Exception $ex) {
            throw new \Exception('Configuration file ' . basename($config_file) . ' could not be loaded');
        }
        $this->config['view_id'] = $view_id;
        if (empty($this->config['view_id'])) {
            throw new \Exception('Analytics View ID is not defined');
        }
    }
    public function prepare_reporting()
    {
        try {
            $this->client = new Google_Client();
            $this->client->set_application_name('Analytics Reporting');
            $this->client->set_auth_config($this->config['privacy']);
            $this->client->set_scopes(self::$scopes);
            $this->reporting = new Google_service_analytics_Reporting($this->client);
            return $this->reporting;
        } catch (\Exception $ex) {
            echo $ex->get_message();
        }
    }
    protected function get_view_id()
    {
        return $this->config['view_id'];
    }
    public function get_report($filters = [])
    {
        // Create the DateRange object.
        $date_range = new Google_service_analytics_Reporting_date_Range();
        if (isset($filters['date_range'][0])) {
            $date_range->set_start_date($filters['date_range'][0]);
        } else {
            $date_range->set_start_date(date('Y-m-d', strtotime('-1 year')));
        }
        if (isset($filters['date_range'][1])) {
            $date_range->set_end_date($filters['date_range'][1]);
        } else {
            $date_range->set_end_date('today');
        }
        if (isset($filters['dimensions'])) {
            if (is_array($filters['dimensions']) && count($filters['dimensions'])) {
                $dimentions = [];
                foreach ($filters['dimensions'] as $dim) {
                    $d = new Google_service_analytics_Reporting_dimension();
                    $d->set_name($dim);
                    $dimentions[] = $d;
                }
            }
        }
        if (isset($filters['metrics'])) {
            if (is_array($filters['metrics']) && count($filters['metrics'])) {
                $metrics = [];
                foreach ($filters['metrics'] as $met) {
                    $m = new Google_service_analytics_Reporting_metric();
                    $m->set_expression($met);
                    $metrics[] = $m;
                }
            }
        }
        $request = new Google_service_analytics_Reporting_report_Request();
        $request->set_view_id($this->get_view_id());
        $request->set_date_ranges($date_range);
        if (is_array($metrics ?? null)) {
            $request->set_metrics($metrics);
        }
        if (is_array($dimentions)) {
            $request->set_dimensions($dimentions);
        }
        if (isset($filters['dimensionsFilter'])) {
            if (is_array($filters['dimensionsFilter']) && count($filters['dimensionsFilter'])) {
                $dimentions_filters = [];
                foreach ($filters['dimensionsFilter'] as $d_filter) {
                    $filter = new \Google\Google_service_analytics_Reporting_dimension_Filter();
                    $filter->set_dimension_name($d_filter['dimension']);
                    $filter->set_expressions($d_filter['expression']);
                    $filter->set_operator($d_filter['operator']);
                    $dimentions_filters[] = $filter;
                }
                if ($dimentions_filters) {
                    $filter_clause = new Google_service_analytics_Reporting_dimension_Filter_Clause();
                    $filter_clause->set_filters($dimentions_filters);
                    $request->set_dimension_filter_clauses($filter_clause);
                }
            }
        }
        $body = new Google_service_analytics_Reporting_get_Reports_Request();
        $body->set_report_requests([$request]);
        return $this->reporting->reports->batch_get($body);
    }
    public function get_ua_code()
    {
        $detected_ua_code = false;
        $analytics = new Google_Service_Analytics($this->client);
        if ($analytics) {
            try {
                $accounts = $analytics->management_account_summaries->list_management_account_summaries();
                $models = $accounts->get_model_data();
                if (isset($models['items']) && is_array($models['items'])) {
                    // has included accounts
                    foreach ($models['items'] as $acc) {
                        $web_properties = $acc['webProperties'];
                        if ($web_properties) {
                            foreach ($web_properties as $web_property) {
                                if ($web_property['profiles']) {
                                    $views_id = \yii\helpers\Array_Helper::get_column($web_property['profiles'], 'id');
                                    if (is_array($views_id) && in_array($this->get_view_id(), $views_id)) {
                                        $detected_ua_code = $web_property['id'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {
                \Yii::info($ex->get_message(), 'Google analytics Exception');
                //var_dump($ex->getMessage());
            }
        }
        return $detected_ua_code;
    }
}