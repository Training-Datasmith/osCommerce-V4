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
use common\helpers\Price_Formula;
use common\models\Suppliers;
use yii\db\Expression;
class Suppliers_Data extends Ep_Map
{
    public $suppliers_name;
    protected $hide_fields = ['products_id'];
    protected $parent_object;
    public static function primary_key()
    {
        return ['products_id', 'uprid', 'suppliers_id'];
    }
    public static function table_name()
    {
        return 'suppliers_products';
    }
    public function custom_fields()
    {
        $fields = parent::custom_fields();
        $fields[] = 'suppliers_name';
        return $fields;
    }
    public function is_modified()
    {
        // prevent product modify
        return false;
        //return parent::isModified();
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        if (isset($parent_object->uprid)) {
            $this->uprid = $parent_object->uprid;
        } else {
            $this->uprid = $this->products_id;
        }
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        $object_match = $imported_object->products_id == $this->products_id && $imported_object->uprid == $this->uprid && $imported_object->suppliers_id == $this->suppliers_id;
        if ($object_match) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (count($fields) == 0 || array_key_exists('suppliers_name', $fields)) {
            static $fetched = [];
            if (!isset($fetched[$this->suppliers_id])) {
                $fetched[$this->suppliers_id] = '';
                $supplier_name = Suppliers::find()->select('suppliers_name')->where(['suppliers_id' => $this->suppliers_id])->as_array(true)->one();
                if (is_array($supplier_name)) {
                    $fetched[$this->suppliers_id] = $supplier_name['suppliers_name'];
                }
            }
            $this->suppliers_name = $fetched[$this->suppliers_id];
            $data['suppliers_name'] = $this->suppliers_name;
        }
        return $data;
    }
    public function before_save($insert)
    {
        if ($insert) {
            if (empty($this->date_added)) {
                $this->date_added = new Expression('NOW()');
            }
            if (is_null($this->suppliers_surcharge_amount) || is_null($this->suppliers_margin_percentage)) {
                $supplier_data = Tools::get_instance()->supplier_data($this->suppliers_id);
                if (is_array($supplier_data)) {
                    if (is_null($this->suppliers_surcharge_amount) && $supplier_data['suppliers_surcharge_amount']) {
                        $this->suppliers_surcharge_amount = $supplier_data['suppliers_surcharge_amount'];
                    }
                    if (is_null($this->suppliers_margin_percentage) && $supplier_data['suppliers_margin_percentage']) {
                        $this->suppliers_margin_percentage = $supplier_data['suppliers_margin_percentage'];
                    }
                }
            }
        } else if ($this->is_modified()) {
            $this->last_modified = new Expression('NOW()');
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (count($changed_attributes) > 0) {
            Price_Formula::apply_db($this->products_id);
        }
    }
}