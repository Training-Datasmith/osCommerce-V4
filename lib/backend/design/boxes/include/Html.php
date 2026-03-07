<?php

declare(strict_types=1);

if ($type == 'email') {
    $widgets[] = ['name' => 'Html_box', 'title' => 'html', 'description' => '', 'type' => 'email', 'class' => 'html'];
}

if ($type == 'invoice') {
    $widgets[] = ['name' => 'Html_box', 'title' => 'html', 'description' => '', 'type' => 'invoice', 'class' => 'html'];
}

if ($type == 'packingslip') {
    $widgets[] = ['name' => 'Html_box', 'title' => 'html', 'description' => '', 'type' => 'packingslip', 'class' => 'html'];
}

if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'Html_box', 'title' => 'html', 'description' => '', 'type' => 'general', 'class' => 'html'];
}
