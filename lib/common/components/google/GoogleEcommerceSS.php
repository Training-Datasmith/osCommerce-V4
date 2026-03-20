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

use common\classes\Order;
/*
 * Google Analytics Ecoomerce tracker Server Side
 */
class Google_Ecommerce_Ss
{
    public $platform_id;
    public $order_id;
    public $ga;
    public $platform;
    protected $ua_code = false;
    private $google_collect_url = 'https://www.google-analytics.com/collect';
    private $dimensions = ['Transaction ID' => 'ga:transactionId', 'Affiliation' => 'ga:affiliation', 'Sessions to Transaction' => 'ga:sessionsToTransaction', 'Days to Transaction' => 'ga:daysToTransaction', 'Product SKU' => 'ga:productSku', 'Product' => 'ga:productName', 'Product Category' => 'ga:productCategory', 'Currency Code' => 'ga:currencyCode', 'Checkout Options' => 'ga:checkoutOptions', 'Internal Promotion Creative' => 'ga:internalPromotionCreative', 'Internal Promotion ID' => 'ga:internalPromotionId', 'Internal Promotion Name' => 'ga:internalPromotionName', 'Internal Promotion Position' => 'ga:internalPromotionPosition', 'Order Coupon Code' => 'ga:orderCouponCode', 'Product Brand' => 'ga:productBrand', 'Product Category (Enhanced Ecommerce)' => 'ga:productCategoryHierarchy', 'Product Category Level XX' => 'ga:productCategoryLevelXX', 'Product Coupon Code' => 'ga:productCouponCode', 'Product List Name' => 'ga:productListName', 'Product List Position' => 'ga:productListPosition', 'Product Variant' => 'ga:productVariant', 'Shopping Stage' => 'ga:shoppingStage'];
    private $metrics = ['Transactions' => 'ga:transactions', 'Ecommerce Conversion Rate' => 'ga:transactionsPerSession', 'Revenue' => 'ga:transactionRevenue', 'Avg. Order Value' => 'ga:revenuePerTransaction', 'Per Session Value' => 'ga:transactionRevenuePerSession', 'Shipping' => 'ga:transactionShipping', 'Tax' => 'ga:transactionTax', 'Total Value' => 'ga:totalValue', 'Quantity' => 'ga:itemQuantity', 'Unique Purchases' => 'ga:uniquePurchases', 'Avg. Price' => 'ga:revenuePerItem', 'Product Revenue' => 'ga:itemRevenue', 'Avg. QTY' => 'ga:itemsPerPurchase', 'Local Revenue' => 'ga:localTransactionRevenue', 'Local Shipping' => 'ga:localTransactionShipping', 'Local Tax' => 'ga:localTransactionTax', 'Local Product Revenue' => 'ga:localItemRevenue', 'Buy-to-Detail Rate' => 'ga:buyToDetailRate', 'Cart-to-Detail Rate' => 'ga:cartToDetailRate', 'Internal Promotion CTR' => 'ga:internalPromotionCTR', 'Internal Promotion Clicks' => 'ga:internalPromotionClicks', 'Internal Promotion Views' => 'ga:internalPromotionViews', 'Local Product Refund Amount' => 'ga:localProductRefundAmount', 'Local Refund Amount' => 'ga:localRefundAmount', 'Product Adds To Cart' => 'ga:productAddsToCart', 'Product Checkouts' => 'ga:productCheckouts', 'Product Detail Views' => 'ga:productDetailViews', 'Product List CTR' => 'ga:productListCTR', 'Product List Clicks' => 'ga:productListClicks', 'Product List Views' => 'ga:productListViews', 'Product Refund Amount' => 'ga:productRefundAmount', 'Product Refunds' => 'ga:productRefunds', 'Product Removes From Cart' => 'ga:productRemovesFromCart', 'Product Revenue per Purchase' => 'ga:productRevenuePerPurchase', 'Quantity Added To Cart' => 'ga:quantityAddedToCart', 'Quantity Checked Out' => 'ga:quantityCheckedOut', 'Quantity Refunded' => 'ga:quantityRefunded', 'Quantity Removed From Cart' => 'ga:quantityRemovedFromCart', 'Refund Amount' => 'ga:refundAmount', 'Revenue per User' => 'ga:revenuePerUser', 'Refunds' => 'ga:totalRefunds', 'Transactions per User' => 'ga:transactionsPerUser'];
    public function __construct($order = '', $platform_id = null, $ua_code = '')
    {
        $this->ga = null;
        $this->ua_code = $ua_code;
        if (is_object($order) && method_exists($order, 'getOrderId')) {
            $this->order_id = $order->get_order_id();
            $this->platform_id = $order->info['platform_id'];
        } else {
            $this->order_id = $order;
            $this->platform_id = $platform_id;
        }
        if (!$this->platform_id) {
            $this->platform_id = \common\classes\platform::default_id();
        }
        if (!$this->platform_id) {
            throw new \Exception('Invalid Platform Id');
        }
        $this->platform = (new \common\classes\platform())->config($this->platform_id);
    }
    public function set_platform_id($platform_id)
    {
        $this->platform_id = $platform_id;
        $this->platform = (new \common\classes\platform())->config($this->platform_id);
        $this->ga = null;
        $this->ua_code = false;
    }
    public function init_ga()
    {
        $analytics = (new \common\components\Google_Tools())->get_analytics_provider();
        try {
            $conf_file = $analytics->get_file_key($this->platform_id);
            if (!empty($conf_file)) {
                $this->ga = new Google_Analytics($conf_file, $analytics->get_view_id($this->platform_id));
                if ($this->ga) {
                    $this->ga->prepare_reporting();
                    $this->ua_code = $this->ga->get_ua_code();
                }
            }
        } catch (\Exception $ex) {
            $noty = new \backend\models\Admin_Notifier();
            $noty->add_notification(null, $ex->get_message(), 'warning');
            \Yii::info($ex->get_message(), 'Google analytics Exception');
        }
    }
    public function get_filters()
    {
        return ['date_range' => ['5daysAgo', 'today'], 'dimensions' => ['ga:transactionId'], 'dimensionsFilter' => [['dimension' => 'ga:transactionId', 'expression' => (string) $this->order_id, 'operator' => 'EXACT']], 'metrics' => ['ga:transactions']];
    }
    /**
     *
     * @param array $range of dates
     * @return array|false - false if no response received
     */
    public function get_transactions_report($range = [])
    {
        if (is_null($this->ga)) {
            $this->init_ga();
        }
        $ret = false;
        if ($this->ga) {
            $start = date('Y-m-d', strtotime('-1 month'));
            $end = date('Y-m-d');
            if (is_array($range)) {
                if (!empty($range[0])) {
                    $start = $range[0];
                }
                if (!empty($range[1])) {
                    $end = $range[1];
                }
            }
            try {
                $response = $this->ga->get_report(['date_range' => [$start, $end], 'dimensions' => ['ga:transactionId'], 'metrics' => ['ga:transactionRevenue']]);
                if (is_object($response) && property_exists($response, 'reports')) {
                    $ret = [];
                    $report = $response->reports[0];
                    //$this->printResults($response->reports);
                    if ($report instanceof \Google\Google_service_analytics_Reporting_report) {
                        $header = $report->get_column_header();
                        $dimension_headers = $header->get_dimensions();
                        $metric_headers = $header->get_metric_header()->get_metric_header_entries();
                        $rows = $report->get_data()->get_rows();
                        if ($rows) {
                            foreach ($rows as $row) {
                                $dimensions = $row->get_dimensions();
                                $metrics = $row->get_metrics();
                                $oid = 0;
                                $tmp = [];
                                for ($i = 0; $i < count($dimension_headers) && $i < count($dimensions); $i++) {
                                    $tmp[$dimension_headers[$i]] = $dimensions[$i];
                                    if ($dimension_headers[$i] == 'ga:transactionId') {
                                        $oid = $dimensions[$i];
                                    }
                                }
                                for ($j = 0; $j < count($metrics); $j++) {
                                    $values = $metrics[$j]->get_values();
                                    for ($k = 0; $k < count($values); $k++) {
                                        $entry = $metric_headers[$k];
                                        $tmp[$entry->get_name()] = $values[$k];
                                    }
                                }
                                $ret[$oid] = $tmp;
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {
                \Yii::info($ex->get_message(), 'Google analytics Exception');
                //var_dump($ex->getMessage());
            }
        }
        return $ret;
    }
    public function print_results($reports)
    {
        for ($report_index = 0; $report_index < count($reports); $report_index++) {
            $report = $reports[$report_index];
            $header = $report->get_column_header();
            $dimension_headers = $header->get_dimensions();
            $metric_headers = $header->get_metric_header()->get_metric_header_entries();
            $rows = $report->get_data()->get_rows();
            for ($row_index = 0; $row_index < count($rows); $row_index++) {
                $row = $rows[$row_index];
                $dimensions = $row->get_dimensions();
                $metrics = $row->get_metrics();
                echo '<br>Dimensions ';
                for ($i = 0; $i < count($dimension_headers) && $i < count($dimensions); $i++) {
                    print $dimension_headers[$i] . ': ' . $dimensions[$i] . "\n";
                }
                echo '<br>Metrics ';
                for ($j = 0; $j < count($metrics); $j++) {
                    $values = $metrics[$j]->get_values();
                    for ($k = 0; $k < count($values); $k++) {
                        $entry = $metric_headers[$k];
                        print $entry->get_name() . ': ' . $values[$k] . "\n";
                    }
                }
            }
        }
    }
    /**
     * @depricated use ecommerce widget save only for EP module
     * checks order in analytics report in last 5 days :( (full shit for old orders, useless if order was placed few minutes ago....
     * @return boolean
     */
    public function is_order_placed_to_analytics()
    {
        if (is_null($this->ga)) {
            $this->init_ga();
        }
        if ($this->ga) {
            try {
                $response = $this->ga->get_report($this->get_filters());
                if (is_object($response) && property_exists($response, 'reports')) {
                    $report = $response->reports[0];
                    if ($report instanceof \Google\Google_service_analytics_Reporting_report) {
                        $rows = $report->get_data()->get_rows();
                        if ($rows) {
                            $row = $rows[0];
                            $placed = $row->get_dimensions();
                            return in_array($this->order_id, $placed);
                        }
                    }
                }
            } catch (\Exception $ex) {
                \Yii::info($ex->get_message(), 'Google analytics Exception');
                //var_dump($ex->getMessage());
            }
        }
        return false;
    }
    /**
     *
     * @param Order $order
     * @param type $revert https://support.google.com/analytics/answer/1037443?hl=en
     * @return boolean
     * @throws \Exception
     */
    public function push_data_to_analytics(Order $order = null, $revert = false, $date_shift = false)
    {
        if (strlen($this->ua_code) > 3) {
            $g_commerce = new \common\components\google\widgets\Google_Commerce();
            if (!$order) {
                $g_commerce->order = new Order($this->order_id);
            } else {
                $g_commerce->order = $order;
            }
            if (!is_object($g_commerce->order)) {
                throw new \Exception('Order is not loaded');
            }
            if ($revert) {
                $rr = -1;
            } else {
                $rr = 1;
            }
            $g_commerce->prepare_data();
            $ua_code = $this->ua_code;
            if (isset($g_commerce->used_module) && $ua_code !== false) {
                $language_code = \common\helpers\Language::get_language_code($g_commerce->order->info['language_id']);
                if ($language_code) {
                    $language_code = $language_code['code'];
                } else {
                    $language_code = 'en';
                }
                $data = [
                    'v' => 1,
                    //protocol
                    'ds' => 'web',
                    'uid' => $g_commerce->order->customer['id'],
                    'ua' => '',
                    //user agent, can be ua from payment bot
                    'geoid' => $g_commerce->order->billing['country']['iso_code_2'],
                    //get country ISO-2
                    'de' => 'UTF-8',
                    'ul' => $language_code . '-' . $language_code,
                    't' => 'pageview',
                    'dl' => $this->platform->get_catalog_base_url(true),
                    'ti' => $this->order_id,
                    'pa' => 'purchase',
                    'tid' => $ua_code,
                    'cu' => $g_commerce->order->info['currency'],
                    'dp' => '/checkout/success?order_id=' . $this->order_id,
                ];
                if ($date_shift) {
                    $qt = time() - strtotime($order->info['date_purchased']);
                    if ($qt < 60 * 60 * 24 * 120) {
                        // 4 month then int32 unsigned
                        $data['qt'] = $qt * 1000;
                    }
                }
                if (($ga = \common\helpers\System::get_ga_detection($this->order_id)) !== false) {
                    $data['cn'] = $ga['utmccn'];
                    $data['cid'] = $ga['utmcmd'];
                    $data['ck'] = $ga['utmctr'];
                    $data['gclid'] = $ga['utmgclid'];
                    //$data['ua'] = $ga['user_agent'];//serialized
                    $data['sr'] = $ga['resolution'];
                    $data['uip'] = $ga['ip_address'];
                    if ($data['sr']) {
                        $data['je'] = 1;
                    }
                }
                if ($g_commerce->used_module == 'tagmanger') {
                    $data['ta'] = $g_commerce->gtm['actionField']['affiliation'];
                    $data['tr'] = $rr * $g_commerce->gtm['actionField']['revenue'];
                    $data['ts'] = $rr * $g_commerce->gtm['actionField']['shipping'];
                    $data['tt'] = $rr * $g_commerce->gtm['actionField']['tax'];
                    $data += $this->get_gtm_products($g_commerce);
                } else if (is_array($g_commerce->ga['ecommerce:addTransaction']) && count($g_commerce->ga['ecommerce:addTransaction'])) {
                    //ga
                    $data['ta'] = $g_commerce->ga['ecommerce:addTransaction']['affiliation'];
                    $data['tr'] = $rr * $g_commerce->ga['ecommerce:addTransaction']['revenue'];
                    $data['ts'] = $rr * $g_commerce->ga['ecommerce:addTransaction']['shipping'];
                    $data['tt'] = $rr * $g_commerce->ga['ecommerce:addTransaction']['tax'];
                    $data += $this->get_ga_products($g_commerce);
                } else {
                    //_gaq
                    $data['ta'] = $g_commerce->_gaq['_addTrans'][1];
                    //affiliate
                    $data['tr'] = $rr * $g_commerce->_gaq['_addTrans'][2];
                    //total
                    $data['tt'] = $rr * $g_commerce->_gaq['_addTrans'][3];
                    //tax
                    $data['ts'] = $rr * $g_commerce->_gaq['_addTrans'][4];
                    //shipping
                    $data += $this->get_gaq_products($g_commerce);
                }
                if ($data) {
                    return $this->collect_google_data($data);
                }
            }
        }
        return false;
    }
    public function get_gtm_products($g_commerce, $revert = false)
    {
        $data = [];
        if ($revert) {
            $rr = -1;
        } else {
            $rr = 1;
        }
        if (is_array($g_commerce->gtm['products'])) {
            $i = 1;
            foreach ($g_commerce->gtm['products'] as $product) {
                $data["pr{$i}id"] = $product['id'];
                $data["pr{$i}nm"] = $product['name'];
                $data["pr{$i}ca"] = $product['category'];
                $data["pr{$i}br"] = $product['brand'];
                $data["pr{$i}va"] = $product['variant'];
                $data["pr{$i}pr"] = $product['price'];
                $data["pr{$i}qt"] = $rr * $product['quantity'];
                $data["pr{$i}ps"] = $i;
                $i++;
            }
        }
        return $data;
    }
    public function get_ga_products($g_commerce, $revert = false)
    {
        $data = [];
        if ($revert) {
            $rr = -1;
        } else {
            $rr = 1;
        }
        if (is_array($g_commerce->ga['ecommerce:addItem'])) {
            $i = 1;
            foreach ($g_commerce->ga['ecommerce:addItem'] as $product) {
                $data["pr{$i}id"] = $product['sku'];
                $data["pr{$i}nm"] = $product['name'];
                $data["pr{$i}ca"] = $product['category'];
                $data["pr{$i}pr"] = $product['price'];
                $data["pr{$i}qt"] = $rr * $product['quantity'];
                $data["pr{$i}ps"] = $i;
                $i++;
            }
        }
        return $data;
    }
    public function get_gaq_products($g_commerce, $revert = false)
    {
        $data = [];
        if ($revert) {
            $rr = -1;
        } else {
            $rr = 1;
        }
        if (is_array($g_commerce->ga['_addItem'])) {
            $i = 1;
            foreach ($g_commerce->ga['_addItem'] as $product) {
                $data["pr{$i}id"] = $product[1];
                $data["pr{$i}nm"] = $product[2];
                $data["pr{$i}ca"] = $product[3];
                $data["pr{$i}pr"] = $product[4];
                $data["pr{$i}qt"] = $rr * $product[5];
                $data["pr{$i}ps"] = $i;
                $i++;
            }
        }
        return $data;
    }
    public function collect_google_data($data = [])
    {
        $http_client = new \yii\httpclient\Client();
        $request = $http_client->post($this->google_collect_url, $data);
        $response = $request->send();
        if ($response->is_ok) {
            return true;
        } else {
            return false;
        }
    }
}