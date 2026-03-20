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
namespace backend\models\EP\Provider\Magento\helpers;

class Soap_Client
{
    private $config;
    private $client;
    public function __construct($config)
    {
        $this->config = $config;
        try {
            $this->client = new \Soap_Client($this->config['location'] . '/api/soap/?wsdl', [
                'trace' => 1,
                //'proxy_host'     => "localhost",
                //'proxy_port'     => 8080,
                //'proxy_login'    => "some_name",
                //'proxy_password' => "some_password",
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                'stream_context' => stream_context_create(['http' => []]),
            ]);
            $auth = new \stdClass();
            $auth->api_key = $this->config['api_key'];
            $soap_headers = new \Soap_Header('http://schemas.xmlsoap.org/ws/2002/07/utility', 'auth', $auth, false);
            $this->client->__set_soap_headers($soap_headers);
        } catch (\Exception $ex) {
            throw new Exception('Configuration error');
        }
    }
    public function get_client()
    {
        return $this->client;
    }
    public function login_client()
    {
        try {
            $session = $this->client->login($this->config['api_user'], $this->config['api_key']);
        } catch (\Exception $ex) {
            throw new \Exception('Authentication error');
        }
        return $session;
    }
}