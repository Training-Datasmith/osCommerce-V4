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
namespace common\classes;

class Captcha
{
    private $identity_by;
    private $field;
    private $captcha;
    private $widget;
    public function __construct($field, $identity_by = null)
    {
        $this->identity_by = $identity_by;
        $captcha = new \common\classes\Re_Captcha();
        if (defined('PREFERRED_USE_RECAPTCHA') && PREFERRED_USE_RECAPTCHA == 'True' && $captcha->is_enabled()) {
            $this->widget = \frontend\design\boxes\Re_Captcha_Widget::widget();
            $this->captcha = 'recaptcha';
            $this->field = 'g-recaptcha-response';
        } else {
            $this->field = $field;
            $params = ['attribute' => 'captcha'];
            $type = is_object($this->identity_by) ? 'model' : 'name';
            $params[$type] = $this->field;
            $this->captcha = 'captcha';
            $this->widget = \yii\captcha\Captcha::widget($params);
        }
    }
    public function get_widget()
    {
        return $this->widget;
    }
    public function is_valid($post)
    {
        switch ($this->captcha) {
            case 'recaptcha':
                $captcha = new \common\classes\Re_Captcha();
                return $captcha->check_verification($post[$this->field] ?? null);
                break;
            case 'captcha':
                if (is_object($this->identity_by && property_exists($this->identity_by, $this->field))) {
                    $user_value = $this->identity_by->{$this->field};
                } else {
                    $user_value = $post[$this->field] ?? null;
                }
                return (new \yii\captcha\Captcha_Validator())->validate($user_value);
                break;
        }
        return false;
    }
}