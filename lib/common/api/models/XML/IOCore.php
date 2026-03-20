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

use yii\base\Invalid_Param_Exception;
class Io_Core
{
    public $project_id;
    protected $project_data;
    private $attribute_mapper;
    private $type_class_map = [];
    private $locations = [];
    private $attachment_modes = [];
    private function __construct()
    {
        $this->attribute_mapper = new Attribute_Mapper();
        $project_id = \common\models\Io_Project::find()->one()->project_id ?? null;
        if (is_null($project_id)) {
            // specially for somebody who runs migration SQL mannually, but misses init function)
            echo 'Migration io_init was not performed correctly. Please apply it.';
            die;
        }
        $this->set_project_id($project_id);
        $this->type_class_map = ['IOMap' => '\common\api\models\XML\IOMap', 'IOCurrencyMap' => '\common\api\models\XML\IOCurrencyMap', 'IOLanguageMap' => '\common\api\models\XML\IOLanguageMap', 'IOPK' => '\common\api\models\XML\IOPK', 'IOPlatformMap' => '\common\api\models\XML\IOPlatformMap', 'IOAttachment' => '\common\api\models\XML\IOAttachment', 'IOGalleryAttachment' => '\common\api\models\XML\IOGalleryAttachment', 'IOCountryMap' => '\common\api\models\XML\IOCountryMap', 'IOCountryZoneMap' => '\common\api\models\XML\IOCountryZoneMap', 'IOOrderStatus' => '\common\api\models\XML\IOOrderStatus'];
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
        $this->project_id = $project_id;
        $get_project_code_r = tep_db_query("SELECT * FROM io_project WHERE project_id='" . intval($this->project_id) . "'");
        if (tep_db_num_rows($get_project_code_r) > 0) {
            $this->project_data = tep_db_fetch_array($get_project_code_r);
        }
        $this->attribute_mapper->set_project_id($this->project_id);
    }
    public function set_project_by_code($project_code)
    {
        $get_project_id_r = tep_db_query("SELECT project_id FROM io_project WHERE project_code='" . tep_db_input($project_code) . "'");
        if (tep_db_num_rows($get_project_id_r) > 0) {
            $project_id_arr = tep_db_fetch_array($get_project_id_r);
            $this->set_project_id((int) $project_id_arr['project_id']);
        }
    }
    public function is_local_project()
    {
        if (is_array($this->project_data)) {
            return !!$this->project_data['is_local'];
        }
        return false;
    }
    public function get_project_code()
    {
        if (is_array($this->project_data)) {
            return $this->project_data['project_code'];
        }
        return '';
    }
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
    public function get_project_list()
    {
        $project_list = [];
        $get_project_id_r = tep_db_query('SELECT project_id, project_code FROM io_project WHERE 1 ORDER BY project_id');
        if (tep_db_num_rows($get_project_id_r) > 0) {
            while ($project_id_arr = tep_db_fetch_array($get_project_id_r)) {
                $project_list[$project_id_arr['project_id']] = $project_id_arr['project_code'];
            }
        }
        return $project_list;
    }
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
        if (is_file(dirname(__FILE__) . '/structure/' . $structure . '.php')) {
            $config = include dirname(__FILE__) . '/structure/' . $structure . '.php';
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
        return [];
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
}