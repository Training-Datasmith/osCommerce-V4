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

use yii\helpers\File_Helper;
class Data_Sources
{
    public static $source_path = '\backend\models\EP\Datasource\\';
    public static function get_available_list()
    {
        static $list = false;
        if (!is_array($list)) {
            $list = [];
            try {
                foreach (File_Helper::find_files(dirname(__FILE__) . '/Datasource/', ['recursive' => false, 'only' => ['pattern' => '*.php']]) as $file) {
                    $class_name = pathinfo($file, PATHINFO_FILENAME);
                    $instance = \Yii::create_object(static::$source_path . $class_name);
                    if (is_object($instance) && $instance instanceof Datasource_Base) {
                        $list[] = ['class' => $class_name, 'className' => static::$source_path . $class_name, 'name' => $instance->get_name()];
                    }
                }
                $list = array_merge($list, \common\helpers\Acl::get_extension_ep_data_sources());
            } catch (\Exception $ex) {
                \Yii::warning('Error in DataSources::getAvailableList: ' . $ex->get_message() . "\n" . $ex->get_trace_as_string());
            }
        }
        return $list;
    }
    /** @var array $condition like ['class' => 'PdfCatalogues']*/
    private static function find_available(array $condition)
    {
        foreach (self::get_available_list() as $data_source) {
            $matched = false;
            foreach ($condition as $key => $value) {
                $matched = $data_source[$key] == $value;
                if (!$matched) {
                    break;
                }
            }
            if ($matched) {
                return $data_source;
            }
        }
    }
    public static function add($data)
    {
        tep_db_perform('ep_datasources', ['code' => $data['name'], 'class' => $data['class']]);
        $ds_root = Directory::load_by_id(5);
        File_Helper::create_directory($ds_root->files_root() . $data['name'], 0777);
        $ds_root->synchronize_directories(false);
        $get_created_id_r = tep_db_query('SELECT directory_id FROM ' . TABLE_EP_DIRECTORIES . " WHERE directory='" . tep_db_input($data['name']) . "' AND parent_id=5");
        if (tep_db_num_rows($get_created_id_r) > 0) {
            $get_created_id = tep_db_fetch_array($get_created_id_r);
            $created_dir = Directory::load_by_id($get_created_id['directory_id']);
            File_Helper::create_directory($created_dir->files_root() . 'processed', 0777);
            $created_dir->synchronize_directories(false);
        }
    }
    public static function remove($name)
    {
        tep_db_query("DELETE FROM ep_datasources WHERE code='" . tep_db_input($name) . "'");
    }
    public static function get_by_name($name)
    {
        $datasource = false;
        $get_data_r = tep_db_query("SELECT * FROM ep_datasources WHERE code='" . tep_db_input($name) . "'");
        if (tep_db_num_rows($get_data_r) > 0) {
            $data = tep_db_fetch_array($get_data_r);
            $available_ds = self::find_available(['class' => $data['class']]);
            if ($available_ds) {
                $datasource = \Yii::create_object(['class' => $available_ds['className'], 'code' => $data['code'], 'settings' => $data['settings']]);
            }
        }
        return $datasource;
    }
    public static function get_active_by_class($name)
    {
        $datasources = [];
        $get_data_r = tep_db_query('SELECT ds.*, ed.directory_id ' . 'FROM ep_datasources ds ' . " INNER JOIN ep_directories ed ON ed.directory_type='datasource' AND ds.code=ed.directory " . "WHERE ds.class='" . tep_db_input($name) . "'");
        if (tep_db_num_rows($get_data_r) > 0) {
            while ($data = tep_db_fetch_array($get_data_r)) {
                $available_ds = self::find_available(['class' => $name]);
                if ($available_ds) {
                    $datasources[$data['directory_id']] = \Yii::create_object(['class' => $available_ds['className'], 'code' => $data['code'], 'settings' => $data['settings']]);
                }
            }
        }
        return $datasources;
    }
    public static function order_view($order_id)
    {
        if (empty($order_id)) {
            return [];
        }
        \common\helpers\Translation::init('admin/easypopulate');
        $result = [];
        $get_any_sources_r = tep_db_query('SELECT * ' . 'FROM ep_holbi_soap_link_orders ' . "WHERE local_orders_id='" . $order_id . "' and cfg_export_as = 'order' " . 'ORDER BY ep_directory_id');
        if (tep_db_num_rows($get_any_sources_r) > 0) {
            while ($_source = tep_db_fetch_array($get_any_sources_r)) {
                if ($_source['remote_orders_id'] == 0) {
                    continue;
                }
                try {
                    $directory = Directory::find_by_id($_source['ep_directory_id']);
                    /**
                     * @var \backend\models\EP\Directory $directory
                     */
                    if (empty($directory->get_datasource())) {
                        continue;
                        // in case of deleted datasources
                    }
                    $_module_order_view = $directory->get_datasource()->order_view($order_id);
                    if ($_module_order_view === false) {
                        continue;
                    }
                    $_source['datasourceName'] = $directory->get_datasource()->get_name();
                    $_source['directory'] = $directory;
                    $result[] = $_source;
                } catch (\Exception $e) {
                }
                // not important - none details available any more
            }
        }
        $info = '';
        if (count($result) > 0) {
            foreach ($result as $item) {
                $info .= '<div class="cr-ord-cust cr-ord-cust-datasource" id="jsBlkExchangeInfo' . $item['ep_directory_id'] . '"><span>' . $item['directory']->directory . '</span>';
                if ($item['remote_orders_id'] > 0) {
                    $remote_id = $item['remote_orders_id'];
                    if (!empty($item['remote_order_number'])) {
                        $remote_id = $item['remote_order_number'];
                    } elseif (!empty($item['remote_guid']) && $remote_id == $order_id) {
                        $remote_id = $item['remote_guid'];
                    }
                    $export_date = $item['date_exported'] > 2000 ? \common\helpers\Date::datetime_short($item['date_exported']) : '';
                    $info .= '<div>';
                    if (method_exists($directory->get_datasource(), 'getOrderLink')) {
                        $info .= TEXT_EXTERNAL_ORDERS_ID . ' ' . $directory->get_datasource()->get_order_link($remote_id) . '<br />';
                    } else {
                        $info .= TEXT_EXTERNAL_ORDERS_ID . ' ' . $remote_id . '<br />';
                    }
                    if ($export_date) {
                        $info .= TEXT_DATE_ADDED . ' ' . $export_date;
                    }
                    $info .= '</div>';
                } elseif ($item['remote_orders_id'] == -1) {
                    $info .= TEXT_DISABLED;
                }
                $info .= '</div>';
            }
        }
        return $info;
    }
    public static function order_view_export($order_id)
    {
        if (empty($order_id)) {
            return [];
        }
        \common\helpers\Translation::init('admin/easypopulate');
        $result = [];
        $get_any_sources_r = tep_db_query('select ld.directory_id  ' . ' from ep_directories ld ' . " where ld.directory_config like '%ExportOrders%'  and ld.directory_type='datasource' " . ' and not exists( SELECT * ' . 'FROM ep_holbi_soap_link_orders lo ' . "WHERE local_orders_id='" . $order_id . "' and remote_orders_id>0 and ld.directory_id =lo.ep_directory_id and cfg_export_as = 'order' " . ')');
        if (tep_db_num_rows($get_any_sources_r) > 0) {
            while ($_source = tep_db_fetch_array($get_any_sources_r)) {
                $result[] = $_source;
            }
        }
        return $result;
    }
}