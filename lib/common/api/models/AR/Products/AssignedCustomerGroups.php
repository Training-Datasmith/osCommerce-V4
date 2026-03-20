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
class Assigned_Customer_Groups extends Ep_Map
{
    protected $hide_fields = ['products_id'];
    public static function table_name()
    {
        return 'groups_products';
    }
    public static function primary_key()
    {
        return ['products_id', 'groups_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->groups_id) && !is_null($this->groups_id) && $imported_object->groups_id == $this->groups_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        $data['groups_name'] = Tools::get_instance()->get_customer_group_name($this->groups_id);
        return $data;
    }
    public function import_array($data)
    {
        if (isset($data['groups_name'])) {
            $data['groups_id'] = Tools::get_instance()->get_customer_group_id($data['groups_name']);
        }
        return parent::import_array($data);
    }
}