<?php

declare (strict_types=1);
namespace common\helpers;

class Smarty
{
    public static function render_str($str_template, $params = [])
    {
        $smarty = new \Smarty();
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                $smarty->assign($key, $value);
            }
        }
        return $smarty->fetch('string:' . $str_template);
    }
}