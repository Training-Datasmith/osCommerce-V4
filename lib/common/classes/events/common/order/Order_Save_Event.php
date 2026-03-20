<?php

declare (strict_types=1);
namespace common\classes\events\common\order;

use common\classes\extended\Order_Abstract;
class Order_Save_Event
{
    /** @var OrderAbstract */
    private $order;
    public function __construct(Order_Abstract $order)
    {
        $this->order = $order;
    }
    /**
     * @return OrderAbstract
     */
    public function get_order(): Order_Abstract
    {
        return $this->order;
    }
}