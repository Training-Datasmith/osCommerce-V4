<?php

declare (strict_types=1);
namespace common\components;

use Yii;
use yii\authclient\Client_Interface;
use yii\authclient\O_Auth1;
use yii\authclient\O_Auth2;
use yii\authclient\Open_Id;
use yii\helpers\Array_Helper;
class Socials
{
    private static $defined_modules = [['name' => 'google', 'class' => 'yii\authclient\clients\Google', 'site' => 'https://console.developers.google.com'], ['name' => 'facebook', 'class' => 'yii\authclient\clients\Facebook', 'site' => 'https://developers.facebook.com'], ['name' => 'twitter', 'class' => 'yii\authclient\clients\Twitter', 'site' => 'https://apps.twitter.com'], ['name' => 'linkedin', 'class' => 'yii\authclient\clients\LinkedIn', 'site' => 'https://www.linkedin.com/secure/developer'], ['name' => 'instagram', 'class' => 'yii\authclient\clients\Instagram', 'site' => 'https://www.instagram.com/developer'], ['name' => 'youtube', 'class' => 'yii\authclient\clients\Youtube', 'site' => 'https://developers.google.com/youtube/v3/'], ['name' => 'amazonlogin', 'class' => 'common\extensions\Amazon\authclient\Amazon', 'site' => 'https://sellercentral-europe.amazon.com/']];
    private $client;
    public const HASHCODE = 'kdk73mjdJjalkas-0!ksjsdl((232kaj';
    public function __construct(Client_Interface $client)
    {
        $this->client = $client;
    }
    public static function get_defined_modules()
    {
        static $is_hook_executed = false;
        if (!$is_hook_executed) {
            $is_hook_executed = true;
            foreach (\common\helpers\Hooks::get_list('socials/get-defined-modules') as $filename) {
                include $filename;
            }
        }
        return self::$defined_modules;
    }
    public static function get_site_url($_module)
    {
        $modules = Array_Helper::map(self::get_defined_modules(), 'name', 'site');
        return $modules[$_module] ?? null;
    }
    public static function load_components($platform_id, $default = '')
    {
        if (tep_not_null($default)) {
            $_modules = tep_db_query('select * from ' . TABLE_SOCIALS . " where platform_id = '{$platform_id}' and module = '" . tep_db_input($default) . "'");
        } else {
            $_modules = tep_db_query('select * from ' . TABLE_SOCIALS . " where platform_id = '{$platform_id}' and active = 1");
        }
        if (tep_db_num_rows($_modules)) {
            $clients = [];
            $_dm = \yii\helpers\Array_Helper::map(self::get_defined_modules(), 'name', 'class');
            while ($row = tep_db_fetch_array($_modules)) {
                $clients[$row['module']] = ['class' => $_dm[$row['module']]];
                if (in_array($row['module'], ['twitter'])) {
                    $clients[$row['module']]['consumerKey'] = $row['client_id'];
                    $clients[$row['module']]['consumerSecret'] = $row['client_secret'];
                    $clients[$row['module']]['attributeParams'] = ['include_email' => 'true'];
                } else {
                    $clients[$row['module']]['clientId'] = $row['client_id'];
                    $clients[$row['module']]['clientSecret'] = $row['client_secret'];
                }
            }
            if (count($clients)) {
                if (is_object(Yii::$app->auth_client_collection)) {
                    Yii::$app->auth_client_collection->set_clients($clients);
                }
            }
        }
    }
    public static function load_social_addons($platform_id)
    {
        $_modules = tep_db_query('select * from ' . TABLE_SOCIALS . ' s left join ' . TABLE_SOCIALS_ADDONS . " sa on sa.socials_id = s.socials_id where platform_id = '{$platform_id}'");
        if (tep_db_num_rows($_modules)) {
            while ($row = tep_db_fetch_array($_modules)) {
                if (!is_null($row['configuration_key'])) {
                    defined($row['configuration_key']) or define($row['configuration_key'], $row['configuration_value']);
                }
            }
        }
    }
    public function handle()
    {
        $attributes = $this->client->get_user_attributes();
        $attributes = $this->client->prepare_attributes($attributes);
        $email = tep_db_prepare_input(Array_Helper::get_value($attributes, 'email'));
        $gender = tep_db_prepare_input(Array_Helper::get_value($attributes, 'gender'));
        $firstname = tep_db_prepare_input(Array_Helper::get_value($attributes, 'firstname'));
        $lastname = tep_db_prepare_input(Array_Helper::get_value($attributes, 'lastname'));
        $customer = new Customer(Customer::LOGIN_SOCIALS);
        if (!$customer->login_customer($email, static::HASHCODE)) {
            if (tep_not_null($email)) {
                $model = new \frontend\forms\registration\Customer_Registration();
                if (ENABLE_CUSTOMER_GROUP_CHOOSE == 'True') {
                    $model->group = 0;
                    //ToDo, ask customer for group
                } else if (!defined('DEFAULT_USER_LOGIN_GROUP')) {
                    $model->group = 0;
                } else {
                    $model->group = DEFAULT_USER_LOGIN_GROUP;
                }
                $model->password = \common\helpers\Password::create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
                $model->newsletter = 0;
                $model->email_address = $email;
                if (isset($gender) && !empty($gender)) {
                    $model->gender = $gender;
                }
                if (isset($firstname) && !empty($firstname)) {
                    $model->firstname = $firstname;
                }
                if (isset($lastname) && !empty($lastname)) {
                    $model->lastname = $lastname;
                }
                if (defined('SOCIALS_NEW_ACCOUNT_COUNTRY') && strtolower(SOCIALS_NEW_ACCOUNT_COUNTRY) == 'empty') {
                    $model->country = 0;
                } else {
                    $model->country = (int) STORE_COUNTRY;
                    //$model->zone_id = (int)STORE_ZONE;
                }
                if (is_object($attributes['address'])) {
                    $customer->register_customer($model, true, $attributes['address']);
                } else {
                    $customer->register_customer($model);
                }
            } else {
                \Yii::$container->get('message_stack')->add_session(TEXT_INVALID_EMAIL, 'login', 'error');
                return Yii::$app->controller->redirect(['account/login']);
            }
        }
        foreach (\common\helpers\Hooks::get_list('frontend/socials/login-success') as $filename) {
            include $filename;
        }
        global $cart;
        if ($cart->count_contents()) {
            return Yii::$app->controller->redirect(['checkout/']);
        }
        return Yii::$app->controller->redirect(['account/']);
    }
    public function test($socials_id, $paltform_id)
    {
        $url = '';
        $platform_config = new \common\classes\platform_config($paltform_id);
        $redirect = $platform_config->get_catalog_base_url(true) . 'account/auth';
        if ($this->client instanceof O_Auth2) {
            $this->client->set_return_url($redirect);
            $url = $this->client->build_auth_url();
            //$response = Yii::$app->getResponse()->redirect($url);
        } elseif ($this->client instanceof O_Auth1) {
            $this->client->set_return_url($redirect);
            $request_token = $this->client->fetch_request_token();
            $url = $this->client->build_auth_url($request_token);
            //$response = Yii::$app->getResponse()->redirect($url);
        } elseif ($this->client instanceof Open_Id) {
            $this->client->set_return_url($redirect);
        }
        {
            $url = $this->client->build_auth_url();
            //$response = Yii::$app->getResponse()->redirect($url);
        }
        $info = [];
        if (!empty($url)) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLINFO_HEADER_OUT, 1);
            $response = curl_exec($curl);
            $info = curl_getinfo($curl);
        }
        $success = 1;
        if (!isset($info['http_code']) || $info['http_code'] != 200) {
            $success = 0;
        }
        if ($socials_id) {
            tep_db_query('update ' . TABLE_SOCIALS . " set test_success = '" . (int) $success . "' where socials_id = '" . (int) $socials_id . "'");
            if (!$success) {
                tep_db_query('update ' . TABLE_SOCIALS . " set active = '0' where socials_id = '" . (int) $socials_id . "'");
            }
        }
        return (bool) $success;
    }
}