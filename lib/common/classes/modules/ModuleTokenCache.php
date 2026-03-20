<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 *
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright 2000-2023 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\classes\modules;

use yii\httpclient\Client;
/**
 * cache access token (encrypted in DB)
 * implement in your module
 * protected function getToken():['token' => 'xxx', 'until' => 'db_date_time|time_seconds|epoch_until'];
 *
 * if use prepareSendRequest
 *
 * protected function getRestAPIUser() {}
 * protected function getRestApiPassword() {$ret = $this->decryptConst('MODULE_xxxx_CLIENT_SECRET'); return $ret; }
 * protected function getTokenUrl() {}
 * protected function getApiUrl($action) {}
 *
 *
 * optionally
 * protected function getEncryptionKey():string {}
 *
 */
trait Module_Token_Cache
{
    public $auth_platform_id = 0;
    public $auth_login_id = 0;
    public $auth_location = 'db';
    //2do file
    /**
     * get token from Cache, if not found/expired - call getToken and save new token in cache.
     * @return string
     * @throws type
     */
    protected function get_cache_token()
    {
        $platform_id = $admin_id = 0;
        if (!empty($this->auth_platform_id)) {
            $platform_id = intval($this->auth_platform_id);
        }
        if (!empty($this->auth_login_id)) {
            $admin_id = intval($this->auth_login_id);
        }
        $q = \common\models\Module_Tokens::find()->and_where(['>', 'valid_until', date(\common\helpers\Date::DATABASE_DATETIME_FORMAT)])->and_where(['class' => !empty($this->code) ? $this->code : $this->get_module_code(), 'admin_id' => $admin_id, 'platform_id' => $platform_id]);
        $cached = $q->one();
        if (!empty($cached->token)) {
            $ret = $cached->token;
            if (method_exists($this, 'getEncryptionKey')) {
                $key = $this->get_encryption_key();
            }
            if (empty($key)) {
                $key = \Yii::$app->params['secKey.backend'];
            }
            $ret = \Yii::$app->security->decrypt_by_key(utf8_decode($ret), $key);
        }
        if (empty($ret)) {
            if (!method_exists($this, 'getToken')) {
                throw new \Exception('Method getToken does not exists');
            }
            $token_info = $this->get_token();
            if (empty($token_info['token'])) {
                throw new \Exception('New Token not found');
            }
            $this->save_token_to_cache($token_info);
            $ret = $token_info['token'];
        }
        return $ret;
    }
    protected function save_token_to_cache($token_info)
    {
        $token = $token_info['token'];
        $until = $token_info['until'];
        if (is_numeric($until)) {
            //time or linux epoch
            // generally 10sec delay is too huge (token is taken from cache right before request - all request details already prepared).
            if ($until < 1689000000) {
                $until = date(\common\helpers\Date::DATABASE_DATETIME_FORMAT, time() + $until - 10);
            } else {
                $until = date(\common\helpers\Date::DATABASE_DATETIME_FORMAT, $until);
            }
        }
        $platform_id = $admin_id = 0;
        if (!empty($this->auth_platform_id)) {
            $platform_id = intval($this->auth_platform_id);
        }
        if (!empty($this->auth_login_id)) {
            $admin_id = intval($this->auth_login_id);
        }
        if (method_exists($this, 'getEncryptionKey')) {
            $key = $this->get_encryption_key();
        }
        if (empty($key)) {
            $key = \Yii::$app->params['secKey.backend'];
        }
        if ($this->auth_location == 'db') {
            \common\models\Module_Tokens::delete_all(['class' => !empty($this->code) ? $this->code : $this->get_module_code(), 'admin_id' => $admin_id, 'platform_id' => $platform_id]);
            $model = new \common\models\Module_Tokens();
            $model->load_default_values();
            $model->set_attributes(['class' => !empty($this->code) ? $this->code : $this->get_module_code(), 'admin_id' => $admin_id, 'platform_id' => $platform_id, 'valid_until' => $until, 'token' => utf8_encode(\Yii::$app->security->encrypt_by_key($token, $key))]);
            $model->save();
        }
    }
    /**
     *
     * @param string $type
     * @param array|false $params POST data or False to send GET request
     * @param array $url_params
     * @return array ['error' => , 'description' => , 'http_code' => , 'data' => ];
     */
    protected function prepare_send_request($type, $params, $url_params = [])
    {
        $url = $this->get_api_url($type);
        if (!empty($url_params)) {
            if (!is_array($url_params)) {
                $url_params = [$url_params];
            }
            $url = vsprintf($url, $url_params);
        }
        $client = new Client(['requestConfig' => ['format' => $type != 'get_token' ? Client::FORMAT_JSON : Client::FORMAT_RAW_URLENCODED], 'responseConfig' => ['format' => Client::FORMAT_JSON], 'parsers' => ['json' => '\yii\httpclient\JsonParser']]);
        $request = $client->create_request();
        $request->set_method('post');
        try {
            if ($type != 'get_token') {
                $request->headers->set('Authorization', 'bearer ' . $this->get_cache_token());
            } else {
                $url = $this->get_token_url();
                $username = $this->get_rest_api_user();
                $password = $this->get_rest_api_password();
                $request->headers->set('Authorization', 'Basic ' . base64_encode("{$username}:{$password}"));
            }
            if ($params === false) {
                $request->set_method('get');
            }
            $request->set_url($url)->set_data($params);
            if (!empty($this->debug)) {
                if ($this->debug > 1) {
                    \Yii::warning(print_r($request, true), $this->code . 'REQUEST');
                } else {
                    \Yii::warning($url . ' post  => ' . print_r($params, true), $this->code . 'REQUEST');
                }
            }
            $transaction_response = $request->send();
            if (!empty($this->debug) && $this->debug > 1) {
                \Yii::warning(print_r($transaction_response, true), $this->code . 'RESPONCE');
            }
            if ($transaction_response->is_ok) {
                $return = ['http_code' => $transaction_response->get_status_code(), 'data' => $transaction_response->get_data()];
            } else {
                $return = ['error' => 1, 'description' => ''];
                $data = $transaction_response->get_data();
                if (!empty($data['description'])) {
                    $return['description'] = $data['description'];
                }
                $data = json_decode($transaction_response->get_content(), true);
                if (!empty($data['errors']) && is_array($data['errors'])) {
                    foreach ($data['errors'] as $error) {
                        $return['description'] .= ' ' . $error['description'] . ' ' . ($error['property'] ?? '');
                    }
                } elseif (!empty($data['statusDetail'])) {
                    $return['description'] = $data['statusDetail'];
                }
            }
        } catch (\Exception $ex) {
            $return = $ex->get_message();
        }
        if (!empty($this->debug)) {
            \Yii::warning(print_r($return, true), $this->code . 'RESPONCE');
        }
        return $return;
    }
}