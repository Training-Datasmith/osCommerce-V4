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
namespace common\helpers;

class Warehouses
{
    public static function warehouse_status_change()
    {
        $active_ids = \common\models\Warehouses::find()->select(['warehouse_id'])->where(['status' => 1])->column();
        if (empty($active_ids)) {
            $active_ids[] = 0;
        }
        \Yii::$app->get_db()->create_command('UPDATE products p ' . ' LEFT JOIN (' . ' SELECT ' . '  SUM(w.products_quantity) AS products_quantity, ' . '  SUM(w.allocated_stock_quantity) AS allocated_stock_quantity, ' . '  SUM(w.warehouse_stock_quantity) AS warehouse_stock_quantity, ' . '  SUM(w.temporary_stock_quantity) AS temporary_stock_quantity, ' . '  w.prid ' . ' FROM ' . \common\models\Warehouses_Products::table_name() . ' w ' . " WHERE w.warehouse_id IN('" . implode("','", $active_ids) . "') " . ' GROUP BY w.prid ' . ') ws ON ws.prid=p.products_id ' . 'SET ' . ' p.products_quantity=IFNULL(ws.products_quantity,0), ' . ' p.allocated_stock_quantity=IFNULL(ws.allocated_stock_quantity,0), ' . ' p.temporary_stock_quantity=IFNULL(ws.temporary_stock_quantity,0), ' . ' p.warehouse_stock_quantity=IFNULL(ws.warehouse_stock_quantity,0) ')->execute();
    }
    public static function get_temporary_stock_table_name()
    {
        $freeze_prefix = '';
        if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
            $freeze_prefix = 'freeze_';
        }
        return $freeze_prefix . \common\models\Orders_Products_Temporary_Stock::table_name();
    }
    public static function get_warehouses_count($include_inactive = false)
    {
        $warehouses_query = tep_db_query('select count(*) as warehouses_count from ' . TABLE_WAREHOUSES . ' where 1 ' . ($include_inactive ? '' : " and status = '1' ") . ' limit 1');
        $warehouses = tep_db_fetch_array($warehouses_query);
        return $warehouses['warehouses_count'];
    }
    public static function get_default_warehouse()
    {
        static $_cached_warehouse_id = false;
        if ($_cached_warehouse_id === false) {
            $warehouses_query = tep_db_query('select warehouse_id from ' . TABLE_WAREHOUSES . " where is_default = '1' and status = '1' limit 1");
            $warehouses = tep_db_fetch_array($warehouses_query);
            $_cached_warehouse_id = $warehouses['warehouse_id'];
        }
        return $_cached_warehouse_id;
    }
    public static function get_warehouse_name($warehouse_id)
    {
        $warehouses_query = tep_db_query('select warehouse_name from ' . TABLE_WAREHOUSES . " where warehouse_id = '" . (int) $warehouse_id . "' limit 1");
        $warehouses = tep_db_fetch_array($warehouses_query);
        return $warehouses['warehouse_name'];
    }
    public static function get_warehouse_id($warehouse_name)
    {
        $warehouses_query = tep_db_query('select warehouse_id from ' . TABLE_WAREHOUSES . " where warehouse_name = '" . tep_db_input($warehouse_name) . "' limit 1");
        $warehouses = tep_db_fetch_array($warehouses_query);
        return $warehouses['warehouse_id'];
    }
    /**
     *
     * @staticvar array $cached
     * @param bool $include_inactive
     * @return array [id=>NN text => 'name'
     */
    public static function get_warehouses($include_inactive = false)
    {
        static $cached = [];
        if (!isset($cached[$include_inactive])) {
            $warehouses_query = tep_db_query('select warehouse_id, warehouse_name from ' . TABLE_WAREHOUSES . ' where 1 ' . ($include_inactive ? '' : " and status = '1' ") . ' order by is_default DESC, sort_order, warehouse_name');
            while ($warehouses = tep_db_fetch_array($warehouses_query)) {
                $warehouses_array[] = ['id' => $warehouses['warehouse_id'], 'text' => $warehouses['warehouse_name']];
            }
            $cached[$include_inactive] = $warehouses_array;
        }
        return $cached[$include_inactive];
    }
    public static function get_warehouse_address($warehouse_id, $languages_id = -1)
    {
        if ($languages_id < 1) {
            $languages_id = \Yii::$app->settings->get('languages_id');
        }
        $address_book_query = tep_db_query('SELECT w.warehouse_owner as owner, w.warehouse_telephone as telephone, w.warehouse_email_address as email_address, ' . ' wab.entry_company as company, wab.entry_company_vat, wab.entry_company_reg_number as reg_number, ' . ' wab.entry_street_address as street_address, entry_suburb as suburb, ' . ' wab.entry_city as city, wab.entry_postcode as postcode, ' . ' wab.entry_state as state, wab.entry_zone_id as zone_id, wab.entry_country_id as country_id, ' . ' c.countries_name as country_name, c.countries_iso_code_2 as country_iso_code_2, c.countries_iso_code_3 as country_iso_code_3, c.address_format_id ' . 'FROM ' . TABLE_WAREHOUSES . ' w, ' . TABLE_WAREHOUSES_ADDRESS_BOOK . ' wab ' . ' LEFT JOIN ' . TABLE_COUNTRIES . " c ON c.countries_id = wab.entry_country_id and c.language_id = '" . (int) $languages_id . "' " . "WHERE w.warehouse_id = wab.warehouse_id and wab.is_default = '1' and w.warehouse_id = '" . (int) $warehouse_id . "' ");
        $address_book = tep_db_fetch_array($address_book_query);
        $name_parts = explode(' ', $address_book['owner']);
        $address_book['lastname'] = array_pop($name_parts);
        $address_book['firstname'] = implode(' ', $name_parts);
        return $address_book;
    }
    public static function update_products_quantity($products_id, $warehouse_id, $qty, $qty_prefix, $suppliers_id = 0, $location_id = 0, $parameters = [])
    {
        /**
         * NOTE!!! function return overall warehouse qty (?!)
         */
        $is_error = false;
        $qty = (int) $qty;
        $qty = $qty_prefix == '-' ? $qty < 0 ? abs($qty) : -$qty : $qty;
        if ($suppliers_id <= 0) {
            $supplier = \common\helpers\Suppliers::get_suppliers_list($products_id);
            if (count($supplier) > 0) {
                $suppliers_id = key($supplier);
            } else {
                $supplier = \common\helpers\Suppliers::get_default_supplier();
                $suppliers_id = is_object($supplier) ? $supplier->suppliers_id : 0;
            }
            unset($supplier);
        }
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        $warehouse_id = (int) $warehouse_id;
        $suppliers_id = (int) $suppliers_id;
        $location_id = (int) $location_id;
        $parameters = is_array($parameters) ? $parameters : [];
        $layers_id = (int) (isset($parameters['layers_id']) ? $parameters['layers_id'] : 0);
        $batch_id = (int) (isset($parameters['batch_id']) ? $parameters['batch_id'] : 0);
        if ($qty == 0) {
            $is_error = true;
        }
        $product_record = \common\helpers\Product::get_record($products_id);
        if (count(\common\helpers\Product::get_child_array($product_record)) > 0) {
            return (int) $product_record->warehouse_stock_quantity;
        }
        if ($is_error == false and strpos($products_id, '{') !== false) {
            $warehouse_product_inventory_record = \common\models\Warehouses_Products::find()->where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $suppliers_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layers_id])->and_where(['batch_id' => $batch_id])->and_where(['products_id' => $products_id])->and_where(['prid' => (int) $products_id])->one();
            if (!$warehouse_product_inventory_record instanceof \common\models\Warehouses_Products) {
                $warehouse_product_inventory_record = new \common\models\Warehouses_Products();
                $warehouse_product_inventory_record->warehouse_id = $warehouse_id;
                $warehouse_product_inventory_record->suppliers_id = $suppliers_id;
                $warehouse_product_inventory_record->location_id = $location_id;
                $warehouse_product_inventory_record->layers_id = $layers_id;
                $warehouse_product_inventory_record->batch_id = $batch_id;
                $warehouse_product_inventory_record->products_id = $products_id;
                $warehouse_product_inventory_record->prid = (int) $products_id;
            }
            $qty = $warehouse_product_inventory_record->warehouse_stock_quantity + $qty < 0 ? -$warehouse_product_inventory_record->warehouse_stock_quantity : $qty;
            if ($qty == 0) {
                unset($warehouse_product_inventory_record);
                $is_error = true;
            } else {
                $warehouse_product_inventory_record->warehouse_stock_quantity += $qty;
                $warehouse_product_inventory_record->products_quantity += $qty;
                try {
                    $warehouse_product_inventory_record->save();
                } catch (\Exception $exc) {
                    unset($warehouse_product_inventory_record);
                    $is_error = true;
                }
                if ($is_error == false) {
                    $inventory_record = \common\helpers\Inventory::get_record($products_id);
                    if ($inventory_record instanceof \common\models\Inventory) {
                        $inventory_record->products_quantity += $qty;
                        try {
                            $inventory_record->save();
                        } catch (\Exception $exc) {
                            unset($inventory_record);
                            $is_error = true;
                        }
                    }
                }
            }
        }
        if ($is_error == false) {
            $warehouse_product_record = \common\models\Warehouses_Products::find()->where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $suppliers_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layers_id])->and_where(['batch_id' => $batch_id])->and_where(['products_id' => trim((int) $products_id)])->and_where(['prid' => (int) $products_id])->one();
            if (!$warehouse_product_record instanceof \common\models\Warehouses_Products) {
                $warehouse_product_record = new \common\models\Warehouses_Products();
                $warehouse_product_record->warehouse_id = $warehouse_id;
                $warehouse_product_record->suppliers_id = $suppliers_id;
                $warehouse_product_record->location_id = $location_id;
                $warehouse_product_record->layers_id = $layers_id;
                $warehouse_product_record->batch_id = $batch_id;
                $warehouse_product_record->products_id = (int) $products_id;
                $warehouse_product_record->prid = (int) $products_id;
            }
            $check_qty = $qty;
            $check_qty = $warehouse_product_record->warehouse_stock_quantity + $check_qty < 0 ? -$warehouse_product_record->warehouse_stock_quantity : $check_qty;
            if (isset($warehouse_product_inventory_record) and $check_qty != $qty or $check_qty == 0) {
                unset($warehouse_product_record);
                $is_error = true;
            } else {
                $qty = $check_qty;
                $warehouse_product_record->warehouse_stock_quantity += $qty;
                $warehouse_product_record->products_quantity += $qty;
                try {
                    $warehouse_product_record->save();
                } catch (\Exception $exc) {
                    unset($warehouse_product_record);
                    $is_error = true;
                }
            }
            unset($check_qty);
        }
        if ($is_error == false) {
            if ($product_record instanceof \common\models\Products) {
                $product_record->products_quantity += $qty;
                try {
                    $product_record->save();
                } catch (\Exception $exc) {
                    $is_error = true;
                }
            }
        }
        unset($product_record);
        if ($is_error == true) {
            if (isset($warehouse_product_record) and $warehouse_product_record instanceof \common\models\Warehouses_Products) {
                $warehouse_product_record->warehouse_stock_quantity -= $qty;
                $warehouse_product_record->products_quantity -= $qty;
                try {
                    $warehouse_product_record->save();
                } catch (\Exception $exc) {
                }
            }
            if (isset($inventory_record) and $inventory_record instanceof \common\models\Inventory) {
                $inventory_record->products_quantity -= $qty;
                try {
                    $inventory_record->save();
                } catch (\Exception $exc) {
                }
            }
            if (isset($warehouse_product_inventory_record) and $warehouse_product_inventory_record instanceof \common\models\Warehouses_Products) {
                $warehouse_product_inventory_record->warehouse_stock_quantity -= $qty;
                $warehouse_product_inventory_record->products_quantity -= $qty;
                try {
                    $warehouse_product_inventory_record->save();
                } catch (\Exception $exc) {
                }
            }
        }
        unset($warehouse_product_inventory_record);
        unset($warehouse_product_record);
        unset($inventory_record);
        $warehouse_stock_quantity = 0;
        $active_warehouse_ids = \yii\helpers\Array_Helper::map(static::get_warehouses(), 'id', 'id');
        foreach (self::get_product_array($products_id) as $warehouse_product_record) {
            //            if ( $warehouse_id>0 && $warehouse_id!=$warehouseProductRecord['warehouse_id'] ) continue;
            //            if ( $suppliers_id>0 && $suppliers_id!=$warehouseProductRecord['suppliers_id'] ) continue;
            if (!isset($active_warehouse_ids[$warehouse_product_record['warehouse_id']])) {
                continue;
            }
            $warehouse_stock_quantity += $warehouse_product_record['warehouse_stock_quantity'];
        }
        unset($warehouse_product_record);
        if ($is_error == false) {
            $parameters['layers_id'] = $layers_id;
            $parameters['batch_id'] = $batch_id;
            $parameters['is_temporary'] = 0;
            \common\helpers\Product::write_history($products_id, $warehouse_id, $suppliers_id, $location_id, $qty, $parameters);
            if ($ext = \common\helpers\Acl::check_extension_allowed('Ebay', 'allowed')) {
                $ext::set_update_product($products_id);
            }
        }
        unset($parameters);
        return $warehouse_stock_quantity;
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_products_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wp.products_quantity) as products_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . ' wp, ' . TABLE_WAREHOUSES . " w where wp.warehouse_id = w.warehouse_id and w.status = '1' " . ($warehouse_id > 0 ? " and w.warehouse_id = '" . (int) $warehouse_id . "' " : '') . ' ' . ($suppliers_id > 0 ? " and wp.suppliers_id = '" . (int) $suppliers_id . "' " : '') . " and wp.products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'"));
        } else {
            $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wp.products_quantity) as products_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . ' wp, ' . TABLE_WAREHOUSES . " w where wp.warehouse_id = w.warehouse_id and w.status = '1' " . ($warehouse_id > 0 ? " and w.warehouse_id = '" . (int) $warehouse_id . "' " : '') . ' ' . ($suppliers_id > 0 ? " and wp.suppliers_id = '" . (int) $suppliers_id . "' " : '') . " and wp.products_id = '" . (int) $products_id . "' and wp.prid = '" . (int) $products_id . "'"));
        }
        return $warehouses_stock['products_quantity'];
    }
    public static function get_quantity_info_data($products_id)
    {
        $quantity_data = [];
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            $products_condition = "wp.products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'";
        } else {
            $products_condition = "wp.products_id = '" . (int) $products_id . "' and wp.prid = '" . (int) $products_id . "'";
        }
        $product_stock_r = tep_db_query('SELECT wp.products_quantity, wp.warehouse_id, wp.suppliers_id, wp.location_id ' . 'FROM ' . TABLE_WAREHOUSES_PRODUCTS . ' wp ' . '  INNER JOIN ' . TABLE_WAREHOUSES . " w ON wp.warehouse_id = w.warehouse_id and w.status = '1' " . "WHERE {$products_condition}");
        if (tep_db_num_rows($product_stock_r) > 0) {
            while ($product_stock = tep_db_fetch_array($product_stock_r)) {
                $quantity_data[] = $product_stock;
            }
        }
        return $quantity_data;
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_allocated_stock_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        return 0;
        $allocated_stock = 0;
        $orders_status_array = [];
        // not Completed and not Cancelled orders
        $orders_status_query = tep_db_query('select distinct orders_status_id from ' . TABLE_ORDERS_STATUS . ' where orders_status_groups_id not in (4,5)');
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            $orders_status_array[] = $orders_status['orders_status_id'];
        }
        $warehouses_query = tep_db_query('select warehouse_id from ' . TABLE_WAREHOUSES . " where status = '1' " . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "' " : '') . ' order by sort_order, warehouse_name');
        while ($warehouses = tep_db_fetch_array($warehouses_query)) {
            $suppliers_query = tep_db_query('select suppliers_id from ' . TABLE_SUPPLIERS . " where status = '1' " . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "' " : '') . ' order by sort_order, suppliers_name');
            while ($suppliers = tep_db_fetch_array($suppliers_query)) {
                if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
                    $allocated_stock_data = tep_db_fetch_array(tep_db_query('select sum(wop.products_quantity) as allocated_stock_quantity from ' . TABLE_INVENTORY . ' i left join ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . " wop on wop.uprid = i.products_id and wop.products_id = i.prid and wop.warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and wop.suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' left join " . TABLE_ORDERS . " o on o.orders_id = wop.orders_id where i.products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' and o.stock_updated = '1' and o.orders_status in ('" . implode("','", $orders_status_array) . "') group by i.products_id"));
                    tep_db_query('update ' . TABLE_WAREHOUSES_PRODUCTS . " set allocated_stock_quantity = '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "', warehouse_stock_quantity =  products_quantity + '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "' + temporary_stock_quantity where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'");
                    $allocated_stock += $allocated_stock_data['allocated_stock_quantity'];
                } else {
                    $allocated_stock_data = tep_db_fetch_array(tep_db_query('select sum(wop.products_quantity) as allocated_stock_quantity from ' . TABLE_PRODUCTS . ' p left join ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . " wop on wop.products_id = p.products_id and wop.warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and wop.suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' left join " . TABLE_ORDERS . " o on o.orders_id = wop.orders_id where p.products_id = '" . (int) $products_id . "' and o.stock_updated = '1' and o.orders_status in ('" . implode("','", $orders_status_array) . "') group by p.products_id"));
                    tep_db_query('update ' . TABLE_WAREHOUSES_PRODUCTS . " set allocated_stock_quantity = '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "', warehouse_stock_quantity =  products_quantity + '" . (int) $allocated_stock_data['allocated_stock_quantity'] . "' + temporary_stock_quantity where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id = '" . (int) $products_id . "'");
                    $allocated_stock += $allocated_stock_data['allocated_stock_quantity'];
                }
            }
        }
        // Update products stock as summa of active warehouses stock (for now)
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            if ($warehouse_id > 0 || $suppliers_id > 0) {
                $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wp.allocated_stock_quantity) as allocated_stock_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . ' wp, ' . TABLE_WAREHOUSES . " w where wp.warehouse_id = w.warehouse_id and w.status = '1' and wp.products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'"));
                $warehouses_allocated_stock = $warehouses_stock['allocated_stock_quantity'];
            } else {
                $warehouses_allocated_stock = $allocated_stock;
            }
            tep_db_query('update ' . TABLE_INVENTORY . " set allocated_stock_quantity = '" . (int) $warehouses_allocated_stock . "', warehouse_stock_quantity =  products_quantity + '" . (int) $warehouses_allocated_stock . "' + temporary_stock_quantity where products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'");
        } else {
            if ($warehouse_id > 0 || $suppliers_id > 0) {
                $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wp.allocated_stock_quantity) as allocated_stock_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . ' wp, ' . TABLE_WAREHOUSES . " w where wp.warehouse_id = w.warehouse_id and w.status = '1' and wp.products_id = '" . (int) $products_id . "' and wp.prid = '" . (int) $products_id . "'"));
                $warehouses_allocated_stock = $warehouses_stock['allocated_stock_quantity'];
            } else {
                $warehouses_allocated_stock = $allocated_stock;
            }
            tep_db_query('update ' . TABLE_PRODUCTS . " set allocated_stock_quantity = '" . (int) $warehouses_allocated_stock . "', warehouse_stock_quantity =  products_quantity + '" . (int) $warehouses_allocated_stock . "' + temporary_stock_quantity where products_id = '" . (int) $products_id . "'");
        }
        return $allocated_stock;
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_temporary_stock_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        return 0;
        $temporary_stock = 0;
        $warehouses_query = tep_db_query('select warehouse_id from ' . TABLE_WAREHOUSES . " where status = '1' " . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "' " : '') . ' order by sort_order, warehouse_name');
        while ($warehouses = tep_db_fetch_array($warehouses_query)) {
            $suppliers_query = tep_db_query('select suppliers_id from ' . TABLE_SUPPLIERS . " where status = '1' " . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "' " : '') . ' order by sort_order, suppliers_name');
            while ($suppliers = tep_db_fetch_array($suppliers_query)) {
                if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
                    $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and if(length(normalize_id) > 0, normalize_id, products_id) = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' group by if(length(normalize_id) > 0, normalize_id, products_id)"));
                    tep_db_query('update ' . TABLE_WAREHOUSES_PRODUCTS . " set temporary_stock_quantity = '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "', warehouse_stock_quantity = products_quantity + allocated_stock_quantity + '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "' where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'");
                    $temporary_stock += $temporary_stock_data['temporary_stock_quantity'];
                } else {
                    $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and prid = '" . (int) $products_id . "' group by prid"));
                    tep_db_query('update ' . TABLE_WAREHOUSES_PRODUCTS . " set temporary_stock_quantity = '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "', warehouse_stock_quantity = products_quantity + allocated_stock_quantity + '" . (int) $temporary_stock_data['temporary_stock_quantity'] . "' where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id = '" . (int) $products_id . "'");
                    $temporary_stock += $temporary_stock_data['temporary_stock_quantity'];
                }
            }
        }
        // Update products stock as summa of active warehouses stock (for now)
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            if ($warehouse_id > 0 || $suppliers_id > 0) {
                $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wp.temporary_stock_quantity) as temporary_stock_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . ' wp, ' . TABLE_WAREHOUSES . " w where wp.warehouse_id = w.warehouse_id and w.status = '1' and wp.products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'"));
                $warehouses_temporary_stock = $warehouses_stock['temporary_stock_quantity'];
            } else {
                $warehouses_temporary_stock = $temporary_stock;
            }
            tep_db_query('update ' . TABLE_INVENTORY . " set temporary_stock_quantity = '" . (int) $warehouses_temporary_stock . "', warehouse_stock_quantity = products_quantity + allocated_stock_quantity + '" . (int) $warehouses_temporary_stock . "' where products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "'");
        } else {
            if ($warehouse_id > 0 || $suppliers_id > 0) {
                $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wp.temporary_stock_quantity) as temporary_stock_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . ' wp, ' . TABLE_WAREHOUSES . " w where wp.warehouse_id = w.warehouse_id and w.status = '1' and wp.products_id = '" . (int) $products_id . "' and wp.prid = '" . (int) $products_id . "'"));
                $warehouses_temporary_stock = $warehouses_stock['temporary_stock_quantity'];
            } else {
                $warehouses_temporary_stock = $temporary_stock;
            }
            tep_db_query('update ' . TABLE_PRODUCTS . " set temporary_stock_quantity = '" . (int) $warehouses_temporary_stock . "', warehouse_stock_quantity = products_quantity + allocated_stock_quantity + '" . (int) $warehouses_temporary_stock . "' where products_id = '" . (int) $products_id . "'");
        }
        return $temporary_stock;
    }
    public static function update_sum_of_inventory_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        $warehouses_query = tep_db_query('select warehouse_id from ' . TABLE_WAREHOUSES . " where status = '1' " . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "' " : '') . ' order by sort_order, warehouse_name');
        while ($warehouses = tep_db_fetch_array($warehouses_query)) {
            $suppliers_query = tep_db_query('select suppliers_id from ' . TABLE_SUPPLIERS . " where status = '1' " . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "' " : '') . ' order by sort_order, suppliers_name');
            while ($suppliers = tep_db_fetch_array($suppliers_query)) {
                $all_inventory_stock = tep_db_fetch_array(tep_db_query('select count(*) as inventory_count, sum(products_quantity) as products_quantity from ' . TABLE_WAREHOUSES_PRODUCTS . " where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id != '" . (int) \common\helpers\Inventory::get_prid($products_id) . "' and prid = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'"));
                if ($all_inventory_stock['inventory_count'] > 0) {
                    $check_warehouse = tep_db_fetch_array(tep_db_query('select count(*) as stock_exists from ' . TABLE_WAREHOUSES_PRODUCTS . " where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "' and prid = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'"));
                    if ($check_warehouse['stock_exists']) {
                        tep_db_query('update ' . TABLE_WAREHOUSES_PRODUCTS . " set products_quantity = '" . (int) $all_inventory_stock['products_quantity'] . "' where warehouse_id = '" . (int) $warehouses['warehouse_id'] . "' and suppliers_id = '" . (int) $suppliers['suppliers_id'] . "' and products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "' and prid = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'");
                    } else {
                        $data = tep_db_fetch_array(tep_db_query('select products_model from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'"));
                        tep_db_query('insert into ' . TABLE_WAREHOUSES_PRODUCTS . " set products_model = '" . tep_db_input($data['products_model']) . "', products_quantity = '" . (int) $all_inventory_stock['products_quantity'] . "', warehouse_id = '" . (int) $warehouses['warehouse_id'] . "', suppliers_id = '" . (int) $suppliers['suppliers_id'] . "', products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "', prid = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'");
                    }
                }
            }
        }
    }
    public static function update_customers_temporary_stock_quantity($products_id, $qty)
    {
        $original_products_id = $products_id;
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        if (defined('STOCK_LIMITED') && defined('TEMPORARY_STOCK_ENABLE') && STOCK_LIMITED == 'true' && TEMPORARY_STOCK_ENABLE == 'true') {
            $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
            $platform_warehoused_ordered = \Yii::$app->get('platform')->config()->assigned_warehouses();
            $suppliers_id = 0;
            // all suppliers
            if ($ext = \common\helpers\Extensions::is_allowed('SupplierPurchase')) {
                $suppliers_id = $ext::get_supplier_from_uprid($original_products_id);
            }
            $active_suppliers = \common\helpers\Suppliers::ordered_active_ids();
            if ($suppliers_id > 0) {
                if (in_array((int) $suppliers_id, $active_suppliers)) {
                    $active_suppliers = [(int) $suppliers_id];
                } else {
                    $active_suppliers = [];
                }
            }
            $temporary_info = static::get_customer_temporary_stock_info($products_id, $original_products_id);
            $temporary_stock_quantity = 0;
            $_per_warehouse_supplier_temporary = [];
            foreach ($temporary_info as $temporary_info_row) {
                $temporary_stock_quantity += intval($temporary_info_row['temporary_stock_quantity']);
                $whs_key = (int) $temporary_info_row['warehouse_id'] . '|' . (int) $temporary_info_row['suppliers_id'];
                if (!isset($_per_warehouse_supplier_temporary[$whs_key])) {
                    $_per_warehouse_supplier_temporary[$whs_key] = 0;
                }
                $_per_warehouse_supplier_temporary[$whs_key] += intval($temporary_info_row['temporary_stock_quantity']);
            }
            if ($qty > $temporary_stock_quantity) {
                $grouped_available_quantity = [];
                foreach (static::get_quantity_info_data($products_id) as $product_quantity_data) {
                    $whs_key = (int) $product_quantity_data['warehouse_id'] . '|' . (int) $product_quantity_data['suppliers_id'];
                    if (!isset($grouped_available_quantity[$whs_key])) {
                        $grouped_available_quantity[$whs_key] = 0;
                    }
                    $grouped_available_quantity[$whs_key] += $product_quantity_data['products_quantity'];
                }
                // increase temporary stock
                $update_qty = $qty - $temporary_stock_quantity;
                foreach ($platform_warehoused_ordered as $warehouse_id) {
                    // update warehouses by sort order ascending
                    foreach ($active_suppliers as $supplier_id) {
                        $whs_key = (int) $warehouse_id . '|' . (int) $supplier_id;
                        if ($update_qty > 0) {
                            $warehouse_temporary_stock_quantity = isset($_per_warehouse_supplier_temporary[$whs_key]) ? $_per_warehouse_supplier_temporary[$whs_key] : 0;
                            $available_products_quantity = isset($grouped_available_quantity[$whs_key]) ? $grouped_available_quantity[$whs_key] : 0;
                            if ($available_products_quantity > 0) {
                                if ($update_qty <= $available_products_quantity) {
                                    \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, $warehouse_temporary_stock_quantity + $update_qty, $warehouse_id, $supplier_id, false, $original_products_id);
                                    $update_qty = 0;
                                    break;
                                } else {
                                    \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, $warehouse_temporary_stock_quantity + $available_products_quantity, $warehouse_id, $supplier_id, false, $original_products_id);
                                    $update_qty -= $available_products_quantity;
                                }
                            }
                        } else {
                            break;
                        }
                    }
                }
                // {{           // if no available stock left - update default warehouse & supplier
                if ($update_qty > 0) {
                    $default_warehouse_id = self::get_default_warehouse();
                    $default_suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
                    $warehouse_temporary_stock_quantity = self::get_customers_temporary_stock_quantity($products_id, $default_warehouse_id, $default_suppliers_id, $original_products_id);
                    \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, $warehouse_temporary_stock_quantity + $update_qty, $default_warehouse_id, $default_suppliers_id, true, $original_products_id);
                }
                // }}
            } elseif ($qty < $temporary_stock_quantity) {
                // decrease temporary stock
                $update_qty = $temporary_stock_quantity - $qty;
                // {{           // if not available stock > 0 - update default warehouse & supplier
                $default_warehouse_id = self::get_default_warehouse();
                $default_suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
                $warehouse_temporary_not_available_quantity = self::get_customers_not_available_quantity($products_id, $default_warehouse_id, $default_suppliers_id);
                if ($warehouse_temporary_not_available_quantity > 0) {
                    $warehouse_temporary_stock_quantity = self::get_customers_temporary_stock_quantity($products_id, $default_warehouse_id, $default_suppliers_id, $original_products_id);
                    if ($warehouse_temporary_stock_quantity > 0) {
                        if ($update_qty <= $warehouse_temporary_not_available_quantity) {
                            \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, $warehouse_temporary_stock_quantity - $update_qty, $default_warehouse_id, $default_suppliers_id, true, $original_products_id);
                            $update_qty = 0;
                        } else {
                            \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, $warehouse_temporary_stock_quantity - $warehouse_temporary_not_available_quantity, $default_warehouse_id, $default_suppliers_id, true, $original_products_id);
                            $update_qty -= $warehouse_temporary_not_available_quantity;
                        }
                    }
                }
                // }}
                $temporary_info = static::get_customer_temporary_stock_info($products_id, $original_products_id);
                $_per_warehouse_supplier_temporary = [];
                foreach ($temporary_info as $temporary_info_row) {
                    $whs_key = (int) $temporary_info_row['warehouse_id'] . '|' . (int) $temporary_info_row['suppliers_id'];
                    if (!isset($_per_warehouse_supplier_temporary[$whs_key])) {
                        $_per_warehouse_supplier_temporary[$whs_key] = 0;
                    }
                    $_per_warehouse_supplier_temporary[$whs_key] += intval($temporary_info_row['temporary_stock_quantity']);
                }
                foreach (array_reverse($platform_warehoused_ordered) as $warehouse_id) {
                    foreach (array_reverse($active_suppliers) as $supplier_id) {
                        if ($update_qty > 0) {
                            $whs_key = (int) $warehouse_id . '|' . (int) $supplier_id;
                            $warehouse_temporary_stock_quantity = isset($_per_warehouse_supplier_temporary[$whs_key]) ? $_per_warehouse_supplier_temporary[$whs_key] : 0;
                            if ($warehouse_temporary_stock_quantity > 0) {
                                if ($update_qty <= $warehouse_temporary_stock_quantity) {
                                    \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, $warehouse_temporary_stock_quantity - $update_qty, $warehouse_id, $supplier_id, false, $original_products_id);
                                    $update_qty = 0;
                                    break;
                                } else {
                                    \common\helpers\Product::update_customers_temporary_stock_quantity($products_id, 0, $warehouse_id, $supplier_id, false, $original_products_id);
                                    $update_qty -= $warehouse_temporary_stock_quantity;
                                }
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
        }
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_customers_temporary_stock_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0, $original_products_id = '')
    {
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        $original_products_id = trim($original_products_id);
        //$the_session_id = tep_session_id();
        if (\Yii::$app->id == 'app-console') {
            $the_session_id = \Yii::$app->storage->get('guid');
        } else {
            $the_session_id = tep_session_id();
        }
        if (!\Yii::$app->user->is_guest) {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where (customers_id = '" . (int) \Yii::$app->user->get_id() . "' or (customers_id = '0' and session_id = '" . tep_db_input($the_session_id) . "'))" . ($original_products_id != '' ? " and child_id = '" . tep_db_input($original_products_id) . "'" : '') . " and products_id = '" . tep_db_input($products_id) . "'" . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "'" : '') . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '')));
        } else {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(temporary_stock_quantity) as temporary_stock_quantity from ' . self::get_temporary_stock_table_name() . " where session_id = '" . tep_db_input($the_session_id) . "'" . ($original_products_id != '' ? " and child_id = '" . tep_db_input($original_products_id) . "'" : '') . " and products_id = '" . tep_db_input($products_id) . "'" . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "'" : '') . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '')));
        }
        return $temporary_stock_data['temporary_stock_quantity'];
    }
    protected static function get_customer_temporary_stock_info($products_id, $original_products_id = '')
    {
        $temporary_stock_data = [];
        $original_products_id = trim($original_products_id);
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        //$session_id = tep_session_id();
        if (\Yii::$app->id == 'app-console') {
            $the_session_id = \Yii::$app->storage->get('guid');
        } else {
            $the_session_id = tep_session_id();
        }
        if (\Yii::$app->user->is_guest) {
            $search_customer_condition = "session_id = '" . tep_db_input($the_session_id) . "'";
        } else {
            $search_customer_condition = "(customers_id = '" . (int) \Yii::$app->user->get_id() . "' or (customers_id = '0' and session_id = '" . tep_db_input($the_session_id) . "'))";
        }
        $get_temporary_stock_r = tep_db_query('SELECT temporary_stock_quantity, warehouse_id, suppliers_id, location_id ' . 'FROM ' . self::get_temporary_stock_table_name() . ' ' . "WHERE products_id = '" . tep_db_input($products_id) . "'" . ($original_products_id != '' ? " AND child_id = '" . tep_db_input($original_products_id) . "'" : '') . " AND {$search_customer_condition}");
        if (tep_db_num_rows($get_temporary_stock_r) > 0) {
            while ($temporary_stock = tep_db_fetch_array($get_temporary_stock_r)) {
                $temporary_stock_data[] = $temporary_stock;
            }
        }
        return $temporary_stock_data;
    }
    public static function remove_customers_temporary_stock_quantity($products_id)
    {
        self::update_customers_temporary_stock_quantity($products_id, 0);
    }
    public static function update_stock_of_order($orders_id, $products_id, $qty, $warehouse_id = 0, $suppliers_id = 0, $platform_id = 0)
    {
        global $login_id;
        if ($platform_id > 0) {
            $current_platform_id = $platform_id;
        } elseif (defined('PLATFORM_ID') && PLATFORM_ID > 0) {
            $current_platform_id = PLATFORM_ID;
        } else {
            $current_platform_id = \common\classes\platform::default_id();
        }
        /*if ($warehouse_id == 0) {
              $warehouse_id = \common\helpers\Warehouses::get_default_warehouse();
          }*/
        //$suppliers_id = 0; // all suppliers
        if (($ext = \common\helpers\Extensions::is_allowed('SupplierPurchase')) && $suppliers_id == 0) {
            $suppliers_id = $ext::get_supplier_from_uprid($products_id);
        }
        $products_id = \common\helpers\Inventory::normalize_inventory_id($products_id);
        // {{   // needed to calculate allocated stock
        tep_db_query('update ' . TABLE_ORDERS . " set stock_updated = '1' where orders_id = '" . (int) $orders_id . "'");
        // }}
        $orders_products_quantity = self::get_orders_products_quantity($products_id, $orders_id);
        if ($qty > $orders_products_quantity) {
            // increase orders products quantity
            $update_qty = $qty - $orders_products_quantity;
            if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                $warehouse_id_check = $ext_scl::update_update_stock_of_order($products_id, $current_platform_id);
                if ($warehouse_id_check !== false) {
                    $warehouse_id = $warehouse_id_check;
                }
                unset($warehouse_id_check);
            }
            $warehouses_ids = [];
            if ($warehouse_id > 0) {
                //$warehouses_query = tep_db_query("select warehouse_id from " . TABLE_WAREHOUSES . " where status = '1' and warehouse_id='" . $warehouse_id . "'");
                $warehouses_ids[] = $warehouse_id;
            } else {
                $warehouses_query = tep_db_query('select w.warehouse_id from ' . TABLE_WAREHOUSES . ' w left join ' . TABLE_WAREHOUSES_TO_PLATFORMS . " w2p on w.warehouse_id = w2p.warehouse_id and w2p.platform_id = '" . (int) $current_platform_id . "' where ifnull(w2p.status, w.status) = '1' order by ifnull(w2p.sort_order, w.sort_order), w.warehouse_name");
                while ($warehouses = tep_db_fetch_array($warehouses_query)) {
                    $warehouses_ids[] = $warehouses['warehouse_id'];
                }
                /**
                 * @var $ext \common\extensions\WarehousePriority\WarehousePriority
                 */
                if ($ext = \common\helpers\Extensions::is_allowed('WarehousePriority')) {
                    $ordered_product = ['products_id' => $products_id, 'products_quantity' => $update_qty];
                    $preffered_warehouse_ids = $ext::get_instance()->get_preferred_warehouse_id($ordered_product, $platform_id);
                    if (count($preffered_warehouse_ids) > 0) {
                        $warehouses_ids = $preffered_warehouse_ids;
                    }
                }
            }
            $suppliers_ids = [];
            if ($suppliers_id > 0) {
                $suppliers_ids[] = $suppliers_id;
            } else {
                $suppliers_query = tep_db_query('select suppliers_id from ' . TABLE_SUPPLIERS . " where status = '1' " . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '') . ' order by sort_order, suppliers_name');
                while ($suppliers = tep_db_fetch_array($suppliers_query)) {
                    // update suppliers by sort order ascending
                    $suppliers_ids[] = $suppliers['suppliers_id'];
                }
                $calculated_prices = [];
                //TODO /lib/backend/controllers/CategoriesController.php actionAutoSupplierPrice
                if (count($calculated_prices) > 0) {
                    if ($ext = \common\helpers\Acl::check_extension_allowed('SupplierPriority', 'getInstance')) {
                        $preferred_suppliers_ids = $ext::get_instance()->get_preferred_supplier_id($calculated_prices);
                        if (count($preferred_suppliers_ids) > 0) {
                            $suppliers_ids = $preferred_suppliers_ids;
                        }
                    }
                }
            }
            foreach ($warehouses_ids as $warehouses_id) {
                foreach ($suppliers_ids as $suppliers_id) {
                    if ($update_qty > 0) {
                        $available_products_quantity = self::get_products_quantity($products_id, $warehouses_id, $suppliers_id);
                        if ($available_products_quantity > 0) {
                            if ($update_qty <= $available_products_quantity) {
                                \common\helpers\Product::log_stock_history_before_update($products_id, $update_qty, '-', ['warehouse_id' => $warehouses_id, 'suppliers_id' => $suppliers_id, 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                                \common\helpers\Product::update_stock($products_id, 0, $update_qty, $warehouses_id, $suppliers_id, $current_platform_id);
                                self::update_orders_products_quantity($products_id, $orders_id, $warehouses_id, $update_qty, '+', $suppliers_id);
                                self::get_allocated_stock_quantity($products_id, $warehouses_id, $suppliers_id);
                                $update_qty = 0;
                                break;
                            } else {
                                \common\helpers\Product::log_stock_history_before_update($products_id, $available_products_quantity, '-', ['warehouse_id' => $warehouses_id, 'suppliers_id' => $suppliers_id, 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                                \common\helpers\Product::update_stock($products_id, 0, $available_products_quantity, $warehouses_id, $suppliers_id, $current_platform_id);
                                self::update_orders_products_quantity($products_id, $orders_id, $warehouses_id, $available_products_quantity, '+', $suppliers_id);
                                self::get_allocated_stock_quantity($products_id, $warehouses_id, $suppliers_id);
                                $update_qty -= $available_products_quantity;
                            }
                        }
                    } else {
                        break;
                    }
                }
            }
            // {{       // if no available stock left - update default warehouse & supplier
            if ($update_qty > 0) {
                $default_warehouse_id = self::get_default_warehouse();
                $default_suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
                \common\helpers\Product::log_stock_history_before_update($products_id, $update_qty, '-', ['warehouse_id' => $default_warehouse_id, 'suppliers_id' => $default_suppliers_id, 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                \common\helpers\Product::update_stock($products_id, 0, $update_qty, $default_warehouse_id, $default_suppliers_id);
                self::update_orders_products_quantity($products_id, $orders_id, $default_warehouse_id, $update_qty, '+', $default_suppliers_id, true);
                self::get_allocated_stock_quantity($products_id, $default_warehouse_id, $default_suppliers_id);
            }
            // }}
        } elseif ($qty < $orders_products_quantity) {
            // decrease orders products quantity
            $update_qty = $orders_products_quantity - $qty;
            // {{       // if not available stock > 0 - update default warehouse & supplier
            $default_warehouse_id = self::get_default_warehouse();
            $default_suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
            $warehouse_orders_not_available_quantity = self::get_orders_not_available_quantity($products_id, $orders_id, $default_warehouse_id, $default_suppliers_id);
            if ($warehouse_orders_not_available_quantity > 0) {
                $warehouse_orders_products_quantity = self::get_orders_products_quantity($products_id, $orders_id, $default_warehouse_id, $default_suppliers_id);
                if ($warehouse_orders_products_quantity > 0) {
                    if ($update_qty <= $warehouse_orders_not_available_quantity) {
                        \common\helpers\Product::log_stock_history_before_update($products_id, $update_qty, '+', ['warehouse_id' => $default_warehouse_id, 'suppliers_id' => $default_suppliers_id, 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                        \common\helpers\Product::update_stock($products_id, $update_qty, 0, $default_warehouse_id, $default_suppliers_id);
                        self::update_orders_products_quantity($products_id, $orders_id, $default_warehouse_id, $update_qty, '-', $default_suppliers_id, true);
                        self::get_allocated_stock_quantity($products_id, $default_warehouse_id, $default_suppliers_id);
                        $update_qty = 0;
                    } else {
                        \common\helpers\Product::log_stock_history_before_update($products_id, $warehouse_orders_not_available_quantity, '+', ['warehouse_id' => $default_warehouse_id, 'suppliers_id' => $default_suppliers_id, 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                        \common\helpers\Product::update_stock($products_id, $warehouse_orders_not_available_quantity, 0, $default_warehouse_id, $default_suppliers_id);
                        self::update_orders_products_quantity($products_id, $orders_id, $default_warehouse_id, $warehouse_orders_not_available_quantity, '-', $default_suppliers_id, true);
                        self::get_allocated_stock_quantity($products_id, $default_warehouse_id, $default_suppliers_id);
                        $update_qty -= $warehouse_orders_not_available_quantity;
                    }
                }
            }
            // }}
            //$warehouses_query = tep_db_query("select warehouse_id from " . TABLE_WAREHOUSES . " where status = '1' order by sort_order desc, warehouse_name desc");
            $warehouses_query = tep_db_query('select w.warehouse_id from ' . TABLE_WAREHOUSES . ' w left join ' . TABLE_WAREHOUSES_TO_PLATFORMS . " w2p on w.warehouse_id = w2p.warehouse_id and w2p.platform_id = '" . (int) $current_platform_id . "' where ifnull(w2p.status, w.status) = '1' order by ifnull(w2p.sort_order, w.sort_order) desc, w.warehouse_name desc");
            while ($warehouses = tep_db_fetch_array($warehouses_query)) {
                // update warehouses by sort order descending
                $suppliers_query = tep_db_query('select suppliers_id from ' . TABLE_SUPPLIERS . " where status = '1' " . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '') . ' order by sort_order desc, suppliers_name desc');
                while ($suppliers = tep_db_fetch_array($suppliers_query)) {
                    // update suppliers by sort order descending
                    if ($update_qty > 0) {
                        $warehouse_orders_products_quantity = self::get_orders_products_quantity($products_id, $orders_id, $warehouses['warehouse_id'], $suppliers['suppliers_id']);
                        if ($warehouse_orders_products_quantity > 0) {
                            if ($update_qty <= $warehouse_orders_products_quantity) {
                                \common\helpers\Product::log_stock_history_before_update($products_id, $update_qty, '+', ['warehouse_id' => $warehouses['warehouse_id'], 'suppliers_id' => $suppliers['suppliers_id'], 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                                \common\helpers\Product::update_stock($products_id, $update_qty, 0, $warehouses['warehouse_id'], $suppliers['suppliers_id']);
                                self::update_orders_products_quantity($products_id, $orders_id, $warehouses['warehouse_id'], $update_qty, '-', $suppliers['suppliers_id']);
                                self::get_allocated_stock_quantity($products_id, $warehouses['warehouse_id'], $suppliers['suppliers_id']);
                                $update_qty = 0;
                                break;
                            } else {
                                \common\helpers\Product::log_stock_history_before_update($products_id, $warehouse_orders_products_quantity, '+', ['warehouse_id' => $warehouses['warehouse_id'], 'suppliers_id' => $suppliers['suppliers_id'], 'comments' => TEXT_ORDER_STOCK_UPDATE, 'admin_id' => $login_id, 'orders_id' => $orders_id]);
                                \common\helpers\Product::update_stock($products_id, $warehouse_orders_products_quantity, 0, $warehouses['warehouse_id'], $suppliers['suppliers_id']);
                                self::update_orders_products_quantity($products_id, $orders_id, $warehouses['warehouse_id'], $warehouse_orders_products_quantity, '-', $suppliers['suppliers_id']);
                                self::get_allocated_stock_quantity($products_id, $warehouses['warehouse_id'], $suppliers['suppliers_id']);
                                $update_qty -= $warehouse_orders_products_quantity;
                            }
                        }
                    } else {
                        break;
                    }
                }
            }
        }
    }
    public static function relocate_qty($uprid, $from_warehouse_id, $to_warehouse_id, $qty, $supplier_id = 0, $from_location_id = 0, $to_location_id = 0)
    {
        $available_qty = \common\helpers\Warehouses::get_products_quantity($uprid, $from_warehouse_id, $supplier_id);
        if ($qty > $available_qty) {
            $qty = $available_qty;
        }
        $qty = max(0, $qty);
        if ($qty > 0) {
            $TEXT_AUTO_STOCK_RELOCATE = defined('TEXT_AUTO_STOCK_RELOCATE') ? TEXT_AUTO_STOCK_RELOCATE : 'Auto Warehouses Relocate from %s to %s';
            $comments = sprintf($TEXT_AUTO_STOCK_RELOCATE, \common\helpers\Warehouses::get_warehouse_name($from_warehouse_id), \common\helpers\Warehouses::get_warehouse_name($to_warehouse_id));
            $parameters = ['comments' => $comments];
            \common\helpers\Warehouses::update_products_quantity($uprid, $from_warehouse_id, $qty, '-', $supplier_id, $from_location_id, $parameters);
            \common\helpers\Warehouses::update_products_quantity($uprid, $to_warehouse_id, $qty, '+', $supplier_id, $to_location_id, $parameters);
        }
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_orders_products_quantity($products_id, $orders_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        return 0;
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wop.products_quantity) as products_quantity from ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . ' wop, ' . TABLE_WAREHOUSES . " w where wop.warehouse_id = w.warehouse_id and w.status = '1' " . ($warehouse_id > 0 ? " and w.warehouse_id = '" . (int) $warehouse_id . "' " : '') . ($suppliers_id > 0 ? " and wop.suppliers_id = '" . (int) $suppliers_id . "' " : '') . " and wop.orders_id = '" . (int) $orders_id . "' and wop.uprid = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' and wop.template_uprid = '" . tep_db_input($products_id) . "' and wop.products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'"));
        } else {
            $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wop.products_quantity) as products_quantity from ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . ' wop, ' . TABLE_WAREHOUSES . " w where wop.warehouse_id = w.warehouse_id and w.status = '1' " . ($warehouse_id > 0 ? " and w.warehouse_id = '" . (int) $warehouse_id . "' " : '') . ($suppliers_id > 0 ? " and wop.suppliers_id = '" . (int) $suppliers_id . "' " : '') . " and wop.orders_id = '" . (int) $orders_id . "' and wop.uprid = '" . (int) $products_id . "' and wop.template_uprid = '" . tep_db_input($products_id) . "' and wop.products_id = '" . (int) $products_id . "'"));
        }
        return $warehouses_stock['products_quantity'];
    }
    public static function update_orders_products_quantity($products_id, $orders_id, $warehouse_id, $qty, $qty_prefix, $suppliers_id = 0, $not_available = false)
    {
        return 0;
        if ($qty_prefix != '-') {
            $qty_prefix = '+';
        }
        if ($suppliers_id == 0) {
            $suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
        }
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            $check_warehouse = tep_db_fetch_array(tep_db_query('select count(*) as stock_exists from ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . " where warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "' and orders_id = '" . (int) $orders_id . "' and uprid = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' and template_uprid = '" . tep_db_input($products_id) . "' and products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'"));
            if ($check_warehouse['stock_exists']) {
                tep_db_query('update ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . ' set products_quantity = products_quantity ' . $qty_prefix . (int) $qty . ($not_available ? ', not_available_quantity = not_available_quantity ' . $qty_prefix . (int) $qty : '') . " where warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "' and orders_id = '" . (int) $orders_id . "' and uprid = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' and template_uprid = '" . tep_db_input($products_id) . "' and products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'");
            } else {
                $data = tep_db_fetch_array(tep_db_query('select products_model from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' and prid = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'"));
                tep_db_query('insert into ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . " set products_model = '" . tep_db_input($data['products_model']) . "', products_quantity = " . $qty_prefix . (int) $qty . ($not_available ? ', not_available_quantity = ' . $qty_prefix . (int) $qty : '') . ", warehouse_id = '" . (int) $warehouse_id . "', suppliers_id = '" . (int) $suppliers_id . "', orders_id = '" . (int) $orders_id . "', uprid = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "', template_uprid = '" . tep_db_input($products_id) . "', products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'");
            }
        } else {
            $check_warehouse = tep_db_fetch_array(tep_db_query('select count(*) as stock_exists from ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . " where warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "' and orders_id = '" . (int) $orders_id . "' and uprid = '" . (int) $products_id . "' and template_uprid = '" . tep_db_input($products_id) . "' and products_id = '" . (int) $products_id . "'"));
            if ($check_warehouse['stock_exists']) {
                tep_db_query('update ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . ' set products_quantity = products_quantity ' . $qty_prefix . (int) $qty . ($not_available ? ', not_available_quantity = not_available_quantity ' . $qty_prefix . (int) $qty : '') . " where warehouse_id = '" . (int) $warehouse_id . "' and suppliers_id = '" . (int) $suppliers_id . "' and orders_id = '" . (int) $orders_id . "' and uprid = '" . (int) $products_id . "' and template_uprid = '" . tep_db_input($products_id) . "' and products_id = '" . (int) $products_id . "'");
            } else {
                $data = tep_db_fetch_array(tep_db_query('select products_model from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'"));
                tep_db_query('insert into ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . " set products_model = '" . tep_db_input($data['products_model']) . "', products_quantity = " . $qty_prefix . (int) $qty . ($not_available ? ', not_available_quantity = ' . $qty_prefix . (int) $qty : '') . ", warehouse_id = '" . (int) $warehouse_id . "', suppliers_id = '" . (int) $suppliers_id . "', orders_id = '" . (int) $orders_id . "', uprid = '" . (int) $products_id . "', template_uprid = '" . tep_db_input($products_id) . "', products_id = '" . (int) $products_id . "'");
            }
        }
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_customers_not_available_quantity($products_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        //$the_session_id = tep_session_id();
        if (\Yii::$app->id == 'app-console') {
            $the_session_id = \Yii::$app->storage->get('guid');
        } else {
            $the_session_id = tep_session_id();
        }
        if (!\Yii::$app->user->is_guest) {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(not_available_quantity) as not_available_quantity from ' . self::get_temporary_stock_table_name() . " where (customers_id = '" . (int) \Yii::$app->user->get_id() . "' or (customers_id = '0' and session_id = '" . tep_db_input($the_session_id) . "')) and products_id = '" . tep_db_input($products_id) . "'" . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "'" : '') . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '')));
        } else {
            $temporary_stock_data = tep_db_fetch_array(tep_db_query('select sum(not_available_quantity) as not_available_quantity from ' . self::get_temporary_stock_table_name() . " where session_id = '" . tep_db_input($the_session_id) . "' and products_id = '" . tep_db_input($products_id) . "'" . ($warehouse_id > 0 ? " and warehouse_id = '" . (int) $warehouse_id . "'" : '') . ($suppliers_id > 0 ? " and suppliers_id = '" . (int) $suppliers_id . "'" : '')));
        }
        return $temporary_stock_data['not_available_quantity'];
    }
    // $warehouse_id = 0 - all warehouses, $suppliers_id = 0 - all suppliers
    public static function get_orders_not_available_quantity($products_id, $orders_id, $warehouse_id = 0, $suppliers_id = 0)
    {
        return 0;
        if (strpos(\common\helpers\Inventory::normalize_inventory_id($products_id), '{') !== false) {
            $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wop.not_available_quantity) as not_available_quantity from ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . ' wop, ' . TABLE_WAREHOUSES . " w where wop.warehouse_id = w.warehouse_id and w.status = '1' " . ($warehouse_id > 0 ? " and w.warehouse_id = '" . (int) $warehouse_id . "' " : '') . ($suppliers_id > 0 ? " and wop.suppliers_id = '" . (int) $suppliers_id . "' " : '') . " and wop.orders_id = '" . (int) $orders_id . "' and wop.uprid = '" . tep_db_input(\common\helpers\Inventory::normalize_inventory_id($products_id)) . "' and wop.template_uprid = '" . tep_db_input($products_id) . "' and wop.products_id = '" . (int) \common\helpers\Inventory::get_prid($products_id) . "'"));
        } else {
            $warehouses_stock = tep_db_fetch_array(tep_db_query('select sum(wop.not_available_quantity) as not_available_quantity from ' . TABLE_WAREHOUSES_ORDERS_PRODUCTS . ' wop, ' . TABLE_WAREHOUSES . " w where wop.warehouse_id = w.warehouse_id and w.status = '1' " . ($warehouse_id > 0 ? " and w.warehouse_id = '" . (int) $warehouse_id . "' " : '') . ($suppliers_id > 0 ? " and wop.suppliers_id = '" . (int) $suppliers_id . "' " : '') . " and wop.orders_id = '" . (int) $orders_id . "' and wop.uprid = '" . (int) $products_id . "' and wop.template_uprid = '" . tep_db_input($products_id) . "' and wop.products_id = '" . (int) $products_id . "'"));
        }
        return $warehouses_stock['not_available_quantity'];
    }
    /**
     * Get Warehouse Products for specific enabled Platform
     * @param mixed $uProductId Product Id or Product Inventory Id
     * @param mixed $platformId Platform Id. If false - calculate for all platforms; if equals 0 - front-end mode; if greater than 0 - calculate for specific platform
     * @param boolean $asArray switching return type between array of arrays or array of instances of WarehousesProducts
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_product_array($u_product_id = 0, $platform_id = false, $as_array = true)
    {
        $return = [];
        $u_product_id = \common\helpers\Inventory::normalize_inventory_id($u_product_id);
        if ($platform_id !== false) {
            if ($platform_id <= 0) {
                if (defined('PLATFORM_ID') and (int) PLATFORM_ID > 0) {
                    $platform_id = PLATFORM_ID;
                } elseif (\common\classes\platform::default_id() > 0) {
                    $platform_id = \common\classes\platform::default_id();
                } else {
                    $platform_id = \common\classes\platform::current_id();
                }
            }
            $platform_id = (int) $platform_id;
        }
        $supplier_id_array = \common\helpers\Product::get_supplier_id_priority_array($u_product_id);
        $warehouse_product_query = \common\models\Warehouses_Products::find()->and_where(['products_id' => $u_product_id])->and_where(['prid' => (int) $u_product_id])->and_where(['IN', 'suppliers_id', $supplier_id_array])->as_array($as_array);
        unset($supplier_id_array);
        if ($platform_id !== false) {
            $warehouse_id_array = \common\helpers\Product::get_warehouse_id_priority_array($u_product_id, 1, $platform_id);
            $warehouse_product_query->and_where(['IN', 'warehouse_id', $warehouse_id_array]);
            unset($warehouse_id_array);
        }
        foreach ($warehouse_product_query->all() as $warehouse_product_record) {
            $return[] = $warehouse_product_record;
        }
        unset($warehouse_product_record);
        unset($warehouse_product_query);
        unset($platform_id);
        unset($u_product_id);
        unset($as_array);
        return $return;
    }
    public static function get_location_path($location_id = 0, $warehouse_id = 0, $blocks_list = [])
    {
        $item = '';
        if ($location_id == 0) {
            return $item;
        }
        $location = \common\models\Locations::find()->where(['warehouse_id' => $warehouse_id, 'location_id' => $location_id])->as_array()->one();
        $item .= self::get_location_path($location['parrent_id'], $warehouse_id, $blocks_list);
        if (!empty($item)) {
            $item .= ', ';
        }
        $item .= $blocks_list[$location['block_id']] . ': ' . $location['location_name'];
        return $item;
    }
    public static function is_relocation_possible()
    {
        $wh = self::get_warehouses();
        if (!is_array($wh) || count($wh) == 0) {
            return false;
        } elseif (count($wh) > 1) {
            return true;
        } else {
            return \common\models\Locations::find()->where(['warehouse_id' => $wh[0]['id'] ?? null, 'is_final' => 1])->count() > 1;
        }
    }
    public static function get_locations($warehouse_id, $with_blocks = true, $only_final = true, $separator = ', ')
    {
        $blocks = [];
        $locations = [];
        $parent_map = [];
        $location_list = [];
        foreach (\common\models\Locations::find()->where(['warehouse_id' => $warehouse_id])->order_by(['parrent_id' => SORT_ASC, 'location_name' => SORT_ASC])->all() as $location) {
            $locations[$location->location_id] = $location;
            if (!isset($parent_map[$location->parrent_id])) {
                $parent_map[$location->parrent_id] = [];
            }
            $parent_map[$location->parrent_id][] = $location->location_id;
            if (!isset($blocks[$location->block_id])) {
                $blocks[$location->block_id] = $location->location_block->block_name;
            }
            $location_list[$location->location_id] = ['location_id' => $location->location_id, 'parent_id' => $location->parrent_id, 'is_final' => $location->is_final, 'block_name' => $blocks[$location->block_id], 'location_name' => $location->location_name, 'complete_name' => []];
        }
        $max_level = 0;
        foreach ($location_list as $_loc_id => $location_variant) {
            $level = 0;
            $parent_id = $location_variant['parent_id'];
            $weight = [];
            $weight[] = array_search($_loc_id, $parent_map[$parent_id]) + 1;
            $complete_name = [];
            $complete_name[] = ($with_blocks ? $location_variant['block_name'] . ': ' : '') . $location_variant['location_name'];
            while ($parent_id != 0) {
                if (!isset($location_list[$parent_id])) {
                    $level = false;
                    break;
                }
                $complete_name[] = ($with_blocks ? $location_list[$parent_id]['block_name'] . ': ' : '') . $location_list[$parent_id]['location_name'];
                $parent_id = $location_list[$parent_id]['parent_id'];
                $weight[] = array_search($_loc_id, $parent_map[$parent_id]) + 1;
                $level++;
            }
            if ($level === false) {
                unset($location_list[$_loc_id]);
            } else {
                $max_level = max($max_level, $level);
                $location_list[$_loc_id]['level'] = $level;
                $location_list[$_loc_id]['complete_name'] = implode($separator, array_reverse($complete_name));
            }
        }
        if ($only_final) {
            foreach ($location_list as $_loc_id => $location_variant) {
                if (!$location_variant['is_final']) {
                    unset($location_list[$_loc_id]);
                }
            }
        }
        return $location_list;
    }
    public static function get_warehouses_products_layers_i_dby_expiry_date($expiry_date)
    {
        list($year, $month, $day) = array_pad(explode('-', $expiry_date), 3, null);
        if (checkdate((int) $month, (int) $day, (int) $year)) {
            $warehouses_products_layers_record = \common\models\Warehouses_Products_Layers::find_one(['expiry_date' => $expiry_date]);
            if (!$warehouses_products_layers_record instanceof \common\models\Warehouses_Products_Layers) {
                $warehouses_products_layers_record = new \common\models\Warehouses_Products_Layers();
                $warehouses_products_layers_record->layers_name = \common\helpers\Date::date_short($expiry_date);
                $warehouses_products_layers_record->expiry_date = $expiry_date;
                $warehouses_products_layers_record->save(false);
            }
            return $warehouses_products_layers_record->layers_id;
        }
        return 0;
    }
    public static function get_expiry_date_by_layers_id($layers_id)
    {
        $warehouses_products_layers_record = \common\models\Warehouses_Products_Layers::find_one(['layers_id' => $layers_id]);
        if ($warehouses_products_layers_record instanceof \common\models\Warehouses_Products_Layers) {
            return $warehouses_products_layers_record->expiry_date;
        }
    }
    public static function get_warehouses_products_batch_i_dby_batch_name($batch_name)
    {
        if ($batch_name != '') {
            $warehouses_products_batches_record = \common\models\Warehouses_Products_Batches::find_one(['batch_name' => $batch_name]);
            if (!$warehouses_products_batches_record instanceof \common\models\Warehouses_Products_Batches) {
                $warehouses_products_batches_record = new \common\models\Warehouses_Products_Batches();
                $warehouses_products_batches_record->batch_name = $batch_name;
                $warehouses_products_batches_record->save(false);
            }
            return $warehouses_products_batches_record->batch_id;
        }
        return 0;
    }
    public static function get_batch_name_by_batch_id($batch_id)
    {
        $warehouses_products_batches_record = \common\models\Warehouses_Products_Batches::find_one(['batch_id' => $batch_id]);
        if ($warehouses_products_batches_record instanceof \common\models\Warehouses_Products_Batches) {
            return $warehouses_products_batches_record->batch_name;
        }
    }
}