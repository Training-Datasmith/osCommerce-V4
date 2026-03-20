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

use backend\models\EP;
use backend\models\EP\Messages;
use common\api\models\AR\Manufacturer;
use common\classes\Images as CommonImages;
class Brands extends Provider_Abstract implements Import_Interface, Export_Interface
{
    protected $data = [];
    protected $e_ptools;
    protected $entry_counter = 0;
    protected $import_folder = '';
    protected $with_images = false;
    public function init()
    {
        parent::init();
        $this->init_fields();
        $this->e_ptools = new EP\Tools();
        if ($this->directory_obj) {
            $this->set_images_directory($this->directory_obj->files_root(EP\Directory::TYPE_IMAGES));
        }
    }
    public function set_images_directory($images_folder)
    {
        $this->import_folder = $images_folder;
    }
    protected function init_fields()
    {
        $this->fields = [];
        $this->fields[] = ['name' => 'key_field', 'value' => 'KEY_FIELD', 'is_key' => true, 'calculated' => true, 'get' => 'get_key_field'];
        $dummy = new Manufacturer();
        $attr = $dummy->get_possible_keys();
        $column_cover = ['manufacturers_id' => false, 'date_added' => false, 'last_modified' => false, 'manufacturers_image_data' => false, 'manufacturers_image_source_url' => false, 'manufacturers_old_seo_page_name' => ['value' => 'Old Seo Page Name']];
        foreach ($attr as $key) {
            $column_describe = ['name' => $key, 'value' => ucwords(preg_replace('/[ \._]/', ' ', $key))];
            if (isset($column_cover[$key])) {
                if ($column_cover[$key] == false) {
                    continue;
                }
                if (is_array($column_cover[$key])) {
                    $column_describe = array_merge($column_describe, $column_cover[$key]);
                }
            }
            $this->fields[] = $column_describe;
        }
    }
    public function get_key_field($field_data, $id)
    {
        return $id;
    }
    public function prepare_export($use_columns, $filter)
    {
        $this->build_sources($use_columns);
        $main_source = $this->main_source;
        $filter_sql = '';
        if (is_array($filter)) {
            $this->with_images = isset($filter['with_images']) && $filter['with_images'];
            if (isset($filter['category_id']) && $filter['category_id'] > 0) {
                $categories = [(int) $filter['category_id']];
                \common\helpers\Categories::get_subcategories($categories, $categories[0]);
                $get_categories_brands_r = tep_db_query('SELECT DISTINCT p.manufacturers_id ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . '  INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . " p2c ON p2c.products_id=p.products_id AND p2c.categories_id IN('" . implode("','", $categories) . "') " . 'WHERE (p.manufacturers_id IS NOT NULL OR p.manufacturers_id!=0) ');
                if (tep_db_num_rows($get_categories_brands_r) > 0) {
                    $brand_ids = [];
                    while ($_categories_brand = tep_db_fetch_array($get_categories_brands_r)) {
                        $brand_ids[] = $_categories_brand['manufacturers_id'];
                    }
                    $filter_sql .= "AND manufacturers_id IN('" . implode("','", $brand_ids) . "') ";
                } else {
                    $filter_sql .= 'AND 1=0 ';
                }
            }
        }
        $main_sql = 'SELECT manufacturers_id ' . 'FROM ' . TABLE_MANUFACTURERS . ' ' . "WHERE 1 {$filter_sql} " . 'ORDER BY manufacturers_name';
        $this->export_query = tep_db_query($main_sql);
    }
    public function export_row()
    {
        $this->data = tep_db_fetch_array($this->export_query);
        if (!is_array($this->data)) {
            return $this->data;
        }
        //$data_sources = $this->data_sources;
        $export_columns = $this->export_columns;
        $data_object = Manufacturer::find_one(['manufacturers_id' => $this->data['manufacturers_id']]);
        $object_multi_data = $data_object->export_array([]);
        $object_flat_data = EP\Array_Transform::convert_multi_dimensional_to_flat($object_multi_data);
        $this->data = array_merge($this->data, $object_flat_data);
        foreach ($export_columns as $db_key => $export) {
            if (isset($export['get']) && method_exists($this, $export['get'])) {
                $this->data[$db_key] = call_user_func_array([$this, $export['get']], [$export, $this->data['manufacturers_id']]);
            }
            $this->data[$db_key] = isset($this->data[$db_key]) ? $this->data[$db_key] : '';
        }
        if ($this->with_images) {
            $files_add = [];
            foreach (['manufacturers_image'] as $image_column) {
                if (empty($this->data[$image_column])) {
                    continue;
                }
                $fs_image_name = Common_Images::get_fs_catalog_images_path() . $this->data[$image_column];
                if (!is_file($fs_image_name)) {
                    continue;
                }
                $files_add[] = ['filename' => $fs_image_name, 'localname' => 'images/' . $this->data[$image_column]];
            }
            if (count($files_add) > 0) {
                return [':feed_data' => $this->data, ':attachments' => $files_add];
            }
        }
        return $this->data;
    }
    public function import_row($data, Messages $message)
    {
        $this->data = $data;
        $multi_data = EP\Array_Transform::convert_flat_to_multi_dimensional($this->data);
        $brand_model = Manufacturer::find_one(['manufacturers_id' => $this->data['key_field']]);
        if (!$brand_model) {
            $brand_model = Manufacturer::find_one(['manufacturers_name' => $multi_data['manufacturers_name']]);
            if (!empty($brand_model)) {
                $message->info('Duplicate name. Skipped');
                return false;
            }
            $brand_model = new Manufacturer();
            $brand_model->load_default_values();
            unset($multi_data['key_field']);
        }
        if (!empty($multi_data['manufacturers_image'])) {
            if (preg_match('/^https?:\/\//', $multi_data['manufacturers_image'])) {
                // download remote images
                $multi_data['manufacturers_image_source_url'] = $multi_data['manufacturers_image'];
                $multi_data['manufacturers_image'] = basename($multi_data['manufacturers_image_source_url']);
            } elseif ($this->import_folder && is_dir($this->import_folder) && is_file($this->import_folder . $multi_data['manufacturers_image'])) {
                $multi_data['manufacturers_image_source_url'] = $this->import_folder . $multi_data['manufacturers_image'];
            } elseif (is_file(\common\classes\Images::get_fs_catalog_images_path() . 'import/' . $multi_data['manufacturers_image'])) {
                $multi_data['manufacturers_image_source_url'] = \common\classes\Images::get_fs_catalog_images_path() . 'import/' . $multi_data['manufacturers_image'];
            } elseif (is_file(\common\classes\Images::get_fs_catalog_images_path() . $multi_data['manufacturers_image'])) {
                $multi_data['manufacturers_image_source_url'] = \common\classes\Images::get_fs_catalog_images_path() . $multi_data['manufacturers_image'];
            }
        }
        $brand_model->import_array($multi_data);
        if ($brand_model->save(false)) {
            $this->entry_counter++;
        }
    }
    public function post_process(Messages $message)
    {
        $message->info('Brands processed: ' . $this->entry_counter);
        $message->info('Done');
    }
}