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
namespace backend\models\EP\Datasource;

use backend\models\EP\Datasource_Base;
class Trueloaded extends Datasource_Base
{
    protected $remote_data = [];
    public function get_name()
    {
        return 'Trueloaded';
    }
    public static function get_provider_list()
    {
        return ['Trueloaded\ImportBrands' => ['group' => 'Trueloaded', 'name' => 'Import Brands', 'class' => 'Provider\Trueloaded\ImportBrands', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportCategories' => ['group' => 'Trueloaded', 'name' => 'Import Categories', 'class' => 'Provider\Trueloaded\ImportCategories', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportProductsOptions' => ['group' => 'Trueloaded', 'name' => 'Import Product Options', 'class' => 'Provider\Trueloaded\ImportProductsOptions', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportProducts' => ['group' => 'Trueloaded', 'name' => 'Import Products', 'class' => 'Provider\Trueloaded\ImportProducts', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportGroups' => ['group' => 'Trueloaded', 'name' => 'Import Customer Groups', 'class' => 'Provider\Trueloaded\ImportGroups', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportCustomers' => ['group' => 'Trueloaded', 'name' => 'Import Customers', 'class' => 'Provider\Trueloaded\ImportCustomers', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportOrdersStatuses' => ['group' => 'Trueloaded', 'name' => 'Import Orders Statuses', 'class' => 'Provider\Trueloaded\ImportOrdersStatuses', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportOrders' => ['group' => 'Trueloaded', 'name' => 'Import Orders', 'class' => 'Provider\Trueloaded\ImportOrders', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportTaxZones' => ['group' => 'Trueloaded', 'name' => 'Import Tax Zones', 'class' => 'Provider\Trueloaded\ImportTaxZones', 'export' => ['disableSelectFields' => true]], 'Trueloaded\ImportTax' => ['group' => 'Trueloaded', 'name' => 'Import Taxes', 'class' => 'Provider\Trueloaded\ImportTax', 'export' => ['disableSelectFields' => true]]];
    }
    public function get_view_template()
    {
        return 'datasource/trueloaded.tpl';
    }
    public function prepare_config_for_view($config_array)
    {
        return parent::prepare_config_for_view($config_array);
    }
}