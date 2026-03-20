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
namespace Osc_Link\XML;

class Xml_Writer
{
    protected $first_output = true;
    protected $file_name = '';
    protected $file_handle;
    protected $root_tag = '';
    protected $xslt;
    /**
     * IOProject constructor.
     * @param string $fileName
     */
    public function __construct($file_name)
    {
        $this->file_name = $file_name;
    }
    public function apply_xslt($filename)
    {
        $this->xslt = new \Xslt_Processor();
        $this->xslt->import_stylesheet(simplexml_load_file($filename));
    }
    public function export_begin($header)
    {
        $this->file_handle = fopen($this->file_name, 'w+');
        fwrite($this->file_handle, '<?xml version="1.0" encoding="UTF-8"?' . '>' . "\n");
        fwrite($this->file_handle, '<data>' . "\n");
        if (is_string($header) && !empty($header)) {
            $header = ['type' => $header, 'projectCode' => Io_Core::get()->get_project_code()];
        }
        if (is_array($header) && count($header) > 0) {
            $header['projectCode'] = Io_Core::get()->get_project_code();
            $xml_header = $this->serialize_to_xml(Io_Data::from_array($header), 'Header');
            fwrite($this->file_handle, $xml_header);
        }
    }
    public function export_data($data)
    {
        $record_tag = '';
        if (isset($data->meta['xmlCollection'])) {
            $root_tag = '';
            if (strpos($data->meta['xmlCollection'], '>') !== false) {
                list($root_tag, $record_tag) = explode('>', $data->meta['xmlCollection'], 2);
            } else {
                $record_tag = $data->meta['xmlCollection'];
            }
            if ($this->first_output) {
                $this->root_tag = $root_tag;
                fwrite($this->file_handle, "<{$this->root_tag}>" . "\n");
            }
        }
        if ($record_tag) {
            $xml = $this->serialize_to_xml($data, $record_tag);
            fwrite($this->file_handle, $xml);
        }
        $this->first_output = false;
    }
    protected function serialize_to_xml(Io_Data $data, $record_tag, $root_element = null)
    {
        $element = Io_Data::serialize_to_simple_xml($data, $record_tag, $root_element);
        $xml = '';
        if (is_object($element)) {
            if ($this->xslt) {
                if (true) {
                    $xml = $this->xslt->transform_to_xml($element);
                } else {
                    $doc = $this->xslt->transform_to_doc($element);
                    //echo '<pre>'; var_dump(json_encode(simplexml_import_dom($doc))); echo '</pre>';
                    $xml = $doc->save_xml($doc);
                }
            } else {
                $xml = $element->as_xml();
            }
            $head_pos = strpos($xml, "?>\n");
            if ($head_pos !== false) {
                $xml = substr($xml, $head_pos + 3);
            } else {
                $head_pos = strpos($xml, '?>');
                if ($head_pos !== false) {
                    $xml = substr($xml, $head_pos + 2);
                }
            }
        }
        return $xml;
    }
    public function export_end()
    {
        if (!empty($this->root_tag)) {
            fwrite($this->file_handle, "</{$this->root_tag}>" . "\n");
        }
        fwrite($this->file_handle, '</data>' . "\n");
        fclose($this->file_handle);
        if (is_file($this->file_name)) {
            @chmod($this->file_name, 0666);
        }
    }
}