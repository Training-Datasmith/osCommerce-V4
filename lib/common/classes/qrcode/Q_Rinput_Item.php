<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//---- qrinput.php -----------------------------
/*
 * PHP QR Code encoder
 *
 * Input encoding class
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
class Q_Rinput_Item
{
    public $mode;
    public $size;
    public $data;
    public $bstream;
    public function __construct($mode, $size, $data, $bstream = null)
    {
        $set_data = array_slice($data, 0, $size);
        if (count($set_data) < $size) {
            $set_data = array_merge($set_data, array_fill(0, $size - count($set_data), 0));
        }
        if (!Q_Rinput::check($mode, $size, $set_data)) {
            throw new \Exception('Error m:' . $mode . ',s:' . $size . ',d:' . join(',', $set_data));
            return null;
        }
        $this->mode = $mode;
        $this->size = $size;
        $this->data = $set_data;
        $this->bstream = $bstream;
    }
    //----------------------------------------------------------------------
    public function encode_mode_num($version)
    {
        try {
            $words = (int) ($this->size / 3);
            $bs = new Q_Rbitstream();
            $val = 0x1;
            $bs->append_num(4, $val);
            $bs->append_num(Q_Rspec::length_indicator(QR_MODE_NUM, $version), $this->size);
            for ($i = 0; $i < $words; $i++) {
                $val = (ord($this->data[$i * 3]) - ord('0')) * 100;
                $val += (ord($this->data[$i * 3 + 1]) - ord('0')) * 10;
                $val += ord($this->data[$i * 3 + 2]) - ord('0');
                $bs->append_num(10, $val);
            }
            if ($this->size - $words * 3 == 1) {
                $val = ord($this->data[$words * 3]) - ord('0');
                $bs->append_num(4, $val);
            } elseif ($this->size - $words * 3 == 2) {
                $val = (ord($this->data[$words * 3]) - ord('0')) * 10;
                $val += ord($this->data[$words * 3 + 1]) - ord('0');
                $bs->append_num(7, $val);
            }
            $this->bstream = $bs;
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function encode_mode_an($version)
    {
        try {
            $words = (int) ($this->size / 2);
            $bs = new Q_Rbitstream();
            $bs->append_num(4, 0x2);
            $bs->append_num(Q_Rspec::length_indicator(QR_MODE_AN, $version), $this->size);
            for ($i = 0; $i < $words; $i++) {
                $val = (int) Q_Rinput::look_an_table(ord($this->data[$i * 2])) * 45;
                $val += (int) Q_Rinput::look_an_table(ord($this->data[$i * 2 + 1]));
                $bs->append_num(11, $val);
            }
            if ($this->size & 1) {
                $val = Q_Rinput::look_an_table(ord($this->data[$words * 2]));
                $bs->append_num(6, $val);
            }
            $this->bstream = $bs;
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function encode_mode8($version)
    {
        try {
            $bs = new Q_Rbitstream();
            $bs->append_num(4, 0x4);
            $bs->append_num(Q_Rspec::length_indicator(QR_MODE_8, $version), $this->size);
            for ($i = 0; $i < $this->size; $i++) {
                $bs->append_num(8, ord($this->data[$i]));
            }
            $this->bstream = $bs;
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function encode_mode_kanji($version)
    {
        try {
            $bs = new Q_Rbitrtream();
            $bs->append_num(4, 0x8);
            $bs->append_num(Q_Rspec::length_indicator(QR_MODE_KANJI, $version), (int) ($this->size / 2));
            for ($i = 0; $i < $this->size; $i += 2) {
                $val = ord($this->data[$i]) << 8 | ord($this->data[$i + 1]);
                if ($val <= 0x9ffc) {
                    $val -= 0x8140;
                } else {
                    $val -= 0xc140;
                }
                $h = ($val >> 8) * 0xc0;
                $val = ($val & 0xff) + $h;
                $bs->append_num(13, $val);
            }
            $this->bstream = $bs;
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function encode_mode_structure()
    {
        try {
            $bs = new Q_Rbitstream();
            $bs->append_num(4, 0x3);
            $bs->append_num(4, ord($this->data[1]) - 1);
            $bs->append_num(4, ord($this->data[0]) - 1);
            $bs->append_num(8, ord($this->data[2]));
            $this->bstream = $bs;
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function estimate_bit_stream_size_of_entry($version)
    {
        $bits = 0;
        if ($version == 0) {
            $version = 1;
        }
        switch ($this->mode) {
            case QR_MODE_NUM:
                $bits = Q_Rinput::estimate_bits_mode_num($this->size);
                break;
            case QR_MODE_AN:
                $bits = Q_Rinput::estimate_bits_mode_an($this->size);
                break;
            case QR_MODE_8:
                $bits = Q_Rinput::estimate_bits_mode8($this->size);
                break;
            case QR_MODE_KANJI:
                $bits = Q_Rinput::estimate_bits_mode_kanji($this->size);
                break;
            case QR_MODE_STRUCTURE:
                return STRUCTURE_HEADER_BITS;
            default:
                return 0;
        }
        $l = Q_Rspec::length_indicator($this->mode, $version);
        $m = 1 << $l;
        $num = (int) (($this->size + $m - 1) / $m);
        $bits += $num * (4 + $l);
        return $bits;
    }
    //----------------------------------------------------------------------
    public function encode_bit_stream($version)
    {
        try {
            unset($this->bstream);
            $words = Q_Rspec::maximum_words($this->mode, $version);
            if ($this->size > $words) {
                $st1 = new Q_Rinput_Item($this->mode, $words, $this->data);
                $st2 = new Q_Rinput_Item($this->mode, $this->size - $words, array_slice($this->data, $words));
                $st1->encode_bit_stream($version);
                $st2->encode_bit_stream($version);
                $this->bstream = new Q_Rbitstream();
                $this->bstream->append($st1->bstream);
                $this->bstream->append($st2->bstream);
                unset($st1);
                unset($st2);
            } else {
                $ret = 0;
                switch ($this->mode) {
                    case QR_MODE_NUM:
                        $ret = $this->encode_mode_num($version);
                        break;
                    case QR_MODE_AN:
                        $ret = $this->encode_mode_an($version);
                        break;
                    case QR_MODE_8:
                        $ret = $this->encode_mode8($version);
                        break;
                    case QR_MODE_KANJI:
                        $ret = $this->encode_mode_kanji($version);
                        break;
                    case QR_MODE_STRUCTURE:
                        $ret = $this->encode_mode_structure();
                        break;
                    default:
                        break;
                }
                if ($ret < 0) {
                    return -1;
                }
            }
            return $this->bstream->size();
        } catch (\Exception $e) {
            return -1;
        }
    }
}