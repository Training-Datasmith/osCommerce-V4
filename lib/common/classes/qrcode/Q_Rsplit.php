<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//---- qrsplit.php -----------------------------
/*
 * PHP QR Code encoder
 *
 * Input splitting classes
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * The following data / specifications are taken from
 * "Two dimensional symbol -- QR-code -- Basic Specification" (JIS X0510:2004)
 *  or
 * "Automatic identification and data capture techniques --
 *  QR Code 2005 bar code symbology specification" (ISO/IEC 18004:2006)
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
class Q_Rsplit
{
    public $data_str = '';
    public $input;
    public $mode_hint;
    //----------------------------------------------------------------------
    public function __construct($data_str, $input, $mode_hint)
    {
        $this->data_str = $data_str;
        $this->input = $input;
        $this->mode_hint = $mode_hint;
    }
    //----------------------------------------------------------------------
    public static function isdigitat($str, $pos)
    {
        if ($pos >= strlen($str)) {
            return false;
        }
        return ord($str[$pos]) >= ord('0') && ord($str[$pos]) <= ord('9');
    }
    //----------------------------------------------------------------------
    public static function isalnumat($str, $pos)
    {
        if ($pos >= strlen($str)) {
            return false;
        }
        return Q_Rinput::look_an_table(ord($str[$pos])) >= 0;
    }
    //----------------------------------------------------------------------
    public function identify_mode($pos)
    {
        if ($pos >= strlen($this->data_str)) {
            return QR_MODE_NUL;
        }
        $c = $this->data_str[$pos];
        if (self::isdigitat($this->data_str, $pos)) {
            return QR_MODE_NUM;
        } elseif (self::isalnumat($this->data_str, $pos)) {
            return QR_MODE_AN;
        } elseif ($this->mode_hint == QR_MODE_KANJI) {
            if ($pos + 1 < strlen($this->data_str)) {
                $d = $this->data_str[$pos + 1];
                $word = ord($c) << 8 | ord($d);
                if ($word >= 0x8140 && $word <= 0x9ffc || $word >= 0xe040 && $word <= 0xebbf) {
                    return QR_MODE_KANJI;
                }
            }
        }
        return QR_MODE_8;
    }
    //----------------------------------------------------------------------
    public function eat_num()
    {
        $ln = Q_Rspec::length_indicator(QR_MODE_NUM, $this->input->get_version());
        $p = 0;
        while (self::isdigitat($this->data_str, $p)) {
            $p++;
        }
        $run = $p;
        $mode = $this->identify_mode($p);
        if ($mode == QR_MODE_8) {
            $dif = Q_Rinput::estimate_bits_mode_num($run) + 4 + $ln + Q_Rinput::estimate_bits_mode8(1) - Q_Rinput::estimate_bits_mode8($run + 1);
            // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        if ($mode == QR_MODE_AN) {
            $dif = Q_Rinput::estimate_bits_mode_num($run) + 4 + $ln + Q_Rinput::estimate_bits_mode_an(1) - Q_Rinput::estimate_bits_mode_an($run + 1);
            // - 4 - la
            if ($dif > 0) {
                return $this->eat_an();
            }
        }
        $ret = $this->input->append(QR_MODE_NUM, $run, str_split($this->data_str));
        if ($ret < 0) {
            return -1;
        }
        return $run;
    }
    //----------------------------------------------------------------------
    public function eat_an()
    {
        $la = Q_Rspec::length_indicator(QR_MODE_AN, $this->input->get_version());
        $ln = Q_Rspec::length_indicator(QR_MODE_NUM, $this->input->get_version());
        $p = 0;
        while (self::isalnumat($this->data_str, $p)) {
            if (self::isdigitat($this->data_str, $p)) {
                $q = $p;
                while (self::isdigitat($this->data_str, $q)) {
                    $q++;
                }
                $dif = Q_Rinput::estimate_bits_mode_an($p) + Q_Rinput::estimate_bits_mode_num($q - $p) + 4 + $ln - Q_Rinput::estimate_bits_mode_an($q);
                // - 4 - la
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }
        $run = $p;
        if (!self::isalnumat($this->data_str, $p)) {
            $dif = Q_Rinput::estimate_bits_mode_an($run) + 4 + $la + Q_Rinput::estimate_bits_mode8(1) - Q_Rinput::estimate_bits_mode8($run + 1);
            // - 4 - l8
            if ($dif > 0) {
                return $this->eat8();
            }
        }
        $ret = $this->input->append(QR_MODE_AN, $run, str_split($this->data_str));
        if ($ret < 0) {
            return -1;
        }
        return $run;
    }
    //----------------------------------------------------------------------
    public function eat_kanji()
    {
        $p = 0;
        while ($this->identify_mode($p) == QR_MODE_KANJI) {
            $p += 2;
        }
        $ret = $this->input->append(QR_MODE_KANJI, $p, str_split($this->data_str));
        if ($ret < 0) {
            return -1;
        }
        return $run;
    }
    //----------------------------------------------------------------------
    public function eat8()
    {
        $la = Q_Rspec::length_indicator(QR_MODE_AN, $this->input->get_version());
        $ln = Q_Rspec::length_indicator(QR_MODE_NUM, $this->input->get_version());
        $p = 1;
        $data_str_len = strlen($this->data_str);
        while ($p < $data_str_len) {
            $mode = $this->identify_mode($p);
            if ($mode == QR_MODE_KANJI) {
                break;
            }
            if ($mode == QR_MODE_NUM) {
                $q = $p;
                while (self::isdigitat($this->data_str, $q)) {
                    $q++;
                }
                $dif = Q_Rinput::estimate_bits_mode8($p) + Q_Rinput::estimate_bits_mode_num($q - $p) + 4 + $ln - Q_Rinput::estimate_bits_mode8($q);
                // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } elseif ($mode == QR_MODE_AN) {
                $q = $p;
                while (self::isalnumat($this->data_str, $q)) {
                    $q++;
                }
                $dif = Q_Rinput::estimate_bits_mode8($p) + Q_Rinput::estimate_bits_mode_an($q - $p) + 4 + $la - Q_Rinput::estimate_bits_mode8($q);
                // - 4 - l8
                if ($dif < 0) {
                    break;
                } else {
                    $p = $q;
                }
            } else {
                $p++;
            }
        }
        $run = $p;
        $ret = $this->input->append(QR_MODE_8, $run, str_split($this->data_str));
        if ($ret < 0) {
            return -1;
        }
        return $run;
    }
    //----------------------------------------------------------------------
    public function split_string()
    {
        while (strlen($this->data_str) > 0) {
            if ($this->data_str == '') {
                return 0;
            }
            $mode = $this->identify_mode(0);
            switch ($mode) {
                case QR_MODE_NUM:
                    $length = $this->eat_num();
                    break;
                case QR_MODE_AN:
                    $length = $this->eat_an();
                    break;
                case QR_MODE_KANJI:
                    if ($hint == QR_MODE_KANJI) {
                        $length = $this->eat_kanji();
                    } else {
                        $length = $this->eat8();
                    }
                    break;
                default:
                    $length = $this->eat8();
                    break;
            }
            if ($length == 0) {
                return 0;
            }
            if ($length < 0) {
                return -1;
            }
            $this->data_str = substr($this->data_str, $length);
        }
    }
    //----------------------------------------------------------------------
    public function to_upper()
    {
        $string_len = strlen($this->data_str);
        $p = 0;
        while ($p < $string_len) {
            $mode = self::identify_mode(substr($this->data_str, $p), $this->mode_hint);
            if ($mode == QR_MODE_KANJI) {
                $p += 2;
            } else {
                if (ord($this->data_str[$p]) >= ord('a') && ord($this->data_str[$p]) <= ord('z')) {
                    $this->data_str[$p] = chr(ord($this->data_str[$p]) - 32);
                }
                $p++;
            }
        }
        return $this->data_str;
    }
    //----------------------------------------------------------------------
    public static function split_string_to_q_rinput($string, Q_Rinput $input, $mode_hint, $casesensitive = true)
    {
        if (is_null($string) || $string == '\0' || $string == '') {
            throw new \Exception('empty string!!!');
        }
        $split = new Q_Rsplit($string, $input, $mode_hint);
        if (!$casesensitive) {
            $split->to_upper();
        }
        return $split->split_string();
    }
}