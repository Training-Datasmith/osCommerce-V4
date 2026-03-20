<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
class Set_Products extends Ep_Map
{
    protected $hide_fields = [];
    protected $parent_object;
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }
    public static function table_name()
    {
        return TABLE_SETS_PRODUCTS;
    }
    public static function primary_key()
    {
        return ['product_id', 'sets_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->sets_id = $parent_object->products_id;
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
}