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
namespace common\api\models\AR;

use common\api\models\AR\Categories\Assigned_Customer_Groups as CategoryAssignedCustomerGroups;
use common\api\models\AR\Categories\Assigned_Departments;
use common\api\models\AR\Categories\Assigned_Platforms;
use common\api\models\AR\Categories\Description;
use yii\db\Expression;
use yii\helpers\File_Helper;
class Categories extends Ep_Map
{
    protected $hide_fields = [
        'previous_status',
        'last_xml_import',
        'last_xml_export',
        //'categories_level',
        'categories_left',
        'categories_right',
    ];
    protected $child_collections = ['descriptions' => [], 'assigned_platforms' => false, 'assigned_customer_groups' => false];
    protected $indexed_collections = ['assigned_platforms' => 'common\api\models\AR\Categories\AssignedPlatforms', 'assigned_customer_groups' => 'common\api\models\AR\Categories\AssignedCustomerGroups'];
    public $categories_image_data = '';
    public $categories_image_source_url = '';
    public $categories_image_2_data = '';
    public $categories_image_2_source_url = '';
    public $categories_image_3_data = '';
    public $categories_image_3_source_url = '';
    protected $auto_status = null;
    private $changed_name = false;
    public function __construct(array $config = [])
    {
        if (defined('TABLE_DEPARTMENTS_CATEGORIES')) {
            $this->child_collections['assigned_departments'] = false;
            $this->indexed_collections['assigned_departments'] = 'common\api\models\AR\Categories\AssignedDepartments';
        }
        if (!$ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'allowed')) {
            unset($this->child_collections['assigned_customer_groups']);
            unset($this->indexed_collections['assigned_customer_groups']);
        }
        parent::__construct($config);
    }
    public static function table_name()
    {
        return TABLE_CATEGORIES;
    }
    public static function primary_key()
    {
        return ['categories_id'];
    }
    public function custom_fields()
    {
        $fields = parent::custom_fields();
        $fields[] = 'categories_image_data';
        $fields[] = 'categories_image_source_url';
        $fields[] = 'categories_image_2_data';
        $fields[] = 'categories_image_2_source_url';
        $fields[] = 'categories_image_3_data';
        $fields[] = 'categories_image_3_source_url';
        return $fields;
    }
    public function set_auto_status($value)
    {
        $this->auto_status = $value;
    }
    public function init_collection_by_lookup_key_descriptions($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        foreach (Description::get_all_key_codes() as $key_code => $lookup_pk) {
            $this->child_collections['descriptions'][$key_code] = null;
            if (is_null($this->categories_id)) {
                $this->child_collections['descriptions'][$key_code] = new Description($lookup_pk);
            } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                if (!isset($this->child_collections['descriptions'][$key_code])) {
                    $lookup_pk['categories_id'] = $this->categories_id;
                    $this->child_collections['descriptions'][$key_code] = Description::find_one($lookup_pk);
                    if (!is_object($this->child_collections['descriptions'][$key_code])) {
                        $this->child_collections['descriptions'][$key_code] = new Description($lookup_pk);
                    }
                }
            }
        }
        return $this->child_collections['descriptions'];
    }
    public function init_collection_by_lookup_key_assigned_platforms($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_platforms'])) {
            $this->child_collections['assigned_platforms'] = [];
            if ($this->categories_id) {
                $this->child_collections['assigned_platforms'] = Assigned_Platforms::find()->where(['categories_id' => $this->categories_id])->order_by(['platform_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_platforms'];
    }
    public function init_collection_by_lookup_key_assigned_customer_groups($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_customer_groups'])) {
            $this->child_collections['assigned_customer_groups'] = [];
            if ($this->categories_id) {
                $this->child_collections['assigned_customer_groups'] = Category_Assigned_Customer_Groups::find()->where(['categories_id' => $this->categories_id])->order_by(['groups_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_customer_groups'];
    }
    public function init_collection_by_lookup_key_assigned_departments($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_departments'])) {
            $this->child_collections['assigned_departments'] = [];
            if ($this->categories_id) {
                $this->child_collections['assigned_departments'] = Assigned_Departments::find()->where(['categories_id' => $this->categories_id])->order_by(['departments_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_departments'];
    }
    public function export_array(array $fields = [])
    {
        if (!empty($this->categories_image) && is_file(\common\classes\Images::get_fs_catalog_images_path() . $this->categories_image)) {
            if (count($fields) == 0 || array_key_exists('categories_image_data', $fields)) {
                //$this->categories_image_data = file_get_contents(\common\classes\Images::getFSCatalogImagesPath().$data['categories_image']);
            }
            if (count($fields) == 0 || array_key_exists('categories_image_source_url', $fields)) {
                $this->categories_image_source_url = \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . DIR_WS_IMAGES . rawurlencode($this->categories_image);
            }
        }
        if (!empty($this->categories_image_2) && is_file(\common\classes\Images::get_fs_catalog_images_path() . $this->categories_image_2)) {
            if (count($fields) == 0 || array_key_exists('categories_image_2_data', $fields)) {
                //$this->categories_image_2_data = file_get_contents(\common\classes\Images::getFSCatalogImagesPath().$data['categories_image_2']);
            }
            if (count($fields) == 0 || array_key_exists('categories_image_2_source_url', $fields)) {
                $this->categories_image_2_source_url = \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . DIR_WS_IMAGES . rawurlencode($this->categories_image_2);
            }
        }
        if (!empty($this->categories_image_3) && is_file(\common\classes\Images::get_fs_catalog_images_path() . $this->categories_image_3)) {
            if (count($fields) == 0 || array_key_exists('categories_image_3_data', $fields)) {
                //$this->categories_image_3_data = file_get_contents(\common\classes\Images::getFSCatalogImagesPath().$data['categories_image_3']);
            }
            if (count($fields) == 0 || array_key_exists('categories_image_3_source_url', $fields)) {
                $this->categories_image_3_source_url = \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . DIR_WS_IMAGES . rawurlencode($this->categories_image_3);
            }
        }
        $data = parent::export_array($fields);
        if ((count($fields) == 0 || array_key_exists('categories_image_data', $fields)) && !empty($this->categories_image_data)) {
            $data['categories_image_data'] = base64_encode($this->categories_image_data);
        }
        if ((count($fields) == 0 || array_key_exists('categories_image_2_data', $fields)) && !empty($this->categories_image_2_data)) {
            $data['categories_image_2_data'] = base64_encode($this->categories_image_2_data);
        }
        if ((count($fields) == 0 || array_key_exists('categories_image_3_data', $fields)) && !empty($this->categories_image_3_data)) {
            $data['categories_image_3_data'] = base64_encode($this->categories_image_3_data);
        }
        if ((count($fields) == 0 || array_key_exists('categories_image_source_url', $fields)) && !is_null($this->categories_image_source_url)) {
            $data['categories_image_source_url'] = $this->categories_image_source_url;
        }
        if ((count($fields) == 0 || array_key_exists('categories_image_2_source_url', $fields)) && !is_null($this->categories_image_2_source_url)) {
            $data['categories_image_2_source_url'] = $this->categories_image_2_source_url;
        }
        if ((count($fields) == 0 || array_key_exists('categories_image_3_source_url', $fields)) && !is_null($this->categories_image_3_source_url)) {
            $data['categories_image_3_source_url'] = $this->categories_image_3_source_url;
        }
        return $data;
    }
    public function import_array($data)
    {
        $result = parent::import_array($data);
        if (isset($data['categories_image_data']) && !empty($data['categories_image_data'])) {
            $this->categories_image_data = base64_decode($data['categories_image_data']);
        } elseif (array_key_exists('categories_image_source_url', $data) && !empty($data['categories_image_source_url'])) {
            $this->categories_image_source_url = $data['categories_image_source_url'];
        }
        if (isset($data['categories_image_2_data']) && !empty($data['categories_image_2_data'])) {
            $this->categories_image_2_data = base64_decode($data['categories_image_2_data']);
        } elseif (array_key_exists('categories_image_2_source_url', $data) && !empty($data['categories_image_2_source_url'])) {
            $this->categories_image_2_source_url = $data['categories_image_2_source_url'];
        }
        if (isset($data['categories_image_3_data']) && !empty($data['categories_image_3_data'])) {
            $this->categories_image_3_data = base64_decode($data['categories_image_3_data']);
        } elseif (array_key_exists('categories_image_3_source_url', $data) && !empty($data['categories_image_3_source_url'])) {
            $this->categories_image_3_source_url = $data['categories_image_3_source_url'];
        }
        if (isset($data['AutoStatus'])) {
            $this->auto_status = $data['AutoStatus'];
        }
        return $result;
    }
    public function before_save($insert)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('AutomaticallyStatus', 'allowed') && isset($this->auto_status)) {
            unset($this->categories_status);
        }
        $target_dir = \common\classes\Images::get_fs_catalog_images_path();
        if (!empty($this->categories_image_source_url) || !empty($this->categories_image_data)) {
            $target_filename = !empty($this->categories_image) ? $this->categories_image : basename($this->categories_image_source_url);
            if (!empty($this->categories_image_source_url)) {
                if (!is_dir(dirname($target_dir . $target_filename))) {
                    try {
                        File_Helper::create_directory(dirname($target_dir . $target_filename), 0777);
                    } catch (\Exception $ex) {
                    }
                }
                @copy($this->categories_image_source_url, $target_dir . $target_filename);
            } elseif (!empty($this->categories_image_data) && !empty($target_filename)) {
                @file_put_contents($target_dir . $target_filename, $this->categories_image_data);
                unset($this->categories_image_data);
            }
        }
        if (!empty($this->categories_image_2_source_url) || !empty($this->categories_image_2_data)) {
            $target_filename = !empty($this->categories_image_2) ? $this->categories_image_2 : basename($this->categories_image_2_source_url);
            if (!empty($this->categories_image_2_source_url)) {
                if (!is_dir(dirname($target_dir . $target_filename))) {
                    try {
                        File_Helper::create_directory(dirname($target_dir . $target_filename), 0777);
                    } catch (\Exception $ex) {
                    }
                }
                @copy($this->categories_image_2_source_url, $target_dir . $target_filename);
            } elseif (!empty($this->categories_image_2_data) && !empty($target_filename)) {
                @file_put_contents($target_dir . $target_filename, $this->categories_image_2_data);
                unset($this->categories_image_2_data);
            }
        }
        if (!empty($this->categories_image_3_source_url) || !empty($this->categories_image_3_data)) {
            $target_filename = !empty($this->categories_image_3) ? $this->categories_image_3 : basename($this->categories_image_3_source_url);
            if (!empty($this->categories_image_3_source_url)) {
                if (!is_dir(dirname($target_dir . $target_filename))) {
                    try {
                        File_Helper::create_directory(dirname($target_dir . $target_filename), 0777);
                    } catch (\Exception $ex) {
                    }
                }
                @copy($this->categories_image_3_source_url, $target_dir . $target_filename);
            } elseif (!empty($this->categories_image_3_data) && !empty($target_filename)) {
                @file_put_contents($target_dir . $target_filename, $this->categories_image_3_data);
                unset($this->categories_image_3_data);
            }
        }
        if ($insert) {
            if (is_null($this->categories_status)) {
                $this->categories_status = 0;
            }
            // override default from table schema
            if (empty($this->date_added)) {
                $this->date_added = new Expression('NOW()');
            }
        } else if ($this->is_modified()) {
            $this->last_modified = new Expression('NOW()');
        }
        $this->changed_name = false;
        $default_key = \common\classes\language::get_code(\common\classes\language::default_id()) . '_0';
        if (is_array($this->child_collections['descriptions']) && isset($this->child_collections['descriptions'][$default_key]) && is_object($this->child_collections['descriptions'][$default_key])) {
            $default_description = $this->child_collections['descriptions'][$default_key];
            /**
             * @var EPMap $defaultDescription
             */
            if ($default_description->is_attribute_changed('categories_name', false)) {
                $this->changed_name = true;
            }
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (array_key_exists('sort_order', $changed_attributes) || $this->changed_name) {
            \common\helpers\Categories::update_categories();
        }
        if (isset($this->auto_status) && $ext = \common\helpers\Acl::check_extension_allowed('AutomaticallyStatus', 'allowed')) {
            $ext::set_auto_status_category($this->categories_id, $this->auto_status, true);
            unset($this->auto_status);
        }
        if ($insert && !is_array($this->child_collections['assigned_customer_groups'] ?? null)) {
            /** @var \common\extensions\UserGroupsRestrictions\UserGroupsRestrictions $ext */
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'allowed')) {
                if ($group_service = $ext::get_groups_service()) {
                    $group_service->add_category_to_all_groups($this->categories_id);
                }
            }
        }
    }
}