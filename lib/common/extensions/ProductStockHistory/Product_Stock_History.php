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
namespace common\extensions\Product_Stock_History;

use common\models\Admin;
use Yii;
use yii\helpers\Array_Helper;
class Product_Stock_History extends \common\classes\modules\Module_Extensions
{
    public static function get_description()
    {
        return 'This extension allows to log the product stock changes.';
    }
    public static function allowed()
    {
        return self::enabled();
    }
    public static function product_block($prid)
    {
        if (!self::allowed()) {
            return '';
        }
        return \common\extensions\Product_Stock_History\Render::widget(['template' => 'product-block.tpl', 'params' => ['prid' => $prid]]);
    }
    public static function action_stock_history()
    {
        if (!self::allowed()) {
            return '';
        }
        \common\helpers\Translation::init('admin/categories');
        $prid = Yii::$app->request->get('prid');
        $temp = Yii::$app->request->get('temp');
        if (strpos($prid, '{') !== false) {
            $stock_history_query = tep_db_query('select * from ' . TABLE_STOCK_HISTORY . " where products_id = '" . tep_db_input($prid) . "' " . (!$temp ? " and is_temporary = '0'" : '') . ' order by stock_history_id desc');
        } else {
            $stock_history_query = tep_db_query('select * from ' . TABLE_STOCK_HISTORY . " where prid = '" . (int) $prid . "' " . (!$temp ? " and is_temporary = '0'" : '') . ' order by stock_history_id desc');
        }
        $admin_names = Array_Helper::map(Admin::find()->select(['admin_id', new \yii\db\Expression("CONCAT(admin_firstname,' ',admin_lastname) AS admin_name")])->as_array()->all(), 'admin_id', 'admin_name');
        $warehouse_names = Array_Helper::map(\common\helpers\Warehouses::get_warehouses(true), 'id', 'text');
        $relation_types = ['linked' => TEXT_RELATION_TYPE_LINKED, 'bundle' => TEXT_RELATION_TYPE_BUNDLE, 'sub_product' => TEXT_RELATION_TYPE_SUB_PRODUCT];
        $history = [];
        while ($stock_history = tep_db_fetch_array($stock_history_query)) {
            $extra_comment = '';
            if ($stock_history['relation_type']) {
                $extra_comment .= '<span style="white-space: nowrap">' . TEXT_RELATION_TYPE . ': <strong>' . (isset($relation_types[$stock_history['relation_type']]) ? $relation_types[$stock_history['relation_type']] : $stock_history['relation_type']) . '</strong>;</span>';
            }
            if (!empty($stock_history['parent_products_model']) || !empty($stock_history['parent_products_name'])) {
                $extra_comment .= ' ' . PRODUCT_NAME_MODEL . " <strong>[{$stock_history['parent_products_model']}] {$stock_history['parent_products_name']}</strong>";
            }
            $admin = isset($admin_names[$stock_history['admin_id']]) ? $admin_names[$stock_history['admin_id']] : '';
            $stock_before = (int) $stock_history['is_temporary'] > 0 ? $stock_history['products_quantity_before'] : $stock_history['warehouse_quantity_before'];
            $history[] = ['id' => $stock_history['stock_history_id'], 'date' => \common\helpers\Date::datetime_short($stock_history['date_added']), 'model' => $stock_history['products_model'], 'warehouse' => isset($warehouse_names[$stock_history['warehouse_id']]) ? $warehouse_names[$stock_history['warehouse_id']] : '', 'supplier' => \common\helpers\Suppliers::get_supplier_name($stock_history['suppliers_id']), 'stock_before' => $stock_before, 'stock_update' => $stock_history['products_quantity_update_prefix'] . $stock_history['products_quantity_update'], 'stock_after' => $stock_history['products_quantity_update_prefix'] == '-' ? $stock_before - $stock_history['products_quantity_update'] : $stock_before + $stock_history['products_quantity_update'], 'order' => $stock_history['orders_id'] > 0 ? '<a target="_blank" href="' . \yii\helpers\Url::to([FILENAME_ORDERS . '/process-order', 'orders_id' => $stock_history['orders_id']]) . '">' . $stock_history['orders_id'] . '</a>' : '', 'comments' => $stock_history['comments'], 'extra_comment' => $extra_comment, 'admin' => $admin];
        }
        return \common\extensions\Product_Stock_History\Render::widget(['template' => 'stock-history', 'params' => ['history' => $history, 'prid' => $prid, 'temp' => $temp]]);
    }
}