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
return ['Header' => ['type' => 'site/orders'], 'dependsOn' => ['site/languages', 'site/order_statuses', 'site/currencies', 'site/platforms', 'site/customers', 'site/products', 'site/countries'], 'Data' => ['common\models\Orders' => [
    //'where' => ['orders_id'=>110610],
    'xmlCollection' => 'Orders>Order',
    'properties' => ['customers_id' => ['class' => 'IOMap', 'table' => 'customers', 'attribute' => 'customers_id'], 'orders_status' => ['class' => 'IOOrderStatus'], 'language_id' => ['class' => 'IOLanguageMap']],
    'beforeImportSave' => function ($model, $data) {
        if (!\Osc_Link\XML\Io_Core::get()->is_local_project()) {
            if (isset($data->data['orders_id']->external_id)) {
                $model->external_orders_id = $data->data['orders_id']->external_id;
            } elseif (isset($data->data['orders_id']->internal_id)) {
                $model->external_orders_id = $data->data['orders_id']->internal_id;
            }
        }
    },
    'withRelated' => ['ordersProducts' => ['xmlCollection' => 'OrdersProducts>OrdersProduct', 'properties' => ['orders_products_id' => false], 'withRelated' => ['ordersProductsAttributes' => ['xmlCollection' => 'Attributes>Attribute', 'properties' => ['orders_products_attributes_id' => false]]]], 'ordersTotals' => ['xmlCollection' => 'OrdersTotals>OrdersTotal', 'properties' => ['orders_total_id' => false]], 'ordersStatusHistory' => ['xmlCollection' => 'OrdersStatusHistory>OrdersStatus', 'properties' => [
        'orders_status_history_id' => false,
        //???
        'orders_status_id' => ['class' => 'IOOrderStatus'],
    ]]],
    'beforeDelete' => function ($model, $id) {
        \common\helpers\Order::remove_order($id, '', 'OscLink cleaned');
        return 'deleted';
    },
    'afterImport' => function ($model, $data) {
        if (isset($data->data['orders_id']) && is_object($data->data['orders_id'])) {
            $tools = new \backend\models\EP\Tools();
            $orders_id = $data->data['orders_id']->to_import_model();
            $platform_id = \common\classes\platform::default_id();
            \common\models\Orders::update_all(['platform_id' => $platform_id], 'platform_id = 0');
            $language_id = \common\helpers\Language::get_default_language_id();
            \common\models\Orders::update_all(['language_id' => $language_id], 'language_id = 0');
            foreach (\common\models\Orders_Products::find_all(['orders_id' => $orders_id, 'uprid' => '']) as $op_record) {
                // fill uprid if empty
                $attributes_array = [];
                foreach (\common\models\Orders_Products_Attributes::find_all(['orders_id' => $orders_id, 'orders_products_id' => $op_record->orders_products_id]) as $opa_record) {
                    if ($opa_record->products_options_id == 0) {
                        $opa_record->products_options_id = $tools->get_option_by_name($opa_record->products_options);
                    }
                    if ($opa_record->products_options_values_id == 0) {
                        $opa_record->products_options_values_id = $tools->get_option_value_by_name($opa_record->products_options_id, $opa_record->products_options_values);
                    }
                    if ($opa_record->products_options_id && $opa_record->products_options_values_id) {
                        $attributes_array[$opa_record->products_options_id] = $opa_record->products_options_values_id;
                    }
                    $opa_record->save(false);
                }
                $op_record->uprid = \common\helpers\Inventory::get_uprid($op_record->products_id, $attributes_array);
                $op_record->save(false);
            }
        }
    },
]], 'covered_tables' => ['orders', 'orders_products', 'orders_products_attributes', 'orders_total', 'orders_status_history']];