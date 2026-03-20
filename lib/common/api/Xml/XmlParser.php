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
namespace common\api\Xml;

/**
 * Responsible for parsing XML and returning a PHP object.
 */
class Xml_Parser
{
    /**
     * @var mixed
     */
    private $root_object;
    /**
     * @var array
     */
    private $current_item = [];
    private $name_num = 0;
    /**
     * Parse the passed XML
     *
     * @param object $rootObject
     * @param string $xml The xml string to parse.
     * @return mixed A PHP object
     */
    public function parse($root_object, $xml)
    {
        $this->root_object = $root_object;
        $this->current_item = [];
        $this->name_num = 0;
        $parser = xml_parser_create_ns('UTF-8', '@');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, 'startElement', 'endElement');
        xml_set_character_data_handler($parser, 'cdata');
        xml_parse($parser, $xml, true);
        xml_parser_free($parser);
        return $this->root_object;
    }
    /**
     * Handler for the parser that is called at the start of each XML element.
     *
     * @param resource $parser Reference to the XML parser calling the handler.
     * @param string $name The name of the element.
     * @param array $attributes Associative array of the element's attributes.
     */
    private function start_element($parser, $name, array $attributes)
    {
        $class = get_class($this->root_object);
        if (property_exists($class, $name)) {
            $this->current_item[] = $name;
        } elseif (count($this->current_item) > 0) {
            if ($name == 'item') {
                $this->name_num++;
                $name = $this->name_num;
            }
            $this->current_item[] = $name;
        }
    }
    /**
     * Handler for the parser that is called for character data.
     *
     * @param resource $parser Reference to the XML parser calling the handler.
     * @param string $cdata The character data.
     */
    private function cdata($parser, $cdata)
    {
        if (isset($this->current_item[0])) {
            $class = get_class($this->root_object);
            if (property_exists($class, $this->current_item[0])) {
                if (count($this->current_item) == 1) {
                    $this->root_object->{$this->current_item[0]} = $cdata;
                } else {
                    $deep =& $this->root_object->{$this->current_item[0]};
                    foreach ($this->current_item as $key => $value) {
                        if ($key == 0) {
                            continue;
                        }
                        $deep =& $deep[$value];
                    }
                    $deep .= $cdata;
                }
            }
        }
    }
    /**
     * Handler for the parser that is called at the end of each XML element.
     *
     * @param resource $parser Reference to the XML parser calling the handler.
     * @param string $name The name of the element.
     */
    private function end_element($parser, $name)
    {
        if (count($this->current_item) > 0) {
            array_pop($this->current_item);
        }
    }
}