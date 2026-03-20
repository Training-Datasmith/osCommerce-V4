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
namespace backend\models\EP\Provider;

use backend\models\EP;
use backend\models\EP\Formatter;
abstract class Provider_Abstract
{
    /**
     * @var EP\Directory
     */
    public $directory_obj;
    public $languages_id = 0;
    public $job_config = [];
    protected $fields = [];
    protected $export_columns;
    protected $main_source;
    protected $data_sources;
    protected $pre_lookup;
    protected $file_primary_column;
    protected $file_primary_columns;
    protected $sources_for_key = '';
    public $format = '';
    public $import_config = [];
    protected $w_columns = [];
    public function __construct($config = [])
    {
        $this->languages_id = \common\classes\language::default_id();
        $languages_id = (int) \Yii::$app->settings->get('languages_id');
        if ($languages_id > 0) {
            $this->languages_id = $languages_id;
        }
        if (is_array($config)) {
            $props = \Yii::get_object_vars($this);
            foreach ($config as $config_key => $config_value) {
                if (array_key_exists($config_key, $props)) {
                    $this->{$config_key} = $config_value;
                }
            }
        }
        $this->init();
    }
    public function init()
    {
    }
    public static function is_export_available()
    {
        return true;
    }
    public static function is_import_available()
    {
        return true;
    }
    /**
     * @param string $format
     */
    public function set_format($format)
    {
        $this->format = $format;
    }
    public function set_columns($columns)
    {
        $this->w_columns = $columns;
    }
    public function custom_config($config)
    {
    }
    public function import_options()
    {
        return false;
    }
    protected function build_sources($use_columns)
    {
        $in_columns = md5(implode('|', $use_columns));
        if ($this->sources_for_key == $in_columns) {
            return false;
        }
        // {{ prepare column data
        $export_columns = [];
        $main_source = ['select' => '', 'columns' => [], 'select_raw' => null];
        $data_sources = [];
        $file_primary_column = '';
        $file_primary_columns = [];
        $pre_lookup = [];
        foreach ($this->fields as $_field) {
            if (isset($_field['is_key']) && $_field['is_key'] === true) {
                $file_primary_column = isset($_field['column_db']) ? $_field['column_db'] : $_field['name'];
            } elseif (isset($_field['is_key_part']) && $_field['is_key_part'] === true) {
                $file_primary_columns[$_field['name']] = (!empty($_field['prefix']) ? $_field['prefix'] . '.' : '') . (isset($_field['column_db']) ? $_field['column_db'] : $_field['name']);
            }
            if (!in_array($_field['name'], $use_columns)) {
                continue;
            }
            // skip not configured here
            //      if ( is_array($selected_fields) && !in_array($_field['name'],$selected_fields) ) {
            //        continue;
            //      }
            $select_prefix = isset($_field['prefix']) && !empty($_field['prefix']) ? $_field['prefix'] . '.' : '';
            if (isset($_field['data_descriptor'])) {
                if (isset($_field['pre_lookup'])) {
                    if (!isset($pre_lookup[$_field['pre_lookup']])) {
                        $pre_lookup[$_field['pre_lookup']] = [];
                    }
                    $pre_lookup[$_field['pre_lookup']][] = $_field;
                }
                if (!isset($data_sources[$_field['data_descriptor']])) {
                    $data_descriptor = explode('|', $_field['data_descriptor']);
                    $data_sources[$_field['data_descriptor']] = ['select' => '', 'select_raw' => '', 'columns' => [], 'table' => $data_descriptor[0] == '%' ? $data_descriptor[1] : false, 'init_function' => $data_descriptor[0] == '@' ? $data_descriptor[1] : false, 'params' => array_slice($data_descriptor, 2)];
                }
                if (isset($_field['calculated']) && $_field['calculated']) {
                } else {
                    $data_sources[$_field['data_descriptor']]['select'] .= (isset($_field['column_db']) ? "{$select_prefix}{$_field['column_db']} AS {$_field['name']}" : "{$select_prefix}{$_field['name']}") . ', ';
                    $data_sources[$_field['data_descriptor']]['select_raw'] .= (isset($_field['column_db']) ? "{$select_prefix}{$_field['column_db']}" : "{$select_prefix}{$_field['name']}") . ', ';
                    $data_sources[$_field['data_descriptor']]['columns'][$_field['name']] = isset($_field['column_db']) ? $_field['column_db'] : $_field['name'];
                }
            } else if (isset($_field['calculated']) && $_field['calculated']) {
            } else {
                $main_source['select'] .= (isset($_field['column_db']) ? "{$select_prefix}{$_field['column_db']} AS {$_field['name']}" : "{$select_prefix}{$_field['name']}") . ', ';
                $main_source['select_raw'] .= (isset($_field['column_db']) ? "{$select_prefix}{$_field['column_db']}" : "{$select_prefix}{$_field['name']}") . ', ';
                $main_source['columns'][$_field['name']] = isset($_field['column_db']) ? $_field['column_db'] : $_field['name'];
            }
            $export_columns[$_field['name']] = $_field;
        }
        // }}
        $this->export_columns = $export_columns;
        $this->main_source = $main_source;
        $this->data_sources = $data_sources;
        $this->pre_lookup = $pre_lookup;
        $this->file_primary_column = $file_primary_column;
        $this->file_primary_columns = $file_primary_columns;
        $this->sources_for_key = $in_columns;
        return true;
    }
    public function export(Formatter\Formatter_Interface $output, $selected_fields, $filter)
    {
    }
    public function import(Formatter\Formatter_Interface $input, EP\Messages $message)
    {
    }
    /**
     * @deprecated
     *
     * @param Formatter\FormatterInterface $input
     * @param array $file_header_line
     * @return float|int
     */
    public function is_columns_match(Formatter\Formatter_Interface $input, array $file_header_line)
    {
        $file_header_line = $input->get_headers();
        return $this->get_column_match_score($file_header_line);
    }
    public function get_column_match_score($input_columns)
    {
        $columns = $this->get_columns();
        $score = 0;
        foreach ($input_columns as $field) {
            if (in_array($field, $columns)) {
                $score++;
            }
        }
        if (count($input_columns) == 0) {
            return 0;
        }
        return $score / count($input_columns);
    }
    /**
     *
     * @param array|false $skip skip fields with a flag ['adm_hidden' => 1]
     * @return array
     */
    public function get_columns($skip = false)
    {
        $columns = [];
        foreach ($this->fields as $field) {
            if (!empty($skip) && is_array($skip)) {
                foreach ($skip as $_key) {
                    if (!empty($field[$_key])) {
                        continue 2;
                    }
                }
            }
            $columns[$field['name']] = $field['value'];
        }
        return $columns;
    }
    public function set_column_remap($remap)
    {
        foreach ($this->fields as $idx => $field) {
            if (isset($remap[$field['name']])) {
                $this->fields[$idx]['value'] = $remap[$field['name']];
            }
        }
    }
    /**
     * unset disabled not key fields according EP_DISABLED_FILEDS_<classname> constant (if specified)
     */
    protected function init_fields()
    {
        $reflect = new \ReflectionClass($this);
        $const = 'EP_DISABLED_FILEDS_' . strtoupper($reflect->get_short_name());
        if (defined($const) && !empty(constant($const))) {
            $disbled_keys = explode(',', constant($const));
            if (is_array($this->fields)) {
                foreach ($this->fields as $k => $v) {
                    if (empty($v['is_key']) && empty($v['is_key_part']) && in_array($v['name'], $disbled_keys)) {
                        unset($this->fields[$k]);
                    }
                }
            }
        }
    }
    protected function report_error(\Exception $e)
    {
        if (YII_DEBUG) {
            \Yii::error($e->get_message() . "\n" . $e->get_trace_as_string());
        }
    }
}