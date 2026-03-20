<?php

declare(strict_types=1);

if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'HeaderStock', 'title' => TEXT_HEADER_STOCK, 'description' => '', 'type' => 'general', 'class' => 'headerStock'];
}
