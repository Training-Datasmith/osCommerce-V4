<?php

declare(strict_types=1);
if ($ext = \common\helpers\Acl::checkExtensionAllowed('UserGroups', 'allowed')) {
    if ($ext::useWithBanners()) {
        $banners_data = $ext::addBannerData($banners_data, $banners_id);
    }
}
