<?php

declare(strict_types=1);

namespace common\classes;

class TlErrorHandlerConsole extends \yii\console\ErrorHandler
{
    use TlErrorHandlerTrait;
}
