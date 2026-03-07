<?php

declare(strict_types=1);

if ($type != 'email' && $type != 'invoice' && $type != 'packingslip' && $type != 'pdf' && $type != 'orders') {
    $widgets[] = ['name' => 'YoutubeVideo', 'title' => TEXT_YOUTUBE_VIDEO, 'description' => '', 'type' => 'general', 'class' => ''];
}
