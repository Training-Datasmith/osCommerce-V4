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

use backend\models\EP\Tools;
use common\api\models\AR\Ep_Map;
class Assigned_Platforms extends Ep_Map
{
    protected $hide_fields = ['products_id'];
    public static function table_name()
    {
        return TABLE_PLATFORMS_PRODUCTS;
    }
    public static function primary_key()
    {
        return ['products_id', 'platform_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->platform_id) && !is_null($this->platform_id) && $imported_object->platform_id == $this->platform_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        $data['platform_name'] = Tools::get_instance()->get_platform_name($this->platform_id);
        return $data;
    }
    public function import_array($data)
    {
        if (isset($data['platform_name'])) {
            $data['platform_id'] = Tools::get_instance()->get_platform_id($data['platform_name']);
        }
        return parent::import_array($data);
    }
}