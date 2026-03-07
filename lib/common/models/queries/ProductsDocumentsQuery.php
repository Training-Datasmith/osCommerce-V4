<?php

declare(strict_types=1);

namespace common\models\queries;

use common\models\Product\ProductsDocuments;
use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[ProductsDocuments]].
 *
 * @see ProductsDocuments
 */
class ProductsDocumentsQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return ProductsDocuments[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return ProductsDocuments|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }

    /**
     * @param int|null $languageId
     * @return ProductsDocumentsQuery
     */
    public function withDescription(?int $languageId = null)
    {
        if (!$languageId) {
            return $this->joinWith(['titles pdt']);
        }
        return $this->joinWith(['title pdt' => function (ActiveQuery $query) use ($languageId) {
            $query->andOnCondition(['language_id' => $languageId]);
        }]);
    }
}
