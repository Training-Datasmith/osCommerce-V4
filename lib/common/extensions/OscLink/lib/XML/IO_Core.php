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

use common\helpers\Assert;
use yii\base\Invalid_Param_Exception;
use yii\helpers\File_Helper;
class Io_Core
{
    public $project_id;
    protected $project_data;
    private $attribute_mapper;
    private $type_class_map = [];
    private $locations = [];
    private $attachment_modes = [];
    private $tablenames_with_mirror_ids = [];
    private function __construct()
    {
        $this->attribute_mapper = new Attribute_Mapper();
        $project_id = 1;
        $this->set_project_id($project_id);
        $this->type_class_map = ['IOMap' => '\OscLink\XML\IOMap', 'IOCurrencyMap' => '\OscLink\XML\IOCurrencyMap', 'IOLanguageMap' => '\OscLink\XML\IOLanguageMap', 'IOPK' => '\OscLink\XML\IOPK', 'IOPlatformMap' => '\OscLink\XML\IOPlatformMap', 'IOAttachment' => '\OscLink\XML\IOAttachment', 'IOGalleryAttachment' => '\OscLink\XML\IOGalleryAttachment', 'IOCountryMap' => '\OscLink\XML\IOCountryMap', 'IOCountryZoneMap' => '\OscLink\XML\IOCountryZoneMap', 'IOOrderStatus' => '\OscLink\XML\IOOrderStatus'];
        if (class_exists('\Yii')) {
            foreach ($this->type_class_map as $short_name => $full_name) {
                \Yii::$container->set($short_name, $full_name);
            }
        }
        $this->append_location('@home', DIR_FS_CATALOG, \Yii::$app->get('platform')->config()->get_catalog_base_url());
        $this->append_location('@images', '@home/images');
        $this->append_location('@documents', '@home/documents');
        $this->append_location('@documents', '@home/documents');
    }
    public function set_project_id($project_id)
    {
        //        $this->project_id = $projectId;
        //        $getProjectCode_r = tep_db_query("SELECT * FROM io_project WHERE project_id='".intval($this->project_id)."'");
        //        if ( tep_db_num_rows($getProjectCode_r)>0 ) {
        //            $this->project_data = tep_db_fetch_array($getProjectCode_r);
        //        }
        //        $this->attributeMapper->setProjectId($this->project_id);
    }
    //    public function setProjectByCode($projectCode)
    //    {
    //        $getProjectId_r = tep_db_query("SELECT project_id FROM io_project WHERE project_code='".tep_db_input($projectCode)."'");
    //        if ( tep_db_num_rows($getProjectId_r)>0 ) {
    //            $projectIdArr = tep_db_fetch_array($getProjectId_r);
    //            $this->setProjectId((int)$projectIdArr['project_id']);
    //        }
    //    }
    public function is_local_project()
    {
        //        if (is_array($this->project_data) ){
        //            return !!$this->project_data['is_local'];
        //        }
        return false;
    }
    //    public function getProjectCode()
    //    {
    //        if (is_array($this->project_data) ){
    //            return $this->project_data['project_code'];
    //        }
    //        return '';
    //    }
    public static function get()
    {
        static $instance;
        if (!is_object($instance)) {
            $instance = new self();
        }
        return $instance;
    }
    public function get_lookup_tool()
    {
        static $obj_lookup = false;
        if (!is_object($obj_lookup)) {
            $obj_lookup = new Io_Lookup();
        }
        return $obj_lookup;
    }
    //    public function getProjectList()
    //    {
    //        $projectList = [];
    //        $getProjectId_r = tep_db_query("SELECT project_id, project_code FROM io_project WHERE 1 ORDER BY project_id");
    //        if ( tep_db_num_rows($getProjectId_r)>0 ) {
    //            while ($projectIdArr = tep_db_fetch_array($getProjectId_r)){
    //                $projectList[ $projectIdArr['project_id'] ] = $projectIdArr['project_code'];
    //            }
    //        }
    //        return $projectList;
    //    }
    /**
     * @return AttributeMapper
     */
    public function get_attribute_mapper()
    {
        return $this->attribute_mapper;
    }
    public static function create_object($type, array $params = [])
    {
        $obj = self::get();
        if (class_exists('\Yii')) {
            return \Yii::create_object($type, $params);
        } else if (isset($obj->type_class_map[$type])) {
            $class_name = $obj->type_class_map[$type];
            $object = new $class_name();
            foreach ($params as $name => $value) {
                $object->{$name} = $value;
            }
            return $object;
        }
        return false;
    }
    public static function construct_object_instance($object_array, $params)
    {
        if (\Yii::$container->has($object_array[0])) {
            $Definitions = \Yii::$container->get_definitions();
            $full_class_name = $Definitions[$object_array[0]]['class'];
            return call_user_func_array([$full_class_name, $object_array[1]], $params);
        }
        return $params;
    }
    public static function get_export_structure($structure)
    {
        $config_fn = dirname(__FILE__) . '/structure/' . $structure . '.php';
        \common\helpers\Assert::assert(file_exists($config_fn), 'Config file is not found: ' . $config_fn);
        $config = include $config_fn;
        $config['XSL'] = ['export' => false, 'import' => false];
        $transform_xsl = dirname(__FILE__) . '/transform/export/' . $structure . '.xsl';
        if (is_file($transform_xsl)) {
            $config['XSL']['export'] = $transform_xsl;
        }
        $transform_xsl = dirname(__FILE__) . '/transform/import/' . $structure . '.xsl';
        if (is_file($transform_xsl)) {
            $config['XSL']['import'] = $transform_xsl;
        }
        return $config;
    }
    public function append_location($alias, $file_system_path, $url_path = '')
    {
        $this->locations[$alias] = ['local' => rtrim($file_system_path, '/'), 'public' => rtrim(empty($url_path) ? $file_system_path : $url_path, '/')];
    }
    public function get_local_location($path)
    {
        return $this->compute_location_value($path, 'local');
    }
    public function get_public_location($path)
    {
        return $this->compute_location_value($path, 'public');
    }
    protected function compute_location_value($path, $target)
    {
        if (substr($path, 0, 1) == '@') {
            $pos = strpos($path, '/');
            $root = $pos === false ? $path : substr($path, 0, $pos);
            if (isset($this->locations[$root][$target])) {
                return $this->compute_location_value($pos === false ? $this->locations[$root][$target] : $this->locations[$root][$target] . substr($path, $pos), $target);
            } elseif (class_exists('\Yii')) {
                return \Yii::get_alias($path, false);
            }
        }
        return $path;
    }
    /**
     * @return array
     */
    public function get_attachment_modes()
    {
        return array_values($this->attachment_modes);
    }
    public function is_attachment_mode_present($check_mode)
    {
        return isset($this->attachment_modes[$check_mode]);
    }
    /**
     * @param array|string $attachmentModes
     */
    public function set_attachment_mode($attachment_modes)
    {
        if (!is_array($attachment_modes)) {
            $attachment_modes = [$attachment_modes];
        }
        $io_attachment = static::create_object('IOAttachment');
        /**
         * @var $IOAttachment IOAttachment
         */
        $known_attachment_modes = $io_attachment->get_attachment_mode_variants();
        $unknown = array_diff($attachment_modes, $known_attachment_modes);
        if (count($unknown) > 0) {
            throw new Invalid_Param_Exception('Wrong mode "' . implode('", "', $unknown) . '" Possible values for AttachmentModes is [' . implode(', ', $known_attachment_modes) . ']');
        }
        $this->attachment_modes = [];
        foreach ($attachment_modes as $attachment_mode) {
            $this->attachment_modes[$attachment_mode] = $attachment_mode;
        }
    }
    public function normalize_local_file_name($fn)
    {
        return str_replace(' ', '_', $fn);
    }
    public function download($source_file, &$physical_file, $prefix = '')
    {
        $prefix = empty($prefix) ? '' : $prefix . ': ';
        $dir = dirname($physical_file);
        try {
            if (!is_dir($dir)) {
                File_Helper::create_directory($dir, 0777);
            }
            Assert::assert_not_empty($source_file, 'Source file is empty');
            Assert::assert_not_empty($physical_file, 'Destination file is empty');
            $source_file = str_replace(' ', '%20', $source_file);
            $physical_file = $this->normalize_local_file_name($physical_file);
            $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            Assert::assert(@copy($source_file, $physical_file, $context), "Could not download file '{$source_file}': " . (error_get_last()['message'] ?? 'unknown reason'));
            if (!@chmod($physical_file, 0666)) {
                \Osc_Link\Logger::print($prefix . 'warning: failed chmod: ' . (error_get_last()['message'] ?? 'unknown reason'));
            }
            return true;
        } catch (\Exception $e) {
            \Osc_Link\Logger::print($prefix . $e->get_message());
            return false;
        }
    }
    /**
     * @param $tablenames string|array like 'products,orders' or ['products,orders']
     * @return void
     */
    public function set_tablenames_with_mirror_ids($tablenames)
    {
        if (is_string($tablenames)) {
            \common\helpers\Assert::assert(strpos($tablenames, ' ') === false, 'Spaces are not allowed');
            $this->tablenames_with_mirror_ids = explode(',', $tablenames);
        } elseif (is_array($tablenames)) {
            $this->tablenames_with_mirror_ids = $tablenames;
        }
    }
    /**
     * @return array
     */
    public function get_tablenames_with_mirror_ids()
    {
        return $this->tablenames_with_mirror_ids;
    }
}