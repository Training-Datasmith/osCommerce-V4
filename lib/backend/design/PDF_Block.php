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

use frontend\design\Info;
use Yii;
use yii\base\Widget;
use yii\helpers\Array_Helper;
class Pdf_Box extends \TCPDF
{
    public $sizes;
    public $ds;
    public $global_top = 0;
    public $width_sheet;
    public $block_name;
    public static $font_family;
    public function font_family($font_family)
    {
        switch ($font_family) {
            case 'Varela Round':
                return 'VarelaRound-Regular';
        }
        return $font_family;
    }
    public function font_styles($settings, $content)
    {
        $htm = '<span style="';
        if ($settings[0]['text-align']) {
            $htm .= 'text-align:' . $settings[0]['text-align'] . ';';
        }
        if ($settings[0]['color']) {
            $htm .= 'color:' . $settings[0]['color'] . ';';
        }
        if ($settings[0]['font-style']) {
            $htm .= 'font-style:' . $settings[0]['font-style'] . ';';
        }
        if ($settings[0]['font-family'] == 'Tahoma' || self::$font_family == 'Tahoma') {
            if ($settings[0]['font-weight'] == 'bold') {
                $htm .= 'font-family:Tahomabd;';
            } else {
                $htm .= 'font-family:Tahoma;';
            }
        } else {
            if ($settings[0]['font-weight']) {
                $htm .= 'font-weight:' . $settings[0]['font-weight'] . ';';
            }
            if ($settings[0]['font-family']) {
                $htm .= 'font-family:' . $this->font_family($settings[0]['font-family']) . ';';
            }
        }
        if ($settings[0]['text-decoration']) {
            $htm .= 'text-decoration:' . $settings[0]['text-decoration'] . ';';
        }
        if ($settings[0]['text-transform']) {
            $htm .= 'text-transform:' . $settings[0]['text-transform'] . ';';
        }
        $htm .= '">' . $content . '<span>';
        return $htm;
    }
    private function null_settings(&$settings)
    {
        \common\helpers\Php8::null_arr_props($settings[0], ['position', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom', 'border-left-width', 'border-right-width', 'border-top-width', 'border-bottom-width', 'block_type', 'logo_from', 'params', 'pdf', 'text-align', 'color', 'font-style', 'font-weight', 'font-family', 'text-decoration', 'text-transform', 'font-size', 'style_class', 'show_text', 'show_number']);
    }
    public function block_sizes($name, $page_params, $width, $pdf_params)
    {
        $ds = $pdf_params['dimension_scale'];
        $items_query = tep_db_query('select id, widget_name, widget_params from ' . TABLE_DESIGN_BOXES . " where block_name = '" . $name . "' and theme_name = '" . $page_params['theme_name'] . "' order by sort_order");
        while ($item = tep_db_fetch_array($items_query)) {
            $this->sizes[$item['id']]['width'] = $width;
            $settings = [];
            $settings_query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES_SETTINGS . " where box_id = '" . (int) $item['id'] . "' and visibility = ''");
            while ($set = tep_db_fetch_array($settings_query)) {
                $settings[$set['language_id']][$set['setting_name']] = $set['setting_value'];
            }
            $settings[0]['pdf'] = 1;
            $this->null_settings($settings);
            if ($settings[0]['position'] == 'absolute') {
                continue;
            }
            $width2 = $width - $settings[0]['padding-left'] * $ds - $settings[0]['padding-right'] * $ds - $settings[0]['border-left-width'] * $ds - $settings[0]['border-right-width'] * $ds;
            if ($item['widget_name'] == 'BlockBox' || $item['widget_name'] == 'email\BlockBox' || $item['widget_name'] == 'cart\CartTabs') {
                $w = $this->width_by_type($settings[0]['block_type'], $width2);
                if ($w['1']) {
                    $this->block_sizes('block-' . $item['id'], $page_params, $w['1'], $pdf_params);
                }
                if ($w['2']) {
                    $this->block_sizes('block-' . $item['id'] . '-2', $page_params, $w['2'], $pdf_params);
                }
                if ($w['3']) {
                    $this->block_sizes('block-' . $item['id'] . '-3', $page_params, $w['3'], $pdf_params);
                }
                if ($w['4']) {
                    $this->block_sizes('block-' . $item['id'] . '-4', $page_params, $w['4'], $pdf_params);
                }
                if ($w['5']) {
                    $this->block_sizes('block-' . $item['id'] . '-5', $page_params, $w['5'], $pdf_params);
                }
            } elseif ($item['widget_name'] == 'invoice\Container') {
                $this->block_sizes('block-' . $item['id'], $page_params, $width2, $pdf_params);
            } elseif ($item['widget_name'] == 'Tabs') {
                for ($i = 1; $i < 11; $i++) {
                    $this->block_sizes('block-' . $item['id'] . '-' . $i, $page_params, $width2, $pdf_params);
                }
            } else {
                $widget_array['settings'] = $settings;
                $widget_array['params'] = $page_params;
                if ($item['widget_name'] == 'pdf\PageNumber') {
                    $widget = $this->get_alias_num_page();
                } elseif (($ext_widget = \common\helpers\Acl::run_extension_widget($item['widget_name'], $widget_array)) !== false) {
                    $widget = $ext_widget;
                } else {
                    $widget_name = 'frontend\design\boxes\\' . $item['widget_name'];
                    $widget = '';
                    if (class_exists($widget_name)) {
                        $widget = $widget_name::widget($widget_array);
                    }
                    $widget = preg_replace('/[ ]+/', ' ', $widget);
                    $widget = str_replace('<br> ', '<br>', $widget);
                }
                $pdf2 = clone $this;
                $pdf2->add_page();
                $pdf2->set_font_size($settings[0]['font-size'], $name);
                //$pdf2->Set_FontBold($settings[0]['font-weight'], $name);
                $htm = $this->font_styles($settings, $widget);
                $pdf2->write_html_cell($width2, 0, 0, 0, $htm, 1, 1);
                $height = $pdf2->get_y() + $settings[0]['padding-top'] * $ds + $settings[0]['padding-bottom'] * $ds + $settings[0]['border-top-width'] * $ds + $settings[0]['border-bottom-width'] * $ds;
                $pdf2->delete_page($pdf2->get_page());
                /*if ($item['widget_name'] == 'invoice\Products') {
                      echo '<pre>';
                      var_dump($pdf_params['height'] - $pdf_params['pdf_margin_bottom'] * $ds);
                      var_dump($height);
                      echo '</pre>';die;
                  }*/
                $this->sizes[$item['id']]['height'] = $height;
                /*if ($item['widget_name'] == 'Text'){
                    echo '<pre>';
                    var_dump($settings);
                    echo '</pre>';
                  }*/
                $this->set_top_height($height, $name);
            }
        }
    }
    public function get_item_height($html, $settings, $width, $pdf_params)
    {
        $ds = $pdf_params['dimension_scale'];
        $pdf3 = clone $this;
        $pdf3->add_page();
        $pdf3->set_font_size($settings[0]['font-size'] * 0.8);
        $htm = $this->font_styles($settings, $html);
        $pdf3->write_html_cell($width, 0, 0, 0, $htm, 1, 1);
        $height = $pdf3->get_y() + $settings[0]['padding-top'] * $ds + $settings[0]['padding-bottom'] * $ds + $settings[0]['border-top-width'] * $ds + $settings[0]['border-bottom-width'] * $ds;
        $pdf3->delete_page($pdf3->get_page());
        return $height;
    }
    public function width_by_type($block_type, $width)
    {
        $w['1'] = $w['2'] = $w['3'] = $w['4'] = $w['5'] = 0;
        switch ($block_type) {
            case '1':
                $w['1'] = $width;
                break;
            case '2':
                $w['1'] = $w['2'] = $width / 2;
                break;
            case '3':
                $w['1'] = $w['2'] = $w['3'] = round($width / 3, 4);
                break;
            case '4':
                $w['1'] = round($width / 3 * 2, 4);
                $w['2'] = round($width / 3, 4);
                break;
            case '5':
                $w['1'] = round($width / 3, 4);
                $w['2'] = round($width / 3 * 2, 4);
                break;
            case '6':
                $w['1'] = $width / 4;
                $w['2'] = $width / 4 * 3;
                break;
            case '7':
                $w['1'] = $width / 4 * 3;
                $w['2'] = $width / 4;
                break;
            case '8':
                $w['1'] = $w['3'] = $width / 4;
                $w['2'] = $width / 2;
                break;
            case '9':
                $w['1'] = $width / 5;
                $w['2'] = $width / 5 * 4;
                break;
            case '10':
                $w['1'] = $width / 5 * 4;
                $w['2'] = $width / 5;
                break;
            case '11':
                $w['1'] = $width / 5 * 2;
                $w['2'] = $width / 5 * 3;
                break;
            case '12':
                $w['1'] = $width / 5 * 3;
                $w['2'] = $width / 5 * 2;
                break;
            case '13':
                $w['1'] = $w['3'] = $width / 5;
                $w['2'] = $width / 5 * 3;
                break;
            case '14':
                $w['1'] = $w['2'] = $w['3'] = $w['4'] = $width / 4;
                break;
            case '15':
                $w['1'] = $w['2'] = $w['3'] = $w['4'] = $w['5'] = $width / 5;
                break;
        }
        return $w;
    }
    public function set_top_height($height, $name, $n = 0)
    {
        if (!$n && substr($name, 0, 6) == 'block-' || $n && strpos($name, $n . '1block') === 0) {
            $e = explode('-', $name);
            $id = $e[1];
            if ($this->sizes[$name]['height'] ?? null) {
                $this->sizes[$name]['height'] += $height;
            } else {
                $padding_top = tep_db_fetch_array(tep_db_query('
            select setting_value 
            from ' . TABLE_DESIGN_BOXES_SETTINGS . " \r\n            where box_id = '" . $id . "' and setting_name='padding-top' and visibility = ''"));
                $padding_bottom = tep_db_fetch_array(tep_db_query('
            select setting_value 
            from ' . TABLE_DESIGN_BOXES_SETTINGS . " \r\n            where box_id = '" . $id . "' and setting_name='padding-bottom' and visibility = ''"));
                $border_top_width = tep_db_fetch_array(tep_db_query('
            select setting_value 
            from ' . TABLE_DESIGN_BOXES_SETTINGS . " \r\n            where box_id = '" . $id . "' and setting_name='border-top-width' and visibility = ''"));
                $border_bottom_width = tep_db_fetch_array(tep_db_query('
            select setting_value 
            from ' . TABLE_DESIGN_BOXES_SETTINGS . " \r\n            where box_id = '" . $id . "' and setting_name='border-bottom-width' and visibility = ''"));
                $p = Array_Helper::get_value($padding_top, 'setting_value') + Array_Helper::get_value($padding_bottom, 'setting_value') + Array_Helper::get_value($border_top_width, 'setting_value') + Array_Helper::get_value($border_bottom_width, 'setting_value');
                $p = $this->ds * $p;
                $height += $p;
                $this->sizes[$name]['height'] = $height;
            }
            //$items_query = tep_db_fetch_array(tep_db_query("select block_name from " . TABLE_DESIGN_BOXES . " where id = '" . $id . "'"));
            //$this->setTopHeight($height, $items_query['block_name']);
            //$items_query = tep_db_fetch_array(tep_db_query("select block_name from " . TABLE_DESIGN_BOXES . " where id = '" . $id . "'"));
            //$this->setTopHeight($height, $items_query['block_name']);
        }
    }
    public function set_choose_height($n = 0)
    {
        $continue = false;
        foreach ($this->sizes as $key => $item) {
            if (!$n && substr($key, 0, 6) == 'block-' || $n && strpos($key, $n . '1block') === 0) {
                $e = explode('-', $key);
                $id = $e[1];
                $this->sizes[$id]['height'] = $this->sizes[$id]['height'] ?? null;
                if (!$this->sizes[$id]['height'] || $item['height'] > $this->sizes[$id]['height']) {
                    $this->sizes[$id]['height'] = $item['height'];
                    $this->sizes[$n + 1 . '2block-' . $id]['height'] = $item['height'];
                    $continue = true;
                }
            }
        }
        if ($continue) {
            $this->set_plus_height($n + 1);
        }
    }
    public function set_plus_height($n = 1)
    {
        $continue = false;
        foreach ($this->sizes as $key => $item) {
            if (strpos($key, $n . '2block') === 0) {
                $e = explode('-', $key);
                $id = $e[1];
                $items_query = tep_db_fetch_array(tep_db_query('select block_name from ' . TABLE_DESIGN_BOXES . " where id = '" . $id . "'"));
                if (substr($items_query['block_name'], 0, 6) == 'block-') {
                    $e2 = explode('-', $items_query['block_name']);
                    $id2 = $e2[1];
                    $e2[2] = $e2[2] ?? null;
                    $name = $n + 1 . '1block-' . $id2 . ($e2[2] ? '-' . $e2[2] : '');
                    $this->set_top_height($item['height'], $name, $n + 1);
                    $continue = true;
                }
            }
        }
        if ($continue) {
            $n++;
            $this->set_choose_height($n);
        }
    }
    public function set_all_height_bak()
    {
        foreach ($this->sizes as $key => $item) {
            if (substr($key, 0, 6) == 'block-') {
                $e = explode('-', $key);
                $id = $e[1];
                if (!$this->sizes[$id]['height'] || $item['height'] > $this->sizes[$id]['height']) {
                    $this->sizes[$id]['height'] = $item['height'];
                }
            }
        }
    }
    public function set_font_size($setting, $name)
    {
        $style = 'font_size';
        if ($setting) {
            $this->set_font_size($setting * 0.8);
        } else if (substr($name, 0, 6) == 'block-') {
            $e = explode('-', $name);
            $id = $e[1];
            $items_query = tep_db_fetch_array(tep_db_query('select b.block_name, bs.setting_value from ' . TABLE_DESIGN_BOXES . ' b, ' . TABLE_DESIGN_BOXES_SETTINGS . " bs where b.id = '" . $id . "' and b.id = bs.box_id and bs.setting_name='" . $style . "' and bs.visibility = ''"));
            if ($items_query['setting_value'] ?? null) {
                $this->set_font_size($items_query['setting_value'], $items_query['block_name']);
            } else {
                $block = tep_db_fetch_array(tep_db_query('select block_name from ' . TABLE_DESIGN_BOXES . " where id = '" . $id . "'"));
                $this->set_font_size(0, $block['block_name']);
            }
        }
    }
    public function set_font_bold($setting, $name)
    {
        $style = 'font_weight';
        if ($setting == 'bold') {
            $this->set_font('', 'B');
        } elseif ($setting == 'normal') {
            $this->set_font('', '');
        } else {
            $this->set_font('', '');
            if (substr($name, 0, 6) == 'block-') {
                $e = explode('-', $name);
                $id = $e[1];
                $items_query = tep_db_fetch_array(tep_db_query('select b.block_name, bs.setting_value from ' . TABLE_DESIGN_BOXES . ' b, ' . TABLE_DESIGN_BOXES_SETTINGS . " bs where b.id = '" . $id . "' and b.id = bs.box_id and bs.setting_name='" . $style . "' and bs.visibility = ''"));
                if ($items_query['setting_value']) {
                    $this->set_font_bold($items_query['setting_value'], $items_query['block_name']);
                } else {
                    $block = tep_db_fetch_array(tep_db_query('select block_name from ' . TABLE_DESIGN_BOXES . " where id = '" . $id . "'"));
                    $this->set_font_bold(0, $block['block_name']);
                }
            }
        }
    }
    public function set_font_color($setting, $name)
    {
        $style = 'color';
        if ($setting) {
            list($r, $g, $b) = sscanf($setting, '#%02x%02x%02x');
            $this->set_text_color($r, $g, $b);
        } else if (substr($name, 0, 6) == 'block-') {
            $e = explode('-', $name);
            $id = $e[1];
            $items_query = tep_db_fetch_array(tep_db_query('select b.block_name, bs.setting_value from ' . TABLE_DESIGN_BOXES . ' b, ' . TABLE_DESIGN_BOXES_SETTINGS . " bs where b.id = '" . $id . "' and b.id = bs.box_id and bs.setting_name='" . $style . "' and bs.visibility = ''"));
            if ($items_query['setting_value']) {
                $this->set_font_color($items_query['setting_value'], $items_query['block_name']);
            } else {
                $block = tep_db_fetch_array(tep_db_query('select block_name from ' . TABLE_DESIGN_BOXES . " where id = '" . $id . "'"));
                $this->set_font_color(0, $block['block_name']);
            }
        }
    }
    /**
     * extract R,g,b from either #NNnnNN or rgba() string
     * @param string $strColor
     * @return array( R, G, B)
     */
    public static function extract_color($str_color = '')
    {
        $rgb = [];
        if (strpos($str_color, 'rgba') !== false) {
            preg_match_all('/\d+/', $str_color, $rgb);
            if ($rgb[0] > 3) {
                $rgb = array_slice($rgb[0], 0, 3);
            }
        } else {
            $rgb = sscanf($str_color, '#%02x%02x%02x');
        }
        return $rgb;
    }
    public function create_item($html, $item, $settings, $height, $position, $pdf_params)
    {
        $ds = $pdf_params['dimension_scale'];
        $this->set_font_size($settings[0]['font-size'] * 0.8);
        $width = $this->sizes[$item['id']]['width'];
        $width = $width - $settings[0]['padding-left'] * $ds - $settings[0]['padding-right'] * $ds - $settings[0]['border-left-width'] * $ds - $settings[0]['border-right-width'] * $ds;
        $htm = $this->font_styles($settings, $html);
        $this->write_html_cell($width, $height, $position['left'], $position['top'], $htm, 0, 1);
    }
    public function create_products_block($item, $page_params, $position, $pdf_params)
    {
        $ds = $pdf_params['dimension_scale'];
        $width = $this->sizes[$item['id']]['width'];
        $settings = [];
        $settings_query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES_SETTINGS . " where box_id = '" . (int) $item['id'] . "' and visibility = ''");
        while ($set = tep_db_fetch_array($settings_query)) {
            $settings[$set['language_id']][$set['setting_name']] = $set['setting_value'];
        }
        $widget_array['settings'] = $settings;
        $widget_array['settings'][0]['pdf'] = $settings;
        $widget_array['params'] = $page_params;
        $widget_name = 'frontend\design\boxes\\' . $item['widget_name'];
        $total_products = 0;
        if (is_array($page_params['order']->products)) {
            $total_products = count($page_params['order']->products);
        }
        $fuse = 0;
        $last_product = 0;
        while ($last_product < $total_products && $fuse < $total_products) {
            $fuse++;
            if ($last_product == 0) {
                $position['top'] = $position['top'] + $settings[0]['padding-top'] * $ds;
            } else {
                $position['top'] = $pdf_params['pdf_margin_top'] * $ds;
            }
            $height = 0;
            $fit_items = 0;
            for ($i = 30; $i > 2; $i--) {
                $widget_array['params']['from'] = $last_product;
                $widget_array['params']['to'] = $last_product + $i;
                $widget = '';
                if (class_exists($widget_name)) {
                    $widget = $widget_name::widget($widget_array);
                }
                $widget = preg_replace('/[ ]+/', ' ', $widget);
                $widget = str_replace('<br> ', '<br>', $widget);
                $height = $this->get_item_height($widget, $settings, $width, $pdf_params);
                if ($position['top'] + $height < $pdf_params['height'] - $pdf_params['pdf_margin_bottom'] * $ds) {
                    $fit_items = $i;
                    break;
                }
            }
            $widget_array['params']['from'] = $last_product;
            $widget_array['params']['to'] = $last_product + $fit_items;
            $widget = '';
            if (class_exists($widget_name)) {
                $widget = $widget_name::widget($widget_array);
            }
            $widget = preg_replace('/[ ]+/', ' ', $widget);
            $widget = str_replace('<br> ', '<br>', $widget);
            if ($last_product != 0) {
                $page_params['page_number'] = $page_params['page_number'] + 1;
                $this->add_page();
                $this->page_header($page_params, $pdf_params);
                $this->page_footer($page_params, $pdf_params);
            }
            $this->create_item($widget, $item, $settings, $height, $position, $pdf_params);
            $last_product += $fit_items;
        }
        $position['top'] = $position['top'] + $height;
        return $position;
    }
    public function block_create($name, $page_params, $position, $pdf_params)
    {
        $main_styles = \backend\design\Style::main_styles($page_params['theme_name']);
        $ds = $pdf_params['dimension_scale'];
        $items_query = tep_db_query('select id, widget_name, widget_params from ' . TABLE_DESIGN_BOXES . " where block_name = '" . $name . "' and theme_name = '" . $page_params['theme_name'] . "' order by sort_order");
        while ($item = tep_db_fetch_array($items_query)) {
            $width = $this->sizes[$item['id']]['width'];
            $height = $this->sizes[$item['id']]['height'];
            $height_box = $this->sizes[$item['id']]['height'];
            if ($position['top'] + $height > $pdf_params['height'] - $pdf_params['pdf_margin_bottom'] * $ds && !preg_match('/_footer$/', $this->block_name)) {
                if ($item['widget_name'] == 'invoice\Products' || $item['widget_name'] == 'packingslip\Products') {
                    $position = $this->create_products_block($item, $page_params, $position, $pdf_params);
                    continue;
                }
                if ($height < $pdf_params['height'] - $pdf_params['pdf_margin_top'] - $pdf_params['pdf_margin_bottom']) {
                    $position['top'] = $pdf_params['pdf_margin_top'] * $ds;
                    $page_params['page_number'] = $page_params['page_number'] + 1;
                    $this->add_page();
                    $this->page_header($page_params, $pdf_params);
                    $this->page_footer($page_params, $pdf_params);
                }
            }
            $settings = [];
            $settings_query = tep_db_query('select * from ' . TABLE_DESIGN_BOXES_SETTINGS . " where box_id = '" . (int) $item['id'] . "' and visibility = ''");
            while ($set = tep_db_fetch_array($settings_query)) {
                $settings[$set['language_id']][$set['setting_name']] = $set['setting_value'];
            }
            $settings[0]['pdf'] = 1;
            $this->null_settings($settings);
            $top_box = $position['top'];
            $left_box = $position['left'];
            if ($settings[0]['position'] == 'absolute') {
                if (isset($settings[0]['width'])) {
                    if ($settings[0]['width_measure'] == '%') {
                        $width = $this->width_sheet * ($settings[0]['width'] / 100);
                    } else {
                        $width = $settings[0]['width'] * $ds;
                    }
                }
                if (isset($settings[0]['top'])) {
                    if ($settings[0]['top_measure'] == '%') {
                        $top_box = $pdf_params['top'] * ($settings[0]['top'] / 100);
                    } else {
                        $top_box = $settings[0]['top'] * $ds;
                    }
                }
                if (isset($settings[0]['left'])) {
                    if ($settings[0]['left_measure'] == '%') {
                        $left_box = $pdf_params['left'] * ($settings[0]['left'] / 100);
                    } else {
                        $left_box = $settings[0]['left'] * $ds;
                    }
                }
            }
            if (isset($settings[0]['height'])) {
                if ($settings[0]['height_measure'] == '%') {
                    $height_box = $pdf_params['height'] * ($settings[0]['height'] / 100);
                } else {
                    $height_box = $settings[0]['height'] * $ds;
                }
            }
            if (!empty($settings[0]['background-color'])) {
                if ($main_styles[$settings[0]['background-color']] ?? false) {
                    $settings[0]['background-color'] = $main_styles[$settings[0]['background-color']];
                }
                // rgba(250,244,151,0.54) or #NNnnNN
                $rgb = self::extract_color($settings[0]['background-color']);
                if (is_array($rgb) && count($rgb) == 3) {
                    $this->set_fill_color_array($rgb);
                }
                $this->write_html_cell($width, $height_box, $left_box, $top_box, ' ', 0, 1, 1);
            }
            if (!empty($settings[0]['background_image'])) {
                /* 2do
                            [background-position] => top left
                            [background-repeat] => no-repeat
                            [background-size] => contain
                   */
                $fs_root = Yii::get_alias('@webroot') . DIRECTORY_SEPARATOR;
                if (Yii::$app->id != 'app-frontend') {
                    $fs_root = $fs_root . '..' . DIRECTORY_SEPARATOR;
                }
                $image = $fs_root . Info::theme_image($settings[0]['background_image']);
                $fitbox = 'LT';
                if (is_file($image)) {
                    switch ($settings[0]['background-position']) {
                        case 'top left':
                            $fitbox = 'LT';
                            break;
                        case 'top center':
                            $fitbox = 'CT';
                            break;
                        case 'top right':
                            $fitbox = 'RT';
                            break;
                        case 'left':
                            $fitbox = 'LM';
                            break;
                        case 'center':
                            $fitbox = 'CM';
                            break;
                        case 'right':
                            $fitbox = 'RM';
                            break;
                        case 'bottom left':
                            $fitbox = 'LB';
                            break;
                        case 'bottom center':
                            $fitbox = 'CB';
                            break;
                        case 'bottom right':
                            $fitbox = 'RB';
                            break;
                    }
                    $b_margin = $this->get_break_margin();
                    $auto_page_break = $this->get_auto_page_break();
                    $this->set_auto_page_break(false, 0);
                    $this->Image($image, $left_box, $top_box, $width, $height_box, '', '', '', false, 300, '', false, false, 0, $fitbox, false, false);
                    // restore auto-page-break status
                    $this->set_auto_page_break($auto_page_break, $b_margin);
                }
            }
            if ($settings[0]['border-top-width'] ?? false) {
                if ($main_styles[$settings[0]['border-top-color']] ?? false) {
                    $settings[0]['border-top-color'] = $main_styles[$settings[0]['border-top-color']];
                }
                list($r, $g, $b) = sscanf($settings[0]['border-top-color'], '#%02x%02x%02x');
                $this->Line($position['left'] - $ds / 10 - $ds / 10, $position['top'] + $settings[0]['border-top-width'] * $ds / 2 - $ds / 10, $position['left'] + $width + $ds / 10, $position['top'] + $settings[0]['border-top-width'] * $ds / 2 - $ds / 10, ['width' => $settings[0]['border-top-width'] * $ds, 'color' => [$r, $g, $b]]);
            }
            if ($settings[0]['border-left-width'] ?? false) {
                if ($main_styles[$settings[0]['border-left-color']] ?? false) {
                    $settings[0]['border-left-color'] = $main_styles[$settings[0]['border-left-color']];
                }
                list($r, $g, $b) = sscanf($settings[0]['border-left-color'], '#%02x%02x%02x');
                $this->Line($position['left'] + $settings[0]['border-left-width'] * $ds / 2 - $ds / 10, $position['top'] - $ds / 10, $position['left'] + $settings[0]['border-left-width'] * $ds / 2 - $ds / 10, $position['top'] + $height + $ds / 10, ['width' => $settings[0]['border-left-width'] * $ds, 'color' => [$r, $g, $b]]);
            }
            if ($settings[0]['border-right-width'] ?? false) {
                if ($main_styles[$settings[0]['border-right-color']] ?? false) {
                    $settings[0]['border-right-color'] = $main_styles[$settings[0]['border-right-color']];
                }
                list($r, $g, $b) = sscanf($settings[0]['border-right-color'], '#%02x%02x%02x');
                $this->Line($position['left'] + $width - $settings[0]['border-right-width'] * $ds / 2 + $ds / 10, $position['top'] - $ds / 10, $position['left'] + $width - $settings[0]['border-right-width'] * $ds / 2 + $ds / 10, $position['top'] + $height + $ds / 10, ['width' => $settings[0]['border-right-width'] * $ds, 'color' => [$r, $g, $b]]);
            }
            if ($settings[0]['border-bottom-width'] ?? false) {
                if (($settings[0]['border-bottom-color'] ?? false) && ($main_styles[$settings[0]['border-bottom-color']] ?? false)) {
                    $settings[0]['border-bottom-color'] = $main_styles[$settings[0]['border-bottom-color']];
                }
                if ($settings[0]['border-bottom-color'] ?? false) {
                    list($r, $g, $b) = sscanf($settings[0]['border-bottom-color'], '#%02x%02x%02x');
                } else {
                    list($r, $g, $b) = [0, 0, 0];
                }
                $this->Line($position['left'] - $ds / 10, $position['top'] + $height - $settings[0]['border-bottom-width'] * $ds / 2 + $ds / 10, $position['left'] + $width + $ds / 10, $position['top'] + $height - $settings[0]['border-bottom-width'] * $ds / 2 + $ds / 10, ['width' => $settings[0]['border-bottom-width'] * $ds, 'color' => [$r, $g, $b]]);
            }
            $width = $width - $settings[0]['padding-left'] * $ds - $settings[0]['padding-right'] * $ds - $settings[0]['border-left-width'] * $ds - $settings[0]['border-right-width'] * $ds;
            $p['left'] = $p2['left'] = $position['left'] + $settings[0]['padding-left'] * $ds + $settings[0]['border-left-width'] * $ds;
            $p['top'] = $p2['top'] = $position['top'] + $settings[0]['padding-top'] * $ds + $settings[0]['border-top-width'] * $ds;
            if ($item['widget_name'] == 'BlockBox' || $item['widget_name'] == 'email\BlockBox' || $item['widget_name'] == 'cart\CartTabs') {
                if ($settings[0]['style_class'] == 'product' && \frontend\design\Info::$pdf_products_end) {
                } else {
                    $w = $this->width_by_type($settings[0]['block_type'], $width);
                    if ($w['1']) {
                        $this->block_create('block-' . $item['id'], $page_params, $p, $pdf_params);
                    }
                    $p['left'] = $p['left'] + $w['1'];
                    if ($w['2']) {
                        $this->block_create('block-' . $item['id'] . '-2', $page_params, $p, $pdf_params);
                    }
                    $p['left'] = $p['left'] + $w['2'];
                    if ($w['3']) {
                        $this->block_create('block-' . $item['id'] . '-3', $page_params, $p, $pdf_params);
                    }
                    $p['left'] = $p['left'] + $w['3'];
                    if ($w['4']) {
                        $this->block_create('block-' . $item['id'] . '-4', $page_params, $p, $pdf_params);
                    }
                    $p['left'] = $p['left'] + $w['4'];
                    if ($w['5']) {
                        $this->block_create('block-' . $item['id'] . '-5', $page_params, $p, $pdf_params);
                    }
                }
            } elseif ($item['widget_name'] == 'invoice\Container') {
                $this->block_create('block-' . $item['id'], $page_params, $p, $pdf_params);
            } elseif ($item['widget_name'] == 'Tabs') {
                for ($i = 1; $i < 11; $i++) {
                }
            } else {
                $widget_array['settings'] = $settings;
                $widget_array['settings']['out'] = 1;
                $widget_array['params'] = $page_params;
                if ($item['widget_name'] == 'pdf\PageNumber') {
                    $widget = $this->get_alias_num_page();
                } elseif (($ext_widget = \common\helpers\Acl::run_extension_widget($item['widget_name'], $widget_array)) !== false) {
                    $widget = $ext_widget;
                } else {
                    $widget_name = 'frontend\design\boxes\\' . $item['widget_name'];
                    $widget = '';
                    if (class_exists($widget_name)) {
                        $widget = $widget_name::widget($widget_array);
                    }
                    $widget = preg_replace('/[ ]+/', ' ', $widget);
                    $widget = str_replace('<br> ', '<br>', $widget);
                }
                //$this->Line($this->GetX(), $this->GetY(), $this->GetX() + $width, $this->GetY(),  array('width' => 1, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 255)));
                $this->set_font_size($settings[0]['font-size'], $name);
                //$this->Set_FontBold($settings[0]['font-weight'], $name);
                //$this->Set_FontColor($settings[0]['color'], $name);
                if (($settings[0]['color'] ?? false) && ($main_styles[$settings[0]['color']] ?? false)) {
                    $settings[0]['color'] = $main_styles[$settings[0]['color']];
                }
                $htm = $this->font_styles($settings, $widget);
                if ($settings[0]['position'] == 'absolute') {
                    $this->write_html_cell($width, $height_box, $left_box, $top_box, $htm, 0, 1);
                } else {
                    $this->write_html_cell($width, $height_box, $p2['left'], $p2['top'], $htm, 0, 1);
                }
                $this->sizes[$item['id']]['height'] = $height;
                $this->set_top_height($height, $name);
            }
            $position['top'] = $position['top'] + $height;
            if ($name == 'pdf' || $name == 'pdf_category') {
                $this->global_top = $position['top'];
            }
        }
    }
    public function Block($name, $page_params, $pdf_params)
    {
        $ds = $pdf_params['dimension_scale'];
        $this->ds = $ds;
        $width = '210';
        $height = '297';
        if (is_array($pdf_params['sheet_format'])) {
            $width = $pdf_params['sheet_format'][0];
            $height = $pdf_params['sheet_format'][1];
        } elseif ($pdf_params['sheet_format'] == 'A0' && $pdf_params['orientation'] == 'P') {
            $width = '841';
            $height = '1189';
        } elseif ($pdf_params['sheet_format'] == 'A0' && $pdf_params['orientation'] == 'L') {
            $width = '1189';
            $height = '841';
        } elseif ($pdf_params['sheet_format'] == 'A1' && $pdf_params['orientation'] == 'P') {
            $width = '594';
            $height = '841';
        } elseif ($pdf_params['sheet_format'] == 'A1' && $pdf_params['orientation'] == 'L') {
            $width = '841';
            $height = '594';
        } elseif ($pdf_params['sheet_format'] == 'A2' && $pdf_params['orientation'] == 'P') {
            $width = '420';
            $height = '594';
        } elseif ($pdf_params['sheet_format'] == 'A2' && $pdf_params['orientation'] == 'L') {
            $width = '594';
            $height = '420';
        } elseif ($pdf_params['sheet_format'] == 'A3' && $pdf_params['orientation'] == 'P') {
            $width = '297';
            $height = '420';
        } elseif ($pdf_params['sheet_format'] == 'A3' && $pdf_params['orientation'] == 'L') {
            $width = '420';
            $height = '297';
        } elseif ($pdf_params['sheet_format'] == 'A4' && $pdf_params['orientation'] == 'P') {
            $width = '210';
            $height = '297';
        } elseif ($pdf_params['sheet_format'] == 'A4' && $pdf_params['orientation'] == 'L') {
            $width = '297';
            $height = '210';
        } elseif ($pdf_params['sheet_format'] == 'A5' && $pdf_params['orientation'] == 'P') {
            $width = '148';
            $height = '210';
        } elseif ($pdf_params['sheet_format'] == 'A5' && $pdf_params['orientation'] == 'L') {
            $width = '210';
            $height = '148';
        } elseif ($pdf_params['sheet_format'] == 'A6' && $pdf_params['orientation'] == 'P') {
            $width = '105';
            $height = '148';
        } elseif ($pdf_params['sheet_format'] == 'A6' && $pdf_params['orientation'] == 'L') {
            $width = '148';
            $height = '105';
        }
        $this->sizes = [];
        $pdf_params['height'] = $height;
        $this->width_sheet = $width;
        $width = $width - $pdf_params['pdf_margin_left'] * $ds - $pdf_params['pdf_margin_right'] * $ds;
        $position = ['top' => $pdf_params['pdf_margin_top'] * $ds, 'left' => $pdf_params['pdf_margin_left'] * $ds];
        //$this->BlockCreate($name, $page_params, $position, $pdf_params);
        $page_params['page_number'] = 1;
        $this->page_header($page_params, $pdf_params);
        $this->page_footer($page_params, $pdf_params);
        if ($name == 'pdf') {
            if (is_array($page_params['products'])) {
                $this->global_top = $position['top'];
                if ($page_params['categoryName']) {
                    $this->Bookmark($page_params['categoryName'], 0, 1);
                    $this->sizes = [];
                    $this->block_sizes('pdf_category', $page_params, $width, $pdf_params);
                    $this->set_choose_height();
                    $position['top'] = $this->global_top;
                    $this->block_create('pdf_category', $page_params, $position, $pdf_params);
                }
                \frontend\design\boxes\pdf\Product_Element::widget(['settings' => ['item_clear' => true]]);
                for ($i = 0; $i < count($page_params['products']) && !\frontend\design\Info::$pdf_products_end; $i++) {
                    $this->sizes = [];
                    $this->block_sizes($name, $page_params, $width, $pdf_params);
                    $this->set_choose_height();
                    $position['top'] = $this->global_top;
                    $this->block_create($name, $page_params, $position, $pdf_params);
                }
                \frontend\design\Info::$pdf_products_end = false;
            }
        } else {
            $this->block_sizes($name, $page_params, $width, $pdf_params);
            $this->set_choose_height();
            $this->block_create($name, $page_params, $position, $pdf_params);
        }
    }
    public function page_header($page_params, $pdf_params)
    {
        if (!$pdf_params['showHeader']) {
            return null;
        }
        static $sizes = [];
        $sizes_tmp = $this->sizes;
        if (count($sizes) > 0) {
            $this->sizes = $sizes;
        } else {
            $this->sizes = [];
            $this->block_sizes($page_params['page_name'] . '_header', $page_params, $this->width_sheet, $pdf_params);
            $this->set_choose_height();
        }
        $this->block_create($page_params['page_name'] . '_header', $page_params, ['top' => 0, 'left' => 0], $pdf_params);
        $this->sizes = $sizes_tmp;
    }
    public function page_footer($page_params, $pdf_params)
    {
        if (!$pdf_params['showFooter']) {
            return null;
        }
        $this->block_name = $page_params['page_name'] . '_footer';
        $blocks = \common\models\Design_Boxes::find()->where(['block_name' => $this->block_name])->as_array()->all();
        if (!is_array($blocks) || count($blocks) == 0) {
            $this->block_name = '';
            return null;
        }
        static $sizes = [];
        $sizes_tmp = $this->sizes;
        if (count($sizes) > 0) {
            $this->sizes = $sizes;
        } else {
            $this->sizes = [];
            $this->block_sizes($this->block_name, $page_params, $this->width_sheet, $pdf_params);
            $this->set_choose_height();
        }
        $height = 0;
        foreach ($blocks as $block) {
            $height += $this->sizes[$block['id']]['height'] ?? null;
        }
        $this->block_create($this->block_name, $page_params, ['top' => $pdf_params['height'] - $height, 'left' => 0], $pdf_params);
        $this->block_name = '';
        $this->sizes = $sizes_tmp;
    }
}
class Pdf_Block extends Widget
{
    public $pages;
    public $params;
    public function init()
    {
        parent::init();
        \common\helpers\Translation::init('admin/orders');
    }
    public function run()
    {
        $theme = tep_db_fetch_array(tep_db_query('select theme_name from ' . TABLE_THEMES));
        $default = [
            'theme_name' => $theme['theme_name'],
            'document_name' => 'document',
            'sheet_format' => 'A4',
            'orientation' => 'P',
            'destination' => 'I',
            /*Destination where to send the document. It can take one of the following values:
            I: send the file inline to the browser (default). The plug-in is used if available. The name given by name is used when one selects the “Save as” option on the link generating the PDF.
            D: send to the browser and force a file download with the name given by name.
            F: save to a local server file with the name given by name.
            S: return the document as a string. name is ignored.
            FI: equivalent to F + I option
            FD: equivalent to F + D option*/
            'title' => 'document',
            'subject' => 'document',
            'keywords' => '',
            'pdf_margin_top' => 25,
            'pdf_margin_left' => 20,
            'pdf_margin_right' => 20,
            'pdf_margin_bottom' => 25,
            'dimension_scale' => 0.3,
            'showTOC' => false,
            'pageNumberTOC' => '',
            //page number where this TOC should be inserted (leave empty for current page)
            'showHeader' => true,
            //header created in designer
            'showFooter' => true,
        ];
        $params = array_merge($default, $this->params);
        $params = $this->set_page_settings($params);
        $ds = $params['dimension_scale'];
        stream_context_set_default(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $pdf = new Pdf_Box($params['orientation'], 'mm', $params['sheet_format'], true, 'UTF-8', false);
        $pdf->set_creator(PDF_CREATOR);
        $pdf->set_author(defined('STORE_NAME') ? STORE_NAME : 'Holbi');
        $pdf->set_title($params['title']);
        $pdf->set_subject($params['subject']);
        $pdf->set_keywords($params['keywords']);
        $pdf->set_print_header(false);
        $pdf->set_print_footer(false);
        $pdf->set_default_monospaced_font(PDF_FONT_MONOSPACED);
        $pdf->set_margins($params['pdf_margin_left'] * $ds, $params['pdf_margin_top'] * $ds, $params['pdf_margin_right'] * $ds);
        //$pdf->SetAutoPageBreak(TRUE, $params['pdf_margin_bottom'] * $ds);
        $pdf->set_auto_page_break(false);
        //\TCPDF_FONTS::addTTFfont(DIR_FS_CATALOG . 'themes/basic/fonts/Hind-Bold.ttf');
        $params['pdf_font_family'] = $params['pdf_font_family'] ?? null;
        $pdf->set_font($params['pdf_font_family'] ? $params['pdf_font_family'] : 'Helvetica', '', 12);
        $pdf->set_image_scale(PDF_IMAGE_SCALE_RATIO);
        $pdf::$font_family = $params['pdf_font_family'];
        if (is_array($this->pages ?? null)) {
            foreach ($this->pages as $page) {
                if ($page['params']['theme_name'] ?? null) {
                    $theme_name = $page['params']['theme_name'];
                } elseif ($params['theme_name'] ?? null) {
                    $theme_name = $params['theme_name'];
                } else {
                    $theme = tep_db_fetch_array(tep_db_query('select theme_name from ' . TABLE_THEMES));
                    $theme_name = $theme['theme_name'];
                }
                $items_query = tep_db_query('select id, widget_name, widget_params from ' . (\frontend\design\Info::is_admin() ? TABLE_DESIGN_BOXES_TMP : TABLE_DESIGN_BOXES) . " where block_name = '" . $page['name'] . "' and theme_name = '" . $theme_name . "' order by sort_order");
                $count = tep_db_num_rows($items_query);
                if ($count > 0) {
                    $pdf->add_page();
                    $pdf->Block($page['name'], array_merge($page['params'], ['theme_name' => $theme_name, 'page_name' => $page['name']]), $params);
                }
            }
        }
        if ($params['showTOC'] ?? null) {
            // add a new page for Table of Content
            $pdf->add_toc_page();
            // write the TOC title
            $pdf->set_font('dejavusans', 'B', 16);
            $pdf->multi_cell(0, 0, PDF_CONTENT, 0, 'C', 0, 1, '', '', true, 0);
            $pdf->Ln();
            $pdf->set_font('dejavusans', '', 12);
            // add a simple Table Of Content at first page
            // (check the example n. 59 for the HTML version)
            $pdf->add_toc($params['pageNumberTOC'], 'courier', '.', '');
            // end of TOC page
            $pdf->end_toc_page();
        }
        $params['destination'] = $params['destination'] ?? null;
        if ($params['destination'] == 'S') {
            return $pdf->Output($params['document_name'], $params['destination']);
        } else {
            $pdf->Output($params['document_name'], $params['destination']);
        }
        if (!in_array($params['destination'], ['S', 'F'])) {
            die;
        }
    }
    public function set_page_settings($params)
    {
        if ($this->pages[0]['params']['theme_name'] ?? null) {
            $theme_name = $this->pages[0]['params']['theme_name'];
        } elseif ($params['theme_name'] ?? null) {
            $theme_name = $params['theme_name'];
        } else {
            $theme = tep_db_fetch_array(tep_db_query('select theme_name from ' . TABLE_THEMES));
            $theme_name = $theme['theme_name'];
        }
        $page_settings_query = \common\models\Themes_Settings::find()->where(['theme_name' => $theme_name, 'setting_group' => 'added_page_settings', 'setting_name' => $this->pages[0]['name'] ?? null])->as_array()->all();
        $page_settings = [];
        foreach ($page_settings_query as $sett) {
            $set_arr = explode(':', $sett['setting_value']);
            $page_settings[$set_arr[0]] = $set_arr[1];
        }
        \common\helpers\Php8::null_arr_props($page_settings, ['sheet_format', 'orientation', 'page_width', 'page_height', 'page_height', 'page_width', 'pdf_font_family', 'pdf_margin_top', 'pdf_margin_left', 'pdf_margin_right', 'pdf_margin_bottom', 'dimension_scale']);
        if ($page_settings['sheet_format'] && $page_settings['sheet_format'] != 'size') {
            $params['sheet_format'] = $page_settings['sheet_format'];
            if ($page_settings['orientation']) {
                $params['orientation'] = $page_settings['orientation'];
            }
        } elseif ($page_settings['page_width'] && $page_settings['page_height']) {
            $params['sheet_format'] = [$page_settings['page_width'], $page_settings['page_height']];
            $params['orientation'] = $page_settings['page_height'] > $page_settings['page_width'] ? 'P' : 'L';
        }
        if ($page_settings['pdf_margin_top']) {
            $params['pdf_margin_top'] = $page_settings['pdf_margin_top'] / $params['dimension_scale'];
        }
        if ($page_settings['pdf_margin_left']) {
            $params['pdf_margin_left'] = $page_settings['pdf_margin_left'] / $params['dimension_scale'];
        }
        if ($page_settings['pdf_margin_right']) {
            $params['pdf_margin_right'] = $page_settings['pdf_margin_right'] / $params['dimension_scale'];
        }
        if ($page_settings['pdf_margin_bottom']) {
            $params['pdf_margin_bottom'] = $page_settings['pdf_margin_bottom'] / $params['dimension_scale'];
        }
        if ($page_settings['pdf_font_family']) {
            $params['pdf_font_family'] = $page_settings['pdf_font_family'];
        }
        return $params;
    }
}