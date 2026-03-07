<?php

declare(strict_types=1);

if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'Socials', 'title' => BOX_HEADING_SOCIALS, 'description' => '', 'type' => 'general', 'class' => ''];
}
