<?php

declare (strict_types=1);
if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
    if ($ext::use_with_banners() && !\frontend\design\Info::is_admin()) {
        $customer_groups_id = Yii::$app->storage->get('customer_groups_id');
        $and_where .= " and (nb2p.user_groups like '%#" . $customer_groups_id . "#%' or nb2p.user_groups like '%#0#%')";
    }
}