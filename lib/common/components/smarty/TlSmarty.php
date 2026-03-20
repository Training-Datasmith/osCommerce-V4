<?php

declare (strict_types=1);
namespace common\components\smarty;

class Tl_Smarty extends \yii\smarty\Extension
{
    public function __construct($view_renderer, $smarty)
    {
        parent::__construct($view_renderer, $smarty);
        $smarty->register_plugin('modifier', 'json_encode', 'json_encode');
        $smarty->register_plugin('modifier', 'is_array', 'is_array');
        $smarty->register_plugin('modifier', 'intval', 'intval');
        $smarty->register_plugin('modifier', 'trim', 'trim');
        $smarty->register_plugin('modifier', 'constant', 'constant');
    }
}