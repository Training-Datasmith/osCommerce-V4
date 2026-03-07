<?php

declare(strict_types=1);

namespace common\models;

/**
 * This is the model class for table "orders_status_groups".
 *
 * @property int $orders_status_groups_id
 * @property int $language_id
 * @property string $orders_status_groups_name
 * @property string $orders_status_groups_color
 * @property int $orders_status_type_id
 */
class OrdersStatusGroups extends \yii\db\ActiveRecord
{
    public const NEW_GROUP = 1;
    public const PROCESSING_GROUP = 2;
    public const INCOMPLETE_GROUP = 3;
    public const COMPLETE_GROUP = 4;
    public const CANCELLED_GROUP = 5;
    public const SUBSCRIPTION_GROUP = 6;
    public const QUOTATION_GROUP = 7;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'orders_status_groups';
    }

    public function getStatuses()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        return $this->hasMany(OrdersStatus::className(), ['orders_status_groups_id' => 'orders_status_groups_id'])->where([OrdersStatus::tableName() . '.language_id' => $languages_id]);
    }

}
