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

class Io_Map extends Complex
{
    public $internal_id;
    public $external_id;
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        if (Io_Core::get()->is_local_project()) {
            $parent->add_attribute('internalId', $this->value);
            $external_id = Io_Core::get()->get_attribute_mapper()->external_id($this);
            if (is_numeric($external_id)) {
                $parent->add_attribute('externalId', $external_id);
            }
        } else {
            $parent->add_attribute('externalId', $this->value);
            $external_id = Io_Core::get()->get_attribute_mapper()->external_id($this);
            if (is_numeric($external_id)) {
                $parent->add_attribute('internalId', $external_id);
            }
        }
    }
    public static function restore_from(\Simple_Xml_Element $node, $obj)
    {
        if (!is_object($obj) || !$obj instanceof Complex) {
            $obj = new self();
        }
        $obj_properties = \Yii::get_object_vars($obj);
        foreach ($node->attributes() as $attr_name => $attr_value) {
            if (array_key_exists($attr_name, $obj_properties)) {
                $obj->{$attr_name} = strval($attr_value);
            }
        }
        if (empty($obj->internal_id) && empty($obj->external_id) && trim($node) != '') {
            // imported XML without type : <tag>value</tag>
            $obj->internal_id = trim($node);
        }
        if ($obj->internal_id) {
            $obj->internal_id = intval($obj->internal_id);
        }
        if ($obj->external_id) {
            $obj->external_id = intval($obj->external_id);
        }
        if (!Io_Core::get()->is_local_project()) {
            $external_id = $obj->external_id;
            $obj->external_id = $obj->internal_id;
            $obj->internal_id = $external_id;
        }
        //if ( is_numeric($map->internalId) ) {
        $obj->value = $obj->internal_id;
        //}else{
        //}
        return $obj;
    }
    public function is_map_valid()
    {
    }
    public function to_import_model()
    {
        if (!Io_Core::get()->is_local_project() && empty($this->internal_id) && !empty($this->external_id)) {
            $mapped_id = Io_Core::get()->get_attribute_mapper()->internal_id($this);
            if ($mapped_id) {
                $this->internal_id = $mapped_id;
                $this->value = $mapped_id;
            }
        }
        return $this->value;
    }
    public function after_import_model($value)
    {
        if (!empty($this->external_id) && !Io_Core::get()->is_local_project()) {
            Io_Core::get()->get_attribute_mapper()->map_ids($this, $value, $this->external_id);
        }
        //echo '<pre>afterImportModel '; var_dump($this->table, $this->attribute, $value); echo '</pre>';
    }
}