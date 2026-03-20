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
namespace common\api\models\AR\Warehouses;

use common\api\models\AR\Ep_Map;
class Info extends Ep_Map
{
    protected $hide_fields = ['warehouse_id', 'time_long', 'token'];
    protected $parent_object;
    public static function table_name()
    {
        return TABLE_WAREHOUSES_OPEN_HOURS;
    }
    public static function primary_key()
    {
        return ['warehouse_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->warehouse_id = $parent_object->warehouse_id;
        //echo '<pre>'; var_dump($this->warehouse_id); echo '</pre>';
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        $this->pending_removal = false;
        return true;
        //return parent::matchIndexedValue($importedObject);
    }
}