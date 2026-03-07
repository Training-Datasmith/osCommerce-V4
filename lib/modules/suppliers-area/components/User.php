<?php

declare(strict_types=1);

namespace suppliersarea\components;

class User extends \yii\web\User
{
    public $idParam = '__sid';

    private $_identity = false;

}
