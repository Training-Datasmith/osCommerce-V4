<?php

declare (strict_types=1);
namespace backend\models\forms;

use common\models\Platforms;
use common\models\Recover_Cart_Config;
use yii\base\Model;
class Recover_Cart_Config_Form_Container extends Model
{
    /**
     * @var array | RecoverCartConfigForm
     */
    private $_forms = [];
    public function __construct(array $config = [])
    {
        $platforms = Platforms::get_platforms_by_type('physical')->select('platform_id')->active()->index_by('platform_id')->as_array()->column();
        $recover_cart_configs = Recover_Cart_Config::find()->index_by('platform_id')->and_where(['platform_id' => $platforms])->all();
        foreach ($recover_cart_configs as $recover_cart_config) {
            $config_form = new Recover_Cart_Config_Form();
            $config_form->set_attributes($recover_cart_config->attributes);
            $this->_forms[] = $config_form;
        }
        foreach (array_diff($platforms, array_keys($recover_cart_configs)) as $platform) {
            $this->_forms[] = new Recover_Cart_Config_Form(['platform_id' => $platform]);
        }
        parent::__construct($config);
    }
    public function load($data, $form_name = null)
    {
        $load_forms = Model::load_multiple($this->_forms, $data, $form_name === null ? null : 'RecoverCartConfigForm');
        return $load_forms;
    }
    public function save()
    {
        foreach ($this->_forms as $form) {
            if ($row = Recover_Cart_Config::find_one(['platform_id' => $form->platform_id])) {
                $row->edit($form);
            } else {
                $row = Recover_Cart_Config::create($form);
            }
            $row->save();
        }
    }
    public function get_forms()
    {
        return $this->_forms;
    }
}