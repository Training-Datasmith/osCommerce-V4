<?php

declare (strict_types=1);
namespace common\extensions\Osc_Link\models;

/**
 * This is the model class for table "connector_osclink_mapping".
 *
 * @property int $entity_id
 * @property int $external_id
 * @property int $internal_id
 */
class Mapping extends \yii\db\Active_Record
{
    /**
     * {@inheritdoc}
     */
    public static function table_name()
    {
        return 'connector_osclink_mapping';
    }
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [[['entity_id', 'external_id', 'internal_id'], 'required'], [['entity_id', 'external_id', 'internal_id'], 'integer'], [['entity_id', 'external_id'], 'unique', 'targetAttribute' => ['entity_id', 'external_id']]];
    }
    /**
     * {@inheritdoc}
     */
    public function attribute_labels()
    {
        return ['entity_id' => 'Entity ID', 'external_id' => 'External ID', 'internal_id' => 'Internal ID'];
    }
}