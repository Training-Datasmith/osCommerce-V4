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
class Xsell extends Ep_Map
{
    protected $hide_fields = ['ID', 'products_id'];
    public static function table_name()
    {
        return TABLE_PRODUCTS_XSELL;
    }
    public static function primary_key()
    {
        return ['ID'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->xsell_id) && !is_null($this->xsell_id) && $imported_object->xsell_id == $this->xsell_id && !is_null($imported_object->xsell_type_id) && !is_null($this->xsell_type_id) && $imported_object->xsell_type_id == $this->xsell_type_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function export_array(array $fields = [])
    {
        $tools = new Tools();
        $data = parent::export_array($fields);
        $data['xsell_type_name'] = $tools->get_x_sell_type_name($this->xsell_type_id);
        return $data;
    }
    public function import_array($data)
    {
        if (isset($data['xsell_type_name'])) {
            $tools = new Tools();
            $data['xsell_type_id'] = $tools->get_x_sell_type_id($data['xsell_type_name']);
        }
        return parent::import_array($data);
    }
}