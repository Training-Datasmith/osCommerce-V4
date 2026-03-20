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

class Helper
{
    public static function get_ident_ar($ar)
    {
        if ($ar instanceof \yii\db\Active_Record) {
            $key = $ar->get_primary_key();
            $key = is_array($key) ? implode('-', array_filter($key, 'is_string')) : $key;
            return $ar->tablename() . '(' . $key . ')';
        } elseif (is_object($ar)) {
            return $ar::classname();
        } else {
            return (string) $ar;
        }
    }
    public static function sum_cols(&$sum, $add)
    {
        if (empty($sum)) {
            $sum = $add;
        } else {
            foreach ($add as $key => $val) {
                $sum[$key] += $val;
            }
        }
    }
    public static function format_arr(string $format, array $arr)
    {
        $temp_arr = [];
        array_walk($arr, function (&$value, $key) use (&$temp_arr) {
            $temp_arr['{' . $key . '}'] = $value;
        });
        return strtr($format, $temp_arr);
    }
    public static function get_feed_name($feed)
    {
        return \common\helpers\Php8::get_const('EXTENSION_OSCLINK_TEXT_ENTITY_' . strtoupper($feed));
    }
    public static function get_group_name($group)
    {
        return \common\helpers\Php8::get_const('EXTENSION_OSCLINK_TEXT_GROUP_' . strtoupper($group));
    }
    public static function get_feed_group_info($feed)
    {
        foreach (\common\extensions\Osc_Link\Osc_Link::FEED_GROUPS as $group => $feeds) {
            if (($index = array_search($feed, $feeds)) !== false) {
                return ['group' => $group, 'index' => $index, 'count' => count($feeds)];
            }
        }
        throw new \Exception("Feed {$feed} is not included in group");
    }
    public static function progress_and_log($msg)
    {
        \Osc_Link\Progress::Log($msg);
        \Osc_Link\Logger::print($msg);
    }
}