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

class Php8
{
    public static function get_const(string $name, string $default = null)
    {
        return defined($name) ? constant($name) : (is_null($default) ? $name : $default);
    }
    public static function null_obj_props(&$obj, array $prop_names)
    {
        if (!is_object($obj)) {
            $obj = new \stdClass();
        }
        foreach ($prop_names as $name) {
            $obj->{$name} = $obj->{$name} ?? null;
        }
    }
    public static function null_arr_props(&$arr, array $prop_names)
    {
        foreach ($prop_names as $name) {
            $arr[$name] = $arr[$name] ?? null;
        }
    }
    public static function null_props(&$arr_obj, array $prop_names)
    {
        if (is_null($arr_obj) || is_array($arr_obj)) {
            self::null_arr_props($arr_obj, $prop_names);
        } elseif (is_object($arr_obj)) {
            self::null_obj_props($arr_obj, $prop_names);
        } else {
            throw new \Exception('Wrong type of the first param: ' . gettype($arr_obj));
        }
    }
    /**
     * Same as Yii\helpers\ArrayHelper but:
     *   - allows multiple string subkeys
     *   - allows -> as key separator
     *   - returns an default if object property does not exists (ArrayHelper throws an exception)
     * @param null|array|object     $array
     * @param string|array          $key
     * @param type                  $default
     * @return type
     */
    public static function get_value($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }
        if (is_array($key)) {
            $last_key = array_pop($key);
            foreach ($key as $key_part) {
                $array = static::get_value($array, $key_part);
            }
            $key = $last_key;
        }
        if (\yii\helpers\Array_Helper::key_exists($key, $array)) {
            return $array[$key];
        }
        $pos1 = strrpos($key, '.');
        $pos2 = strrpos($key, '->');
        if ($pos1 !== false || $pos2 !== false) {
            $pos = min($pos1 ?: PHP_INT_MAX, $pos2 ?: PHP_INT_MAX);
            $array = static::get_value($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + ($pos === $pos1 ? 1 : 2));
            return static::get_value($array, $key, $default);
        }
        if (is_object($array)) {
            // this is expected to fail if the property does not exist, or __get() is not implemented
            // it is not reliably possible to check whether a property is accessible beforehand
            try {
                return $array->{$key};
            } catch (\Exception $e) {
                if ($array instanceof ArrayAccess) {
                    return $default;
                } elseif (preg_match('/^Undefined property/', $e->get_message())) {
                    return $default;
                }
                throw $e;
            }
        }
        return $default;
    }
    /**
     * Get value of complex expression without warnings
     * @param  Closure     $expr
     * @param  mixed       $default
     * @return mixed
     *
     * samples:
     * PHP < 7.4
     *    Php8::getByClosure( function() use($assigned_obj, $another_arr) { return $assigned_obj->assigned_arr[$another_arr['id']]; }, 1);
     * PHP >= 7.4
     *    Php8::getByClosure( fn() => $assigned_obj->assigned_arr[$another_arr['id']], $default);
     */
    public static function get_by_closure($expr, $default = null)
    {
        if (!$expr instanceof \Closure) {
            throw new InvalidArgumentException('Param must be Closure.');
        }
        try {
            return $expr->call(new self());
        } catch (\Exception $e) {
            if (preg_match('/^(Undefined property|Undefined array key)/', $e->get_message())) {
                return $default;
            }
            throw $e;
        }
    }
    public static function str_start_with($haystack, $needle)
    {
        return Php::str_start_with($haystack, $needle);
    }
    public static function array_key_first($arr)
    {
        return Php::array_key_first($arr);
    }
    public static function preg_last_error_msg()
    {
        return preg_last_error() . function_exists('preg_last_error_msg') ? ': ' . preg_last_error_msg() : '';
    }
}