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

use common\api\models\AR\Manufacturer\Info;
use yii\db\Expression;
use yii\helpers\File_Helper;
class Manufacturer extends Ep_Map
{
    protected $child_collections = ['infos' => []];
    public $manufacturers_image_data = '';
    public $manufacturers_image_source_url = '';
    public $manufacturers_image_after_save = false;
    public static function table_name()
    {
        return TABLE_MANUFACTURERS;
    }
    public static function primary_key()
    {
        return ['manufacturers_id'];
    }
    public function custom_fields()
    {
        $fields = parent::custom_fields();
        $fields[] = 'manufacturers_image_data';
        $fields[] = 'manufacturers_image_source_url';
        return $fields;
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function get_infos()
    {
        return $this->has_many(Info::class_name(), ['manufacturers_id' => 'manufacturers_id']);
    }
    // SeoRedirectsNamed moved to extensions/SeoRedirectsNamed/models/
    //    public function getSeoRedirectsNamed()
    //    {
    //        return $this->hasMany(\common\models\SeoRedirectsNamed::className(), ['owner_id' => 'manufacturers_id'])->andWhere(['redirects_type'=>'brand']);
    //    }
    public function get_possible_keys()
    {
        $possible_keys = parent::get_possible_keys();
        $nested_collect_object = new Info();
        $info_keys = $nested_collect_object->get_possible_keys();
        foreach (Info::get_all_key_codes() as $key_code => $lookup_pk) {
            foreach ($info_keys as $info_key) {
                $possible_keys[] = 'infos.' . $key_code . '.' . $info_key;
            }
        }
        return $possible_keys;
    }
    public function init_collection_by_lookup_key_infos($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        foreach (Info::get_all_key_codes() as $key_code => $lookup_pk) {
            $this->child_collections['infos'][$key_code] = null;
            if (is_null($this->manufacturers_id)) {
                $this->child_collections['infos'][$key_code] = new Info($lookup_pk);
            } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                if (!isset($this->child_collections['infos'][$key_code])) {
                    $lookup_pk['manufacturers_id'] = $this->manufacturers_id;
                    $this->child_collections['infos'][$key_code] = Info::find_one($lookup_pk);
                    if (!is_object($this->child_collections['infos'][$key_code])) {
                        $this->child_collections['infos'][$key_code] = new Info($lookup_pk);
                    }
                }
            }
        }
        return $this->child_collections['infos'];
    }
    public function export_array(array $fields = [])
    {
        if (!empty($this->manufacturers_image) && is_file(\common\classes\Images::get_fs_catalog_images_path() . $this->manufacturers_image)) {
            if (count($fields) == 0 || array_key_exists('manufacturers_image_data', $fields)) {
                //$this->manufacturers_image_data = file_get_contents(\common\classes\Images::getFSCatalogImagesPath().$this->manufacturers_image);
            }
            if (count($fields) == 0 || array_key_exists('manufacturers_image_source_url', $fields)) {
                $this->manufacturers_image_source_url = \Yii::$app->get('platform')->config()->get_catalog_base_url() . DIR_WS_IMAGES . rawurlencode($this->manufacturers_image);
            }
        }
        $data = parent::export_array($fields);
        if ((count($fields) == 0 || array_key_exists('manufacturers_image_source_url', $fields)) && !empty($this->manufacturers_image_source_url)) {
            $data['manufacturers_image_source_url'] = $this->manufacturers_image_source_url;
        }
        if ((count($fields) == 0 || array_key_exists('manufacturers_image_data', $fields)) && !empty($this->manufacturers_image_data)) {
            $data['manufacturers_image_data'] = base64_encode($this->manufacturers_image_data);
        }
        return $data;
    }
    public function import_array($data)
    {
        $result = parent::import_array($data);
        if (isset($data['manufacturers_image_data']) && !empty($data['manufacturers_image_data'])) {
            $this->manufacturers_image_data = base64_decode($data['manufacturers_image_data']);
        } elseif (array_key_exists('manufacturers_image_source_url', $data) && !empty($data['manufacturers_image_source_url'])) {
            $this->manufacturers_image_source_url = $data['manufacturers_image_source_url'];
        }
        return $result;
    }
    public function before_save($insert)
    {
        $target_dir = \common\classes\Images::get_fs_catalog_images_path();
        if (!empty($this->manufacturers_image_source_url) || !empty($this->manufacturers_image_data)) {
            $target_filename = !empty($this->manufacturers_image) ? $this->manufacturers_image : basename($this->manufacturers_image_source_url);
            if (!empty($this->manufacturers_image_source_url)) {
                if (!is_dir(dirname($target_dir . $target_filename))) {
                    try {
                        File_Helper::create_directory(dirname($target_dir . $target_filename), 0777);
                    } catch (\Exception $ex) {
                    }
                }
                @copy($this->manufacturers_image_source_url, $target_dir . $target_filename);
            } elseif (!empty($this->manufacturers_image_data) && !empty($target_filename)) {
                @file_put_contents($target_dir . $target_filename, $this->manufacturers_image_data);
                unset($this->manufacturers_image_data);
            }
            if (empty($this->manufacturers_image)) {
                $this->manufacturers_image_after_save = [$target_dir . $target_filename, $target_dir . 'brands/%ID/gallery/' . $target_filename, 'brands/%ID/gallery/' . $target_filename];
            }
        }
        if ($insert) {
            if (empty($this->date_added)) {
                $this->date_added = new Expression('NOW()');
            }
        } else if ($this->is_modified()) {
            $this->last_modified = new Expression('NOW()');
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (is_array($this->manufacturers_image_after_save) && !empty($this->manufacturers_image_after_save)) {
            $move_from = $this->manufacturers_image_after_save[0];
            $move_to = str_replace('%ID', $this->manufacturers_id, $this->manufacturers_image_after_save[1]);
            $rel_name = str_replace('%ID', $this->manufacturers_id, $this->manufacturers_image_after_save[2]);
            if (@rename($move_from, $move_to)) {
                \common\classes\Images::create_webp($rel_name, true);
                \common\classes\Images::create_resize_images($rel_name, 'Brand gallery', true);
            }
            $this->manufacturers_image = $rel_name;
            $this->manufacturers_image_after_save = false;
            $this->save(false);
        }
    }
}