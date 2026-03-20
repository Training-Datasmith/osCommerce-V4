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
namespace Osc_Link\XML;

class Xm_Lto_Simple_Parser
{
    protected $filename = '';
    protected $file_handle;
    protected $parser;
    /**
     * @var \SimpleXMLElement
     */
    protected $root;
    /**
     * @var \SimpleXMLElement
     */
    protected $current_node;
    protected $cdata_collect = false;
    protected $is_nodes_collect = true;
    protected $collect_path = [];
    // collect only selected "xpath"
    protected $read_out_queue = [];
    // collect elements here
    protected $current_xpath_array = [];
    protected function init_parser()
    {
        $this->parser = xml_parser_create('utf-8');
        xml_set_object($this->parser, $this);
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
        xml_set_element_handler($this->parser, 'tag_open', 'tag_close');
        xml_set_character_data_handler($this->parser, 'cdata');
        $this->root = null;
        $this->current_node = null;
        $this->cdata_collect = false;
    }
    public function set_collect_path($collect_path)
    {
        $this->collect_path[$collect_path] = $collect_path;
    }
    public function parse_file($filename)
    {
        $this->init_parser();
        $this->filename = $filename;
        $this->file_handle = @fopen($this->filename, 'r');
        if (!$this->file_handle) {
            throw new \Exception('Can\'t open file', 20);
        }
    }
    public function parse_string($string)
    {
        $this->init_parser();
        if (!xml_parse($this->parser, $string, true)) {
            throw new \Exception('XML Error: ' . xml_error_string(xml_get_error_code($this->parser)) . ' at line ' . xml_get_current_line_number($this->parser) . '');
        }
    }
    /**
     * @return bool|\SimpleXMLElement
     * @throws \Exception
     */
    public function read()
    {
        // fill out from file
        if ($this->file_handle && count($this->read_out_queue) == 0) {
            while ($data = fread($this->file_handle, 4096 * 1)) {
                if (!xml_parse($this->parser, $data, feof($this->file_handle))) {
                    throw new \Exception('XML Error: ' . xml_error_string(xml_get_error_code($this->parser)) . ' at line ' . xml_get_current_line_number($this->parser) . '');
                }
            }
            if (feof($this->file_handle)) {
                fclose($this->file_handle);
                $this->file_handle = false;
            }
        }
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
        return false;
    }
    protected function tag_open($parser, $tag, $attributes)
    {
        $this->current_xpath_array[] = $tag;
        if (!$this->is_nodes_collect) {
            return;
        }
        if (is_null($this->root)) {
            $element = new \Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8"?><' . $tag . '></' . $tag . '>');
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
    protected function cdata($parser, $cdata)
    {
        if (!$this->is_nodes_collect) {
            return;
        }
        if ($this->cdata_collect !== false) {
            $this->cdata_collect .= $cdata;
        }
    }
    protected function tag_close($parser, $tag)
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