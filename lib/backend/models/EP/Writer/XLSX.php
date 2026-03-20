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
namespace backend\models\EP\Writer;

use Php_Office\Php_Spreadsheet\Worksheet\Worksheet;
use yii\base\Base_Object;
class XLSX extends Base_Object implements Writer_Interface
{
    public $filename;
    public $writer_type = 'Xlsx';
    protected $_first_write = true;
    public $header_line = true;
    protected $spreadsheet;
    protected $columns = [];
    protected $descriptions = [];
    protected $row_counter = 0;
    protected function open_output_file()
    {
        $this->spreadsheet = new \Php_Office\Php_Spreadsheet\Spreadsheet();
        //        $sheet = $this->spreadsheet->getActiveSheet();
        //        $headers = array_values($headers_map);
        //        for ($i = 0, $l = sizeof($headers); $i < $l; $i++) {
        //            $sheet->setCellValueByColumnAndRow($i + 1, 1, $headers[$i]);
        //            $sheet->getColumnDimension(chr(ord('A')+$i))->setAutoSize(true);
        //            if ($headers[$i]=='Discount Amount') {
        //                $sheet->getStyle(chr(ord('A') + $i))
        //                    ->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00);
        //            }else{
        //                $sheet->getStyle(chr(ord('A') + $i))
        //                    ->getNumberFormat()
        //                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        //            }
        //        }
    }
    public function set_description(array $descriptions)
    {
        if (!empty($descriptions)) {
            $this->descriptions = $descriptions;
        }
    }
    protected function write_header()
    {
        $headers = array_values($this->columns);
        $sheet = $this->spreadsheet->get_active_sheet();
        $row = 1;
        if (!empty($this->descriptions['top'])) {
            $sheet->merge_cells('A1:' . \Php_Office\Php_Spreadsheet\Cell\Coordinate::string_from_column_index(sizeof($headers)) . '1');
            $sheet->set_cell_value_by_column_and_row(1, $row, $this->descriptions['top']);
            $lines_cnt = count(explode("\n", $this->descriptions['top']));
            if ($lines_cnt > 1) {
                $sheet->get_style('A1:A1')->get_alignment()->set_vertical(\Php_Office\Php_Spreadsheet\Style\Alignment::VERTICAL_TOP);
                $sheet->get_row_dimension('1')->set_row_height(min(($lines_cnt + 1) * 12.75, 409));
            }
            $row++;
            $this->row_counter++;
            $row++;
            $this->row_counter++;
        }
        for ($i = 0, $l = sizeof($headers); $i < $l; $i++) {
            $sheet->set_cell_value_by_column_and_row($i + 1, $row, $headers[$i]);
            $sheet->get_column_dimension(\Php_Office\Php_Spreadsheet\Cell\Coordinate::string_from_column_index($i + 1))->set_auto_size(true);
        }
        $style_array = ['font' => ['bold' => true]];
        $sheet->get_style('A' . $row . ':' . \Php_Office\Php_Spreadsheet\Cell\Coordinate::string_from_column_index(sizeof($headers)) . $row)->apply_from_array($style_array);
        $sheet->get_style('A:' . \Php_Office\Php_Spreadsheet\Cell\Coordinate::string_from_column_index(sizeof($headers)))->get_number_format()->set_format_code(\Php_Office\Php_Spreadsheet\Style\Number_Format::FORMAT_TEXT);
        $this->row_counter++;
        $this->_first_write = false;
    }
    public function set_columns(array $columns)
    {
        $this->columns = $columns;
        if ($this->_first_write) {
            $this->open_output_file();
            if ($this->header_line !== false) {
                $this->write_header();
            }
        }
    }
    public function write(array $write_data)
    {
        if (substr(strval(key($write_data)), 0, 1) == ':') {
            if (isset($write_data[':feed_data'])) {
                $write_data = $write_data[':feed_data'];
            } else {
                return;
            }
        }
        if ($this->_first_write) {
            $this->open_output_file();
            if ($this->header_line !== false) {
                $this->write_header();
            }
        }
        $sheet = $this->spreadsheet->get_active_sheet();
        /**
         * @var $sheet Worksheet
         */
        foreach (array_keys($this->columns) as $idx => $column_name) {
            if (isset($write_data[$column_name])) {
                if (true) {
                    $sheet->get_cell_by_column_and_row($idx + 1, $this->row_counter + 1)->set_value_explicit($write_data[$column_name], \Php_Office\Php_Spreadsheet\Cell\Data_Type::TYPE_STRING);
                } else {
                    $sheet->set_cell_value_by_column_and_row($idx + 1, $this->row_counter + 1, $write_data[$column_name]);
                }
            } else {
                $sheet->set_cell_value_by_column_and_row($idx + 1, $this->row_counter + 1, '');
            }
        }
        $this->row_counter++;
        $this->_first_write = false;
    }
    public function close()
    {
        $writer = \Php_Office\Php_Spreadsheet\Io_Factory::create_writer($this->spreadsheet, $this->writer_type);
        $writer->save($this->filename);
    }
}