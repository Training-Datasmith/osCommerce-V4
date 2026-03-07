<?php

declare(strict_types=1);

namespace common\models\queries;

use yii\db\ActiveQuery;

class CustomersErrorsQuery extends ActiveQuery
{
    public function linkingTo($className)
    {
        return $this->innerJoin(
            $className::tableName(). ' cbt',
            "{$this->modelClass::tableName()}.basket_id = cbt.basket_id and {$this->modelClass::tableName()}.customers_id = cbt.customers_id"
        );
    }

}
