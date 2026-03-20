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

use Dom_Document;
use Dom_Element;
use Dom_Text;
use Simple_Xml_Element;
use Yii;
use yii\base\Arrayable;
use yii\base\Base_Object;
use yii\helpers\String_Helper;
use yii\httpclient\Formatter_Interface;
/**
 * XmlFormatter formats HTTP message as XML.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0
 */
class Xml_Formatter extends Base_Object implements Formatter_Interface
{
    /**
     * @var string the Content-Type header for the response
     */
    public $content_type = 'application/xml';
    /**
     * @var string the XML version
     */
    public $version = '1.0';
    /**
     * @var string the XML encoding. If not set, it will use the value of [[\yii\base\Application::charset]].
     */
    public $encoding;
    /**
     * @var string the name of the root element.
     */
    public $root_tag = 'Response';
    /**
     * @var string the name of the elements that represent the array elements with numeric keys.
     * @since 2.0.1
     */
    public $item_tag = 'item';
    /**
     * @var bool whether to interpret objects implementing the [[\Traversable]] interface as arrays.
     * Defaults to `true`.
     * @since 2.0.1
     */
    public $use_traversable_as_array = true;
    /**
     * @inheritdoc
     */
    public function format($data)
    {
        $content_type = $this->content_type;
        $charset = $this->encoding === null ? Yii::$app->charset : $this->encoding;
        if (stripos($content_type, 'charset') === false) {
            $content_type .= '; charset=' . $charset;
        }
        if ($data !== null) {
            if ($data instanceof Dom_Document) {
                $content = $data->save_xml();
            } elseif ($data instanceof Simple_Xml_Element) {
                $content = $data->save_xml();
            } else {
                $dom = new Dom_Document($this->version, $charset);
                $root = new Dom_Element($this->root_tag);
                $dom->append_child($root);
                $this->build_xml($root, $data);
                $content = $dom->save_xml();
            }
        }
        return $content;
    }
    protected function valid_name($name)
    {
        return preg_replace('/[^a-z0-9_-]/si', '_', trim($name));
    }
    /**
     * @param DOMElement $element
     * @param mixed $data
     */
    protected function build_xml($element, $data)
    {
        if (is_array($data) || $data instanceof \Traversable && $this->use_traversable_as_array && !$data instanceof Arrayable) {
            foreach ($data as $name => $value) {
                $name = $this->valid_name($name);
                if (is_numeric($name) && is_object($value)) {
                    $this->build_xml($element, $value);
                } elseif (is_array($value) || is_object($value)) {
                    $child = new Dom_Element(is_numeric($name) ? $this->item_tag : $name);
                    $element->append_child($child);
                    $this->build_xml($child, $value);
                } else {
                    $child = new Dom_Element(is_numeric($name) ? $this->item_tag : $name);
                    $element->append_child($child);
                    $child->append_child(new Dom_Text((string) $value));
                }
            }
        } elseif (is_object($data)) {
            $child = new Dom_Element(String_Helper::basename(get_class($data)));
            $element->append_child($child);
            if ($data instanceof Arrayable) {
                $this->build_xml($child, $data->to_array());
            } else {
                $array = [];
                foreach ($data as $name => $value) {
                    $name = $this->valid_name($name);
                    $array[$name] = $value;
                }
                $this->build_xml($child, $array);
            }
        } else {
            $element->append_child(new Dom_Text((string) $data));
        }
    }
}