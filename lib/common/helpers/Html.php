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
namespace common\helpers;

class Html extends \yii\helpers\Html
{
    public static function text_input_nullable($name, $value = null, $options = [])
    {
        $button = [];
        if (isset($options['button'])) {
            $button = $options['button'];
        }
        if (!isset($button['options']) || !is_array($button['options'])) {
            $button['options'] = [];
        }
        $class = ['input-group-addon', 'js-input-nullable-btn'];
        if (isset($button['options']['class']) && strpos($button['options']['class'], 'input-group-addon') === false) {
            $class = ['input-group-addon', 'js-input-nullable-btn', $button['options']['class']];
        }
        $button['options']['title'] = IMAGE_EDIT;
        $button['options']['class'] = array_merge($class, ['js-input-nullable-edit']);
        $input_button = static::tag('div', '<i class="icon-pencil"></i>', $button['options']);
        $button['options']['style'] = ['display' => 'none'];
        if (isset($options['placeholder']) && $options['placeholder'] !== '') {
            $button['options']['title'] = RETURN_DEFAULT_VALUE;
            $button['options']['class'] = array_merge($class, ['js-input-nullable-undo']);
            $input_button .= static::tag('div', '<i class="icon-undo"></i>', $button['options']);
        }
        $button['options']['title'] = IMAGE_CANCEL;
        $button['options']['class'] = array_merge($class, ['js-input-nullable-close']);
        $input_button .= static::tag('div', '<i class="icon-close"></i>', $button['options']);
        $button['options']['title'] = TEXT_APPLY;
        $button['options']['class'] = array_merge($class, ['js-input-nullable-save']);
        $input_button .= static::tag('div', '<i class="icon-ok"></i>', $button['options']);
        $options['readonly'] = 'readonly';
        $default = '';
        if (isset($options['placeholder']) && $options['placeholder'] !== '') {
            $default = '<div class="js-input-nullable-default"' . ($value ? '' : ' style="display: none"') . '>
                            <span>' . TEXT_DEFAULT . '</span>
                            <span class="js-input-nullable-default-val">' . $options['placeholder'] . '</span>
                        </div>';
        }
        return '<div class="input-group js-main-text-input-nullable">' . $default . static::text_input($name, $value, $options) . $input_button . '</div>';
    }
    /**
     * adds css default class (form-control), unique class (<last-class>|<$type>-<start[name]>) id by name (_ to lo-camel-ed),
     * @param type $type
     * @param type $name
     * @param type $value
     * @param type $options
     * @return type
     */
    public static function input($type, $name = null, $value = null, $options = [])
    {
        self::common_class($type, $name, $options);
        self::common_id($name, $options);
        if (!isset($options['class']) || strpos($options['class'], 'form-control') === false) {
            if (in_array($type, ['checkbox', 'radio'])) {
                $c = 'form-control-bool ';
            } else {
                $c = 'form-control ';
            }
            $options['class'] = $c . (isset($options['class']) ? $options['class'] : '');
        }
        return parent::input($type, $name, $value, $options);
    }
    public static function checkbox($name, $checked = false, $options = [])
    {
        if (!isset($options['class']) || strpos($options['class'], 'multiOption') === false && strpos($options['class'], 'uniform') === false && strpos($options['class'], '_on_off') === false) {
            // check|switch| etc _on_off O_O
            $options['class'] = 'multiOption ' . (isset($options['class']) ? $options['class'] : '');
        }
        return parent::checkbox($name, $checked, $options);
    }
    public static function common_id($name, &$options)
    {
        if (!isset($options['id']) && !empty($name) && !strpos($name, '[]')) {
            $p = explode('_', $name);
            array_walk($p, function (&$val, $key) {
                if ($key > 0) {
                    $val = ucfirst($val);
                }
            });
            $options['id'] = str_replace(['[', ']'], '_', implode('', $p));
        }
    }
    public static function common_class($type, $name, &$options)
    {
        if (!empty($name)) {
            $m = [];
            preg_match('/^[\da-z]+/i', $name, $m);
            if (count($m)) {
                $sf = '-' . $m[0];
            } else {
                $sf = '-' . $name;
            }
            if (isset($options['class'])) {
                $m = [];
                preg_match('/[\S]+$/i', trim($options['class']), $m);
                if (count($m)) {
                    $options['class'] .= ' ' . $m[0] . $sf;
                } else {
                    $options['class'] .= ' ' . $type . $sf;
                }
            } else {
                $options['class'] = ' ' . $type . $sf;
            }
        }
    }
    /**
     * {@inheritdocs}
     */
    public static function drop_down_list($name, $selection = null, $items = [], $options = [])
    {
        self::common_class('select', $name, $options);
        self::common_id($name, $options);
        if (!isset($options['class']) || strpos($options['class'], 'form-control') === false) {
            $options['class'] = 'form-control' . (isset($options['class']) ? $options['class'] : '');
        }
        return parent::drop_down_list($name, $selection, $items, $options);
    }
    public static function active_file_input($model, $attribute, $options = [])
    {
        $hidden_options = ['id' => null];
        if (isset($options['name'])) {
            $hidden_options['name'] = $options['name'];
        }
        // make sure disabled input is not sending any value
        if (!empty($options['disabled'])) {
            $hidden_options['disabled'] = $options['disabled'];
        }
        $hidden_options = \yii\helpers\Array_Helper::merge($hidden_options, \yii\helpers\Array_Helper::remove($options, 'hiddenOptions', []));
        return static::active_hidden_input($model, $attribute, $hidden_options) . static::active_input('file', $model, $attribute, $options);
    }
    public static function fix_html_tags($html)
    {
        if (!class_exists('\DOMDocument')) {
            return $html;
        }
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        if (empty($html)) {
            return $html;
        }
        $dom = new \Dom_Document();
        @$dom->load_html($html);
        $nodes = $dom->get_elements_by_tag_name('body')->item(0)->child_nodes;
        $html = '';
        $len = $nodes->length;
        for ($i = 0; $i < $len; $i++) {
            $html .= $dom->save_html($nodes->item($i));
        }
        $html = preg_replace('/<p[^>]{0,}>/', '', $html);
        $html = str_replace('</p>', '', $html);
        return $html;
    }
    private static function tr(string $const_name, bool $camel_if_not_found_constant = null)
    {
        if (defined($const_name)) {
            $string = constant($const_name);
        } else {
            if (is_null($camel_if_not_found_constant)) {
                $camel_if_not_found_constant = \common\helpers\System::is_production();
            }
            $string = $camel_if_not_found_constant ? \yii\helpers\Inflector::camel2words($const_name) : $const_name;
        }
        return $string;
    }
    public static function tr_html(string $const_name)
    {
        return self::encode(self::tr($const_name));
    }
    public static function tr_js(string $const_name)
    {
        return \yii\helpers\Json::encode(self::tr($const_name));
    }
}