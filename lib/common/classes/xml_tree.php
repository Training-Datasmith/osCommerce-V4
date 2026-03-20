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
namespace common\classes;

class xml_tree
{
    public $parser;
    public $active_node;
    public $encoding;
    public function __construct($encoding = '')
    {
        $this->encoding = $encoding;
    }
    public function &_create_node($name)
    {
        //Standardmäßig wird ein xml_node Objekt erzeugt
        return new xml_node();
    }
    public function _start_element($parser, $name, $attrs)
    {
        /*
         Neues Element gefunden:
         1. Neues Node Objekt erzeugen und Werte zuweisen
         2. Objekt als Subitem des Active Node einsetzen, Active Node als Parent des Neuen Objektes eintragen
         3. Neues Objekt als Active Node festlegen
        */
        //1.
        /*
         if ($name != ''){
         $this->active_node[$name]
         }
        */
        $node =& $this->_create_node($name);
        $node->name = $name;
        $node->attributes = $attrs;
        $node->depth = $this->active_node->depth + 1;
        //2.
        $node->parent_node =& $this->active_node;
        $this->active_node->subitems[] =& $node;
        //3.
        $this->active_node =& $node;
    }
    public function _end_element($parser, $name)
    {
        /*
         Elementende:
         Parent des Aktiven Elements wieder zum Aktiven Element machen
        */
        $this->active_node =& $this->active_node->parent_node;
    }
    public function _character_data($parser, $data)
    {
        /*
         Mittelstück:
         Inhalt zum aktiven Knoten hinzufügen:
        */
        $this->active_node->content .= $data;
    }
    public function &create_tree($xml_data)
    {
        $this->parser = xml_parser_create($this->encoding);
        xml_set_object($this->parser, $this);
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler($this->parser, '_startElement', '_endElement');
        xml_set_character_data_handler($this->parser, '_characterData');
        //Erzeuge Root Objekt und setze aktive Node darauf:
        //$root = array();
        $root = new xml_node();
        $this->active_node =& $root;
        //Parse Dokument:
        if (!xml_parse($this->parser, $xml_data, sizeof($xml_data))) {
            unset($root);
        }
        xml_parser_free($this->parser);
        return $root;
    }
}
class xml_node
{
    public $parent_node;
    public $name;
    public $attributes;
    public $content;
    public $depth;
    public $subitems;
    public function __construct()
    {
        $this->parent_node = null;
        $this->subitems = [];
    }
    public function itemcount($name_filter = '', $recursive = true)
    {
        $count = 0;
        $name_filter = trim($name_filter);
        for ($i = 0; $i < count($this->subitems); $i++) {
            if ($name_filter == '' || $this->subitems[$i]->name == $name_filter) {
                $count++;
            }
            if ($recursive == true) {
                $count += $this->subitems[$i]->itemcount($recursive, $name_filter);
            }
        }
        return $count;
    }
    public function path_itemcount($path, $name_filter = '', $recursive = true)
    {
        $count = 0;
        $node =& $this->item($path);
        if ($node != null) {
            $count = $node->itemcount($name_filter, $recursive);
        }
        return $count;
    }
    public function &item($name, $offset = 0)
    {
        //Weiterhin kann auch nach einem bestimmten Attribut gefiltert werden Syntax item1/item2.attribut=wert/item3
        $node = null;
        $counter = 0;
        $name = trim($name);
        $pos = strpos($name, '/');
        if ($pos !== false) {
            $subpath = substr($name, $pos + 1);
            $name = substr($name, 0, $pos);
        }
        //Filtern nach einem evtl. vorhandenem Attribut
        $pos_attr = strpos($name, '.');
        if ($pos_attr !== false) {
            $attr_filter = substr($name, $pos_attr + 1);
            $name = substr($name, 0, $pos_attr);
            //Filter String weiter aufteilen
            $pos_filter = strpos($attr_filter, '=');
            $value = substr($attr_filter, $pos_filter + 1);
            $attrib = substr($attr_filter, 0, $pos_filter);
        }
        for ($i = 0; $i < count($this->subitems); $i++) {
            if ($name == $this->subitems[$i]->name) {
                if ($pos_attr === false || $this->subitems[$i]->attributes[$attrib] == $value) {
                    //Filter nach Attribut
                    if ($counter == $offset || $pos != false) {
                        //Wenn ein Subpfad gefunden wurde, dann reiche Offset weiter...
                        $node =& $this->subitems[$i];
                        if ($pos !== false) {
                            //Wenn Pfadangabe gefunden, dann suche mit dem Rest vom Pfad im Unterobjekt
                            $node =& $node->item($subpath, $offset);
                        }
                        break;
                        //Item gefunden, Schleife verlassen...
                    } else {
                        $counter++;
                    }
                }
            }
        }
        return $node;
    }
    public function print_structure()
    {
        for ($i = 0; $i < count($this->subitems); $i++) {
            $prefix = str_repeat('.&nbsp;.&nbsp;', $this->subitems[$i]->depth - 1);
            echo $prefix . '<b>' . $this->subitems[$i]->name . '</b>: ' . $this->subitems[$i]->content . '<br>';
            $ausgabe = '';
            if (is_array($this->subitems[$i]->attributes)) {
                foreach ($this->subitems[$i]->attributes as $key => $val) {
                    $ausgabe .= "{$key} = '{$val}', ";
                }
            }
            $ausgabe = substr($ausgabe, 0, strlen($ausgabe) - 2);
            if (strlen($ausgabe) > 2) {
                echo "{$prefix} Attributes: ({$ausgabe})<br>";
            }
            $this->subitems[$i]->print_structure();
        }
    }
    public function get_structure(&$result)
    {
        //$result = array();
        if (!is_array($result)) {
            $result = [];
        }
        $cur_level = $this->depth;
        for ($i = 0; $i < count($this->subitems); $i++) {
            //$prefix = str_repeat(".&nbsp;.&nbsp;",$this->subitems[$i]->depth-1);
            //echo $prefix."<b>".$this->subitems[$i]->name."</b>: ".$this->subitems[$i]->content."<br>";
            $result[$this->subitems[$i]->name] = [];
            if (trim($this->subitems[$i]->content) != '') {
                $result[$this->subitems[$i]->name]['text'] = $this->subitems[$i]->content;
            }
            $this->subitems[$i]->get_structure($result[$this->subitems[$i]->name]);
        }
    }
}