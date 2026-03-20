<?php

declare (strict_types=1);
Yii::set_alias('site_root', dirname(dirname(dirname(__DIR__))));
Yii::set_alias('common', dirname(__DIR__));
Yii::set_alias('frontend', dirname(dirname(__DIR__)) . '/frontend');
Yii::set_alias('backend', dirname(dirname(__DIR__)) . '/backend');
Yii::set_alias('superadmin', dirname(dirname(__DIR__)) . '/superadmin');
Yii::set_alias('console', dirname(dirname(__DIR__)) . '/console');
Yii::set_alias('ep_files', dirname(dirname(dirname(__DIR__))) . '/ep_files');
Yii::set_alias('pos', dirname(dirname(__DIR__)) . '/pos');
Yii::set_alias('rest', dirname(dirname(__DIR__)) . '/rest');
Yii::set_alias('suppliersarea', dirname(dirname(__DIR__)) . '/modules/suppliers-area');