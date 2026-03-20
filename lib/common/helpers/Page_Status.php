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

use common\models\Page_Status as mPageStatus;
use common\models\Page_Status_Switch;
class Page_Status
{
    public const PAGESTATUS_CACHE_LIFETIME = 5;
    public const PAGE_STATUSES = ['public' => STATUS_PUBLIC, 'draft' => STATUS_DRAFT];
    public const PAGE_STATUS_PERIODS = ['once' => STATUS_PERIOD_ONCE, 'year' => STATUS_PERIOD_EVERY_YEAR, 'month' => STATUS_PERIOD_EVERY_MONTH, 'week' => STATUS_PERIOD_EVERY_WEEK, 'day' => STATUS_PERIOD_EVERY_DAY];
    public static function get_ids($status, $type)
    {
        self::switch_statuses();
        $query_key = (string) $status . '&' . (string) $type;
        static $page_status_ids = [];
        if (!isset($page_status_ids[$query_key])) {
            $page_statuses = M_Page_Status::find()->where(['type' => $type, 'status' => $status])->cache(self::PAGESTATUS_CACHE_LIFETIME)->as_array()->all();
            $ids = [];
            foreach ($page_statuses as $page_status) {
                $ids[] = $page_status['page_id'];
            }
            $page_status_ids[$query_key] = $ids;
        }
        return $page_status_ids[$query_key];
    }
    public static function is_status($status, $type, $page_id)
    {
        self::switch_statuses();
        $exists = M_Page_Status::find()->where(['type' => $type, 'page_id' => $page_id, 'status' => $status])->exists();
        return $exists;
    }
    public static function switch_statuses()
    {
        static $changed = false;
        if ($changed) {
            return;
        }
        $changed = true;
        $page_status_switchers = Page_Status_Switch::find()->where(['<', 'date', new \yii\db\Expression('NOW()')])->order_by('date')->all();
        $now = new \DateTime('NOW');
        foreach ($page_status_switchers as $page_status_switcher) {
            $page_status = M_Page_Status::find_one(['page_status_id' => $page_status_switcher->page_status_id]);
            $page_status->status = $page_status_switcher->status;
            $page_status->save();
            $date = date_create_from_format(\common\helpers\Date::DATABASE_DATETIME_FORMAT, $page_status_switcher->date);
            while (date_diff($now, $date)->invert) {
                switch ($page_status_switcher->period) {
                    case 'year':
                        $date->modify('+1 year');
                        break;
                    case 'month':
                        $date->modify('first day of next month');
                        $date->modify('+' . ($page_status_switcher->day - 1) . ' days');
                        break;
                    case 'week':
                        $date->modify('+1 week');
                        break;
                    case 'day':
                        $date->modify('+1 day');
                        break;
                    default:
                        break 2;
                }
            }
            if ($page_status_switcher->period == 'once') {
                $page_status_switcher->delete();
            } else {
                $page_status_switcher->date = $date->format(\common\helpers\Date::DATABASE_DATETIME_FORMAT);
                $page_status_switcher->save();
            }
        }
    }
    public static function save_scheduled_statuses($type, $page_id, $statuses)
    {
        $page_status_id = M_Page_Status::find_one(['type' => $type, 'page_id' => $page_id])->page_status_id ?? null;
        if ($page_status_id) {
            Page_Status_Switch::delete_all(['page_status_id' => $page_status_id]);
        } else {
            $page_status = new M_Page_Status();
            $page_status->type = $type;
            $page_status->page_id = $page_id;
            $page_status->status = 'draft';
            $page_status->save();
            $page_status_id = $page_status->page_status_id;
        }
        foreach ($statuses['action'] as $key => $action) {
            $period = $statuses['period'][$key];
            $entry_date = $statuses['date'][$key];
            $day = $statuses['day'][$key];
            if (!$entry_date) {
                continue;
            }
            switch ($period) {
                case 'year':
                    $format = 'd M g:i a';
                    break;
                case 'month':
                    $format = 'd g:i a';
                    break;
                case 'week':
                    $format = 'g:i a';
                    break;
                case 'day':
                    $format = 'g:i a';
                    break;
                default:
                    $format = 'd M Y g:i a';
            }
            $date = date_create_from_format($format, $entry_date);
            if ($period == 'month') {
                preg_match('/^[0-9]{1,2}/', $entry_date, $matches);
                $date->modify('first day of this month');
                $date->modify('+' . ($matches[0] - 1) . ' days');
            } elseif ($period == 'week') {
                $date = new \DateTime('NOW');
                $time = date_create_from_format($format, $entry_date);
                $date->modify('Monday this week');
                $date->set_time($time->format('G'), $time->format('i'));
                $date->modify('+' . $day . ' days');
            }
            $now = new \DateTime('NOW');
            $date_diff = date_diff($now, $date);
            if ($date_diff->invert) {
                switch ($period) {
                    case 'year':
                        $date->modify('+1 year');
                        break;
                    case 'month':
                        $date->modify('first day of next month');
                        $date->modify('+' . ($matches[0] - 1) . ' days');
                        break;
                    case 'week':
                        $date->modify('+1 week');
                        break;
                    case 'day':
                        $date->modify('+1 day');
                        break;
                    default:
                        continue 2;
                }
            }
            $page_status_switch = new Page_Status_Switch();
            $page_status_switch->page_status_id = $page_status_id;
            $page_status_switch->status = $action;
            $page_status_switch->period = $period;
            $page_status_switch->date = $date->format(\common\helpers\Date::DATABASE_DATETIME_FORMAT);
            $page_status_switch->save();
        }
    }
    public static function show_status($type, $page_id)
    {
        self::switch_statuses();
        $page = M_Page_Status::find()->where(['type' => $type, 'page_id' => $page_id])->as_array()->one();
        return Html::tag('span', self::PAGE_STATUSES[$page['status']], ['class' => 'current-page-status']);
    }
    public static function show_button($type, $page_id, $statuses = [])
    {
        return \backend\design\Change_Status::widget(['element' => 'button', 'type' => $type, 'pageId' => $page_id, 'statuses' => $statuses]);
    }
    public static function show_dropdown($type, $page_id, $statuses = [])
    {
        return \backend\design\Change_Status::widget(['element' => 'dropdown', 'type' => $type, 'pageId' => $page_id, 'statuses' => $statuses]);
    }
    public static function show_schedule($type, $page_id, $statuses = [], $periods = [])
    {
        return \backend\design\Change_Status::widget(['element' => 'schedule', 'type' => $type, 'pageId' => $page_id, 'statuses' => $statuses, 'periods' => $periods]);
    }
}