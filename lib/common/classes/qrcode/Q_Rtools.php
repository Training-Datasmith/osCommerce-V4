<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//---- qrtools.php -----------------------------
/*
 * PHP QR Code encoder
 *
 * Toolset, handy and debug utilites.
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
class Q_Rtools
{
    //----------------------------------------------------------------------
    public static function binarize($frame)
    {
        $len = count($frame);
        foreach ($frame as &$frame_line) {
            for ($i = 0; $i < $len; $i++) {
                $frame_line[$i] = ord($frame_line[$i]) & 1 ? '1' : '0';
            }
        }
        return $frame;
    }
    //----------------------------------------------------------------------
    public static function tcpdf_barcode_array($code, $mode = 'QR,L', $tc_pdf_version = '4.5.037')
    {
        $barcode_array = [];
        if (!is_array($mode)) {
            $mode = explode(',', $mode);
        }
        $ecc_level = 'L';
        if (count($mode) > 1) {
            $ecc_level = $mode[1];
        }
        $qr_tab = Q_Rcode::text($code, false, $ecc_level);
        $size = count($qr_tab);
        $barcode_array['num_rows'] = $size;
        $barcode_array['num_cols'] = $size;
        $barcode_array['bcode'] = [];
        foreach ($qr_tab as $line) {
            $arr_add = [];
            foreach (str_split($line) as $char) {
                $arr_add[] = $char == '1' ? 1 : 0;
            }
            $barcode_array['bcode'][] = $arr_add;
        }
        return $barcode_array;
    }
    //----------------------------------------------------------------------
    public static function clear_cache()
    {
        self::$frames = [];
    }
    //----------------------------------------------------------------------
    public static function build_cache()
    {
        Q_Rtools::mark_time('before_build_cache');
        $mask = new Q_Rmask();
        for ($a = 1; $a <= QRSPEC_VERSION_MAX; $a++) {
            $frame = Q_Rspec::new_frame($a);
            if (QR_IMAGE) {
                $file_name = QR_CACHE_DIR . 'frame_' . $a . '.png';
                Q_Rimage::png(self::binarize($frame), $file_name, 1, 0);
            }
            $width = count($frame);
            $bit_mask = array_fill(0, $width, array_fill(0, $width, 0));
            for ($mask_no = 0; $mask_no < 8; $mask_no++) {
                $mask->make_mask_no($mask_no, $width, $frame, $bit_mask, true);
            }
        }
        Q_Rtools::mark_time('after_build_cache');
    }
    //----------------------------------------------------------------------
    public static function log($outfile, $err)
    {
        if (QR_LOG_DIR !== false) {
            if ($err != '') {
                if ($outfile !== false) {
                    file_put_contents(QR_LOG_DIR . basename($outfile) . '-errors.txt', date('Y-m-d H:i:s') . ': ' . $err, FILE_APPEND);
                } else {
                    file_put_contents(QR_LOG_DIR . 'errors.txt', date('Y-m-d H:i:s') . ': ' . $err, FILE_APPEND);
                }
            }
        }
    }
    //----------------------------------------------------------------------
    public static function dump_mask($frame)
    {
        $width = count($frame);
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                echo ord($frame[$y][$x]) . ',';
            }
        }
    }
    //----------------------------------------------------------------------
    public static function mark_time($marker_id)
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = (float) $usec + (float) $sec;
        if (!isset($GLOBALS['qr_time_bench'])) {
            $GLOBALS['qr_time_bench'] = [];
        }
        $GLOBALS['qr_time_bench'][$marker_id] = $time;
    }
    //----------------------------------------------------------------------
    public static function time_benchmark()
    {
        self::mark_time('finish');
        $last_time = 0;
        $start_time = 0;
        $p = 0;
        echo '<table cellpadding="3" cellspacing="1">
                    <thead><tr style="border-bottom:1px solid silver"><td colspan="2" style="text-align:center">BENCHMARK</td></tr></thead>
                    <tbody>';
        foreach ($GLOBALS['qr_time_bench'] as $marker_id => $this_time) {
            if ($p > 0) {
                echo '<tr><th style="text-align:right">till ' . $marker_id . ': </th><td>' . number_format($this_time - $last_time, 6) . 's</td></tr>';
            } else {
                $start_time = $this_time;
            }
            $p++;
            $last_time = $this_time;
        }
        echo '</tbody><tfoot>
                <tr style="border-top:2px solid black"><th style="text-align:right">TOTAL: </th><td>' . number_format($last_time - $start_time, 6) . 's</td></tr>
            </tfoot>
            </table>';
    }
}
//##########################################################################
Q_Rtools::mark_time('start');