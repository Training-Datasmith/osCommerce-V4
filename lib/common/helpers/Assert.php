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

class Assert
{
    public static function error(string $message)
    {
        throw new \Exception($message);
    }
    public static function assert($condition, string $message = null)
    {
        if (!$condition) {
            static::error($message ?? 'Assertion failed');
        }
    }
    protected static function ident($obj)
    {
        $type = gettype($obj);
        if (is_object($obj)) {
            $value = get_class($obj);
        } elseif (is_array($obj)) {
            $value = var_export($obj, true);
        } else {
            $value = (string) $obj;
        }
        return sprintf('%s(%s)', $type, $value);
    }
    protected static function error_msg($message, $message_def)
    {
        $message = $message ?? 'Assertion: %s';
        static::error(sprintf($message, $message_def));
    }
    public static function is_set(bool $is_set, $var_name, string $message = null)
    {
        if (!$is_set) {
            static::error_msg($message, 'variable "$varName" is not set');
        }
    }
    /**
     * @deprecated Use isNotNull
     */
    public static function assert_not_null($value, string $message = null)
    {
        self::is_not_null($value, $message);
    }
    public static function is_not_null($value, string $message = null)
    {
        if (is_null($value)) {
            static::error_msg($message, 'unexpected value: null');
        }
    }
    /**
     * @deprecated Use isNotEmpty
     */
    public static function assert_not_empty($value, string $message = null)
    {
        self::is_not_empty($value, $message);
    }
    public static function is_not_empty($value, string $message = null)
    {
        if (empty($value)) {
            static::error_msg($message, 'unexpected value: empty');
        }
    }
    public static function is_empty($value, string $message = null)
    {
        if (!empty($value)) {
            static::error_msg($message, 'expect empty value, but given: ' . self::ident($value));
        }
    }
    public static function has_method($obj, $method, string $message = null)
    {
        if (!method_exists($obj, $method)) {
            $def = sprintf('%s has not method %s', self::ident($obj), self::ident($method));
            static::error_msg($message, $def);
        }
    }
    public static function string_matched($str, string $match, string $message = null)
    {
        if (!preg_match($match, $str)) {
            $def = sprintf('%s does not match %s', self::ident($str), self::ident($match));
            static::error_msg($message, $def);
        }
    }
    public static function file_exists($fn, string $message = null)
    {
        if (!file_exists($fn)) {
            $def = sprintf('file %s does not exist', self::ident($fn));
            static::error_msg($message, $def);
        }
    }
    public static function is_object($obj, string $message = null)
    {
        if (!is_object($obj)) {
            $def = sprintf('%s is not object', self::ident($obj));
            static::error_msg($message, $def);
        }
    }
    public static function instance_of($obj, $class, string $message = null)
    {
        if (!$obj instanceof $class) {
            $def = sprintf('%s is not instance of %s', self::ident($obj), self::ident($class));
            static::error_msg($message, $def);
        }
    }
    public static function class_exists($class_name, string $message = null)
    {
        if (!class_exists($class_name)) {
            $def = sprintf('Class %s does not exist', self::ident($class_name));
            static::error_msg($message, $def);
        }
    }
    public static function class_implements($class, $interface, string $message = null)
    {
        if (!\common\helpers\Php::is_class_implements_interface($class, $interface)) {
            $def = sprintf('%s does not implements %s', self::ident($class), self::ident($interface));
            static::error_msg($message, $def);
        }
    }
    public static function is_array($arr, string $message = null)
    {
        if (!is_array($arr)) {
            $def = sprintf('%s is not array', self::ident($arr));
            static::error_msg($message, $def);
        }
    }
    public static function key_exists($arr, $key, string $message = null)
    {
        static::is_array($arr, $message);
        if (!(isset($arr[$key]) || \array_key_exists($key, $arr))) {
            $def = sprintf('Key %s is not exist in %s', self::ident($key), self::ident($arr));
            static::error_msg($message, $def);
        }
    }
    public static function keys_exists($arr, $keys, string $message = null)
    {
        static::is_array($arr, $message);
        static::is_array($keys, $message);
        foreach ($keys as $key) {
            static::key_exists($arr, $key);
        }
    }
    public static function match($pattern, $value, string $message = null)
    {
        if (!preg_match($pattern, $value)) {
            $def = sprintf('%s is not match template', self::ident($value));
            static::error_msg($message, $def);
        }
    }
    public static function not_implemented(string $message = null)
    {
        static::error_msg($message, 'Not implemented yet');
    }
    public static function is_extension_allowed(string $ext_code, string $message = null)
    {
        if (!\common\helpers\Extensions::is_allowed($ext_code)) {
            $def = sprintf('Extension %s is not allowed', self::ident($ext_code));
            static::error_msg($message, $def);
        }
    }
}