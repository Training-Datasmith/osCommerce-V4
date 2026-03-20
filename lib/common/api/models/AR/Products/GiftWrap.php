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
class Gift_Wrap extends Ep_Map
{
    protected $hide_fields = ['gw_id', 'products_id'];
    /**
     * @var EPMap
     */
    protected $parent_object;
    public static function table_name()
    {
        return 'gift_wrap_products';
    }
    public static function primary_key()
    {
        return ['gw_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        $this->parent_object = $parent_object;
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (isset($imported_object->gw_id) && intval($imported_object->gw_id) > 0) {
            if (intval($imported_object->gw_id) == intval($this->gw_id)) {
                $this->pending_removal = false;
                return true;
            }
            return false;
        }
        if (!is_null($imported_object->groups_id) && !is_null($this->groups_id) && $imported_object->groups_id == $this->groups_id && !is_null($imported_object->currencies_id) && !is_null($this->currencies_id) && $imported_object->currencies_id == $this->currencies_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
}