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

abstract class Abstract_Props
{
    /**
     * Convert paramt to xml
     */
    abstract public static function params_to_xml($params = [], $product_id = false);
    /**
     * Retrieve params to working state
     */
    abstract public static function explain_params($params = [], $tax_rate = 0);
    /**
     * Describe Uprid without any transforms
     */
    abstract public static function normalize_id($uprid);
    /**
     * Cart accessible unique uprid
     */
    abstract public static function cart_uprid($products_id, $props);
    /**
     * event in add_cart method
     */
    abstract public static function on_cart_add($props);
    /**
     *
     */
    abstract public static function cart_changed($cart);
    /**
     * describe product properties for cartDecorator
     * @param type $cartProduct
     */
    public static function describe_product(&$cart_product)
    {
        if (isset($cart_product['explain_info']) && is_array($cart_product['explain_info'])) {
            if (!is_array($cart_product['attr'])) {
                $cart_product['attr'] = [];
            }
            foreach ($cart_product['explain_info'] as $_props_info) {
                if ($_props_info['extra_view']) {
                    $_props_info['products_options_values_name'] .= $_props_info['extra_view'];
                }
                $cart_product['attr'][] = $_props_info;
            }
        }
    }
    /**
     * Return extra properties information to product
     */
    abstract public static function admin_order_product_view($order_product);
    /**
     * Append properties as continious of attributes list
     * @param type $orderProduct
     */
    public static function describe_order_product(&$order_product)
    {
        if ($order_product['propsData']) {
            $explain_info = static::explain_params($order_product['propsData'], $order_product['tax']);
            if (is_array($explain_info)) {
                !is_array($order_product['attributes']) && $order_product['attributes'] = [];
                $subindex = (int) count($order_product['attributes']);
                foreach ($explain_info as $attributes) {
                    $order_product['attributes'][$subindex] = ['option' => $attributes['products_options_name'], 'value' => $attributes['products_options_values_name'], 'prefix' => false, 'price' => false];
                    $subindex++;
                }
            }
        }
    }
    public static function to_xml($data, $root = null)
    {
        $xml = new \Simple_Xml_Element($root ? '<' . $root . '/>' : '<root/>');
        self::array_to_xml($data, $xml);
        return $xml->as_xml();
    }
    public static function xml_to_params($xmlstring = '')
    {
        $params = [];
        $xml = @simplexml_load_string($xmlstring);
        if ($xml) {
            $params = self::parse_simple_xml($xml);
        }
        return $params;
    }
    public static function parse_simple_xml($xmldata)
    {
        $child_names = [];
        $children = [];
        if (count($xmldata) !== 0) {
            foreach ($xmldata->children() as $child) {
                $name = $child->get_name();
                if (!isset($child_names[$name])) {
                    $child_names[$name] = 0;
                }
                $child_names[$name]++;
                $children[$name][] = self::parse_simple_xml($child);
            }
        }
        $returndata = [];
        if (count($child_names) > 0) {
            foreach ($child_names as $name => $count) {
                if ($count === 1) {
                    $returndata[$name] = $children[$name][0];
                } else {
                    $returndata[$name] = [];
                    $counter = 0;
                    foreach ($children[$name] as $data) {
                        $returndata[$name][$counter] = $data;
                        $counter++;
                    }
                }
            }
        } else {
            if (defined('CHARSET')) {
                $xmldata = iconv('utf-8', CHARSET, $xmldata);
            }
            $returndata = (string) $xmldata;
            $returndata = stripslashes($returndata);
        }
        return $returndata;
    }
    public static function array_to_xml($data, &$xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item' . $key;
                    //dealing with <0/>..<n/> issues
                }
                $subnode = $xml_data->add_child($key);
                self::array_to_xml($value, $subnode);
            } else {
                $xml_data->add_child("{$key}", htmlspecialchars("{$value}"));
            }
        }
    }
}