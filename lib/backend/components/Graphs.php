<?php

declare(strict_types=1);
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

namespace backend\components;

use Yii;

class Graphs
{
    public static $headers_array = [];
    public static $headers_array_colors = [];
    public static $classes_array = [];
    public static $tmp = []; // array for bad days (with 0 total(s))
    public static $sel_total = [];
    public static $show_total_order;
    public static $sel_status_sql;

    /**
     * @return array{id: mixed, text: non-falsy-string}[]
     */
    public static function getCurrency(): array
    {

        $currencies = \Yii::$container->get('currencies');
        $currencies_array = [];
        foreach ($currencies->currencies as $code => $currency) {
            $currencies_array[] = ['id' => $code,
                                        'text' => $currency['title'] . ' [' . $code . ']'];
        }

        return $currencies_array;
    }

    public static function prepareQueryString(): ?string
    {
        $patterns =  ['/filter_name=[^&]*&/',
                           '/([?&])x=[^&]*&/',
                           '/([?&])y=[^&]*&/',
                           '/apply_filter=[^&]*&/',
                           '/remove_filter=[^&]*&/',
                           '/&$/',
                           '/month=[^&]*&/',
                           '/year=[^&]*&/',
                           '/&+/',
                           '/^&/',
                           '/' . tep_session_name() . '=[^&]*&/' ];
        $replace =  ['', '\\1', '\\1', '', '', '', '', '', '&', '', ''];

        return preg_replace($patterns, $replace, $_SERVER['QUERY_STRING'] . '&');
    }

    public static function insertFilter($str): void
    {
        if (strlen((string) $_GET['filter_name']) > 0) {
            //save customer's filter
            if (!tep_db_num_rows(tep_db_query('select sales_filter_name from ' . TABLE_SALES_FILTERS . " where sales_filter_name='" . tep_db_input(tep_db_prepare_input($_GET['filter_name'])) . "'"))) {
                tep_db_query('insert into ' . TABLE_SALES_FILTERS . " set sales_filter_vals='" . tep_db_input(tep_db_prepare_input($str)) . "', sales_filter_name='" . tep_db_input(tep_db_prepare_input($_GET['filter_name'])) . "'");
            }
            $_GET['filter_name'] = '';
        }
    }

    public static function deleteFilter($str): bool
    {
        if ($_GET['remove_filter'] == 1) {
            tep_db_query('delete from ' . TABLE_SALES_FILTERS . " where sales_filter_vals='" . tep_db_prepare_input($str) . "' limit 1 ");
            unset($_GET['remove_filter']);
            return true;
        }
        return false;
    }

    public static function prepareFilter($only_delete = false)
    {

        $str = self::prepareQueryString();

        if (self::deleteFilter($str)) {
            return true;
        }

        if (!$only_delete) {

            self::insertFilter($str);

            $r = tep_db_query('select * from ' . TABLE_SALES_FILTERS . " where sales_filter_vals='" . tep_db_prepare_input($str) . "' limit 0,1 ");
            if ($d = tep_db_fetch_array($r)) {
                $params = [];
                $str = '';
                $query = explode('&amp;', htmlspecialchars((string) $d['sales_filter_vals']));
                if (is_array($query)) {
                    foreach ($query as $str) {
                        $ex = explode('=', $str);
                        $params[$ex[0]] = $ex[1];
                    }
                }
                array_unshift($params, 'sales_statistics/index');
                return Yii::$app->urlManager->createUrl($params);
            }
        }

        return false;
    }

    /**
     * @return array{id: mixed, text: mixed}[]
     */
    public static function getYears(): array
    {
        $years_array = [];
        $years_query = tep_db_query('select distinct year(date_purchased) as order_year from ' . TABLE_ORDERS . ' order by order_year desc');
        while ($years = tep_db_fetch_array($years_query)) {
            $years_array[] = ['id' => $years['order_year'],
                                   'text' => $years['order_year']];
        }
        return $years_array;
    }

