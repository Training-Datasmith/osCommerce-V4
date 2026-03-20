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
namespace backend\design;

use common\helpers\Page_Status as hPageStatus;
use common\models\Page_Status;
use common\models\Page_Status_Switch;
use yii\base\Widget;
class Change_Status extends Widget
{
    public $type;
    public $element;
    public $page_id;
    public $statuses = [];
    public $periods = [];
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        switch ($this->element) {
            case 'button':
                return $this->switcher('button');
            case 'dropdown':
                return $this->switcher('dropdown');
            case 'schedule':
                return $this->schedule();
        }
        return '';
    }
    private function switcher($name)
    {
        $status = Page_Status::find()->where(['type' => $this->type, 'page_id' => $this->page_id])->as_array()->one();
        $status_keys = [];
        $statuses = [];
        if (count($this->statuses) == 0) {
            $statuses = H_Page_Status::PAGE_STATUSES;
            foreach ($statuses as $status_key => $status_title) {
                $status_keys[] = $status_key;
            }
        } else {
            foreach (H_Page_Status::PAGE_STATUSES as $status_key => $status_title) {
                if (in_array($status_key, $this->statuses)) {
                    $statuses[$status_key] = $status_title;
                }
            }
            $status_keys = $this->statuses;
        }
        return $this->render('change-status-' . $name . '.tpl', ['id' => rand(1, 100000), 'pageStatusActions' => $this->status_actions(), 'status' => $status['status'], 'statuses' => $statuses, 'data' => json_encode(['statuses' => $statuses, 'statusKeys' => $status_keys, 'status' => $status['status'], 'pageStatusActions' => $this->status_actions(), 'pageId' => $this->page_id, 'type' => $this->type])]);
    }
    private function schedule()
    {
        $page_switchers = Page_Status_Switch::find()->alias('pss')->inner_join(Page_Status::table_name() . ' ps', 'ps.page_status_id = pss.page_status_id')->where(['ps.type' => $this->type, 'ps.page_id' => $this->page_id])->as_array()->all();
        foreach ($page_switchers as $key => $page_switcher) {
            $date = date_create_from_format(\common\helpers\Date::DATABASE_DATETIME_FORMAT, $page_switcher['date']);
            $page_switchers[$key]['day'] = -1;
            switch ($page_switcher['period']) {
                case 'year':
                    $page_switchers[$key]['date'] = $date->format('d M g:i A');
                    break;
                case 'month':
                    $page_switchers[$key]['date'] = $date->format('d g:i A');
                    break;
                case 'week':
                    $page_switchers[$key]['date'] = $date->format('g:i A');
                    $page_switchers[$key]['day'] = $date->format('N') - 1;
                    break;
                case 'day':
                    $page_switchers[$key]['date'] = $date->format('g:i A');
                    break;
                default:
                    $page_switchers[$key]['date'] = $date->format('d M Y g:i A');
            }
        }
        return $this->render('change-status.tpl', ['pageSwitchers' => $page_switchers, 'pageStatusActions' => $this->status_actions(), 'pageStatusPeriods' => $this->status_periods(), 'weekDays' => [TEXT_MONDAY, TEXT_TUESDAY, TEXT_WEDNESDAY, TEXT_THURSDAY, TEXT_FRIDAY, TEXT_SATURDAY, TEXT_SUNDAY]]);
    }
    private function status_actions()
    {
        $actions = [];
        if (count($this->statuses) > 0) {
            foreach ($this->statuses as $status) {
                if (H_Page_Status::PAGE_STATUSES[$status]) {
                    $actions[$status] = sprintf(STATUS_MOVE_TO, H_Page_Status::PAGE_STATUSES[$status]);
                }
            }
        } else {
            foreach (H_Page_Status::PAGE_STATUSES as $status => $title) {
                $actions[$status] = sprintf(STATUS_MOVE_TO, $title);
            }
        }
        return $actions;
    }
    private function status_periods()
    {
        if (count($this->periods) == 0) {
            return H_Page_Status::PAGE_STATUS_PERIODS;
        }
        $periods = [];
        foreach ($this->periods as $period) {
            if (H_Page_Status::PAGE_STATUS_PERIODS[$period]) {
                $periods[$period] = H_Page_Status::PAGE_STATUS_PERIODS[$period];
            }
        }
        return $periods;
    }
}