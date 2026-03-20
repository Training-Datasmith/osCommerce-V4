<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//##########################################################################
class Q_Rencode
{
    public $casesensitive = true;
    public $eightbit = false;
    public $version = 0;
    public $size = 3;
    public $margin = 4;
    public $structured = 0;
    // not supported yet
    public $level = QR_ECLEVEL_L;
    public $hint = QR_MODE_8;
    //----------------------------------------------------------------------
    public static function factory($level = QR_ECLEVEL_L, $size = 3, $margin = 4)
    {
        $enc = new Q_Rencode();
        $enc->size = $size;
        $enc->margin = $margin;
        switch ($level . '') {
            case '0':
            case '1':
            case '2':
            case '3':
                $enc->level = $level;
                break;
            case 'l':
            case 'L':
                $enc->level = QR_ECLEVEL_L;
                break;
            case 'm':
            case 'M':
                $enc->level = QR_ECLEVEL_M;
                break;
            case 'q':
            case 'Q':
                $enc->level = QR_ECLEVEL_Q;
                break;
            case 'h':
            case 'H':
                $enc->level = QR_ECLEVEL_H;
                break;
        }
        return $enc;
    }
    //----------------------------------------------------------------------
    public function encode_raw($intext, $outfile = false)
    {
        $code = new Q_Rcode();
        if ($this->eightbit) {
            $code->encode_string8bit($intext, $this->version, $this->level);
        } else {
            $code->encode_string($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }
        return $code->data;
    }
    //----------------------------------------------------------------------
    public function encode($intext, $outfile = false)
    {
        $code = new Q_Rcode();
        if ($this->eightbit) {
            $code->encode_string8bit($intext, $this->version, $this->level);
        } else {
            $code->encode_string($intext, $this->version, $this->level, $this->hint, $this->casesensitive);
        }
        Q_Rtools::mark_time('after_encode');
        if ($outfile !== false) {
            file_put_contents($outfile, join("\n", Q_Rtools::binarize($code->data)));
        } else {
            return Q_Rtools::binarize($code->data);
        }
    }
    //----------------------------------------------------------------------
    public function encode_png($intext, $outfile = false, $saveandprint = false)
    {
        try {
            ob_start();
            $tab = $this->encode($intext);
            $err = ob_get_contents();
            ob_end_clean();
            if ($err != '') {
                Q_Rtools::log($outfile, $err);
            }
            $max_size = (int) (QR_PNG_MAXIMUM_SIZE / (count($tab) + 2 * $this->margin));
            Q_Rimage::png($tab, $outfile, min(max(1, $this->size), $max_size), $this->margin, $saveandprint);
        } catch (\Exception $e) {
            var_dump($e->get_message());
            Q_Rtools::log($outfile, $e->get_message());
        }
    }
}