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
namespace backend\models\EP;

use Yii;
use yii\base\Base_Object;
use yii\helpers\File_Helper;
class Directory extends Base_Object
{
    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';
    public const TYPE_IMAGES = 'images';
    public const TYPE_DATASOURCE = 'datasource';
    public const TYPE_PROCESSED = 'processed';
    public $directory_id;
    public $parent_id;
    public $removable;
    public $cron_enabled;
    public $directory_type = 'import';
    public $name;
    public $directory;
    public $directory_config;
    protected $parent_directory;
    public function __construct($config)
    {
        parent::__construct($config);
        $language_id = isset($_SESSION['language_id']) ? $_SESSION['language_id'] : \common\classes\language::default_id();
        $name_value = \common\helpers\Translation::get_translation_value($this->name, 'admin/easypopulate', $language_id);
        $this->name = $name_value;
        if (is_string($this->directory_config)) {
            $this->directory_config = json_decode($this->directory_config, true);
        }
        if (!is_array($this->directory_config)) {
            $this->directory_config = [];
        }
    }
    public function can_configure()
    {
        return $this->cron_enabled && in_array($this->directory_type, [self::TYPE_IMPORT, self::TYPE_PROCESSED, self::TYPE_DATASOURCE]);
    }
    public function can_configure_datasource()
    {
        return $this->cron_enabled && in_array($this->directory_type, [self::TYPE_DATASOURCE]);
    }
    public function can_remove()
    {
        return $this->parent_id != 0 && $this->removable && !empty($this->directory_id) && !in_array($this->directory_type, [self::TYPE_IMAGES, self::TYPE_PROCESSED]);
    }
    public function files_root($type = '')
    {
        if (empty($type)) {
            $type = $this->directory_type;
        }
        $files_root = '';
        $global_root = Yii::get_alias('@ep_files/');
        if ($this->parent_id) {
            $global_root = self::load_by_id($this->parent_id)->files_root($this->directory_type);
        }
        if ($type == self::TYPE_PROCESSED) {
            if ($this->directory_type == self::TYPE_PROCESSED) {
                $files_root = $global_root;
            } else {
                $files_root = $global_root . $this->directory . '/processed/';
            }
        } elseif ($type == self::TYPE_IMAGES) {
            $files_root = $global_root . $this->directory . '/images/';
        } else {
            $files_root = $global_root . $this->directory . '/';
        }
        if (!is_dir($files_root)) {
            try {
                \yii\helpers\File_Helper::create_directory($files_root, 0777, true);
            } catch (\Exception $ex) {
            }
        } else {
            @chmod($files_root, 0777);
        }
        return $files_root;
    }
    public static function load_by_id($id)
    {
        return new self(tep_db_fetch_array(tep_db_query('SELECT * FROM ' . TABLE_EP_DIRECTORIES . " WHERE directory_id='" . $id . "'")));
    }
    public function delete()
    {
        if ($this->parent_id == 5) {
            Data_Sources::remove($this->directory);
        }
        foreach ($this->get_subdirectories(true) as $directory) {
            $directory->delete();
        }
        $get_dir_job_r = tep_db_query('SELECT job_id FROM ' . TABLE_EP_JOB . " WHERE directory_id='" . (int) $this->directory_id . "'");
        if (tep_db_num_rows($get_dir_job_r) > 0) {
            while ($_job = tep_db_fetch_array($get_dir_job_r)) {
                $job = Job::load_by_id($_job['job_id']);
                $job->delete();
            }
        }
        try {
            File_Helper::remove_directory($this->files_root());
        } catch (\Exception $ex) {
        }
        tep_db_query('DELETE FROM ' . TABLE_EP_DIRECTORIES . " WHERE directory_id='" . $this->directory_id . "' AND removable=1");
        self::get_all(true);
        return true;
    }
    /**
     * @param bool $recursive
     * @return static[]
     */
    public function get_subdirectories($recursive = false)
    {
        $subdirectories = [];
        $get_db_link_r = tep_db_query('SELECT * ' . 'FROM ' . TABLE_EP_DIRECTORIES . ' ' . "WHERE parent_id='" . $this->directory_id . "'");
        if (tep_db_num_rows($get_db_link_r) > 0) {
            while ($dir_data = tep_db_fetch_array($get_db_link_r)) {
                $subdirectory = new self($dir_data);
                $subdirectories[] = $subdirectory;
                if ($recursive) {
                    $subdirs = $subdirectory->get_subdirectories($recursive);
                    if (is_array($subdirs) && count($subdirs) > 0) {
                        $subdirectories = array_merge($subdirectories, $subdirs);
                    }
                }
            }
        }
        return $subdirectories;
    }
    public function get_processed_directory()
    {
        foreach ($this->get_subdirectories() as $sub_dir) {
            if ($sub_dir->directory_type == self::TYPE_PROCESSED) {
                return $sub_dir;
            }
        }
        if ($this->cron_enabled) {
            $processed_root = $this->files_root(self::TYPE_PROCESSED);
            if (is_dir($processed_root)) {
                $this->synchronize_directories(true);
                foreach ($this->get_subdirectories() as $sub_dir) {
                    if ($sub_dir->directory_type == self::TYPE_PROCESSED) {
                        return $sub_dir;
                    }
                }
            }
        }
        return false;
    }
    public function get_parent()
    {
        if (is_null($this->parent_directory)) {
            $this->parent_directory = false;
        }
        if ($this->parent_id) {
            $this->parent_directory = self::find_by_id($this->parent_id);
        }
        return $this->parent_directory;
    }
    public function apply_directory_config()
    {
        if ($this->directory_type == self::TYPE_PROCESSED) {
            $this->clean_dir();
        } elseif ($this->directory_type == self::TYPE_DATASOURCE) {
            if ($this->cron_enabled) {
                foreach ($this->directory_config as $directory_config) {
                    if ($job = $this->find_job_by_filename($directory_config['filename_pattern'])) {
                        $update_array = [];
                        $update_array['run_frequency'] = $directory_config['run_frequency'];
                        $update_array['run_time'] = $directory_config['run_time'];
                        $update_array['job_provider'] = $directory_config['job_provider'];
                        tep_db_perform(TABLE_EP_JOB, $update_array, 'update', "job_id='" . $job->job_id . "'");
                    } else {
                        $data_array = ['directory_id' => $this->directory_id, 'direction' => $this->directory_type, 'file_name' => $directory_config['filename_pattern'], 'file_time' => 0, 'file_size' => 0, 'job_state' => 'configured', 'job_provider' => $directory_config['job_provider'], 'run_frequency' => $directory_config['run_frequency'], 'run_time' => $directory_config['run_time']];
                        tep_db_perform(TABLE_EP_JOB, $data_array);
                    }
                }
            }
            $get_db_files_r = tep_db_query('SELECT job_id, file_name ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' AND direction='" . $this->directory_type . "' ");
            if (tep_db_num_rows($get_db_files_r) > 0) {
                while ($db_file = tep_db_fetch_array($get_db_files_r)) {
                    if ($config = $this->find_config_by_file_name($db_file['file_name'])) {
                        $update_array = [];
                        $update_array['run_frequency'] = $config['run_frequency'];
                        $update_array['run_time'] = $config['run_time'];
                        $update_array['job_provider'] = $config['job_provider'];
                        tep_db_perform(TABLE_EP_JOB, $update_array, 'update', "job_id='" . $db_file['job_id'] . "'");
                    }
                }
            }
        } elseif ($this->directory_type == self::TYPE_IMPORT) {
            if ($this->cron_enabled) {
                foreach ($this->directory_config as $directory_config) {
                    if ($directory_config['file_format'] == 'BrightPearl') {
                        if ($job = $this->find_job_by_filename($directory_config['filename_pattern'])) {
                            $update_array = [];
                            $update_array['run_frequency'] = $directory_config['run_frequency'];
                            $update_array['run_time'] = $directory_config['run_time'];
                            $update_array['job_provider'] = $directory_config['job_provider'];
                            tep_db_perform(TABLE_EP_JOB, $update_array, 'update', "job_id='" . $job->job_id . "'");
                        } else {
                            $data_array = ['directory_id' => $this->directory_id, 'direction' => $this->directory_type, 'file_name' => $directory_config['filename_pattern'], 'file_time' => 0, 'file_size' => 0, 'job_state' => 'configured', 'job_provider' => $directory_config['job_provider'], 'run_frequency' => $directory_config['run_frequency'], 'run_time' => $directory_config['run_time']];
                            tep_db_perform(TABLE_EP_JOB, $data_array);
                        }
                    }
                }
            }
            $get_db_files_r = tep_db_query('SELECT job_id, file_name ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' AND direction='" . $this->directory_type . "' ");
            if (tep_db_num_rows($get_db_files_r) > 0) {
                while ($db_file = tep_db_fetch_array($get_db_files_r)) {
                    if ($config = $this->find_config_by_file_name($db_file['file_name'])) {
                        $update_array = [];
                        $update_array['run_frequency'] = $config['run_frequency'];
                        $update_array['run_time'] = $config['run_time'];
                        $update_array['job_provider'] = $config['job_provider'];
                        tep_db_perform(TABLE_EP_JOB, $update_array, 'update', "job_id='" . $db_file['job_id'] . "'");
                    }
                }
            }
        }
    }
    public function find_config_by_file_name($filename)
    {
        $config_data = false;
        foreach ($this->directory_config as $directory_job_config) {
            if (isset($directory_job_config['filename_pattern']) && fnmatch($directory_job_config['filename_pattern'], $filename, FNM_PERIOD)) {
                $config_data = $directory_job_config;
                break;
            }
        }
        return $config_data;
    }
    public function is_cron_import_job_directory()
    {
        return $this->directory_type == self::TYPE_IMPORT && $this->parent_id == 4;
    }
    public function process($cron_ask = false)
    {
        if ($this->directory_type == self::TYPE_IMPORT) {
            $this->synchronize_files($cron_ask);
            foreach ($this->get_subdirectories(true) as $check_dir) {
                if ($check_dir->directory_type == self::TYPE_PROCESSED) {
                    $check_dir->clean_dir();
                }
            }
        } elseif ($this->directory_type == self::TYPE_DATASOURCE) {
            foreach ($this->get_subdirectories(true) as $check_dir) {
                if ($check_dir->directory_type == self::TYPE_PROCESSED) {
                    $check_dir->clean_dir();
                } elseif ($check_dir->directory_type == self::TYPE_DATASOURCE) {
                    $check_dir->watch_idle_hang();
                }
            }
        } elseif ($this->directory_type == self::TYPE_PROCESSED) {
            $this->clean_dir();
        }
    }
    public function watch_idle_hang()
    {
        foreach ($this->get_jobs() as $ep_job) {
            /**
             * @var $epJob JobDatasource
             */
            if ($ep_job instanceof Job_Datasource && $ep_job->is_hang_job()) {
                $ep_job->move_to_processed();
            }
        }
    }
    public function clean_dir()
    {
        if (isset($this->directory_config['cleaning_term']) && $this->directory_config['cleaning_term'] != -1) {
            $remove_older_then = strtotime('-' . $this->directory_config['cleaning_term']);
            if ($this->get_parent()->directory_type == self::TYPE_DATASOURCE) {
                $get_job_remove_r = tep_db_query('SELECT job_id ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' " . " AND last_cron_run<='" . date('Y-m-d H:i:s', $remove_older_then) . "' " . 'ORDER BY job_id DESC ' . 'LIMIT 256');
                if (tep_db_num_rows($get_job_remove_r) > 0) {
                    while ($job_remove = tep_db_fetch_array($get_job_remove_r)) {
                        Job::load_by_id($job_remove['job_id'])->delete();
                    }
                }
            } else {
                $get_job_remove_r = tep_db_query('SELECT job_id ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' " . " AND last_cron_run<='" . date('Y-m-d H:i:s', $remove_older_then) . "'");
                if (tep_db_num_rows($get_job_remove_r) > 0) {
                    while ($job_remove = tep_db_fetch_array($get_job_remove_r)) {
                        Job::load_by_id($job_remove['job_id'])->delete();
                    }
                }
            }
        }
    }
    public function synchronize_directories($cron_ask)
    {
        if (self::TYPE_PROCESSED == $this->directory_type) {
            return;
        }
        $db_link_check = [];
        $get_db_link_r = tep_db_query('SELECT directory_id, directory ' . 'FROM ' . TABLE_EP_DIRECTORIES . ' ' . "WHERE parent_id='" . $this->directory_id . "'");
        if (tep_db_num_rows($get_db_link_r) > 0) {
            while ($_db_link = tep_db_fetch_array($get_db_link_r)) {
                $db_link_check[$_db_link['directory']] = $_db_link['directory_id'];
            }
        }
        $directory_root = $this->files_root($this->directory_type);
        foreach (glob($directory_root . '*', GLOB_ONLYDIR) as $check_dir) {
            $check_directory_name = basename($check_dir);
            //if (preg_match('/^(images|processed)$/', $checkDirectoryName)) continue;
            if (preg_match('/^(images)$/', $check_directory_name)) {
                continue;
            }
            if (!isset($db_link_check[$check_directory_name])) {
                tep_db_perform(TABLE_EP_DIRECTORIES, ['removable' => 1, 'cron_enabled' => $this->cron_enabled, 'parent_id' => $this->directory_id, 'name' => '--', 'directory_type' => $check_directory_name == self::TYPE_PROCESSED ? self::TYPE_PROCESSED : $this->directory_type, 'directory' => $check_directory_name]);
                $new_directory_id = tep_db_insert_id();
                $new_directory = self::load_by_id($new_directory_id);
                if ($new_directory->is_cron_import_job_directory()) {
                    // touch dir
                    $new_directory->files_root(self::TYPE_PROCESSED);
                }
                $new_directory->synchronize_files($cron_ask);
            } else {
                $old_directory_id = $db_link_check[$check_directory_name];
                unset($db_link_check[$check_directory_name]);
                $old_directory = self::load_by_id($old_directory_id);
                if ($old_directory->is_cron_import_job_directory()) {
                    // touch dir
                    $old_directory->files_root(self::TYPE_PROCESSED);
                }
                $old_directory->synchronize_files($cron_ask);
            }
        }
        // remove not found
        if (count($db_link_check) > 0) {
            tep_db_query('DELETE FROM ' . TABLE_EP_JOB . " WHERE directory_id IN ('" . implode("','", $db_link_check) . "')");
            tep_db_query('DELETE FROM ' . TABLE_EP_DIRECTORIES . " WHERE directory_id IN ('" . implode("','", $db_link_check) . "') AND removable=1");
        }
    }
    public function synchronize_files($cron_ask = false)
    {
        if ($this->directory_type != self::TYPE_IMPORT) {
            return;
        }
        $dir_scan = $this->files_root();
        if (!is_dir($dir_scan)) {
            return;
        }
        if ($this->cron_enabled || $this->directory_type == 'import') {
            $this->synchronize_directories($cron_ask);
        }
        $dir_scan = File_Helper::normalize_path($dir_scan, '/') . '/';
        $dir_files = File_Helper::find_files($dir_scan, ['recursive' => false, 'except' => ['.svn', 'images', 'processed']]);
        $dir_files = array_map(function ($val) {
            return File_Helper::normalize_path($val, '/');
        }, $dir_files);
        $get_db_files_r = tep_db_query('SELECT job_id, IF(LENGTH(file_name_internal)>0, file_name_internal, file_name) AS file_name ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' /*AND direction='" . $this->directory_type . "'*/ ");
        $db_files = [];
        if (tep_db_num_rows($get_db_files_r) > 0) {
            while ($_db_file = tep_db_fetch_array($get_db_files_r)) {
                $check_fs_job = Job::load_by_id($_db_file['job_id']);
                if ($check_fs_job instanceof Job_File) {
                    $db_files[$_db_file['file_name']] = $_db_file['job_id'];
                }
            }
        }
        foreach ($dir_files as $fs_filename) {
            $fs_filename = str_replace($dir_scan, '', $fs_filename);
            if (isset($db_files[$fs_filename])) {
                unset($db_files[$fs_filename]);
            }
            $job = $this->find_job_by_filename($fs_filename);
            if ($cron_ask && $this->cron_enabled) {
                if ($job && $job instanceof Job_File) {
                    $job->watch_file_changes();
                    if ($job->job_state == Job::STATE_UPLOADED) {
                        $job->try_auto_configure();
                    }
                    /*
                    //$this->touchImportJob($fsFilename,'upload','auto');
                    if ($job->checkUploadFinish()) {
                        $job->tryAutoConfigure();
                    }
                    */
                } else {
                    $this->touch_import_job($fs_filename, 'upload', 'auto');
                }
            } elseif (!$this->cron_enabled) {
                if (!$job) {
                    $job_id = $this->touch_import_job($fs_filename, 'uploaded', 'auto');
                    $job = Job::load_by_id($job_id);
                    if ($job && $job instanceof Job_File) {
                        $job->try_auto_configure();
                    }
                }
            }
        }
        if (count($db_files) > 0) {
            tep_db_query('DELETE FROM ' . TABLE_EP_JOB . ' ' . "WHERE job_id IN('" . implode("','", $db_files) . "') AND directory_id='" . $this->directory_id . "'");
        }
    }
    public static function get_all($renew_cache = false)
    {
        static $directories_list = false;
        if ($renew_cache) {
            $directories_list = false;
        }
        if (!is_array($directories_list)) {
            $directories_list = [];
            $get_all_r = tep_db_query('SELECT * FROM ' . TABLE_EP_DIRECTORIES . ' WHERE 1 ORDER BY directory_id');
            if (tep_db_num_rows($get_all_r) > 0) {
                while ($data = tep_db_fetch_array($get_all_r)) {
                    $directories_list[] = new static($data);
                }
            }
        }
        return $directories_list;
    }
    public static function get_all_roots()
    {
        $roots = [];
        foreach (self::get_all() as $directory) {
            if (!empty($directory->parent_id)) {
                continue;
            }
            if ($directory->directory_id == 5 && count(Data_Sources::get_available_list()) == 0) {
                continue;
            }
            $roots[] = $directory;
        }
        return $roots;
    }
    /**
     *
     * @param id $id
     * @return self
     */
    public static function find_by_id($id)
    {
        $result = false;
        foreach (self::get_all() as $directory) {
            if ($directory->directory_id == $id) {
                $result = $directory;
                break;
            }
        }
        if ($result === false) {
            $result = self::load_by_id($id);
            if (is_object($result)) {
                self::get_all(true);
            }
        }
        return $result;
    }
    public function find_job_by_filename($filename)
    {
        $get_db_file_r = tep_db_query('SELECT job_id ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' /*AND direction = '" . tep_db_input($this->directory_type) . "'*/ " . "  AND (BINARY file_name = BINARY '" . tep_db_input($filename) . "' OR file_name_internal='" . tep_db_input($filename) . "') ");
        if (tep_db_num_rows($get_db_file_r)) {
            $job_data = tep_db_fetch_array($get_db_file_r);
            return Job::load_by_id($job_data['job_id']);
        }
        return false;
    }
    public function get_jobs()
    {
        $job_list = [];
        $get_db_file_r = tep_db_query('SELECT job_id ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "'" . '');
        if (tep_db_num_rows($get_db_file_r)) {
            while ($job_data = tep_db_fetch_array($get_db_file_r)) {
                $job_list[] = Job::load_by_id($job_data['job_id']);
            }
        }
        return $job_list;
    }
    public function get_job_config_template($job_id)
    {
        $job_obj = Job::load_by_id($job_id);
        if (!$job_obj) {
            return [];
        }
        $directory_config = [];
        foreach ($this->directory_config as $idx => $job_config) {
            if (!($job_obj->job_provider == $job_config['job_provider'] && $job_obj->file_name == $job_config['filename_pattern'])) {
                continue;
            }
            $directory_config[$idx] = $job_config;
        }
        return $directory_config;
    }
    public function update_job_directory_config($job_id, $new_config)
    {
        if (!is_array($new_config) || !$this->directory_id) {
            return;
        }
        $configs = $this->get_job_config_template($job_id);
        if (empty($configs)) {
            return;
        }
        foreach (array_keys($configs) as $idx) {
            $this->directory_config[$idx] = array_merge($this->directory_config[$idx], $new_config);
        }
        tep_db_query('UPDATE ' . TABLE_EP_DIRECTORIES . ' ' . "SET directory_config='" . tep_db_input(json_encode($this->directory_config)) . "' " . "WHERE directory_id='" . (int) $this->directory_id . "'");
    }
    public function touch_import_job($file_name, $job_state, $job_provider)
    {
        if ($this->directory_type == self::TYPE_DATASOURCE) {
            $file_data_array = ['directory_id' => $this->directory_id, 'direction' => preg_match('/\.zip$/i', $file_name) ? 'import_zip' : $this->directory_type, 'file_name' => $file_name, 'file_time' => 0, 'file_size' => 0, 'job_state' => $job_state];
        } else {
            clearstatcache();
            $file_dir = $this->files_root();
            $file_path_name = $file_dir . $file_name;
            if (!is_file($file_path_name)) {
                return false;
            }
            $direction = preg_match('/\.zip$/i', $file_name) ? 'import_zip' : $this->directory_type;
            if (defined('EP_MULTI_SHEETS') && !empty(EP_MULTI_SHEETS) && $direction != 'import_zip') {
                $multi_sheets = explode(',', EP_MULTI_SHEETS);
                if (!empty($multi_sheets) && is_array($multi_sheets)) {
                    foreach ($multi_sheets as $file_ext) {
                        $file_ext = strtolower($file_ext);
                        if (preg_match('/\.' . preg_quote($file_ext) . '$/i', $file_name)) {
                            $direction = 'import_sheets';
                            break;
                        }
                    }
                }
            }
            $file_data_array = ['directory_id' => $this->directory_id, 'direction' => $direction, 'file_name' => $file_name, 'file_time' => filemtime($file_path_name), 'file_size' => filesize($file_path_name), 'job_state' => $job_state];
        }
        if (!is_null($job_provider)) {
            $file_data_array['job_provider'] = $job_provider;
        }
        if (!isset($file_data_array['job_provider']) || empty($file_data_array['job_provider']) || $file_data_array['job_provider'] == 'auto') {
            $config = $this->find_config_by_file_name($file_name);
            if ($config) {
                $file_data_array['run_frequency'] = $config['run_frequency'];
                $file_data_array['run_time'] = $config['run_time'];
                $file_data_array['job_provider'] = $config['job_provider'];
            }
        }
        $db_connection = Yii::$app->get('db');
        /**
         * @var yii\db\Connection $dbConnection
         */
        $get_existing_job_id = $db_connection->create_command('SELECT job_id ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->directory_id . "' /*AND direction = '" . tep_db_input($this->directory_type) . "'*/ " . "  AND BINARY file_name = BINARY '" . tep_db_input($file_name) . "' ")->query_all();
        if (count($get_existing_job_id) > 0) {
            $_db_file = reset($get_existing_job_id);
            tep_db_perform(TABLE_EP_JOB, $file_data_array, 'update', "job_id='" . (int) $_db_file['job_id'] . "'");
            $job_id = (int) $_db_file['job_id'];
        } else {
            tep_db_perform(TABLE_EP_JOB, $file_data_array);
            $job_id = tep_db_insert_id();
        }
        return $job_id;
    }
    /**
     * @return bool|DatasourceBase
     */
    public function get_datasource()
    {
        $datasource = false;
        if ($this->directory_type != self::TYPE_DATASOURCE) {
            if ($this->parent_id) {
                return $this->get_parent()->get_datasource();
            }
            return $datasource;
        }
        $datasource = Data_Sources::get_by_name($this->directory);
        return $datasource;
    }
    /**
     * @param $name int
     * @return Directory|bool
     */
    public static function get_datasource_root($name)
    {
        $get_created_id_r = tep_db_query('SELECT directory_id FROM ' . TABLE_EP_DIRECTORIES . " WHERE directory='" . tep_db_input($name) . "' AND parent_id=5");
        if (tep_db_num_rows($get_created_id_r) > 0) {
            $get_directory_id = tep_db_fetch_array($get_created_id_r);
            return self::find_by_id($get_directory_id['directory_id']);
        }
        return false;
    }
}