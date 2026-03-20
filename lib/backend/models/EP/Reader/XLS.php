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
namespace backend\models\EP\Reader;

use backend\models\EP\Exception;
use Php_Office\Php_Spreadsheet\Reader\Xls as PhpOfficeXls;
class XLS implements Reader_Interface
{
    protected $file_header;
    private $file_start_pointer = 0;
    private $file_header_rows = 10;
    private $file_data_start_pointer;
    private $currow = 1;
    //excel style
    public $filename;
    protected $file_handle;
    protected $max_column;
    //A,B,C ...
    protected $max_column_index;
    //1,2,3 ...
    protected $max_row;
    //1,2,3 ....
    protected $max_column_to_check = 'CZ';
    protected $reader;
    protected function open_file()
    {
        $this->file_header = null;
        $this->file_start_pointer = 0;
        /*        $this->file_handle = fopen($this->filename,'r');
                        if ( !$this->file_handle ) {
                            throw new Exception('Can\'t open file', 20);
                        }
                */
        $reader = new Php_Office_Xls();
        $worksheet_names = $reader->list_worksheet_names($this->filename);
        if (is_array($worksheet_names) && count($worksheet_names) > 0) {
            $sheetname = $worksheet_names[0];
        }
        $reader->set_load_sheets_only($sheetname);
        $reader->set_read_data_only(true);
        if (is_object($this->filter_subset)) {
            $reader->set_read_filter($this->filter_subset);
        }
        $spreadsheet = $reader->load($this->filename);
        $this->file_handle = $spreadsheet->get_active_sheet();
        $worksheet = $this->file_handle;
        $this->max_row = $worksheet->get_highest_row();
        // e.g. 10
        $this->max_column = $worksheet->get_highest_column();
        // e.g 'F'
        $this->max_column_index = \Php_Office\Php_Spreadsheet\Cell\Coordinate::column_index_from_string($this->max_column);
        // e.g. 5
        $this->max_column++;
        $this->read_columns();
        $this->currow = $this->file_data_start_pointer + 1;
        //$tmp = $this;         unset($tmp->file_handle);        echo "#### <PRE>" .print_r($tmp, 1) ."</PRE>";        die;
    }
    public function current_position()
    {
        return $this->currow;
    }
    public function set_data_position($position)
    {
        $this->currow = $position;
    }
    public static function cell_range($from, $to)
    {
        $ret = [];
        if ($from > $to) {
            $x = $from;
            $from = $to;
            $to = $x;
        }
        $to++;
        while ($from !== $to) {
            $ret[] = $from++;
        }
        return $ret;
    }
    public function read_columns()
    {
        if (is_null($this->file_header)) {
            if (!$this->file_handle) {
                $this->filter_subset = new Xls_Read_Filter($this->file_start_pointer, $this->file_header_rows, self::cell_range('A', $this->max_column_to_check));
                $this->open_file();
            }
            $data_start = 0;
            if (is_null($this->file_header)) {
                $this->file_header = false;
                if ($this->without_header) {
                    $tmp = $this->file_handle->range_to_array(
                        'A' . 1 . ':' . $this->max_column . 1,
                        // The worksheet range that we want to retrieve
                        '',
                        // Value that should be returned for empty cells
                        true,
                        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
                        true,
                        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
                        false
                    );
                } else {
                    // skip table header
                    // until max filled row with unique values
                    $u = [];
                    for ($row = 1; $row <= $this->max_row; ++$row) {
                        $tmp = $this->file_handle->range_to_array(
                            'A' . $row . ':' . $this->max_column . $row,
                            // The worksheet range that we want to retrieve
                            '',
                            // Value that should be returned for empty cells
                            true,
                            // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
                            true,
                            // Should values be formatted (the equivalent of getFormattedValue() for each cell)
                            false
                        );
                        $u = array_unique($tmp[0]);
                        if (ceil(0.8 * $this->max_column_index) <= count($u)) {
                            $this->max_column_index = count($u);
                            $data_start = $row;
                            break;
                        }
                        //echo  'A' . ($row) . ':' . ($this->maxColumn) . ($row) . " \$u #### <PRE>" .print_r($u , 1) ."</PRE>";
                    }
                    $u = array_filter($u, 'strlen');
                    /// strip empty and Null headers
                    $this->file_header = $u;
                }
            }
            if (is_null($this->file_data_start_pointer) || $this->file_data_start_pointer < $data_start) {
                $this->file_data_start_pointer = $data_start;
            }
            //reopen full file (without row limits)
            if (is_object($this->filter_subset)) {
                unset($this->filter_subset);
                $this->open_file();
            }
        }
        return array_values($this->file_header);
    }
    public function get_progress()
    {
        $percent_done = min(100, $this->currow / $this->max_row * 100);
        return number_format($percent_done, 1, '.', '');
    }
    public function read()
    {
        if (!$this->file_handle) {
            $this->open_file();
        }
        $data = false;
        if ($this->currow <= $this->max_row) {
            $data = $this->file_handle->range_to_array(
                'A' . $this->currow . ':' . $this->max_column . $this->currow,
                // The worksheet range that we want to retrieve
                null,
                // Value that should be returned for empty cells
                true,
                // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
                true,
                // Should values be formatted (the equivalent of getFormattedValue() for each cell)
                false
            );
            $data = $data[0];
            $this->currow++;
            if (is_array($data)) {
                if (is_array($this->file_header)) {
                    $named_data = [];
                    foreach ($this->file_header as $idx => $key_name) {
                        $named_data[$key_name] = isset($data[$idx]) ? $data[$idx] : null;
                    }
                    return $named_data;
                }
            }
        }
        return $data;
    }
    protected function detect_encoding()
    {
        // check UTF encoding
        rewind($this->file_handle);
        $utf_map = $this->get_utf_bom_map();
        if (isset($utf_map[$this->use_config['input_encoding']])) {
            $this->file_start_pointer = strlen($utf_map[$this->use_config['input_encoding']]);
        }
        if ($this->use_config['input_encoding'] == 'auto') {
            $read_length = array_reduce($utf_map, function ($initial, $signature) {
                return max($initial, strlen($signature));
            }, 0);
            $check_signature = fread($this->file_handle, $read_length);
            rewind($this->file_handle);
            foreach ($utf_map as $utf_encoding => $utf_signature) {
                if (substr($check_signature, 0, strlen($utf_signature)) == $utf_signature) {
                    $this->use_config['input_encoding'] = $utf_encoding;
                    $this->file_start_pointer = strlen($utf_signature);
                    break;
                }
            }
        }
        fseek($this->file_handle, $this->file_start_pointer, SEEK_SET);
    }
    private function de_encode($data_array)
    {
        static $preferred_encoding_order = false;
        if (!is_array($preferred_encoding_order)) {
            $encoding_list = mb_list_encodings();
            $encoding_list = preg_grep('/(-Mobile|auto)/i', $encoding_list, PREG_GREP_INVERT);
            $prefer3_order = 'UTF,ISO,WIN,CP8';
            usort($encoding_list, function ($a, $b) use ($prefer3_order) {
                $cmp_res = 0;
                $a_idx = strpos($prefer3_order, strtoupper(substr($a, 0, 3)));
                $b_idx = strpos($prefer3_order, strtoupper(substr($b, 0, 3)));
                if ($a_idx !== false && $b_idx !== false) {
                    $cmp_res = $a_idx - $b_idx;
                } elseif ($a_idx !== false) {
                    $cmp_res = -1;
                } elseif ($b_idx !== false) {
                    $cmp_res = 1;
                }
                return $cmp_res;
            });
            $preferred_encoding_order = $encoding_list;
        }
        foreach ($data_array as $key => $file_data) {
            if (!empty($file_data) && !is_numeric($file_data)) {
                $cell_encoding = mb_detect_encoding($file_data, $preferred_encoding_order, true);
                if ($cell_encoding != 'UTF-8') {
                    $data_array[$key] = mb_convert_encoding($file_data, 'UTF-8', $cell_encoding);
                }
            }
        }
        return $data_array;
    }
}