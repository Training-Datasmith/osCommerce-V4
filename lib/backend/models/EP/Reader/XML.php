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
class XML implements Reader_Interface
{
    protected $file_header;
    private $file_start_pointer = 0;
    private $file_data_start_pointer;
    public $filename;
    protected $file_handle;
    public $root_tag = 'data';
    public $rows_tag = 'records';
    public $row_tag = 'record';
    public $import_data = 'array';
    public $parser;
    protected $current_tag_stack = [];
    protected $detected_indexed = [];
    protected $last_closed_tag = '';
    protected $cdata_collect = false;
    protected $collected_path_data = false;
    protected $cut_count_from_collect_path = 0;
    protected $read_out_queue = [];
    // {{ SimpleXML
    /**
     * @var \SimpleXMLElement
     */
    protected $root;
    /**
     * @var \SimpleXMLElement
     */
    protected $current_node;
    protected $is_nodes_collect = true;
    protected $collect_path = [];
    // collect only selected "xpath"
    protected $current_xpath_array = [];
    // }} SimpleXML
    protected function open_file()
    {
        $this->file_header = null;
        $this->file_start_pointer = 0;
        $this->file_handle = @fopen($this->filename, 'r');
        if (!$this->file_handle) {
            throw new Exception('Can\'t open file', 20);
        }
        $this->current_tag_stack = [];
        $this->parser = xml_parser_create('utf-8');
        xml_set_object($this->parser, $this);
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
        if ($this->import_data == 'SimpleXml') {
            xml_set_element_handler($this->parser, 'sx_tag_open', 'sx_tag_close');
            xml_set_character_data_handler($this->parser, 'sx_cdata');
            if (empty($this->root_tag)) {
                $collect_path = '/' . $this->rows_tag . '/' . $this->row_tag;
            } else {
                $collect_path = '/' . $this->root_tag . '/' . $this->rows_tag . '/' . $this->row_tag;
            }
            $this->collect_path[$collect_path] = $collect_path;
        } else {
            xml_set_element_handler($this->parser, 'tag_open', 'tag_close');
            xml_set_character_data_handler($this->parser, 'cdata');
            if (empty($this->root_tag)) {
                $this->collect_path[$this->rows_tag . '.' . $this->row_tag] = true;
            } else {
                $this->collect_path[$this->root_tag . '.' . $this->rows_tag . '.' . $this->row_tag] = true;
            }
        }
        $this->read_columns();
    }
    public function read_columns()
    {
        return [];
    }
    public function read()
    {
        if (!$this->file_handle) {
            $this->open_file();
        }
        if ($this->import_data == 'SimpleXml') {
            if (count($this->read_out_queue) > 0) {
                while ($item = array_shift($this->read_out_queue)) {
                    if (count($this->collect_path) > 0) {
                        if (isset($this->collect_path[$item['xpath']])) {
                            return $item['node'];
                        }
                    } else {
                        return $item['node'];
                    }
                }
            }
        } else if (count($this->read_out_queue) > 0) {
            $data = array_shift($this->read_out_queue);
            if (count($this->read_out_queue) == 0) {
                unset($this->read_out_queue);
                $this->read_out_queue = [];
            }
            return $data;
        } else {
            unset($this->read_out_queue);
            $this->read_out_queue = [];
        }
        while ($data = fread($this->file_handle, 4096 * 1)) {
            if (!xml_parse($this->parser, $data, feof($this->file_handle))) {
                $fmt = new \yii\i18n\Formatter();
                $memusage = $fmt->as_short_size(memory_get_usage(true), 3);
                $mempeakusage = $fmt->as_short_size(memory_get_peak_usage(true), 3);
                throw new Exception("XML Error: memusage {$memusage} mempeakusage {$mempeakusage} " . xml_error_string(xml_get_error_code($this->parser)) . ' at line ' . xml_get_current_line_number($this->parser) . '');
            }
            if ($this->import_data == 'SimpleXml') {
                if (count($this->read_out_queue) > 0) {
                    while ($item = array_shift($this->read_out_queue)) {
                        if (count($this->collect_path) > 0) {
                            if (isset($this->collect_path[$item['xpath']])) {
                                return $item['node'];
                            }
                        } else {
                            return $item['node'];
                        }
                    }
                }
            } else if (count($this->read_out_queue) > 0) {
                $data = array_shift($this->read_out_queue);
                if (count($this->read_out_queue) == 0) {
                    unset($this->read_out_queue);
                    $this->read_out_queue = [];
                }
                return $data;
            }
        }
        return false;
    }
    public function current_position()
    {
        if ($this->file_handle) {
            return ftell($this->file_handle);
        }
        return 0;
    }
    public function set_data_position($position)
    {
        // TODO: Implement setDataPosition() method.
    }
    public function get_progress()
    {
        $file_position = $this->current_position();
        if ($this->file_handle) {
            $fstat = fstat($this->file_handle);
            $percent_done = min(100, $file_position / max(1, $fstat['size']) * 100);
        } else {
            $percent_done = min(100, $file_position / filesize($this->filename) * 100);
        }
        return number_format($percent_done, 1, '.', '');
    }
    public function tag_open($parser, $tag, $attributes)
    {
        // {{ indexed arrays
        if ($this->last_closed_tag == $tag) {
            $indexed_path = substr(implode('.', $this->current_tag_stack) . '.' . $tag, $this->cut_count_from_collect_path);
            if (!isset($this->detected_indexed[$indexed_path])) {
                $this->detected_indexed[$indexed_path] = 0;
                foreach (preg_grep('/^' . preg_quote($indexed_path) . '/', array_keys((array) $this->collected_path_data)) as $rename_key) {
                    $this->collected_path_data[str_replace($indexed_path, $indexed_path . '.' . $this->detected_indexed[$indexed_path], $rename_key)] = $this->collected_path_data[$rename_key];
                    unset($this->collected_path_data[$rename_key]);
                }
            }
            $this->detected_indexed[$indexed_path]++;
        }
        // }} indexed arrays
        $this->current_tag_stack[] = $tag;
        $started_path = implode('.', $this->current_tag_stack);
        if (isset($this->collect_path[$started_path])) {
            unset($this->collected_path_data);
            $this->collected_path_data = [];
            $this->cut_count_from_collect_path = strlen($started_path) + 1;
        }
        $this->cdata_collect = '';
        if (count($attributes) > 0 && is_array($this->collected_path_data)) {
            foreach ($attributes as $attribute_name => $attribute_value) {
                $this->collected_path_data[substr($started_path . '.@' . $attribute_name, $this->cut_count_from_collect_path)] = $attribute_value;
            }
        }
    }
    public function cdata($parser, $cdata)
    {
        if (!empty($cdata) && $this->cdata_collect !== false) {
            $this->cdata_collect .= $cdata;
        }
    }
    public function tag_close($parser, $tag)
    {
        $this->last_closed_tag = $tag;
        $close_path = implode('.', $this->current_tag_stack);
        if ($this->cdata_collect !== false) {
            if (is_array($this->collected_path_data)) {
                $data_key = substr($close_path, $this->cut_count_from_collect_path);
                foreach ($this->detected_indexed as $indexed_key => $index_counter) {
                    if (strpos($data_key, $indexed_key) !== 0) {
                        continue;
                    }
                    $data_key = $indexed_key . '.' . $index_counter . substr($data_key, strlen($indexed_key));
                }
                $this->collected_path_data[$data_key] = $this->cdata_collect;
            }
        }
        if (isset($this->collect_path[$close_path])) {
            $this->last_closed_tag = '';
            $this->detected_indexed = [];
            $this->read_out_queue[] = \backend\models\EP\Array_Transform::convert_flat_to_multi_dimensional($this->collected_path_data);
            unset($this->collected_path_data);
            $this->collected_path_data = false;
        }
        unset($close_path);
        array_pop($this->current_tag_stack);
        $this->cdata_collect = false;
    }
    protected function sx_tag_open($parser, $tag, $attributes)
    {
        $this->current_xpath_array[] = $tag;
        if (!$this->is_nodes_collect) {
            return;
        }
        if (is_null($this->root)) {
            $element = new \Simple_Xml_Element('<' . $tag . '></' . $tag . '>');
            $element->register_x_path_namespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $this->root = $element;
            $this->current_node = $element;
        } else {
            $this->current_node = $this->current_node->add_child($tag);
            if (is_array($attributes) && count($attributes) > 0) {
                foreach ($attributes as $attr_name => $attr_value) {
                    $ns = null;
                    //if ( strpos($attrName,':')!==false ) {
                    //list($ns, /*$attrName*/) = explode(':',$attrName,2);
                    //}
                    //echo '<pre>'; var_dump($attrName, $attrValue, $ns); echo '</pre>';
                    $this->current_node->add_attribute($attr_name, $attr_value, $ns);
                }
            }
        }
        $this->cdata_collect = '';
    }
    protected function sx_cdata($parser, $cdata)
    {
        if (!$this->is_nodes_collect) {
            return;
        }
        if ($this->cdata_collect !== false) {
            $this->cdata_collect .= $cdata;
        }
    }
    protected function sx_tag_close($parser, $tag)
    {
        $close_tag_path = $this->current_xpath_array;
        $close_tag_x_path = '/' . implode('/', $close_tag_path);
        // put collected CDATA
        if ($this->cdata_collect !== false) {
            //$this->currentNode[0] = $this->cdataCollect; //??
            $this->current_node[0] = trim($this->cdata_collect);
            $this->cdata_collect = false;
        }
        $closed_node = $this->current_node;
        // shift current to parent
        $parent_node_array = $this->current_node->xpath('..');
        if (count($parent_node_array) == 1) {
            $this->current_node = $parent_node_array[0];
        }
        // fill readOutQueue
        if (!empty($this->collect_path) && isset($this->collect_path[$close_tag_x_path])) {
            $this->read_out_queue[] = ['xpath' => '/' . implode('/', $this->current_xpath_array), 'node' => $closed_node];
            $closed_dom_node = dom_import_simplexml($closed_node);
            $closed_dom_node->parent_node->remove_child($closed_dom_node);
        } elseif (empty($this->collect_path) && count($close_tag_path) == 2) {
            // collect every 1st level node from root
            $this->read_out_queue[] = ['xpath' => '/' . implode('/', $this->current_xpath_array), 'node' => $closed_node];
            $closed_dom_node = dom_import_simplexml($closed_node);
            $closed_dom_node->parent_node->remove_child($closed_dom_node);
        }
        array_pop($this->current_xpath_array);
    }
}