<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//##########################################################################
class Q_Rinput
{
    public $items;
    private $version;
    private $level;
    //----------------------------------------------------------------------
    public function __construct($version = 0, $level = QR_ECLEVEL_L)
    {
        if ($version < 0 || $version > QRSPEC_VERSION_MAX || $level > QR_ECLEVEL_H) {
            throw new \Exception('Invalid version no');
            return null;
        }
        $this->version = $version;
        $this->level = $level;
    }
    //----------------------------------------------------------------------
    public function get_version()
    {
        return $this->version;
    }
    //----------------------------------------------------------------------
    public function set_version($version)
    {
        if ($version < 0 || $version > QRSPEC_VERSION_MAX) {
            throw new \Exception('Invalid version no');
            return -1;
        }
        $this->version = $version;
        return 0;
    }
    //----------------------------------------------------------------------
    public function get_error_correction_level()
    {
        return $this->level;
    }
    //----------------------------------------------------------------------
    public function set_error_correction_level($level)
    {
        if ($level > QR_ECLEVEL_H) {
            throw new \Exception('Invalid ECLEVEL');
            return -1;
        }
        $this->level = $level;
        return 0;
    }
    //----------------------------------------------------------------------
    public function append_entry(Q_Rinput_Item $entry)
    {
        $this->items[] = $entry;
    }
    //----------------------------------------------------------------------
    public function append($mode, $size, $data)
    {
        try {
            $entry = new Q_Rinput_Item($mode, $size, $data);
            $this->items[] = $entry;
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function insert_structured_append_header($size, $index, $parity)
    {
        if ($size > MAX_STRUCTURED_SYMBOLS) {
            throw new \Exception('insertStructuredAppendHeader wrong size');
        }
        if ($index <= 0 || $index > MAX_STRUCTURED_SYMBOLS) {
            throw new \Exception('insertStructuredAppendHeader wrong index');
        }
        $buf = [$size, $index, $parity];
        try {
            $entry = new Q_Rinput_Item(QR_MODE_STRUCTURE, 3, buf);
            array_unshift($this->items, $entry);
            return 0;
        } catch (\Exception $e) {
            return -1;
        }
    }
    //----------------------------------------------------------------------
    public function calc_parity()
    {
        $parity = 0;
        foreach ($this->items as $item) {
            if ($item->mode != QR_MODE_STRUCTURE) {
                for ($i = $item->size - 1; $i >= 0; $i--) {
                    $parity ^= $item->data[$i];
                }
            }
        }
        return $parity;
    }
    //----------------------------------------------------------------------
    public static function check_mode_num($size, $data)
    {
        for ($i = 0; $i < $size; $i++) {
            if (ord($data[$i]) < ord('0') || ord($data[$i]) > ord('9')) {
                return false;
            }
        }
        return true;
    }
    //----------------------------------------------------------------------
    public static function estimate_bits_mode_num($size)
    {
        $w = (int) $size / 3;
        $bits = $w * 10;
        switch ($size - $w * 3) {
            case 1:
                $bits += 4;
                break;
            case 2:
                $bits += 7;
                break;
            default:
                break;
        }
        return $bits;
    }
    //----------------------------------------------------------------------
    public static $an_table = [-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, 36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 44, -1, -1, -1, -1, -1, -1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1];
    //----------------------------------------------------------------------
    public static function look_an_table($c)
    {
        return $c > 127 ? -1 : self::$an_table[$c];
    }
    //----------------------------------------------------------------------
    public static function check_mode_an($size, $data)
    {
        for ($i = 0; $i < $size; $i++) {
            if (self::look_an_table(ord($data[$i])) == -1) {
                return false;
            }
        }
        return true;
    }
    //----------------------------------------------------------------------
    public static function estimate_bits_mode_an($size)
    {
        $w = (int) ($size / 2);
        $bits = $w * 11;
        if ($size & 1) {
            $bits += 6;
        }
        return $bits;
    }
    //----------------------------------------------------------------------
    public static function estimate_bits_mode8($size)
    {
        return $size * 8;
    }
    //----------------------------------------------------------------------
    public function estimate_bits_mode_kanji($size)
    {
        return (int) ($size / 2 * 13);
    }
    //----------------------------------------------------------------------
    public static function check_mode_kanji($size, $data)
    {
        if ($size & 1) {
            return false;
        }
        for ($i = 0; $i < $size; $i += 2) {
            $val = ord($data[$i]) << 8 | ord($data[$i + 1]);
            if ($val < 0x8140 || $val > 0x9ffc && $val < 0xe040 || $val > 0xebbf) {
                return false;
            }
        }
        return true;
    }
    /***********************************************************************
     * Validation
     **********************************************************************/
    public static function check($mode, $size, $data)
    {
        if ($size <= 0) {
            return false;
        }
        switch ($mode) {
            case QR_MODE_NUM:
                return self::check_mode_num($size, $data);
                break;
            case QR_MODE_AN:
                return self::check_mode_an($size, $data);
                break;
            case QR_MODE_KANJI:
                return self::check_mode_kanji($size, $data);
                break;
            case QR_MODE_8:
                return true;
                break;
            case QR_MODE_STRUCTURE:
                return true;
                break;
            default:
                break;
        }
        return false;
    }
    //----------------------------------------------------------------------
    public function estimate_bit_stream_size($version)
    {
        $bits = 0;
        foreach ($this->items as $item) {
            $bits += $item->estimate_bit_stream_size_of_entry($version);
        }
        return $bits;
    }
    //----------------------------------------------------------------------
    public function estimate_version()
    {
        $version = 0;
        $prev = 0;
        do {
            $prev = $version;
            $bits = $this->estimate_bit_stream_size($prev);
            $version = Q_Rspec::get_minimum_version((int) (($bits + 7) / 8), $this->level);
            if ($version < 0) {
                return -1;
            }
        } while ($version > $prev);
        return $version;
    }
    //----------------------------------------------------------------------
    public static function length_of_code($mode, $version, $bits)
    {
        $payload = $bits - 4 - Q_Rspec::length_indicator($mode, $version);
        switch ($mode) {
            case QR_MODE_NUM:
                $chunks = (int) ($payload / 10);
                $remain = $payload - $chunks * 10;
                $size = $chunks * 3;
                if ($remain >= 7) {
                    $size += 2;
                } elseif ($remain >= 4) {
                    $size += 1;
                }
                break;
            case QR_MODE_AN:
                $chunks = (int) ($payload / 11);
                $remain = $payload - $chunks * 11;
                $size = $chunks * 2;
                if ($remain >= 6) {
                    $size++;
                }
                break;
            case QR_MODE_8:
                $size = (int) ($payload / 8);
                break;
            case QR_MODE_KANJI:
                $size = (int) ($payload / 13 * 2);
                break;
            case QR_MODE_STRUCTURE:
                $size = (int) ($payload / 8);
                break;
            default:
                $size = 0;
                break;
        }
        $maxsize = Q_Rspec::maximum_words($mode, $version);
        if ($size < 0) {
            $size = 0;
        }
        if ($size > $maxsize) {
            $size = $maxsize;
        }
        return $size;
    }
    //----------------------------------------------------------------------
    public function create_bit_stream()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $bits = $item->encode_bit_stream($this->version);
            if ($bits < 0) {
                return -1;
            }
            $total += $bits;
        }
        return $total;
    }
    //----------------------------------------------------------------------
    public function convert_data()
    {
        $ver = $this->estimate_version();
        if ($ver > $this->get_version()) {
            $this->set_version($ver);
        }
        for (;;) {
            $bits = $this->create_bit_stream();
            if ($bits < 0) {
                return -1;
            }
            $ver = Q_Rspec::get_minimum_version((int) (($bits + 7) / 8), $this->level);
            if ($ver < 0) {
                throw new \Exception('WRONG VERSION');
                return -1;
            } elseif ($ver > $this->get_version()) {
                $this->set_version($ver);
            } else {
                break;
            }
        }
        return 0;
    }
    //----------------------------------------------------------------------
    public function append_padding_bit(&$bstream)
    {
        $bits = $bstream->size();
        $maxwords = Q_Rspec::get_data_length($this->version, $this->level);
        $maxbits = $maxwords * 8;
        if ($maxbits == $bits) {
            return 0;
        }
        if ($maxbits - $bits < 5) {
            return $bstream->append_num($maxbits - $bits, 0);
        }
        $bits += 4;
        $words = (int) (($bits + 7) / 8);
        $padding = new Q_Rbitstream();
        $ret = $padding->append_num($words * 8 - $bits + 4, 0);
        if ($ret < 0) {
            return $ret;
        }
        $padlen = $maxwords - $words;
        if ($padlen > 0) {
            $padbuf = [];
            for ($i = 0; $i < $padlen; $i++) {
                $padbuf[$i] = $i & 1 ? 0x11 : 0xec;
            }
            $ret = $padding->append_bytes($padlen, $padbuf);
            if ($ret < 0) {
                return $ret;
            }
        }
        $ret = $bstream->append($padding);
        return $ret;
    }
    //----------------------------------------------------------------------
    public function merge_bit_stream()
    {
        if ($this->convert_data() < 0) {
            return null;
        }
        $bstream = new Q_Rbitstream();
        foreach ($this->items as $item) {
            $ret = $bstream->append($item->bstream);
            if ($ret < 0) {
                return null;
            }
        }
        return $bstream;
    }
    //----------------------------------------------------------------------
    public function get_bit_stream()
    {
        $bstream = $this->merge_bit_stream();
        if ($bstream == null) {
            return null;
        }
        $ret = $this->append_padding_bit($bstream);
        if ($ret < 0) {
            return null;
        }
        return $bstream;
    }
    //----------------------------------------------------------------------
    public function get_byte_stream()
    {
        $bstream = $this->get_bit_stream();
        if ($bstream == null) {
            return null;
        }
        return $bstream->to_byte();
    }
}