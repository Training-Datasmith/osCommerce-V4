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
namespace Osc_Link;

use common\helpers\Assert;
use common\helpers\Php8;
class Downloader
{
    private $api_url_base = '';
    private $api_url = '';
    private $api_method = '';
    private $api_key = '';
    private $working_dir;
    public function __construct(array $configuration_array)
    {
        $this->working_dir = dirname(__DIR__) . '/temp/';
        $this->api_url_base = trim($configuration_array['api_url']['cmc_value'] ?? '');
        $pos = strpos($this->api_url_base, 'index.php');
        if ($pos) {
            $this->api_url_base = substr($this->api_url_base, 0, $pos);
        }
        $this->api_url_base = rtrim($this->api_url_base, '/');
        $this->api_url = $this->api_url_base . '/index.php';
        $this->api_method = trim($configuration_array['api_method']['cmc_value'] ?? 'bearer');
        $this->api_key = trim($configuration_array['api_key']['cmc_value'] ?? '');
    }
    private function check_vars()
    {
        Assert::assert(is_dir($this->working_dir), 'Temp dir does not exists');
        Assert::assert_not_empty($this->api_url, 'Url is empty');
        Assert::assert(in_array(substr($this->api_url, 0, 7), ['http://', 'https:/']), 'Url is invalid');
        Assert::assert_not_empty($this->api_key, 'Secure key is empty');
    }
    public function test_connection()
    {
        $this->check_version();
    }
    private function get_stream_context()
    {
        $stream_context_params = ['http' => ['timeout' => 1200]];
        switch ($this->api_method) {
            case 'get':
                $stream_context_params['http']['method'] = 'GET';
                $stream_context_params['http']['header'] = 'Cache-Control: no-store';
                break;
            case 'post':
                $stream_context_params['http']['method'] = 'POST';
                $stream_context_params['http']['header'] = 'Content-Type: application/x-www-form-urlencoded';
                $stream_context_params['http']['content'] = 'key=' . $this->api_key;
                // . '&feed=' . urlencode($feed);
                break;
            case 'bearer':
                $stream_context_params['http']['header'] = 'Authorization: Bearer ' . $this->api_key;
                break;
            default:
                throw new \Exception('Secure method is invalid: ' . $this->api_method);
        }
        //        if (YII_ENV=='dev') { // disable checking self-signed cert
        $stream_context_params['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true];
        //        }
        return stream_context_create($stream_context_params);
    }
    private function internal_download($params)
    {
        $this->check_vars();
        Assert::assert(is_array($params));
        $url = $this->api_url . '?';
        foreach ($params as $name => $value) {
            $url .= $name . '=' . urlencode($value) . '&';
        }
        if ($this->api_method == 'get') {
            $url .= 'key=' . $this->api_key;
        }
        $feed = $params['feed'] ?? null;
        if (!empty($feed) && empty($params['r'])) {
            $suffix = isset($params['offset']) ? '_offset' . $params['offset'] : '';
            $filename = $this->working_dir . urlencode($feed) . $suffix . '.xml';
            // download XML feed to working folder
            Assert::assert(copy($url, $filename, $this->get_stream_context()), error_get_last()['message'] ?? '');
            return $filename;
        } else {
            $result = @file_get_contents($url, false, $this->get_stream_context());
            Assert::assert(false !== $result, error_get_last()['message'] ?? '');
            return json_decode($result, true);
        }
    }
    public function get_count($feed, &$error_msg)
    {
        Assert::assert_not_empty($feed, 'Feed param is empty');
        $result = $this->internal_download(['r' => 'site/count', 'feed' => $feed]);
        $count = $result['count'] ?? -1;
        $error_msg = $count >= 0 ? '' : $result['error'] ?? 'Unknown error';
        return $result['count'];
    }
    public function get_status()
    {
        return $this->internal_download(['r' => 'site/status']);
    }
    public function check_version()
    {
        $required_ver = '1.56';
        // oscb/compat/configure.php
        $required_msg = sprintf(Php8::get_const('EXTENSION_OSCLINK_TEXT_ERROR_OLD_VERSION'), $required_ver);
        // check access and auth
        $this->internal_download([]);
        try {
            $status = $this->get_status();
        } catch (\Exception $e) {
            if (false !== strpos($e->get_message(), '404 Not Found')) {
                throw new \Exception($required_msg);
            } else {
                throw $e;
            }
        }
        Assert::assert(isset($status['version']), Php8::get_const('EXTENSION_OSCLINK_TEXT_ERROR_NOT_FOUND'));
        $required_msg .= ' (' . sprintf(Php8::get_const('EXTENSION_OSCLINK_TEXT_ERROR_OLD_VER_FOUND'), $status['version']) . ')';
        Assert::assert(version_compare($status['version'], $required_ver) >= 0, $required_msg);
    }
    public function get_feed($feed, $offset = null, $limit = null)
    {
        Assert::assert_not_empty($feed, 'Feed param is empty');
        $params = ['feed' => $feed];
        if (!empty($offset)) {
            $params['offset'] = $offset;
        }
        if (!empty($limit)) {
            $params['limit'] = $limit;
        }
        return $this->internal_download($params);
    }
}