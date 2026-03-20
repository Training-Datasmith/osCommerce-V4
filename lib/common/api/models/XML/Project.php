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
namespace common\api\models\XML;

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
    public function __construct($file_name)
    {
        $this->file_name = $file_name;
        $this->Serializer = new Related_Serialize();
        static::check_local_projects();
    }
    public static function check_local_projects()
    {
        $check_primary_project = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM io_project WHERE is_local=1 AND department_id=0 AND platform_id=0'));
        if ($check_primary_project['c'] == 0) {
            $project_code = static::allocate_code(defined('STORE_NAME') ? STORE_NAME : \Yii::$app->name);
            static::create_project($project_code, ['is_local' => 1, 'department_id' => 0, 'platform_id' => 0]);
        }
    }
    public static function allocate_code($prefix)
    {
        do {
            $get_server_uuid = tep_db_fetch_array(tep_db_query('SELECT HEX(UUID_SHORT()) AS short_u'));
            $prefix_t = substr(strtoupper(preg_replace('/[^\da-z]/i', '', $prefix)), 0, 11) . '_' . $get_server_uuid['short_u'] . date('ymd');
            $check_uniq = tep_db_fetch_array(tep_db_query("SELECT COUNT(*) AS c FROM io_project WHERE project_code='" . tep_db_input($prefix_t) . "'"));
        } while ($check_uniq['c'] > 0);
        return $prefix_t;
    }
    public static function create_project($project_code, $extra_data)
    {
        $data = ['project_code' => $project_code];
        if (is_array($extra_data)) {
            $data = array_merge($data, $extra_data);
        }
        tep_db_perform('io_project', $data);
        return tep_db_insert_id();
    }
    public function set_structure($structure)
    {
        $this->structure = $structure;
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
        $this->Serializer->import($this->file_name);
    }
}