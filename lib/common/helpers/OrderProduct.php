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

class Order_Product
{
    public const OPS_QUOTED = 1;
    public const OPS_STOCK_DEFICIT = 10;
    public const OPS_STOCK_PENDING = 12;
    public const OPS_STOCK_ORDERED = 15;
    public const OPS_RECEIVED = 20;
    public const OPS_DISPATCHED = 30;
    public const OPS_DELIVERED = 40;
    public const OPS_CANCELLED = 50;
    /**
     * Automatically allocating stock for Order Product.
     * Rules: Product Record exists, Order Record exists, Order Product Record exists, Order Product Status acceptable, Order updateAllocateAllow passed, Product isValidAllocated passed.
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return mixed false on error or current Order Product Status Id
     */
    public static function do_allocate_automatic($order_product_record = 0, $do_cache = false)
    {
        $order_product_record = self::get_record($order_product_record);
        if (!$order_product_record instanceof \common\models\Orders_Products) {
            return false;
        }
        $order_record = \common\models\Orders::find_one($order_product_record->orders_id);
        if (!$order_record instanceof \common\models\Orders) {
            return false;
        }
        if (\common\helpers\Order::is_allocate_temporary($order_record) == true) {
            \common\helpers\Order::update_allocate_allow($order_record, true);
        }
        if (\common\helpers\Order::update_allocate_allow($order_record) <= 0) {
            return false;
        }
        $product_record = \common\helpers\Product::get_record($order_product_record->products_id, false, true);
        if (!$product_record instanceof \common\models\Products) {
            return false;
        }
        if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
            $ext::do_allocate_automatic_warehouse_freeze($order_product_record, $order_record, $product_record);
            \common\helpers\Product::do_cache($product_record);
            return false;
        }
        if (\common\helpers\Product::is_valid_allocated($order_product_record->uprid) != true) {
            return false;
        }
        if (defined('STOCK_LIMITED') and STOCK_LIMITED == 'true') {
            $return = self::do_allocate_automatic_warehouse($order_product_record, $order_record, $product_record);
        } else {
            $return = self::do_allocate_automatic_unlimited($order_product_record, $order_record, $product_record);
        }
        unset($order_record);
        $order_product_status_id = self::evaluate($order_product_record);
        unset($order_product_record);
        if ($return !== false) {
            $return = $order_product_status_id;
        }
        unset($order_product_status_id);
        if ((int) $do_cache > 0) {
            \common\helpers\Product::do_cache($product_record);
        }
        unset($product_record);
        unset($do_cache);
        return $return;
    }
    private static function collect_point_auto_relocate()
    {
    }
    /**
     * Automatically allocating stock for Order Product from Warehouse stock.
     * Rules: Received Dispatched Allocations locked on amount of Dispatched quantity.
     * Behaviour: updating already present Allocations by Warehouse/Supplier/Location/Layer/Batch (W/S/L/L/B) priority, cleaning up unavailable Allocations, Allocating deficit by W/S/L/L/B priority
     * @param \common\models\OrdersProducts $orderProductRecord
     * @param \common\models\Orders $orderRecord
     * @param \common\models\Products $productRecord
     * @return boolean false on error, true on success
     */
    private static function do_allocate_automatic_warehouse(\common\models\Orders_Products $order_product_record, \common\models\Orders $order_record, \common\models\Products $product_record)
    {
        $u_product_id = \common\helpers\Inventory::get_inventory_id($order_product_record->uprid);
        $is_temporary = \common\helpers\Order::is_allocate_temporary($order_record);
        $product_quantity_real = self::get_quantity_real($order_product_record);
        $force_sell_from_collect_warehouse_id = false;
        if (\common\helpers\Acl::check_extension_allowed('CollectionPoints') && preg_match('/^collect_(\d+)$/', $order_record->shipping_class, $collect_id_match)) {
            $collection_point = \common\extensions\Collection_Points\models\Collection_Points::find_one($collect_id_match[1]);
            if ($collection_point instanceof \common\extensions\Collection_Points\models\Collection_Points && $collection_point->warehouses_address_book_id > 0) {
                $warehouses_address_book_id = $collection_point->warehouses_address_book_id;
                $collect_warehouse = \common\models\Warehouses::find()->inner_join_with('address')->where([\common\models\Warehouses_Address_Book::table_name() . '.warehouses_address_book_id' => $warehouses_address_book_id])->one();
                if ($collect_warehouse) {
                    $collect_warehouse_id = (int) $collect_warehouse->warehouse_id;
                    if ($collection_point->notify_warehouse == 1 && !empty($collect_warehouse->warehouse_email_address)) {
                        $platform_config = \Yii::$app->get('platform')->config($order_record->platform_id);
                        $STORE_NAME = $platform_config->const_value('STORE_NAME');
                        $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                        $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                        $email_params = [];
                        $email_params['STORE_NAME'] = $STORE_NAME;
                        $email_params['ORDER_NUMBER'] = method_exists($order_record, 'getOrderNumber') ? $order_record->get_order_number() : $order_record->orders_id;
                        $email_params['PRODUCTS_ORDERED'] = $order_product_record->products_name . ' (' . $order_product_record->products_model . ') X ' . $order_product_record->products_quantity;
                        list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Warehouse notification', $email_params);
                        \common\helpers\Mail::send($collect_warehouse->warehouse_owner, $collect_warehouse->warehouse_email_address, $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, $email_params);
                    }
                    if ($collection_point->relocate_warehouse_id && (int) $collect_warehouse->warehouse_id != (int) $collection_point->relocate_warehouse_id) {
                        $force_sell_from_collect_warehouse_id = $collect_warehouse_id;
                        // {{ relocate missing stock
                        $warehouse_uprid = \common\helpers\Inventory::normalize_inventory_id($u_product_id);
                        $need_qty = $product_quantity_real - \common\helpers\Warehouses::get_products_quantity($warehouse_uprid, $collect_warehouse_id);
                        if ($need_qty > 0) {
                            \common\helpers\Warehouses::relocate_qty($warehouse_uprid, $collection_point->relocate_warehouse_id, $collect_warehouse_id, $need_qty);
                        }
                        // }} relocate missing stock
                    }
                }
            }
        }
        $warehouse_id_array = \common\helpers\Product::get_warehouse_id_priority_array($u_product_id, $product_quantity_real, $order_record->platform_id);
        if ($force_sell_from_collect_warehouse_id !== false) {
            $_exist_idx = array_search((int) $force_sell_from_collect_warehouse_id, $warehouse_id_array);
            if ($_exist_idx !== false) {
                unset($warehouse_id_array[$_exist_idx]);
            }
            array_unshift($warehouse_id_array, (int) $force_sell_from_collect_warehouse_id);
        }
        $supplier_id_array = \common\helpers\Product::get_supplier_id_priority_array($u_product_id);
        $location_id_array = \common\helpers\Product::get_location_id_priority_array($u_product_id);
        $layer_id_array = \common\helpers\Product::get_layers_id_priority_array($u_product_id);
        $batch_id_array = \common\helpers\Product::get_batch_id_priority_array($u_product_id);
        $warehouse_product_array = [];
        foreach (\common\helpers\Warehouses::get_product_array($u_product_id, $order_record->platform_id) as $warehouse_product_record) {
            $warehouse_product_array[$warehouse_product_record['layers_id']][$warehouse_product_record['warehouse_id']][$warehouse_product_record['suppliers_id']][$warehouse_product_record['location_id']][$warehouse_product_record['batch_id']] = $warehouse_product_record;
        }
        unset($warehouse_product_record);
        $warehouse_product_priority_array = [];
        foreach ($layer_id_array as $layer_id) {
            foreach ($warehouse_id_array as $warehouse_id) {
                foreach ($supplier_id_array as $supplier_id) {
                    foreach ($location_id_array as $location_id) {
                        foreach ($batch_id_array as $batch_id) {
                            if (isset($warehouse_product_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id])) {
                                $warehouse_product = $warehouse_product_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id];
                                if ($warehouse_product['layers_id'] == $layer_id and $warehouse_product['warehouse_id'] == $warehouse_id and $warehouse_product['suppliers_id'] == $supplier_id and $warehouse_product['location_id'] == $location_id and $warehouse_product['batch_id'] == $batch_id) {
                                    $key = "{$warehouse_product['layers_id']}_{$warehouse_product['warehouse_id']}_{$warehouse_product['suppliers_id']}_{$warehouse_product['location_id']}_{$warehouse_product['batch_id']}";
                                    $warehouse_product_priority_array[$key] = $warehouse_product;
                                    unset($key);
                                }
                            }
                            unset($warehouse_product);
                        }
                        unset($batch_id);
                    }
                    unset($location_id);
                }
                unset($supplier_id);
            }
            unset($warehouse_id);
        }
        unset($warehouse_product_array);
        unset($layer_id);
        $product_quantity_received = 0;
        $order_product_allocated_array = [];
        // REMOVE EXISTING NON PRIORITY ALLOCATION
        foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocated) {
            $key = "{$order_product_allocated->layers_id}_{$order_product_allocated->warehouse_id}_{$order_product_allocated->suppliers_id}_{$order_product_allocated->location_id}_{$order_product_allocated->batch_id}";
            if (!isset($warehouse_product_priority_array[$key])) {
                if ($order_product_allocated->allocate_dispatched > 0) {
                    try {
                        $order_product_allocated->allocate_received = $order_product_allocated->allocate_dispatched;
                        $order_product_allocated->is_temporary = 0;
                        $order_product_allocated->datetime = date('Y-m-d H:i:s');
                        $order_product_allocated->save(false);
                    } catch (\Exception $exc) {
                        \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Helper.OrderProduct.doAllocateAutomaticWarehouse.RENPA.update');
                    }
                } else {
                    try {
                        $order_product_allocated->allocate_dispatched = 0;
                        $order_product_allocated->allocate_received = 0;
                        $order_product_allocated->delete();
                    } catch (\Exception $exc) {
                        \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Helper.OrderProduct.doAllocateAutomaticWarehouse.RENPA.delete');
                    }
                }
            } else {
                $order_product_allocated_array[$key] = $order_product_allocated;
            }
            $product_quantity_real -= (int) $order_product_allocated->allocate_dispatched;
            $product_quantity_received += (int) $order_product_allocated->allocate_received - (int) $order_product_allocated->allocate_dispatched;
            unset($key);
        }
        unset($order_product_allocated);
        // EOF REMOVE EXISTING NON PRIORITY ALLOCATION
        if ($product_quantity_real < 0) {
            return false;
        }
        $product_allocated_temporary_array = [];
        /*foreach (\common\helpers\Product::getAllocatedTemporaryArray($uProductId) as $productAllocatedTemporaryRecord) {
              $productAllocatedTemporaryArray[$productAllocatedTemporaryRecord['layers_id']][$productAllocatedTemporaryRecord['warehouse_id']][$productAllocatedTemporaryRecord['suppliers_id']][$productAllocatedTemporaryRecord['location_id']][$productAllocatedTemporaryRecord['batch_id']][] = $productAllocatedTemporaryRecord;
          }
          unset($productAllocatedTemporaryRecord);*/
        // UPDATE INVALID ALLOCATION
        if ($product_quantity_received > $product_quantity_real) {
            $product_allocated_array = [];
            foreach (\common\helpers\Product::get_allocated_array($u_product_id, false) as $product_allocated_record) {
                $product_allocated_array[$product_allocated_record->layers_id][$product_allocated_record->warehouse_id][$product_allocated_record->suppliers_id][$product_allocated_record->location_id][$product_allocated_record->batch_id][] = $product_allocated_record;
            }
            unset($product_allocated_record);
            foreach (['update', 'remove'] as $type) {
                foreach (array_reverse($warehouse_product_priority_array, true) as $key => $warehouse_product) {
                    if (isset($order_product_allocated_array[$key])) {
                        $order_product_allocated = $order_product_allocated_array[$key];
                        if ($type == 'update') {
                            $layer_id = (int) $order_product_allocated->layers_id;
                            $warehouse_id = (int) $order_product_allocated->warehouse_id;
                            $supplier_id = (int) $order_product_allocated->suppliers_id;
                            $location_id = (int) $order_product_allocated->location_id;
                            $batch_id = (int) $order_product_allocated->batch_id;
                            $stock_quantity = (int) $warehouse_product['warehouse_stock_quantity'];
                            if (isset($product_allocated_temporary_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id])) {
                                foreach ($product_allocated_temporary_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id] as $product_allocated_temporary_record) {
                                    $stock_quantity -= $product_allocated_temporary_record['temporary_stock_quantity'] > 0 ? $product_allocated_temporary_record['temporary_stock_quantity'] : 0;
                                }
                                unset($product_allocated_temporary_record);
                            }
                            if (isset($product_allocated_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id])) {
                                foreach ($product_allocated_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id] as $product_allocated) {
                                    if ($order_product_allocated->orders_products_id == $product_allocated->orders_products_id) {
                                        continue;
                                    }
                                    $stock_quantity -= $product_allocated->allocate_received - $product_allocated->allocate_dispatched;
                                }
                                unset($product_allocated);
                            }
                            $received_quantity = $order_product_allocated->allocate_received;
                            if ($order_product_allocated->allocate_received > $stock_quantity + $order_product_allocated->allocate_dispatched) {
                                $order_product_allocated->allocate_received = $stock_quantity + $order_product_allocated->allocate_dispatched;
                            }
                            if ($order_product_allocated->allocate_dispatched > $order_product_allocated->allocate_received) {
                                $order_product_allocated->allocate_received = $order_product_allocated->allocate_dispatched;
                            }
                            $product_quantity_received += $order_product_allocated->allocate_received - $received_quantity;
                            unset($received_quantity);
                            unset($stock_quantity);
                            unset($warehouse_id);
                            unset($supplier_id);
                            unset($location_id);
                            unset($batch_id);
                            unset($layer_id);
                        } elseif ($type == 'remove') {
                            if ($product_quantity_received <= $product_quantity_real) {
                                break 2;
                            }
                            $received_quantity = $order_product_allocated->allocate_received - $order_product_allocated->allocate_dispatched;
                            if ($product_quantity_received - $received_quantity < $product_quantity_real) {
                                $received_quantity = $product_quantity_received - $product_quantity_real;
                            }
                            $product_quantity_received -= $received_quantity;
                            $order_product_allocated->allocate_received -= $received_quantity;
                            unset($received_quantity);
                        }
                        try {
                            $order_product_allocated->allocate_received > 0 ? $order_product_allocated->save(false) : $order_product_allocated->delete();
                        } catch (\Exception $exc) {
                            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Helper.OrderProduct.doAllocateAutomaticWarehouse.UIA.update');
                        }
                    }
                }
                $order_product_allocated_array = [];
                foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocated) {
                    $key = "{$order_product_allocated->layers_id}_{$order_product_allocated->warehouse_id}_{$order_product_allocated->suppliers_id}_{$order_product_allocated->location_id}_{$order_product_allocated->batch_id}";
                    if (isset($warehouse_product_priority_array[$key])) {
                        $order_product_allocated_array[$key] = $order_product_allocated;
                    }
                    unset($key);
                }
                unset($order_product_allocated);
            }
            unset($order_product_allocated);
            unset($product_allocated_array);
            unset($warehouse_product);
            unset($type);
            unset($key);
        }
        unset($order_product_allocated_array);
        // EOF UPDATE INVALID ALLOCATION
        // ALLOCATE BY PRIORITY
        foreach (['pallet', 'pack', 'item'] as $type) {
            $multiplier = 1;
            if ($type == 'pallet') {
                $multiplier = (int) $product_record->pack_unit * (int) $product_record->packaging;
            } elseif ($type == 'pack') {
                $multiplier = (int) $product_record->pack_unit;
            }
            if ($multiplier <= 0) {
                continue;
            }
            $product_allocated_array = [];
            foreach (\common\helpers\Product::get_allocated_array($u_product_id, false) as $product_allocated_record) {
                $product_allocated_array[$product_allocated_record->layers_id][$product_allocated_record->warehouse_id][$product_allocated_record->suppliers_id][$product_allocated_record->location_id][$product_allocated_record->batch_id][] = $product_allocated_record;
            }
            unset($product_allocated_record);
            foreach ($warehouse_product_priority_array as $warehouse_product) {
                if ($product_quantity_received >= $product_quantity_real) {
                    break 2;
                }
                $layer_id = (int) $warehouse_product['layers_id'];
                $warehouse_id = (int) $warehouse_product['warehouse_id'];
                $supplier_id = (int) $warehouse_product['suppliers_id'];
                $location_id = (int) $warehouse_product['location_id'];
                $batch_id = (int) $warehouse_product['batch_id'];
                $stock_quantity = (int) $warehouse_product['warehouse_stock_quantity'];
                if (isset($product_allocated_temporary_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id])) {
                    foreach ($product_allocated_temporary_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id] as $product_allocated_temporary_record) {
                        $stock_quantity -= $product_allocated_temporary_record['temporary_stock_quantity'] > 0 ? $product_allocated_temporary_record['temporary_stock_quantity'] : 0;
                    }
                    unset($product_allocated_temporary_record);
                }
                $order_product_allocate_record = false;
                if (isset($product_allocated_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id])) {
                    foreach ($product_allocated_array[$layer_id][$warehouse_id][$supplier_id][$location_id][$batch_id] as $product_allocated) {
                        $stock_quantity -= (int) $product_allocated->allocate_received - (int) $product_allocated->allocate_dispatched;
                        if ($order_product_record->orders_products_id == $product_allocated->orders_products_id) {
                            $order_product_allocate_record = $product_allocated;
                        }
                    }
                    unset($product_allocated);
                }
                if ($stock_quantity < $multiplier) {
                    continue;
                }
                $stock_quantity = (int) (floor($stock_quantity / $multiplier) * $multiplier);
                if ($stock_quantity > 0) {
                    if ($product_quantity_received + $stock_quantity > $product_quantity_real) {
                        $stock_quantity = $product_quantity_real - $product_quantity_received;
                        $stock_quantity = (int) (floor($stock_quantity / $multiplier) * $multiplier);
                    }
                }
                if ($stock_quantity > 0) {
                    $product_quantity_received += $stock_quantity;
                    try {
                        if (!$order_product_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                            $order_product_allocate_record = new \common\models\Orders_Products_Allocate();
                            $order_product_allocate_record->orders_products_id = $order_product_record->orders_products_id;
                            $order_product_allocate_record->layers_id = $layer_id;
                            $order_product_allocate_record->warehouse_id = $warehouse_id;
                            $order_product_allocate_record->suppliers_id = $supplier_id;
                            $order_product_allocate_record->location_id = $location_id;
                            $order_product_allocate_record->batch_id = $batch_id;
                            $order_product_allocate_record->platform_id = $order_record->platform_id;
                            $order_product_allocate_record->orders_id = $order_record->orders_id;
                            $order_product_allocate_record->prid = $product_record->products_id;
                            $order_product_allocate_record->products_id = $u_product_id;
                        }
                        $order_product_allocate_record->allocate_received += $stock_quantity;
                        $order_product_allocate_record->is_temporary = $is_temporary;
                        $order_product_allocate_record->datetime = date('Y-m-d H:i:s');
                        $order_product_allocate_record->suppliers_price = \common\models\Suppliers_Products::get_suppliers_price($u_product_id, $supplier_id);
                        $order_product_allocate_record->save(false);
                    } catch (\Exception $exc) {
                        \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Helper.OrderProduct.doAllocateAutomaticWarehouse.ABP.save');
                    }
                    unset($order_product_allocate_record);
                }
                unset($stock_quantity);
            }
        }
        unset($product_allocated_array);
        unset($warehouse_product);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($batch_id);
        unset($multiplier);
        unset($layer_id);
        unset($type);
        // EOF ALLOCATE BY PRIORITY
        unset($product_allocated_temporary_array);
        unset($warehouse_product_priority_array);
        unset($product_quantity_real);
        unset($warehouse_id_array);
        unset($supplier_id_array);
        unset($location_id_array);
        unset($batch_id_array);
        unset($product_record);
        unset($layer_id_array);
        unset($order_record);
        unset($is_temporary);
        unset($u_product_id);
        try {
            $order_product_record->qty_rcvd = $product_quantity_received;
            $order_product_record->save();
        } catch (\Exception $exc) {
            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Helper.OrderProduct.doAllocateAutomaticWarehouse.orderProductRecord.save');
        }
        unset($product_quantity_received);
        unset($order_product_record);
        return true;
    }
    /**
     * Automatically allocating stock for Order Product from Warehouse unlimited stock.
     * Rules: Received Dispatched Allocations locked on amount of Dispatched quantity.
     * Behaviour: updating already present Allocations by Warehouse/Supplier/Location/Layers/Batch (W/S/L/L/B) priority, cleaning up unavailable Allocations, Allocating deficit by W/S/L/L/B priority
     * @param \common\models\OrdersProducts $orderProductRecord
     * @param \common\models\Orders $orderRecord
     * @param \common\models\Products $productRecord
     * @return boolean false on error, true on success
     */
    private static function do_allocate_automatic_unlimited(\common\models\Orders_Products $order_product_record, \common\models\Orders $order_record, \common\models\Products $product_record)
    {
        $u_product_id = \common\helpers\Inventory::get_inventory_id($order_product_record->uprid);
        $product_quantity_real = self::get_quantity_real($order_product_record);
        $warehouse_id_array = \common\helpers\Product::get_warehouse_id_priority_array($u_product_id, $product_quantity_real, $order_record->platform_id);
        $supplier_id_array = \common\helpers\Product::get_supplier_id_priority_array($u_product_id);
        $location_id_array = \common\helpers\Product::get_location_id_priority_array($u_product_id);
        $layer_id_array = \common\helpers\Product::get_layers_id_priority_array($u_product_id);
        $batch_id_array = \common\helpers\Product::get_batch_id_priority_array($u_product_id);
        $product_quantity_received = 0;
        $order_product_allocated_array = [];
        foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocated_record) {
            $order_product_allocated_array[$order_product_allocated_record->warehouse_id][$order_product_allocated_record->suppliers_id][$order_product_allocated_record->location_id][$order_product_allocated_record->layers_id][$order_product_allocated_record->batch_id] = $order_product_allocated_record;
            $product_quantity_received += $order_product_allocated_record->allocate_dispatched;
        }
        unset($order_product_allocated_record);
        // UPDATE EXISTING ALLOCATION BY PRIORITY
        if (count($order_product_allocated_array) > 0) {
            foreach ($layer_id_array as $layer_id) {
                foreach ($warehouse_id_array as $warehouse_id) {
                    foreach ($supplier_id_array as $supplier_id) {
                        foreach ($location_id_array as $location_id) {
                            foreach ($batch_id_array as $batch_id) {
                                if ($product_quantity_received >= $product_quantity_real) {
                                    break 5;
                                }
                                if (isset($order_product_allocated_array[$warehouse_id][$supplier_id][$location_id][$layer_id][$batch_id])) {
                                    $order_product_allocated = $order_product_allocated_array[$warehouse_id][$supplier_id][$location_id][$layer_id][$batch_id];
                                    $order_product_allocated->products_id = $u_product_id;
                                    $stock_received = $product_quantity_real - $product_quantity_received + $order_product_allocated->allocate_dispatched;
                                    $product_quantity_received += $stock_received - $order_product_allocated->allocate_dispatched;
                                    $order_product_allocated->allocate_received = $stock_received;
                                    try {
                                        $stock_received > 0 ? $order_product_allocated->save() : $order_product_allocated->delete();
                                    } catch (\Exception $exc) {
                                    }
                                    unset($stock_received);
                                    unset($order_product_allocated);
                                    unset($order_product_allocated_array[$warehouse_id][$supplier_id][$location_id][$layer_id][$batch_id]);
                                }
                            }
                        }
                    }
                }
            }
            unset($warehouse_id);
            unset($supplier_id);
            unset($location_id);
            unset($layer_id);
            unset($batch_id);
        }
        // EOF UPDATE EXISTING ALLOCATION BY PRIORITY
        // UPDATE EXISTING NON PRIORITY ALLOCATION
        foreach ($order_product_allocated_array as $warehouse_id => $supplier_array) {
            foreach ($supplier_array as $supplier_id => $location_array) {
                foreach ($location_array as $location_id => $layers_array) {
                    foreach ($layers_array as $layer_id => $batch_array) {
                        foreach ($batch_array as $batch_id => $order_product_allocated) {
                            if ($product_quantity_received >= $product_quantity_real and $product_quantity_received == self::get_received($order_product_record, true)) {
                                break 5;
                            }
                            if ($order_product_allocated->allocate_dispatched > 0) {
                                $order_product_allocated->allocate_received = $order_product_allocated->allocate_dispatched;
                                try {
                                    $order_product_allocated->save();
                                } catch (\Exception $exc) {
                                }
                            } else {
                                try {
                                    $order_product_allocated->delete();
                                } catch (\Exception $exc) {
                                }
                            }
                        }
                    }
                }
            }
        }
        unset($order_product_allocated);
        unset($location_array);
        unset($layers_array);
        unset($batch_array);
        unset($supplier_array);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        // EOF UPDATE EXISTING NON PRIORITY ALLOCATION
        unset($order_product_allocated_array);
        // ALLOCATE BY PRIORITY
        $product_allocated_array = [];
        foreach (\common\helpers\Product::get_allocated_array($u_product_id) as $product_allocated_record) {
            $product_allocated_array[$product_allocated_record['warehouse_id']][$product_allocated_record['suppliers_id']][$product_allocated_record['location_id']][$product_allocated_record['layers_id']][$product_allocated_record['batch_id']][] = $product_allocated_record;
        }
        unset($product_allocated_record);
        foreach ($layer_id_array as $layer_id) {
            foreach ($warehouse_id_array as $warehouse_id) {
                foreach ($supplier_id_array as $supplier_id) {
                    foreach ($location_id_array as $location_id) {
                        foreach ($batch_id_array as $batch_id) {
                            if ($product_quantity_received >= $product_quantity_real) {
                                break 5;
                            }
                            if (isset($product_allocated_array[$warehouse_id][$supplier_id][$location_id][$layer_id][$batch_id])) {
                                foreach ($product_allocated_array[$warehouse_id][$supplier_id][$location_id][$layer_id][$batch_id] as $product_allocated) {
                                    if ($product_allocated['orders_products_id'] == $order_product_record->orders_products_id) {
                                        unset($product_allocated);
                                        continue 2;
                                    }
                                }
                                unset($product_allocated);
                            }
                            $stock_received = $product_quantity_real - $product_quantity_received;
                            $product_quantity_received += $stock_received;
                            $order_product_allocate_record = new \common\models\Orders_Products_Allocate();
                            try {
                                $order_product_allocate_record->orders_products_id = $order_product_record->orders_products_id;
                                $order_product_allocate_record->warehouse_id = $warehouse_id;
                                $order_product_allocate_record->suppliers_id = $supplier_id;
                                $order_product_allocate_record->location_id = $location_id;
                                $order_product_allocate_record->layers_id = $layer_id;
                                $order_product_allocate_record->batch_id = $batch_id;
                                $order_product_allocate_record->platform_id = $order_record->platform_id;
                                $order_product_allocate_record->orders_id = $order_record->orders_id;
                                $order_product_allocate_record->prid = $product_record->products_id;
                                $order_product_allocate_record->products_id = $u_product_id;
                                $order_product_allocate_record->allocate_received = $stock_received;
                                $order_product_allocate_record->suppliers_price = \common\models\Suppliers_Products::get_suppliers_price($u_product_id, $supplier_id);
                                $order_product_allocate_record->save();
                                unset($order_product_allocate_record);
                            } catch (\Exception $exc) {
                            }
                            unset($stock_received);
                        }
                    }
                }
            }
        }
        unset($product_allocated_array);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        // EOF ALLOCATE BY PRIORITY
        unset($product_quantity_real);
        unset($warehouse_id_array);
        unset($supplier_id_array);
        unset($location_id_array);
        unset($layer_id_array);
        unset($batch_id_array);
        unset($product_record);
        unset($order_record);
        unset($u_product_id);
        try {
            $order_product_record->qty_rcvd = $product_quantity_received;
            $order_product_record->save();
        } catch (\Exception $exc) {
        }
        unset($product_quantity_received);
        return true;
    }
    /**
     * Allocate Order Product specific quantity. Will restock Dispatched products
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param integer $quantity pointer to Quantity to Quote. Will be updated with real Received quantity
     * @param mixed $warehouseId Warehouse id
     * @param mixed $supplierId Supplier id
     * @param mixed $locationId Location id
     * @param mixed $layerId Layer id
     * @param mixed $batchId Batch id
     * @return boolean false on error, true on success
     */
    public static function do_allocate_specific($order_product_record = 0, &$quantity = 0, $warehouse_id = 0, $supplier_id = 0, $location_id = 0, $layer_id = 0, $batch_id = 0)
    {
        global $login_id;
        $warehouse_id = (int) $warehouse_id;
        $supplier_id = (int) $supplier_id;
        $location_id = (int) $location_id;
        $layer_id = (int) $layer_id;
        $batch_id = (int) $batch_id;
        $quantity_awaiting = (int) $quantity;
        $quantity = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($quantity_awaiting >= 0 and $order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) == true) {
                $op_allocate_record = \common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layer_id])->and_where(['batch_id' => $batch_id])->as_array(false)->one();
                if ($op_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                    if ($quantity_awaiting > (int) $op_allocate_record->allocate_received) {
                        $quantity_real = self::get_quantity_real($order_product_record);
                        $quantity_received = self::get_received($order_product_record, true);
                        if ($quantity_awaiting > $quantity_real - $quantity_received + (int) $op_allocate_record->allocate_received) {
                            $quantity_awaiting = $quantity_real - $quantity_received + (int) $op_allocate_record->allocate_received;
                        }
                        unset($quantity_received);
                        unset($quantity_real);
                        $quantity_awaiting -= (int) $op_allocate_record->allocate_received;
                        $wp_record = \common\models\Warehouses_Products::find()->where(['prid' => $op_allocate_record->prid])->and_where(['products_id' => $op_allocate_record->products_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layer_id])->and_where(['batch_id' => $batch_id])->as_array(true)->one();
                        if ($quantity_awaiting > 0 and is_array($wp_record) and (int) $wp_record['products_quantity'] > 0) {
                            if ($quantity_awaiting > (int) $wp_record['products_quantity']) {
                                $quantity_awaiting = (int) $wp_record['products_quantity'];
                            }
                            $op_allocate_record->allocate_received += $quantity_awaiting;
                            try {
                                $op_allocate_record->save();
                            } catch (\Exception $exc) {
                                $quantity_awaiting = 0;
                            }
                        } else {
                            $quantity_awaiting = 0;
                        }
                        unset($wp_record);
                    } elseif ($quantity_awaiting < $op_allocate_record->allocate_received) {
                        $quantity_awaiting = (int) $op_allocate_record->allocate_received - $quantity_awaiting;
                        self::do_quote_specific($order_product_record, $quantity_awaiting, $warehouse_id, $supplier_id, $location_id, $layer_id, $batch_id);
                    }
                } elseif ($quantity_awaiting > 0) {
                    $order_record = \common\helpers\Order::get_record($order_product_record->orders_id);
                    if ($order_record instanceof \common\models\Orders) {
                        $u_product_id = \common\helpers\Inventory::get_inventory_id($order_product_record->uprid);
                        $wp_record = \common\models\Warehouses_Products::find()->where(['prid' => (int) $u_product_id])->and_where(['products_id' => $u_product_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layer_id])->and_where(['batch_id' => $batch_id])->as_array(true)->one();
                        if (is_array($wp_record)) {
                            if ((int) $wp_record['products_quantity'] > 0) {
                                if ($quantity_awaiting > (int) $wp_record['products_quantity']) {
                                    $quantity_awaiting = (int) $wp_record['products_quantity'];
                                }
                                $op_allocate_record = new \common\models\Orders_Products_Allocate();
                                $op_allocate_record->orders_products_id = $order_product_record->orders_products_id;
                                $op_allocate_record->warehouse_id = $warehouse_id;
                                $op_allocate_record->suppliers_id = $supplier_id;
                                $op_allocate_record->location_id = $location_id;
                                $op_allocate_record->layers_id = $layer_id;
                                $op_allocate_record->batch_id = $batch_id;
                                $op_allocate_record->platform_id = $order_record->platform_id;
                                $op_allocate_record->orders_id = $order_record->orders_id;
                                $op_allocate_record->prid = $wp_record['prid'];
                                $op_allocate_record->products_id = $wp_record['products_id'];
                                $op_allocate_record->allocate_received += $quantity_awaiting;
                                $op_allocate_record->suppliers_price = \common\models\Suppliers_Products::get_suppliers_price($wp_record['products_id'], $supplier_id);
                                try {
                                    $op_allocate_record->save();
                                } catch (\Exception $exc) {
                                    $quantity_awaiting = 0;
                                }
                            } else {
                                $quantity_awaiting = 0;
                            }
                        }
                        unset($u_product_id);
                        unset($wp_record);
                    }
                    unset($order_record);
                }
                unset($op_allocate_record);
                if ($quantity_awaiting > 0) {
                    $quantity = $quantity_awaiting;
                    self::evaluate($order_product_record);
                    \common\helpers\Product::do_cache($order_product_record->products_id);
                }
            }
        }
        unset($order_product_record);
        unset($quantity_awaiting);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        unset($login_id);
        return $quantity > 0 ? true : false;
    }
    /**
     * Dispatch Order Product
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $isForced defines should be Order Product set as Dispatched even if there is no stock available
     * @return boolean false on error, true on success
     */
    public static function do_dispatch($order_product_record = 0, $is_forced = false)
    {
        global $login_id;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) != true) {
                return false;
            }
            $quantity_dispatch = self::get_quantity_real($order_product_record) - self::get_dispatched($order_product_record);
            if ($quantity_dispatch <= 0) {
                return true;
            }
            $update_stock_params = ['orders_id' => $order_product_record->orders_id, 'admin_id' => $login_id];
            if ($order_product_record->has_method('stockUpdateExtraParams')) {
                $order_product_stock_params = $order_product_record->stock_update_extra_params();
                $update_stock_params = array_merge($order_product_stock_params, $update_stock_params);
            }
            $u_product_id = \common\helpers\Inventory::get_inventory_id($order_product_record->uprid);
            $warehouse_stock_quantity = 0;
            foreach (\common\helpers\Warehouses::get_product_array($u_product_id) as $warehouse_product_record) {
                $warehouse_stock_quantity += $warehouse_product_record['warehouse_stock_quantity'];
            }
            unset($warehouse_product_record);
            foreach (\common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id])->and_where('allocate_received > allocate_dispatched')->all() as $order_product_allocate_record) {
                if ($u_product_id !== $order_product_allocate_record->products_id) {
                    continue;
                }
                $quantity_awaiting = (int) $order_product_allocate_record->allocate_received - (int) $order_product_allocate_record->allocate_dispatched;
                if ($quantity_awaiting > $quantity_dispatch) {
                    $quantity_awaiting = $quantity_dispatch;
                }
                $warehouse_stock_quantity_new = \common\helpers\Warehouses::update_products_quantity($u_product_id, $order_product_allocate_record->warehouse_id, $quantity_awaiting, '-', $order_product_allocate_record->suppliers_id, $order_product_allocate_record->location_id, array_merge($update_stock_params, ['layers_id' => $order_product_allocate_record->layers_id, 'batch_id' => $order_product_allocate_record->batch_id]));
                if ($warehouse_stock_quantity_new < $warehouse_stock_quantity) {
                    $quantity_awaiting = $warehouse_stock_quantity - $warehouse_stock_quantity_new;
                    $order_product_allocate_record->allocate_dispatched += $quantity_awaiting;
                    try {
                        $order_product_allocate_record->save();
                        $warehouse_stock_quantity = $warehouse_stock_quantity_new;
                    } catch (\Exception $exc) {
                        $warehouse_stock_quantity = \common\helpers\Warehouses::update_products_quantity($u_product_id, $order_product_allocate_record->warehouse_id, $quantity_awaiting, '+', $order_product_allocate_record->suppliers_id, $order_product_allocate_record->location_id, array_merge($update_stock_params, ['layers_id' => $order_product_allocate_record->layers_id, 'batch_id' => $order_product_allocate_record->batch_id, 'comments' => TEXT_ORDER_PRODUCT_DO_DISPATCH_ERROR_RESTOCK]));
                        $quantity_awaiting = 0;
                    }
                    $quantity_dispatch -= $quantity_awaiting;
                }
                if ($quantity_dispatch <= 0) {
                    break;
                }
            }
            unset($order_product_allocate_record);
            unset($warehouse_stock_quantity_new);
            unset($warehouse_stock_quantity);
            unset($quantity_awaiting);
            unset($login_id);
            if ((int) $is_forced > 0 and $quantity_dispatch > 0) {
                $order_record = \common\helpers\Order::get_record($order_product_record->orders_id);
                if ($order_record instanceof \common\models\Orders) {
                    $warehouse_id_array = \common\helpers\Product::get_warehouse_id_priority_array($u_product_id, $quantity_dispatch, $order_record->platform_id);
                    $supplier_id_array = \common\helpers\Product::get_supplier_id_priority_array($u_product_id);
                    foreach ($warehouse_id_array as $warehouse_id) {
                        foreach ($supplier_id_array as $supplier_id) {
                            $location_id = 0;
                            $layer_id = 0;
                            $batch_id = 0;
                            $order_product_allocate_record = \common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layer_id])->and_where(['batch_id' => $batch_id])->one();
                            if (!$order_product_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                                $order_product_allocate_record = new \common\models\Orders_Products_Allocate();
                                $order_product_allocate_record->orders_products_id = $order_product_record->orders_products_id;
                                $order_product_allocate_record->warehouse_id = $warehouse_id;
                                $order_product_allocate_record->suppliers_id = $supplier_id;
                                $order_product_allocate_record->location_id = $location_id;
                                $order_product_allocate_record->layers_id = $layer_id;
                                $order_product_allocate_record->batch_id = $batch_id;
                                $order_product_allocate_record->platform_id = $order_record->platform_id;
                                $order_product_allocate_record->orders_id = $order_record->orders_id;
                                $order_product_allocate_record->prid = $order_product_record->products_id;
                                $order_product_allocate_record->products_id = $u_product_id;
                                $order_product_allocate_record->suppliers_price = \common\models\Suppliers_Products::get_suppliers_price($u_product_id, $supplier_id);
                            }
                            $order_product_allocate_record->allocate_dispatched += $quantity_dispatch;
                            $order_product_allocate_record->allocate_received = $order_product_allocate_record->allocate_dispatched;
                            try {
                                $order_product_allocate_record->save();
                                $quantity_dispatch = 0;
                            } catch (\Exception $exc) {
                            }
                            unset($order_product_allocate_record);
                            if ($quantity_dispatch == 0) {
                                break 2;
                            }
                        }
                    }
                    unset($warehouse_id_array);
                    unset($supplier_id_array);
                    unset($warehouse_id);
                    unset($supplier_id);
                    unset($location_id);
                }
                unset($order_record);
            }
            if ($quantity_dispatch <= 0) {
                \common\helpers\Order::update_allocate_allow($order_product_record->orders_id, 1);
                self::do_allocate_automatic($order_product_record, true);
            } else {
                self::evaluate($order_product_record);
                \common\helpers\Product::do_cache($order_product_record->products_id);
            }
            unset($order_product_record);
            unset($quantity_dispatch);
            unset($u_product_id);
            unset($is_forced);
            return true;
        }
        return false;
    }
    /**
     * Dispatch Order Product specific quantity
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param integer $quantity pointer to Quantity to Dispatch. Will be updated with real Dispatched quantity
     * @param mixed $warehouseId Warehouse id. Dispatch from specific Warehouse if passed
     * @param mixed $supplierId Supplier id. Dispatch from specific Supplier if passed
     * @param mixed $locationId Location id. Dispatch from specific Location if passed
     * @param mixed $layerId Layer id. Dispatch from specific Layer if passed
     * @param mixed $batchId Batch id. Dispatch from specific Batch if passed
     * @return boolean false on error, true on success
     */
    public static function do_dispatch_specific($order_product_record = 0, &$quantity = 0, $warehouse_id = false, $supplier_id = false, $location_id = false, $layer_id = false, $batch_id = false)
    {
        global $login_id;
        $quantity_awaiting = (int) $quantity;
        $quantity = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($quantity_awaiting > 0 and $order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) == true) {
                $update_stock_params = ['orders_id' => $order_product_record->orders_id, 'admin_id' => $login_id];
                if ($order_product_record->has_method('stockUpdateExtraParams')) {
                    $order_product_stock_params = $order_product_record->stock_update_extra_params();
                    $update_stock_params = array_merge($order_product_stock_params, $update_stock_params);
                }
                $quantity_dispatched = 0;
                foreach (self::get_allocated_array($order_product_record, false) as $op_allocate_record) {
                    if ($quantity_awaiting <= 0) {
                        break;
                    }
                    if ($warehouse_id !== false and $warehouse_id != $op_allocate_record->warehouse_id) {
                        continue;
                    }
                    if ($supplier_id !== false and $supplier_id != $op_allocate_record->suppliers_id) {
                        continue;
                    }
                    if ($location_id !== false and $location_id != $op_allocate_record->location_id) {
                        continue;
                    }
                    if ($layer_id !== false and $layer_id != $op_allocate_record->layers_id) {
                        continue;
                    }
                    if ($batch_id !== false and $batch_id != $op_allocate_record->batch_id) {
                        continue;
                    }
                    $awaiting_dispatch = (int) $op_allocate_record->allocate_received - (int) $op_allocate_record->allocate_dispatched;
                    if ($awaiting_dispatch > 0) {
                        if ($awaiting_dispatch > $quantity_awaiting) {
                            $awaiting_dispatch = $quantity_awaiting;
                        }
                        $quantity_warehouse = \common\helpers\Warehouses::update_products_quantity($op_allocate_record->products_id, 0, 0, '+');
                        $quantity_warehouse_new = \common\helpers\Warehouses::update_products_quantity($op_allocate_record->products_id, $op_allocate_record->warehouse_id, $awaiting_dispatch, '-', $op_allocate_record->suppliers_id, $op_allocate_record->location_id, array_merge($update_stock_params, ['layers_id' => $op_allocate_record->layers_id, 'batch_id' => $op_allocate_record->batch_id]));
                        $quantity_warehouse -= $quantity_warehouse_new;
                        $awaiting_dispatch = ($quantity_warehouse >= 0 and $quantity_warehouse <= $awaiting_dispatch) ? $quantity_warehouse : $awaiting_dispatch;
                        unset($quantity_warehouse_new);
                        unset($quantity_warehouse);
                        if ($awaiting_dispatch > 0) {
                            $op_allocate_record->allocate_dispatched += $awaiting_dispatch;
                            try {
                                $op_allocate_record->save();
                                $quantity_awaiting -= $awaiting_dispatch;
                                $quantity_dispatched += $awaiting_dispatch;
                            } catch (\Exception $exc) {
                                \common\helpers\Warehouses::update_products_quantity($op_allocate_record->products_id, $op_allocate_record->warehouse_id, $awaiting_dispatch, '+', $op_allocate_record->suppliers_id, $op_allocate_record->location_id, array_merge($update_stock_params, ['layers_id' => $op_allocate_record->layers_id, 'batch_id' => $op_allocate_record->batch_id, 'comments' => TEXT_ORDER_PRODUCT_DO_DISPATCH_ERROR_RESTOCK]));
                            }
                        }
                    }
                    unset($awaiting_dispatch);
                }
                unset($op_allocate_record);
                if ($quantity_dispatched > 0) {
                    $quantity = $quantity_dispatched;
                    self::evaluate($order_product_record);
                    \common\helpers\Product::do_cache($order_product_record->products_id);
                }
                unset($quantity_dispatched);
            }
        }
        unset($order_product_record);
        unset($quantity_awaiting);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        unset($login_id);
        return $quantity > 0 ? true : false;
    }
    /**
     * Deliver Order Product
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $isForced defines should be Order Product set as Delivered even if there is quantity awaiting for Dispatch
     * @return boolean false on error, true on success
     */
    public static function do_deliver($order_product_record = 0, $is_forced = false)
    {
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if ((int) $is_forced > 0) {
                self::do_dispatch($order_product_record, true);
            }
            foreach (self::get_allocated_array($order_product_record, false) as $op_allocate_record) {
                $op_allocate_record->allocate_delivered = $op_allocate_record->allocate_dispatched;
                try {
                    $op_allocate_record->save();
                } catch (\Exception $exc) {
                }
            }
            unset($op_allocate_record);
            self::evaluate($order_product_record);
            unset($order_product_record);
            unset($is_forced);
            return true;
        }
        return false;
    }
    /**
     * Deliver Order Product specific quantity
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param integer $quantity pointer to Quantity to Deliver. Will be updated with real Delivered quantity
     * @param mixed $warehouseId Warehouse id
     * @param mixed $supplierId Supplier id
     * @param mixed $locationId Location id
     * @param mixed $layerId Layer id
     * @param mixed $batchId Batch id
     * @return boolean false on error, true on success
     */
    public static function do_deliver_specific($order_product_record = 0, &$quantity = 0, $warehouse_id = 0, $supplier_id = 0, $location_id = 0, $layer_id = 0, $batch_id = 0)
    {
        global $login_id;
        $warehouse_id = (int) $warehouse_id;
        $supplier_id = (int) $supplier_id;
        $location_id = (int) $location_id;
        $layer_id = (int) $layer_id;
        $batch_id = (int) $batch_id;
        $quantity_awaiting = (int) $quantity;
        $quantity = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($quantity_awaiting > 0 and $order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) == true) {
                $op_allocate_record = \common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layer_id])->and_where(['batch_id' => $batch_id])->as_array(false)->one();
                if ($op_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                    if ($quantity_awaiting > (int) $op_allocate_record->allocate_dispatched - (int) $op_allocate_record->allocate_delivered) {
                        $quantity_awaiting = (int) $op_allocate_record->allocate_dispatched - (int) $op_allocate_record->allocate_delivered;
                    }
                    $op_allocate_record->allocate_delivered += $quantity_awaiting;
                    try {
                        $op_allocate_record->save();
                    } catch (\Exception $exc) {
                        $quantity_awaiting = 0;
                    }
                    if ($quantity_awaiting > 0) {
                        $quantity = $quantity_awaiting;
                        self::evaluate($order_product_record);
                    }
                }
                unset($op_allocate_record);
            }
        }
        unset($order_product_record);
        unset($quantity_awaiting);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        unset($login_id);
        return $quantity > 0 ? true : false;
    }
    /**
     * Cancel Order Product
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $isRestock defines should Dispatched quantity be returned to stock
     * @param string $messageStock returning to stock message
     * @return boolean false on error, true on success
     */
    public static function do_cancel($order_product_record = 0, $is_restock = false, $message_stock = '')
    {
        global $login_id;
        $message_stock = trim($message_stock);
        if ($message_stock == '') {
            $message_stock = TEXT_ORDER_PRODUCT_DO_CANCEL_RESTOCK;
        }
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) != true) {
                return false;
            }
            $update_stock_params = ['orders_id' => $order_product_record->orders_id, 'admin_id' => $login_id];
            if ($order_product_record->has_method('stockUpdateExtraParams')) {
                $order_product_stock_params = $order_product_record->stock_update_extra_params();
                $update_stock_params = array_merge($order_product_stock_params, $update_stock_params);
            }
            $return = true;
            $allocate_dispatched = 0;
            foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocate_record) {
                if ((int) $is_restock > 0) {
                    if ($order_product_allocate_record->allocate_dispatched > 0) {
                        \common\helpers\Warehouses::update_products_quantity($order_product_allocate_record->products_id, $order_product_allocate_record->warehouse_id, $order_product_allocate_record->allocate_dispatched, '+', $order_product_allocate_record->suppliers_id, $order_product_allocate_record->location_id, array_merge($update_stock_params, ['layers_id' => $order_product_allocate_record->layers_id, 'batch_id' => $order_product_allocate_record->batch_id, 'comments' => $message_stock]));
                    }
                    try {
                        $order_product_allocate_record->delete();
                    } catch (\Exception $exc) {
                        $return = false;
                    }
                } else {
                    $allocate_dispatched += $order_product_allocate_record->allocate_dispatched;
                    $order_product_allocate_record->allocate_received = $order_product_allocate_record->allocate_dispatched;
                    $order_product_allocate_record->is_temporary = 0;
                    $order_product_allocate_record->datetime = date('Y-m-d H:i:s');
                    try {
                        $order_product_allocate_record->save();
                    } catch (\Exception $exc) {
                        $return = false;
                    }
                }
            }
            unset($order_product_allocate_record);
            $order_product_record->qty_cnld = $order_product_record->products_quantity - $allocate_dispatched;
            unset($allocate_dispatched);
            try {
                $order_product_record->save();
            } catch (\Exception $exc) {
                $return = false;
            }
            self::evaluate($order_product_record);
            \common\helpers\Product::do_cache($order_product_record->products_id);
            unset($order_product_record);
            unset($is_restock);
            unset($login_id);
            return $return;
        }
        return false;
    }
    /**
     * Cancel Order Product specific quantity. Will restock Dispatched products
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param integer $quantity pointer to Quantity to Cancel. Will be updated with real Cancelled (Quoted) quantity
     * @param boolean $isUseDeficit if true - stock deficit will be used in cancellation. If false - function will try to quote, restock and cancel $quantity
     * @param mixed $warehouseId Warehouse id for specific warehouse
     * @param mixed $supplierId Supplier id for specific supplier
     * @param mixed $locationId Location id for specific location
     * @param mixed $layerId Layer id for specific layer
     * @param mixed $batchId Batch id for specific batch
     * @return boolean false on error, true on success
     */
    public static function do_cancel_specific($order_product_record = 0, &$quantity = 0, $is_use_deficit = false, $warehouse_id = 0, $supplier_id = 0, $location_id = 0, $layer_id = 0, $batch_id = 0)
    {
        $warehouse_id = (int) $warehouse_id;
        $supplier_id = (int) $supplier_id;
        $location_id = (int) $location_id;
        $layer_id = (int) $layer_id;
        $batch_id = (int) $batch_id;
        $quantity_quote = (int) $quantity;
        $quantity = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($quantity_quote > 0 and $order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) == true) {
                if ((int) $is_use_deficit > 0) {
                    $quantity_quote_tmp = $quantity_quote - self::get_stock_deficit($order_product_record);
                    $quantity_quote_tmp = $quantity_quote_tmp > 0 ? $quantity_quote_tmp : 0;
                } else {
                    $quantity_quote_tmp = $quantity_quote;
                }
                unset($is_use_deficit);
                if ($quantity_quote_tmp > 0) {
                    $op_allocate_query = \common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id]);
                    if ($warehouse_id > 0) {
                        $op_allocate_query->and_where(['warehouse_id' => $warehouse_id]);
                    }
                    if ($supplier_id > 0) {
                        $op_allocate_query->and_where(['suppliers_id' => $supplier_id]);
                    }
                    if ($location_id > 0) {
                        $op_allocate_query->and_where(['location_id' => $location_id]);
                    }
                    if ($layer_id > 0) {
                        $op_allocate_query->and_where(['layers_id' => $layer_id]);
                    }
                    if ($batch_id > 0) {
                        $op_allocate_query->and_where(['batch_id' => $batch_id]);
                    }
                    foreach ($op_allocate_query->as_array(true)->all() as $op_allocate_record) {
                        $quantity_awaiting = $quantity_quote_tmp;
                        if (\common\helpers\Order_Product::do_quote_specific($order_product_record, $quantity_awaiting, $op_allocate_record['warehouse_id'], $op_allocate_record['suppliers_id'], $op_allocate_record['location_id'], $op_allocate_record['layers_id'], $op_allocate_record['batch_id'])) {
                            $quantity_quote_tmp -= $quantity_awaiting;
                            if ($quantity_quote_tmp <= 0) {
                                $quantity_quote_tmp = 0;
                                break;
                            }
                        }
                    }
                    unset($op_allocate_record);
                    unset($op_allocate_query);
                }
                unset($quantity_awaiting);
                $quantity = $quantity_quote - $quantity_quote_tmp;
                unset($quantity_quote_tmp);
                $order_product_record->qty_cnld += $quantity;
                $order_product_record->qty_cnld = $order_product_record->qty_cnld > $order_product_record->products_quantity ? $order_product_record->products_quantity : $order_product_record->qty_cnld;
                try {
                    $order_product_record->save();
                } catch (\Exception $exc) {
                }
                self::evaluate($order_product_record);
                \common\helpers\Product::do_cache($order_product_record->products_id);
            }
        }
        unset($order_product_record);
        unset($quantity_quote);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        return $quantity > 0 ? true : false;
    }
    /**
     * Quote Order Product
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $isReset defines should Cancelled quantity be reset to 0
     * @return boolean false on error, true on success
     */
    public static function do_quote($order_product_record = 0, $is_reset = false)
    {
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $qty_cnld = $order_product_record->qty_cnld;
            if (self::do_cancel($order_product_record, true, TEXT_ORDER_PRODUCT_DO_QUOTE_RESTOCK) != true) {
                return false;
            }
            if ((int) $is_reset > 0) {
                $qty_cnld = 0;
            }
            $order_product_record->qty_cnld = $qty_cnld;
            unset($qty_cnld);
            try {
                $order_product_record->save();
            } catch (\Exception $exc) {
                return false;
            }
            self::evaluate($order_product_record);
            if ($order_product_record->qty_rcvd == 0 and $order_product_record->orders_products_status == self::OPS_STOCK_DEFICIT) {
                $order_product_record->orders_products_status = self::OPS_QUOTED;
                $order_product_record->orders_products_status_manual = 0;
                try {
                    $order_product_record->save();
                } catch (\Exception $exc) {
                }
            }
            unset($order_product_record);
            unset($is_reset);
            return true;
        }
        return false;
    }
    /**
     * Quote Order Product specific quantity. Will restock Dispatched products
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param integer $quantity pointer to Quantity to Quote. Will be updated with real Quoted quantity
     * @param mixed $warehouseId Warehouse id
     * @param mixed $supplierId Supplier id
     * @param mixed $locationId Location id
     * @param mixed $layerId Layer id
     * @param mixed $batchId Batch id
     * @return boolean false on error, true on success
     */
    public static function do_quote_specific($order_product_record = 0, &$quantity = 0, $warehouse_id = 0, $supplier_id = 0, $location_id = 0, $layer_id = 0, $batch_id = 0)
    {
        global $login_id;
        $warehouse_id = (int) $warehouse_id;
        $supplier_id = (int) $supplier_id;
        $location_id = (int) $location_id;
        $layer_id = (int) $layer_id;
        $batch_id = (int) $batch_id;
        $quantity_awaiting = (int) $quantity;
        $quantity = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($quantity_awaiting > 0 and $order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) == true) {
                $update_stock_params = ['orders_id' => $order_product_record->orders_id, 'admin_id' => $login_id];
                if ($order_product_record->has_method('stockUpdateExtraParams')) {
                    $order_product_stock_params = $order_product_record->stock_update_extra_params();
                    $update_stock_params = array_merge($order_product_stock_params, $update_stock_params);
                }
                $op_allocate_record = \common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layer_id])->and_where(['batch_id' => $batch_id])->as_array(false)->one();
                if ($op_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                    if ($quantity_awaiting > (int) $op_allocate_record->allocate_received) {
                        $quantity_awaiting = (int) $op_allocate_record->allocate_received;
                    }
                    $quantity_restock = (int) $op_allocate_record->allocate_received - (int) $op_allocate_record->allocate_dispatched;
                    $quantity_restock = $quantity_awaiting - $quantity_restock;
                    $quantity_restock = $quantity_restock > 0 ? $quantity_restock : 0;
                    if ($quantity_restock > 0) {
                        $quantity_warehouse = \common\helpers\Warehouses::update_products_quantity($op_allocate_record->products_id, 0, 0, '+');
                        $quantity_warehouse_new = \common\helpers\Warehouses::update_products_quantity($op_allocate_record->products_id, $op_allocate_record->warehouse_id, $quantity_restock, '+', $op_allocate_record->suppliers_id, $op_allocate_record->location_id, array_merge($update_stock_params, ['layers_id' => $op_allocate_record->layers_id, 'batch_id' => $op_allocate_record->batch_id, 'comments' => TEXT_ORDER_PRODUCT_DO_QUOTE_RESTOCK]));
                        if ($quantity_warehouse == $quantity_warehouse_new) {
                            $quantity_restock = 0;
                        }
                        unset($quantity_warehouse_new);
                        unset($quantity_warehouse);
                    }
                    $op_allocate_record->allocate_dispatched -= $quantity_restock;
                    unset($quantity_restock);
                    if ($op_allocate_record->allocate_delivered > $op_allocate_record->allocate_dispatched) {
                        $op_allocate_record->allocate_delivered = $op_allocate_record->allocate_dispatched;
                    }
                    $op_allocate_record->allocate_received -= $quantity_awaiting;
                    try {
                        if ($op_allocate_record->allocate_received <= 0) {
                            $op_allocate_record->delete();
                        } else {
                            $op_allocate_record->save();
                        }
                    } catch (\Exception $exc) {
                        $quantity_awaiting = 0;
                    }
                    if ($quantity_awaiting > 0) {
                        $quantity = $quantity_awaiting;
                        self::evaluate($order_product_record);
                        \common\helpers\Product::do_cache($order_product_record->products_id);
                        if ($order_product_record->qty_rcvd == 0 and $order_product_record->orders_products_status == self::OPS_STOCK_DEFICIT) {
                            $order_product_record->orders_products_status = self::OPS_QUOTED;
                            $order_product_record->orders_products_status_manual = 0;
                            try {
                                $order_product_record->save();
                            } catch (\Exception $exc) {
                            }
                        }
                    }
                }
                unset($op_allocate_record);
            }
        }
        unset($order_product_record);
        unset($quantity_awaiting);
        unset($warehouse_id);
        unset($supplier_id);
        unset($location_id);
        unset($layer_id);
        unset($batch_id);
        unset($login_id);
        return $quantity > 0 ? true : false;
    }
    /**
     * Validate and updating Order Product Allocation records.
     * Updating Dispatched based on Delivered and Received based on Disptached.
     * Deleting empty allocation records where Received equals 0.
     * Rule: Received >= Dispatched >= Delivered
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return boolean false on error or Dispatched > Order Product Quantity Real, true - if validation is passed
     */
    public static function is_valid_allocated($order_product_record = 0)
    {
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $order_product_quantity_real = self::get_quantity_real($order_product_record);
            $order_product_quantity_received = 0;
            $order_product_quantity_dispatched = 0;
            $order_product_quantity_delivered = 0;
            foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocated) {
                if ($order_product_allocated->allocate_dispatched > 0 or $order_product_allocated->allocate_delivered > 0) {
                    $is_save = false;
                    if ($order_product_allocated->allocate_delivered > $order_product_allocated->allocate_dispatched) {
                        $order_product_allocated->allocate_dispatched = $order_product_allocated->allocate_delivered;
                        $is_save = true;
                    }
                    if ($order_product_allocated->allocate_dispatched > $order_product_allocated->allocate_received) {
                        $order_product_allocated->allocate_received = $order_product_allocated->allocate_dispatched;
                        $is_save = true;
                    }
                    if ($is_save == true) {
                        try {
                            $order_product_allocated->save();
                        } catch (\Exception $exc) {
                            return false;
                        }
                    }
                    unset($is_save);
                } elseif ($order_product_allocated->allocate_received == 0) {
                    try {
                        $order_product_allocated->delete();
                        continue;
                    } catch (\Exception $exc) {
                        return false;
                    }
                }
                $order_product_quantity_received += $order_product_allocated->allocate_received;
                $order_product_quantity_dispatched += $order_product_allocated->allocate_dispatched;
                $order_product_quantity_delivered += $order_product_allocated->allocate_delivered;
            }
            unset($order_product_allocated);
            if ($order_product_record->qty_rcvd != $order_product_quantity_received or $order_product_record->qty_dspd != $order_product_quantity_dispatched or $order_product_record->qty_dlvd != $order_product_quantity_delivered) {
                try {
                    $order_product_record->qty_rcvd = $order_product_quantity_received;
                    $order_product_record->qty_dspd = $order_product_quantity_dispatched;
                    $order_product_record->qty_dlvd = $order_product_quantity_delivered;
                    $order_product_record->save();
                } catch (\Exception $exc) {
                    return false;
                }
            }
            unset($order_product_quantity_delivered);
            unset($order_product_quantity_received);
            // PRODUCT ASSET AUTO ASSIGN
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
                try {
                    $ext::validate_assign($order_product_record);
                } catch (\Exception $exc) {
                    \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'ErrorProductAssetsValidateAssign');
                }
            }
            // EOF PRODUCT ASSET AUTO ASSIGN
            unset($order_product_record);
            return $order_product_quantity_dispatched <= $order_product_quantity_real;
        }
        return false;
    }
    /**
     * Get Order Product Parent
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $asArray switching return type between array or instance of OrdersProducts
     * @return mixed dependent on $asArray parameter
     */
    public static function get_parent($order_product_record = 0, $as_array = true)
    {
        $return = false;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if (trim($order_product_record->parent_product) != '') {
                $return = \common\models\Orders_Products::find()->where(['orders_id' => $order_product_record->orders_id])->and_where(['template_uprid' => trim($order_product_record->parent_product)])->and_where(['!=', 'orders_products_id', $order_product_record->orders_products_id])->as_array($as_array)->one();
            }
        }
        unset($order_product_record);
        unset($as_array);
        return $return;
    }
    /**
     * Get Order Product Child array
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $asArray switching return type between array of arrays or array of instances of OrdersProducts
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_child_array($order_product_record = 0, $as_array = true)
    {
        $return = [];
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if (trim($order_product_record->sub_products) != '' && $order_product_record->relation_type != 'linked') {
                foreach (\common\models\Orders_Products::find()->where(['orders_id' => $order_product_record->orders_id])->and_where(['parent_product' => trim($order_product_record->template_uprid)])->and_where(['!=', 'orders_products_id', $order_product_record->orders_products_id])->as_array($as_array)->all() as $order_product_child_record) {
                    $return[] = $order_product_child_record;
                }
                unset($order_product_child_record);
            }
        }
        unset($order_product_record);
        unset($as_array);
        return $return;
    }
    /**
     * Automatically update Order Product Status based on Order Product Allocation
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return mixed false on error or current Order Product Status Id
     */
    public static function evaluate($order_product_record = 0)
    {
        $return = false;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if (self::is_valid_allocated($order_product_record) != true) {
                return $return;
            }
            $order_product_status = $order_product_record->orders_products_status;
            $return = $order_product_status;
            $is_parent = false;
            $order_product_quantity_real = -1;
            $order_product_received = -1;
            $order_product_dispatched = -1;
            $order_product_delivered = -1;
            $order_product_cancelled = self::get_cancelled($order_product_record);
            foreach (self::get_child_array($order_product_record, false) as $order_product_child_record) {
                $is_parent = true;
                $opc_quantity_multiplier = 1;
                if ((int) $order_product_record->products_quantity > 0) {
                    $opc_quantity_multiplier = (int) ceil((int) $order_product_child_record->products_quantity / (int) $order_product_record->products_quantity);
                }
                $opc_quantity_real = (int) floor(self::get_quantity_real($order_product_child_record) / $opc_quantity_multiplier);
                if ($order_product_quantity_real < 0 or $order_product_quantity_real > $opc_quantity_real) {
                    $order_product_quantity_real = $opc_quantity_real;
                }
                $opc_received = (int) floor(self::get_received($order_product_child_record) / $opc_quantity_multiplier);
                if ($order_product_received < 0 or $order_product_received > $opc_received) {
                    $order_product_received = $opc_received;
                }
                $opc_dispatched = (int) floor(self::get_dispatched($order_product_child_record) / $opc_quantity_multiplier);
                if ($order_product_dispatched < 0 or $order_product_dispatched > $opc_dispatched) {
                    $order_product_dispatched = $opc_dispatched;
                }
                $opc_delivered = (int) floor(self::get_delivered($order_product_child_record) / $opc_quantity_multiplier);
                if ($order_product_delivered < 0 or $order_product_delivered > $opc_delivered) {
                    $order_product_delivered = $opc_delivered;
                }
            }
            unset($order_product_child_record);
            unset($opc_quantity_multiplier);
            unset($opc_quantity_real);
            unset($opc_dispatched);
            unset($opc_delivered);
            unset($opc_received);
            if ($is_parent == true) {
                $order_product_cancelled = (int) $order_product_record->products_quantity - $order_product_quantity_real;
                foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocated) {
                    try {
                        $order_product_allocated->delete();
                    } catch (\Exception $exc) {
                    }
                }
                unset($order_product_allocated);
            } else {
                $order_product_quantity_real = self::get_quantity_real($order_product_record);
                $order_product_received = 0;
                $order_product_dispatched = 0;
                $order_product_delivered = 0;
            }
            if ($order_product_quantity_real <= 0) {
                $return = self::OPS_CANCELLED;
                foreach (self::get_allocated_array($order_product_record, false) as $order_product_allocated) {
                    try {
                        $order_product_allocated->delete();
                    } catch (\Exception $exc) {
                    }
                }
                unset($order_product_allocated);
            } else {
                foreach (self::get_allocated_array($order_product_record) as $order_product_allocated) {
                    $order_product_received += $order_product_allocated['allocate_received'];
                    $order_product_dispatched += $order_product_allocated['allocate_dispatched'];
                    $order_product_delivered += $order_product_allocated['allocate_delivered'];
                }
                unset($order_product_allocated);
                if ($order_product_quantity_real == $order_product_delivered) {
                    $return = self::OPS_DELIVERED;
                } elseif ($order_product_quantity_real == $order_product_dispatched) {
                    $return = self::OPS_DISPATCHED;
                } elseif ($order_product_quantity_real == $order_product_received) {
                    $return = self::OPS_RECEIVED;
                } else {
                    $return = self::OPS_STOCK_DEFICIT;
                    if ($order_product_status == self::OPS_STOCK_ORDERED) {
                        if (self::get_stock_ordered($order_product_record) > 0) {
                            $return = $order_product_status;
                        }
                    }
                }
            }
            if ($order_product_record->qty_rcvd != $order_product_received or $order_product_record->qty_dspd != $order_product_dispatched or $order_product_record->qty_dlvd != $order_product_delivered or $order_product_record->qty_cnld != $order_product_cancelled or $return != $order_product_status) {
                $order_product_record->qty_rcvd = $order_product_received;
                $order_product_record->qty_dspd = $order_product_dispatched;
                $order_product_record->qty_dlvd = $order_product_delivered;
                $order_product_record->qty_cnld = $order_product_cancelled;
                $order_product_record->orders_products_status = $return;
                $order_product_record->orders_products_status_manual = 0;
                try {
                    $order_product_record->save();
                } catch (\Exception $exc) {
                    $return = $order_product_status;
                }
            }
            unset($order_product_quantity_real);
            unset($order_product_dispatched);
            unset($order_product_delivered);
            unset($order_product_cancelled);
            unset($order_product_received);
            unset($order_product_status);
            if ($is_parent == false) {
                $is_parent = self::get_parent($order_product_record, false);
                if ($is_parent instanceof \common\models\Orders_Products) {
                    self::evaluate($is_parent);
                }
            } else {
                \common\helpers\Product::do_cache($order_product_record->products_id);
            }
            unset($is_parent);
        }
        unset($order_product_record);
        return $return;
    }
    /**
     * Get Order Product allocated quantity
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $isCalculate define should allocated quantity be calculated or gathered from cache
     * @return integer calculated allocated quantity
     */
    public static function get_allocated($order_product_record = 0, $is_calculate = false)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if ((int) $is_calculate > 0) {
                foreach (\common\models\Orders_Products_Allocate::find_all(['orders_products_id' => $order_product_record->orders_products_id]) as $op_allocate_record) {
                    $return += (int) $op_allocate_record->allocate_received - (int) $op_allocate_record->allocate_dispatched;
                }
                unset($op_allocate_record);
            } else {
                $return = self::get_received($order_product_record) - self::get_dispatched($order_product_record);
            }
        }
        unset($order_product_record);
        unset($is_calculate);
        return $return;
    }
    /**
     * Get Order Product Allocation array
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $asArray switching return type between array of arrays or array of instances of OrdersProductsAllocate
     * @return array array of mixed depending on $asArray parameter
     */
    public static function get_allocated_array($order_product_record = 0, $as_array = true)
    {
        $return = [];
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            foreach (\common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_record->orders_products_id])->as_array($as_array)->all() as $op_allocate_record) {
                $return[] = $op_allocate_record;
            }
            unset($op_allocate_record);
        }
        unset($order_product_record);
        unset($as_array);
        return $return;
    }
    /**
     * Get product's cancelled quantity
     * (Cancelled <= Product quantity [0 -> Product quantity])
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return int product's cancelled quantity
     */
    public static function get_cancelled($order_product_record = 0)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $return = (int) $order_product_record->qty_cnld;
        }
        unset($order_product_record);
        return $return;
    }
    /**
     * Get product's real quantity
     * (Real quantity = Product quantity - Cancelled [Product quantity -> 0])
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return int product's real quantity
     */
    public static function get_quantity_real($order_product_record = 0)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $return = (int) $order_product_record->products_quantity - self::get_cancelled($order_product_record);
        }
        unset($order_product_record);
        return $return;
    }
    /**
     * Get product's received quantity
     * (Received <= Real quantity [0 -> Real quantity])
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @param boolean $isCalculate define should received quantity be calculated or gathered from cache
     * @return int product's received quantity
     */
    public static function get_received($order_product_record = 0, $is_calculate = false)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            if ((int) $is_calculate > 0) {
                foreach (\common\models\Orders_Products_Allocate::find_all(['orders_products_id' => $order_product_record->orders_products_id]) as $op_allocate_record) {
                    $return += (int) $op_allocate_record->allocate_received;
                }
                unset($op_allocate_record);
            } else {
                $return = (int) $order_product_record->qty_rcvd;
            }
        }
        unset($order_product_record);
        unset($is_calculate);
        return $return;
    }
    /**
     * Get product's stock deficit quantity
     * (Stock deficit = Real quantity - Received [Real quantity -> 0])
     * (Stock deficit >= Stock pending + Stock ordered)
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return int product's stock deficit quantity
     */
    public static function get_stock_deficit($order_product_record = 0)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $return = self::get_quantity_real($order_product_record) - self::get_received($order_product_record);
        }
        unset($order_product_record);
        return $return;
    }
    /**
     * Get product's stock ordered quantity
     * (Dependent on pending Purchase Orders Products)
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return int product's stock ordered quantity
     */
    public static function get_stock_ordered($order_product_record = 0)
    {
        $return = 0;
        if (\common\helpers\Acl::check_extension_allowed('PurchaseOrders')) {
            $order_product_record = self::get_record($order_product_record);
            if ($order_product_record instanceof \common\models\Orders_Products) {
                $return = \common\extensions\Purchase_Orders\helpers\Purchase_Order::get_stock_ordered($order_product_record->uprid, false);
            }
            unset($order_product_record);
        }
        return $return;
    }
    /**
     * Get product's dispatched quantity
     * (Dispatched <= Received [0 -> Received])
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return int product's dispatched quantity
     */
    public static function get_dispatched($order_product_record = 0)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $return = (int) $order_product_record->qty_dspd;
        }
        unset($order_product_record);
        return $return;
    }
    /**
     * Get product's delivered quantity
     * (Delivered <= Received [0 -> Received])
     * @param mixed $orderProductRecord Order Product Id or instance of OrdersProducts model
     * @return int product's delivered quantity
     */
    public static function get_delivered($order_product_record = 0)
    {
        $return = 0;
        $order_product_record = self::get_record($order_product_record);
        if ($order_product_record instanceof \common\models\Orders_Products) {
            $return = (int) $order_product_record->qty_dlvd;
        }
        unset($order_product_record);
        return $return;
    }
    /**
     * Get Order Product record
     * @param mixed $orderProductId Order Product Id or instance of OrdersProducts model
     * @return mixed instance of OrdersProducts model or null
     */
    public static function get_record($order_product_id = 0)
    {
        return $order_product_id instanceof \common\models\Orders_Products ? $order_product_id : \common\models\Orders_Products::find_one(['orders_products_id' => (int) $order_product_id]);
    }
    /**
     * Get configuration array of possible automated statuses
     * @return array configuration array of possible automated statuses
     */
    public static function get_status_array()
    {
        return [self::OPS_QUOTED => ['long' => 'Quoted', 'short' => 'Qted', 'colour' => '#667981', 'key' => 'OPS_QUOTED'], self::OPS_STOCK_DEFICIT => ['long' => 'Stock deficit', 'short' => 'StckDfct', 'colour' => '#ff9100', 'key' => 'OPS_STOCK_DEFICIT'], self::OPS_STOCK_PENDING => ['long' => 'Stock pending', 'short' => 'StckPndg', 'colour' => '#8e8d0d', 'key' => 'OPS_STOCK_PENDING'], self::OPS_STOCK_ORDERED => ['long' => 'Stock ordered', 'short' => 'StckOrdr', 'colour' => '#aa00ff', 'key' => 'OPS_STOCK_ORDERED'], self::OPS_RECEIVED => ['long' => 'Received', 'short' => 'Rcvd', 'colour' => '#283593', 'key' => 'OPS_RECEIVED'], self::OPS_DISPATCHED => ['long' => 'Dispatched', 'short' => 'Dspd', 'colour' => '#2962ff', 'key' => 'OPS_DISPATCHED'], self::OPS_DELIVERED => ['long' => 'Delivered', 'short' => 'Dlvd', 'colour' => '#028908', 'key' => 'OPS_DELIVERED'], self::OPS_CANCELLED => ['long' => 'Cancelled', 'short' => 'Cnld', 'colour' => '#ff0000', 'key' => 'OPS_CANCELLED']];
    }
}