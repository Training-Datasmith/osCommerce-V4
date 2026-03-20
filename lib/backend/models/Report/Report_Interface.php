<?php

declare (strict_types=1);
namespace backend\models\Report;

interface Report_Interface
{
    public function load_purchases();
    public function get_table_title();
    public function get_range();
    public function get_options($range);
    public function get_rows_count();
    public function convert_column_title($value);
}