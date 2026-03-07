<?php

declare(strict_types=1);

namespace common\classes;

class TlErrorHandler extends \yii\web\ErrorHandler
{
    use TlErrorHandlerTrait;
}
