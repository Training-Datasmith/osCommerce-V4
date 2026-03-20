<?php

declare(strict_types=1);

if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'CumulativeDiscount', 'title' => TEXT_ACCUMULATIVE_DISCOUNT, 'description' => '', 'type' => 'general', 'class' => ''];
}
