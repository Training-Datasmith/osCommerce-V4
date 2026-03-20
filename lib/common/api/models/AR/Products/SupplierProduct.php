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
class Supplier_Product extends Ep_Map
{
    protected $hide_fields = [];
    protected $parent_object;
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }
    public static function table_name()
    {
        return 'suppliers_products';
    }
    public static function primary_key()
    {
        return ['products_id', 'uprid', 'suppliers_id'];
    }
    public function before_save($insert)
    {
        if (is_null($this->status)) {
            $this->status = 1;
        }
        return parent::before_save($insert);
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        //        if (is_subclass_of($parentObject, 'backend\models\EP\Provider\Inventory')){
        if (is_subclass_of($parent_object, 'common\extensions\Inventory\EP\Providers\Inventory')) {
            $this->uprid = $parent_object->products_id;
        } else {
            $this->uprid = $parent_object->products_id;
        }
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
}