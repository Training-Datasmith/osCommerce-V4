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
class Magento extends Datasource_Base
{
    public function get_name()
    {
        return 'Magento SOAP';
    }
    public function prepare_config_for_view($config_array)
    {
        return parent::prepare_config_for_view($config_array);
    }
    public function get_view_template()
    {
        return 'datasource/magento.tpl';
    }
}