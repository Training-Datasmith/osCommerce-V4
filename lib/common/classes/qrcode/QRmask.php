<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//---- qrmask.php -----------------------------
/*
 * PHP QR Code encoder
 *
 * Masking
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
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
class Q_Rmask
{
    public $run_length = [];
    //----------------------------------------------------------------------
    public function __construct()
    {
        $this->run_length = array_fill(0, QRSPEC_WIDTH_MAX + 1, 0);
    }
    //----------------------------------------------------------------------
    public function write_format_information($width, &$frame, $mask, $level)
    {
        $blacks = 0;
        $format = Q_Rspec::get_format_info($mask, $level);
        for ($i = 0; $i < 8; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }
            $frame[8][$width - 1 - $i] = chr($v);
            if ($i < 6) {
                $frame[$i][8] = chr($v);
            } else {
                $frame[$i + 1][8] = chr($v);
            }
            $format = $format >> 1;
        }
        for ($i = 0; $i < 7; $i++) {
            if ($format & 1) {
                $blacks += 2;
                $v = 0x85;
            } else {
                $v = 0x84;
            }
            $frame[$width - 7 + $i][8] = chr($v);
            if ($i == 0) {
                $frame[8][7] = chr($v);
            } else {
                $frame[8][6 - $i] = chr($v);
            }
            $format = $format >> 1;
        }
        return $blacks;
    }
    //----------------------------------------------------------------------
    public function mask0($x, $y)
    {
        return $x + $y & 1;
    }
    public function mask1($x, $y)
    {
        return $y & 1;
    }
    public function mask2($x, $y)
    {
        return $x % 3;
    }
    public function mask3($x, $y)
    {
        return ($x + $y) % 3;
    }
    public function mask4($x, $y)
    {
        return (int) ($y / 2) + (int) ($x / 3) & 1;
    }
    public function mask5($x, $y)
    {
        return ($x * $y & 1) + $x * $y % 3;
    }
    public function mask6($x, $y)
    {
        return ($x * $y & 1) + $x * $y % 3 & 1;
    }
    public function mask7($x, $y)
    {
        return $x * $y % 3 + ($x + $y & 1) & 1;
    }
    //----------------------------------------------------------------------
    private function generate_mask_no($mask_no, $width, $frame)
    {
        $bit_mask = array_fill(0, $width, array_fill(0, $width, 0));
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (ord($frame[$y][$x]) & 0x80) {
                    $bit_mask[$y][$x] = 0;
                } else {
                    $mask_func = call_user_func([$this, 'mask' . $mask_no], $x, $y);
                    $bit_mask[$y][$x] = $mask_func == 0 ? 1 : 0;
                }
            }
        }
        return $bit_mask;
    }
    //----------------------------------------------------------------------
    public static function serial($bit_frame)
    {
        $code_arr = [];
        foreach ($bit_frame as $line) {
            $code_arr[] = join('', $line);
        }
        return gzcompress(join("\n", $code_arr), 9);
    }
    //----------------------------------------------------------------------
    public static function unserial($code)
    {
        $code_arr = [];
        $code_lines = explode("\n", gzuncompress($code));
        foreach ($code_lines as $line) {
            $code_arr[] = str_split($line);
        }
        return $code_arr;
    }
    //----------------------------------------------------------------------
    public function make_mask_no($mask_no, $width, $s, &$d, $mask_gen_only = false)
    {
        $b = 0;
        $bit_mask = [];
        $file_name = QR_CACHE_DIR . 'mask_' . $mask_no . DIRECTORY_SEPARATOR . 'mask_' . $width . '_' . $mask_no . '.dat';
        if (QR_CACHEABLE) {
            if (file_exists($file_name)) {
                $bit_mask = self::unserial(file_get_contents($file_name));
            } else {
                $bit_mask = $this->generate_mask_no($mask_no, $width, $s, $d);
                if (!file_exists(QR_CACHE_DIR . 'mask_' . $mask_no)) {
                    mkdir(QR_CACHE_DIR . 'mask_' . $mask_no);
                }
                file_put_contents($file_name, self::serial($bit_mask));
            }
        } else {
            $bit_mask = $this->generate_mask_no($mask_no, $width, $s, $d);
        }
        if ($mask_gen_only) {
            return;
        }
        $d = $s;
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($bit_mask[$y][$x] == 1) {
                    $d[$y][$x] = chr(ord($s[$y][$x]) ^ (int) $bit_mask[$y][$x]);
                }
                $b += (int) (ord($d[$y][$x]) & 1);
            }
        }
        return $b;
    }
    //----------------------------------------------------------------------
    public function make_mask($width, $frame, $mask_no, $level)
    {
        $masked = array_fill(0, $width, str_repeat("\x00", $width));
        $this->make_mask_no($mask_no, $width, $frame, $masked);
        $this->write_format_information($width, $masked, $mask_no, $level);
        return $masked;
    }
    //----------------------------------------------------------------------
    public function calc_n1n3($length)
    {
        $demerit = 0;
        for ($i = 0; $i < $length; $i++) {
            if ($this->run_length[$i] >= 5) {
                $demerit += N1 + ($this->run_length[$i] - 5);
            }
            if ($i & 1) {
                if ($i >= 3 && $i < $length - 2 && $this->run_length[$i] % 3 == 0) {
                    $fact = (int) ($this->run_length[$i] / 3);
                    if ($this->run_length[$i - 2] == $fact && $this->run_length[$i - 1] == $fact && $this->run_length[$i + 1] == $fact && $this->run_length[$i + 2] == $fact) {
                        if ($this->run_length[$i - 3] < 0 || $this->run_length[$i - 3] >= 4 * $fact) {
                            $demerit += N3;
                        } elseif ($i + 3 >= $length || $this->run_length[$i + 3] >= 4 * $fact) {
                            $demerit += N3;
                        }
                    }
                }
            }
        }
        return $demerit;
    }
    //----------------------------------------------------------------------
    public function evaluate_symbol($width, $frame)
    {
        $head = 0;
        $demerit = 0;
        for ($y = 0; $y < $width; $y++) {
            $head = 0;
            $this->run_length[0] = 1;
            $frame_y = $frame[$y];
            if ($y > 0) {
                $frame_ym = $frame[$y - 1];
            }
            for ($x = 0; $x < $width; $x++) {
                if ($x > 0 && $y > 0) {
                    $b22 = ord($frame_y[$x]) & ord($frame_y[$x - 1]) & ord($frame_ym[$x]) & ord($frame_ym[$x - 1]);
                    $w22 = ord($frame_y[$x]) | ord($frame_y[$x - 1]) | ord($frame_ym[$x]) | ord($frame_ym[$x - 1]);
                    if (($b22 | $w22 ^ 1) & 1) {
                        $demerit += N2;
                    }
                }
                if ($x == 0 && ord($frame_y[$x]) & 1) {
                    $this->run_length[0] = -1;
                    $head = 1;
                    $this->run_length[$head] = 1;
                } elseif ($x > 0) {
                    if ((ord($frame_y[$x]) ^ ord($frame_y[$x - 1])) & 1) {
                        $head++;
                        $this->run_length[$head] = 1;
                    } else {
                        $this->run_length[$head]++;
                    }
                }
            }
            $demerit += $this->calc_n1n3($head + 1);
        }
        for ($x = 0; $x < $width; $x++) {
            $head = 0;
            $this->run_length[0] = 1;
            for ($y = 0; $y < $width; $y++) {
                if ($y == 0 && ord($frame[$y][$x]) & 1) {
                    $this->run_length[0] = -1;
                    $head = 1;
                    $this->run_length[$head] = 1;
                } elseif ($y > 0) {
                    if ((ord($frame[$y][$x]) ^ ord($frame[$y - 1][$x])) & 1) {
                        $head++;
                        $this->run_length[$head] = 1;
                    } else {
                        $this->run_length[$head]++;
                    }
                }
            }
            $demerit += $this->calc_n1n3($head + 1);
        }
        return $demerit;
    }
    //----------------------------------------------------------------------
    public function mask($width, $frame, $level)
    {
        $min_demerit = PHP_INT_MAX;
        $best_mask_num = 0;
        $best_mask = [];
        $checked_masks = [0, 1, 2, 3, 4, 5, 6, 7];
        if (QR_FIND_FROM_RANDOM !== false) {
            $how_manu_out = 8 - QR_FIND_FROM_RANDOM % 9;
            for ($i = 0; $i < $how_manu_out; $i++) {
                $rem_pos = rand(0, count($checked_masks) - 1);
                unset($checked_masks[$rem_pos]);
                $checked_masks = array_values($checked_masks);
            }
        }
        $best_mask = $frame;
        foreach ($checked_masks as $i) {
            $mask = array_fill(0, $width, str_repeat("\x00", $width));
            $demerit = 0;
            $blacks = 0;
            $blacks = $this->make_mask_no($i, $width, $frame, $mask);
            $blacks += $this->write_format_information($width, $mask, $i, $level);
            $blacks = (int) (100 * $blacks / ($width * $width));
            $demerit = (int) ((int) (abs($blacks - 50) / 5) * N4);
            $demerit += $this->evaluate_symbol($width, $mask);
            if ($demerit < $min_demerit) {
                $min_demerit = $demerit;
                $best_mask = $mask;
                $best_mask_num = $i;
            }
        }
        return $best_mask;
    }
    //----------------------------------------------------------------------
}