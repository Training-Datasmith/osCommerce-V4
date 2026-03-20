<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//##########################################################################
class Q_Rcode
{
    public $version;
    public $width;
    public $data;
    //----------------------------------------------------------------------
    public function encode_mask(Q_Rinput $input, $mask)
    {
        if ($input->get_version() < 0 || $input->get_version() > QRSPEC_VERSION_MAX) {
            throw new \Exception('wrong version');
        }
        if ($input->get_error_correction_level() > QR_ECLEVEL_H) {
            throw new \Exception('wrong level');
        }
        $raw = new Q_Rrawcode($input);
        Q_Rtools::mark_time('after_raw');
        $version = $raw->version;
        $width = Q_Rspec::get_width($version);
        $frame = Q_Rspec::new_frame($version);
        $filler = new Frame_Filler($width, $frame);
        if (is_null($filler)) {
            return null;
        }
        // inteleaved data and ecc codes
        for ($i = 0; $i < $raw->data_length + $raw->ecc_length; $i++) {
            $code = $raw->get_code();
            $bit = 0x80;
            for ($j = 0; $j < 8; $j++) {
                $addr = $filler->next();
                $filler->set_frame_at($addr, 0x2 | ($bit & $code) != 0);
                $bit = $bit >> 1;
            }
        }
        Q_Rtools::mark_time('after_filler');
        unset($raw);
        // remainder bits
        $j = Q_Rspec::get_remainder($version);
        for ($i = 0; $i < $j; $i++) {
            $addr = $filler->next();
            $filler->set_frame_at($addr, 0x2);
        }
        $frame = $filler->frame;
        unset($filler);
        // masking
        $mask_obj = new Q_Rmask();
        if ($mask < 0) {
            if (QR_FIND_BEST_MASK) {
                $masked = $mask_obj->mask($width, $frame, $input->get_error_correction_level());
            } else {
                $masked = $mask_obj->make_mask($width, $frame, intval(QR_DEFAULT_MASK) % 8, $input->get_error_correction_level());
            }
        } else {
            $masked = $mask_obj->make_mask($width, $frame, $mask, $input->get_error_correction_level());
        }
        if ($masked == null) {
            return null;
        }
        Q_Rtools::mark_time('after_mask');
        $this->version = $version;
        $this->width = $width;
        $this->data = $masked;
        return $this;
    }
    //----------------------------------------------------------------------
    public function encode_input(Q_Rinput $input)
    {
        return $this->encode_mask($input, -1);
    }
    //----------------------------------------------------------------------
    public function encode_string8bit($string, $version, $level)
    {
        if (string == null) {
            throw new \Exception('empty string!');
            return null;
        }
        $input = new Q_Rinput($version, $level);
        if ($input == null) {
            return null;
        }
        $ret = $input->append($input, QR_MODE_8, strlen($string), str_split($string));
        if ($ret < 0) {
            unset($input);
            return null;
        }
        return $this->encode_input($input);
    }
    //----------------------------------------------------------------------
    public function encode_string($string, $version, $level, $hint, $casesensitive)
    {
        if ($hint != QR_MODE_8 && $hint != QR_MODE_KANJI) {
            throw new \Exception('bad hint');
            return null;
        }
        $input = new Q_Rinput($version, $level);
        if ($input == null) {
            return null;
        }
        $ret = Q_Rsplit::split_string_to_q_rinput($string, $input, $hint, $casesensitive);
        if ($ret < 0) {
            return null;
        }
        return $this->encode_input($input);
    }
    //----------------------------------------------------------------------
    public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint = false)
    {
        $enc = Q_Rencode::factory($level, $size, $margin);
        return $enc->encode_png($text, $outfile, $saveandprint = false);
    }
    //----------------------------------------------------------------------
    public static function text($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = Q_Rencode::factory($level, $size, $margin);
        return $enc->encode($text, $outfile);
    }
    //----------------------------------------------------------------------
    public static function raw($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = Q_Rencode::factory($level, $size, $margin);
        return $enc->encode_raw($text, $outfile);
    }
}