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
namespace backend\models\EP;

use backend\models\EP\Provider\Provider_Abstract;
use backend\models\EP\Provider\Trueloaded\Trueloaded_Xml_Feed_Provider;
use Yii;
class Providers
{
    protected $providers = [];
    public function __construct()
    {
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/easypopulate');
        \common\helpers\Translation::init('admin/main');
        $this->providers = [
            'product\catalog' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_DOWNLOAD_CATALOG, 'class' => 'Provider\CatalogArchive', 'export' => ['allow_format' => ['ZIP'], 'filters' => ['category', 'products', 'with-images'], 'disableSelectFields' => true]],
            'product\products' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_PRODUCT, 'class' => 'Provider\Products', 'export' => ['filters' => ['category', 'price_tax', 'products']]],
            'product\categories' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_CATEGORIES, 'class' => 'Provider\Categories', 'export' => ['filters' => ['category', 'products', 'with-images']]],
            'product\brands' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_BRANDS, 'class' => 'Provider\Brands', 'export' => ['filters' => ['category', 'with-images']]],
            'product\products_to_categories' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_PRODUCTS_TO_CATEGORIES, 'class' => 'Provider\ProductsToCategories', 'export' => ['filters' => ['category', 'products'], 'disableSelectFields' => true]],
            'product\attributes' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_ATTRIBUTES, 'class' => 'Provider\Attributes', 'export' => ['filters' => ['category', 'products']]],
            'product\brands' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_BRANDS, 'class' => 'Provider\Brands', 'export' => ['filters' => []]],
            'product\suppliers' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => BOX_CATALOG_SUPPIERS, 'class' => 'Provider\Suppliers', 'export' => ['filters' => []]],
            'product\suppliersproducts' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => BOX_CATALOG_SUPPIERS_PRODUCTS, 'class' => 'Provider\SuppliersProducts', 'export' => ['filters' => ['category', 'products']]],
            'product\stock' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_STOCK_FEED, 'class' => 'Provider\Stock', 'export' => ['filters' => ['category', 'products']]],
            'product\sales' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_SALES_FEED, 'class' => 'Provider\Sales', 'export' => ['filters' => ['category', 'products', 'price_tax']]],
            'product\warehousestock' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => 'Warehouse ' . TEXT_OPTION_STOCK_FEED, 'class' => 'Provider\WarehouseStock', 'export' => ['filters' => ['category', 'products']]],
            'product\images' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_IMAGES, 'class' => 'Provider\Images', 'export' => ['filters' => ['category', 'products', 'with-images']]],
            'product\properties' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_PROPERTIES, 'class' => 'Provider\Properties', 'export' => ['filters' => ['category', 'products'], 'disableSelectFields' => true]],
            'product\catalog_properties' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_OPTION_PROPERTIES_SETTINGS, 'class' => 'Provider\CatalogProperties', 'export' => ['filters' => ['properties']]],
            'product\assets' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TEXT_PRODUCT_ASSETS, 'class' => 'Provider\Assets', 'export' => ['filters' => ['category', 'products'], 'disableSelectFields' => true]],
            'product\reviews' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => BOX_CATALOG_REVIEWS, 'class' => 'Provider\ProductReviews', 'export' => ['filters' => ['category', 'products']]],
            'product\documents' => ['group' => TEXT_CATALOG_PRODUCTS, 'name' => TAB_DOCUMENTS, 'class' => 'Provider\Documents', 'export' => ['filters' => ['category', 'products', 'with-images']]],
            'statistic\orders' => ['group' => TEXT_SITE_STATISTIC, 'name' => 'Order Statistic', 'class' => 'Provider\OrderStatistic', 'export' => ['filters' => ['orders-date-range'], 'disableSelectFields' => true]],
            'BrightPearl\Stock' => ['group' => TEXT_BRIGHT_PEARL, 'name' => 'Stock', 'class' => 'Provider\BrightPearl\Stock', 'export' => ['disableSelectFields' => true]],
            'BrightPearl\ExportPrice' => ['group' => TEXT_BRIGHT_PEARL, 'name' => 'Export Price', 'class' => 'Provider\BrightPearl\ExportPrice', 'export' => ['disableSelectFields' => true]],
            'BrightPearl\ExportOrder' => ['group' => TEXT_BRIGHT_PEARL, 'name' => 'Export Order', 'class' => 'Provider\BrightPearl\ExportOrder', 'export' => ['disableSelectFields' => true]],
            'HolbiLink\Products' => ['group' => 'Holbi Link', 'name' => 'Import products', 'class' => 'Provider\HolbiLink\ImportProducts', 'export' => ['disableSelectFields' => true]],
            'HPCap\ImportProducts' => ['group' => 'HP Cap', 'name' => 'Import products', 'class' => 'Provider\HPCap\ImportProducts', 'export' => ['disableSelectFields' => true]],
            'Magento\ImportGroups' => ['group' => 'Magento', 'name' => 'Import groups', 'class' => 'Provider\Magento\ImportGroups', 'export' => ['disableSelectFields' => true]],
            'Magento\ImportProducts' => ['group' => 'Magento', 'name' => 'Import products', 'class' => 'Provider\Magento\ImportProducts', 'export' => ['disableSelectFields' => true]],
            'Magento\ImportCustomers' => ['group' => 'Magento', 'name' => 'Import customers', 'class' => 'Provider\Magento\ImportCustomers', 'export' => ['disableSelectFields' => true]],
            'Magento\ImportOrders' => ['group' => 'Magento', 'name' => 'Import orders', 'class' => 'Provider\Magento\ImportOrders', 'export' => ['disableSelectFields' => true]],
            'orders\customers' => ['group' => TEXT_SITE_ORDER_EXPORT_IMPORT, 'name' => 'Customers', 'class' => 'Provider\Customers', 'export' => ['allow_format' => ['CSV']]],
            /*
            'orders\orders' => [
                'group' => TEXT_SITE_ORDER_EXPORT_IMPORT,
                'name' => 'Order Export/Import',
                'class' => 'Provider\\OrderExport',
                'export' =>[
                    'allow_format' => ['XML_orders_new'],
                    'filters' => ['orders-date-range'],
                    'disableSelectFields' => true,
                ],
                'import' =>[
                    'format' => 'XML_orders_new'
                ],
            ],
            */
            'orders\order' => ['group' => TEXT_SITE_ORDER_EXPORT_IMPORT, 'name' => 'Order', 'class' => 'Provider\Order', 'export' => ['allow_format' => ['CSV', 'XML'], 'filters' => ['orders-date-range']], 'import' => ['format' => 'XML']],
            'report\customers' => ['group' => 'Report', 'name' => 'Customers', 'class' => 'Provider\CustomersReport', 'export' => ['allow_format' => ['CSV'], 'filters' => ['platform']]],
            'PaymentBots\PaypalCollector' => ['group' => 'PaymentBots', 'name' => 'Paypal Transactions Collector', 'class' => 'Provider\PaymentBots\PaypalCollector', 'export' => ['disableSelectFields' => true]],
            'Google\SyncEcommerce' => ['group' => 'Google', 'name' => 'Sync e-commerce', 'class' => 'Provider\Google\SyncEcommerce'],
            'Trueloaded\Platforms' => ['group' => 'Trueloaded', 'name' => 'Platforms', 'class' => 'Provider\Trueloaded\Platforms', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['with-images'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Customers' => ['group' => 'Trueloaded', 'name' => 'Customers', 'class' => 'Provider\Trueloaded\Customers', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['platform'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Orders' => ['group' => 'Trueloaded', 'name' => 'Orders', 'class' => 'Provider\Trueloaded\Orders', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['platform', 'orders-date-range'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Quotes' => ['group' => 'Trueloaded', 'name' => 'Quotations', 'class' => 'Provider\Trueloaded\Quotes', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['platform', 'orders-date-range'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\OrdersStatusGroups' => ['group' => 'Trueloaded', 'name' => 'Order Statuses Groups', 'class' => 'Provider\Trueloaded\OrdersStatusGroups', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\OrdersStatuses' => ['group' => 'Trueloaded', 'name' => 'Order Statuses', 'class' => 'Provider\Trueloaded\OrdersStatuses', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Brands' => ['group' => 'Trueloaded', 'name' => 'Brands', 'class' => 'Provider\Trueloaded\Brands', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['with-images'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Countries' => ['group' => 'Trueloaded', 'name' => 'Countries', 'class' => 'Provider\Trueloaded\Countries', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Tax' => ['group' => 'Trueloaded', 'name' => 'Tax', 'class' => 'Provider\Trueloaded\Tax', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\TaxZones' => ['group' => 'Trueloaded', 'name' => 'Tax Zones', 'class' => 'Provider\Trueloaded\TaxZones', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Currencies' => ['group' => 'Trueloaded', 'name' => 'Currencies', 'class' => 'Provider\Trueloaded\Currencies', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Groups' => ['group' => 'Trueloaded', 'name' => 'Groups', 'class' => 'Provider\Trueloaded\Groups', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['with-images'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Languages' => ['group' => 'Trueloaded', 'name' => 'Languages', 'class' => 'Provider\Trueloaded\Languages', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
            'Trueloaded\Products' => ['group' => 'Trueloaded', 'name' => 'Products', 'class' => 'Provider\Trueloaded\Products', 'export' => [
                'allow_format' => ['XML', 'XML-ZIP'],
                //'filters' => ['with-images'],
                'disableSelectFields' => true,
            ], 'import' => ['format' => 'XML']],
            'Trueloaded\Warehouses' => ['group' => 'Trueloaded', 'name' => 'Warehouses', 'class' => 'Provider\Trueloaded\Warehouses', 'export' => [
                'allow_format' => ['XML', 'XML-ZIP'],
                //'filters' => ['with-images'],
                'disableSelectFields' => true,
            ], 'import' => ['format' => 'XML']],
            'Trueloaded\Suppliers' => ['group' => 'Trueloaded', 'name' => 'Suppliers', 'class' => 'Provider\Trueloaded\Suppliers', 'export' => [
                'allow_format' => ['XML', 'XML-ZIP'],
                //'filters' => ['with-images'],
                'disableSelectFields' => true,
            ], 'import' => ['format' => 'XML']],
            'Trueloaded\Themes' => ['group' => 'Trueloaded', 'name' => 'Themes', 'class' => 'Provider\Trueloaded\Themes', 'export' => ['allow_format' => ['XML', 'XML-ZIP'], 'filters' => ['with-images'], 'disableSelectFields' => true], 'import' => ['format' => 'XML']],
        ];
        $this->providers = array_merge($this->providers, Trueloaded_Xml_Feed_Provider::get_provider_list());
        if (!\common\helpers\Acl::check_extension_table_exist('Quotations', 'QuoteOrders')) {
            unset($this->providers['Trueloaded\Quotes']);
        }
        $this->providers = $this->providers + \common\helpers\Acl::get_extension_ep_providers();
        foreach (Data_Sources::get_available_list() as $data_source_info) {
            if (method_exists($data_source_info['className'], 'getProviderList')) {
                $data_source_provider_list = call_user_func([$data_source_info['className'], 'getProviderList']);
                if (is_array($data_source_provider_list) && count($data_source_provider_list) > 0) {
                    $this->providers = array_merge($this->providers, $data_source_provider_list);
                }
            }
        }
        $get_custom_r = tep_db_query('SELECT custom_provider_id, name, parent_provider, provider_configure ' . 'FROM ' . TABLE_EP_CUSTOM_PROVIDERS . ' ' . 'WHERE 1 ' . 'ORDER BY 1');
        if (tep_db_num_rows($get_custom_r) > 0) {
            while ($custom = tep_db_fetch_array($get_custom_r)) {
                $parent_provider = $custom['parent_provider'];
                if (!isset($this->providers[$parent_provider])) {
                    continue;
                }
                $provider_info = $this->providers[$parent_provider];
                $provider_info['name'] = $custom['name'];
                $provider_key = 'custom\\' . $custom['custom_provider_id'];
                //$provider_info['provider_configure'];
                $this->providers[$provider_key] = $provider_info;
            }
        }
    }
    public function get_available_providers($type, $filter_group = '')
    {
        $provider_list = [];
        foreach ($this->providers as $provider_key => $provider_info) {
            if (!empty($filter_group) && is_callable($filter_group)) {
                if (!$filter_group($provider_key, $provider_info)) {
                    continue;
                }
            } else if (!empty($filter_group) && strpos($provider_key, $filter_group . '\\') !== 0) {
                continue;
            }
            $provider_class_name = $this->get_provider_full_class_name($provider_key);
            if ($type == 'Import' && is_subclass_of($provider_class_name, 'backend\models\EP\Provider\ImportInterface', true) && call_user_func([$provider_class_name, 'isImportAvailable'])) {
                $provider_info['key'] = $provider_key;
                $provider_list[] = $provider_info;
            } elseif ($type == 'Export' && is_subclass_of($provider_class_name, 'backend\models\EP\Provider\ExportInterface', true) && call_user_func([$provider_class_name, 'isExportAvailable'])) {
                $provider_info['key'] = $provider_key;
                $provider_list[] = $provider_info;
            } elseif ($type == 'Datasource' && is_subclass_of($provider_class_name, 'backend\models\EP\Provider\DatasourceInterface', true)) {
                $provider_info['key'] = $provider_key;
                $provider_list[] = $provider_info;
            } elseif ($ext = \common\helpers\Acl::check_extension($provider_info['class'], 'allowed')) {
                $provider_info['key'] = $provider_key;
                $provider_list[] = $provider_info;
            }
        }
        return $provider_list;
    }
    public function pull_down_variants($for = 'Import', $pull_down_data = [], $filter_group = '')
    {
        if (!isset($pull_down_data['items'])) {
            $pull_down_data['items'] = [];
        }
        if (!isset($pull_down_data['options'])) {
            $pull_down_data['options'] = [];
        }
        if (!isset($pull_down_data['options']['options'])) {
            $pull_down_data['options']['options'] = [];
        }
        $option_key = strtolower($for);
        foreach ($this->get_available_providers($for, $filter_group) as $provider_info) {
            $group = $provider_info['group'];
            if (!isset($pull_down_data['items'][$group])) {
                $pull_down_data['items'][$group] = [];
            }
            $pull_down_data['items'][$group][$provider_info['key']] = $provider_info['name'];
            $provider_options = [];
            if (isset($provider_info[$option_key]) && is_array($provider_info[$option_key])) {
                $provider_options = $provider_info[$option_key];
            }
            $options_data = [];
            if (!isset($provider_options['disableSelectFields']) || !$provider_options['disableSelectFields']) {
                $options_data['data-select-fields'] = 'true';
            }
            if (isset($provider_options['filters']) && count($provider_options['filters']) > 0) {
                foreach ($provider_options['filters'] as $filter_code) {
                    $options_data['data-allow-select-' . $filter_code] = 'true';
                }
            }
            if (isset($provider_options['allow_format']) && count($provider_options['allow_format']) > 0) {
                $options_data['data-allow-format'] = implode(',', $provider_options['allow_format']);
            } else {
                $options_data['data-allow-format'] = 'CSV,ZIP,XLSX';
            }
            if (count($options_data) > 0) {
                $pull_down_data['options']['options'][$provider_info['key']] = $options_data;
            }
        }
        return $pull_down_data;
    }
    public function get_provider_name($provider)
    {
        if (isset($this->providers[$provider])) {
            return $this->providers[$provider]['name'];
        }
        return 'Unknown';
    }
    public function get_provider_full_class_name($key)
    {
        if (!isset($this->providers[$key])) {
            return false;
        }
        if ($provider_class_name = \common\helpers\Acl::check_extension($this->providers[$key]['class'], 'allowed')) {
        } elseif (class_exists($this->providers[$key]['class'])) {
            $provider_class_name = $this->providers[$key]['class'];
        } else {
            $provider_class_name = 'backend\models\EP\\' . $this->providers[$key]['class'];
        }
        return $provider_class_name;
    }
    public function get_provider_config($key)
    {
        return isset($this->providers[$key]) ? $this->providers[$key] : [];
    }
    /**
     * @param $key
     * @param array $providerConfig
     * @return bool|ProviderAbstract
     */
    public function get_provider_instance($key, $provider_config = [])
    {
        $provider_class_name = $this->get_provider_full_class_name($key);
        if ($provider_class_name) {
            /**
             * @var $obj ProviderAbstract
             */
            $obj = Yii::create_object($provider_class_name, [$provider_config]);
            if (method_exists($obj, 'customConfig')) {
                $obj->custom_config($provider_config);
            }
            return $obj;
        }
        return false;
    }
    public function best_match(array $file_columns)
    {
        $providers_match_rate = [];
        foreach ($this->get_available_providers('Import') as $provider_info) {
            if (isset($provider_info['export']) && isset($provider_info['export']['allow_format']) && count($provider_info['export']['allow_format']) > 0) {
                if (!in_array('CSV', $provider_info['export']['allow_format'])) {
                    continue;
                }
            }
            if (strpos($provider_info['key'], 'BrightPearl') !== false) {
                continue;
            }
            $provider = $this->get_provider_instance($provider_info['key']);
            if (!is_object($provider)) {
                continue;
            }
            /**
             * @var $provider ProviderAbstract
             */
            $score = $provider->get_column_match_score($file_columns);
            if ($score > 0) {
                $providers_match_rate[$provider_info['key']] = $score;
            }
        }
        arsort($providers_match_rate, SORT_NUMERIC);
        return $providers_match_rate;
    }
}