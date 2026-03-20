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
namespace common\extensions\Stock_Control\models;

/**
 * This is the model class for table "warehouse_inventory_control".
 *
 * @property string $products_id
 * @property integer $platform_id
 * @property integer $warehouse_id
 */
class Warehouse_Inventory_Control extends \yii\db\Active_Record
{
    /**
     * @inheritdoc
     */
    public static function table_name()
    {
        return 'warehouse_inventory_control';
    }
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [[['products_id', 'platform_id'], 'required'], [['platform_id', 'warehouse_id'], 'integer'], [['products_id'], 'string', 'max' => 160]];
    }
    /**
     * @inheritdoc
     */
    public function attribute_labels()
    {
        return ['products_id' => 'Products ID', 'platform_id' => 'Platform ID', 'warehouse_id' => 'Warehouse ID'];
    }
}