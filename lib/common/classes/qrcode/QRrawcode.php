<?php

declare (strict_types=1);
namespace common\classes\qrcode;

require_once 'init.php';
//##########################################################################
class Q_Rrawcode
{
    public $version;
    public $datacode = [];
    public $ecccode = [];
    public $blocks;
    public $rsblocks = [];
    //of RSblock
    public $count;
    public $data_length;
    public $ecc_length;
    public $b1;
    //----------------------------------------------------------------------
    public function __construct(Q_Rinput $input)
    {
        $spec = [0, 0, 0, 0, 0];
        $this->datacode = $input->get_byte_stream();
        if (is_null($this->datacode)) {
            throw new \Exception('null imput string');
        }
        Q_Rspec::get_ecc_spec($input->get_version(), $input->get_error_correction_level(), $spec);
        $this->version = $input->get_version();
        $this->b1 = Q_Rspec::rs_block_num1($spec);
        $this->data_length = Q_Rspec::rs_data_length($spec);
        $this->ecc_length = Q_Rspec::rs_ecc_length($spec);
        $this->ecccode = array_fill(0, $this->ecc_length, 0);
        $this->blocks = Q_Rspec::rs_block_num($spec);
        $ret = $this->init($spec);
        if ($ret < 0) {
            throw new \Exception('block alloc error');
            return null;
        }
        $this->count = 0;
    }
    //----------------------------------------------------------------------
    public function init(array $spec)
    {
        $dl = Q_Rspec::rs_data_codes1($spec);
        $el = Q_Rspec::rs_ecc_codes1($spec);
        $rs = Q_Rrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);
        $block_no = 0;
        $data_pos = 0;
        $ecc_pos = 0;
        for ($i = 0; $i < Q_Rspec::rs_block_num1($spec); $i++) {
            $ecc = array_slice($this->ecccode, $ecc_pos);
            $this->rsblocks[$block_no] = new Q_Rrsblock($dl, array_slice($this->datacode, $data_pos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $ecc_pos), $ecc);
            $data_pos += $dl;
            $ecc_pos += $el;
            $block_no++;
        }
        if (Q_Rspec::rs_block_num2($spec) == 0) {
            return 0;
        }
        $dl = Q_Rspec::rs_data_codes2($spec);
        $el = Q_Rspec::rs_ecc_codes2($spec);
        $rs = Q_Rrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);
        if ($rs == null) {
            return -1;
        }
        for ($i = 0; $i < Q_Rspec::rs_block_num2($spec); $i++) {
            $ecc = array_slice($this->ecccode, $ecc_pos);
            $this->rsblocks[$block_no] = new Q_Rrsblock($dl, array_slice($this->datacode, $data_pos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode, 0, $ecc_pos), $ecc);
            $data_pos += $dl;
            $ecc_pos += $el;
            $block_no++;
        }
        return 0;
    }
    //----------------------------------------------------------------------
    public function get_code()
    {
        $ret;
        if ($this->count < $this->data_length) {
            $row = $this->count % $this->blocks;
            $col = $this->count / $this->blocks;
            if ($col >= $this->rsblocks[0]->data_length) {
                $row += $this->b1;
            }
            $ret = $this->rsblocks[$row]->data[$col];
        } elseif ($this->count < $this->data_length + $this->ecc_length) {
            $row = ($this->count - $this->data_length) % $this->blocks;
            $col = ($this->count - $this->data_length) / $this->blocks;
            $ret = $this->rsblocks[$row]->ecc[$col];
        } else {
            return 0;
        }
        $this->count++;
        return $ret;
    }
}