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
class Google extends Datasource_Base
{
    public function get_name()
    {
        return 'Google';
    }
    public function prepare_config_for_view($config_array)
    {
        $order_statuses_select = ['*' => '[Any order status]'];
        foreach (\common\helpers\Order::get_statuses_grouped(true) as $option) {
            $order_statuses_select[$option['id']] = html_entity_decode($option['text'], null, 'UTF-8');
        }
        $config_array['order']['export_statuses'] = ['items' => $order_statuses_select, 'value' => $config_array['order']['export_statuses'], 'options' => ['class' => 'form-control', 'multiple' => true, 'size' => 9, 'options' => []]];
        $config_array['delays']['latencity'] = isset($config_array['delays']['latencity']) ? (int) $config_array['delays']['latencity'] : 2;
        $config_array['delays']['outdated'] = isset($config_array['delays']['outdated']) ? (int) $config_array['delays']['outdated'] : 3;
        return parent::prepare_config_for_view($config_array);
    }
    public function get_view_template()
    {
        return 'datasource/ecommerce-tracking';
    }
}