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
namespace common\helpers;

class Curl
{
    public const HEADERS_JSON = ['Content-Type: application/json'];
    public static function run_safe($url, $method = 'GET', $data = null, $headers = null, $options = [])
    {
        try {
            $res = self::run($url, $method, $data, $headers, $options);
        } catch (\yii\base\User_Exception $e) {
            $res = ['success' => false, 'error' => $e->get_message()];
        } catch (\Throwable $e) {
            Php::log_error($e, 'Curl error', 'helpers/curl');
            $res = ['success' => false, 'error' => 'Internal error' . (System::is_production() ? '' : ': ' . $e->get_message())];
        }
        if (isset($options['dbg']) && class_exists($options['dbg'])) {
            $options['dbg']::log_var($res, 'Curl::runSafe result');
        }
        return $res;
    }
    /**
     * @param $url
     * @param $method - 'POST'/'GET' etc
     * @param $data - CURLOPT_POSTFIELDS value
     * @param $headers - CURLOPT_HTTPHEADER value
     * @param $options - others curl options and some specific values:
     *      $options['verify'] (bool) - verify peer
     *      $options['successHttpCodes'] array of success http codes
     *      $options['decodeResultJson'] (bool) decode result as json
     *      $options['decodeResultJsonRequired'] (array) check results required keys
     *      $options['dbg'] (class) dbg class
     * @return array
     * $res =  [
     *  'success' => bool,
     *  'error' => string,
     *  'data' => curl_exec,
     *  'extra' => curl_getinfo
     * ];
     */
    public static function run($url, $method = 'GET', $data = null, $headers = null, $options = [])
    {
        Assert::assert(function_exists('curl_version'), 'Curl extension is not installed');
        $handle = curl_init($url);
        Assert::assert($handle, 'Curl_init failed: ' . curl_error($handle));
        self::set_opt($handle, CURLOPT_CUSTOMREQUEST, $method);
        if (!is_null($data)) {
            self::set_opt($handle, CURLOPT_POSTFIELDS, $data);
        }
        if (!is_null($headers)) {
            self::set_opt($handle, CURLOPT_HTTPHEADER, $headers);
        }
        if (isset($options['verify'])) {
            $value = (bool) $options['verify'];
            self::set_opt($handle, CURLOPT_SSL_VERIFYPEER, $value);
            self::set_opt($handle, CURLOPT_SSL_VERIFYHOST, $value);
            if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                // Added in cURL 7.41.0
                self::set_opt($handle, CURLOPT_SSL_VERIFYPEER, $value);
            }
        }
        if (!empty($options)) {
            foreach ($options as $option => $value) {
                if (in_array($option, ['verify', 'dbg', 'dbg_prefix', 'successHttpCodes', 'decodeResultJson', 'decodeResultJsonErrorKey', 'decodeResultJsonRequiredKeys', 'decodeResultJsonReturnRaw'])) {
                    continue;
                }
                self::set_opt($handle, $option, $value);
            }
        }
        $result = curl_exec($handle);
        Assert::assert($result, 'curl_exec failed: ' . curl_error($handle));
        $info = curl_getinfo($handle);
        if (isset($options['dbg']) && class_exists($options['dbg'])) {
            if (isset($options['dbg_prefix'])) {
                $log_state = $options['dbg']::log_prefix($options['dbg_prefix']);
            }
            $options['dbg']::log_var($result, 'CURL Result');
            $options['dbg']::log_var($info, 'CURL Info');
            if (!empty($log_state)) {
                $options['dbg']::del_prefix($log_state);
            }
        }
        curl_close($handle);
        $res = ['success' => false, 'error' => 'Unknown error', 'data' => $result, 'extra' => $info];
        $success_http_codes = $options['successHttpCodes'] ?? [200];
        if (in_array($info['http_code'], $success_http_codes)) {
            if ($options['decodeResultJson'] ?? false) {
                $json = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                $error_key = $options['decodeResultJsonErrorKey'] ?? 'error';
                if (!empty($error_key) && isset($json[$error_key])) {
                    Assert_User::assert(false, $json[$error_key]);
                }
                if (is_array($options['decodeResultJsonRequiredKeys'] ?? null)) {
                    foreach ($options['decodeResultJsonRequiredKeys'] as $key) {
                        Assert::assert(isset($json[$key]), "{$key} not found in decoded result: " . print_r($json, true));
                    }
                }
                if ($options['decodeResultJsonReturnRaw'] ?? false) {
                    $res = $json;
                } else {
                    $res['success'] = true;
                    $res['json'] = $json;
                }
            } else {
                $res['success'] = true;
            }
        } else {
            $res['success'] = false;
            $res['error'] = 'HTTP code: ' . $info['http_code'];
        }
        return $res;
    }
    private static function set_opt($handle, $option, $value)
    {
        $res = curl_setopt($handle, $option, $value);
        Assert::assert($res, "curl_setopt setting option {$option} value " . print_r($value, true) . ' failed: ' . curl_error($handle));
    }
}