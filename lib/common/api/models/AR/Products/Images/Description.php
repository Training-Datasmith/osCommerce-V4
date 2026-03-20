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
namespace common\api\models\AR\Products\Images;

use common\api\models\AR\Ep_Map;
use common\classes\Images;
use common\classes\language;
use yii\helpers\File_Helper;
class Description extends Ep_Map
{
    protected $hide_fields = ['products_images_id', 'language_id'];
    protected $child_collections = ['external_urls' => false];
    protected $indexed_collections = ['external_urls' => 'common\api\models\AR\Products\Images\ExternalUrl'];
    public $image_data = '';
    public $image_source_url = '';
    public $image_sources = [];
    protected $parent_object;
    private $generate_image_thumbnails = false;
    public function __construct(array $config = [])
    {
        if (!defined('TABLE_PRODUCTS_IMAGES_EXTERNAL_URL')) {
            unset($this->child_collections['external_urls']);
            unset($this->indexed_collections['external_urls']);
        }
        parent::__construct($config);
    }
    public static function get_all_key_codes()
    {
        $key_codes = ['00' => ['products_images_id' => null, 'language_id' => 0]];
        foreach (\common\classes\language::get_all() as $lang) {
            $key_code = $lang['code'];
            $key_codes[$key_code] = ['products_images_id' => null, 'language_id' => $lang['id']];
        }
        return $key_codes;
    }
    public static function table_name()
    {
        return TABLE_PRODUCTS_IMAGES_DESCRIPTION;
    }
    public static function primary_key()
    {
        return ['products_images_id', 'language_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        if ((int) $parent_object->products_images_id > 0) {
            $this->products_images_id = (int) $parent_object->products_images_id;
        }
        $this->parent_object = $parent_object;
    }
    public function custom_fields()
    {
        $fields = parent::custom_fields();
        $fields[] = 'image_data';
        $fields[] = 'image_source_url';
        return $fields;
    }
    public function init_collection_by_lookup_key_external_urls($lookup_keys)
    {
        if (!is_array($this->child_collections['external_urls'])) {
            $this->child_collections['external_urls'] = [];
            if ($this->products_images_id) {
                if ($this->parent_object && method_exists($this->parent_object, 'getAssocExternalUrls')) {
                    $urls = $this->parent_object->get_assoc_external_urls();
                    if ($this->language_id == 0) {
                        $key_code = '00';
                    } else {
                        $key_code = \common\classes\language::get_code($this->language_id, true);
                    }
                    $this->child_collections['external_urls'] = isset($urls[$key_code]) ? $urls[$key_code] : [];
                } else {
                    $this->child_collections['external_urls'] = External_Url::find()->where(['products_images_id' => $this->products_images_id, 'language_id' => $this->language_id])->order_by(['image_types_id' => SORT_ASC])->all();
                }
            }
        }
        return $this->child_collections['external_urls'];
    }
    public function export_array(array $fields = [])
    {
        if (count($fields) == 0 || array_key_exists('image_data', $fields)) {
            if (!empty($this->hash_file_name)) {
                $product_image_location = Images::get_fs_catalog_images_path() . 'products/' . $this->parent_object->products_id . '/' . $this->products_images_id . '/';
                $image_filename = $product_image_location . $this->hash_file_name;
                if (is_file($image_filename)) {
                    //$this->image_data = file_get_contents($imageFilename);
                    $this->image_source_url = \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . DIR_WS_IMAGES . 'products/' . $this->parent_object->products_id . '/' . $this->products_images_id . '/' . $this->hash_file_name;
                }
                foreach (Images::get_image_types() as $image_types) {
                    $image_location = $product_image_location . $image_types['image_types_x'] . 'x' . $image_types['image_types_y'] . DIRECTORY_SEPARATOR;
                    if ($this->language_id) {
                        $image_location .= language::get_code($this->language_id) . DIRECTORY_SEPARATOR;
                    }
                    $image_name = $this->file_name;
                    if (!empty($this->alt_file_name)) {
                        $image_name = $this->alt_file_name;
                    }
                    if (file_exists($image_location . $image_name)) {
                        $partial_file_name = str_replace(Images::get_fs_catalog_images_path(), '', $image_location . $image_name);
                        $this->image_sources[] = ['size' => $image_types['image_types_name'], 'url' => \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . DIR_WS_IMAGES . $partial_file_name];
                    }
                }
            }
        }
        $data = parent::export_array($fields);
        if ((count($fields) == 0 || array_key_exists('image_data', $fields)) && !empty($this->image_data)) {
            $data['image_data'] = base64_encode($this->image_data);
        }
        if ((count($fields) == 0 || array_key_exists('image_source_url', $fields)) && !is_null($this->image_source_url)) {
            $data['image_source_url'] = $this->image_source_url;
            $data['image_sources'] = $this->image_sources;
        }
        return $data;
    }
    public function import_array($data)
    {
        $result = parent::import_array($data);
        if (isset($data['image_data']) && !empty($data['image_data'])) {
            $this->image_data = base64_decode($data['image_data']);
        } elseif (array_key_exists('image_source_url', $data) && !empty($data['image_source_url'])) {
            if (isset($this->parent_object) && is_object($this->parent_object) && $this->products_images_id && $this->hash_file_name && $data['image_source_url'] == \Yii::$app->get('platform')->config()->get_catalog_base_url(true) . DIR_WS_IMAGES . 'products/' . $this->parent_object->products_id . '/' . $this->products_images_id . '/' . $this->hash_file_name) {
                // url to this image description (external image come back)
            } else {
                $this->image_source_url = $data['image_source_url'];
            }
        }
        return $result;
    }
    public function before_save($insert)
    {
        if ($this->has_property('use_external_images') && $this->use_external_images) {
        } elseif (!empty($this->image_source_url) || !empty($this->image_data)) {
            $skip_processing = false;
            // make image local copy
            $target_dir = Images::get_fs_catalog_images_path() . 'products/' . $this->parent_object->products_id . '/' . $this->products_images_id . '/';
            if (!is_dir($target_dir)) {
                try {
                    File_Helper::create_directory($target_dir, 0777);
                } catch (\Exception $ex) {
                    \Yii::error('AR Image process error CreateDirectory: ' . $ex->get_message());
                }
            }
            $target_not_hashed = tempnam($target_dir, 'img');
            if (!empty($this->image_source_url)) {
                if (empty($this->orig_file_name)) {
                    //??
                    $this->orig_file_name = basename($this->image_source_url);
                }
                if (preg_match('/^https?:\/\//', $this->image_source_url)) {
                    if (copy(str_replace(' ', '%20', $this->image_source_url), $target_not_hashed, stream_context_create(['http' => ['protocol_version' => '1.1']]))) {
                        $skip_processing = !(is_file($target_not_hashed) && filesize($target_not_hashed) > 10);
                    } else {
                        $skip_processing = true;
                    }
                } else {
                    copy($this->image_source_url, $target_not_hashed);
                }
            } elseif (!empty($this->image_data)) {
                file_put_contents($target_not_hashed, $this->image_data);
                unset($this->image_data);
            }
            $orig_file = basename($this->image_source_url);
            if (!empty($this->orig_file_name)) {
                $orig_file = $this->orig_file_name;
            }
            // check file change
            $put_new_file = true;
            if ($skip_processing) {
                $put_new_file = false;
            } elseif (!empty($this->hash_file_name)) {
                $image_filename = Images::get_fs_catalog_images_path() . 'products/' . $this->parent_object->products_id . '/' . $this->products_images_id . '/' . $this->hash_file_name;
                if (is_file($image_filename) && (filesize($image_filename) == filesize($target_not_hashed) && md5_file($image_filename) == md5_file($target_not_hashed))) {
                    // need update file - file differ
                    $put_new_file = false;
                } elseif (!is_file($image_filename)) {
                }
            }
            if ($put_new_file) {
                if (empty($this->hash_file_name) || !preg_match('/^[\da-f]{32}$/', $this->hash_file_name)) {
                    $this->hash_file_name = md5($orig_file . '_' . date('dmYHis') . '_' . microtime(true));
                }
                $new_image_filename = Images::get_fs_catalog_images_path() . 'products/' . intval($this->parent_object->products_id) . '/' . intval($this->products_images_id) . '/' . $this->hash_file_name;
                rename($target_not_hashed, $new_image_filename);
                chmod($new_image_filename, 0666);
                $this->generate_image_thumbnails = true;
            } else if ($target_not_hashed) {
                try {
                    unlink($target_not_hashed);
                } catch (\Exception $ex) {
                }
            }
        }
        //        if ( !empty($this->image_source_url) ) {
        //            $data = @file_get_contents($this->image_source_url, false, stream_context_create(array('http'=>
        //                array(
        //                    'timeout' => 10,
        //                    'header' => array(
        //                        "User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:53.0) Gecko/20100101 Firefox/53.0\r\n".
        //                        "Accept-language: en\r\n" .
        //                        "Accept: text/javascript, text/html, application/xml, text/xml, */*\r\n"
        //                    ),
        //                )
        //            )));
        //
        //            if ( $data ) {
        //                $orig_file = basename($this->image_source_url);
        //                if ( !empty($this->orig_file_name) ) {
        //                    $orig_file = $this->orig_file_name;
        //                }
        //                if (!empty($this->hash_file_name)) {
        //                    $imageFilename = Images::getFSCatalogImagesPath() . 'products/' . $this->parentObject->products_id . '/' . $this->products_images_id . '/' . $this->hash_file_name;
        //                    if ( !is_file($imageFilename) || (is_file($imageFilename) && filesize($imageFilename)!=strlen($data)) ) {
        //                        $this->image_data = $data;
        //                        $this->hash_file_name = md5($orig_file . "_" . date('dmYHis') . "_" . microtime(true));
        //                    }
        //                }else{
        //                    $this->image_data = $data;
        //                    $this->hash_file_name = md5($orig_file . "_" . date('dmYHis') . "_" . microtime(true));
        //                }
        //            }
        //        }
        if ($this->get_dirty_attributes(['hash_file_name', 'orig_file_name', 'use_origin_image_name'])) {
            $this->file_name = basename($this->orig_file_name ?? '');
        }
        if ($insert && empty($this->file_name)) {
            if (!empty($this->alt_file_name)) {
                $this->file_name = $this->alt_file_name;
            } else if (!empty($this->orig_file_name)) {
                $this->file_name = $this->orig_file_name;
            }
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (is_object($this->parent_object) && $this->parent_object instanceof \common\api\models\AR\Products\Images) {
            if (count($changed_attributes) != 0) {
                $this->parent_object->initiate_after_save('Image::normalize');
            }
        }
    }
    public function before_delete()
    {
        if (defined('TABLE_PRODUCTS_IMAGES_EXTERNAL_URL')) {
            if (!is_array($this->child_collections['external_urls'])) {
                $this->init_collection_by_lookup_key_external_urls(['*']);
            }
            foreach ($this->child_collections['external_urls'] as $external_url) {
                $external_url->delete();
            }
        }
        return parent::before_delete();
    }
}