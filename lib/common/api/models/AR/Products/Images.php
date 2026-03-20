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
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
use common\api\models\AR\Products\Images\Description as ImageDescription;
use common\api\models\AR\Products\Images\External_Url;
use common\models\Products_Images_Attributes;
class Images extends Ep_Map
{
    protected $hide_fields = ['products_images_id', 'products_id'];
    protected $child_collections = ['image_description' => []];
    protected $assign_to_attributes;
    protected $child_external_urls;
    /**
     * @var EPMap
     */
    protected $parent_object;
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->after_save_hooks['Image::normalize'] = 'normalizeImageFiles';
    }
    public static function table_name()
    {
        return TABLE_PRODUCTS_IMAGES;
    }
    public static function primary_key()
    {
        return ['products_images_id'];
    }
    public function refresh()
    {
        $this->child_external_urls = false;
        return parent::refresh();
    }
    public function get_assoc_external_urls()
    {
        if (!is_array($this->child_external_urls)) {
            $this->child_external_urls = [];
            if ($this->products_images_id) {
                foreach (External_Url::find()->where(['products_images_id' => $this->products_images_id])->order_by(['image_types_id' => SORT_ASC])->all() as $obj) {
                    if ($obj->language_id == 0) {
                        $key_code = '00';
                    } else {
                        $key_code = \common\classes\language::get_code($obj->language_id, true);
                        if (!$key_code) {
                            continue;
                        }
                    }
                    if (!isset($this->child_external_urls[$key_code])) {
                        $this->child_external_urls[$key_code] = [];
                    }
                    $this->child_external_urls[$key_code][] = $obj;
                }
            }
        }
        return $this->child_external_urls;
    }
    public function init_collection_by_lookup_key_image_description($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        if (true) {
            if (!is_null($this->products_images_id)) {
                $db_map_collect = [];
                foreach (Image_Description::find_all(['products_images_id' => $this->products_images_id]) as $obj) {
                    if ($obj->language_id == 0) {
                        $code = '00';
                    } else {
                        $code = \common\classes\language::get_code($obj->language_id, true);
                        if ($code == false) {
                            continue;
                        }
                    }
                    $db_map_collect[$code] = $obj;
                }
                foreach (Image_Description::get_all_key_codes() as $key_code => $lookup_pk) {
                    if ($load_all || in_array($key_code, $lookup_keys)) {
                        if (isset($db_map_collect[$key_code])) {
                            $this->child_collections['image_description'][$key_code] = $db_map_collect[$key_code];
                        } else {
                            $lookup_pk['products_images_id'] = (int) $this->products_images_id;
                            $this->child_collections['image_description'][$key_code] = new Image_Description($lookup_pk);
                        }
                        $this->child_collections['image_description'][$key_code]->parent_ep_map($this);
                    }
                }
            } else {
                foreach (Image_Description::get_all_key_codes() as $key_code => $lookup_pk) {
                    $this->child_collections['image_description'][$key_code] = new Image_Description($lookup_pk);
                    $this->child_collections['image_description'][$key_code]->parent_ep_map($this);
                }
            }
        } else {
            foreach (Image_Description::get_all_key_codes() as $key_code => $lookup_pk) {
                if (is_object($this->child_collections['image_description'][$key_code])) {
                    continue;
                }
                $this->child_collections['image_description'][$key_code] = null;
                if (is_null($this->products_images_id)) {
                    $this->child_collections['image_description'][$key_code] = new Image_Description($lookup_pk);
                    $this->child_collections['image_description'][$key_code]->parent_ep_map($this);
                } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                    if (!is_object($this->child_collections['image_description'][$key_code])) {
                        $lookup_pk['products_images_id'] = $this->products_images_id;
                        $this->child_collections['image_description'][$key_code] = Image_Description::find_one($lookup_pk);
                        if (!is_object($this->child_collections['image_description'][$key_code])) {
                            $this->child_collections['image_description'][$key_code] = new Image_Description($lookup_pk);
                        }
                        $this->child_collections['image_description'][$key_code]->parent_ep_map($this);
                    }
                }
            }
        }
        return $this->child_collections['image_description'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        $this->parent_object = $parent_object;
    }
    public function get_image_hashes()
    {
        if (count($this->child_collections['image_description']) == 0) {
            $this->init_collection_by_lookup_key_image_description(['*']);
        }
        $hashes = [];
        foreach ($this->child_collections['image_description'] as $key => $image_desc) {
            $hashes[$key] = $image_desc->hash_file_name;
        }
        return $hashes;
    }
    public function get_image_compare_keys()
    {
        if (count($this->child_collections['image_description']) == 0) {
            $this->init_collection_by_lookup_key_image_description(['*']);
        }
        $hashes = [];
        foreach ($this->child_collections['image_description'] as $key => $image_desc) {
            $hashes[$key] = ['products_images_id' => $image_desc->products_images_id, 'language_id' => $image_desc->language_id, 'hash' => $image_desc->hash_file_name, 'orig_name' => $image_desc->orig_file_name];
        }
        return $hashes;
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (isset($imported_object->products_images_id) && intval($imported_object->products_images_id) > 0) {
            if (intval($imported_object->products_images_id) == intval($this->products_images_id)) {
                $this->pending_removal = false;
                return true;
            }
            return false;
        }
        $k1_match = 0;
        $k2_match = 0;
        $this_keys = $this->get_image_compare_keys();
        $imported_keys = $imported_object->get_image_compare_keys();
        foreach ($this_keys as $key => $compare_values) {
            if (!empty($compare_values['hash']) && isset($imported_keys[$key]['hash']) && $compare_values['hash'] == $imported_keys[$key]['hash']) {
                $k1_match++;
            }
            if (!empty($compare_values['orig_name']) && isset($imported_keys[$key]['orig_name']) && $compare_values['orig_name'] == $imported_keys[$key]['orig_name']) {
                $k2_match++;
            }
        }
        if ($k1_match > 0 || $k2_match > 0) {
            $this->pending_removal = false;
            return true;
        }
        return false;
        /*
        $match_images = 0;
        $this_hashes = $this->getImageHashes();
        $imported_hashes = $importedObject->getImageHashes();
        foreach ( $this_hashes as $key=>$hash ) {
            if ( !empty($hash) && isset($imported_hashes[$key]) && $hash==$imported_hashes[$key] ) {
                $match_images++;
            }
        }
        if ( $match_images>0 ) {
            $this->pendingRemoval = false;
            return true;
        }
        return false;
        */
    }
    public function import_array($data)
    {
        //if ( count($this->childCollections['image_description'])==0 ) {
        //    $this->initCollectionByLookupKey_ImageDescription([]);
        //}
        if (isset($data['products_images_id']) && (int) $data['products_images_id'] > 0) {
            $data['products_images_id'] = (int) $data['products_images_id'];
        }
        $result = parent::import_array($data);
        if (isset($data['assign_to_attributes']) && is_array($data['assign_to_attributes'])) {
            $this->assign_to_attributes = $data['assign_to_attributes'];
        }
        return $result;
    }
    public function before_delete()
    {
        if (count($this->child_collections['image_description']) == 0) {
            $this->init_collection_by_lookup_key_image_description(['*']);
        }
        foreach ($this->child_collections['image_description'] as $image_description) {
            $image_description->delete();
        }
        \common\classes\Images::remove_product_image($this->products_id, $this->products_images_id);
        return parent::before_delete();
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (is_array($this->assign_to_attributes) && count($this->assign_to_attributes) > 0) {
            foreach ($this->assign_to_attributes as $option_id => $values_ids) {
                if (!is_array($values_ids)) {
                    continue;
                }
                $values_ids = array_flip($values_ids);
                foreach (Products_Images_Attributes::find()->where(['AND', ['products_images_id' => $this->products_images_id], ['products_options_id' => $option_id]])->all() as $existing_assign) {
                    if (!isset($values_ids[$existing_assign->products_options_values_id])) {
                        $existing_assign->delete();
                    } else {
                        unset($values_ids[$existing_assign->products_options_values_id]);
                    }
                }
                foreach ($values_ids as $missing_value_id) {
                    $missing_map = new Products_Images_Attributes(['products_images_id' => $this->products_images_id, 'products_options_id' => $option_id, 'products_options_values_id' => $missing_value_id]);
                    $missing_map->load_default_values();
                    $missing_map->save(false);
                }
            }
        }
        // assign_to_attributes
    }
    protected function normalize_image_files()
    {
        \common\classes\Images::normalize_image_files($this->products_id, $this->products_images_id);
    }
}