    /**
     * @return mixed[]
     */
    public static function getMonths(): array
    {
        $months_array = [];
        for ($i = 1; $i < 13; $i++) {
            $months_array[] = ['id' => $i,
                                    'text' => (defined('_' . strtoupper(date('F', mktime(0, 0, 0, $i, 1)))) ? constant('_' . strtoupper(date('F', mktime(0, 0, 0, $i, 1)))) : date('F', mktime(0, 0, 0, $i, 1))) ] ;
        }
        return $months_array;
    }

    /**
     * @return string[]
     */
    public static function getColors($colors_graph): array
    {
        $colors_html = [];
        for ($i = 0; $i < count($colors_graph);$i++) {
            $colors_html[] = sprintf('#%02X%02X%02X', $colors_graph[$i][0], $colors_graph[$i][1], $colors_graph[$i][2]);
        }
        return $colors_html;
    }

    /**
     * @return mixed[]
     */
    public static function getHeadColors($colors_graph): array
    {
        $list = [];
        if (self::$show_total_order) {
            $list[] = $colors_graph[0];
        }
        foreach (self::$headers_array_colors as $color) {
            $list[] = $color;
        }
        return $list;
    }

    public static function getTypes(): array
    {
        return [['id' => 'daily',
                                'text' => STATISTICS_TYPE_DAILY],
                          ['id' => 'monthly',
                                'text' => STATISTICS_TYPE_MONTHLY],
                          ['id' => 'yearly',
                                'text' => STATISTICS_TYPE_YEARLY]];
    }

    /**
     * @return mixed[]
     */
    public static function getFilters(): array
    {
        $filters_query = tep_db_query('select sales_filter_vals, sales_filter_name from ' . TABLE_SALES_FILTERS . ' order by sales_filter_name');
        $filters = [];
        $filters[] = ['id' => '', 'text' => TEXT_SELECT];
        while ($d = tep_db_fetch_array($filters_query)) {
            $params = [];
            $query = explode('&amp;', htmlspecialchars((string) $d['sales_filter_vals']));
            if (is_array($query)) {
                foreach ($query as $str) {
                    $ex = explode('=', $str);
                    $params[$ex[0]] = $ex[1];
                }
            }
            array_unshift($params, 'sales_statistics/index') ;
            $filters[] = ['id' => Yii::$app->urlManager->createUrl($params),  'text' => $d['sales_filter_name']];
        }
        return $filters;
    }

    public static function calculate_yearly_report(array &$stats)
    {
        $stats = [];

        $orders_query = tep_db_query('select year(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o inner join ' . TABLE_ORDERS_TOTAL . ' ot on (o.orders_id = ot.orders_id) where '.(USE_MARKET_PRICES == 'True' ? "o.currency = '" . tep_db_input($_GET['currency'] ?: DEFAULT_CURRENCY) . "'" : '1').' '. self::$sel_status_sql . ' group by year(o.date_purchased), ot.class order by report_day, ot.sort_order ');

        $prev_day = -1;
        $report_data = array_merge((self::$show_total_order ? [date('Y'), 0] : [date('Y')]), self::$tmp);
        $i = 0;
        $max_total = 0;
        while ($order_stats = tep_db_fetch_array($orders_query)) {
            if (($prev_day != $order_stats['report_day']) && ($prev_day != -1)) {
                //next year, save prev year data into $stats
                $stats[] = $report_data;
                //$report_data = $stats[$i];
                $report_data = array_merge((self::$show_total_order ? [date('Y'), 0] : [date('Y')]), self::$tmp);
                $i++;
                $max_total = 0;
            }
            $report_data[0] = $order_stats['report_day'];

            if (self::$show_total_order && ($max_total < $order_stats['report_total'])) {
                $report_data[1] = $order_stats['report_total'];
                $max_total = $order_stats['report_total'];
            }
            if ((count(self::$sel_total) > 0) && in_array($order_stats['class'], self::$sel_total)) {
                $report_data[self::$headers_array[$order_stats['class']]] = $order_stats['report_total_sum'];
            }
            $prev_day = $order_stats['report_day'];
        }
        if ($prev_day != -1) {
            if ($i == 0) {
                $stats[] = $report_data;
                for ($k = 0; $k < count($report_data); $k++) {
                    if ($k == 0) {
                        $stats[0][0] = $report_data[0] - 1;
                    } else {
                        $stats[0][$k] = 0;
                    }
                }
            }
            $stats[] = $report_data;
        }
        return self::convertToChart($stats);
    }

