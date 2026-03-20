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
use yii\helpers\Array_Helper;
class Assigned_Categories extends Ep_Map
{
    protected $hide_fields = ['products_id'];
    public static function table_name()
    {
        return TABLE_PRODUCTS_TO_CATEGORIES;
    }
    public static function primary_key()
    {
        return ['products_id', 'categories_id'];
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (count($fields) == 0 || in_array('categories_path', $fields) || in_array('categories_path_array', $fields)) {
            $categories_arr = \common\helpers\Categories::generate_category_path($this->categories_id);
            if (count($fields) == 0 || in_array('categories_path', $fields)) {
                $data['categories_path'] = implode(';', Array_Helper::get_column($categories_arr[0], 'text'));
            }
            if (count($fields) == 0 || in_array('categories_path_array', $fields)) {
                $data['categories_path_array'] = $categories_arr[0];
            }
        }
        return $data;
    }
    public function import_array($data)
    {
        if (isset($data['categories_path'])) {
            $tools = new \backend\models\EP\Tools();
            $data['categories_id'] = $tools->tep_get_categories_by_name($data['categories_path']);
        }
        return parent::import_array($data);
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->categories_id) && !is_null($this->categories_id) && $imported_object->categories_id == $this->categories_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
}