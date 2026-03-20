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
class External_Url extends Ep_Map
{
    protected $hide_fields = ['products_images_id', 'language_id', 'image_types_id'];
    protected $parent_object;
    public static function table_name()
    {
        return TABLE_PRODUCTS_IMAGES_EXTERNAL_URL;
    }
    public static function primary_key()
    {
        return ['products_images_id', 'language_id', 'image_types_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_images_id = $parent_object->products_images_id;
        $this->language_id = $parent_object->language_id;
        $this->parent_object = $parent_object;
    }
    public function export_array(array $fields = [])
    {
        static $type_id_to_name = false;
        if (!is_array($type_id_to_name)) {
            $type_id_to_name = [];
            foreach (Images::get_image_types() as $image_type) {
                $type_id_to_name[$image_type['image_types_id']] = $image_type['image_types_name'];
            }
        }
        $data = parent::export_array($fields);
        $data['image_types_name'] = $type_id_to_name[$this->image_types_id];
        return $data;
    }
    public function import_array($data)
    {
        if (isset($data['image_types_name'])) {
            $type_array = Images::get_image_types($data['image_types_name']);
            if (!is_array($type_array)) {
                return false;
            }
            $data['image_types_id'] = $type_array['image_types_id'];
        } else {
            return false;
        }
        $result = parent::import_array($data);
        return $result;
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->image_types_id) && !is_null($this->image_types_id) && $imported_object->image_types_id == $this->image_types_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
}