    public static function calculate_monthly_report(array &$stats)
    {

        $year = ($_GET['year'] ?: date('Y'));
        $stats = [];
        if ($year == date('Y')) {
            $t = date('m') + 1;
        } else {
            $t = 13;
        }
        for ($i = 1; $i < $t; $i++) {
            $stats[] = array_merge((self::$show_total_order ? [$i, 0] : [$i]), self::$tmp);
        }

        if (USE_MARKET_PRICES != 'True') {
            $orders_query = tep_db_query('select month(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where year(o.date_purchased) = '" . $year . "' " . self::$sel_status_sql . ' group by month(o.date_purchased), ot.class order by report_day, ot.sort_order');
        } else {
            if ($_GET['currency'] == '') {
                $orders_query = tep_db_query('select month(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value * if(o.currency_value_default > 0, o.currency_value_default, o.currency_value)) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where year(o.date_purchased) = '" . $year . "' " . self::$sel_status_sql . ' group by month(o.date_purchased), ot.class order by report_day, ot.sort_order');
            } else {
                $orders_query = tep_db_query('select month(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where o.currency = '" . tep_db_input(\common\helpers\Currencies::currency_exists($_GET['currency']) ? $_GET['currency'] : DEFAULT_CURRENCY) . "' and year(o.date_purchased) = '" . $year . "' " . self::$sel_status_sql . ' group by month(o.date_purchased), ot.class order by report_day, ot.sort_order');
            }
        }
        $prev_day = -1;
        $report_data = array_merge((self::$show_total_order ? [0, 0] : [0]), self::$tmp);
        $max_total = 0;
        while ($order_stats = tep_db_fetch_array($orders_query)) {
            if (($prev_day != $order_stats['report_day']) && ($prev_day != -1)) {
                $stats[($prev_day - 1)] = $report_data;
                $report_data = array_merge((self::$show_total_order ? [0, 0] : [0]), self::$tmp);
                $max_total = 0;
            }
            $report_data[0] = $order_stats['report_day'];
            if (self::$show_total_order && ($max_total < $order_stats['report_total']) && $order_stats['class'] == 'ot_total') {
                $report_data[1] = $order_stats['report_total'];
                $max_total = $order_stats['report_total'];
            }
            if ((count(self::$sel_total) > 0) && in_array($order_stats['class'], self::$sel_total)) {
                $report_data[self::$headers_array[$order_stats['class']]] = $order_stats['report_total_sum'];
            }
            $prev_day = $order_stats['report_day'];
        }

        if ($prev_day != -1) {
            if ($i == 0) {
                for ($k = 0; $k < count($report_data); $k++) {
                    if ($k == 0) {
                        $stats[($prev_day - 2)][0] = $report_data[0] - 1;
                    } else {
                        $stats[($prev_day - 2)][$k] = 0;
                    }
                }
                $stats[($prev_day - 1)] = $report_data;
            } else {
                $stats[($prev_day - 1)] = $report_data;
            }
        }
        unset($stats[-1]);

        return self::convertToChart($stats);
    }

    public static function calculate_daily_report(array &$stats)
    {
        $year = ($_GET['year'] ?: date('Y'));
        $month = ($_GET['month'] ?: date('n'));

        $stats = [];
        if (($year == date('Y')) && ($month == date('n'))) {
            $days = (date('d'));
        } else {
            $days = (date('t', mktime(0, 0, 0, $month, 1)));//+1
        }
        for ($i = 1; $i <= $days; $i++) {
            $stats[] = array_merge((self::$show_total_order ? [$i, 0] : [$i]), self::$tmp);
        }

        if (USE_MARKET_PRICES != 'True') {
            $sql = 'select dayofmonth(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o inner join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where month(o.date_purchased) = '" . $month . "' and year(o.date_purchased) = '" . $year . "' " . self::$sel_status_sql . ' group by dayofmonth(o.date_purchased), ot.class order by report_day, ot.sort_order ';
        } else {
            if ($_GET['currency'] == '') {
                $sql = 'select dayofmonth(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value * if(o.currency_value_default > 0, o.currency_value_default, o.currency_value)) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o inner join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where month(o.date_purchased) = '" . $month . "' and year(o.date_purchased) = '" . $year . "' " . self::$sel_status_sql . ' group by dayofmonth(o.date_purchased), ot.class order by report_day, ot.sort_order ';
            } else {
                $sql = 'select dayofmonth(o.date_purchased) as report_day, count(*) as report_total, sum(ot.value) as report_total_sum, ot.class from ' . TABLE_ORDERS . ' o inner join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where o.currency = '" . tep_db_input(\common\helpers\Currencies::currency_exists($_GET['currency']) ? $_GET['currency'] : DEFAULT_CURRENCY) . "' and month(o.date_purchased) = '" . $month . "' and year(o.date_purchased) = '" . $year . "' " . self::$sel_status_sql . ' group by dayofmonth(o.date_purchased), ot.class order by report_day, ot.sort_order ';
            }
        }

        $orders_query = tep_db_query($sql);
        $prev_day = -1;
        $report_data = array_merge((self::$show_total_order ? [0, 0] : [0]), self::$tmp);
        $max_total = 0;
        while ($order_stats = tep_db_fetch_array($orders_query)) {
            if (($prev_day != $order_stats['report_day']) && ($prev_day != -1)) {
                $stats[($prev_day - 1)] = $report_data;
                $report_data = array_merge((self::$show_total_order ? [0, 0] : [0]), self::$tmp);
                $max_total = 0;
            }
            $report_data[0] = $order_stats['report_day']; // day of month
            if (self::$show_total_order && ($max_total < $order_stats['report_total']) && $order_stats['class'] == 'ot_total') { //total orders per day
                $report_data[1] = $order_stats['report_total'];
                $max_total = $order_stats['report_total'];
            }
            if ((count(self::$sel_total) > 0) && (in_array($order_stats['class'], self::$sel_total))) {
                $report_data[self::$headers_array[$order_stats['class']]] = $order_stats['report_total_sum'];
            }
            $prev_day = $order_stats['report_day'];
        }

        if ($prev_day != -1) {
            if ($i == 0) {
                for ($k = 0; $k < count($report_data); $k++) {
                    if ($k == 0) {
                        $stats[($prev_day - 2)][0] = $report_data[0] - 1;
                    } else {
                        $stats[($prev_day - 2)][$k] = 0;
                    }
                }
                $stats[($prev_day - 1)] = $report_data;
            } else {
                $stats[($prev_day - 1)] = $report_data;
            }
        }

        unset($stats[-1]);
        return self::convertToChart($stats);
    }

    /**
     * @return mixed[][]
     */
    public static function convertToChart($stats): array
    {
        $new_stats = [];
        foreach ($stats as $calendarItem) {
            foreach ($calendarItem as $key => $progressLine) {
                if (!is_array($new_stats[$key])) {
                    $new_stats[$key] = [];
                }
                array_push($new_stats[$key], round($progressLine, 2));
            }
        }
        return $new_stats;
    }
}
