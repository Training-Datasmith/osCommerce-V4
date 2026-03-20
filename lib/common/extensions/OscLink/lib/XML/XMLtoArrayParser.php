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

class Xm_Lto_Array_Parser extends Xm_Lto_Simple_Parser
{
    public function read()
    {
        $xml_element = parent::read();
        if (is_object($xml_element) && $xml_element instanceof \Simple_Xml_Element) {
            return $this->simple_xml_element_to_array($xml_element);
        }
        return $xml_element;
    }
    protected function simple_xml_element_to_array(\Simple_Xml_Element $element)
    {
        $result = [];
        foreach ($element->attributes() as $attribute_name => $attribute_value) {
            $result['@' . $attribute_name] = (string) $attribute_value;
        }
        foreach ($element->children() as $child) {
            /**
             * @var $child \SimpleXMLElement
             */
            $node_name = $child->get_name();
            if ($child->count() > 0) {
                if (isset($result[$node_name])) {
                    if (\yii\helpers\Array_Helper::is_associative($result[$node_name])) {
                        $result[$node_name] = [$result[$node_name]];
                    }
                    $result[$node_name][] = $this->simple_xml_element_to_array($child);
                } else {
                    $result[$node_name] = $this->simple_xml_element_to_array($child);
                }
            } else {
                $child_value = (string) $child;
                if ($child->attributes()) {
                    foreach ($child->attributes() as $child_attr_name => $child_attr_value) {
                        if ($child_attr_name == 'nil') {
                            $child_value = null;
                        }
                    }
                }
                $result[$node_name] = $child_value;
            }
        }
        return $result;
    }
}