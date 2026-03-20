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
namespace common\api\models\XML;

class Xm_Lto_Data_Parser extends Xm_Lto_Simple_Parser
{
    protected $configure_map = [];
    public function set_configure_map($map)
    {
        $this->configure_map = [];
        $config_data = isset($map['Data']) ? $map['Data'] : $map;
        foreach ($config_data as $active_model => $config_set) {
            $this->configure_map = $config_set;
            $this->configure_map['class'] = $active_model;
            break;
        }
    }
    /**
     * @return bool|IOData
     */
    public function read()
    {
        $xml_element = parent::read();
        if ($xml_element === false) {
            return false;
        }
        //echo '<pre style="background-color: #0b62a9">'; var_dump($xmlElement); echo '</pre>';
        if (is_object($xml_element) && $xml_element instanceof \Simple_Xml_Element) {
            $data = $this->make_io_data($xml_element);
            //echo '<pre>$data '; var_dump($data); echo '</pre>';
            //return $this->simpleXmlElementToArray($xmlElement);
        } else {
            $data = true;
        }
        return $data;
    }
    public function make_io_data(\Simple_Xml_Element $xml_element)
    {
        return $this->simple_xml_element_to_data($xml_element, $this->configure_map);
    }
    protected function simple_xml_element_to_data(\Simple_Xml_Element $xml_element, $level_configure)
    {
        $data = new Io_Data();
        $data->meta = $level_configure;
        $data->data = [];
        foreach ($xml_element->attributes() as $attribute_name => $attribute_value) {
            $result['@' . $attribute_name] = (string) $attribute_value;
        }
        $properties_remap = isset($data->meta['properties']) && is_array($data->meta['properties']) ? $data->meta['properties'] : [];
        $rename_properties = [];
        foreach ($properties_remap as $table_attribute => $xml_attribute) {
            if (is_string($xml_attribute)) {
                $rename_properties[$xml_attribute] = $table_attribute;
            } elseif (is_array($xml_attribute) && !empty($xml_attribute['rename'])) {
                $rename_properties[$xml_attribute['rename']] = $table_attribute;
            }
        }
        $collections = [];
        if (isset($data->meta['withRelated']) && is_array($data->meta['withRelated'])) {
            foreach ($data->meta['withRelated'] as $model_collection => $collection_config) {
                list($collection_xml_tag, $collection_xml_tag_item) = explode('>', $collection_config['xmlCollection'], 2);
                $collections[$collection_xml_tag] = array_merge(['itemTag' => $collection_xml_tag_item, 'modelCollection' => $model_collection], $collection_config);
            }
        }
        foreach ($xml_element->children() as $child) {
            /**
             * @var $child \SimpleXMLElement
             */
            $node_name = $child->get_name();
            if (isset($collections[$node_name])) {
                $collection_config = $collections[$node_name];
                if (!empty($collection_config['modelCollection'])) {
                    $node_name = $collection_config['modelCollection'];
                }
                if (!is_object($data->data[$node_name] ?? null)) {
                    $data->data[$node_name] = new Io_Data_Related();
                    $data->data[$node_name]->meta = $collection_config;
                }
                if (isset($child->{$collection_config['itemTag']})) {
                    $children_collection = $child->{$collection_config['itemTag']};
                    foreach ($children_collection as $children_element) {
                        $data->data[$node_name]->data[] = $this->simple_xml_element_to_data($children_element, $collection_config);
                    }
                }
                //die;
            } else {
                $child_value = (string) $child;
                if ($child->attributes()->count() > 0) {
                    foreach ($child->attributes() as $child_attr_name => $child_attr_value) {
                        if ($child_attr_name == 'type') {
                            $child_type = (string) $child_attr_value;
                            if ($child_type == 'nil') {
                                $child_value = null;
                            } else {
                                /*if ( isset($data->meta['properties'][$nodeName]) && is_array($data->meta['properties'][$nodeName]) ) {
                                
                                                                }else{
                                                                    $propMeta = [
                                                                        'class' => $childType,
                                                                        'table' => $attribute,
                                                                        'attribute' => $nodeName,
                                                                    ];
                                                                }
                                                                echo '<pre>'; var_dump($data->meta); echo '</pre>'; die;*/
                                $io_object = null;
                                if (isset($data->meta['properties'][$node_name]) && is_array($data->meta['properties'][$node_name]) && isset($data->meta['properties'][$node_name]['class'])) {
                                    $io_object = Io_Core::create_object($data->meta['properties'][$node_name]);
                                }
                                $child_value = Io_Core::construct_object_instance([$child_type, 'restoreFrom'], [$child, $io_object]);
                            }
                            break;
                        }
                        if ($child_attr_name == 'nil') {
                            $child_value = null;
                        }
                    }
                }
                if (isset($rename_properties[$node_name])) {
                    $node_name = $rename_properties[$node_name];
                }
                if (!is_object($child_value) && isset($data->meta['properties'][$node_name]) && is_array($data->meta['properties'][$node_name]) && isset($data->meta['properties'][$node_name]['class'])) {
                    $child_type = $data->meta['properties'][$node_name]['class'];
                    $io_object = Io_Core::create_object($data->meta['properties'][$node_name]);
                    if (is_object($io_object)) {
                        $child_value = Io_Core::construct_object_instance([$child_type, 'restoreFrom'], [$child, $io_object]);
                    }
                }
                $data->data[$node_name] = $child_value;
            }
        }
        return $data;
    }
}