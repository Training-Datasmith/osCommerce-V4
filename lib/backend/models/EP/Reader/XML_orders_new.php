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

use yii\base\Base_Object;
class XML_orders_new extends Base_Object implements Reader_Interface
{
    public const MAX_LINE_LENGTH = 1000000;
    public $column_separator = 'auto';
    public $column_enclosure = '"';
    public $data_escape = '\\';
    public $line_separator = "\r\n";
    public $input_encoding = 'auto';
    public $without_header = false;
    private $tag;
    private $doc;
    public $filename;
    protected $file_handle;
    protected $file_header;
    protected $use_config = ['column_separator' => 'auto', 'column_enclosure' => '"', 'data_escape' => '\\', 'line_separator' => 'auto', 'input_encoding' => 'auto'];
    private $file_start_pointer = 0;
    private $file_end_pointer = 0;
    private $file_data_start_pointer;
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\Exception $ex) {
        }
    }
    public function current_position()
    {
        if ($this->file_handle) {
            return ftell($this->file_handle);
        }
        return 0;
    }
    public function get_progress()
    {
        $percent_done = min(100, $this->file_start_pointer / $this->file_end_pointer * 100);
        return number_format($percent_done, 1, '.', '');
    }
    public function set_data_position($position)
    {
        return false;
    }
    public function read_columns()
    {
        return ['name' => 'undefined', 'value' => 'null'];
    }
    public function read()
    {
        $data = false;
        if (!$this->file_handle) {
            $tmp_reader = new \Xml_Reader();
            $tmp_reader->open($this->filename);
            while ($tmp_reader->read() && $tmp_reader->name !== 'Order') {
            }
            do {
                $this->file_end_pointer++;
                $tmp_reader->next('Order');
            } while ($tmp_reader->name === 'Order');
            unset($tmp_reader);
            $this->file_handle = new \Xml_Reader();
            $this->file_handle->open($this->filename);
            $this->doc = new \Dom_Document();
            while ($this->file_handle->read() && $this->file_handle->name !== 'Order') {
            }
        } else {
            $this->file_handle->next('Order');
        }
        if ($this->file_handle->name === 'Order') {
            $data = simplexml_import_dom($this->doc->import_node($this->file_handle->expand(), true));
            $data = $this->simple_xml2array($data);
            $data['row'] = $data;
            $this->file_start_pointer++;
            //while ($this->file_handle->read() && $this->file_handle->name !== 'Order');
        }
        return $data;
    }
    public function simple_xml2array($xml)
    {
        $array = (array) $xml;
        //recursive Parser
        foreach ($array as $key => $value) {
            if (is_object($value) || is_array($value)) {
                $array[$key] = $this->simple_xml2array($value);
            }
        }
        if (empty($array)) {
            return '';
        }
        return $array;
    }
}