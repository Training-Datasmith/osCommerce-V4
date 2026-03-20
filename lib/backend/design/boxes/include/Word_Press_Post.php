<?php

declare(strict_types=1);

if (false && $type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'WordPressPost', 'title' => TEXT_WORDPRESS_POST, 'description' => '', 'type' => 'general', 'class' => ''];
}
