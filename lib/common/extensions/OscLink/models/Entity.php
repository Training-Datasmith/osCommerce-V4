<?php

declare (strict_types=1);
namespace common\extensions\Osc_Link\models;

/**
 * This is the model class for table "connector_osclink_entity".
 *
 * @property int $id
 * @property int $project_id
 * @property string $entity_name
 */
class Entity extends \yii\db\Active_Record
{
    /**
     * {@inheritdoc}
     */
    public static function table_name()
    {
        return 'connector_osclink_entity';
    }
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [[['project_id', 'entity_name'], 'required'], [['project_id'], 'integer'], [['entity_name'], 'string', 'max' => 128]];
    }
    /**
     * {@inheritdoc}
     */
    public function attribute_labels()
    {
        return ['id' => 'ID', 'project_id' => 'Project ID', 'entity_name' => 'Entity Name'];
    }
    public function get_mapping()
    {
        return $this->has_many(Mapping::class, ['entity_id' => 'id']);
    }
    public static function clean_mapping()
    {
        $status_id = self::return_entity_id('@order_status');
        $condition_mapping = empty($status_id) ? '' : "entity_id <> {$status_id}";
        $condition_entity = empty($status_id) ? '' : "id <> {$status_id}";
        \common\extensions\Osc_Link\models\Mapping::delete_all($condition_mapping);
        \common\extensions\Osc_Link\models\Entity::delete_all($condition_entity);
    }
    public static function is_mapped_exist()
    {
        $status_id = self::return_entity_id('@order_status');
        $condition_mapping = empty($status_id) ? '' : "entity_id <> {$status_id}";
        return !empty(Mapping::find()->where($condition_mapping)->one());
    }
    public static function return_entity_id($name, $project_id = 1)
    {
        $row = self::find_one(['project_id' => $project_id, 'entity_name' => $name]);
        return empty($row) ? null : $row->id;
    }
    public static function force_entity_id($name, $project_id = 1)
    {
        $res = self::return_entity_id($name, $project_id);
        if (empty($res)) {
            $row = new self();
            $row->project_id = $project_id;
            $row->entity_name = $name;
            $row->save(false);
            $row->refresh();
            $res = $row->id;
        }
        return $res;
    }
}