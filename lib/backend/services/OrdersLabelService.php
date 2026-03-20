<?php

declare (strict_types=1);
namespace backend\services;

use common\models\Orders_Label;
use common\models\repositories\Orders_Label_Repository;
use common\models\repositories\Orders_Label_To_Orders_Products_Repository;
class Orders_Label_Service
{
    /** @var OrdersLabelRepository */
    private $orders_label_repository;
    /** @var OrdersLabelToOrdersProductsRepository */
    private $label_to_orders_products_repository;
    public function __construct(Orders_Label_Repository $orders_label_repository, Orders_Label_To_Orders_Products_Repository $label_to_orders_products_repository)
    {
        $this->orders_label_repository = $orders_label_repository;
        $this->label_to_orders_products_repository = $label_to_orders_products_repository;
    }
    /**
     * @param int $orderId
     * @param int $orderLabelId
     * @param bool $asArray
     * @return array|\common\models\OrdersLabel|null
     */
    public function find_label_by_order(int $order_id, int $order_label_id, bool $as_array = false)
    {
        return $this->orders_label_repository->find_label_by_order($order_id, $order_label_id, $as_array);
    }
    /**
     * @param int $orderId
     * @param int|array $productsId
     * @param bool $asArray
     * @return array|\common\models\OrdersLabel|null
     */
    public function find_label_by_products(int $order_id, $products_id, bool $as_array = false)
    {
        $product_label = $this->label_to_orders_products_repository->find_label_by_order($order_id, $products_id, true);
        return $this->find_label_by_order((int) $product_label['orders_id'], (int) $product_label['orders_label_id'], $as_array);
    }
    /**
     * @param OrdersLabel $ordersLabel
     * @param array $params
     * @param bool $validation
     * @param bool $safeOnly
     * @return array|bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function edit(Orders_Label $orders_label, array $params = [], bool $validation = false, bool $safe_only = false)
    {
        return $this->orders_label_repository->edit($orders_label, $params, $validation, $safe_only);
    }
    /**
     * @param OrdersLabel $ordersLabel
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function remove(Orders_Label $orders_label): bool
    {
        return $this->orders_label_repository->remove($orders_label);
    }
    public function remove_order_product_labels_by_order(int $order_id): int
    {
        return $this->orders_label_repository->remove_order_product_labels_by_order($order_id);
    }
}