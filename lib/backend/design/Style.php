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
namespace backend\design;

use common\models\Themes_Settings;
use common\models\Themes_Styles;
use common\models\Themes_Styles_Main;
use Yii;
use yii\helpers\Array_Helper;
class Style
{
    public const STYLE_CACHE_LIFETIME = 15;
    public static $css_frontend = false;
    public static function hide($class)
    {
        $arr = [];
        if ($class == 'body') {
            $arr = ['hover' => 1, 'display' => 1, 'padding' => 1, 'border' => 1, 'size' => 1];
        }
        if ($class == 'a') {
            $arr = ['font' => ['font_size' => 1, 'line_height' => 1, 'text_align' => 1, 'vertical_align' => 1], 'padding' => 1, 'border' => 1, 'size' => 1, 'display' => 1];
        }
        if ($class == '.main-width, .type-1 > .block') {
            $arr = ['hover' => 1, 'font' => 1, 'background' => 1, 'border' => 1, 'size' => ['width' => 1, 'min_width' => 1, 'height' => 1, 'min_height' => 1, 'max_height' => 1], 'display' => 1];
        }
        if ($class == '.menu-slider .close') {
            $arr = ['font' => ['font_family' => 1, 'vertical_align' => 1], 'size' => ['min_width' => 1, 'min_height' => 1, 'max_width' => 1, 'max_height' => 1], 'display' => 1];
        }
        return $arr;
    }
    public static function show($class)
    {
        $arr = [];
        if ($class == '.w-tabs .tab-a' || $class == '.menu-style-1 > ul > li' || $class == '.menu-style-1 > ul > li > ul > li' || $class == '.menu-style-1 > ul > li > ul > li > ul > li' || $class == '.menu-style-1 > ul > li > ul > li > ul > li > ul > li' || $class == '.menu-slider > ul > li' || $class == '.menu-slider > ul > li > ul > li' || $class == '.menu-slider > ul > li > ul > li > ul > li' || $class == '.menu-slider > ul > li > ul > li > ul > li > ul > li' || $class == '.menu-horizontal > ul > li' || $class == '.menu-horizontal > ul > li > ul > li' || $class == '.menu-horizontal > ul > li > ul > li > ul > li' || $class == '.menu-horizontal > ul > li > ul > li > ul > li > ul > li' || $class == 'a.my-acc-link' || $class == '.paging a, .paging span' || $class == '.page-style a.grid' || $class == '.page-style a.list' || $class == '.page-style a.b2b') {
            $arr = ['active' => 1];
        }
        return $arr;
    }
    public static function css_compile($css, $theme_name, $accessibility = '')
    {
        $css = preg_replace('/\/\*.+\*\//', ' ', $css);
        $attributes = [];
        //foreach (self::explodeByAccessibility($css) as $accessibility => $styles) {
        $blocks = self::explode_by_media_blocks($css, $theme_name);
        $attributes = array_merge($attributes, self::pars_block($blocks['no_media'], '', '', $accessibility, $theme_name));
        foreach ($blocks['visibility'] as $key => $value) {
            $attributes = array_merge($attributes, self::pars_block($value, $key, '', $accessibility, $theme_name));
        }
        foreach ($blocks['media'] as $key => $value) {
            $attributes = array_merge($attributes, self::pars_block($value, '', $key, $accessibility, $theme_name));
        }
        //}
        return $attributes;
    }
    public static function get_accessibility($selector, $theme_name)
    {
        $acc = '';
        if (preg_match('/^(\.p-[0-9a-zA-Z\-\_]+)/', $selector, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^(\.b-[0-9a-zA-Z\-\_]+)/', $selector, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^(\.s-[0-9a-zA-Z\-\_]+)/', $selector, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^(\.w-[0-9a-zA-Z\-\_]+)/', $selector, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^\.p-[0-9a-zA-Z\-\_]+[\s]+(\.w-[0-9a-zA-Z\-\_]+)/', $selector, $matches)) {
            return $matches[1];
        }
        static $classes = [];
        if (count($classes) == 0) {
            $query = tep_db_query('
                select distinct setting_value 
                from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " \r\n                where setting_name = 'style_class'");
            while ($item = tep_db_fetch_array($query)) {
                $style_class = $item['setting_value'];
                $style_class = preg_replace('/[\s]+/', ' ', $style_class);
                $cl = explode(' ', $style_class);
                foreach ($cl as $cl) {
                    $classes[] = $cl;
                }
            }
        }
        foreach ($classes as $class) {
            if (strpos($selector, '.' . $class . ' ') === 0) {
                return '.' . $class;
            }
        }
        return $acc;
    }
    public static function pars_block($block, $visibility = '', $media = '', $accessibility = '', $theme_name = '')
    {
        $class_arr = explode('}', $block);
        $attributes = [];
        foreach ($class_arr as $class) {
            $v_class = $visibility;
            $first = stripos($class, '{');
            $selector = trim(substr($class, 0, $first));
            $selector = preg_replace('/\n/', ' ', $selector);
            $selector = preg_replace('/\/\*[.\n]+\*\//', '', $selector);
            $selector = preg_replace('/[\s]+/', ' ', $selector);
            if ($accessibility) {
                $sl = explode(',', $selector);
                foreach ($sl as $sl_item => $sl_val) {
                    $sl_val = trim($sl_val);
                    if (substr($sl_val, 0, 1) == '&') {
                        $sl[$sl_item] = $accessibility . substr($sl_val, 1);
                    } else {
                        $sl[$sl_item] = $accessibility . ' ' . $sl_val;
                    }
                }
                $selector = implode(', ', $sl);
            }
            $acc = self::get_accessibility($selector, $theme_name);
            $selector_arr = explode(',', $selector);
            $class_tmp = '';
            $ps_class = '';
            $editor = true;
            foreach ($selector_arr as $item) {
                $pos = stripos($item, ':');
                $pos_active = stripos($item, '.active');
                if ($pos || $pos_active) {
                    if ($pos_active) {
                        $ps_class = substr($item, $pos_active);
                    }
                    if (!$ps_class) {
                        $ps_class = substr($item, $pos);
                    }
                    $last = str_replace('.active', '', $ps_class);
                    $last = str_replace(':hover', '', $last);
                    $last = str_replace(':before', '', $last);
                    $last = str_replace(':after', '', $last);
                    if ($last) {
                        $editor = false;
                    }
                    if ($ps_class && $class_tmp && $ps_class != $class_tmp) {
                        $editor = false;
                    }
                    $class_tmp = $ps_class;
                }
            }
            if ($editor && $ps_class) {
                if (stripos($ps_class, '.active') !== false) {
                    $v_class .= ($v_class ? ',' : '') . 2;
                }
                if (stripos($ps_class, ':hover') !== false) {
                    $v_class .= ($v_class ? ',' : '') . 1;
                }
                if (stripos($ps_class, ':before') !== false) {
                    $v_class .= ($v_class ? ',' : '') . 3;
                }
                if (stripos($ps_class, ':after') !== false) {
                    $v_class .= ($v_class ? ',' : '') . 4;
                }
                $arr = [];
                foreach ($selector_arr as $item) {
                    $arr[] = str_replace($ps_class, '', $item);
                }
                $selector = implode(',', $arr);
            }
            $content = trim(substr($class, $first + 1));
            $rows = explode(';', $content);
            foreach ($rows as $row) {
                $row_explode = explode(':', $row);
                $attribute = trim($row_explode[0]);
                $value = isset($row_explode[1]) ? trim($row_explode[1]) : '';
                if ($selector && $attribute && $value !== '') {
                    $attribute_tl = self::pars_attributes($attribute, $value);
                    foreach ($attribute_tl as $item) {
                        $attributes[] = ['selector' => $selector, 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $v_class, 'media' => $media, 'accessibility' => $acc];
                    }
                }
            }
        }
        return $attributes;
    }
    public static function pars_attributes($attribute, $value)
    {
        $attr = [];
        $default = false;
        $attribute_value_size = ['top', 'left', 'right', 'bottom', 'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'font-size', 'line-height', 'padding-top', 'padding-left', 'padding-right', 'padding-bottom', 'margin-top', 'margin-left', 'margin-right', 'margin-bottom'];
        $important_attr = false;
        if (strpos($value, '!important') !== false) {
            $value = str_replace('!important', '', $value);
            $value = trim($value);
            $important_attr = true;
        }
        if ($attribute == 'padding') {
            if (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'padding-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-right', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'padding_right_measure', 'value' => $matches[4]];
                }
                $attr[] = ['attribute' => 'padding-bottom', 'value' => $matches[5]];
                if ($matches[6] && $matches[6] != 'px') {
                    $attr[] = ['attribute' => 'padding_bottom_measure', 'value' => $matches[6]];
                }
                $attr[] = ['attribute' => 'padding-left', 'value' => $matches[7]];
                if ($matches[8] && $matches[8] != 'px') {
                    $attr[] = ['attribute' => 'padding_left_measure', 'value' => $matches[8]];
                }
            } elseif (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'padding-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-right', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'padding_right_measure', 'value' => $matches[4]];
                }
                $attr[] = ['attribute' => 'padding-bottom', 'value' => $matches[5]];
                if ($matches[6] && $matches[6] != 'px') {
                    $attr[] = ['attribute' => 'padding_bottom_measure', 'value' => $matches[6]];
                }
                $attr[] = ['attribute' => 'padding-left', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'padding_left_measure', 'value' => $matches[4]];
                }
            } elseif (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'padding-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-right', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'padding_right_measure', 'value' => $matches[4]];
                }
                $attr[] = ['attribute' => 'padding-bottom', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_bottom_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-left', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'padding_left_measure', 'value' => $matches[4]];
                }
            } elseif (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'padding-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-right', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_right_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-bottom', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_bottom_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'padding-left', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'padding_left_measure', 'value' => $matches[2]];
                }
            } else {
                $default = true;
            }
        } elseif ($attribute == 'margin') {
            if (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'margin-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-right', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'margin_right_measure', 'value' => $matches[4]];
                }
                $attr[] = ['attribute' => 'margin-bottom', 'value' => $matches[5]];
                if ($matches[6] && $matches[6] != 'px') {
                    $attr[] = ['attribute' => 'margin_bottom_measure', 'value' => $matches[6]];
                }
                $attr[] = ['attribute' => 'margin-left', 'value' => $matches[7]];
                if ($matches[8] && $matches[8] != 'px') {
                    $attr[] = ['attribute' => 'margin_left_measure', 'value' => $matches[8]];
                }
            } elseif (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'margin-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-right', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'margin_right_measure', 'value' => $matches[4]];
                }
                $attr[] = ['attribute' => 'margin-bottom', 'value' => $matches[5]];
                if ($matches[6] && $matches[6] != 'px') {
                    $attr[] = ['attribute' => 'margin_bottom_measure', 'value' => $matches[6]];
                }
                $attr[] = ['attribute' => 'margin-left', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'margin_left_measure', 'value' => $matches[4]];
                }
            } elseif (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'margin-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-right', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'margin_right_measure', 'value' => $matches[4]];
                }
                $attr[] = ['attribute' => 'margin-bottom', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_bottom_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-left', 'value' => $matches[3]];
                if ($matches[4] && $matches[4] != 'px') {
                    $attr[] = ['attribute' => 'margin_left_measure', 'value' => $matches[4]];
                }
            } elseif (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'margin-top', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_top_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-right', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_right_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-bottom', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_bottom_measure', 'value' => $matches[2]];
                }
                $attr[] = ['attribute' => 'margin-left', 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => 'margin_left_measure', 'value' => $matches[2]];
                }
            } else {
                $default = true;
            }
        } elseif ($attribute == 'line-height') {
            if (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => $attribute, 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'em') {
                    $attr[] = ['attribute' => 'line_height_measure', 'value' => $matches[2]];
                }
            } else {
                $default = true;
            }
        } elseif ($attribute == 'transform') {
            if (preg_match('/^rotate\(([\-0-9\.]+)deg\)$/', $value, $matches)) {
                $attr[] = ['attribute' => 'rotate', 'value' => $matches[1]];
            } else {
                $default = true;
            }
        } elseif ($attribute == 'content') {
            if ($value == "''" || $value == '""') {
                $attr[] = ['attribute' => 'content', 'value' => '\_'];
            } else {
                $attr[] = ['attribute' => 'content', 'value' => substr(substr($value, 0, -1), 1)];
            }
        } elseif (in_array($attribute, $attribute_value_size)) {
            if (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => $attribute, 'value' => $matches[1]];
                if ($matches[2] && $matches[2] != 'px') {
                    $attr[] = ['attribute' => str_replace('-', '_', $attribute) . '_measure', 'value' => $matches[2]];
                }
            } else {
                $attr[] = ['attribute' => $attribute, 'value' => $value];
            }
        } elseif ($attribute == 'border-radius') {
            if (preg_match('/^([0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'border-top-left-radius', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border_radius_1_measure', 'value' => $matches[2]];
                $attr[] = ['attribute' => 'border-top-right-radius', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border_radius_2_measure', 'value' => $matches[2]];
                $attr[] = ['attribute' => 'border-bottom-right-radius', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border_radius_3_measure', 'value' => $matches[2]];
                $attr[] = ['attribute' => 'border-bottom-left-radius', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border_radius_4_measure', 'value' => $matches[2]];
            } elseif (preg_match('/^([0-9\.]+)([a-z\%]{0,})[\s]+([0-9\.]+)([a-z\%]{0,})[\s]+([0-9\.]+)([a-z\%]{0,})[\s]+([0-9\.]+)([a-z\%]{0,})$/', $value, $matches)) {
                $attr[] = ['attribute' => 'border-top-left-radius', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border_radius_1_measure', 'value' => $matches[2]];
                $attr[] = ['attribute' => 'border-top-right-radius', 'value' => $matches[3]];
                $attr[] = ['attribute' => 'border_radius_2_measure', 'value' => $matches[4]];
                $attr[] = ['attribute' => 'border-bottom-right-radius', 'value' => $matches[5]];
                $attr[] = ['attribute' => 'border_radius_3_measure', 'value' => $matches[6]];
                $attr[] = ['attribute' => 'border-bottom-left-radius', 'value' => $matches[7]];
                $attr[] = ['attribute' => 'border_radius_4_measure', 'value' => $matches[8]];
            } else {
                $attr[] = ['attribute' => $attribute, 'value' => $value];
            }
        } elseif ($attribute == 'border') {
            if ($value == 'inherit') {
                $attr[] = ['attribute' => 'border', 'value' => 'inherit'];
            } elseif ($value == 'none') {
                $attr[] = ['attribute' => 'border', 'value' => 'none'];
            } elseif ($value == 'hidden') {
                $attr[] = ['attribute' => 'border', 'value' => 'hidden'];
            } else {
                if (preg_match('/([0-9\.]+)([a-z\%]+)/', $value, $matches)) {
                    $attr[] = ['attribute' => 'border-top-width', 'value' => $matches[1]];
                    $attr[] = ['attribute' => 'border-left-width', 'value' => $matches[1]];
                    $attr[] = ['attribute' => 'border-right-width', 'value' => $matches[1]];
                    $attr[] = ['attribute' => 'border-bottom-width', 'value' => $matches[1]];
                    if ($matches[2] != 'px') {
                        $attr[] = ['attribute' => 'border_top_width_measure', 'value' => $matches[2]];
                        $attr[] = ['attribute' => 'border_left_width_measure', 'value' => $matches[2]];
                        $attr[] = ['attribute' => 'border_right_width_measure', 'value' => $matches[2]];
                        $attr[] = ['attribute' => 'border_bottom_width_measure', 'value' => $matches[2]];
                    }
                }
                $border_style = '';
                if (strpos($value, 'solid') !== false) {
                    $border_style = '';
                } elseif (strpos($value, 'dotted') !== false) {
                    $border_style = 'dotted';
                } elseif (strpos($value, 'dashed') !== false) {
                    $border_style = 'dashed';
                } elseif (strpos($value, 'double') !== false) {
                    $border_style = 'double';
                } elseif (strpos($value, 'groove') !== false) {
                    $border_style = 'groove';
                } elseif (strpos($value, 'ridge') !== false) {
                    $border_style = 'ridge';
                } elseif (strpos($value, 'inset') !== false) {
                    $border_style = 'inset';
                } elseif (strpos($value, 'outset') !== false) {
                    $border_style = 'outset';
                }
                if ($border_style) {
                    $attr[] = ['attribute' => 'border-top-style', 'value' => $border_style];
                    $attr[] = ['attribute' => 'border-left-style', 'value' => $border_style];
                    $attr[] = ['attribute' => 'border-right-style', 'value' => $border_style];
                    $attr[] = ['attribute' => 'border-bottom-style', 'value' => $border_style];
                }
                $border_color = '';
                if (preg_match('/(rgb[a]{0,1}\([\-0-9\.\,\s]+\))/', $value, $matches)) {
                    $border_color = $matches[1];
                } elseif (preg_match('/(\#[\-0-9a-fA-F]{3,6})/', $value, $matches)) {
                    $border_color = $matches[1];
                } elseif (preg_match('/(\$[\-0-9a-z]+)/', $value, $matches)) {
                    $border_color = $matches[1];
                }
                if ($border_color) {
                    $attr[] = ['attribute' => 'border-top-color', 'value' => $border_color];
                    $attr[] = ['attribute' => 'border-left-color', 'value' => $border_color];
                    $attr[] = ['attribute' => 'border-right-color', 'value' => $border_color];
                    $attr[] = ['attribute' => 'border-bottom-color', 'value' => $border_color];
                }
            }
        } elseif ($attribute == 'border-top' || $attribute == 'border-left' || $attribute == 'border-right' || $attribute == 'border-bottom') {
            if ($value == 'inherit') {
                $attr[] = ['attribute' => $attribute, 'value' => 'inherit'];
            } elseif ($value == 'none') {
                $attr[] = ['attribute' => $attribute, 'value' => 'none'];
            } elseif ($value == 'hidden') {
                $attr[] = ['attribute' => $attribute, 'value' => 'hidden'];
            } else {
                if (preg_match('/([0-9\.]+)([a-z\%]{0,})/', $value, $matches)) {
                    $attr[] = ['attribute' => $attribute . '-width', 'value' => $matches[1]];
                    if ($matches[2] != 'px') {
                        $attr[] = ['attribute' => str_replace('-', '_', $attribute) . '_width_measure', 'value' => $matches[2]];
                    }
                }
                $border_style = '';
                if (strpos($value, 'solid') !== false) {
                    $border_style = '';
                } elseif (strpos($value, 'dotted') !== false) {
                    $border_style = 'dotted';
                } elseif (strpos($value, 'dashed') !== false) {
                    $border_style = 'dashed';
                } elseif (strpos($value, 'double') !== false) {
                    $border_style = 'double';
                } elseif (strpos($value, 'groove') !== false) {
                    $border_style = 'groove';
                } elseif (strpos($value, 'ridge') !== false) {
                    $border_style = 'ridge';
                } elseif (strpos($value, 'inset') !== false) {
                    $border_style = 'inset';
                } elseif (strpos($value, 'outset') !== false) {
                    $border_style = 'outset';
                }
                if ($border_style) {
                    $attr[] = ['attribute' => $attribute . '-style', 'value' => $border_style];
                }
                $border_color = '';
                if (preg_match('/(rgb[a]{0,1}\([\-0-9\.\,\s]+\))/', $value, $matches)) {
                    $border_color = $matches[1];
                } elseif (preg_match('/(\#[\-0-9a-fA-F]{3,6})/', $value, $matches)) {
                    $border_color = $matches[1];
                } elseif (preg_match('/(\$[\-0-9a-z]+)/', $value, $matches)) {
                    $border_color = $matches[1];
                } elseif (strpos($value, 'transparent') !== false) {
                    $border_color = 'transparent';
                }
                if ($border_color) {
                    $attr[] = ['attribute' => $attribute . '-color', 'value' => $border_color];
                }
            }
        } elseif ($attribute == 'border-top-width' || $attribute == 'border-left-width' || $attribute == 'border-right-width' || $attribute == 'border-bottom-width') {
            if (preg_match('/([0-9\.]+)([a-z\%]{0,})/', $value, $matches)) {
                $attr[] = ['attribute' => $attribute, 'value' => $matches[1]];
                if ($matches[2] != 'px') {
                    $attr[] = ['attribute' => str_replace('-', '_', $attribute) . '_measure', 'value' => $matches[2]];
                }
            }
        } elseif ($attribute == 'border-color') {
            $border_color = '';
            if (preg_match('/(rgb[a]{0,1}\([\-0-9\.\,\s]+\))/', $value, $matches)) {
                $border_color = $matches[1];
            } elseif (preg_match('/(\#[\-0-9a-fA-F]{3,6})/', $value, $matches)) {
                $border_color = $matches[1];
            } elseif (preg_match('/(\$[\-0-9a-z]+)/', $value, $matches)) {
                $border_color = $matches[1];
            }
            if ($border_color) {
                $attr[] = ['attribute' => 'border-top-color', 'value' => $border_color];
                $attr[] = ['attribute' => 'border-left-color', 'value' => $border_color];
                $attr[] = ['attribute' => 'border-right-color', 'value' => $border_color];
                $attr[] = ['attribute' => 'border-bottom-color', 'value' => $border_color];
            }
        } elseif ($attribute == 'border-style') {
            $border_style = '';
            if (strpos($value, 'solid') !== false) {
                $border_style = '';
            } elseif (strpos($value, 'dotted') !== false) {
                $border_style = 'dotted';
            } elseif (strpos($value, 'dashed') !== false) {
                $border_style = 'dashed';
            } elseif (strpos($value, 'double') !== false) {
                $border_style = 'double';
            } elseif (strpos($value, 'groove') !== false) {
                $border_style = 'groove';
            } elseif (strpos($value, 'ridge') !== false) {
                $border_style = 'ridge';
            } elseif (strpos($value, 'inset') !== false) {
                $border_style = 'inset';
            } elseif (strpos($value, 'outset') !== false) {
                $border_style = 'outset';
            } elseif (strpos($value, 'none') !== false) {
                $border_style = 'none';
            } elseif (strpos($value, 'inherit') !== false) {
                $border_style = 'inherit';
            }
            if ($border_style) {
                $attr[] = ['attribute' => 'border-top-style', 'value' => $border_style];
                $attr[] = ['attribute' => 'border-left-style', 'value' => $border_style];
                $attr[] = ['attribute' => 'border-right-style', 'value' => $border_style];
                $attr[] = ['attribute' => 'border-bottom-style', 'value' => $border_style];
            }
        } elseif ($attribute == 'border-width') {
            if (preg_match('/([0-9\.]+)([a-z\%]{0,})/', $value, $matches)) {
                $attr[] = ['attribute' => 'border-top-width', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border-left-width', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border-right-width', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'border-bottom-width', 'value' => $matches[1]];
                if ($matches[2] != 'px') {
                    $attr[] = ['attribute' => 'border_top_width_measure', 'value' => $matches[2]];
                    $attr[] = ['attribute' => 'border_left_width_measure', 'value' => $matches[2]];
                    $attr[] = ['attribute' => 'border_right_width_measure', 'value' => $matches[2]];
                    $attr[] = ['attribute' => 'border_bottom_width_measure', 'value' => $matches[2]];
                }
            }
        } elseif ($attribute == 'text-shadow') {
            if (preg_match('/^([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([0-9\.]+)([a-z\%]{0,})[\s]+([0-9a-zA-Z\(\)\,\s\#]+)$/', $value, $matches)) {
                $attr[] = ['attribute' => 'text_shadow_left', 'value' => $matches[1]];
                $attr[] = ['attribute' => 'text_shadow_left_measure', 'value' => $matches[2]];
                $attr[] = ['attribute' => 'text_shadow_top', 'value' => $matches[3]];
                $attr[] = ['attribute' => 'text_shadow_top_measure', 'value' => $matches[4]];
                $attr[] = ['attribute' => 'text_shadow_size', 'value' => $matches[5]];
                $attr[] = ['attribute' => 'text_shadow_size_measure', 'value' => $matches[6]];
                $attr[] = ['attribute' => 'text_shadow_color', 'value' => $matches[7]];
            } else {
                $attr[] = ['attribute' => $attribute, 'value' => $value];
            }
        } elseif ($attribute == 'box-shadow') {
            if (preg_match('/^([inset]{0,})[\s]{0,}([\-0-9\.]+)([a-z\%]{0,})[\s]+([\-0-9\.]+)([a-z\%]{0,})[\s]+([0-9\.]+)([a-z\%]{0,})[\s]+([0-9\.]{0,})([a-z\%]{0,})[\s]{0,}([0-9a-zA-Z\(\)\,\s\#]+)$/', $value, $matches)) {
                if ($matches[1] == 'inset') {
                    $attr[] = ['attribute' => 'box_shadow_set', 'value' => $matches[1]];
                }
                $attr[] = ['attribute' => 'box_shadow_left', 'value' => $matches[2]];
                $attr[] = ['attribute' => 'box_shadow_left_measure', 'value' => $matches[3]];
                $attr[] = ['attribute' => 'box_shadow_top', 'value' => $matches[4]];
                $attr[] = ['attribute' => 'box_shadow_top_measure', 'value' => $matches[5]];
                $attr[] = ['attribute' => 'box_shadow_blur', 'value' => $matches[6]];
                $attr[] = ['attribute' => 'box_shadow_blur_measure', 'value' => $matches[7]];
                if ($matches[8]) {
                    $attr[] = ['attribute' => 'box_shadow_spread', 'value' => $matches[8]];
                    $attr[] = ['attribute' => 'box_shadow_spread_measure', 'value' => $matches[9]];
                }
                $attr[] = ['attribute' => 'box_shadow_color', 'value' => $matches[10]];
            } else {
                $attr[] = ['attribute' => $attribute, 'value' => $value];
            }
        } elseif ($attribute == 'background') {
            if ($value == 'inherit') {
                $attr[] = ['attribute' => 'background', 'value' => 'inherit'];
            } elseif ($value == 'none') {
                $attr[] = ['attribute' => 'background', 'value' => 'none'];
            } elseif ($value == 'transparent') {
                $attr[] = ['attribute' => 'background', 'value' => 'transparent'];
            } elseif (strpos($value, 'gradient') !== false) {
                $default = true;
            } else {
                if (strpos($value, 'fixed') !== false) {
                    $attr[] = ['attribute' => 'background-attachment', 'value' => 'fixed'];
                } elseif (strpos($value, 'scroll') !== false) {
                    $attr[] = ['attribute' => 'background-attachment', 'value' => 'scroll'];
                } elseif (strpos($value, 'local') !== false) {
                    $attr[] = ['attribute' => 'background-attachment', 'value' => 'local'];
                }
                if (strpos($value, 'no-repeat') !== false) {
                    $attr[] = ['attribute' => 'background-repeat', 'value' => 'no-repeat'];
                } elseif (strpos($value, 'repeat') !== false) {
                    $attr[] = ['attribute' => 'background-repeat', 'value' => 'repeat'];
                } elseif (strpos($value, 'repeat-x') !== false) {
                    $attr[] = ['attribute' => 'background-repeat', 'value' => 'repeat-x'];
                } elseif (strpos($value, 'repeat-y') !== false) {
                    $attr[] = ['attribute' => 'background-repeat', 'value' => 'repeat-y'];
                }
                $horizontal = '';
                $vertical = '';
                if (strpos($value, 'left') !== false) {
                    $horizontal = 'left';
                } elseif (strpos($value, 'center') !== false) {
                    $horizontal = 'center';
                } elseif (strpos($value, 'right') !== false) {
                    $horizontal = 'right';
                }
                if (strpos($value, 'top') !== false) {
                    $vertical = 'top';
                } elseif (strpos($value, 'bottom') !== false) {
                    $vertical = 'bottom';
                }
                if ($horizontal && $vertical) {
                    $attr[] = ['attribute' => 'background-position', 'value' => $vertical . ' ' . $horizontal];
                } elseif ($horizontal || $vertical) {
                    $attr[] = ['attribute' => 'background-position', 'value' => $vertical . $horizontal];
                } elseif (preg_match('/[\s]+([\-0-9\.]+[a-z\%]+[\s]+[\-0-9\.]+[a-z\%]+)/', $value, $matches)) {
                    $attr[] = ['attribute' => 'background-position', 'value' => $matches[1]];
                }
                if (preg_match('/url\([\'\"](.+)[\'\"]\)/', $value, $matches)) {
                    $attr[] = ['attribute' => 'background_image', 'value' => $matches[1]];
                }
                if (preg_match('/(rgb[a]{0,1}\([\-0-9\.\,\s]+\))/', $value, $matches)) {
                    $attr[] = ['attribute' => 'background-color', 'value' => $matches[1]];
                } elseif (preg_match('/(\#[\-0-9a-fA-F]{3,6})/', $value, $matches)) {
                    $attr[] = ['attribute' => 'background-color', 'value' => $matches[1]];
                } elseif (preg_match('/(\$[\-0-9a-z]+)/', $value, $matches)) {
                    $attr[] = ['attribute' => 'background-color', 'value' => $matches[1]];
                }
            }
        } elseif ($attribute == 'background-image') {
            if (preg_match('/url\([\'\"](.+)[\'\"]\)/', $value, $matches)) {
                $attr[] = ['attribute' => 'background_image', 'value' => $matches[1]];
            } else {
                $default = true;
            }
        } else {
            $default = true;
        }
        if ($default) {
            $attr[] = ['attribute' => $attribute, 'value' => $value];
        }
        $attr_tmp = [];
        if ($important_attr) {
            foreach ($attr as $attr_item) {
                $attr_tmp[] = ['attribute' => $attr_item['attribute'] . '_important', 'value' => 'important'];
            }
        }
        $attr = array_merge($attr, $attr_tmp);
        return $attr;
    }
    public static function explode_by_accessibility($css)
    {
        $area_explode_arr = explode('@area', $css);
        $counter = 0;
        $area_blocks = [];
        foreach ($area_explode_arr as $area_explode) {
            if ($counter == 0) {
                $area_blocks[''] = $area_explode;
            } else {
                $first = stripos($area_explode, '{');
                $area_name = trim(substr($area_explode, 0, $first));
                $last = strrpos($area_explode, '}');
                $area_blocks[$area_name] = substr($area_explode, $first + 1, $last - $first - 1);
            }
            $counter++;
        }
        return $area_blocks;
    }
    public static function explode_by_media_blocks($css, $theme_name)
    {
        $no_media = '';
        $media_explode_arr = explode('@media', $css);
        $counter = 0;
        $visibility_block = [];
        $media_block = [];
        foreach ($media_explode_arr as $media_explode) {
            if ($counter == 0) {
                $no_media .= $media_explode;
            } else {
                $visibility = 0;
                $first = stripos($media_explode, '{');
                $media_name = trim(substr($media_explode, 0, $first));
                if (preg_match('/^\(min\-width\:[\s]{0,}([0-9]+)px\)[\s]{0,}and[\s]{0,}\(max\-width\:[\s]{0,}([0-9]+)px\)$/', $media_name, $matches)) {
                    $visibility = $matches[1] . 'w' . $matches[2];
                } elseif (preg_match('/^\(max\-width\:[\s]{0,}([0-9]+)px\)$/', $media_name, $matches)) {
                    $visibility = 'w' . $matches[1];
                } elseif (preg_match('/^\(min\-width\:[\s]{0,}([0-9]+)px\)$/', $media_name, $matches)) {
                    $visibility = $matches[1] . 'w';
                }
                if ($visibility) {
                    $vid_ar = tep_db_fetch_array(tep_db_query('select id from ' . TABLE_THEMES_SETTINGS . " where\r\n                    theme_name = '" . tep_db_input($theme_name) . "' and\r\n                    setting_group = 'extend' and\r\n                    setting_name = 'media_query' and\r\n                    setting_value = '" . tep_db_input($visibility) . "'\r\n                    "));
                    if (!$vid_ar) {
                        tep_db_perform(TABLE_THEMES_SETTINGS, ['theme_name' => $theme_name, 'setting_group' => 'extend', 'setting_name' => 'media_query', 'setting_value' => $visibility]);
                        $vid = tep_db_insert_id();
                    } else {
                        $vid = $vid_ar['id'];
                    }
                }
                $media_explode_tmp = preg_split('/\}[\s\n]+\}/', $media_explode);
                $no_media .= $media_explode_tmp[1];
                $block = trim(substr($media_explode_tmp[0], $first + 1)) . '}';
                if ($visibility) {
                    $visibility_block[$vid] = $block;
                } else {
                    $media_block[$media_name] = $block;
                }
            }
            $counter++;
        }
        return ['visibility' => $visibility_block, 'media' => $media_block, 'no_media' => $no_media];
    }
    public static function get_css($theme_name, $widgets = [], $page = '', $all = true, $cached_accessibility = null)
    {
        $css = '';
        $tab = '  ';
        $displacement = '';
        $by_media = [];
        $area_arr = [];
        if (!is_array($widgets) && !$widgets) {
            $widgets = [];
        } elseif (is_string($widgets) && $widgets) {
            $widgets = [$widgets];
        }
        if ($all && $page) {
            $area_arr[] = '';
            $area_arr[] = $page;
            foreach ($widgets as $widget) {
                $area_arr[] = $widget;
            }
            foreach ($widgets as $widget) {
                $area_arr[] = $page . ' ' . $widget;
            }
        } elseif (count($widgets) > 0 && $page) {
            foreach ($widgets as $widget) {
                $area_arr[] = $page . ' ' . $widget;
            }
        } elseif ($page) {
            $area_arr[] = $page;
        } elseif (count($widgets) > 0) {
            foreach ($widgets as $widget) {
                $area_arr[] = $widget;
            }
        }
        $main_styles = [];
        if (Yii::$app->controller->action->id != 'get-css') {
            $main_styles = self::main_styles($theme_name);
        }
        if (count($area_arr) == 1) {
            if ($cached_accessibility) {
                $reader = $cached_accessibility;
            } else {
                static $cmd = null;
                // unfortunately prepared queries has not enought effect
                if (is_null($cmd)) {
                    $cmd = \Yii::$app->db->create_command('select * from ' . TABLE_THEMES_STYLES . ' where theme_name = :theme and accessibility = :area order by accessibility, media, selector, attribute, visibility');
                }
                $reader = $cmd->bind_values([':theme' => tep_db_input($theme_name), ':area' => reset($area_arr)])->query();
            }
        } else {
            // it should not happen, but just in case
            $reader = \common\models\Themes_Styles::find()->where(['theme_name' => $theme_name])->order_by('accessibility, media, selector, attribute, visibility')->as_array();
            if (count($area_arr) > 0) {
                $reader = $reader->and_where(['accessibility' => $area_arr]);
            }
            $reader = $reader->each();
        }
        foreach ($reader as $item) {
            $v_arr = self::v_arr($item['visibility']);
            $visibility = '';
            foreach ($v_arr as $v_key => $v_item) {
                if ($v_item > 10) {
                    $visibility = $v_item;
                    unset($v_arr[$v_key]);
                }
            }
            if (self::$css_frontend && $item['accessibility'] && (strpos($item['accessibility'], '.b-') === 0 || strpos($item['accessibility'], '.s-') === 0) && strpos($item['selector'], $item['accessibility']) !== false) {
                $item['selector'] = trim(str_replace($item['accessibility'], '', $item['selector']));
            }
            if (count($v_arr) > 0) {
                $selector_arr = explode(',', $item['selector']);
                foreach ($selector_arr as $s_item => $class) {
                    if (in_array(2, $v_arr)) {
                        $selector_arr[$s_item] .= '.active';
                    }
                    if (in_array(3, $v_arr)) {
                        $selector_arr[$s_item] .= ':before';
                    }
                    if (in_array(4, $v_arr)) {
                        $selector_arr[$s_item] .= ':after';
                    }
                    if (in_array(1, $v_arr)) {
                        $selector_arr[$s_item] .= ':hover';
                    }
                }
                $item['selector'] = implode(', ', $selector_arr);
            }
            if (isset($item['value']) && isset($main_styles[$item['value']])) {
                $item['value'] = $main_styles[$item['value']];
            } elseif (isset($item['value']) && preg_match('/^(\$[0-9a-zA-Z\-\_]+\-)[0-9]+$/', $item['value'], $match)) {
                if (isset($main_styles[$match[1] . '1'])) {
                    $item['value'] = $main_styles[$match[1] . '1'];
                }
            }
            if ($item['attribute'] == 'background' && isset($item['value']) && str_contains($item['value'], '$')) {
                preg_match_all('/(\$[0-9a-zA-Z\-\_]+)/', $item['value'], $match2);
                foreach ($match2[0] as $color_var) {
                    if ($main_styles[$color_var] ?? false) {
                        $item['value'] = str_replace($color_var, $main_styles[$color_var], $item['value']);
                    } elseif (preg_match_all('/(\$[0-9a-zA-Z\-\_]+\-)[0-9]+/', $item['value'], $match3)) {
                        foreach ($match3[1] as $key => $color_var3) {
                            $item['value'] = str_replace($match3[0][$key], $main_styles[$color_var3 . '1'], $item['value']);
                        }
                    }
                }
            }
            if ($visibility) {
                $by_media['visibility'][$visibility][$item['selector']][$item['attribute']] = $item['value'];
            } elseif ($item['media']) {
                $by_media['media'][$item['media']][$item['selector']][$item['attribute']] = $item['value'];
            } else {
                $by_media['general'][$item['selector']][$item['attribute']] = $item['value'];
            }
        }
        if (!self::$css_frontend && (count($widgets) == 0 || $widgets[0] == 'block_box')) {
            $boxes = tep_db_query('
            select bs.box_id, bs.setting_value, bs.visibility, bs.setting_name
            from ' . TABLE_DESIGN_BOXES_TMP . ' b, ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs \r\n            where \r\n                b.theme_name = '" . tep_db_input($theme_name) . "' and\r\n                b.id = bs.box_id\r\n            ");
            while ($item = tep_db_fetch_array($boxes)) {
                if (!in_array($item['setting_name'], self::$attributes_have_rules) && !in_array($item['setting_name'], self::$attributes_no_rules) && !in_array($item['setting_name'], self::$attributes_has_measure)) {
                    continue;
                }
                $v_arr = self::v_arr($item['visibility']);
                $visibility = '';
                foreach ($v_arr as $v_key => $v_item) {
                    if ($v_item > 10) {
                        $visibility = $v_item;
                        unset($v_arr[$v_key]);
                    }
                }
                $selector = '#box-' . $item['box_id'];
                if (count($v_arr) > 0) {
                    $selector_arr = explode(',', $selector);
                    foreach ($selector_arr as $s_item => $class) {
                        if (in_array(2, $v_arr)) {
                            $selector_arr[$s_item] .= '.active';
                        }
                        if (in_array(3, $v_arr)) {
                            $selector_arr[$s_item] .= ':before';
                        }
                        if (in_array(4, $v_arr)) {
                            $selector_arr[$s_item] .= ':after';
                        }
                        if (in_array(1, $v_arr)) {
                            $selector_arr[$s_item] .= ':hover';
                        }
                    }
                    $selector = implode(', ', $selector_arr);
                }
                if ($visibility) {
                    $by_media['visibility'][$visibility][$selector][$item['setting_name']] = $item['setting_value'];
                } else {
                    $by_media['general'][$selector][$item['setting_name']] = $item['setting_value'];
                }
            }
        }
        $css_arr = ['general' => '', 'visibility' => '', 'media' => ''];
        foreach ($by_media as $key => $item) {
            if ($key == 'general') {
                $css_arr['general'] = $css_arr['general'] . self::get_css_media($item, '', $tab, $displacement);
            } elseif ($key == 'visibility') {
                static $cached_media_sizes = [];
                if (!is_array($media_sizes[tep_db_input($theme_name)] ?? null)) {
                    $media_sizes = [];
                    $media_sizes_query = tep_db_query('select id, setting_value from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($theme_name) . "' and setting_group = 'extend' and setting_name = 'media_query'");
                    while ($media_size = tep_db_fetch_array($media_sizes_query)) {
                        $arr2 = explode('w', $media_size['setting_value']);
                        if (isset($arr2[0]) && $arr2[0]) {
                            $media_sizes[(int) ($arr2[0] . '0')] = $media_size['id'];
                        }
                        if (isset($arr2[1]) && $arr2[1]) {
                            $media_sizes[(int) $arr2[1]] = $media_size['id'];
                        }
                    }
                    krsort($media_sizes);
                    $cached_media_sizes[tep_db_input($theme_name)] = $media_sizes;
                } else {
                    $media_sizes = $cached_media_sizes[tep_db_input($theme_name)];
                }
                foreach ($media_sizes as $media) {
                    $arr = $item[$media] ?? null;
                    $query = tep_db_fetch_array(tep_db_query('select setting_value from ' . TABLE_THEMES_SETTINGS . " where id = '" . $media . "'"));
                    $arr2 = explode('w', $query['setting_value']);
                    $media = '';
                    if (isset($arr2[0]) && $arr2[0]) {
                        $media .= '(min-width:' . $arr2[0] . 'px)';
                    }
                    if (isset($arr2[0]) && $arr2[0] && isset($arr2[1]) && $arr2[1]) {
                        $media .= ' and ';
                    }
                    if (isset($arr2[1]) && $arr2[1]) {
                        $media .= '(max-width:' . $arr2[1] . 'px)';
                    }
                    $css_arr['visibility'] = $css_arr['visibility'] . self::get_css_media($arr, $media, $tab, $displacement);
                }
            } elseif ($key == 'media') {
                foreach ($item as $media => $arr) {
                    $css_arr['media'] = $css_arr['media'] . self::get_css_media($arr, $media, $tab, $displacement);
                }
            }
        }
        $css .= $css_arr['general'];
        $css .= $css_arr['visibility'];
        $css .= $css_arr['media'];
        return $css;
    }
    public static function get_css_media($arr, $media, $tab, $displacement)
    {
        if (!is_array($arr)) {
            return '';
        }
        $br = "\n";
        if (self::$css_frontend) {
            //$displacement = '';
            //$br = '';
        }
        $css = '';
        if ($media) {
            $css .= $displacement . '@media ' . $media . ' {' . $br;
            $displacement = $displacement . $tab;
        }
        foreach ($arr as $key => $item) {
            $css .= $displacement . $key . ' {' . $br;
            $displacement = $displacement . $tab;
            $css .= self::get_attributes($item, $displacement, $br, true);
            $displacement = substr($displacement, strlen($tab));
            $css .= $displacement . '}' . $br;
        }
        if ($media) {
            $displacement = substr($displacement, strlen($tab));
            $css .= $displacement . '}' . $br;
        }
        return $css;
    }
    public static $attributes_have_rules = ['rotate', 'content', 'display', 'left_measure', 'right_measure', 'width_measure', 'min_width_measure', 'max_width_measure', 'height_measure', 'min_height_measure', 'max_height_measure', 'p_width', 'font-family', 'font_size_measure', 'line_height_measure', 'text_shadow_left', 'text_shadow_left_measure', 'text_shadow_top', 'text_shadow_top_measure', 'text_shadow_size', 'text_shadow_size_measure', 'text_shadow_color', 'box_shadow_blur', 'box_shadow_blur_measure', 'box_shadow_spread', 'box_shadow_spread_measure', 'box_shadow_color', 'box_shadow_left', 'box_shadow_left_measure', 'box_shadow_top', 'box_shadow_top_measure', 'box_shadow_set', 'background_image', 'padding-top', 'padding-left', 'padding-right', 'padding-bottom', 'padding_top_measure', 'padding_left_measure', 'padding_right_measure', 'padding_bottom_measure', 'margin-top', 'margin-left', 'margin-right', 'margin-bottom', 'margin_top_measure', 'margin_left_measure', 'margin_right_measure', 'margin_bottom_measure', 'border-top-width', 'border_top_width_measure', 'border-top-color', 'border-top-style', 'border-left-width', 'border_left_width_measure', 'border-left-color', 'border-left-style', 'border-right-width', 'border_right_width_measure', 'border-right-style', 'border-right-color', 'border-bottom-width', 'border_bottom_width_measure', 'border-bottom-style', 'border-bottom-color', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius', 'border_radius_1_measure', 'border_radius_2_measure', 'border_radius_3_measure', 'border_radius_4_measure', 'display_none', 'box_align', 'line-height'];
    //these attributes have rules
    public static $attributes_no_rules = ['animation-delay', 'background', 'background-attachment', 'background-clip', 'background-color', 'background-origin', 'background-position', 'background-position-x', 'background-position-y', 'background-repeat', 'background-size', 'border', 'border-bottom', 'border-collapse', 'border-color', 'border-image', 'border-left', 'border-radius', 'border-right', 'border-spacing', 'border-style', 'border-top', 'border-width', 'box-shadow', 'box-sizing', 'caption-side', 'clear', 'clip', 'color', 'column-count', 'column-gap', 'column-rule', 'column-width', 'columns', 'counter-increment', 'counter-reset', 'cursor', 'direction', 'empty-cells', 'filter', 'float', 'font', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'hasLayout', 'hyphens', 'image-rendering', 'letter-spacing', 'list-style', 'list-style-image', 'list-style-position', 'list-style-type', 'opacity', 'orphans', 'outline', 'outline-color', 'outline-offset', 'outline-style', 'outline-width', 'overflow', 'overflow-x', 'overflow-y', 'page-break-after', 'page-break-before', 'page-break-inside', 'position', 'quotes', 'resize', 'scrollbar-3dlight-color', 'scrollbar-arrow-color', 'scrollbar-base-color', 'scrollbar-darkshadow-color', 'scrollbar-face-color', 'scrollbar-highlight-color', 'scrollbar-shadow-color', 'scrollbar-track-color', 'tab-size', 'table-layout', 'text-align', 'text-align-last', 'text-decoration', 'text-decoration-color', 'text-decoration-line', 'text-decoration-style', 'text-indent', 'text-overflow', 'text-shadow', 'text-transform', 'transform', 'transform-origin', 'transform-style', 'transition', 'transition-delay', 'transition-property', 'transition-timing-function', 'unicode-bidi', 'vertical-align', 'visibility', 'white-space', 'widows', 'word-break', 'word-spacing', 'word-wrap', 'writing-mode', 'z-index', 'zoom', 'flex-direction', 'flex-wrap', 'flex-flow', 'justify-content', 'align-items', 'align-content', 'margin', 'padding'];
    public static $attributes_has_measure = ['top', 'left', 'right', 'bottom', 'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'font-size'];
    public static function get_attributes($attributes, $displacement = '', $br = '', $any_attributes = false)
    {
        if (self::$css_frontend) {
            //$displacement = '';
            //$br = '';
        }
        $style = '';
        $attributes_have_rules = self::$attributes_have_rules;
        $attributes_no_rules = self::$attributes_no_rules;
        $attributes_has_measure = self::$attributes_has_measure;
        $important_arr = [];
        if (is_array($attributes)) {
            foreach ($attributes as $attr => $val) {
                if (isset($val)) {
                    if ($val == 'important') {
                        $important_arr[str_replace('_important', '', $attr)] = '!important';
                        unset($attributes[$attr]);
                    }
                }
            }
            foreach ($attributes as $attr => $val) {
                if (isset($val)) {
                    if (in_array($attr, $attributes_no_rules)) {
                        $style .= $displacement . $attr . ': ' . $val . (isset($important_arr[$attr]) ? $important_arr[$attr] : '') . ';' . $br;
                    } elseif (in_array($attr, $attributes_has_measure)) {
                        $style .= $displacement . $attr . ':' . $val . ($val == '0' ? '' : self::dimension(@$attributes[str_replace('-', '_', $attr) . '_measure'], '', $val)) . (isset($important_arr[$attr]) ? $important_arr[$attr] : '') . ';' . $br;
                    } elseif ($any_attributes && !in_array($attr, $attributes_have_rules) && !in_array($attr, $attributes_no_rules) && !in_array($attr, $attributes_has_measure) && !strpos($attr, '_measure') !== false) {
                        $style .= $displacement . $attr . ': ' . $val . Array_Helper::get_value($important_arr, $attr) . ';' . $br;
                    }
                }
            }
        }
        if (isset($attributes['display']) && $attributes['display'] == 'flex' && !$displacement) {
            $style .= 'display:-ms-flexbox;';
        }
        if (isset($attributes['flex-grow']) && !$displacement) {
            $style .= '-ms-flex:' . $attributes['flex-grow'] . ';';
        }
        if (isset($attributes['align-items']) && !$displacement) {
            if ($attributes['align-items'] == 'flex-start') {
                $flex_attr = 'start';
            } elseif ($attributes['align-items'] == 'flex-end') {
                $flex_attr = 'end';
            } else {
                $flex_attr = $attributes['align-items'];
            }
            $style .= '-ms-flex-align:' . $flex_attr . ';';
        }
        if (isset($attributes['line-height'])) {
            $style .= $displacement . 'line-height:' . $attributes['line-height'] . (isset($attributes['line_height_measure']) ? self::dimension($attributes['line_height_measure']) : '') . ';' . $br;
        }
        if (isset($attributes['rotate'])) {
            $style .= $displacement . '-ms-transform:rotate(' . $attributes['rotate'] . 'deg);-webkit-transform:rotate(' . $attributes['rotate'] . 'deg);transform:rotate(' . $attributes['rotate'] . 'deg)' . Array_Helper::get_value($important_arr, 'rotate') . ';' . $br;
        }
        if (isset($attributes['content']) && $attributes['content']) {
            $style .= $displacement . 'content:\'' . ($attributes['content'] == '\_' ? '' : $attributes['content']) . '\'' . (isset($important_arr['content']) ? $important_arr['content'] : '') . ';' . $br;
        }
        if (isset($attributes['display']) && $attributes['display']) {
            $style .= $displacement . 'display:' . $attributes['display'] . (isset($important_arr['display']) ? $important_arr['display'] : '') . ';' . $br;
            /*if ($attributes['display'] == 'none' && \frontend\design\Info::isAdmin()) {
                  $style .= $displacement . 'opacity: 0.2;' . $br;
              } else {
                  $style .= $displacement . 'display:' . $attributes['display'] . ';' . $br;
              }*/
        }
        $important_arr['font-family'] = $important_arr['font-family'] ?? null;
        if (isset($attributes['p_width'])) {
            $style .= $displacement . 'width:' . ($attributes['p_width'] - $attributes['padding-left'] - $attributes['padding-right'] - $attributes['border-left-width'] - $attributes['border-right-width']) . 'px;' . $br;
        }
        $to_pdf = 0;
        if (method_exists(Yii::$app->request, 'get')) {
            $to_pdf = (int) Yii::$app->request->get('to_pdf', 0);
        }
        if (isset($attributes['font-family']) && !$to_pdf) {
            if (Yii::$app->controller->action->id == 'get-css' || stripos($attributes['font-family'], "'") !== false || stripos($attributes['font-family'], '"') !== false) {
                $style .= $displacement . 'font-family:' . $attributes['font-family'] . '' . $important_arr['font-family'] . ';' . $br;
            } else if ($attributes['font-family'] == 'inherit') {
                $style .= $displacement . 'font-family:inherit' . $important_arr['font-family'] . ';' . $br;
            } else {
                $style .= $displacement . 'font-family:\'' . $attributes['font-family'] . '\', Verdana, Arial, sans-serif' . (isset($important_arr['font-family']) ? $important_arr['font-family'] : '') . ';' . $br;
            }
        }
        if (isset($attributes['text_shadow_left']) || isset($attributes['text_shadow_top']) || isset($attributes['text_shadow_size']) || isset($attributes['text_shadow_color']) && $attributes['text_shadow_color']) {
            $text_shadow_left = $attributes['text_shadow_left'];
            $text_shadow_top = $attributes['text_shadow_top'];
            $text_shadow_size = $attributes['text_shadow_size'];
            $text_shadow_color = $attributes['text_shadow_color'];
            if ($text_shadow_left) {
                $text_shadow_left .= 'px';
            } else {
                $text_shadow_left = '0';
            }
            if ($text_shadow_top) {
                $text_shadow_top .= 'px';
            } else {
                $text_shadow_top = '0';
            }
            if ($text_shadow_size) {
                $text_shadow_size .= 'px';
            } else {
                $text_shadow_size = '0';
            }
            if ($text_shadow_size && $text_shadow_color) {
                $style .= $displacement . 'text-shadow:' . $text_shadow_left . ' ' . $text_shadow_top . ' ' . $text_shadow_size . ' ' . $text_shadow_color . Array_Helper::get_value($important_arr, 'text-shadow') . ';' . $br;
            }
        }
        if ((isset($attributes['box_shadow_blur']) || isset($attributes['box_shadow_spread'])) && $attributes['box_shadow_color']) {
            $box_shadow_left = $attributes['box_shadow_left'] ?? null;
            $box_shadow_top = $attributes['box_shadow_top'] ?? null;
            $box_shadow_blur = $attributes['box_shadow_blur'] ?? null;
            $box_shadow_spread = $attributes['box_shadow_spread'] ?? null;
            if ($box_shadow_left) {
                $box_shadow_left .= 'px';
            } else {
                $box_shadow_left = '0';
            }
            if ($box_shadow_top) {
                $box_shadow_top .= 'px';
            } else {
                $box_shadow_top = '0';
            }
            if ($box_shadow_blur) {
                $box_shadow_blur .= 'px';
            } else {
                $box_shadow_blur = '0';
            }
            if ($box_shadow_spread) {
                $box_shadow_spread .= 'px';
            } else {
                $box_shadow_spread = '0';
            }
            $style .= $displacement . 'box-shadow:' . Array_Helper::get_value($attributes, 'box_shadow_set') . ' ' . $box_shadow_left . ' ' . $box_shadow_top . ' ' . $box_shadow_blur . ' ' . $box_shadow_spread . ' ' . $attributes['box_shadow_color'] . Array_Helper::get_value($important_arr, 'box-shadow') . ';' . $br;
        }
        if (isset($attributes['background_image']) && $attributes['background_image']) {
            $style .= $displacement . 'background-image:url(\'' . \frontend\design\Info::theme_image($attributes['background_image']) . '\')' . Array_Helper::get_value($important_arr, 'background-image') . ';' . $br;
        }
        $border_top = '';
        $border_left = '';
        $border_right = '';
        $border_bottom = '';
        $attributes['border_left_width_measure'] = $attributes['border_left_width_measure'] ?? null;
        $attributes['border_top_width_measure'] = $attributes['border_top_width_measure'] ?? null;
        $important_arr['border-top-width'] = $important_arr['border-top-width'] ?? null;
        if (isset($attributes['border-top-width']) && Array_Helper::get_value($attributes, 'border-top-color')) {
            $border_top = $attributes['border-top-width'] . self::dimension(@$attributes['border_top_width_measure']) . (isset($attributes['border-top-style']) && !empty($attributes['border-top-style']) ? ' ' . $attributes['border-top-style'] . ' ' : ' solid ') . $attributes['border-top-color'];
        } else {
            if (isset($attributes['border-top-width'])) {
                $style .= $displacement . 'border-top-width:' . $attributes['border-top-width'] . self::dimension($attributes['border_top_width_measure']) . $important_arr['border-top-width'] . ';' . $br;
            }
            if (isset($attributes['border-top-color']) && $attributes['border-top-color']) {
                $style .= $displacement . 'border-top-color:' . $attributes['border-top-color'] . Array_Helper::get_value($important_arr, 'border-top-color') . ';' . $br;
            }
            if (isset($attributes['border-top-style']) && $attributes['border-top-style']) {
                $style .= $displacement . 'border-top-style:' . $attributes['border-top-style'] . $important_arr['border-top-style'] . ';' . $br;
            }
        }
        if (isset($attributes['border-left-width']) && isset($attributes['border-left-color']) && $attributes['border-left-color']) {
            $border_left = $attributes['border-left-width'] . self::dimension(@$attributes['border_left_width_measure']) . (isset($attributes['border-left-style']) && !empty($attributes['border-left-style']) ? ' ' . $attributes['border-left-style'] . ' ' : ' solid ') . $attributes['border-left-color'];
        } else {
            if (isset($attributes['border-left-width'])) {
                $style .= $displacement . 'border-left-width:' . $attributes['border-left-width'] . self::dimension(@$attributes['border_left_width_measure']) . ($important_arr['border-left-width'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-left-color']) && $attributes['border-left-color']) {
                $style .= $displacement . 'border-left-color:' . $attributes['border-left-color'] . ($important_arr['border-left-color'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-left-style']) && $attributes['border-left-style']) {
                $style .= $displacement . 'border-left-style:' . $attributes['border-left-style'] . ($important_arr['border-left-style'] ?? '') . ';' . $br;
            }
        }
        if (isset($attributes['border-right-width']) && isset($attributes['border-right-color'])) {
            $border_right = $attributes['border-right-width'] . self::dimension(@$attributes['border_right_width_measure']) . (isset($attributes['border-right-style']) && !empty($attributes['border-right-style']) ? ' ' . $attributes['border-right-style'] . ' ' : ' solid ') . $attributes['border-right-color'];
        } else {
            if (isset($attributes['border-right-width'])) {
                $style .= $displacement . 'border-right-width:' . $attributes['border-right-width'] . self::dimension(@$attributes['border_right_width_measure']) . ($important_arr['border-right-width'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-right-color']) && $attributes['border-right-color']) {
                $style .= $displacement . 'border-right-color:' . $attributes['border-right-color'] . ($important_arr['border-right-color'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-right-style']) && $attributes['border-right-style']) {
                $style .= $displacement . 'border-right-style:' . $attributes['border-right-style'] . ($important_arr['border-right-style'] ?? '') . ';' . $br;
            }
        }
        $attributes['border-bottom-color'] = $attributes['border-bottom-color'] ?? null;
        if (isset($attributes['border-bottom-width']) && $attributes['border-bottom-color']) {
            $border_bottom = $attributes['border-bottom-width'] . self::dimension(@$attributes['border_bottom_width_measure']) . (isset($attributes['border-bottom-style']) && !empty($attributes['border-bottom-style']) ? ' ' . $attributes['border-bottom-style'] . ' ' : ' solid ') . $attributes['border-bottom-color'];
        } else {
            if (isset($attributes['border-bottom-width'])) {
                $style .= $displacement . 'border-bottom-width:' . $attributes['border-bottom-width'] . self::dimension(@$attributes['border_bottom_width_measure']) . ($important_arr['border-bottom-width'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-bottom-color']) && $attributes['border-bottom-color']) {
                $style .= $displacement . 'border-bottom-color:' . $attributes['border-bottom-color'] . ($important_arr['border-bottom-color'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-bottom-style']) && $attributes['border-bottom-style']) {
                $style .= $displacement . 'border-bottom-style:' . $attributes['border-bottom-style'] . ($important_arr['border-bottom-style'] ?? '') . ';' . $br;
            }
        }
        if ($border_top && $border_top == $border_left && $border_top == $border_right && $border_top == $border_bottom) {
            $style .= $displacement . 'border:' . $border_top . self::css_important($important_arr, 'border') . ';' . $br;
        } else {
            if ($border_top) {
                $style .= $displacement . 'border-top:' . $border_top . ($important_arr['border-top'] ?? '') . ';' . $br;
            }
            if ($border_left) {
                $style .= $displacement . 'border-left:' . $border_left . ($important_arr['border-left'] ?? '') . ';' . $br;
            }
            if ($border_right) {
                $style .= $displacement . 'border-right:' . $border_right . ($important_arr['border-right'] ?? '') . ';' . $br;
            }
            if ($border_bottom) {
                $style .= $displacement . 'border-bottom:' . $border_bottom . ($important_arr['border-bottom'] ?? '') . ';' . $br;
            }
        }
        $attributes['border_radius_1_measure'] = $attributes['border_radius_1_measure'] ?? null;
        $attributes['border_radius_2_measure'] = $attributes['border_radius_2_measure'] ?? null;
        $attributes['border_radius_3_measure'] = $attributes['border_radius_3_measure'] ?? null;
        $attributes['border_radius_4_measure'] = $attributes['border_radius_4_measure'] ?? null;
        $important_arr['border-radius'] = $important_arr['border-radius'] ?? null;
        if (isset($attributes['border-top-left-radius']) && isset($attributes['border-top-right-radius']) && isset($attributes['border-bottom-right-radius']) && isset($attributes['border-bottom-left-radius'])) {
            $style .= $displacement . 'border-radius:' . $attributes['border-top-left-radius'] . self::dimension($attributes['border_radius_1_measure']) . ' ' . $attributes['border-top-right-radius'] . self::dimension($attributes['border_radius_2_measure']) . ' ' . $attributes['border-bottom-right-radius'] . self::dimension($attributes['border_radius_3_measure']) . ' ' . $attributes['border-bottom-left-radius'] . self::dimension($attributes['border_radius_4_measure']) . $important_arr['border-radius'] . ';' . $br;
        } else {
            if (isset($attributes['border-top-left-radius'])) {
                $style .= $displacement . 'border-top-left-radius:' . $attributes['border-top-left-radius'] . self::dimension($attributes['border_radius_1_measure']) . ($important_arr['border-top-left-radius'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-top-right-radius'])) {
                $style .= $displacement . 'border-top-right-radius:' . $attributes['border-top-right-radius'] . self::dimension($attributes['border_radius_2_measure']) . ($important_arr['border-top-right-radius'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-bottom-right-radius'])) {
                $style .= $displacement . 'border-bottom-right-radius:' . $attributes['border-bottom-right-radius'] . self::dimension($attributes['border_radius_3_measure']) . ($important_arr['border-bottom-right-radius'] ?? '') . ';' . $br;
            }
            if (isset($attributes['border-bottom-left-radius'])) {
                $style .= $displacement . 'border-bottom-left-radius:' . $attributes['border-bottom-left-radius'] . self::dimension($attributes['border_radius_4_measure']) . ($important_arr['border-bottom-left-radius'] ?? '') . ';' . $br;
            }
        }
        if (isset($attributes['padding-top']) && isset($attributes['padding-left']) && isset($attributes['padding-right']) && isset($attributes['padding-bottom'])) {
            if ($attributes['padding-top'] == $attributes['padding-bottom'] && $attributes['padding-left'] == $attributes['padding-right']) {
                $style .= $displacement . 'padding:' . $attributes['padding-top'] . self::dimension(@$attributes['padding_top_measure']) . ' ' . $attributes['padding-right'] . self::dimension(@$attributes['padding_right_measure']) . self::css_important($important_arr, 'padding') . ';' . $br;
            } elseif ($attributes['padding-left'] == $attributes['padding-right']) {
                $style .= $displacement . 'padding:' . $attributes['padding-top'] . self::dimension(@$attributes['padding_top_measure']) . ' ' . $attributes['padding-right'] . self::dimension(@$attributes['padding_right_measure']) . ' ' . $attributes['padding-bottom'] . self::dimension(@$attributes['padding_bottom_measure']) . self::css_important($important_arr, 'padding') . ';' . $br;
            } else {
                $style .= $displacement . 'padding:' . $attributes['padding-top'] . self::dimension(@$attributes['padding_top_measure']) . ' ' . $attributes['padding-right'] . self::dimension(@$attributes['padding_right_measure']) . ' ' . $attributes['padding-bottom'] . self::dimension(@$attributes['padding_bottom_measure']) . ' ' . $attributes['padding-left'] . self::dimension(@$attributes['padding_left_measure']) . self::css_important($important_arr, 'padding') . ';' . $br;
            }
        } else {
            if (isset($attributes['padding-top'])) {
                $style .= $displacement . 'padding-top:' . $attributes['padding-top'] . self::dimension(@$attributes['padding_top_measure']) . (isset($important_arr['padding-top']) ? $important_arr['padding-top'] : '') . ';' . $br;
            }
            if (isset($attributes['padding-right'])) {
                $style .= $displacement . 'padding-right:' . $attributes['padding-right'] . self::dimension(@$attributes['padding_right_measure']) . (isset($important_arr['padding-right']) ? $important_arr['padding-right'] : '') . ';' . $br;
            }
            if (isset($attributes['padding-bottom'])) {
                $style .= $displacement . 'padding-bottom:' . $attributes['padding-bottom'] . self::dimension(@$attributes['padding_bottom_measure']) . (isset($important_arr['padding-bottom']) ? $important_arr['padding-bottom'] : '') . ';' . $br;
            }
            if (isset($attributes['padding-left'])) {
                $style .= $displacement . 'padding-left:' . $attributes['padding-left'] . self::dimension(@$attributes['padding_left_measure']) . (isset($important_arr['padding-left']) ? $important_arr['padding-left'] : '') . ';' . $br;
            }
        }
        if (isset($attributes['margin-top']) && isset($attributes['margin-left']) && isset($attributes['margin-right']) && isset($attributes['margin-bottom'])) {
            $attributes['margin_top_measure'] = $attributes['margin_top_measure'] ?? null;
            $attributes['margin_right_measure'] = $attributes['margin_right_measure'] ?? null;
            $attributes['margin_bottom_measure'] = $attributes['margin_bottom_measure'] ?? null;
            $attributes['margin_left_measure'] = $attributes['margin_left_measure'] ?? null;
            if ($attributes['margin-top'] == $attributes['margin-bottom'] && $attributes['margin-left'] == $attributes['margin-right']) {
                $style .= $displacement . 'margin:' . $attributes['margin-top'] . self::dimension($attributes['margin_top_measure']) . ' ' . $attributes['margin-right'] . self::dimension($attributes['margin_right_measure'], '', $attributes['margin-right']) . self::css_important($important_arr, 'margin') . ';' . $br;
            } elseif ($attributes['margin-left'] == $attributes['margin-right']) {
                $style .= $displacement . 'margin:' . $attributes['margin-top'] . self::dimension($attributes['margin_top_measure']) . ' ' . ($attributes['margin_right_measure'] == 'auto' ? 'auto' : $attributes['margin-right'] . self::dimension($attributes['margin_right_measure'], '', $attributes['margin-right'])) . ' ' . $attributes['margin-bottom'] . self::dimension($attributes['margin_bottom_measure']) . self::css_important($important_arr, 'margin') . ';' . $br;
            } elseif ($attributes['margin_right_measure'] == 'auto') {
                $style .= $displacement . 'margin:' . $attributes['margin-top'] . self::dimension($attributes['margin_top_measure']) . ' auto ' . $attributes['margin-bottom'] . self::dimension($attributes['margin_bottom_measure']) . self::css_important($important_arr, 'margin') . ';' . $br;
            } else {
                $style .= $displacement . 'margin:' . $attributes['margin-top'] . self::dimension($attributes['margin_top_measure']) . ' ' . ($attributes['margin_right_measure'] == 'auto' ? 'auto' : $attributes['margin-right'] . self::dimension($attributes['margin_right_measure'], '', $attributes['margin-right'])) . ' ' . $attributes['margin-bottom'] . self::dimension($attributes['margin_bottom_measure']) . ' ' . ($attributes['margin_left_measure'] == 'auto' ? 'auto' : $attributes['margin-left'] . self::dimension($attributes['margin_left_measure'], '', $attributes['margin-left'])) . self::css_important($important_arr, 'margin') . ';' . $br;
            }
        } else {
            if (isset($attributes['margin-top'])) {
                $style .= $displacement . 'margin-top:' . $attributes['margin-top'] . self::dimension(@$attributes['margin_top_measure']) . ($important_arr['margin-top'] ?? '') . ';' . $br;
            }
            if (isset($attributes['margin-right'])) {
                $style .= $displacement . 'margin-right:' . $attributes['margin-right'] . self::dimension(@$attributes['margin_right_measure'], '', $attributes['margin-right']) . ($important_arr['margin-right'] ?? '') . ';' . $br;
            } elseif (isset($attributes['margin_right_measure']) && $attributes['margin_right_measure'] == 'auto') {
                $style .= $displacement . 'margin-right: auto' . ($important_arr['margin-right'] ?? '') . ';' . $br;
            }
            if (isset($attributes['margin-bottom'])) {
                $style .= $displacement . 'margin-bottom:' . $attributes['margin-bottom'] . self::dimension(@$attributes['margin_bottom_measure']) . ($important_arr['margin-bottom'] ?? '') . ';' . $br;
            }
            if (isset($attributes['margin-left'])) {
                $style .= $displacement . 'margin-left:' . $attributes['margin-left'] . self::dimension(@$attributes['margin_left_measure'], '', $attributes['margin-left']) . ($important_arr['margin-left'] ?? '') . ';' . $br;
            } elseif (isset($attributes['margin_right_measure']) && $attributes['margin_right_measure'] == 'auto') {
                $style .= $displacement . 'margin-left: auto' . ($important_arr['margin-left'] ?? '') . ';' . $br;
            }
        }
        return $style;
    }
    public static function dimension($dimension, $default = '', $value = '')
    {
        if ($value && !preg_match('/^([\-0-9\.]+)$/', $value, $matches)) {
            return '';
        }
        if ($dimension) {
            if ($dimension == 'pr') {
                $dimension = '%';
            }
            $text = $dimension;
        } else {
            if ($default == '') {
                $default = 'px';
            }
            $text = $default;
        }
        return $text;
    }
    public static function css_important($important_arr, $attr)
    {
        if (isset($important_arr[$attr]) && $important_arr[$attr] || isset($important_arr[$attr . '-top']) && $important_arr[$attr . '-top'] || isset($important_arr[$attr . '-left']) && $important_arr[$attr . '-left'] || isset($important_arr[$attr . '-right']) && $important_arr[$attr . '-right'] || isset($important_arr[$attr . '-bottom']) && $important_arr[$attr . '-bottom']) {
            return '!important';
        }
    }
    public static function v_arr($visibility, $string = false)
    {
        $arr = explode(',', $visibility);
        foreach ($arr as $key => $item) {
            if ($string) {
                $arr[$key] = trim($item);
            } else {
                $arr[$key] = (int) trim($item);
            }
        }
        return $arr;
    }
    public static function v_str($arr, $string = false)
    {
        foreach ($arr as $key => $item) {
            if ($string) {
                $arr[$key] = trim($item);
            } else {
                $arr[$key] = (int) trim($item);
            }
        }
        $str = implode(',', $arr);
        if (!$str) {
            $str = '';
        }
        return $str;
    }
    private static function add_theme_style_cache_record($theme_name, $accessibility, $accessibility_styles)
    {
        $css = self::get_css($theme_name, [$accessibility], '', true, $accessibility_styles);
        $sql_data_array = ['theme_name' => $theme_name, 'accessibility' => $accessibility, 'css' => $css];
        tep_db_perform(TABLE_THEMES_STYLES_CACHE, $sql_data_array);
        return $css;
    }
    public static function create_cache($theme_name, $accessibility = false, $need_delete = true)
    {
        if ($accessibility == 'all') {
            $accessibility = false;
        } elseif ($accessibility == 'main') {
            $accessibility = '';
        }
        self::$css_frontend = true;
        $themes_path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR;
        if ($need_delete) {
            tep_db_query('delete from ' . TABLE_THEMES_STYLES_CACHE . " where theme_name = '" . tep_db_input($theme_name) . "'" . ($accessibility !== false ? " and accessibility = '" . $accessibility . "'" : ''));
        }
        $bottom = '';
        $basic_themes_path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR . 'basic' . DIRECTORY_SEPARATOR;
        if (file_exists($basic_themes_path . 'css' . DIRECTORY_SEPARATOR . 'bottom.css')) {
            $bottom = file_get_contents($basic_themes_path . 'css' . DIRECTORY_SEPARATOR . 'bottom.css');
        }
        if ($accessibility === false) {
            $query = \common\models\Themes_Styles::find()->where(['theme_name' => $theme_name])->order_by('accessibility, media, selector, attribute, visibility')->as_array();
            $accessibility_styles = [];
            $prev_accessibility = null;
            foreach ($query->each() as $item) {
                if ($item['accessibility'] != $prev_accessibility && !empty($accessibility_styles)) {
                    $css = self::add_theme_style_cache_record($theme_name, $prev_accessibility, $accessibility_styles);
                    if ($prev_accessibility == '.b-bottom') {
                        $bottom .= $css;
                    }
                    $accessibility_styles = [];
                }
                $prev_accessibility = $item['accessibility'];
                $accessibility_styles[] = $item;
            }
            if (!empty($accessibility_styles)) {
                $css = self::add_theme_style_cache_record($theme_name, $prev_accessibility, $accessibility_styles);
                if ($prev_accessibility == '.b-bottom') {
                    $bottom .= $css;
                }
            }
            unset($accessibility_styles);
            $bottom = \frontend\design\Info::minify_css($bottom);
            $file_path = $themes_path . 'css' . DIRECTORY_SEPARATOR;
            \yii\helpers\File_Helper::create_directory($file_path);
            file_put_contents($file_path . 'style.css', $bottom);
        } else {
            $css = self::get_css($theme_name, [$accessibility]);
            $sql_data_array = ['theme_name' => $theme_name, 'accessibility' => $accessibility, 'css' => $css];
            tep_db_perform(TABLE_THEMES_STYLES_CACHE, $sql_data_array);
            if ($accessibility == '.b-bottom') {
                $bottom .= $css;
                $bottom = \frontend\design\Info::minify_css($bottom);
                $file_path = $themes_path . 'css' . DIRECTORY_SEPARATOR;
                \yii\helpers\File_Helper::create_directory($file_path);
                file_put_contents($file_path . 'style.css', $bottom);
            }
        }
        if (file_exists($themes_path . 'cache' . DIRECTORY_SEPARATOR)) {
            \yii\helpers\File_Helper::remove_directory($themes_path . 'cache' . DIRECTORY_SEPARATOR);
        }
    }
    public static function compare_attributes($attr1, $attr2)
    {
        if ($attr1['selector'] == $attr2['selector'] && $attr1['attribute'] == $attr2['attribute'] && $attr1['visibility'] == $attr2['visibility'] && $attr1['media'] == $attr2['media'] && $attr1['accessibility'] == $attr2['accessibility']) {
            return true;
        } else {
            return false;
        }
    }
    public static function get_one_attribute($attr, $old = false)
    {
        $arr['selector'] = $attr['selector'];
        $arr['attribute'] = $attr['attribute'];
        $arr['value'] = $attr['value'];
        if ($old) {
            $arr['value_old'] = $attr['value_old'];
        }
        $arr['visibility'] = $attr['visibility'];
        $arr['media'] = $attr['media'];
        $arr['accessibility'] = $attr['accessibility'];
        return $arr;
    }
    /*
     * Parsing string with form data.
     * Using when the form has too many inputs
     * */
    public static function params_from_one_input($values)
    {
        if (is_array($values)) {
            $params1 = $values;
        } else {
            $params1 = json_decode($values, true);
        }
        $params = [];
        if (!is_array($params1)) {
            return '';
        }
        foreach ($params1 as $key => $value) {
            $keys = explode('[', $key);
            foreach ($keys as $i => $val) {
                $keys[$i] = str_replace(']', '', $val);
            }
            if (isset($keys[0])) {
                if (isset($keys[1])) {
                    if (isset($keys[2])) {
                        if (isset($keys[3])) {
                            if (isset($keys[4])) {
                                if (isset($keys[5])) {
                                    $params[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]][$keys[5]] = $value;
                                } else {
                                    $params[$keys[0]][$keys[1]][$keys[2]][$keys[3]][$keys[4]] = $value;
                                }
                            } else {
                                $params[$keys[0]][$keys[1]][$keys[2]][$keys[3]] = $value;
                            }
                        } else {
                            $params[$keys[0]][$keys[1]][$keys[2]] = $value;
                        }
                    } else {
                        $params[$keys[0]][$keys[1]] = $value;
                    }
                } else {
                    $params[$keys[0]] = $value;
                }
            }
        }
        return $params;
    }
    /*
     * changeCssAttributes change all old css attributes (not general) to new
     * it can be removed after update all projects
     * */
    public static function change_css_attributes($theme_name)
    {
        $query = tep_db_query('select * from ' . TABLE_THEMES_SETTINGS . " where theme_name = '" . $theme_name . "' and setting_group = 'hide' and setting_name = 'new_attributes'");
        if (tep_db_num_rows($query) === 0) {
            tep_db_perform(TABLE_THEMES_SETTINGS, ['theme_name' => $theme_name, 'setting_group' => 'hide', 'setting_name' => 'new_attributes', 'setting_value' => '1']);
            $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . $theme_name . "'");
            $attributes_arr = ['z_index', 'vertical_align', 'text_transform', 'text_decoration', 'min_width', 'max_width', 'min_height', 'max_height', 'padding_left', 'padding_right', 'border_left_width', 'border_right_width', 'font_family', 'font_size', 'font_weight', 'font_style', 'line_height', 'text_align', 'background_color', 'background_position', 'background_repeat', 'background_size', 'padding_top', 'padding_bottom', 'margin_top', 'margin_left', 'margin_right', 'margin_bottom', 'border_top_width', 'border_top_color', 'border_left_color', 'border_right_color', 'border_bottom_width', 'border_bottom_color'];
            $attributes_arr2 = ['top_dimension', 'left_dimension', 'right_dimension', 'bottom_dimension', 'font_size_dimension', 'padding_top_dimension', 'padding_left_dimension', 'padding_right_dimension', 'padding_bottom_dimension', 'margin_top_dimension', 'margin_left_dimension', 'margin_right_dimension', 'margin_bottom_dimension'];
            $attributes_arr3 = ['border_radius_1', 'border_radius_2', 'border_radius_3', 'border_radius_4'];
            while ($item = tep_db_fetch_array($query)) {
                if (in_array($item['attribute'], $attributes_arr)) {
                    tep_db_perform(TABLE_THEMES_STYLES, ['attribute' => str_replace('_', '-', $item['attribute'])], 'update', " id = '" . $item['id'] . "'");
                    //tep_db_perform(TABLE_THEMES_STYLES_TMP, array('attribute' => str_replace('_', '-', $item['attribute'])), 'update', " id = '" . $item['id'] . "'");
                } elseif (in_array($item['attribute'], $attributes_arr2)) {
                    tep_db_perform(TABLE_THEMES_STYLES, ['attribute' => str_replace('_dimension', '_measure', $item['attribute'])], 'update', " id = '" . $item['id'] . "'");
                    //tep_db_perform(TABLE_THEMES_STYLES_TMP, array('attribute' => str_replace('_dimension', '_measure', $item['attribute'])), 'update', " id = '" . $item['id'] . "'");
                } elseif (in_array($item['attribute'], $attributes_arr3)) {
                    switch ($item['attribute']) {
                        case 'border_radius_1':
                            $attr = 'border-top-left-radius';
                            break;
                        case 'border_radius_2':
                            $attr = 'border-top-right-radius';
                            break;
                        case 'border_radius_3':
                            $attr = 'border-bottom-right-radius';
                            break;
                        case 'border_radius_4':
                            $attr = 'border-bottom-left-radius';
                            break;
                    }
                    tep_db_perform(TABLE_THEMES_STYLES, ['attribute' => $attr], 'update', " id = '" . $item['id'] . "'");
                    //tep_db_perform(TABLE_THEMES_STYLES_TMP, array('attribute' => $attr), 'update', " id = '" . $item['id'] . "'");
                }
            }
            $query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP);
            while ($item = tep_db_fetch_array($query)) {
                if (in_array($item['setting_name'], $attributes_arr)) {
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, ['setting_name' => str_replace('_', '-', $item['setting_name'])], 'update', " id = '" . $item['id'] . "'");
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, ['setting_name' => str_replace('_', '-', $item['setting_name'])], 'update', " id = '" . $item['id'] . "'");
                } elseif (in_array($item['setting_name'], $attributes_arr2)) {
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, ['setting_name' => str_replace('_dimension', '_measure', $item['setting_name'])], 'update', " id = '" . $item['id'] . "'");
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, ['setting_name' => str_replace('_dimension', '_measure', $item['setting_name'])], 'update', " id = '" . $item['id'] . "'");
                } elseif (in_array($item['attribute'] ?? null, $attributes_arr3)) {
                    switch ($item['attribute']) {
                        case 'border_radius_1':
                            $attr = 'border-top-left-radius';
                            break;
                        case 'border_radius_2':
                            $attr = 'border-top-right-radius';
                            break;
                        case 'border_radius_3':
                            $attr = 'border-bottom-right-radius';
                            break;
                        case 'border_radius_4':
                            $attr = 'border-bottom-left-radius';
                            break;
                    }
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, ['setting_name' => $attr], 'update', " id = '" . $item['id'] . "'");
                    tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, ['setting_name' => $attr], 'update', " id = '" . $item['id'] . "'");
                }
            }
        }
    }
    public static function get_css_widgets_list($theme_name)
    {
        $list_arr = [];
        $query = tep_db_query('select distinct accessibility from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($theme_name) . "' order by accessibility");
        while ($item = tep_db_fetch_array($query)) {
            $list_arr[] = $item['accessibility'];
        }
        return $list_arr;
    }
    public static function get_new_updates($theme_name)
    {
        $updates = [];
        $update = tep_db_fetch_array(tep_db_query('
                select setting_value 
                from ' . TABLE_THEMES_SETTINGS . " \r\n                where \r\n                    theme_name = '" . tep_db_input($theme_name) . "' and\r\n                    setting_group = 'hide' and\r\n                    setting_name = 'theme_update'\r\n            "));
        $path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR . 'updates';
        if (file_exists($path)) {
            $dir = scandir($path);
            foreach ($dir as $file) {
                $time = str_replace('.json', '', $file);
                if (file_exists($path . DIRECTORY_SEPARATOR . $file) && is_file($path . DIRECTORY_SEPARATOR . $file) && (int) $time > (int) $update['setting_value']) {
                    $updates[$time] = json_decode(file_get_contents($path . DIRECTORY_SEPARATOR . $file), true);
                }
            }
        }
        ksort($updates);
        return $updates;
    }
    public static function save_update_date($theme_name, $date)
    {
        $update = tep_db_fetch_array(tep_db_query('
                select id 
                from ' . TABLE_THEMES_SETTINGS . " \r\n                where \r\n                    theme_name = '" . tep_db_input($theme_name) . "' and\r\n                    setting_group = 'hide' and\r\n                    setting_name = 'theme_update'\r\n            "));
        if ($update['id']) {
            $sql_data_array = ['setting_value' => $date];
            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array, 'update', " id = '" . (int) $update['id'] . "'");
        } else {
            $sql_data_array = ['theme_name' => $theme_name, 'setting_group' => 'hide', 'setting_name' => 'theme_update', 'setting_value' => $date];
            tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
        }
    }
    public static function change_visibility_from_id_to_width($array)
    {
        foreach ($array as $key => $attr) {
            $visibility_arr = explode(',', $array[$key]['visibility']);
            foreach ($visibility_arr as $i => $id) {
                if ($id > 10) {
                    $width = tep_db_fetch_array(tep_db_query('
                        select setting_value
                        from ' . TABLE_THEMES_SETTINGS . " \r\n                        where id = '" . (int) $id . "' and \tsetting_name = 'media_query'\r\n                    "));
                    $visibility_arr[$i] = $width['setting_value'];
                }
            }
            $array[$key]['visibility'] = implode(',', $visibility_arr);
        }
        return $array;
    }
    public static function change_visibility_from_width_to_id($array, $theme_name)
    {
        foreach ($array as $key => $attr) {
            $visibility_arr = explode(',', $array[$key]['visibility']);
            foreach ($visibility_arr as $i => $width) {
                if (strlen($width) > 1) {
                    $id = tep_db_fetch_array(tep_db_query('
                        select id
                        from ' . TABLE_THEMES_SETTINGS . " \r\n                        where setting_value = '" . tep_db_input($width) . "' and setting_name = 'media_query'\r\n                    "));
                    if ($id['id']) {
                        $visibility_arr[$i] = $id['id'];
                    } else {
                        $sql_data_array = ['theme_name' => $theme_name, 'setting_group' => 'extend', 'setting_name' => 'media_query', 'setting_value' => $width];
                        tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
                        $visibility_arr[$i] = tep_db_insert_id();
                    }
                }
            }
            $array[$key]['visibility'] = implode(',', $visibility_arr);
        }
        return $array;
    }
    public static function merge_steps($steps)
    {
        $attributes_changed = [];
        $attributes_delete = [];
        $attributes_new = [];
        foreach ($steps as $data_from_step) {
            foreach ($data_from_step['attributes_new'] as $item_new) {
                $found_in_delete = false;
                foreach ($attributes_delete as $key => $attr) {
                    if (Style::compare_attributes($item_new, $attr)) {
                        $item_new['value_old'] = $attributes_delete[$key]['value'];
                        $attributes_changed[] = Style::get_one_attribute($item_new, true);
                        unset($attributes_delete[$key]);
                        $found_in_delete = true;
                        break;
                    }
                }
                if (!$found_in_delete) {
                    $attributes_new[] = Style::get_one_attribute($item_new);
                }
            }
            foreach ($data_from_step['attributes_changed'] as $item_changed) {
                $found_in_new = false;
                foreach ($attributes_new as $key => $attr) {
                    if (Style::compare_attributes($item_changed, $attr)) {
                        $attributes_new[$key] = Style::get_one_attribute($item_changed);
                        $found_in_new = true;
                        break;
                    }
                }
                if (!$found_in_new) {
                    $found_in_changed = false;
                    foreach ($attributes_changed as $key => $attr) {
                        if (Style::compare_attributes($item_changed, $attr)) {
                            $old = $attributes_changed[$key]['value_old'];
                            $attributes_changed[$key] = Style::get_one_attribute($item_changed);
                            $attributes_changed[$key]['value_old'] = $old;
                            if ($attributes_changed[$key]['value_old'] == $attributes_changed[$key]['value']) {
                                unset($attributes_changed[$key]);
                            }
                            $found_in_changed = true;
                            break;
                        }
                    }
                    if (!$found_in_changed) {
                        $attributes_changed[] = Style::get_one_attribute($item_changed, true);
                    }
                }
            }
            foreach ($data_from_step['attributes_delete'] as $item_delete) {
                foreach ($attributes_new as $key => $attr) {
                    if (Style::compare_attributes($item_delete, $attr)) {
                        unset($attributes_new[$key]);
                        break;
                    }
                }
                $attributes_delete[] = Style::get_one_attribute($item_delete);
            }
        }
        return ['attributes_new' => $attributes_new, 'attributes_changed' => $attributes_changed, 'attributes_delete' => $attributes_delete];
    }
    public static function add_exist_value_from_current_theme($update, $theme_name)
    {
        foreach ($update as $new_changed_delete => $update_part) {
            foreach ($update_part as $i => $attribute) {
                if (!$attribute) {
                    break;
                }
                $update[$new_changed_delete][$i]['local_id'] = $i;
                $query = tep_db_fetch_array(tep_db_query('
                    select * 
                    from ' . TABLE_THEMES_STYLES . " \r\n                    where \r\n                        theme_name = '" . tep_db_input($theme_name) . "' and\r\n                        selector = '" . tep_db_input($attribute['selector']) . "' and\r\n                        attribute = '" . tep_db_input($attribute['attribute']) . "' and\r\n                        visibility = '" . tep_db_input($attribute['visibility']) . "' and\r\n                        media = '" . tep_db_input($attribute['media']) . "'\r\n                "));
                if ($query['value']) {
                    $update[$new_changed_delete][$i]['value_exist'] = $query['value'];
                }
            }
        }
        return $update;
    }
    public static function change_selectors_by_visibility($update)
    {
        foreach ($update as $new_changed_delete => $update_part) {
            foreach ($update_part as $i => $attribute) {
                if (!$attribute) {
                    break;
                }
                if ($attribute['visibility']) {
                    $visibility_arr = explode(',', $attribute['visibility']);
                    $update[$new_changed_delete][$i]['visibility'] = '';
                    foreach ($visibility_arr as $visibility_id) {
                        if ($visibility_id < 10) {
                            $selector_arr = explode(',', $attribute['selector']);
                            foreach ($selector_arr as $s_item => $class) {
                                $selector_arr[$s_item] = trim($selector_arr[$s_item]);
                                if ($visibility_id == 2) {
                                    $selector_arr[$s_item] .= '.active';
                                }
                                if ($visibility_id == 3) {
                                    $selector_arr[$s_item] .= ':before';
                                }
                                if ($visibility_id == 4) {
                                    $selector_arr[$s_item] .= ':after';
                                }
                                if ($visibility_id == 1) {
                                    $selector_arr[$s_item] .= ':hover';
                                }
                            }
                            $update[$new_changed_delete][$i]['selector'] = implode(', ', $selector_arr);
                        } else {
                            $update[$new_changed_delete][$i]['visibility'] = $visibility_id;
                            // in visibility only media width id
                        }
                    }
                }
            }
        }
        return $update;
    }
    public static function add_to_array_sorted_by_media_and_selector($update, $theme_name)
    {
        $attributes_by_media = [];
        foreach (self::get_theme_media_queries($theme_name) as $item) {
            $media_query = $item ? $item['full'] : '';
            $media_id = $item && $item['id'] ? $item['id'] : '';
            foreach ($update as $new_changed_delete => $update_part) {
                foreach ($update_part as $i => $attribute) {
                    if (!$attribute) {
                        break;
                    }
                    if ($attribute['visibility'] == $media_id && $attribute['media'] == $media_query) {
                        $attributes_by_media[$new_changed_delete][$media_query][$attribute['selector']][$i] = $attribute;
                    }
                }
            }
        }
        return $attributes_by_media;
    }
    public static function get_theme_media_queries($theme_name, $visibility_and_media = 'all', $add_empty_field = true)
    {
        $queries = [];
        if ($add_empty_field) {
            $queries[0] = '';
        }
        if ($visibility_and_media == 'all' || $visibility_and_media == 'visibility') {
            $queries_tmp = [];
            $media_queries = tep_db_query('
                select id, setting_value 
                from ' . TABLE_THEMES_SETTINGS . " \r\n                where\r\n                    theme_name = '" . tep_db_input($theme_name) . "' and\r\n                    setting_name = 'media_query'\r\n            ");
            while ($item = tep_db_fetch_array($media_queries)) {
                $arr2 = explode('w', $item['setting_value']);
                $full = '';
                if ($arr2[0]) {
                    $full .= '(min-width:' . $arr2[0] . 'px)';
                }
                if ($arr2[0] && $arr2[1]) {
                    $full .= ' and ';
                }
                if ($arr2[1]) {
                    $full .= '(max-width:' . $arr2[1] . 'px)';
                }
                $queries_tmp[$arr2[1] ? $arr2[1] : $arr2[0]] = ['id' => $item['id'], 'full' => $full, 'short' => $item['setting_value']];
            }
            krsort($queries_tmp);
            $queries = array_merge($queries, $queries_tmp);
        }
        if ($visibility_and_media == 'all' || $visibility_and_media == 'media') {
            $media_queries = tep_db_query('
                select distinct media 
                from ' . TABLE_THEMES_STYLES . " \r\n                where\r\n                    theme_name = '" . tep_db_input($theme_name) . "' and\r\n                    media != ''\r\n            ");
            while ($item = tep_db_fetch_array($media_queries)) {
                $queries[] = ['full' => $item['media']];
            }
        }
        return $queries;
    }
    public static function save_update($submitted_elements, $updates, $theme_name)
    {
        foreach ($updates as $tide_name => $tide) {
            foreach ($tide as $attribute) {
                if (!$attribute) {
                    break;
                }
                if ($submitted_elements[$tide_name][$attribute['local_id']]) {
                    if ($tide_name == 'attributes_new' || $tide_name == 'attributes_changed') {
                        $query = tep_db_fetch_array(tep_db_query('select id from ' . TABLE_THEMES_STYLES . " where\r\n                                theme_name = '" . tep_db_input($theme_name) . "' and\r\n                                selector = '" . tep_db_input($attribute['selector']) . "' and\r\n                                attribute = '" . tep_db_input($attribute['attribute']) . "' and\r\n                                visibility = '" . tep_db_input($attribute['visibility']) . "' and\r\n                                media = '" . tep_db_input($attribute['media']) . "'\r\n                        "));
                        if ($query['id']) {
                            $sql_data_array = ['value' => tep_db_input($attribute['value'])];
                            tep_db_perform(TABLE_THEMES_STYLES, $sql_data_array, 'update', "\r\n                                theme_name = '" . tep_db_input($theme_name) . "' and\r\n                                selector = '" . tep_db_input($attribute['selector']) . "' and\r\n                                attribute = '" . tep_db_input($attribute['attribute']) . "' and\r\n                                visibility = '" . tep_db_input($attribute['visibility']) . "' and\r\n                                media = '" . tep_db_input($attribute['media']) . "'\r\n                            ");
                        } else {
                            $sql_data_array = ['theme_name' => $theme_name, 'selector' => $attribute['selector'], 'attribute' => $attribute['attribute'], 'value' => $attribute['value'], 'visibility' => $attribute['visibility'], 'media' => $attribute['media']];
                            tep_db_perform(TABLE_THEMES_STYLES, $sql_data_array);
                        }
                    } elseif ($tide_name == 'attributes_delete') {
                        tep_db_query('
                            delete 
                            from ' . TABLE_THEMES_STYLES . " \r\n                            where\r\n                                theme_name = '" . tep_db_input($theme_name) . "' and\r\n                                selector = '" . tep_db_input($attribute['selector']) . "' and\r\n                                attribute = '" . tep_db_input($attribute['attribute']) . "' and\r\n                                visibility = '" . tep_db_input($attribute['visibility']) . "' and\r\n                                media = '" . tep_db_input($attribute['media']) . "'\r\n                        ");
                    }
                }
            }
        }
    }
    public static function css_box_save($attributes, $theme_name)
    {
        $count1 = 0;
        $count2 = 0;
        $count3 = 0;
        $count4 = 0;
        $boxes = tep_db_query('
            select bs.box_id, bs.setting_value, bs.visibility, bs.setting_name
            from ' . TABLE_DESIGN_BOXES_TMP . ' b, ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . " bs \r\n            where \r\n                b.theme_name = '" . tep_db_input($theme_name) . "' and\r\n                b.id = bs.box_id\r\n            ");
        while ($item = tep_db_fetch_array($boxes)) {
            if (!in_array($item['setting_name'], self::$attributes_have_rules) && !in_array($item['setting_name'], self::$attributes_no_rules) && !in_array($item['setting_name'], self::$attributes_has_measure) || $item['setting_name'] == 'display_none' || $item['setting_name'] == 'box_align') {
                continue;
            }
            $item['attribute'] = $item['setting_name'];
            $item['value'] = $item['setting_value'];
            $attributes_old[] = ['box_id' => $item['box_id'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility']];
            $find = false;
            foreach ($attributes as $i => $attr) {
                if (strpos($attr['selector'], '#box-') !== 0) {
                    unset($attributes[$i]);
                    continue;
                }
                if (preg_match('/^\#box\-([0-9]+)$/', trim($attr['selector']), $matches)) {
                    $attr['box_id'] = $matches[1];
                    $attributes[$i]['box_id'] = $matches[1];
                } else {
                    continue;
                }
                if ($attr['box_id'] == $item['box_id'] && $attr['attribute'] == $item['attribute'] && (string) $attr['visibility'] === (string) $item['visibility']) {
                    if ($attr['value'] == $item['value']) {
                        $count1++;
                    } else {
                        // update styles
                        $keys[] = [$attr['value'], $item['value']];
                        $count2++;
                        $attributes_changed[] = ['box_id' => $attr['box_id'], 'attribute' => $attr['attribute'], 'value_old' => $item['value'], 'value' => $attr['value'], 'visibility' => $attr['visibility']];
                        tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, ['setting_value' => $attr['value']], 'update', "\r\n                            box_id = '" . tep_db_input($item['box_id']) . "' and\r\n                            setting_name = '" . tep_db_input($item['attribute']) . "' and\r\n                            visibility = '" . tep_db_input($item['visibility']) . "'\r\n                      ");
                        tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, ['setting_value' => $attr['value']], 'update', "\r\n                            box_id = '" . tep_db_input($item['box_id']) . "' and\r\n                            setting_name = '" . tep_db_input($item['attribute']) . "' and\r\n                            visibility = '" . tep_db_input($item['visibility']) . "'\r\n                      ");
                    }
                    unset($attributes[$i]);
                    $find = true;
                } elseif ($attr['box_id'] == $item['box_id'] && $attr['attribute'] == $item['attribute'] && (string) $attr['visibility'] === (string) $item['visibility'] && $attr['media'] == $item['media']) {
                    unset($attributes[$i]);
                }
            }
            if (!$find) {
                // remove styles
                $count3++;
                $attributes_delete[] = ['box_id' => $item['box_id'], 'setting_name' => $item['attribute'], 'setting_value' => $item['value'], 'visibility' => $item['visibility']];
                tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS . "\r\n                          where \r\n                            box_id = '" . tep_db_input($item['box_id']) . "' and\r\n                            setting_name = '" . tep_db_input($item['attribute']) . "' and\r\n                            visibility = '" . tep_db_input($item['visibility']) . "'\r\n              ");
                tep_db_query('delete from ' . TABLE_DESIGN_BOXES_SETTINGS_TMP . "\r\n                          where \r\n                            box_id = '" . tep_db_input($item['box_id']) . "' and\r\n                            setting_name = '" . tep_db_input($item['attribute']) . "' and\r\n                            visibility = '" . tep_db_input($item['visibility']) . "'\r\n              ");
            }
        }
        // add new styles
        foreach ($attributes as $attr) {
            if (strpos($attr['selector'], '#box-') !== 0) {
                unset($attributes[$i]);
                continue;
            }
            if (preg_match('/^\#box\-([0-9]+)$/', trim($attr['selector']), $matches)) {
                $attr['box_id'] = $matches[1];
            } else {
                continue;
            }
            $sgl_array = ['box_id' => $attr['box_id'], 'setting_name' => $attr['attribute'], 'setting_value' => $attr['value'], 'visibility' => $attr['visibility']];
            $attributes_new[] = $sgl_array;
            tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS, $sgl_array);
            tep_db_perform(TABLE_DESIGN_BOXES_SETTINGS_TMP, $sgl_array);
            $count4++;
        }
        $data = ['attributes_box_changed' => $attributes_changed, 'attributes_box_delete' => $attributes_delete, 'attributes_box_new' => $attributes_new];
        return $data;
    }
    public static function css_save($params)
    {
        /*$query = tep_db_query("select * from " . TABLE_THEMES_SETTINGS . " where theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'css' and setting_group = 'css'");
          $css_old = tep_db_fetch_array($query);
          $css_old = $css_old['setting_value'];*/
        if ($params['widget'] == 'all' || !$params['widget'] || $params['widget'] == 'block_box') {
            $accessibility = false;
        } elseif ($params['widget'] == 'main') {
            $accessibility = '';
        } else {
            $accessibility = $params['widget'];
        }
        $attributes = Style::css_compile($params['css'], $params['theme_name'], $accessibility);
        $all_attr = count($attributes);
        $at = [];
        $attributes_old = [];
        $attributes_changed = [];
        $attributes_delete = [];
        $attributes_new = [];
        $query = tep_db_query('select * from ' . TABLE_THEMES_STYLES . " where theme_name = '" . tep_db_input($params['theme_name']) . "'" . ($accessibility !== false ? " and accessibility = '" . tep_db_input($accessibility) . "' " : ''));
        $keys = [];
        $count1 = 0;
        $count2 = 0;
        $count3 = 0;
        $count4 = 0;
        if ($params['widget'] == 'all' || $params['widget'] == 'block_box') {
            $box_save_data = self::css_box_save($attributes, $params['theme_name']);
        }
        if ($params['widget'] != 'block_box') {
            while ($item = tep_db_fetch_array($query)) {
                $attributes_old[] = ['selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']];
                $find = false;
                foreach ($attributes as $i => $attr) {
                    if (strpos($attr['selector'], '#box-') === 0) {
                        unset($attributes[$i]);
                        continue;
                    }
                    if ($attr['selector'] == $item['selector'] && $attr['attribute'] == $item['attribute'] && (string) $attr['visibility'] === (string) $item['visibility'] && $attr['media'] == $item['media'] && $attr['accessibility'] == $item['accessibility']) {
                        if ($attr['value'] == $item['value']) {
                            $count1++;
                        } else {
                            // update styles
                            $keys[] = [$attr['value'], $item['value']];
                            $count2++;
                            $attributes_changed[] = ['selector' => $attr['selector'], 'attribute' => $attr['attribute'], 'value_old' => $item['value'], 'value' => $attr['value'], 'visibility' => $attr['visibility'], 'media' => $attr['media'], 'accessibility' => $attr['accessibility']];
                            tep_db_perform(TABLE_THEMES_STYLES, ['value' => $attr['value']], 'update', "\r\n                            theme_name = '" . tep_db_input($params['theme_name']) . "' and\r\n                            selector = '" . tep_db_input($item['selector']) . "' and\r\n                            attribute = '" . tep_db_input($item['attribute']) . "' and\r\n                            visibility = '" . tep_db_input($item['visibility']) . "' and\r\n                            media = '" . tep_db_input($item['media']) . "' and\r\n                            accessibility = '" . tep_db_input($item['accessibility']) . "'\r\n                      ");
                        }
                        unset($attributes[$i]);
                        $find = true;
                    } elseif ($attr['selector'] == $item['selector'] && $attr['attribute'] == $item['attribute'] && (string) $attr['visibility'] === (string) $item['visibility'] && $attr['media'] == $item['media'] && !$attr['accessibility'] && $item['accessibility']) {
                        unset($attributes[$i]);
                    }
                }
                if (!$find) {
                    // remove styles
                    $count3++;
                    $attributes_delete[] = ['selector' => $item['selector'], 'attribute' => $item['attribute'], 'value' => $item['value'], 'visibility' => $item['visibility'], 'media' => $item['media'], 'accessibility' => $item['accessibility']];
                    tep_db_query('delete from ' . TABLE_THEMES_STYLES . "\r\n                          where \r\n                            theme_name = '" . tep_db_input($params['theme_name']) . "' and\r\n                            selector = '" . tep_db_input($item['selector']) . "' and\r\n                            attribute = '" . tep_db_input($item['attribute']) . "' and\r\n                            visibility = '" . tep_db_input($item['visibility']) . "' and\r\n                            media = '" . tep_db_input($item['media']) . "' and\r\n                            accessibility = '" . tep_db_input($item['accessibility']) . "'\r\n              ");
                }
            }
            // add new styles
            foreach ($attributes as $attr) {
                if (strpos($attr['selector'], '#box-') === 0) {
                    continue;
                }
                $sgl_array = ['theme_name' => $params['theme_name'], 'selector' => $attr['selector'], 'attribute' => $attr['attribute'], 'value' => $attr['value'], 'visibility' => $attr['visibility'], 'media' => $attr['media'], 'accessibility' => $attr['accessibility']];
                $attributes_new[] = $sgl_array;
                tep_db_perform(TABLE_THEMES_STYLES, $sgl_array);
                $count4++;
            }
        }
        tep_db_query('delete from ' . TABLE_THEMES_SETTINGS . "\r\n                          where \r\n                            theme_name = '" . tep_db_input($params['theme_name']) . "' and\r\n                            setting_group = 'css' and\r\n                            setting_name = 'css'\r\n              ");
        $response = 'All attributes: ' . $all_attr . "\n";
        $response .= 'Not changed: ' . $count1 . "\n";
        $response .= 'Changed: ' . $count2 . "\n";
        $response .= 'Removed: ' . $count3 . "\n";
        $response .= 'Added: ' . $count4 . "\n\n";
        Style::create_cache($params['theme_name'], $params['widget']);
        $data = $box_save_data ?? [];
        $data['theme_name'] = $params['theme_name'];
        $data['attributes_changed'] = $attributes_changed;
        $data['attributes_delete'] = $attributes_delete;
        $data['attributes_new'] = $attributes_new;
        Steps::css_save($data);
        /*if (tep_db_num_rows($query) == 0) {
                  $sql_data_array = array(
                    'theme_name' => $params['theme_name'],
                    'setting_group' => 'css',
                    'setting_name' => 'css',
                    'setting_value' => $params['css']
                  );
                  tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array);
                } else {
                  $sql_data_array = array(
                    'setting_value' => $params['css']
                  );
                  tep_db_perform(TABLE_THEMES_SETTINGS, $sql_data_array, 'update', " theme_name = '" . tep_db_input($params['theme_name']) . "' and setting_group = 'css' and setting_name = 'css'");
                }
        
                $data = [
                  'theme_name' => $params['theme_name'],
                  'css_old' => $css_old,
                  'css' => $params['css'],
                ];
                Steps::cssSave($data);*/
        return $response;
    }
    public static function get_styles_by_classes($theme_name, $accessibility)
    {
        $styles = Themes_Styles::find()->select(['selector', 'attribute', 'value'])->where(['theme_name' => $theme_name, 'accessibility' => $accessibility])->as_array()->all();
        $classes = [];
        foreach ($styles as $attr) {
            $selector = str_replace($accessibility . ' ', '', $attr['selector']);
            $classes[$selector][$attr['attribute']] = $attr['value'];
        }
        $styles_by_classes = [];
        foreach ($classes as $class => $attributes) {
            $styles_by_classes[$class] = self::get_attributes($attributes);
        }
        return ['attributesArray' => $classes, 'attributesText' => $styles_by_classes];
    }
    public static function get_styles_wrapper($block_styles)
    {
        $styles = isset($block_styles[0]) ? $block_styles[0] : '';
        $media_arr = \common\models\Themes_Settings::find()->where(['setting_name' => 'media_query'])->order_by('setting_value')->as_array()->all();
        $media = [];
        $media_sorting = [];
        foreach ($media_arr as $media_item) {
            $arr = explode('w', $media_item['setting_value']);
            $media_sorting[$media_item['id']] = $arr[1] ? $arr[1] : '';
            $media[$media_item['id']] = $media_item;
        }
        arsort($media_sorting);
        foreach ($media_sorting as $id => $val) {
            $item = $media[$id];
            $arr = explode('w', $item['setting_value']);
            $styles .= '@media';
            if ($arr[0]) {
                $styles .= ' (min-width:' . $arr[0] . 'px)';
            }
            if ($arr[0] && $arr[1]) {
                $styles .= ' and ';
            }
            if ($arr[1]) {
                $styles .= ' (max-width:' . $arr[1] . 'px)';
            }
            $styles .= '{';
            $styles .= isset($block_styles[$item['id']]) ? $block_styles[$item['id']] : '';
            $styles .= '} ';
        }
        return $styles;
    }
    public static function schema($val, $selector)
    {
        $htm = '';
        $block_table = $selector . '{display:flex;flex-direction:column} ';
        $flex = $selector . '{display:flex;flex-wrap:wrap;} ';
        $block = $selector . '{display:block;} ';
        $div = $selector . ' > div:nth-child(n){width:100%;}';
        $div_n = $selector . ' > div:nth-child(%s){width:%s;%s}';
        $clear = 'clear:both;';
        $header = 'order:1;';
        $body = 'order:2;';
        $footer = 'order:3;';
        $float_none = 'float:none;';
        switch ($val) {
            case '2-2':
            case '3-4':
            case '4-2':
            case '5-2':
            case '6-2':
            case '7-2':
            case '8-4':
            case '13-4':
            case '9-2':
            case '10-2':
            case '11-2':
            case '12-2':
            case '14-3':
            case '15-6':
                $htm .= $block;
                $htm .= $div;
                break;
            case '2-3':
            case '4-3':
            case '5-3':
            case '6-3':
            case '7-3':
            case '9-3':
            case '10-3':
            case '11-3':
            case '12-3':
                $htm .= $block_table;
                $htm .= sprintf($div_n, 1, '100%', $footer . $float_none);
                $htm .= sprintf($div_n, 2, '100%', $header . $float_none);
                break;
            case '3-2':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '50%', '');
                $htm .= sprintf($div_n, 2, '50%', '');
                $htm .= sprintf($div_n, 3, '100%', '');
                break;
            case '3-3':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '100%', '');
                $htm .= sprintf($div_n, 2, '50%', '');
                $htm .= sprintf($div_n, 3, '50%', '');
                break;
            case '3-5':
            case '8-5':
            case '13-5':
                $htm .= $block_table;
                $htm .= sprintf($div_n, 1, '100%', $footer . $float_none);
                $htm .= sprintf($div_n, 2, '100%', $body . $float_none);
                $htm .= sprintf($div_n, 3, '100%', $header . $float_none);
                break;
            case '3-6':
            case '8-6':
            case '13-6':
                $htm .= $block_table;
                $htm .= sprintf($div_n, 1, '100%', $body . $float_none);
                $htm .= sprintf($div_n, 2, '100%', $header . $float_none);
                $htm .= sprintf($div_n, 3, '100%', $footer . $float_none);
                break;
            case '8-2':
            case '13-2':
                $htm .= $flex;
                $htm .= sprintf($div_n, 1, '50%', 'order:1;');
                $htm .= sprintf($div_n, 2, '100%', 'order:3;');
                $htm .= sprintf($div_n, 3, '50%', 'order:2;');
                break;
            case '8-3':
            case '13-3':
                $htm .= $flex;
                $htm .= sprintf($div_n, 1, '50%', 'order:2;');
                $htm .= sprintf($div_n, 2, '100%', 'order:1;');
                $htm .= sprintf($div_n, 3, '50%', 'order:3;');
                break;
            case '14-2':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '50%', '');
                $htm .= sprintf($div_n, 2, '50%', '');
                $htm .= sprintf($div_n, 3, '50%', $clear);
                $htm .= sprintf($div_n, 4, '50%', '');
                break;
            case '15-2':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '50%', '');
                $htm .= sprintf($div_n, 2, '50%', '');
                $htm .= sprintf($div_n, 3, '33.33%', $clear);
                $htm .= sprintf($div_n, 4, '33.33%', '');
                $htm .= sprintf($div_n, 5, '33.33%', '');
                break;
            case '15-3':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '33.33%', '');
                $htm .= sprintf($div_n, 2, '33.33%', '');
                $htm .= sprintf($div_n, 3, '33.33%', '');
                $htm .= sprintf($div_n, 4, '50%', $clear);
                $htm .= sprintf($div_n, 5, '50%', '');
                break;
            case '15-4':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '100%', '');
                $htm .= sprintf($div_n, 2, '50%', $clear);
                $htm .= sprintf($div_n, 3, '50%', '');
                $htm .= sprintf($div_n, 4, '50%', $clear);
                $htm .= sprintf($div_n, 5, '50%', '');
                break;
            case '15-5':
                $htm .= $block;
                $htm .= sprintf($div_n, 1, '50%', '');
                $htm .= sprintf($div_n, 2, '50%', '');
                $htm .= sprintf($div_n, 3, '50%', $clear);
                $htm .= sprintf($div_n, 4, '50%', '');
                $htm .= sprintf($div_n, 5, '100%', $clear);
                break;
        }
        return $htm;
    }
    public static function get_create_css($styles_raw_array, $media_sizes_arr, $widgets = [], $page = '', $all = true)
    {
        $css = '';
        $tab = '  ';
        $displacement = '';
        $by_media = [];
        $area_arr = [];
        if (!is_array($widgets) && !$widgets) {
            $widgets = [];
        } elseif (is_string($widgets) && $widgets) {
            $widgets = [$widgets];
        }
        if ($all && $page) {
            $area_arr[] = '';
            $area_arr[] = $page;
            foreach ($widgets as $widget) {
                $area_arr[] = $widget;
            }
            foreach ($widgets as $widget) {
                $area_arr[] = $page . ' ' . $widget;
            }
        } elseif (count($widgets) > 0 && $page) {
            foreach ($widgets as $widget) {
                $area_arr[] = $page . ' ' . $widget;
            }
        } elseif ($page) {
            $area_arr[] = $page;
        } elseif (count($widgets) > 0) {
            foreach ($widgets as $widget) {
                $area_arr[] = $widget;
            }
        }
        $area = "'" . implode("','", $area_arr) . "'";
        foreach ($styles_raw_array as $item) {
            $v_arr = self::v_arr($item['visibility']);
            $visibility = '';
            foreach ($v_arr as $v_key => $v_item) {
                if ($v_item > 10) {
                    $visibility = $v_item;
                    unset($v_arr[$v_key]);
                }
            }
            if (self::$css_frontend && $item['accessibility'] && (strpos($item['accessibility'], '.b-') === 0 || strpos($item['accessibility'], '.s-') === 0) && strpos($item['selector'], $item['accessibility']) !== false) {
                $item['selector'] = trim(str_replace($item['accessibility'], '', $item['selector']));
            }
            if (count($v_arr) > 0) {
                $selector_arr = explode(',', $item['selector']);
                foreach ($selector_arr as $s_item => $class) {
                    if (in_array(2, $v_arr)) {
                        $selector_arr[$s_item] .= '.active';
                    }
                    if (in_array(3, $v_arr)) {
                        $selector_arr[$s_item] .= ':before';
                    }
                    if (in_array(4, $v_arr)) {
                        $selector_arr[$s_item] .= ':after';
                    }
                    if (in_array(1, $v_arr)) {
                        $selector_arr[$s_item] .= ':hover';
                    }
                }
                $item['selector'] = implode(', ', $selector_arr);
            }
            if ($visibility) {
                $by_media['visibility'][$visibility][$item['selector']][$item['attribute']] = $item['value'];
            } elseif ($item['media']) {
                $by_media['media'][$item['media']][$item['selector']][$item['attribute']] = $item['value'];
            } else {
                $by_media['general'][$item['selector']][$item['attribute']] = $item['value'];
            }
        }
        $css_arr = ['general' => '', 'visibility' => '', 'media' => ''];
        foreach ($by_media as $key => $item) {
            if ($key == 'general') {
                $css_arr['general'] = $css_arr['general'] . self::get_css_media($item, '', $tab, $displacement);
            } elseif ($key == 'visibility') {
                $media_sizes = [];
                foreach ($media_sizes_arr as $media_size) {
                    $arr2 = explode('w', $media_size['setting_value']);
                    $media_sizes[(int) $arr2[1]] = $media_size['id'];
                }
                krsort($media_sizes);
                foreach ($media_sizes as $media => $media_id) {
                    $arr = $item[$media_id];
                    $arr2 = explode('w', $media);
                    $media_str = '';
                    if ($arr2[0]) {
                        $media_str .= '(min-width:' . $arr2[0] . 'px)';
                    }
                    if ($arr2[0] && $arr2[1]) {
                        $media_str .= ' and ';
                    }
                    if ($arr2[1]) {
                        $media_str .= '(max-width:' . $arr2[1] . 'px)';
                    }
                    $css_arr['visibility'] = $css_arr['visibility'] . self::get_css_media($arr, $media_str, $tab, $displacement);
                }
            } elseif ($key == 'media') {
                foreach ($item as $media => $arr) {
                    $css_arr['media'] = $css_arr['media'] . self::get_css_media($arr, $media, $tab, $displacement);
                }
            }
        }
        $css .= $css_arr['general'];
        $css .= $css_arr['visibility'];
        $css .= $css_arr['media'];
        return $css;
    }
    public static function get_theme_media($theme_name, $id = true)
    {
        static $media_sizes = [];
        if (count($media_sizes)) {
            return $media_sizes[$id];
        }
        $media_sizes_arr = Themes_Settings::find()->where(['theme_name' => $theme_name, 'setting_name' => 'media_query'])->as_array()->all();
        foreach ($media_sizes_arr as $size) {
            $media_sizes[true][$size['id']] = $size['setting_value'];
            $media_sizes[false][$size['setting_value']] = $size['id'];
        }
        return $media_sizes[$id];
    }
    public static function main_styles($theme_name)
    {
        static $styles = [];
        if ($styles[$theme_name] ?? null) {
            return $styles[$theme_name];
        }
        $styles[$theme_name] = [];
        $themes_styles_main = Themes_Styles_Main::find()->where(['theme_name' => $theme_name])->as_array()->all();
        foreach ($themes_styles_main as $style) {
            if (in_array($style['type'], ['color-var', 'font-var'])) {
                $styles[$theme_name]['$' . $style['name']] = self::get_style_value($style['value'], $themes_styles_main);
            } else {
                $styles[$theme_name]['$' . $style['name']] = $style['value'];
            }
        }
        return $styles[$theme_name];
    }
    public static function get_style_value($name, $styles)
    {
        foreach ($styles as $style) {
            if ($style['name'] != $name) {
                continue;
            }
            if (in_array($style['type'], ['color-var', 'font-var'])) {
                return self::get_style_value($style['value'], $styles);
            } else {
                return $style['value'];
            }
        }
    }
    private static function flush_cache_theme($theme_name, $need_delete = true)
    {
        $theme_mobile = $theme_name . '-mobile';
        if ($need_delete) {
            \common\models\Design_Boxes_Cache::delete_all(['or', ['theme_name' => $theme_name], ['theme_name' => $theme_mobile]]);
        }
        self::create_cache($theme_name, false, $need_delete);
        self::create_cache($theme_mobile, false, $need_delete);
    }
    public static function flush_cache_all()
    {
        $themes = \common\models\Themes::find()->as_array()->all();
        $db_arr = ['theme_name' => $themes[0]['theme_name'], 'setting_group' => 'hide', 'setting_name' => 'flush_cache_stamp'];
        $setting = \common\models\Themes_Settings::find_one($db_arr);
        if ($setting && $setting->setting_value + 300 > time()) {
            return false;
        }
        \common\models\Themes_Settings::delete_all($db_arr);
        $setting = new \common\models\Themes_Settings();
        $setting->theme_name = $themes[0]['theme_name'];
        $setting->setting_group = 'hide';
        $setting->setting_name = 'flush_cache_stamp';
        $setting->setting_value = time();
        $setting->save();
        // speed up
        \Yii::$app->db->create_command()->truncate_table(\common\models\Design_Boxes_Cache::table_name())->execute();
        \Yii::$app->db->create_command()->truncate_table(\common\models\Themes_Styles_Cache::table_name())->execute();
        foreach ($themes as $theme) {
            self::flush_cache_theme($theme['theme_name'], false);
        }
        \common\models\Themes_Settings::delete_all($db_arr);
        return true;
    }
    private static $need_reset_style_cache = false;
    public static function invalidate_cache()
    {
        self::$need_reset_style_cache = true;
    }
    public static function validate_cache()
    {
        if (self::$need_reset_style_cache) {
            self::flush_cache_all();
            self::$need_reset_style_cache = false;
        }
    }
    public static function get_css_elements($theme_name, $element)
    {
        $selectors = [];
        switch ($element) {
            case 'buttons':
                $selectors = ['.btn', '.btn-1', '.btn-2', '.btn-3', '.btn-del', '.btn-edit'];
                break;
            case 'headings':
                $selectors = ['h1, .heading-1', '.heading-2, h2, .h-block h2', '.heading-3, h3, .h-block h3', '.heading-3, h3, .h-block h3', '.heading-4, h4, .h-block h4', '.heading-5, h5, .h-block h5', '.heading-6, h6, .h-block h6', '.heading-2 .edit, h2 .edit', '.heading-3 .edit, h3 .edit', '.heading-4 .edit, h4 .edit'];
                break;
            case 'price':
                $selectors = ['.price', '.price .old', '.price .special', '.price .specials'];
                break;
            case 'form':
                $selectors = ["input[type='text'], input[type='password'], input[type='number'], input[type='email'], input[type='search'], select", 'textarea'];
        }
        $themes_styles = Themes_Styles::find()->where(['selector' => $selectors, 'theme_name' => $theme_name])->as_array()->all();
        foreach ($themes_styles as $key => $style) {
            if (strlen($style['visibility']) > 1) {
                $arr = explode(',', $style['visibility']);
                $width = Themes_Settings::find()->select('setting_value')->where(['id' => $arr[0]])->scalar();
                $themes_styles[$key]['visibility'] = $width . ($arr[1] ?? false ? ',' . $arr[1] : '');
            }
        }
        return $themes_styles;
    }
    public static function set_css_elements($theme_name, $styles)
    {
        $selectors = [];
        foreach ($styles as $style) {
            $selectors[$style['selector']] = $style['selector'];
        }
        Themes_Styles::delete_all(['theme_name' => $theme_name, 'selector' => $selectors]);
        foreach ($styles as $style) {
            if (strlen($style['visibility']) > 1) {
                $arr = explode(',', $style['visibility']);
                $width_id = Themes_Settings::find()->select('id')->where(['theme_name' => $theme_name, 'setting_group' => 'extend', 'setting_name' => 'media_query', 'setting_value' => $arr[0]])->scalar();
                if (!$width_id) {
                    $themes_settings = new Themes_Settings();
                    $themes_settings->theme_name = $theme_name;
                    $themes_settings->setting_group = 'extend';
                    $themes_settings->setting_name = 'media_query';
                    $themes_settings->setting_value = $arr[0];
                }
                $style['visibility'] = $width_id . ($arr[1] ?? false ? ',' . $arr[1] : '');
            }
            $themes_styles = new Themes_Styles();
            $themes_styles->theme_name = $theme_name;
            $themes_styles->selector = $style['selector'];
            $themes_styles->attribute = $style['attribute'];
            $themes_styles->value = $style['value'];
            $themes_styles->visibility = $style['visibility'];
            $themes_styles->media = $style['media'];
            $themes_styles->accessibility = $style['accessibility'];
            $themes_styles->save();
        }
    }
}