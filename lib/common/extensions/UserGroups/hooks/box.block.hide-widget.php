<?php

declare (strict_types=1);
if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
    if ($settings[0]['user_groups'] ?? false) {
        $group_ids = explode(',', $settings[0]['user_groups']);
        $customer_groups_id = \Yii::$app->storage->get('customer_groups_id');
        if (!in_array($customer_groups_id, $group_ids)) {
            $hide = true;
        }
    }
}