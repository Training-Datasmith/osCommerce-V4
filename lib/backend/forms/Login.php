<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace backend\forms;

use common\classes\Re_Captcha;
use Yii;
use yii\base\Model;
class Login extends Model
{
    public $captha_enabled = false;
    public $captcha = null;
    public $captcha_response;
    public $captcha_widget;
    private $short_name = 'Login';
    public function __construct($config = [])
    {
        if (isset($config['captha_enabled']) && $config['captha_enabled'] == true) {
            $this->captha_enabled = 'captha';
            unset($config['captha_enabled']);
        } elseif (\common\models\Fraud::under_surveillance_address()) {
            $this->captha_enabled = 'captha';
        }
        if (defined('ADMIN_LOGIN_OTP_ENABLE') and ADMIN_LOGIN_OTP_ENABLE == 'True') {
            $this->captha_enabled = 'captha';
        }
        if ($this->captha_enabled == 'captha') {
            if (defined('PREFERRED_USE_RECAPTCHA') && PREFERRED_USE_RECAPTCHA == 'True') {
                $captcha = new Re_Captcha();
                if ($captcha->is_enabled()) {
                    $this->captha_enabled = 'recaptha';
                    $this->captcha_widget = \frontend\design\boxes\Re_Captcha_Widget::widget();
                    $this->captcha = $captcha;
                }
            }
        }
        parent::__construct($config);
    }
    public function form_name()
    {
        return $this->short_name;
    }
    public function load($data, $form_name = null)
    {
        if ($this->captha_enabled == 'recaptha') {
            $form_name = '';
        }
        return parent::load($data, $form_name);
    }
    public function before_validate()
    {
        if ($this->captha_enabled == 'recaptha') {
            $this->captcha_response = Yii::$app->request->post('g-recaptcha-response', null);
        }
        return parent::before_validate();
    }
    public function rules()
    {
        $_rules = [];
        if ($this->captha_enabled == 'captha') {
            $_rules[] = ['captcha', 'required'];
            $_rules[] = ['captcha', 'captcha'];
        }
        if ($this->captha_enabled == 'recaptha') {
            $_rules[] = ['captcha_response', 'validateCaptcha', 'skipOnEmpty' => false];
        }
        return $_rules;
    }
    public function validate_captcha($attribute, $params)
    {
        if ($this->captha_enabled == 'recaptha') {
            if (!$this->captcha->check_verification($this->captcha_response)) {
                $this->add_error($attribute, 'Wrong captcha verification');
            }
        }
    }
    public function scenarios()
    {
        return ['default' => $this->collect_fields(null)];
    }
    public function collect_fields($type)
    {
        $fields = [];
        if ($this->captha_enabled == 'captha') {
            $fields[] = 'captcha';
        }
        if ($this->captha_enabled == 'recaptha') {
            $fields[] = 'captcha_response';
        }
        return $fields;
    }
}