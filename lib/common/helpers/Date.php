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

class Date
{
    public const DATE_FORMAT = 'j M Y';
    public const DATE_TIME_FORMAT = 'j M Y, g:i a';
    public const CALENDAR_DATE_FORMAT = 'j M Y';
    // d-m-Y j M Y
    public const DATABASE_DATE_FORMAT = 'Y-m-d';
    public const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const JS_DATE_FORMAT = 'D MMM YYYY';
    // DD-MM-YYYY D MMM YYYY
    public const JS_DATE_TIME_FORMAT = 'd M Y h:i a';
    public static function check_input_date($date, $w_time = false)
    {
        $patterns = [];
        $replacements = [];
        for ($m = 1; $m <= 12; $m++) {
            $patterns[] = '/' . strftime('%B', mktime(0, 0, 0, $m)) . '/ui';
            $replacements[] = date('F', mktime(0, 0, 0, $m));
            $patterns[] = '/' . strftime('%b', mktime(0, 0, 0, $m)) . '/ui';
            $replacements[] = date('M', mktime(0, 0, 0, $m));
        }
        if ($w_time) {
            $time = strftime('%P', strtotime($date));
            if (strtolower($time) == 'pm') {
                $patterns[] = '/' . strftime('%l', strtotime($date)) . ':/ui';
                $replacements[] = ' ' . (date('g', strtotime($date)) + 12) . ':';
            }
            $patterns[] = '/\s' . $time . '/ui';
            $replacements[] = ':00';
        }
        $date = preg_replace($patterns, $replacements, $date);
        return $date;
    }
    public static function prepare_input_date($date, $w_time = false)
    {
        $date = self::check_input_date($date);
        if (defined('DATE_FORMAT_DATEPICKER_PHP')) {
            if ($w_time) {
                if (preg_match('/M$/i', $date)) {
                    $date_format = date_create_from_format(DATE_FORMAT_DATEPICKER_PHP . ' g:i A', $date);
                } else {
                    $date_format = date_create_from_format(DATE_FORMAT_DATEPICKER_PHP . ' H:i:s', $date);
                }
                return $date_format ? $date_format->format(self::DATABASE_DATETIME_FORMAT) : '';
            } else {
                $date_format = date_create_from_format(DATE_FORMAT_DATEPICKER_PHP, $date);
                return $date_format ? $date_format->format(self::DATABASE_DATE_FORMAT) : '';
            }
        }
        if ($w_time) {
            return date(self::DATABASE_DATETIME_FORMAT, strtotime($date));
        } else {
            return date(self::DATABASE_DATE_FORMAT, strtotime($date));
        }
    }
    public static function format_date($date)
    {
        if ($date == '0000-00-00' || empty($date)) {
            return '';
        }
        return date(self::DATE_FORMAT, strtotime($date));
    }
    public static function format_date_time($date)
    {
        if ($date == '0000-00-00 00:00:00' || empty($date)) {
            return '';
        }
        return date(self::DATE_TIME_FORMAT, strtotime($date));
    }
    public static function format_date_time_js($date)
    {
        if ($date == '0000-00-00 00:00:00' || empty($date)) {
            return '';
        }
        return date(self::JS_DATE_TIME_FORMAT, strtotime($date));
    }
    public static function format_calendar_date($date)
    {
        if ($date == '0000-00-00' || $date == '0000-00-00 00:00:00' || empty($date)) {
            return '';
        }
        return date(self::CALENDAR_DATE_FORMAT, strtotime($date));
    }
    public static function unformat_calendar_date($date)
    {
        if ($date == '0000-00-00' || empty($date)) {
            return '';
        }
        return date(self::DATABASE_DATE_FORMAT, strtotime($date));
    }
    public static function get_date_range($start_date, $end_date)
    {
        if ($start_date == '0000-00-00' || empty($start_date)) {
            return '';
        }
        if ($end_date == '0000-00-00' || empty($end_date)) {
            return '';
        }
        $start_date = date('Y-m-d', strtotime($start_date));
        $end_date = date('Y-m-d', strtotime($end_date));
        $datetime1 = new \DateTime($start_date);
        $datetime2 = new \DateTime($end_date);
        $difference = $datetime1->diff($datetime2);
        $response = '';
        if ($difference->y == 1) {
            $response .= $difference->y . ' year ';
        } elseif ($difference->y > 1) {
            $response .= $difference->y . ' years ';
        }
        if ($difference->m == 1) {
            $response .= $difference->m . ' ' . TEXT_MONTH_COMMON . ' ';
        } elseif ($difference->m > 1) {
            $response .= $difference->m . ' ' . TEXT_MONTHS_COMMON . ' ';
        }
        if ($difference->d == 1) {
            $response .= '1 ' . TEXT_DAY_COMMON;
        } elseif ($difference->d == 7) {
            $response .= '1 ' . TEXT_WEEK_COMMON;
        } elseif ($difference->d == 14) {
            $response .= '2 ' . TEXT_WEEKS_COMMON;
        } elseif ($difference->d == 21) {
            $response .= '3 ' . TEXT_WEEKS_COMMON;
        } elseif ($difference->d == 28) {
            $response .= '4 ' . TEXT_WEEKS_COMMON;
        } elseif ($difference->d > 1) {
            $response .= $difference->d . ' ' . TEXT_DAYS_COMMON;
        }
        if ($difference->invert == 1) {
            $response .= ' ' . TEXT_AGO_COMMON;
        }
        if (empty($response)) {
            $response = TEXT_TODAY_COMMON;
        }
        return $response;
    }
    public static function date_long($raw_date, $format = DATE_FORMAT_LONG)
    {
        if ($raw_date == '0000-00-00 00:00:00' || $raw_date == '') {
            return false;
        }
        $year = (int) substr($raw_date, 0, 4);
        $month = (int) substr($raw_date, 5, 2);
        $day = (int) substr($raw_date, 8, 2);
        $hour = (int) substr($raw_date, 11, 2);
        $minute = (int) substr($raw_date, 14, 2);
        $second = (int) substr($raw_date, 17, 2);
        return strftime($format, mktime($hour, $minute, $second, $month, $day, $year));
    }
    public static function date_short($raw_date, $format = null)
    {
        if (is_null($format)) {
            $format = defined('DATE_FORMAT_SHORT') ? DATE_FORMAT_SHORT : '%d %b %Y';
        }
        if ($raw_date == '0000-00-00 00:00:00' || $raw_date == '0000-00-00' || $raw_date == '') {
            return false;
        }
        $year = substr($raw_date, 0, 4);
        $month = (int) substr($raw_date, 5, 2);
        $day = (int) substr($raw_date, 8, 2);
        $hour = (int) substr($raw_date, 11, 2);
        $minute = (int) substr($raw_date, 14, 2);
        $second = (int) substr($raw_date, 17, 2);
        return strftime($format, mktime($hour, $minute, $second, $month, $day, $year));
    }
    public static function datetime_short($raw_datetime)
    {
        if ($raw_datetime == '0000-00-00 00:00:00' || $raw_datetime == '') {
            return false;
        }
        $year = (int) substr($raw_datetime, 0, 4);
        $month = (int) substr($raw_datetime, 5, 2);
        $day = (int) substr($raw_datetime, 8, 2);
        $hour = (int) substr($raw_datetime, 11, 2);
        $minute = (int) substr($raw_datetime, 14, 2);
        $second = (int) substr($raw_datetime, 17, 2);
        return strftime(defined('DATE_TIME_FORMAT') ? DATE_TIME_FORMAT : '%d %b %Y %H:%M:%S', mktime($hour, $minute, $second, $month, $day, $year));
    }
    public static function date_raw($date, $reverse = false)
    {
        if ($reverse) {
            return substr($date, 0, 2) . substr($date, 3, 2) . substr($date, 6, 4);
        } else {
            return date('Y-m-d H:i:s', strtotime($date));
            //return substr($date, 6, 4) . substr($date, 3, 2) . substr($date, 0, 2);
        }
    }
    public static function date_format($raw_date, $format)
    {
        if ($raw_date == '0000-00-00 00:00:00' || $raw_date == '') {
            return false;
        }
        $year = (int) substr($raw_date, 0, 4);
        $month = (int) substr($raw_date, 5, 2);
        $day = (int) substr($raw_date, 8, 2);
        $hour = (int) substr($raw_date, 11, 2);
        $minute = (int) substr($raw_date, 14, 2);
        $second = (int) substr($raw_date, 17, 2);
        return strftime($format, mktime($hour, $minute, $second, $month, $day, $year));
    }
    public static function datepicker_date($date)
    {
        if ($date == '0000-00-00 00:00:00' || $date == '0000-00-00' || $date == '') {
            return false;
        }
        return date(DATE_FORMAT_DATEPICKER_PHP, strtotime($date));
    }
    public static function datepicker_date_time($date)
    {
        if ($date == '0000-00-00 00:00:00' || $date == '0000-00-00' || $date == '') {
            return false;
        }
        if (defined('DATE_FORMAT_DATEPTIMEICKER_PHP')) {
            $format = constant('DATE_FORMAT_DATEPTIMEICKER_PHP');
        } else {
            $format = constant('DATE_FORMAT_DATEPICKER_PHP') . ' H:i';
        }
        return date($format, strtotime($date));
    }
    public static function is_unix_date($date)
    {
        return preg_match("/[\\d]{10}/", $date);
    }
    public static function get_default_server_time_zone()
    {
        return defined('TIMEZONE_SERVER') ? TIMEZONE_SERVER : 'Europe/London';
    }
    public static function set_server_time_zone($new_zone = '')
    {
        if (!empty($new_zone)) {
            date_default_timezone_set($new_zone);
        }
        tep_db_query("SET SESSION time_zone = '" . date('P') . "'");
    }
    public static function get_holidays($platform_id, $format = '', $year = '')
    {
        $dates = [];
        $search_year = '';
        if (!is_array($platform_id)) {
            $platform_id = [$platform_id];
        }
        if (is_string($year)) {
            if (!empty($year) && !checkdate(1, 1, $year)) {
                return $dates;
            }
            $search_year = " and year(holidate) = '" . $year . "'";
        }
        if (is_array($year)) {
            foreach ($year as $k => $_year) {
                if (empty($_year) || !empty($_year) && !checkdate(1, 1, $_year)) {
                    unset($year[$k]);
                }
            }
            $search_year = ' and year(holidate) in (' . implode(',', $year) . ')';
        }
        $query = tep_db_query('select * from platforms_holidays where platform_id in (' . implode(',', $platform_id) . ') ' . $search_year . ' order by holidate asc');
        if (tep_db_num_rows($query)) {
            while ($row = tep_db_fetch_array($query)) {
                if (!empty($format)) {
                    $dates[] = date($format, strtotime($row['holidate']));
                } else {
                    $dates[] = $row['holidate'];
                }
            }
        }
        return $dates;
    }
    /**
     * get time interval between two dates
     * @param type $to
     * @param type $from
     * @return DateInterval|false
     */
    public static function get_left_interval_to($to, $from = null)
    {
        if ($last_time = strtotime($to)) {
            $end = new \DateTime();
            $end->set_timestamp($last_time);
            $start = new \DateTime();
            if (!is_null($from) && strtotime($from)) {
                $start->set_timestamp(strtotime($from));
            } else {
                $start->set_timestamp(date('U'));
            }
            return $start->diff($end, false);
        }
        return false;
    }
    public static function translate_date($raw_date, $language_id)
    {
        if ($language_id == 1) {
            return $raw_date;
        }
        $keys = ['DATEPICKER_DAY_FR', 'DATEPICKER_DAY_FRI', 'DATEPICKER_DAY_FRIDAY', 'DATEPICKER_DAY_MO', 'DATEPICKER_DAY_MON', 'DATEPICKER_DAY_MONDAY', 'DATEPICKER_DAY_SA', 'DATEPICKER_DAY_SAT', 'DATEPICKER_DAY_SATURDAY', 'DATEPICKER_DAY_SU', 'DATEPICKER_DAY_SUN', 'DATEPICKER_DAY_SUNDAY', 'DATEPICKER_DAY_TH', 'DATEPICKER_DAY_THU', 'DATEPICKER_DAY_THURSDAY', 'DATEPICKER_DAY_TU', 'DATEPICKER_DAY_TUE', 'DATEPICKER_DAY_TUESDAY', 'DATEPICKER_DAY_WE', 'DATEPICKER_DAY_WED', 'DATEPICKER_DAY_WEDNESAY', 'DATEPICKER_MONTH_APR', 'DATEPICKER_MONTH_APRIL', 'DATEPICKER_MONTH_AUG', 'DATEPICKER_MONTH_AUGUST', 'DATEPICKER_MONTH_DEC', 'DATEPICKER_MONTH_DECEMBER', 'DATEPICKER_MONTH_FEB', 'DATEPICKER_MONTH_FEBRUARY', 'DATEPICKER_MONTH_JAN', 'DATEPICKER_MONTH_JANUARY', 'DATEPICKER_MONTH_JUL', 'DATEPICKER_MONTH_JULY', 'DATEPICKER_MONTH_JUN', 'DATEPICKER_MONTH_JUNE', 'DATEPICKER_MONTH_MAR', 'DATEPICKER_MONTH_MARCH', 'DATEPICKER_MONTH_MAY', 'DATEPICKER_MONTH_NOV', 'DATEPICKER_MONTH_NOVEMBER', 'DATEPICKER_MONTH_OCT', 'DATEPICKER_MONTH_OCTOBER', 'DATEPICKER_MONTH_SEP', 'DATEPICKER_MONTH_SEPTEMBER'];
        $translations = \common\models\Translation::find()->where(['IN', 'translation_key', $keys])->and_where(['IN', 'language_id', [1, (int) $language_id]])->and_where(['translation_entity' => 'admin/js'])->as_array()->all();
        $patterns = [];
        $replacements = [];
        foreach ($translations as $translation) {
            if ($translation['language_id'] == 1) {
                $patterns[$translation['translation_key']] = '/([^a-z]|^)' . $translation['translation_value'] . '([^a-z]|$)/i';
            } else {
                $replacements[$translation['translation_key']] = '$1' . $translation['translation_value'] . '$2';
            }
        }
        return preg_replace($patterns, $replacements, $raw_date);
    }
    public static function time_humanize($date = '')
    {
        $return = '';
        $date = strtotime(trim($date));
        if ($date > 0) {
            $date = time() - $date;
            if ($date > 0) {
                $day = floor($date / (24 * 60 * 60));
                $date = floor($date - $day * (24 * 60 * 60));
                $hour = floor($date / (60 * 60));
                $date = floor($date - $hour * (60 * 60));
                $minute = floor($date / 60);
                $date = floor($date - $minute * 60);
                $return = trim(($day > 0 ? $day . ' ' . TEXT_DAYS_COMMON . ' ' : '') . ($hour > 0 ? str_pad($hour, 2, 0, STR_PAD_LEFT) . ' ' . TEXT_HOURS_COMMON . ' ' : '') . str_pad($minute, 2, 0, STR_PAD_LEFT) . ' ' . TEXT_MINUTES_COMMON);
            }
        }
        return $return;
    }
}