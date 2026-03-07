<?php

declare(strict_types=1);

if ($type == 'checkout') {
    $widgets[] = [
        'name' => 'checkout\PoNumber',
        'title' => TEXT_PO_NUMBER,
        'description' => '',
        'type' => 'checkout',
        'class' => '',
    ];
}
