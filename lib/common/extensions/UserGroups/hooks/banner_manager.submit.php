<?php

declare(strict_types=1);
if ($ext = \common\helpers\Acl::checkExtensionAllowed('UserGroups', 'allowed')) {
    if ($ext::useWithBanners()) {
        $ext::saveBannerUserGroup();
    }
}
