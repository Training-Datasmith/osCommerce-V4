<?php

declare (strict_types=1);
namespace common\classes\events\common\order;

use common\classes\extended\Order_Abstract;
use common\classes\Order;
class Order_Set_Parent_Event
{
    /** @var OrderAbstract */
    private $parent_order;
    private $order;
    public function __construct(Order_Abstract $parent_order, $order)
    {
        $this->parent_order = $parent_order;
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
    public function get_parent_order(): Order_Abstract
    {
        return $this->parent_order;
    }
    public function get_order(): Order
    {
        return $this->order;
    }
}