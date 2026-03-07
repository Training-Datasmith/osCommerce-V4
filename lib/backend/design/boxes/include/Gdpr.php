<?php

declare(strict_types=1);

if ($type == 'checkout') {
    $widgets[] = [
        'name' => 'checkout\Gdpr',
        'title' => TEXT_INFO_GDPR,
        'description' => '',
        'type' => 'checkout',
        'class' => '',
    ];
}
