<?php

declare (strict_types=1);
namespace backend\models\Report;

use Yii;
class Yearly_Report extends Basic_Report implements Report_Interface
{
    public const DELIMETER = '/';
    public const SHOW_ROWS = 10;
    protected $start_year;
    protected $end_year;
    protected $all_params = [];
    private $name = 'yearly';
    protected $sql_params = ['group' => ['year'], 'select_period' => 'date'];
    protected $range = ['all' => TEXT_ALL_PERIOD, 'custom' => TEXT_CUSTOM];
    protected $current_range;
    public function __construct($data)
    {
        if (isset($data['start_custom']) && !empty($data['start_custom'])) {
            $this->start_year = $data['start_custom'];
        }
        if (isset($data['end_custom']) && !empty($data['end_custom'])) {
            $this->end_year = $data['end_custom'];
        }
        if (tep_not_null($this->start_year) && tep_not_null($this->end_year)) {
            if ($this->start_year > $this->end_year) {
                $_y = $this->end_year;
                $this->end_year = $this->start_year;
                $this->start_year = $_y;
            }
        }
        $years = $this->get_years_list();
        $years = array_values($years);
        if (empty($this->start_year)) {
            $this->start_year = $years[0];
        }
        if (empty($this->end_year)) {
            $this->end_year = $years[sizeof($years) - 1];
        }
        //need ordering check
        //echo '<pre>';print_r($this);die;
        parent::__construct($data);
    }
    public function get_options($range)
    {
        switch ($range) {
            case 'all':
                return '';
                break;
            case 'custom':
                return Yii::$app->controller->render_ajax('yearly_options', ['year' => $this->start_year, 'years' => $this->get_years_list(), 'start_custom' => $this->request['start_custom'], 'end_custom' => $this->request['end_custom']]);
                break;
        }
    }
    public function load_purchases($for_map = false)
    {
        $where = " (year(o.date_purchased) between '" . $this->start_year . "' and '" . $this->end_year . "') ";
        $data = $this->get_raw_data($where, $for_map);
        if ($for_map) {
            return $data;
        }
        if (is_array($data)) {
            $filled = false;
            $new_data = [];
            foreach ($data as $k => $v) {
                if (!$filled) {
                    $template = $v;
                    foreach ($template as $key => $value) {
                        if ($key != 'period_full') {
                            $template[$key] = '';
                        }
                    }
                    $new_data = $this->prepare_years_range($template, 'Y');
                    $filled = true;
                }
                if (!empty($v['period'])) {
                    $data[$k]['period'] = date('Y', strtotime($v['period']));
                    $new_data[date('Y', strtotime($v['period']))] = $data[$k];
                }
            }
            $_temp = [];
            foreach ($new_data as $kyear => $vyear) {
                $_temp[] = $vyear;
            }
            $data = $_temp;
        }
        return $data;
    }
    public function get_range()
    {
        return date('Y', mktime(0, 0, 0, 1, 1, $this->start_year)) . ' - ' . date('Y', mktime(0, 0, 0, 12, 1, $this->end_year));
    }
    public function get_table_title()
    {
        return TEXT_SALES_YEARLY_STATISTICS;
    }
    public function convert_column_title($value)
    {
        if ($value == 'period') {
            return parent::convert_column_title(TITLE_YEAR);
        }
        return parent::convert_column_title($value);
    }
    public function get_rows_count()
    {
        return self::SHOW_ROWS;
    }
}