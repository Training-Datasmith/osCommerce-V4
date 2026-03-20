<?php

declare (strict_types=1);
if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
    if ($ext::use_with_banners()) {
        $banners_data = $ext::add_banner_data($banners_data, $banners_id);
    }
}