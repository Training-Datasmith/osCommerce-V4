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

class Io_Data
{
    public $export_pk = [];
    public $meta = [];
    public $data = [];
    public static function from_array($data_array)
    {
        $obj = new self();
        $obj->data = $data_array;
        return $obj;
    }
    public function get_attachment_list()
    {
        $attachment_objects = [];
        foreach ($this->data as $data_value) {
            if (!is_object($data_value)) {
                continue;
            }
            if ($data_value instanceof Io_Attachment) {
                $attachment_objects[] = $data_value;
            } elseif ($data_value instanceof Io_Data) {
                $attachment_objects = array_merge($attachment_objects, $data_value->get_attachment_list());
            }
        }
        return $attachment_objects;
    }
    public function is_importable($process_model)
    {
        $is_valid = true;
        /*foreach ( $this->data as $name=>$value ) {
              if ( is_object($value) && $value instanceof IOMap ) {
                  $value->isMapValid();
                  echo '<pre>'; var_dump($value); echo '</pre>'; die;
              }
              //IOMap
          }*/
        return $is_valid;
    }
    public static function serialize_to_simple_xml(Io_Data $data, $record_tag, $root_element = null)
    {
        if (is_object($record_tag) && $record_tag instanceof \Simple_Xml_Element) {
            $element = $record_tag;
        } else {
            $element = new \Simple_Xml_Element("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$record_tag} />");
        }
        foreach ($data->data as $name => $val) {
            if (false && in_array($name, $data->export_pk)) {
                if (is_object($val) && $val instanceof Complex) {
                    $element->add_attribute('type', Helper::get_class_short_name(get_class($val)));
                    $val->serialize_to($element);
                    //$element->addAttribute($name, $val);
                } else {
                    $element->add_attribute($name, $val);
                }
                continue;
            }
            if (is_array($val)) {
                //echo '<pre>'; var_dump($name, $val); echo '</pre>'; die;
            } elseif (is_object($val) && $val instanceof Io_Data) {
                $root_tag = '';
                if (strpos($val->meta['xmlCollection'], '>') !== false) {
                    list($root_tag, $record_tag) = explode('>', $val->meta['xmlCollection'], 2);
                } else {
                    $record_tag = $val->meta['xmlCollection'];
                }
                $child_element = $element->add_child($root_tag);
                foreach ($val->data as $nested_data) {
                    $child_collection_element = $child_element->add_child($record_tag);
                    static::serialize_to_simple_xml($nested_data, $child_collection_element, $child_element);
                }
            } elseif (is_object($val)) {
                if ($val instanceof Complex) {
                    $complex_object = $element->add_child($name);
                    $complex_object->add_attribute('type', Helper::get_class_short_name(get_class($val)));
                    $val->serialize_to($complex_object);
                }
            } else if (is_null($val)) {
                $element->add_child($name)->add_attribute('type', 'nil');
            } else {
                //$element->{$name} = $val;
                $val_striped_invalid = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $val);
                $element->{$name} = $val_striped_invalid;
            }
        }
        if ($root_element && is_object($root_element)) {
            return null;
        }
        return $element;
    }
}