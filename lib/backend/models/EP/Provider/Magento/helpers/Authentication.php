<?php

declare (strict_types=1);
namespace backend\models\EP\Provider\Magento;

use Yii;
use yii\authclient\O_Auth1;
class Authentication extends O_Auth1
{
    public $auth_url = '/oauth_authorize';
    /**
     * @inheritdoc
     */
    public $request_token_url = '/oauth/initiate';
    /**
     * @inheritdoc
     */
    public $request_token_method = 'POST';
    /**
     * @inheritdoc
     */
    public $access_token_url = '/oauth/token';
    /**
     * @inheritdoc
     */
    public $access_token_method = 'POST';
    /**
     * @inheritdoc
     */
    public $api_base_url = '';
    public $callback_url = '';
    public $access_token = '';
    public $access_token_secret = '';
    public $rest_path = '/api/rest';
    public function __construct($config = [])
    {
        if (substr($config['location'], -1) == '/') {
            $config['location'] = substr($config['location'], 0, -1);
        }
        $this->api_base_url = $config['location'];
        $this->auth_url = $config['location'] . $config['admin_url'] . $this->auth_url;
        $this->request_token_url = $config['location'] . $this->request_token_url;
        $this->access_token_url = $config['location'] . $this->access_token_url;
        $this->consumer_key = $config['consumer_key'];
        $this->consumer_secret = $config['consumer_secret'];
        $this->callback_url = $config['callback_url'];
        parent::__construct();
    }
    public function prepare()
    {
        if ($_SESSION['state']) {
            return;
        }
        if (!isset($_REQUEST['oauth_token'])) {
            $_SESSION['state'] = false;
            try {
                $_SESSION['requestToken'] = $request_token = $this->fetch_request_token();
            } catch (\Exception $e) {
                echo $e->get_message();
                exit;
            }
            $url = $this->build_auth_url($request_token);
            header('Location: ' . $url);
            exit;
        } else {
            $token = $this->fetch_access_token($_REQUEST['oauth_token']);
            $_SESSION['requestToken']->set_token($token->get_token());
            $_SESSION['AccessToken'] = $token;
            $_SESSION['state'] = true;
            header('Location: ' . HTTPS_CATALOG_SERVER . DIR_WS_ADMIN . Yii::$app->controller->get_route() . '?id=' . Yii::$app->get_request()->get('id'));
            exit;
        }
        return;
    }
    protected function init_user_attributes()
    {
        return [];
    }
    public function get_return_url()
    {
        return $this->callback_url;
    }
    public function set_callback_url($url)
    {
        $this->callback_url = $url;
    }
    public function get_access_token()
    {
        return $_SESSION['AccessToken'];
    }
    public function api($api_sub_url, $method = 'GET', $data = [], $headers = [])
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = '*/*';
        $default_params = ['oauth_consumer_key' => $this->consumer_key, 'oauth_token' => $_SESSION['requestToken']->get_token()];
        $default_params = array_merge($default_params, $this->generate_common_request_params());
        $url = $this->api_base_url . $this->rest_path . $api_sub_url;
        $request = $this->create_api_request()->set_method($method)->set_url($url)->add_headers($headers);
        $request->set_data(array_merge($default_params, $data));
        $this->sign_request($request, $_SESSION['AccessToken']);
        $str = [];
        foreach ($request->get_data() as $k => $h) {
            $str[] = $k . '="' . $h . '",';
            unset($headers[$k]);
        }
        $s = substr('Authorization: OAuth ' . implode(' ', $str), 0, -1);
        foreach ($headers as $k => $h) {
            $s .= "\r\n" . $k . ': ' . $h;
        }
        $opts = ['http' => ['method' => 'GET', 'header' => $s]];
        $context = stream_context_create($opts);
        try {
            $fp = file_get_contents($url, false, $context);
        } catch (\Exception $e) {
            echo $e->get_message();
        }
        return json_decode($fp);
    }
}