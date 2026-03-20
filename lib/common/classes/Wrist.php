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

/**
 * Wristband object
 */
class Wrist
{
    /**
     * Data collection for object
     * @var array  $data
     */
    public $data = [];
    /**
     * Color keys setting
     * @var array
     */
    public $colour = [1 => 'white', 2 => 'purple', 3 => 'neon-pink', 4 => 'red', 5 => 'neon-orange', 6 => 'yellow', 7 => 'neon-yellow', 8 => 'aqua', 9 => 'neon-green', 10 => 'dark-green', 11 => 'blue', 12 => 'sky-blue', 13 => 'gold', 14 => 'silver'];
    /**
     * Color names
     * @var array
     */
    public $colour_names = [1 => 'White', 2 => 'Purple', 3 => 'Neon Pink', 4 => 'Red', 5 => 'Neon Orange', 6 => 'Yellow', 7 => 'Neon Yellow', 8 => 'Aqua', 9 => 'Neon Green', 10 => 'Dark Green', 11 => 'Blue', 12 => 'Sky Blue', 13 => 'Gold', 14 => 'Silver'];
    /**
     * Where to store the data
     * possible values: FILE or DB
     */
    public const STORAGE_TYPE = 'FILE';
    /**
     * Dots per inch
     */
    public const DEFAULT_DPI = 96;
    //72
    public $DPI = 300;
    /**
     * Data identifier
     * @var string or numeric
     */
    public $identifier = null;
    /**
     * Constructor
     * @param string or numeric $identifier
     */
    public function __construct($identifier = null)
    {
        $this->identifier = $identifier;
    }
    /**
     * Get value from $data array
     * @param type $key
     * @return boolean
     */
    /*public function get($key) {
          if (isset($this->data[$key])) {
              return $this->data[$key];
          }
          return false;
      }*/
    /**
     * Set value to $data array
     * @param type $key
     * @param type $value
     */
    /*public function set($key, $value) {
          $this->data[$key] = $value;
      }*/
    private function check_possible_values($lookup_value, $lookup_array)
    {
        if (in_array($lookup_value, $lookup_array)) {
            return $lookup_value;
        }
        return current($lookup_array);
    }
    /**
     * Load params from array
     * @param array $params
     */
    public function post($params = [])
    {
        $this->data['options']['version'] = $this->check_possible_values($params['version'], ['simple', 'advanced']);
        $this->data['options']['material'] = $this->check_possible_values($params['material'], ['paper', 'vinil']);
        $this->data['options']['content'] = $this->check_possible_values($params['content'], ['custom', 'plain']);
        //if ($this->data['options']['material'] == 'vinil') {
        //    $this->data['settings']['paper-settings']['size'] = 19;
        //} else {
        $this->data['settings']['paper-settings']['size'] = $this->check_possible_values($params['size'], [25, 19]);
        //}
        $this->data['settings']['paper-settings']['colour'] = [];
        if (isset($this->colour[$params['colour']])) {
            $this->data['settings']['paper-settings']['colour'][$this->colour[$params['colour']]] = 10;
            //qty of copies
        }
        if ($this->data['options']['content'] == 'custom') {
            $this->data['settings']['text-settings']['use-text'] = $this->check_possible_values($params['use-text'], ['no', 'yes']);
            if ($this->data['settings']['text-settings']['use-text'] == 'yes') {
                $this->data['settings']['text-settings']['text'] = ['font' => $params['text_font_1'], 'size' => $params['text_size_1'], 'content' => $params['text_line_1']];
                $this->data['settings']['text-settings']['text']['use-second-line'] = $this->check_possible_values($params['use-second-line'], ['no', 'yes']);
                if ($this->data['settings']['text-settings']['text']['use-second-line'] == 'yes') {
                    $this->data['settings']['text-settings']['text']['second-line'] = ['font' => $params['text_font_2'], 'size' => $params['text_size_2'], 'content' => $params['text_line_2']];
                }
            }
            $this->data['settings']['logo-settings']['type'] = $this->check_possible_values($params['use_logo'], ['email', 'artwork', 'upload']);
            if ($this->data['settings']['logo-settings']['type'] == 'artwork') {
                $this->data['settings']['logo-settings']['artwork'] = ['position' => $this->check_possible_values($params['select-position'], ['left', 'right', 'both']), 'filename' => $params['art_filename']];
            }
            if ($this->data['settings']['logo-settings']['type'] == 'upload') {
                $this->data['settings']['logo-settings']['upload'] = ['position' => $this->check_possible_values($params['select-position-upload'], ['left', 'right', 'both']), 'filename' => $params['upload_filename']];
            }
        }
        $this->data['settings']['branding-settings']['remove-branding'] = $this->check_possible_values($params['remove-branding'], ['no', 'yes']);
        /*foreach($params as $key => $value) {
              $this->set($key, $value);
          }*/
    }
    private function parse_simple_xml($xmldata)
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
                $children[$name][] = $this->parse_simple_xml($child);
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
            $xmldata = iconv('utf-8', CHARSET, $xmldata);
            $returndata = (string) $xmldata;
        }
        return $returndata;
    }
    /**
     * Load params
     */
    public function load($identifier = null)
    {
        if ($identifier != null) {
            $this->identifier = $identifier;
        }
        if ($this->identifier == null) {
            return false;
        }
        if (self::STORAGE_TYPE == 'FILE') {
            $filename = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $this->identifier;
            if (file_exists($filename)) {
                $xmlstring = file_get_contents($filename);
                $xml = simplexml_load_string($xmlstring);
                $this->data = $this->parse_simple_xml($xml);
            }
        } elseif (self::STORAGE_TYPE == 'DB') {
        }
        return false;
    }
    /**
     * Save params
     */
    public function save($identifier = null)
    {
        if ($identifier != null) {
            $this->identifier = $identifier;
        }
        $xml = $this->to_xml();
        if ($this->identifier == null) {
            //add
            if (self::STORAGE_TYPE == 'FILE') {
                if (!file_exists(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR)) {
                    mkdir(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR, 0777, true);
                }
                $filename = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'config.xml';
                $fp = fopen($filename, 'w');
                fputs($fp, $xml);
                fclose($fp);
            } elseif (self::STORAGE_TYPE == 'DB') {
            } else {
                return false;
            }
        } else if (self::STORAGE_TYPE == 'FILE') {
            if (!file_exists(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR)) {
                mkdir(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR, 0777, true);
            }
            $filename = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $this->identifier;
            $fp = fopen($filename, 'w');
            fputs($fp, $xml);
            fclose($fp);
        } elseif (self::STORAGE_TYPE == 'DB') {
        } else {
            return false;
        }
    }
    private function scale_dpi($size)
    {
        return (int) ($size * $this->DPI);
    }
    private function scale_ppi($px)
    {
        return (int) ($px * $this->DPI / self::DEFAULT_DPI);
    }
    private function get_height()
    {
        if ($this->data['settings']['paper-settings']['size'] == '25') {
            $width = $this->scale_dpi(1);
        } else {
            $width = $this->scale_dpi(3 / 4);
        }
        return $width;
    }
    private function get_width()
    {
        if ($this->data['options']['material'] == 'vinil') {
            $height = $this->scale_dpi(9.25);
        } else {
            $height = $this->scale_dpi(7.5625);
        }
        return $height;
    }
    public function create_image($return_object = false, $default_dpi = true)
    {
        if ($default_dpi) {
            $this->DPI = self::DEFAULT_DPI;
        }
        $imagine = new \Imagine\Gd\Imagine();
        $size = new \Imagine\Image\Box($this->get_width(), $this->get_height());
        $color = new \Imagine\Image\Color('#000', 100);
        $image = $imagine->create($size, $color);
        if ($this->data['options']['content'] == 'custom') {
            $x = $this->scale_ppi(57);
            $logo_type = $this->data['settings']['logo-settings']['type'];
            if ($this->data['settings']['logo-settings']['type'] == 'artwork') {
                $art_filename = $this->data['settings']['logo-settings']['artwork']['filename'];
                if (!empty($art_filename) && file_exists(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . $art_filename)) {
                    $art_image = $imagine->open(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . $art_filename);
                }
            } elseif ($this->data['settings']['logo-settings']['type'] == 'upload') {
                $upload_filename = $this->data['settings']['logo-settings']['upload']['filename'];
                if (!empty($upload_filename) && file_exists(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $upload_filename)) {
                    $art_image = $imagine->open(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $upload_filename);
                }
            }
            if (is_object($art_image)) {
                $size = $image->get_size();
                $w_size = $art_image->get_size();
                $art_image->resize(new \Imagine\Image\Box($w_size->get_width() * ($size->get_height() / $w_size->get_height()), $size->get_height()));
                $w_size = $art_image->get_size();
                //$image->paste($artImage, $bottomRight);
                $y = (int) (($this->get_height() - $w_size->get_height()) / 2);
                $x = $w_size->get_width() + $this->scale_dpi(1 / 4);
                $art_position = $this->data['settings']['logo-settings'][$logo_type]['position'];
                if ($art_position == 'left' || $art_position == 'both') {
                    $bottom_left = new \Imagine\Image\Point($this->scale_dpi(1 / 8), $y);
                    $image->paste($art_image, $bottom_left);
                }
                if ($art_position == 'right' || $art_position == 'both') {
                    $bottom_right = new \Imagine\Image\Point($size->get_width() - $w_size->get_width() - $this->scale_dpi(1 / 8), $y);
                    $image->paste($art_image, $bottom_right);
                }
            }
            if ($this->data['settings']['text-settings']['use-text'] == 'yes') {
                $font = new \Imagine\Gd\Font(DIR_FS_CATALOG . 'includes' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . $this->data['settings']['text-settings']['text']['font'] . '.ttf', $this->scale_ppi($this->data['settings']['text-settings']['text']['size']), new \Imagine\Image\Color('000000', 0));
                $text = '';
                //"Type text here or use the options below";
                if (!empty($this->data['settings']['text-settings']['text']['content'])) {
                    $text = $this->data['settings']['text-settings']['text']['content'];
                }
                $text_center_y = (int) ($this->get_height() / 2);
                if ($this->data['settings']['paper-settings']['size'] == 25 && $this->data['settings']['text-settings']['text']['use-second-line'] == 'yes') {
                    $fontsize = $font->get_size();
                    $linewidth = $image->draw()->text_width($text, $font);
                    $x = (int) (($size->get_width() - $linewidth) / 2);
                    if ($x + $linewidth > $size->get_width() || $x < 0) {
                        $text = 'Text too long';
                        $x = $w_size->get_width() + $this->scale_dpi(1 / 4);
                    }
                    $image->draw()->text($text, $font, new \Imagine\Image\Point($x, $text_center_y - $fontsize - $this->scale_ppi(3)));
                    $font2 = new \Imagine\Gd\Font(DIR_FS_CATALOG . 'includes' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . $this->data['settings']['text-settings']['text']['second-line']['font'] . '.ttf', $this->scale_ppi($this->data['settings']['text-settings']['text']['second-line']['size']), new \Imagine\Image\Color('000000', 0));
                    $text2 = '';
                    //"Use the options below - use the comments box if you want a different type of layout";
                    if (!empty($this->data['settings']['text-settings']['text']['second-line']['content'])) {
                        $text2 = $this->data['settings']['text-settings']['text']['second-line']['content'];
                    }
                    $linewidth = $image->draw()->text_width($text2, $font2);
                    $x = (int) (($size->get_width() - $linewidth) / 2);
                    if ($x + $linewidth > $size->get_width() || $x < 0) {
                        $text2 = 'Text too long';
                        $x = $w_size->get_width() + $this->scale_dpi(1 / 4);
                    }
                    $image->draw()->text($text2, $font2, new \Imagine\Image\Point($x, $text_center_y + $this->scale_ppi(3)));
                } else {
                    $fontsize = $font->get_size();
                    $linewidth = $image->draw()->text_width($text, $font);
                    $x = (int) (($size->get_width() - $linewidth) / 2);
                    if ($x + $linewidth > $size->get_width() || $x < 0) {
                        $text = 'Text too long';
                        $x = $w_size->get_width() + $this->scale_dpi(1 / 4);
                    }
                    $image->draw()->text($text, $font, new \Imagine\Image\Point($x, (int) ($text_center_y - $fontsize / 2)));
                }
            }
        }
        $options = ['resolution-units' => \Imagine\Image\Image_Interface::RESOLUTION_PIXELSPERINCH, 'resolution-x' => $this->DPI, 'resolution-y' => $this->DPI, 'resampling-filter' => \Imagine\Image\Image_Interface::FILTER_LANCZOS, 'png_compression_level' => 9];
        if ($return_object) {
            return $image;
        }
        if (!file_exists(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR)) {
            mkdir(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR, 0777, true);
        }
        $image->save(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'test.png', $options);
        //header('Content-Type: image/png');
        //$image->show('png');
        //die();
        $path = \Yii::get_alias('@web');
        //echo '<div class="cus-print'.($this->get('size') == '25'? '' : ' cus-print-small').'">';
        //echo '<div class="cus-print-wrist cus-print-wrist-'.$this->get('colour').'">';
        //echo '<div class="cus-print-wrist-cont">';
        //echo '<div class="wcm_print">';
        echo '<img src="' . $path . '/images/tmp/test.png?' . time() . '" height="100%" width="100%">';
        //echo '<div>';
        //echo '<div>';
        //echo '<div>';
        //echo '<div>';
    }
    public function create_page()
    {
        $pattern = $this->create_image(true, false);
        $imagine = new \Imagine\Gd\Imagine();
        if ($this->data['settings']['paper-settings']['size'] == '25') {
            $size = new \Imagine\Image\Box($this->scale_dpi(9.75), $this->scale_dpi(8));
            $qty_per_page = 8;
        } else {
            $size = new \Imagine\Image\Box($this->scale_dpi(9.75), $this->scale_dpi(7.5));
            $qty_per_page = 10;
        }
        $color = new \Imagine\Image\Color('#000', 100);
        $image = $imagine->create($size, $color);
        $offset = $size->get_height() / $qty_per_page;
        for ($step = 0; $step < $qty_per_page; $step++) {
            $position = new \Imagine\Image\Point($this->scale_dpi(1.25), $step * $offset);
            $image->paste($pattern, $position);
        }
        $options = ['resolution-units' => \Imagine\Image\Image_Interface::RESOLUTION_PIXELSPERINCH, 'resolution-x' => $this->DPI, 'resolution-y' => $this->DPI, 'resampling-filter' => \Imagine\Image\Image_Interface::FILTER_LANCZOS, 'png_compression_level' => 9];
        if (!file_exists(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR)) {
            mkdir(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR, 0777, true);
        }
        $image->save(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'page.png', $options);
    }
    public function create_document()
    {
        $this->create_page();
        $path = \Yii::get_alias('@vendor');
        require_once $path . '/fpdf/fpdf.php';
        if ($this->data['settings']['paper-settings']['size'] == '25') {
            $pdf = new \FPDF('L', 'in', [9.75, 8]);
        } else {
            $pdf = new \FPDF('L', 'in', [9.75, 7.5]);
        }
        for ($page = 0; $page < 10; $page++) {
            $pdf->add_page();
            $pdf->Image(DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'page.png', 0, 0, -300);
        }
        $pdf->Output('F', DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'document.pdf');
    }
    /**
     * Return $data array
     */
    public function to_array()
    {
        return $this->data;
    }
    private function array_to_xml($data, &$xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item' . $key;
                    //dealing with <0/>..<n/> issues
                }
                $subnode = $xml_data->add_child($key);
                $this->array_to_xml($value, $subnode);
            } else {
                $xml_data->add_child("{$key}", htmlspecialchars("{$value}"));
            }
        }
    }
    /**
     * Convert $data array to xml
     */
    public function to_xml($root = null)
    {
        $xml = new \Simple_Xml_Element($root ? '<' . $root . '/>' : '<root/>');
        $this->array_to_xml($this->data, $xml);
        return $xml->as_xml();
    }
    /**
     * Convert $data array to JSON
     */
    public function to_json()
    {
        return json_encode($this->data);
    }
}