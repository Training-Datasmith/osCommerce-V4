<?php

declare(strict_types=1);
if ($type == 'invoice') {
    $widgets[] = ['name' => 'invoice\InvoiceNote', 'title' => 'Invoice Note', 'description' => '', 'type' => 'invoice', 'class' => 'invoice'];
}
if ($type == 'packingslip') {
    $widgets[] = ['name' => 'invoice\InvoiceNote', 'title' => 'Invoice Note', 'description' => '', 'type' => 'packingslip', 'class' => 'packingslip'];
}
