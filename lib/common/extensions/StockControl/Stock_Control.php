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
namespace common\extensions\Stock_Control;

use common\extensions\Stock_Control\models\Platform_Inventory_Control;
use common\extensions\Stock_Control\models\Platform_Stock_Control;
use common\extensions\Stock_Control\models\Warehouse_Inventory_Control;
use common\extensions\Stock_Control\models\Warehouse_Stock_Control;
class Stock_Control extends \common\classes\modules\Module_Extensions
{
    public static function save_product($product_record = false)
    {
        try {
            if ($product_record instanceof \common\models\Products) {
                $stock_control = (int) \Yii::$app->request->post('stock_control', 0);
                if ((int) \Yii::$app->request->post('is_bundle', 0) > 0 or (int) \Yii::$app->request->post('manual_stock_unlimited', 0) > 0) {
                    $stock_control = 0;
                }
                $product_record->set_attributes(['stock_control' => $stock_control], false);
                switch ($stock_control) {
                    case 0:
                        break;
                    case 1:
                        foreach (\common\models\Platforms::find()->where(['status' => 1])->as_array(false)->all() as $platform_record) {
                            $current_quantity = (int) \Yii::$app->request->post('platform_to_qty_' . (int) $platform_record->platform_id);
                            $psc_record = Platform_Stock_Control::find_one(['products_id' => (int) $product_record->products_id, 'platform_id' => (int) $platform_record->platform_id]);
                            if (is_object($psc_record)) {
                                if ($current_quantity != (int) $psc_record->current_quantity) {
                                    $psc_record->current_quantity = $current_quantity;
                                    $psc_record->manual_quantity = $current_quantity;
                                    $psc_record->save(false);
                                }
                            } else {
                                $psc_record = new Platform_Stock_Control();
                                $psc_record->products_id = (int) $product_record->products_id;
                                $psc_record->platform_id = (int) $platform_record->platform_id;
                                $psc_record->current_quantity = $current_quantity;
                                $psc_record->manual_quantity = $current_quantity;
                                $psc_record->save(false);
                            }
                            unset($current_quantity);
                            unset($psc_record);
                        }
                        unset($platform_record);
                        break;
                    case 2:
                        Warehouse_Stock_Control::delete_all(['products_id' => (int) $product_record->products_id]);
                        foreach (\common\models\Platforms::find()->where(['status' => 1])->as_array(false)->all() as $platform_record) {
                            $wsc_record = new Warehouse_Stock_Control();
                            $wsc_record->products_id = (int) $product_record->products_id;
                            $wsc_record->platform_id = (int) $platform_record->platform_id;
                            $wsc_record->warehouse_id = (int) \Yii::$app->request->post('platform_to_warehouse_' . (int) $platform_record->platform_id);
                            $wsc_record->save(false);
                            unset($wsc_record);
                        }
                        unset($platform_record);
                        break;
                    default:
                        break;
                }
                unset($stock_control);
            }
        } catch (\Exception $exc) {
            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Extension.StockControl.saveProduct');
        }
        unset($product_record);
    }
    public static function save_attributes_and_inventory_save($u_product_id)
    {
        try {
            if (\Yii::$app->request->post('inventory_control_present', 0)) {
                $stock_control = (int) \Yii::$app->request->post('inventory_control_' . $u_product_id);
                if ((int) \Yii::$app->request->post('manual_stock_unlimited', 0) > 0 or (int) \Yii::$app->request->post('is_bundle', 0) > 0) {
                    $stock_control = 0;
                }
                tep_db_query('update ' . TABLE_INVENTORY . " set stock_control = '" . $stock_control . "' where products_id = '" . tep_db_input($u_product_id) . "'");
                switch ($stock_control) {
                    case 0:
                        break;
                    case 1:
                        foreach (\common\models\Platforms::find()->where(['status' => 1])->as_array(false)->all() as $platform_record) {
                            $current_quantity = (int) \Yii::$app->request->post('platform_to_qty_' . $u_product_id . '_' . (int) $platform_record->platform_id);
                            $pic_record = Platform_Inventory_Control::find_one(['products_id' => tep_db_input($u_product_id), 'platform_id' => $platform_record->platform_id]);
                            if (is_object($pic_record)) {
                                if ($current_quantity != $pic_record->current_quantity) {
                                    $pic_record->current_quantity = $current_quantity;
                                    $pic_record->manual_quantity = $current_quantity;
                                    $pic_record->save(false);
                                }
                            } else {
                                $pic_record = new Platform_Inventory_Control();
                                $pic_record->products_id = tep_db_input($u_product_id);
                                $pic_record->platform_id = (int) $platform_record->platform_id;
                                $pic_record->current_quantity = $current_quantity;
                                $pic_record->manual_quantity = $current_quantity;
                                $pic_record->save(false);
                            }
                            unset($pic_record);
                        }
                        unset($platform_record);
                        break;
                    case 2:
                        Warehouse_Inventory_Control::delete_all(['products_id' => tep_db_input($u_product_id)]);
                        foreach (\common\models\Platforms::find()->where(['status' => 1])->as_array(false)->all() as $platform_record) {
                            $wic_record = new Warehouse_Inventory_Control();
                            $wic_record->products_id = tep_db_input($u_product_id);
                            $wic_record->platform_id = (int) $platform_record->platform_id;
                            $wic_record->warehouse_id = (int) \Yii::$app->request->post('platform_to_warehouse_' . $u_product_id . '_' . (int) $platform_record->platform_id);
                            $wic_record->save(false);
                            unset($wic_record);
                        }
                        unset($platform_record);
                        break;
                    default:
                        break;
                }
            }
        } catch (\Exception $exc) {
            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Extension.StockControl.saveAttributesAndInventorySave');
        }
        unset($u_product_id);
    }
    public static function view_product_edit($p_info)
    {
        return Render::widget2('admin-product-detail', ['pInfo' => $p_info]);
    }
    public static function view_stock_tab($ikey, $inventory)
    {
        $is_stock_unlimited = false;
        $product_record = \common\helpers\Product::get_record($inventory['uprid'] ?? 0);
        if ($product_record instanceof \common\models\Products) {
            $is_stock_unlimited = (int) $product_record->manual_stock_unlimited > 0;
        }
        unset($product_record);
        return Render::widget2('admin-stock-tab', ['ikey' => $ikey, 'inventory' => $inventory, 'isStockUnlimited' => $is_stock_unlimited]);
    }
    public static function update_product_view_stock_info($p_info)
    {
        $warehouse_stock_control_list = [];
        foreach (Warehouse_Stock_Control::find(['products_id' => (int) $p_info->products_id])->as_array(true)->each() as $warehouse_stock_control) {
            $warehouse_stock_control_list[$warehouse_stock_control['platform_id']] = $warehouse_stock_control['warehouse_id'];
        }
        $platform_stock_control_list = [];
        foreach (Platform_Stock_Control::find(['products_id' => (int) $p_info->products_id])->as_array(true)->each() as $platform_stock_control) {
            $platform_stock_control_list[$platform_stock_control['platform_id']] = $platform_stock_control['current_quantity'];
        }
        $platform_stock_list = [];
        $platform_warehouse_list = [];
        foreach (\common\classes\platform::get_list(true, true) as $platform) {
            $platform_stock_list[] = ['id' => $platform['id'], 'name' => $platform['text'], 'qty' => isset($platform_stock_control_list[$platform['id']]) ? $platform_stock_control_list[$platform['id']] : 0];
            $platform_warehouse_list[] = ['id' => $platform['id'], 'name' => $platform['text'], 'warehouse' => isset($warehouse_stock_control_list[$platform['id']]) ? $warehouse_stock_control_list[$platform['id']] : \common\helpers\Warehouses::get_default_warehouse()];
        }
        unset($platform);
        unset($platform_stock_control_list);
        unset($warehouse_stock_control_list);
        $p_info->platform_stock_list = $platform_stock_list;
        $p_info->platform_warehouse_list = $platform_warehouse_list;
        unset($platform_warehouse_list);
        unset($platform_stock_list);
        unset($p_info);
    }
    public static function update_product_inventory_box($product_id)
    {
        $platform_stock_list = [];
        $platfor_warehouse_list = [];
        $product_id = (int) $product_id;
        $warehouse_stock_control_list = Warehouse_Inventory_Control::find()->and_where(['products_id' => $product_id])->select('warehouse_id')->as_array(true)->index_by('platform_id')->column();
        $platform_stock_control_list = Platform_Inventory_Control::find()->and_where(['products_id' => $product_id])->select('current_quantity')->as_array(true)->index_by('platform_id')->column();
        foreach (\common\models\Platforms::find()->where(['status' => 1])->order_by(['sort_order' => SORT_ASC])->as_array(false)->all() as $platform_record) {
            $platform_stock_list[] = ['id' => $platform_record->platform_id, 'name' => $platform_record->platform_name, 'qty' => isset($platform_stock_control_list[$platform_record->platform_id]) ? $platform_stock_control_list[$platform_record->platform_id] : 0];
            $platfor_warehouse_list[] = ['id' => $platform_record->platform_id, 'name' => $platform_record->platform_name, 'warehouse' => isset($warehouse_stock_control_list[$platform_record->platform_id]) ? $warehouse_stock_control_list[$platform_record->platform_id] : \common\helpers\Warehouses::get_default_warehouse()];
        }
        unset($warehouse_stock_control_list);
        unset($platform_stock_control_list);
        unset($platform_record);
        unset($product_id);
        return [$platform_stock_list, $platfor_warehouse_list];
    }
    public static function update_get_product_stock_inventory($u_product_id, &$stock_value_array)
    {
        $u_product_id = \common\helpers\Inventory::normalize_inventory_id($u_product_id);
        $stock_value_array['stock_control'] = (int) ($stock_value_array['stock_control'] ?? 0);
        switch ($stock_value_array['stock_control']) {
            case 1:
                $platform_inventory_control = Platform_Inventory_Control::find_one(['products_id' => $u_product_id, 'platform_id' => \common\classes\platform::current_id()]);
                if (is_object($platform_inventory_control)) {
                    $stock_value_array['products_quantity'] = $platform_inventory_control->current_quantity;
                }
                unset($platform_inventory_control);
                break;
            case 2:
                $warehouse_inventory_control = Warehouse_Inventory_Control::find_one(['products_id' => $u_product_id, 'platform_id' => \common\classes\platform::current_id()]);
                if (is_object($warehouse_inventory_control)) {
                    $supplier_id = (int) 0;
                    $warehouse_id = (int) $warehouse_inventory_control->warehouse_id;
                    $warehouses_stock_query = tep_db_query('select w.warehouse_id, w.warehouse_name, sum(wp.products_quantity) as products_quantity,' . ' sum(wp.allocated_stock_quantity) as allocated_stock_quantity, sum(wp.temporary_stock_quantity) as temporary_stock_quantity,' . ' sum(wp.warehouse_stock_quantity) as warehouse_stock_quantity, sum(wp.ordered_stock_quantity) as ordered_stock_quantity' . ' from  ' . TABLE_WAREHOUSES . ' w left join ' . TABLE_WAREHOUSES_PRODUCTS . ' wp on wp.warehouse_id = w.warehouse_id' . ($supplier_id > 0 ? " and wp.suppliers_id = '{$supplier_id}'" : '') . " and wp.products_id = '{$u_product_id}'" . " and wp.prid = '" . (int) $u_product_id . "'" . " where w.status = '1' and w.warehouse_id = '{$warehouse_id}'");
                    if (tep_db_num_rows($warehouses_stock_query) > 0) {
                        $warehouses_stock = tep_db_fetch_array($warehouses_stock_query);
                        $stock_value_array['products_quantity'] = $warehouses_stock['products_quantity'];
                        unset($warehouses_stock);
                    }
                    unset($warehouses_stock_query);
                    unset($warehouse_id);
                    unset($supplier_id);
                }
                unset($warehouse_inventory_control);
                break;
        }
        unset($stock_value_array);
        unset($u_product_id);
    }
    public static function update_get_product_stock_product($product_id, &$stock_value_array)
    {
        $product_id = (int) $product_id;
        $stock_value_array['stock_control'] = (int) ($stock_value_array['stock_control'] ?? 0);
        switch ($stock_value_array['stock_control']) {
            case 1:
                $platform_stock_control = Platform_Stock_Control::find_one(['products_id' => $product_id, 'platform_id' => \common\classes\platform::current_id()]);
                if (is_object($platform_stock_control)) {
                    $stock_value_array['products_quantity'] = $platform_stock_control->current_quantity;
                }
                unset($platform_stock_control);
                break;
            case 2:
                $warehouse_stock_control = Warehouse_Stock_Control::find_one(['products_id' => $product_id, 'platform_id' => \common\classes\platform::current_id()]);
                if (is_object($warehouse_stock_control)) {
                    $supplier_id = (int) 0;
                    $warehouse_id = (int) $warehouse_stock_control->warehouse_id;
                    $warehouses_stock_query = tep_db_query('select w.warehouse_id, w.warehouse_name, sum(wp.products_quantity) as products_quantity,' . ' sum(wp.allocated_stock_quantity) as allocated_stock_quantity, sum(wp.temporary_stock_quantity) as temporary_stock_quantity,' . ' sum(wp.warehouse_stock_quantity) as warehouse_stock_quantity, sum(wp.ordered_stock_quantity) as ordered_stock_quantity' . ' from  ' . TABLE_WAREHOUSES . ' w left join ' . TABLE_WAREHOUSES_PRODUCTS . ' wp on wp.warehouse_id = w.warehouse_id' . ($supplier_id > 0 ? " and wp.suppliers_id = '{$supplier_id}'" : '') . " and wp.products_id = '{$product_id}'" . " and wp.prid = '{$product_id}'" . " where w.status = '1' and w.warehouse_id = '{$warehouse_id}'");
                    if (tep_db_num_rows($warehouses_stock_query) > 0) {
                        $warehouses_stock = tep_db_fetch_array($warehouses_stock_query);
                        $stock_value_array['products_quantity'] = $warehouses_stock['products_quantity'];
                        unset($warehouses_stock);
                    }
                    unset($warehouses_stock_query);
                    unset($warehouse_id);
                    unset($supplier_id);
                }
                unset($warehouse_stock_control);
                break;
        }
        unset($stock_value_array);
        unset($product_id);
    }
    public static function update_get_available($u_product_id, $platform_id)
    {
        $return = false;
        $platform_id = (int) $platform_id;
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        if (\common\helpers\Inventory::is_inventory($u_product_id) != true) {
            $product_record = \common\helpers\Product::get_record($u_product_id);
            if ($product_record instanceof \common\models\Products and $product_record->stock_control == 1) {
                $return = 0;
                $platform_stock_control = Platform_Stock_Control::find()->and_where(['products_id' => $u_product_id, 'platform_id' => $platform_id])->cache((defined('ALLOW_ANY_QUERY_CACHE') and ALLOW_ANY_QUERY_CACHE == 'True') ? \common\helpers\Product::PRODUCT_RECORD_CACHE : -1)->one();
                if ($platform_stock_control instanceof Platform_Stock_Control) {
                    $return = $platform_stock_control->current_quantity;
                }
                unset($platform_stock_control);
            }
            unset($product_record);
        } else {
            $inventory_record = \common\helpers\Inventory::get_record($u_product_id);
            if ($inventory_record instanceof \common\models\Inventory and $inventory_record->stock_control == 1) {
                $return = 0;
                $platform_inventory_control = Platform_Inventory_Control::find()->and_where(['products_id' => $u_product_id, 'platform_id' => $platform_id])->cache((defined('ALLOW_ANY_QUERY_CACHE') and ALLOW_ANY_QUERY_CACHE == 'True') ? \common\helpers\Product::PRODUCT_RECORD_CACHE : -1)->one();
                if ($platform_inventory_control instanceof Platform_Inventory_Control) {
                    $return = $platform_inventory_control->current_quantity;
                }
                unset($platform_inventory_control);
            }
            unset($inventory_record);
        }
        unset($u_product_id);
        unset($platform_id);
        return $return;
    }
    public static function update_get_warehouse_id_priority_array($u_product_id, $platform_id)
    {
        $return = false;
        $platform_id = (int) $platform_id;
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        if (\common\helpers\Inventory::is_inventory($u_product_id) != true) {
            $product_record = \common\helpers\Product::get_record($u_product_id);
            if ($product_record instanceof \common\models\Products and $product_record->stock_control == 2) {
                $return = [];
                $warehouse_stock_control_record = Warehouse_Stock_Control::find_one(['products_id' => $product_record->products_id, 'platform_id' => $platform_id]);
                if ($warehouse_stock_control_record instanceof Warehouse_Stock_Control) {
                    $return[] = (int) $warehouse_stock_control_record->warehouse_id;
                }
                unset($warehouse_stock_control_record);
            }
            unset($product_record);
        } else {
            $inventory_record = \common\helpers\Inventory::get_record($u_product_id);
            if ($inventory_record instanceof \common\models\Inventory and $inventory_record->stock_control == 2) {
                $return = [];
                $warehouse_inventory_control = Warehouse_Inventory_Control::find_one(['products_id' => $inventory_record->products_id, 'platform_id' => $platform_id]);
                if ($warehouse_inventory_control instanceof Warehouse_Inventory_Control) {
                    $return[] = (int) $warehouse_inventory_control->warehouse_id;
                }
                unset($warehouse_inventory_control);
            }
            unset($inventory_record);
        }
        unset($u_product_id);
        unset($platform_id);
        return $return;
    }
    public static function update_update_stock_of_order($u_product_id, $platform_id)
    {
        $return = false;
        $product_record = \common\helpers\Product::get_record($u_product_id);
        if ($product_record instanceof \common\models\Products and $product_record->stock_control == 2) {
            //$return = 0;
            $warehouse_stock_control = Warehouse_Stock_Control::find_one(['products_id' => $product_record->products_id, 'platform_id' => (int) $platform_id]);
            if (is_object($warehouse_stock_control)) {
                $return = (int) $warehouse_stock_control->warehouse_id;
            }
            unset($warehouse_stock_control);
        }
        unset($product_record);
        unset($u_product_id);
        unset($platform_id);
        return $return;
    }
    public static function update_stock_inventory_inventory($u_product_id, $platform_id, $quantity)
    {
        $quantity = trim($quantity);
        $platform_id = (int) $platform_id;
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual($u_product_id);
        $inventory_record = \common\helpers\Inventory::get_record($u_product_id);
        if ($inventory_record instanceof \common\models\Inventory and $inventory_record->stock_control == 1) {
            tep_db_query('update platform_inventory_control set current_quantity = current_quantity ' . $quantity . " where products_id = '" . tep_db_input($u_product_id) . "' and platform_id='" . $platform_id . "'");
        }
        unset($inventory_record);
        unset($u_product_id);
        unset($platform_id);
        unset($quantity);
    }
    public static function update_stock_inventory_product($product_id, $platform_id, $quantity)
    {
        $quantity = trim($quantity);
        $product_id = (int) $product_id;
        $platform_id = (int) $platform_id;
        $product_record = \common\helpers\Product::get_record($product_id);
        if ($product_record instanceof \common\models\Products and $product_record->stock_control == 1) {
            tep_db_query('update platform_stock_control set current_quantity = current_quantity ' . $quantity . " where products_id = '" . $product_id . "' and platform_id='" . $platform_id . "'");
        }
        unset($product_record);
        unset($platform_id);
        unset($product_id);
        unset($quantity);
    }
    public static function update_api_product_load(\common\api\Classes\Product $product_class)
    {
        $product_class->platform_stock_control_record_array = Platform_Stock_Control::find()->where(['products_id' => $product_class->product_id])->as_array(true)->all();
        $product_class->warehouse_stock_control_record_array = Warehouse_Stock_Control::find()->where(['products_id' => $product_class->product_id])->as_array(true)->all();
    }
    public static function update_api_product_inventory_load(array &$inventory_record)
    {
        $inventory_record['platformInventoryControlRecordArray'] = Platform_Inventory_Control::find()->where(['products_id' => $inventory_record['products_id']])->as_array(true)->all();
        $inventory_record['warehouseInventoryControlRecordArray'] = Warehouse_Inventory_Control::find()->where(['products_id' => $inventory_record['products_id']])->as_array(true)->all();
    }
    public static function update_api_product_save(\common\api\Classes\Product $product_class)
    {
        /**
         * Platform Stock Control
         */
        $product_class->platform_stock_control_record_array = (array) ($product_class->platform_stock_control_record_array ?? []);
        foreach ($product_class->platform_stock_control_record_array as $platform_stock_control_record) {
            $platform_id = (int) (isset($platform_stock_control_record['platform_id']) ? $platform_stock_control_record['platform_id'] : 0);
            unset($platform_stock_control_record['products_id']);
            unset($platform_stock_control_record['platform_id']);
            if ($platform_id > 0) {
                $platform_stock_class = Platform_Stock_Control::find()->where(['products_id' => $product_class->product_id, 'platform_id' => $platform_id])->one();
                if (!$platform_stock_class instanceof Platform_Stock_Control) {
                    $platform_stock_class = new Platform_Stock_Control();
                    $platform_stock_class->load_default_values();
                    $platform_stock_class->products_id = $product_class->product_id;
                    $platform_stock_class->platform_id = $platform_id;
                }
                $platform_stock_class->set_attributes($platform_stock_control_record, false);
                if ($platform_stock_class->save(false)) {
                } else {
                    $product_class->message_add($platform_stock_class->get_error_summary(true));
                }
                unset($platform_stock_class);
            }
            unset($platform_id);
        }
        unset($platform_stock_control_record);
        /**
         * Warehouses Stock Control
         */
        $product_class->warehouse_stock_control_record_array = (array) ($product_class->warehouse_stock_control_record_array ?? []);
        foreach ($product_class->warehouse_stock_control_record_array as $warehouse_stock_control_record) {
            $platform_id = (int) (isset($warehouse_stock_control_record['platform_id']) ? $warehouse_stock_control_record['platform_id'] : 0);
            unset($warehouse_stock_control_record['products_id']);
            unset($warehouse_stock_control_record['platform_id']);
            if ($platform_id > 0) {
                $warehouse_stock_class = Warehouse_Stock_Control::find()->where(['products_id' => $product_class->product_id, 'platform_id' => $platform_id])->one();
                if (!$warehouse_stock_class instanceof Warehouse_Stock_Control) {
                    $warehouse_stock_class = new Warehouse_Stock_Control();
                    $warehouse_stock_class->load_default_values();
                    $warehouse_stock_class->products_id = $product_class->product_id;
                    $warehouse_stock_class->platform_id = $platform_id;
                }
                $warehouse_stock_class->set_attributes($warehouse_stock_control_record, false);
                if ($warehouse_stock_class->save(false)) {
                } else {
                    $product_class->message_add($warehouse_stock_class->get_error_summary(true));
                }
                unset($warehouse_stock_class);
            }
            unset($platform_id);
        }
        unset($warehouse_stock_control_record);
    }
    public static function update_api_product_inventory_save(\common\api\Classes\Product $product_class, array $inventory_record, $inventory_id, $uprid)
    {
        $inventory_id = (int) $inventory_id;
        $inventory_record['platformInventoryControlRecordArray'] = (array) ($inventory_record['platformInventoryControlRecordArray'] ?? []);
        if (count($inventory_record['platformInventoryControlRecordArray']) > 0) {
            foreach ($inventory_record['platformInventoryControlRecordArray'] as $platform_inventory_control_record) {
                $platform_id = (int) (isset($platform_inventory_control_record['platform_id']) ? $platform_inventory_control_record['platform_id'] : 0);
                unset($platform_inventory_control_record['products_id']);
                unset($platform_inventory_control_record['platform_id']);
                if ($platform_id > 0) {
                    $inventory_class = Platform_Inventory_Control::find()->where(['products_id' => $uprid, 'platform_id' => $platform_id])->one();
                    if (!$inventory_class instanceof Platform_Inventory_Control) {
                        $inventory_class = new Platform_Inventory_Control();
                        $inventory_class->load_default_values();
                        $inventory_class->products_id = $uprid;
                        $inventory_class->platform_id = $platform_id;
                    }
                    $inventory_class->set_attributes($inventory_record, false);
                    if ($inventory_class->save(false)) {
                    } else {
                        $product_class->message_add($inventory_class->get_error_summary(true));
                    }
                    unset($inventory_class);
                }
                unset($platform_id);
            }
            unset($platform_inventory_control_record);
        }
        $inventory_record['warehouseInventoryControlRecordArray'] = (array) ($inventory_record['warehouseInventoryControlRecordArray'] ?? []);
        if ($inventory_id > 0 and count($inventory_record['warehouseInventoryControlRecordArray']) > 0) {
            foreach ($inventory_record['warehouseInventoryControlRecordArray'] as $warehouse_inventory_control_record) {
                $platform_id = (int) (isset($warehouse_inventory_control_record['platform_id']) ? $warehouse_inventory_control_record['platform_id'] : 0);
                unset($warehouse_inventory_control_record['products_id']);
                unset($warehouse_inventory_control_record['platform_id']);
                if ($platform_id > 0) {
                    $inventory_class = Warehouse_Inventory_Control::find()->where(['products_id' => $uprid, 'platform_id' => $platform_id])->one();
                    if (!$inventory_class instanceof Warehouse_Inventory_Control) {
                        $inventory_class = new Warehouse_Inventory_Control();
                        $inventory_class->load_default_values();
                        $inventory_class->products_id = $uprid;
                        $inventory_class->platform_id = $platform_id;
                    }
                    $inventory_class->set_attributes($warehouse_inventory_control_record, false);
                    if ($inventory_class->save(false)) {
                    } else {
                        $product_class->message_add($inventory_class->get_error_summary(true));
                    }
                    unset($inventory_class);
                }
                unset($platform_id);
            }
            unset($warehouse_inventory_control_record);
        }
        unset($inventory_id);
        unset($uprid);
    }
}