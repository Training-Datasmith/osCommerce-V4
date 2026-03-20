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
namespace common\components;

use common\models\Gdpr_Check;
use Yii;
class Gdpr
{
    private $entity = null;
    private $gdpr_check = null;
    private $today_date;
    private $dob_date;
    private $today_date_time = null;
    private $dob_date_time = null;
    private $error = false;
    private $mistake = false;
    private $message = '';
    private $email = null;
    public function __construct($user_identity = null)
    {
        if ($user_identity instanceof \common\models\Customers) {
            $this->entity = $user_identity;
            $this->set_dob_date($this->entity->customers_dob);
        }
        $this->set_today_date();
    }
    public function valid_token($token)
    {
        $this->gdpr_check = Gdpr_Check::find_one(['token' => $token]);
        return $this->gdpr_check;
    }
    public function get_token_entity()
    {
        return $this->gdpr_check;
    }
    public function get_entity()
    {
        return $this->entity;
    }
    public function is_valid_gdpr()
    {
        return;
    }
    public function get_error()
    {
        return $this->error;
    }
    public function get_message()
    {
        return $this->message;
    }
    public function has_mistake()
    {
        return $this->mistake;
    }
    public function set_email($email)
    {
        $this->email = $email;
    }
    public function set_dob_date($date)
    {
        if (!empty($date) && $date != '0000-00-00 00:00:00') {
            if (checkdate(date('m', strtotime($date)), date('d', strtotime($date)), date('Y', strtotime($date)))) {
                $this->dob_date = date('Y-m-d', strtotime($date));
                return true;
            }
        }
        $this->error = true;
        $this->message = ENTRY_DATE_OF_BIRTH_ERROR;
        return false;
    }
    public function set_today_date()
    {
        $this->today_date = date('Y-m-d');
    }
    public function get_t_difference()
    {
        if (is_null($this->dob_date_time)) {
            $this->dob_date_time = new \DateTime($this->dob_date);
        }
        if (is_null($this->today_date_time)) {
            $this->today_date_time = new \DateTime($this->today_date);
        }
        return $this->dob_date_time->diff($this->today_date_time);
    }
    public function is_fraud()
    {
        $fraud = false;
        if (!empty($this->dob_date) && $this->dob_date != '0000-00-00 00:00:00') {
            $difference = $this->get_t_difference();
            $fraud = $difference->invert == 1 || $difference->y < 13 ? true : false;
        }
        return $fraud;
    }
    public function generate_token()
    {
        do {
            $new_token = \common\helpers\Password::create_random_value(32);
            $check_token = Gdpr_Check::find()->where(['token' => $new_token])->one();
        } while ($check_token);
        return $new_token;
    }
    public function save_gdpr_check()
    {
        if ($this->entity) {
            $u_gdpr = new Gdpr_Check();
            $token = $this->generate_token();
            $u_gdpr->set_attributes(['customers_id' => $this->entity->customers_id, 'email' => $this->entity->customers_email_address, 'token' => $token], false);
            $u_gdpr->save(false);
            return $token;
        }
        return false;
    }
    public function get_gdpr_token()
    {
        if ($this->entity) {
            $gdpr_check = Gdpr_Check::find_one($this->entity->customers_id);
            if (!$gdpr_check) {
                $token = $this->save_gdpr_check();
            } else {
                $token = $gdpr_check->token;
            }
            return $token;
        }
        return false;
    }
    //account login
    public function process_gdpr_checking()
    {
        if (ACCOUNT_GDPR == 'true' && in_array(ACCOUNT_DOB, ['required_register', 'visible_register']) && !$this->entity->dob_flag) {
            if (empty($this->entity->customers_dob) || $this->entity->customers_dob == '0000-00-00 00:00:00' || $this->is_fraud()) {
                $new_token = $this->get_gdpr_token();
                if (Yii::$app->request->is_ajax) {
                    global $message_stack;
                    if (is_object($message_stack)) {
                        $message_stack->add_session('login', ENTRY_DATE_OF_BIRTH_ERROR);
                    }
                    tep_redirect(tep_href_link('account/update', 'token=' . $new_token, 'SSL'));
                } else {
                    tep_redirect(tep_href_link('account/update', 'token=' . $new_token, 'SSL'));
                }
                exit;
            }
        }
    }
    private function add_interval(\DateTime $date, $period)
    {
        $date->add($period);
    }
    public function validate_gdpr()
    {
        if (ACCOUNT_GDPR == 'true' && $this->dob_date) {
            $difference = $this->get_t_difference();
            if ($difference->invert == 1) {
                $this->message = ENTRY_DATE_OF_BIRTH_ERROR;
                $this->mistake = true;
            } elseif ($difference->y < 13) {
                $this->add_interval($this->dob_date_time, new \DateInterval('P13Y'));
                $this->add_interval($this->today_date_time, new \DateInterval('P1Y'));
                $difference = $this->get_t_difference();
                if ($difference->invert == 1) {
                    $ban_period = $this->today_date_time->format('Y-m-d');
                } else {
                    $ban_period = $this->dob_date_time->format('Y-m-d');
                }
                $this->error = true;
                $this->message = ENTRY_DATE_OF_BIRTH_RESTRICTION;
                if ($this->gdpr_check) {
                    \common\models\Young_Customers::delete_all(['email' => md5($this->gdpr_check->email)]);
                    $this->ban_user($this->gdpr_check->email, $ban_period);
                    \common\helpers\Customer::delete_customer($this->gdpr_check->customers_id);
                    $this->gdpr_check->delete();
                } else if (!is_null($this->email)) {
                    \common\models\Young_Customers::delete_all(['email' => md5($this->email)]);
                    $this->ban_user($this->email, $ban_period);
                }
            }
        }
        $this->after_validation();
    }
    public function after_validation()
    {
        if (!$this->error && !$this->mistake) {
            if ($this->gdpr_check) {
                \common\models\Young_Customers::delete_all(['email' => md5($this->gdpr_check->email)]);
                $this->gdpr_check->delete();
            }
        }
    }
    public function ban_user($email, $period)
    {
        $ban = new \common\models\Young_Customers();
        $ban->email = md5($email);
        $ban->expiration_date = $period;
        $ban->save();
    }
    public function is_banned()
    {
        if ($this->gdpr_check) {
            return is_object(\common\models\Young_Customers::find()->where(['and', ['email' => md5($this->gdpr_check->email)], ['>', 'expiration_date', date('Y-m-d')]])->one());
        }
        return false;
    }
}