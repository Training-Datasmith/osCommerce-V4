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

use backend\models\EP\Exception;
use yii\base\Base_Object;
class CSV extends Base_Object implements Writer_Interface
{
    public $column_separator = "\t";
    public $line_separator = "\r\n";
    public $output_encoding = 'UTF-16LE';
    public $utf_bom = true;
    public $quote_all = false;
    public $header_line = true;
    public $filename;
    protected $file_handle;
    protected $_first_write = true;
    protected $columns = [];
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\Exception $ex) {
        }
    }
    protected function open_output_file()
    {
        if (strpos($this->filename, 'php://') === false) {
            if (!is_dir(dirname($this->filename))) {
                try {
                    \yii\helpers\File_Helper::create_directory(dirname($this->filename), 0777, true);
                } catch (\yii\base\Exception $ex) {
                }
            }
        }
        $this->file_handle = @fopen($this->filename, 'w');
        if (!$this->file_handle) {
            throw new Exception('Can\'t open file', 21);
        }
        // write BOM
        $encoding_bom = $this->get_output_encoding_bom();
        if ($encoding_bom) {
            fwrite($this->file_handle, $encoding_bom);
        }
    }
    protected function write_header()
    {
        $header = array_values($this->columns);
        $data = array_map([$this, 'quoteText'], $header);
        $line = implode($this->column_separator, $data) . $this->line_separator;
        if ($this->output_encoding == 'UTF-8') {
            fwrite($this->file_handle, $line);
        } else {
            fwrite($this->file_handle, mb_convert_encoding($line, $this->output_encoding, 'UTF-8'));
        }
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
        $data = [];
        foreach (array_keys($this->columns) as $column_name) {
            if (isset($write_data[$column_name])) {
                $data[$column_name] = $this->quote_text($write_data[$column_name]);
            } else {
                $data[$column_name] = '';
            }
        }
        $line = implode($this->column_separator, $data) . $this->line_separator;
        if ($this->output_encoding == 'UTF-8') {
            fwrite($this->file_handle, $line);
        } else {
            fwrite($this->file_handle, mb_convert_encoding($line, $this->output_encoding, 'UTF-8'));
        }
        fflush($this->file_handle);
        $this->_first_write = false;
    }
    public function close()
    {
        if ($this->file_handle && strpos($this->filename, 'php://') === false) {
            fclose($this->file_handle);
        }
        $this->file_handle = null;
    }
    protected function quote_text($string)
    {
        if ((empty($string) || is_numeric($string)) && !$this->quote_all) {
            return $string;
        }
        if ($this->quote_all || strpos($string, $this->column_separator) !== false || strpos($string, '"') !== false || strpos($string, "\n") !== false || strpos($string, "\r") !== false) {
            $string = '"' . str_replace('"', '""', $string) . '"';
        }
        $string = str_replace("\t", '\t', $string);
        return $string;
    }
    private function get_utf_bom_map()
    {
        $UTF_BOM = ['UTF-32BE' => chr(0x0) . chr(0x0) . chr(0xfe) . chr(0xff), 'UTF-32LE' => chr(0xff) . chr(0xfe) . chr(0x0) . chr(0x0), 'UTF-16BE' => chr(0xfe) . chr(0xff), 'UTF-16LE' => chr(0xff) . chr(0xfe), 'UTF-8' => chr(0xef) . chr(0xbb) . chr(0xbf)];
        return $UTF_BOM;
    }
    private function get_output_encoding_bom()
    {
        if (!$this->utf_bom) {
            return '';
        }
        $utf_map = $this->get_utf_bom_map();
        if (isset($utf_map[$this->output_encoding])) {
            return $utf_map[$this->output_encoding];
        }
        return '';
    }
}