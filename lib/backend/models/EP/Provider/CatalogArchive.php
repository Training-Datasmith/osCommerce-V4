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
namespace backend\models\EP\Provider;

use backend\models\EP\Messages;
use backend\models\EP\Providers;
class Catalog_Archive extends Provider_Abstract implements Import_Interface, Export_Interface
{
    protected $archive_providers = [];
    protected $archive_settings = ['useColumns' => [], 'filter' => []];
    public function init()
    {
        parent::init();
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\categories', 'feedname' => 'catalog_categories.csv'];
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\products', 'feedname' => 'catalog_products.csv'];
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\images', 'feedname' => 'catalog_product_images.csv'];
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\products_to_categories', 'feedname' => 'catalog_categories_product_assign.csv'];
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\attributes', 'feedname' => 'catalog_product_attributes.csv'];
        if (\common\helpers\Extensions::is_allowed('Inventory')) {
            $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'Inventory\Product', 'feedname' => 'catalog_inventory.csv'];
        }
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\suppliers', 'feedname' => 'catalog_suppliers.csv'];
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\suppliersproducts', 'feedname' => 'catalog_suppliers_products.csv'];
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\warehousestock', 'feedname' => 'catalog_warehouse_stock.csv'];
        if (\common\helpers\Extensions::is_allowed('ProductBundles')) {
            $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'ProductBundles\ProductBundles', 'feedname' => 'catalog_bundles.csv'];
        }
        if (\common\helpers\Extensions::is_allowed('LinkedProducts')) {
            $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'LinkedProducts\LinkedProducts', 'feedname' => 'catalog_linked_products.csv'];
        }
        if (\common\helpers\Extensions::is_allowed('UpSell')) {
            $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'UpSell\CrossSell', 'feedname' => 'catalog_xsell_products.csv'];
        }
        $this->archive_providers[] = ['format' => 'CSV', 'provider' => 'product\properties', 'feedname' => 'catalog_properties.csv'];
        $providers = new Providers();
        foreach ($this->archive_providers as $idx => $archive_provider) {
            $obj = $providers->get_provider_instance($archive_provider['provider']);
            if (!is_object($obj)) {
                unset($this->archive_providers[$idx]);
            } else {
                $this->archive_providers[$idx]['obj'] = $obj;
                $this->archive_providers[$idx]['name'] = $providers->get_provider_name($archive_provider['provider']);
            }
        }
        //$this->archiveProviders = array_values($this->archiveProviders);
        $this->init_fields();
    }
    protected function init_fields()
    {
        $this->fields = [];
        $this->fields[] = ['name' => 'name', 'value' => 'Feed Name'];
        $this->fields[] = ['name' => 'feedname', 'value' => 'Feed Process Queue'];
        $this->fields[] = ['name' => 'provider', 'value' => 'Feed Type'];
    }
    public function prepare_export($use_columns, $filter)
    {
        foreach ($this->archive_providers as $idx => $archive_provider) {
            $export_columns = $archive_provider['obj']->get_columns();
            $this->archive_providers[$idx]['columns'] = $export_columns;
            $archive_provider['obj']->set_columns($export_columns);
            $archive_provider['obj']->prepare_export(array_keys($export_columns), $filter);
        }
        $this->archive_settings['useColumns'] = $use_columns;
        $this->archive_settings['filter'] = $filter;
        reset($this->archive_providers);
    }
    public function export_row()
    {
        /**
         * @var $exportProviderObj ProviderAbstract
         */
        $export_provider_info = current($this->archive_providers);
        if (!is_array($export_provider_info)) {
            return false;
        }
        $export_provider_obj = $export_provider_info['obj'];
        $format = $export_provider_info['format'];
        $archive_filename = $export_provider_info['feedname'];
        $created_feed_filename = tempnam(sys_get_temp_dir(), 'ep_all_catalog_write');
        $writer = \Yii::create_object(['class' => 'backend\models\EP\Writer\\' . $format, 'filename' => $created_feed_filename]);
        $writer->set_columns($export_provider_info['columns']);
        while (is_array($provider_data = $export_provider_obj->export_row())) {
            if (substr(strval(key($provider_data)), 0, 1) == ':') {
                if (isset($provider_data[':feed_data'])) {
                    $writer->write($provider_data[':feed_data']);
                }
                if (isset($provider_data[':attachments'])) {
                    foreach ($provider_data[':attachments'] as $write_file) {
                        $files_add[] = ['filename' => $write_file['filename'], 'localname' => $write_file['localname']];
                    }
                }
            } else {
                $writer->write($provider_data);
            }
        }
        $writer->close();
        $files_add[] = ['filename' => $created_feed_filename, 'localname' => $archive_filename];
        $next_data = next($this->archive_providers);
        if (!$next_data) {
            ob_start();
            $sequence_writer = \Yii::create_object(['class' => 'backend\models\EP\Writer\\' . $format, 'filename' => 'php://output']);
            $sequence_writer->set_columns($this->get_columns());
            foreach ($this->archive_providers as $__archive_provider) {
                $sequence_writer->write($__archive_provider);
            }
            $feed_queue = ob_get_clean();
            $sequence_writer->close();
            $files_add[] = ['filename' => 'process_sequence.csv', 'string' => $feed_queue];
        }
        return $files_add;
    }
    public function import_row($data, Messages $message)
    {
        $message->command('persist_messages', true);
        $sub_job = $this->directory_obj->find_job_by_filename($data['feedname']);
        if ($sub_job) {
            $message->info('<b>Process "' . $sub_job->file_name . '"</b>');
            try {
                $sub_job->run($message);
            } catch (\Exception $ex) {
                $message->info($ex->get_message());
            }
        }
    }
    public function post_process(Messages $message)
    {
        $message->command('persist_messages', false);
    }
}