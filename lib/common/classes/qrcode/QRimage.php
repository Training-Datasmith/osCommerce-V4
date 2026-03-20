<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//---- qrimage.php -----------------------------
/*
 * PHP QR Code encoder
 *
 * Image output of code using GD2
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
class Q_Rimage
{
    //----------------------------------------------------------------------
    public static function png($frame, $filename = false, $pixel_per_point = 4, $outer_frame = 4, $saveandprint = false)
    {
        $image = self::image($frame, $pixel_per_point, $outer_frame);
        if ($filename === false) {
            Header('Content-type: image/png');
            image_png($image);
        } else if ($saveandprint === true) {
            image_png($image, $filename);
            header('Content-type: image/png');
            image_png($image);
        } else {
            image_png($image, $filename);
        }
        image_destroy($image);
    }
    //----------------------------------------------------------------------
    public static function jpg($frame, $filename = false, $pixel_per_point = 8, $outer_frame = 4, $q = 85)
    {
        $image = self::image($frame, $pixel_per_point, $outer_frame);
        if ($filename === false) {
            Header('Content-type: image/jpeg');
            image_jpeg($image, null, $q);
        } else {
            image_jpeg($image, $filename, $q);
        }
        image_destroy($image);
    }
    //----------------------------------------------------------------------
    private static function image($frame, $pixel_per_point = 4, $outer_frame = 4)
    {
        $h = count($frame);
        $w = strlen($frame[0]);
        $img_w = $w + 2 * $outer_frame;
        $img_h = $h + 2 * $outer_frame;
        $base_image = image_create($img_w, $img_h);
        $col[0] = image_color_allocate($base_image, 255, 255, 255);
        $col[1] = image_color_allocate($base_image, 0, 0, 0);
        imagefill($base_image, 0, 0, $col[0]);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($frame[$y][$x] == '1') {
                    image_set_pixel($base_image, $x + $outer_frame, $y + $outer_frame, $col[1]);
                }
            }
        }
        $target_image = image_create($img_w * $pixel_per_point, $img_h * $pixel_per_point);
        image_copy_resized($target_image, $base_image, 0, 0, 0, 0, $img_w * $pixel_per_point, $img_h * $pixel_per_point, $img_w, $img_h);
        image_destroy($base_image);
        return $target_image;
    }
}