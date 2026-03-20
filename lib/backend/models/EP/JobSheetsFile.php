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

use yii\helpers\File_Helper;
class Job_Sheets_File extends Job_File
{
    public function delete()
    {
        /*        $file = $this->getFileSystemName();
                  $extractDir = dirname($file).'/'.pathinfo($this->file_name,PATHINFO_FILENAME).'/';
                  Directory::findById($this->directory_id);
                  FileHelper::removeDirectory($extractDir);
                 */
        return parent::delete();
    }
    public function can_configure_export()
    {
        return false;
    }
    public function can_configure_import()
    {
        return true;
    }
    public function get_archived_file_columns()
    {
        $result = [];
        $file_system_name = $this->get_file_system_name();
        if (preg_match('/\.xlsx$/i', $file_system_name)) {
            $reader = new Reader\XLSX(['filename' => $file_system_name]);
            $reader->filename = $file_system_name;
            $result = $reader->read_sheets();
            unset($reader);
        }
        return $result;
    }
    public function try_auto_configure($selected_provider = '')
    {
        $detected_providers = [];
        if (empty($this->job_provider) || $this->job_provider == 'auto') {
            $providers = new Providers();
            $full_auto_configure = null;
            $archived_file_columns = $this->get_archived_file_columns();
            $job_configure = ['containerFilesSetting' => []];
            $job_provider = '';
            $container_provider_type = [];
            /*
             if ( isset($archivedFileColumns['process_sequence.csv']) ) {
             $reader = new Reader\ZIP([
             'filename' => $this->getFileSystemName(),
             ]);
             while($fileInfo = $reader->read()){
             if ($fileInfo['filename']=='process_sequence.csv'){
             $fileSystemName = tempnam(sys_get_temp_dir(), 'ep_test_archived_feed');
             $writeStream = fopen($fileSystemName,'wb');
             while( $data = fread($fileInfo['stream'],16*1024) ) {
             fwrite($writeStream, $data);
             }
             fclose($writeStream);
            
             $nestedReader = new Reader\CSV([
             'filename' => $fileSystemName,
             ]);
             while($feedData = $nestedReader->read()){
             if ( !empty($feedData['Feed Type']) ) {
             $containerProviderType[$feedData['Feed Process Queue']] = $feedData['Feed Type'];
             }
             }
             unset($nestedReader);
             unlink($fileSystemName);
             }
             }
             }
            */
            foreach ($archived_file_columns as $archived_file => $file_info) {
                $file_columns = $file_info['columns'];
                if (is_array($file_columns) && count($file_columns) > 0) {
                    $possible_providers = $providers->best_match($file_columns);
                    reset($possible_providers);
                    $__file_provider_list = array_keys($possible_providers);
                    if (count($__file_provider_list) > 0) {
                        if ($job_provider != 'product\catalog') {
                            $job_provider = $__file_provider_list[0];
                            if (isset($container_provider_type[$archived_file]) && !empty($container_provider_type[$archived_file])) {
                                if (array_search($container_provider_type[$archived_file], $__file_provider_list) !== false) {
                                    $job_provider = $container_provider_type[$archived_file];
                                }
                            }
                        } else {
                            continue;
                        }
                        $job_configure['containerFilesSetting'][$archived_file] = ['job_provider' => $job_provider];
                    }
                    //$autoConfigured[$archivedFile] = ['provider'=>current($possibleProviders)];
                    if (current($possible_providers) == 1) {
                        $file_provider = current(array_keys($possible_providers));
                        $detected_providers[] = $file_provider;
                    } else {
                        $full_auto_configure = false;
                    }
                }
            }
            if ($job_provider) {
                $this->job_state = self::STATE_CONFIGURED;
                $this->job_provider = $job_provider;
                $this->job_configure = $job_configure;
                if ($this->job_id) {
                    tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='" . tep_db_input($this->job_state) . "', job_provider='" . tep_db_input($this->job_provider) . "', " . " job_configure='" . tep_db_input(json_encode($this->job_configure)) . "' " . "WHERE job_id='" . $this->job_id . "' ");
                }
            }
        }
        if ($this->job_state != self::STATE_CONFIGURED) {
            $this->job_state = self::STATE_CONFIGURED;
            tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='" . tep_db_input($this->job_state) . "' " . "WHERE job_id='" . $this->job_id . "' ");
        }
        return $detected_providers;
    }
    public function run(Messages $messages)
    {
        $this->run_sheets($messages);
    }
    public function run_sheets(Messages $messages)
    {
        if ($this->job_provider != '' && $this->job_provider != 'auto') {
            //$filename = $this->file_name;
            $filename = $this->get_file_system_name();
            $reader_class = false;
            if (isset($this->job_configure['import']) && !empty($this->job_configure['import']['format'])) {
                $reader_class = $this->job_configure['import']['format'];
            } elseif (preg_match('/\.xls$/i', $filename)) {
                $reader_class = 'XLS';
            } elseif (preg_match('/\.xlsx$/i', $filename)) {
                $reader_class = 'XLSX';
            }
            /**
             * @var $processSubDir Directory
             */
            $providers = new \backend\models\EP\Providers();
            $process_sub_dir = $this->get_directory();
            if ($process_sub_dir) {
                $messages->set_ep_file_id($this->job_id);
                $messages->command('start_import');
                $messages->command('persist_messages', true);
                if (is_array($this->job_configure) && isset($this->job_configure['containerFilesSetting'])) {
                    foreach ($this->job_configure['containerFilesSetting'] as $subfilename => $file_configure) {
                        if (empty($file_configure['job_provider']) || empty($reader_class)) {
                            continue;
                        }
                        $messages->info('<b>Process "' . $subfilename . '"</b>');
                        try {
                            $ext = 'backend\models\EP\Reader\\' . $reader_class;
                            $reader = new $ext(['filename' => $filename]);
                            $reader->filename = $filename;
                            $reader->sheet_name = $subfilename;
                            $provider_obj = $providers->get_provider_instance($file_configure['job_provider'], $file_configure);
                            $provider_obj->set_format($reader_class);
                            $transform = new Transform();
                            $transform->set_provider_columns($provider_obj->get_columns());
                            if ($file_configure['remap_columns']) {
                                $transform->set_transform_map($file_configure['remap_columns']);
                            }
                            $started = time();
                            $progress_row_inform = 100;
                            $row_counter = 0;
                            while ($data = $reader->read()) {
                                if ($reader_class != 'XML') {
                                    $data = $transform->transform($data);
                                }
                                set_time_limit(300);
                                $provider_obj->import_row($data, $messages);
                                $row_counter++;
                                if ($row_counter % $progress_row_inform == 0) {
                                    $percent_progress = $reader->get_progress();
                                    $current_time = time();
                                    if ($percent_progress == 0) {
                                        $seconds_for_job = round(($current_time - $started) * 100 / 0.0001);
                                    } else {
                                        $seconds_for_job = round(($current_time - $started) * 100 / $percent_progress);
                                    }
                                    $time_left = 'Time left: ' . gmdate('H:i:s', max(0, $seconds_for_job - ($current_time - $started)));
                                    if ($current_time != $started) {
                                        $time_left .= ' ' . number_format($row_counter / ($current_time - $started), 1, '.', '') . ' Lines per second';
                                    }
                                    $messages->progress($percent_progress, $time_left);
                                    set_time_limit(300);
                                }
                            }
                            $messages->progress(100);
                            $provider_obj->post_process($messages);
                        } catch (\Exception $ex) {
                            //$messages->info($ex->getMessage());
                            $messages->command('persist_messages', false);
                            $this->job_finished();
                            throw $ex;
                        }
                    }
                    $this->job_finished();
                }
                $messages->command('persist_messages', false);
                return;
            }
        }
    }
}