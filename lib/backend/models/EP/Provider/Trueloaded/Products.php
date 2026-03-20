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
namespace backend\models\EP\Provider\Trueloaded;

use common\api\models\XML\Io_Core;
use Yii;
class Products extends Xml_Base
{
    public function init()
    {
        $this->configure_map = Io_Core::get_export_structure('products');
        parent::init();
    }
    public function clear_local_data()
    {
        \common\classes\Images::clean_image_reference();
        $product_images_dir_path = \common\classes\Images::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR;
        if (is_dir($product_images_dir_path)) {
            $images_dir_handle = opendir($product_images_dir_path);
            while (($product_image_directory = readdir($images_dir_handle)) !== false) {
                if (!is_numeric($product_image_directory) || intval($product_image_directory) != $product_image_directory) {
                    continue;
                }
                $remove_image_directory = $product_images_dir_path . DIRECTORY_SEPARATOR . $product_image_directory;
                if (is_file($remove_image_directory)) {
                    continue;
                }
                //??
                try {
                    \yii\helpers\File_Helper::remove_directory($remove_image_directory);
                } catch (\Exception $ex) {
                }
            }
            closedir($images_dir_handle);
        }
        \common\helpers\Product::trunk_products();
        \common\helpers\Categories::trunk_categories();
        tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_TO_PROPERTIES_CATEGORIES);
        tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES);
        tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_DESCRIPTION);
        tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_TO_PRODUCTS);
        tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_VALUES);
        if (defined('TABLE_PRODUCTS_IMAGES_EXTERNAL_URL')) {
            tep_db_query('TRUNCATE TABLE ' . TABLE_PRODUCTS_IMAGES_EXTERNAL_URL);
        }
        tep_db_query('TRUNCATE TABLE ' . TABLE_DOCUMENT_TYPES);
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_products');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_mapping');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_products');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_products_flags');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_kv_storage');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_kw_id_storage');
        $schema_check = Yii::$app->get('db')->schema->get_table_schema('gapi_search');
        if ($schema_check) {
            tep_db_query('TRUNCATE TABLE gapi_search');
        }
        $schema_check = Yii::$app->get('db')->schema->get_table_schema('gapi_search_to_products');
        if ($schema_check) {
            tep_db_query('TRUNCATE TABLE gapi_search_to_products');
        }
        $schema_check = Yii::$app->get('db')->schema->get_table_schema('products_groups');
        if ($schema_check) {
            tep_db_query('TRUNCATE TABLE products_groups');
        }
    }
}