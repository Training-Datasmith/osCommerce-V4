<?php

declare (strict_types=1);

namespace common\classes\events\common\order;

use common\classes\extended\OrderAbstract;
use common\classes\Order;

class OrderSetParentEvent
{
    /** @var OrderAbstract */
    private $parentOrder;
    private $order;

    public function __construct(OrderAbstract $parentOrder, $order)
    {
        $this->parentOrder = $parentOrder;
        if (is_scalar($order)) {
            $this->order = new Order($order);
        } elseif ($order instanceof Order) {
            $this->order = $order;
        }

        if (!is_object($this->order)) {
            throw new \Exception('Order not found');
        }
    }

    /**
     * @return OrderAbstract
     */
    public function getParentOrder(): OrderAbstract
    {
        return $this->parentOrder;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}
