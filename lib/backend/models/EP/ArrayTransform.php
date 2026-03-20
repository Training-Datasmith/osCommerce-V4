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
namespace backend\models\EP;

class Array_Transform
{
    public static function convert_multi_dimensional_to_flat($array, $separator = '.')
    {
        //return self::__multi_to_flat_set('', $array, $separator);
        $out = [];
        self::__multi_to_flat_set_ref($out, '', $array, $separator);
        return $out;
    }
    public static function convert_flat_to_multi_dimensional($array, $separator = '.')
    {
        $multi = [];
        foreach ($array as $multi_key => $value) {
            $keys = explode($separator, $multi_key);
            $key = array_shift($keys);
            if (count($keys) == 0) {
                $multi[$key] = $value;
            } else {
                if (!isset($multi[$key]) || !is_array($multi[$key])) {
                    $multi[$key] = [];
                }
                self::__flat_to_multi_set($multi[$key], $keys, $value);
            }
        }
        return $multi;
    }
    /**
     *
     * ArrayTransform::transformMulti([
     *  'key1' => 'group.k1',
     *  'key2' => 'group.k2',
     *  'key3' => 'any'
     * ],$array1)
     *
     * @param $mapping
     * @param $array
     * @return array
     */
    public static function transform_multi($mapping, $array)
    {
        $tmp_flat = self::convert_multi_dimensional_to_flat($array);
        $new_flat = [];
        if (is_callable($mapping)) {
            foreach (array_keys($tmp_flat) as $from_path) {
                $to_path = $mapping($from_path);
                if ($to_path !== false) {
                    $new_flat[$to_path] = $tmp_flat[$from_path];
                }
            }
        } else {
            foreach ($mapping as $from_path => $to_path) {
                if (isset($tmp_flat[$from_path])) {
                    $new_flat[$to_path] = $tmp_flat[$from_path];
                }
            }
        }
        return self::convert_flat_to_multi_dimensional($new_flat);
    }
    private static function __multi_to_flat_set_ref(&$flat, $current_key, $array, $separator)
    {
        $key_prepend = '';
        if (!empty($current_key)) {
            $key_prepend = $current_key . $separator;
        }
        foreach ($array as $key => $val) {
            $item_key = $key_prepend . $key;
            if (is_array($val)) {
                self::__multi_to_flat_set_ref($flat, $item_key, $val, $separator);
            } else {
                $flat[$item_key] = $val;
            }
        }
    }
    private static function __multi_to_flat_set($current_key, $array, $separator)
    {
        $flat = [];
        $key_prepend = '';
        if (!empty($current_key)) {
            $key_prepend = $current_key . $separator;
        }
        foreach ($array as $key => $val) {
            $item_key = $key_prepend . $key;
            if (is_array($val)) {
                $flat = array_merge($flat, self::__multi_to_flat_set($item_key, $val, $separator));
            } else {
                $flat[$item_key] = $val;
            }
        }
        return $flat;
    }
    private static function __flat_to_multi_set(&$array, $keys, $value)
    {
        $key = array_shift($keys);
        if (count($keys) == 0) {
            $array[$key] = $value;
        } else {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            self::__flat_to_multi_set($array[$key], $keys, $value);
        }
    }
}