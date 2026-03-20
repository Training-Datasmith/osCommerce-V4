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

class Php
{
    public static function str_start_with($haystack, $needle)
    {
        return (string) $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
    public static function array_key_first($arr)
    {
        if (empty($arr) || !is_array($arr)) {
            return null;
        }
        if (!function_exists('array_key_first')) {
            // PHP < 7.3
            foreach ($arr as $key => $unused) {
                return $key;
            }
        } else {
            return array_key_first($arr);
        }
    }
    public static function array_get_sub_array_key_by_sub_value($array, $sub_array_key, $value)
    {
        if (is_array($array)) {
            foreach ($array as $key => $item) {
                if (is_array($item) && isset($item[$sub_array_key]) && $item[$sub_array_key] == $value) {
                    return $key;
                }
            }
        }
    }
    public static function array_get_sub_array_by_sub_value($array, $sub_array_key, $value, $default = null)
    {
        if (!is_null($key = self::array_get_sub_array_key_by_sub_value($array, $sub_array_key, $value))) {
            return $array[$key];
        }
    }
    /**
     * exec fucntion may be disabled on shared hostings
     * @param string $command
     * @param array|null $output
     * @param int|null $result_code
     * @return void
     */
    public static function exec(string $command, array &$output = null, int &$result_code = null)
    {
        if (function_exists('exec')) {
            return @exec($command, $output, $result_code);
        } else {
            $output = null;
            \Yii::warning('function exec is disabled on this hosting');
            return false;
        }
    }
    public static function sprintf_safe(string $msg)
    {
        return self::vsprintf_safe($msg, array_slice(func_get_args(), 1));
    }
    public static function vsprintf_safe(string $msg, array $args)
    {
        try {
            $res = vsprintf($msg, $args);
        } catch (\Throwable $e) {
            $res = sprintf("Error: %s for msg=%s, args=\n%s", $e->get_message(), $msg, \yii\helpers\Var_Dumper::export($args));
        }
        return $res;
    }
    public static function log_error($exception, $prefix = null, $entity = 'application')
    {
        $prefix = empty($prefix) ? '' : "{$prefix}: ";
        \Yii::warning($prefix . $exception->get_message() . "\n" . $exception->get_trace_as_string(), $entity);
    }
    public static function handle_error_prod($exception, $prefix = null, $entity = 'application')
    {
        if (\common\helpers\System::is_production()) {
            self::log_error($exception, $prefix, $entity);
        } else {
            throw $exception;
        }
    }
    public static function throw_or_log($err_message)
    {
        if (\common\helpers\System::is_production()) {
            \Yii::warning($err_message . "\n" . \common\helpers\Dbg::get_stack());
        } else {
            throw new \Exception($err_message);
        }
    }
    public static function str_insert_spaces_before_capital_char($str)
    {
        return ltrim(preg_replace('/[A-Z]/', ' $0', $str));
    }
    public static function is_implements_interface($class_or_object, $interface)
    {
        if (is_object($class_or_object)) {
            return $class_or_object instanceof $interface;
        } else {
            return self::is_class_implements_interface($class_or_object, $interface);
        }
    }
    public static function is_class_implements_interface($class_name, $interface)
    {
        if (!class_exists($class_name)) {
            return false;
        }
        if (!is_string($interface)) {
            return false;
        }
        $interfaces = class_implements($class_name);
        return isset($interfaces[$interface]);
    }
}