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
use Box\Spout\Reader\Common\Creator\Reader_Entity_Factory;
use yii\base\Base_Object;
//extends BaseObject
class XLSX extends Base_Object implements Reader_Interface
{
    protected $file_header;
    private $file_start_pointer = 0;
    private $file_header_rows = 15;
    ///search headers in first file_header_rows rows (max number of filled in cells)
    private $file_data_start_pointer;
    //    private $currow=1; //excel style ->key()
    protected $currow;
    public $filename = 'ep.xls';
    public $sheet_index = 0;
    public $sheet_name = '';
    public $without_header = false;
    protected $file_handle;
    //protected $maxColumn; //A,B,C ...
    protected $max_column_index = 0;
    //1,2,3 ...
    protected $max_row;
    //1,2,3 ....
    //protected $maxColumnToCheck = 'CZ';
    protected $reader;
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\Exception $ex) {
            \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG-EP');
        }
    }
    protected function open_file()
    {
        $this->file_header = null;
        $this->file_start_pointer = 0;
        /*        $this->file_handle = fopen($this->filename,'r');
                        if ( !$this->file_handle ) {
                            throw new Exception('Can\'t open file', 20);
                        }
                */
        $this->reader = Reader_Entity_Factory::create_xlsx_reader();
        $this->reader->open($this->filename);
        $cnt = 1;
        foreach ($this->reader->get_sheet_iterator() as $sheet) {
            $this->file_handle = $sheet->get_row_iterator();
            if (empty($this->sheet_name) && empty($this->sheet_index) || !empty($this->sheet_name) && $this->sheet_name == $sheet->get_name() || !empty($this->sheet_name) && $this->sheet_name == $cnt . '_' . $sheet->get_name() || !empty($this->sheet_index) && $this->sheet_index == $cnt) {
                break;
                // no need to read more sheets
            }
            $cnt++;
        }
        $this->read_columns();
        $this->currow = $this->file_data_start_pointer;
        $this->max_row = 10000;
        //dummy as as SPOUT don't read all the file Required for progress only
    }
    public function current_position()
    {
        return $this->file_handle->key();
    }
    public function set_data_position($position)
    {
        $this->currow = $position;
    }
    /**
    * @return [sheetName => [
                       'columns' => $fileColumns,
                   ] ];
    */
    public function read_sheets()
    {
        $ret = [];
        $sheets_cnt = 0;
        $this->reader = Reader_Entity_Factory::create_xlsx_reader();
        $this->reader->open($this->filename);
        foreach ($this->reader->get_sheet_iterator() as $sheet) {
            $this->file_header = null;
            $this->file_start_pointer = 0;
            $this->max_column_index = 0;
            $this->file_handle = $sheet->get_row_iterator();
            $headers = $this->read_columns();
            $sheets_cnt++;
            $name = $sheet->get_name();
            if (!empty($headers)) {
                $ret[$sheets_cnt . '_' . $name] = ['columns' => $headers];
            }
        }
        return $ret;
    }
    public function read_columns()
    {
        if (is_null($this->file_header)) {
            if (!$this->file_handle) {
                $this->open_file();
            }
            $data_start = 0;
            if (is_null($this->file_header)) {
                $this->file_handle->rewind();
                $this->file_header = false;
                if ($this->without_header) {
                    $this->file_header = array_keys($this->read());
                    $this->file_handle->rewind();
                } else {
                    // skip table header
                    // until max filled row with unique values
                    $u = [];
                    $_file_header = [];
                    for ($row = 1; $row <= $this->file_header_rows; ++$row) {
                        $tmp = $this->read();
                        if (is_array($tmp)) {
                            $u = array_unique($tmp);
                            if (ceil(0.8 * count($u)) > $this->max_column_index) {
                                $this->max_column_index = count($u);
                                $data_start = $this->current_position() - 1;
                                $_file_header = array_filter($u, 'strlen');
                                /// strip empty and Null headers
                            }
                        }
                    }
                    if (!is_array($_file_header)) {
                        $this->file_header = [];
                    } else {
                        $this->file_header = $_file_header;
                    }
                }
            }
            if (is_null($this->file_data_start_pointer) || $this->file_data_start_pointer < $data_start) {
                $this->file_data_start_pointer = $data_start;
                $this->file_handle->rewind();
                for ($i = 0; $i < $data_start; $i++) {
                    $this->file_handle->next();
                }
            }
        }
        return array_values($this->file_header);
    }
    public function get_progress()
    {
        $percent_done = min(100, $this->current_position() / $this->max_row * 100);
        return number_format($percent_done, 1, '.', '');
    }
    public function read()
    {
        if (!$this->file_handle) {
            $this->open_file();
        }
        $data = false;
        if ($this->file_handle->valid()) {
            $data = $this->file_handle->current();
            $this->file_handle->next();
            if (is_object($data) && $data instanceof \Box\Spout\Common\Entity\Row) {
                $data = $data->to_array();
            }
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (is_a($v, 'DateTime')) {
                        if ($v->format('H:i') == '00:00') {
                            //time not specifiede return date only
                            $data[$k] = $v->format(\common\helpers\Date::DATE_FORMAT);
                        } else {
                            $data[$k] = $v->format(\common\helpers\Date::DATE_TIME_FORMAT);
                        }
                    }
                }
                if (is_array($this->file_header)) {
                    $named_data = [];
                    foreach ($this->file_header as $idx => $key_name) {
                        $named_data[$key_name] = isset($data[$idx]) ? $data[$idx] : null;
                    }
                    $data = $named_data;
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