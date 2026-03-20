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
 * Images
 */
class Images_Source
{
    protected $image_size;
    protected $image_source;
    public function __construct($image)
    {
        try {
            if (!function_exists('gd_info')) {
                throw new \Exception('GD Functions disabled ');
            }
            if (is_file($image)) {
                $this->image_size = @get_image_size($image);
                $this->detect_image_source($image);
            }
        } catch (\Exception $ex) {
            echo $ex->get_message();
        }
    }
    public function __destruct()
    {
        if ($this->image_source) {
            imagedestroy($this->image_source);
        }
    }
    public function detect_image_source($image)
    {
        if ($this->image_size) {
            switch ($this->image_size[2]) {
                case 1:
                    // GIF
                    $this->image_source = @image_create_from_gif($image);
                    break;
                case 3:
                    // PNG
                    $this->image_source = @image_create_from_png($image);
                    if ($this->image_source) {
                        if (function_exists('imageAntiAlias')) {
                            @image_anti_alias($this->image_source, true);
                        }
                        @image_alpha_blending($this->image_source, true);
                        @image_save_alpha($this->image_source, true);
                    }
                    break;
                case 2:
                    // JPEG
                    $this->image_source = @image_create_from_jpeg($image);
                    break;
                default:
                    $this->image_source = false;
            }
        } else {
            $this->image_source = false;
        }
    }
    /*return @source*/
    public function get_image_source()
    {
        return $this->image_source;
    }
    /*return @size&type*/
    public function get_image_size()
    {
        return $this->image_size;
    }
    public function crop_image($image_source, array $crop_rectangle)
    {
        return imagecrop($image_source, $crop_rectangle);
    }
    public function save_image_source($image_source, $destination = null, $quality = null)
    {
        if ($this->image_size && is_resource($image_source)) {
            switch ($this->image_size[2]) {
                case 1:
                    // GIF
                    @imagegif($image_source, $destination);
                    break;
                case 3:
                    // PNG
                    $quality = is_null($quality) ? 9 : $quality;
                    @image_png($image_source, $destination, $quality);
                    break;
                case 2:
                    // JPEG
                    $quality = is_null($quality) ? 100 : $quality;
                    @image_jpeg($image_source, $destination, $quality);
                    break;
                default:
                    break;
            }
            if (!is_null($destination)) {
                return $destination;
            }
        }
    }
}