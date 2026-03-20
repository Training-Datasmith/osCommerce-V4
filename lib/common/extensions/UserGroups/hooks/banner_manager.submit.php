<?php

declare (strict_types=1);
if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
    if ($ext::use_with_banners()) {
        $ext::save_banner_user_group();
    }
}