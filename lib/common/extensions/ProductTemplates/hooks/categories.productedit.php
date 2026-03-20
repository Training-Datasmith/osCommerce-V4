<?php

declare (strict_types=1);
if ($tab_access->tab_data_save('TEXT_MAIN_DETAILS')) {
    if ($ext = \common\helpers\Acl::check_extension_allowed('ProductTemplates', 'allowed')) {
        $ext::product_submit($products_id);
    }
}