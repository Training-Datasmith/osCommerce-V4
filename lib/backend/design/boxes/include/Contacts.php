<?php

declare(strict_types=1);

if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'Contacts', 'title' => TEXT_CONTACTS, 'description' => '', 'type' => 'general', 'class' => ''];
}
