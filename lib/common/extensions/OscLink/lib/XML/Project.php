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

class Project
{
    protected $structure;
    /**
     * @var RelatedSerialize
     */
    protected $Serializer;
    /**
     * IOProject constructor.
     * @param string $fileName
     */
    public function __construct($file_name = null)
    {
        $this->file_name = $file_name;
        $this->Serializer = new Related_Serialize();
        static::check_local_projects();
    }
    public static function check_local_projects()
    {
    }
    public static function allocate_code($prefix)
    {
        return '1';
    }
    public static function create_project($project_code, $extra_data)
    {
        return 1;
    }
    public function set_structure($structure, $tuning)
    {
        $this->structure = $structure;
        $this->structure['importTuning'] = $tuning;
        $this->Serializer->set_configure_map($this->structure);
    }
    public function detect_structure()
    {
        $detected_structure = false;
        $xml_parser = new Xm_Lto_Array_Parser();
        $xml_parser->parse_file($this->file_name);
        $xml_parser->set_collect_path('/data/Header');
        $xml_header = $xml_parser->read();
        if (is_array($xml_header) && !empty($xml_header['type'])) {
            foreach (glob(dirname(__FILE__) . '/structure/*.php') as $structure_file) {
                $test_array = include $structure_file;
                if (is_array($test_array) && isset($test_array['Header'])) {
                    $check_header = $test_array['Header'];
                    if (!is_array($check_header)) {
                        $check_header = ['type' => $check_header];
                    }
                    if ($check_header['type'] == $xml_header['type']) {
                        $detected_structure = pathinfo($structure_file, PATHINFO_FILENAME);
                        break;
                    }
                }
            }
        }
        return $detected_structure;
    }
    public function export()
    {
        $writer = new Xml_Writer($this->file_name);
        if (isset($this->structure['XSL']) && is_array($this->structure['XSL']) && !empty($this->structure['XSL']['export'])) {
            if (is_file($this->structure['XSL']['export'])) {
                $writer->apply_xslt($this->structure['XSL']['export']);
            }
        }
        $this->Serializer->export($writer);
    }
    public function import()
    {
        return $this->Serializer->import($this->file_name);
    }
    public function clean()
    {
        return $this->Serializer->clean();
    }
}