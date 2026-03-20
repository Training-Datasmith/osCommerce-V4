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
namespace common\classes;

use common\models\Orders;
use common\models\Platforms;
/**
 * CutOffTime object
 */
class Cut_Off_Time
{
    /**
     * Data [platform_id][day_of_week] = time
     */
    public $today = [];
    public $nextday = [];
    /**
     * Constructor
     */
    public function __construct()
    {
        $today = [];
        $nextday = [];
        $cut_off_times_query = tep_db_query('select * from ' . TABLE_PLATFORMS_CUT_OFF_TIMES . ' where 1');
        while ($record = tep_db_fetch_array($cut_off_times_query)) {
            if (!isset($today[$record['platform_id']])) {
                $today[$record['platform_id']] = [];
            }
            if (!isset($nextday[$record['platform_id']])) {
                $nextday[$record['platform_id']] = [];
            }
            $cut_off_times_days = explode(',', $record['cut_off_times_days']);
            if (is_array($cut_off_times_days)) {
                foreach ($cut_off_times_days as $day) {
                    if ($day == 0) {
                        //everyday
                        for ($i = 1; $i <= 7; $i++) {
                            if (!isset($today[$record['platform_id']][$i]) && !empty($record['cut_off_times_today'])) {
                                $today[$record['platform_id']][$i] = $record['cut_off_times_today'];
                            }
                            if (!isset($nextday[$record['platform_id']][$i]) && !empty($record['cut_off_times_next_day'])) {
                                $nextday[$record['platform_id']][$i] = $record['cut_off_times_next_day'];
                            }
                        }
                    } else {
                        if (!isset($today[$record['platform_id']][$day]) && !empty($record['cut_off_times_today'])) {
                            $today[$record['platform_id']][$day] = $record['cut_off_times_today'];
                        }
                        if (!isset($nextday[$record['platform_id']][$day]) && !empty($record['cut_off_times_next_day'])) {
                            $nextday[$record['platform_id']][$day] = $record['cut_off_times_next_day'];
                        }
                    }
                }
            }
        }
        $this->today = $today;
        $this->nextday = $nextday;
    }
    public function is_today_delivery($date = '', $platform_id = 0)
    {
        if (empty($date)) {
            $date = date('Y-m-d H:i:s');
        }
        if ($platform_id == 0 && defined('PLATFORM_ID')) {
            $platform_id = (int) PLATFORM_ID;
        }
        if ($platform_id == 0) {
            return false;
        }
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp);
        if (!isset($this->today[$platform_id][$day_of_week])) {
            return false;
        }
        $today_delivery_stamp = strtotime(date('Y-m-d', $timestamp) . ' ' . $this->today[$platform_id][$day_of_week]);
        if ($timestamp <= $today_delivery_stamp) {
            return true;
        }
        return false;
    }
    public function is_next_day_delivery($date = '', $platform_id = 0)
    {
        if (empty($date)) {
            $date = date('Y-m-d H:i:s');
        }
        if ($platform_id == 0 && defined('PLATFORM_ID')) {
            $platform_id = (int) PLATFORM_ID;
        }
        if ($platform_id == 0) {
            return false;
        }
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp);
        if (!isset($this->nextday[$platform_id][$day_of_week])) {
            return false;
        }
        $nextday_delivery_stamp = strtotime(date('Y-m-d', $timestamp) . ' ' . $this->nextday[$platform_id][$day_of_week]);
        if ($timestamp <= $nextday_delivery_stamp) {
            return true;
        }
        return false;
    }
    public function get_today_delivery_date($platform_id = 0)
    {
        $date = date('Y-m-d H:i:s');
        if ($platform_id == 0) {
            $platform_id = (int) PLATFORM_ID;
        }
        if ($platform_id == 0) {
            return '';
        }
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp);
        if (!isset($this->today[$platform_id][$day_of_week])) {
            return '';
        }
        $today_delivery_stamp = strtotime(date('Y-m-d', $timestamp) . ' ' . $this->today[$platform_id][$day_of_week]);
        return date('Y-m-d H:i:s', $today_delivery_stamp);
    }
    public function get_next_day_delivery_date($platform_id = 0)
    {
        $date = date('Y-m-d H:i:s');
        if ($platform_id == 0) {
            $platform_id = (int) PLATFORM_ID;
        }
        if ($platform_id == 0) {
            return '';
        }
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp);
        if (!isset($this->nextday[$platform_id][$day_of_week])) {
            return '';
        }
        $nextday_delivery_stamp = strtotime(date('Y-m-d', $timestamp) . ' ' . $this->nextday[$platform_id][$day_of_week]);
        return date('Y-m-d H:i:s', $nextday_delivery_stamp);
    }
    /**
     * find date of delivery
     * @param Platforms|NULL $oPlatform
     * @param Orders|NULL $oOrder
     * @param string $format
     * @param bool $in_past
     *              true = returned date can be in past
     *              false = returned date cant be in past
     * @return string
     */
    public function get_delivery_date(Platforms $o_platform = null, Orders $o_order = null, $format = null, $in_past = true)
    {
        $return_date = null;
        $a_all_days_num = [];
        // checked date
        $checked_date = new \DateTime($o_order->date_purchased ?? null);
        $day_number = $checked_date->format('N');
        $week_number = $checked_date->format('W');
        $year = $checked_date->format('Y');
        $checked_hour = $checked_date->format('H');
        $checked_minutes = $checked_date->format('i');
        $a_platforms_cut_off_times = $o_platform->platforms_cut_off_times;
        if ($a_platforms_cut_off_times) {
            // get all conditions
            foreach ($a_platforms_cut_off_times as $o_platforms_cut_off_time) {
                // get days of condition
                $a_days_num = explode(',', $o_platforms_cut_off_time->cut_off_times_days);
                $a_all_days_num = array_merge($a_all_days_num, $a_days_num);
                // get time of condition
                $today_h = \DateTime::create_from_format('g:i A', $o_platforms_cut_off_time->cut_off_times_today);
                $next_day_h = \DateTime::create_from_format('g:i A', $o_platforms_cut_off_time->cut_off_times_next_day);
                // if checked day satisfies the condition
                if (($current_day_number = array_search($day_number, $a_days_num)) !== false) {
                    // check today delivery
                    if ($today_h) {
                        $pattern_today_date = new \DateTime();
                        $pattern_today_date->set_iso_date($year, $week_number, $day_number);
                        $pattern_today_date->set_time($today_h->format('H'), $today_h->format('i'));
                        if ($checked_hour <= $pattern_today_date->format('H') && $checked_minutes < $pattern_today_date->format('i')) {
                            $return_date = $pattern_today_date;
                        }
                    }
                    // check tomorrow delivery
                    if ($next_day_h) {
                        $pattern_next_date = new \DateTime();
                        $pattern_next_date->set_iso_date($year, $week_number, $day_number);
                        $pattern_next_date->set_time($next_day_h->format('H'), $next_day_h->format('i'));
                        if ($checked_hour <= $pattern_next_date->format('H') && $checked_minutes < $pattern_next_date->format('i')) {
                            $pattern_next_date->modify('+1 day');
                            $return_date = $pattern_next_date;
                        } else {
                            $pattern_next_date->modify('+2 day');
                            $return_date = $pattern_next_date;
                        }
                    }
                }
            }
        }
        if (count($a_all_days_num) == 0) {
            $a_all_days_num = [1, 2, 3, 4, 5, 6];
        }
        if (is_null($return_date)) {
            $pattern_next_date = new \DateTime();
            //$pattern_next_date->setISODate($year, $week_number, $day_number);
            //$pattern_next_date->setTime($checked_hour->format('H'), $checked_hour->format('i'));
            $pattern_next_date->modify('+1 day');
            $return_date = $pattern_next_date;
        }
        // check if delivery day gets on an affordable day
        // returned date cant be in past
        while (!in_array($return_date->format('N'), $a_all_days_num)) {
            $return_date->modify('+1 day');
        }
        if ((new \DateTime())->diff($return_date)->days > 0 && !$in_past) {
            $return_date = new \DateTime();
            while (!in_array($return_date->format('N'), $a_all_days_num)) {
                $return_date->modify('+1 day');
            }
        }
        return $format ? $return_date->format($format) : $return_date;
    }
    /**
     * get open & close time of specified day
     * @param Platforms|NULL $oPlatform
     * @param $date
     * @return array|null
     */
    public function get_postal_open_ours(Platforms $o_platform = null, $o_date = null)
    {
        is_null($o_date) && $o_date = new \DateTime();
        $o_platforms_open_hours = $o_platform->platforms_open_hours;
        foreach ($o_platforms_open_hours as $o_open_hour) {
            if (in_array($o_date->format('N'), explode(',', $o_open_hour->open_days))) {
                $open_time_from = \DateTime::create_from_format('g:i A', $o_open_hour->open_time_from);
                $open_time_to = \DateTime::create_from_format('g:i A', $o_open_hour->open_time_to);
                return ['open_time_from' => $open_time_from->format('H:i'), 'open_time_to' => $open_time_to->format('H:i')];
            }
        }
        return null;
    }
}