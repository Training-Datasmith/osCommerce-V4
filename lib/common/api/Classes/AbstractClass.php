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
namespace common\api\Classes;

abstract class Abstract_Class
{
    protected $message_array = [];
    /**
     * Returns a object property names
     * @return array
     */
    private function get_public_properties()
    {
        static $properties;
        $class_name = get_class($this);
        if (!isset($properties) or !is_array($properties) or !isset($properties[$class_name])) {
            $properties = is_array($properties) ? $properties : [];
            foreach ((new \Reflection_Object($this))->get_properties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $properties[$class_name][] = $property->name;
            }
        }
        return $properties[$class_name];
    }
    /**
     * Returns a value indicating whether a property is defined.
     * @param string $name
     * @return bool
     */
    public function has_property($name)
    {
        return property_exists($this, $name);
    }
    /**
     * Returns a value indicating whether a method is defined.
     * @param string $name
     * @return bool
     */
    public function has_method($name)
    {
        return method_exists($this, $name);
    }
    /**
     * Clear values of an object property.
     * @return $this
     */
    public function clear()
    {
        foreach ($this->get_public_properties() as $property) {
            if (preg_match('/(Array$)|(Record$)/', $property)) {
                $this->{$property} = [];
            } else {
                $this->{$property} = 0;
            }
        }
        return $this;
    }
    /**
     * Returns the value of an object property.
     * @param string $propertyName
     * @return mixed
     */
    public function get($property_name = null)
    {
        $response = null;
        if (is_null($property_name)) {
            $response = [];
            foreach ($this->get_public_properties() as $property) {
                if ($this->has_property($property)) {
                    $response[$property] = $this->{$property};
                }
            }
        } elseif ($this->has_property($property_name)) {
            $response = $this->{$property_name};
        }
        return $response;
    }
    /**
     * Sets value of an object property.
     * @param string $propertyValue
     * @param string $propertyName
     * @param bool $add
     * @return $this
     */
    public function set($property_value, $property_name = null, $is_add = false)
    {
        if (is_null($property_name)) {
            foreach ($this->get_public_properties() as $property) {
                if (isset($property_value[$property])) {
                    $this->set($property_value[$property], $property);
                }
            }
        } elseif ($this->has_property($property_name)) {
            if (preg_match('/Array$/', $property_name)) {
                if (is_array($property_value)) {
                    if ((int) $is_add > 0) {
                        $this->{$property_name}[] = $property_value;
                    } else {
                        $this->{$property_name} = $property_value;
                    }
                }
            } elseif (preg_match('/Record$/', $property_name)) {
                if (is_array($property_value)) {
                    $this->{$property_name} = $property_value;
                }
            } elseif (is_scalar($property_value)) {
                $this->{$property_name} = $property_value;
            }
        }
        return $this;
    }
    /**
     * Add values of an object property.
     * @param string $propertyValue
     * @param string $propertyName
     * @return $this
     */
    public function add($property_value, $property_name = null)
    {
        return $this->set($property_value, $property_name, true);
    }
    /**
     * Check and prepares data before insertion.
     * @return bool true if valid
     */
    public function validate()
    {
        return true;
    }
    /**
     * Clear relation ids from related data.
     * @return $this
     */
    public function unrelate()
    {
        return $this;
    }
    protected function message_add($message = '', $type = 'error')
    {
        $return = false;
        if (is_array($message)) {
            foreach ($message as $line) {
                $this->message_add($line, $type);
            }
            $return = true;
        } elseif (is_scalar($message)) {
            $message = trim($message);
            if ($message != '') {
                $this->message_array[$message] = $type;
                $return = true;
            }
        }
        unset($message);
        unset($type);
        unset($line);
        return $return;
    }
    public function message_get($is_clear = false)
    {
        $return = $this->message_array;
        if ((int) $is_clear > 0) {
            $this->message_array = [];
        }
        return $return;
    }
    protected static function get_language_id_by_code($language_code = '', $default_language_id = 0, $return_system_default_if_zero = false)
    {
        $language_id = \common\models\Languages::find()->where(['code' => trim($language_code)])->as_array(true)->one();
        if (is_array($language_id)) {
            $language_id = (int) $language_id['languages_id'];
        } else {
            $language_id = (int) $default_language_id;
        }
        if ((int) $return_system_default_if_zero > 0 and $language_id <= 0) {
            $language_id = (int) \common\classes\language::default_id();
        }
        return $language_id;
    }
}