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

use backend\models\EP\Tools;
use common\api\models\AR\Ep_Map;
use yii;
class Warehouses_Products extends Ep_Map
{
    protected $hide_fields = [];
    /**
     * @var EPMap
     */
    protected $parent_object;
    protected $qty_delta = null;
    public function __construct(array $config = [])
    {
        if (isset($config['keyCode'])) {
            $params = explode('_', $config['keyCode']);
            if ($params[0] ?? null) {
                $this->warehouse_id = (int) $params[0];
            }
            if ($params[1] ?? null) {
                $this->suppliers_id = (int) $params[1];
            }
            if ($params[2] ?? null) {
                $this->location_id = (int) $params[2];
            }
        }
        parent::__construct($config);
    }
    public static function table_name()
    {
        return 'warehouses_products';
    }
    public static function primary_key()
    {
        return ['products_id', 'warehouse_id', 'suppliers_id', 'location_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        $this->prid = intval($parent_object->products_id);
        /*if (is_subclass_of($parentObject, 'common\api\models\AR\Products\Inventory')){
            $this->prid = $parentObject->prid;
          } else {
          }*/
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
    public static function get_all_key_codes()
    {
        static $supplier_ids = false;
        if ($supplier_ids === false) {
            $supplier_ids = yii\helpers\Array_Helper::map(\common\models\Suppliers::find()->select('suppliers_id')->order_by(['suppliers_id' => SORT_ASC])->all(), 'suppliers_id', 'suppliers_id');
        }
        static $key_codes;
        if (!is_array($key_codes)) {
            $key_codes = [];
            foreach ($supplier_ids as $suppliers_id) {
                foreach (\common\helpers\Warehouses::get_warehouses(true) as $warehouse) {
                    $key_code = (int) $warehouse['id'] . '_' . $suppliers_id;
                    $key_codes[$key_code] = ['warehouse_id' => (int) $warehouse['id'], 'products_id' => null, 'suppliers_id' => $suppliers_id, 'location_id' => 0];
                    foreach (Tools::get_instance()->get_warehouse_locations($warehouse['id']) as $warehouse_location) {
                        $key_codes[$key_code . '_' . $warehouse_location['location_id']] = ['warehouse_id' => (int) $warehouse['id'], 'products_id' => null, 'suppliers_id' => $suppliers_id, 'location_id' => (int) $warehouse_location['location_id']];
                    }
                }
            }
        }
        return $key_codes;
    }
    public function is_modified()
    {
        return false;
        // no last modify last date update
    }
    public function get_key_code()
    {
        $key_code = (int) $this->warehouse_id . '_' . (int) $this->suppliers_id;
        if (!empty($this->location_id)) {
            $key_code .= '_' . (int) $this->location_id;
        }
        return $key_code;
    }
    public function before_save($insert)
    {
        if (!parent::before_save($insert)) {
            return false;
        }
        $recalc_dirty = $this->get_dirty_attributes(['products_quantity', 'warehouse_stock_quantity', 'allocated_stock_quantity', 'temporary_stock_quantity']);
        $this->qty_delta = intval($this->get_attribute('warehouse_stock_quantity')) - intval($this->get_old_attribute('warehouse_stock_quantity'));
        if (count($recalc_dirty) > 0) {
            // reset dynamical attributes
            foreach (array_keys($recalc_dirty) as $reset_key) {
                $this->set_attribute($reset_key, $insert ? 0 : $this->get_old_attribute($reset_key));
            }
        }
        /*
                if ( count($recalcDirty)>0 || $insert) {
                    $this->setAttribute('products_quantity', intval($this->warehouse_stock_quantity) - intval($this->allocated_stock_quantity) - intval($this->temporary_stock_quantity) );
                    if ( isset($recalcDirty['warehouse_stock_quantity']) ) {
                        $qtyDelta = intval($this->getAttribute('warehouse_stock_quantity')) - intval($this->getOldAttribute('warehouse_stock_quantity'));
                        if ( $qtyDelta!=0 ) {
                            \common\helpers\Product::log_stock_history_before_update($this->products_id, abs($qtyDelta), $qtyDelta<0?'-':'+', [
                                'warehouse_id' => $this->warehouse_id,
                                'suppliers_id' => $this->suppliers_id,
                                'comments' => 'Automatically stock update',
                                'admin_id' => 0
                            ]);
                        }
                    }
                }*/
        return true;
    }
    public function after_delete()
    {
        parent::after_delete();
        if ($this->parent_object) {
            $this->parent_object->initiate_after_save('Product::doCache');
        }
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if ($this->qty_delta != 0) {
            $qty_delta = $this->qty_delta;
            \common\helpers\Warehouses::update_products_quantity($this->products_id, $this->warehouse_id, abs($qty_delta), $qty_delta > 0 ? '+' : '-', $this->suppliers_id, $this->location_id, ['comments' => 'Automatically stock update', 'admin_id' => 0]);
            if (is_object($this->parent_object)) {
                $this->parent_object->initiate_after_save('Product::doCache');
            }
        }
        //\common\helpers\Warehouses::update_products_quantity($this->products_id, $this->warehouse_id,0,'+',$this->suppliers_id);
    }
}