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
namespace backend\services;

use common\models\Orders_Total;
use common\models\repositories\Orders_Total_Repository;
class Orders_Total_Service
{
    /**
     * @var OrdersTotalRepository
     */
    private $orders_total_repository;
    public function __construct(Orders_Total_Repository $orders_total_repository)
    {
        $this->orders_total_repository = $orders_total_repository;
    }
    public function get_by_order_id($order_id, $as_array = false)
    {
        return $this->orders_total_repository->get_by_order_id($order_id, $as_array);
    }
    public function update(Orders_Total $order_totals, $params = [], $validate = false, $safe_only = false)
    {
        return $this->orders_total_repository->edit($order_totals, $params, $validate, $safe_only);
    }
}