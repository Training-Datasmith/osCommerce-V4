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

use backend\models\EP\Array_Transform;
use backend\models\EP\Exception;
use Dom_Document;
use Dom_Element;
use Dom_Text;
use Simple_Xml_Element;
use yii\base\Arrayable;
use yii\helpers\String_Helper;
class XML implements Writer_Interface
{
    public $filename;
    protected $file_handle;
    protected $_first_write = true;
    protected $use_traversable_as_array = true;
    public $Header = [];
    public $header = [];
    public $root_tag = 'data';
    public $rows_tag = 'records';
    public $row_tag = 'record';
    protected $columns = [];
    protected $columns_multi = [];
    public function set_columns(array $columns)
    {
        $this->columns = $columns;
    }
    protected function cut_selected_columns($data)
    {
        $new_data = [];
        $flat_data = Array_Transform::convert_multi_dimensional_to_flat($data);
        foreach (array_keys($this->columns) as $need_path) {
            if (strpos($need_path, '*') !== false) {
                $reg_exp = str_replace('.', '\.', $need_path);
                $reg_exp = str_replace('*', '[^\.]+', $reg_exp);
                foreach (preg_grep('#' . $reg_exp . '#', array_keys($flat_data)) as $need_key) {
                    $new_data[$need_key] = $flat_data[$need_key];
                }
            } else if (array_key_exists($need_path, $flat_data)) {
                $new_data[$need_path] = $flat_data[$need_path];
            }
        }
        $new_data = Array_Transform::convert_flat_to_multi_dimensional($new_data);
        return $new_data;
    }
    public function write(array $write_data)
    {
        if (substr(strval(key($write_data)), 0, 1) == ':') {
            if (isset($write_data[':xmlConfig'])) {
                if (is_array($write_data[':xmlConfig'])) {
                    foreach ($write_data[':xmlConfig'] as $conf_key => $conf_value) {
                        if (isset($this->{$conf_key})) {
                            $this->{$conf_key} = $conf_value;
                        }
                    }
                }
            }
            if (isset($write_data[':feed_data'])) {
                $write_data = $write_data[':feed_data'];
            } else {
                return;
            }
        }
        if ($this->_first_write) {
            $this->_first_write = false;
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
            fwrite($this->file_handle, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
            fwrite($this->file_handle, '<' . $this->root_tag . '>' . "\n");
            if (!empty($this->header)) {
                fwrite($this->file_handle, $this->array2xml($this->header, 'header') . "\n");
            } elseif (!empty($this->Header)) {
                fwrite($this->file_handle, $this->array2xml($this->Header, 'Header') . "\n");
            }
            fwrite($this->file_handle, '<' . $this->rows_tag . '>' . "\n");
        }
        if (substr(strval(key($write_data)), 0, 1) == ':') {
            if (isset($write_data[':feed_data'])) {
                $write_data = $write_data[':feed_data'];
            } else {
                return;
            }
        }
        if (count($write_data) == 1 && is_object($write_data[0]) && $write_data[0] instanceof Dom_Document) {
            fwrite($this->file_handle, preg_replace('#<\?xml.*\?>#', '', $write_data[0]->save_xml()) . "\n");
        } elseif (count($write_data) == 1 && is_object($write_data[0]) && $write_data[0] instanceof Simple_Xml_Element) {
            $xml = $write_data[0]->save_xml();
            $head_pos = strpos($xml, "?>\n");
            if ($head_pos !== false) {
                $xml = substr($xml, $head_pos + 3);
            } else {
                $head_pos = strpos($xml, '?>');
                if ($head_pos !== false) {
                    $xml = substr($xml, $head_pos + 2);
                }
            }
            fwrite($this->file_handle, $xml);
        } else {
            $write_data = $this->cut_selected_columns($write_data);
            fwrite($this->file_handle, $this->array2xml($write_data) . "\n");
        }
        fflush($this->file_handle);
    }
    public function close()
    {
        if ($this->file_handle) {
            fwrite($this->file_handle, '</' . $this->rows_tag . '>' . "\n");
            fwrite($this->file_handle, '</' . $this->root_tag . '>' . "\n");
            if (strpos($this->filename, 'php://') === false) {
                fclose($this->file_handle);
                $this->file_handle = null;
            }
        }
    }
    protected function array2xml($array, $tag = '')
    {
        $dom = new Dom_Document('1.0', 'UTF-8');
        $root = new Dom_Element(empty($tag) ? $this->row_tag : $tag);
        $dom->append_child($root);
        $this->build_xml($root, $array);
        return $dom->save_xml($root);
    }
    /**
     * @param DOMElement $element
     * @param mixed $data
     */
    protected function build_xml($element, $data, $numeric_key_format = 'item%s')
    {
        if (is_array($data) || $data instanceof \Traversable && $this->use_traversable_as_array && !$data instanceof Arrayable) {
            foreach ($data as $name => $value) {
                $item_tag = $name;
                if (is_int($name)) {
                    $item_tag = sprintf($numeric_key_format, $name);
                }
                $item_tag = preg_replace('/[^\w|\d|:|_|\.|-]/i', '_', $item_tag);
                if (is_int($name) && is_object($value)) {
                    $this->build_xml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    if (empty($item_tag)) {
                        continue;
                    }
                    $child = new Dom_Element($item_tag);
                    $element->append_child($child);
                    if (substr($name, -1) == 's' && strlen($name) > 2) {
                        $this->build_xml($child, $value, substr($name, 0, -1));
                    } else {
                        $this->build_xml($child, $value);
                    }
                } else {
                    if (empty($item_tag)) {
                        continue;
                    }
                    $child = new Dom_Element($item_tag);
                    $element->append_child($child);
                    $child->append_child(new Dom_Text((string) $value));
                }
            }
        } elseif (is_object($data)) {
            $child = new Dom_Element(String_Helper::basename(get_class($data)));
            $element->append_child($child);
            if ($data instanceof Arrayable) {
                $this->build_xml($child, $data->to_array());
            } else {
                $array = [];
                foreach ($data as $name => $value) {
                    $array[$name] = $value;
                }
                $this->build_xml($child, $array);
            }
        } else {
            $element->append_child(new Dom_Text((string) $data));
        }
    }
}