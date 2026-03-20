<?php

declare (strict_types=1);
namespace common\extensions\Osc_Link\models;

/**
 * This is the model class for table "connector_osclink_configuration".
 *
 * @property string $cmc_key
 * @property string $cmc_value
 * @property string $cmc_upd_date
 * @property int $cmc_upd_admin
 */
class Configuration extends \yii\db\Active_Record
{
    /**
     * {@inheritdoc}
     */
    public static function table_name()
    {
        return 'connector_osclink_configuration';
    }
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [[['cmc_key', 'cmc_upd_admin'], 'required'], [['cmc_upd_date'], 'safe'], [['cmc_upd_admin'], 'integer'], [['cmc_key'], 'string', 'max' => 128], [['cmc_value'], 'string', 'max' => 250], [['cmc_key'], 'unique']];
    }
    /**
     * {@inheritdoc}
     */
    public function attribute_labels()
    {
        return ['cmc_key' => 'Cmc Key', 'cmc_value' => 'Cmc Value', 'cmc_upd_date' => 'Cmc Upd Date', 'cmc_upd_admin' => 'Cmc Upd Admin'];
    }
    public function before_save($insert)
    {
        if (!parent::before_save($insert)) {
            return false;
        }
        $this->cmc_upd_date = date('Y-m-d H:i:s');
        $this->cmc_upd_admin = \Yii::$app->session->get('login_id');
        return true;
    }
    public static function find_cancel_sign()
    {
        return self::find_one('.cancel_sign');
    }
    public static function is_cancel_sign()
    {
        $row = self::find_cancel_sign();
        return !empty($row) && $row->cmc_value == 'TRUE';
    }
    public static function throw_if_canceled()
    {
        if (self::is_cancel_sign()) {
            \Osc_Link\Logger::print('Cancel sign detected');
            throw new \yii\base\User_Exception('Process was canceled by user.');
            //throw new \Exception('Process was canceled by user.');
        }
    }
    public static function create_cancel_sign()
    {
        $row = self::find_cancel_sign();
        if (empty($row)) {
            $row = new self();
            $row->cmc_key = '.cancel_sign';
        }
        $row->cmc_value = 'TRUE';
        $row->save(false);
    }
    public static function delete_cancel_sign()
    {
        $row = self::find_cancel_sign();
        if (!empty($row)) {
            $row->delete();
        }
    }
}