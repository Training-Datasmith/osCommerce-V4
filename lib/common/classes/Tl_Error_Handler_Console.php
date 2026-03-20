<?php

declare (strict_types=1);
namespace common\classes;

class Tl_Error_Handler_Console extends \yii\console\Error_Handler
{
    use Tl_Error_Handler_Trait;
}