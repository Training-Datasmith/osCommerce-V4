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
class Job_Zip_File extends Job_File
{
    public function delete()
    {
        $file = $this->get_file_system_name();
        $extract_dir = dirname($file) . '/' . pathinfo($this->file_name, PATHINFO_FILENAME) . '/';
        Directory::find_by_id($this->directory_id);
        File_Helper::remove_directory($extract_dir);
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
        if (preg_match('/\.zip$/i', $this->file_name)) {
            $reader = new Reader\ZIP(['filename' => $file_system_name]);
            while ($file_info = $reader->read()) {
                if (preg_match('/\.(csv|txt)$/i', $file_info['filename'])) {
                    $file_system_name = tempnam(sys_get_temp_dir(), 'ep_test_archived_feed');
                    $write_stream = fopen($file_system_name, 'wb');
                    $limit_extract_block = 16;
                    // extract only 256k
                    while ($data = fread($file_info['stream'], 16 * 1024)) {
                        fwrite($write_stream, $data);
                        $limit_extract_block--;
                        if ($limit_extract_block <= 0) {
                            break;
                        }
                    }
                    fclose($write_stream);
                    $nested_reader = new Reader\CSV(['filename' => $file_system_name]);
                    $file_columns = $nested_reader->read_columns();
                    @unlink($file_system_name);
                    $result[$file_info['filename']] = ['columns' => $file_columns];
                } elseif (preg_match('/\.(xml)$/i', $file_info['filename'])) {
                    $file_system_name = tempnam(sys_get_temp_dir(), 'ep_test_archived_feed');
                    //$writeStream = fopen($fileSystemName,'wb');
                    $head_chunk = '';
                    $limit_extract_block = 16;
                    // extract only 256k
                    while ($data = fread($file_info['stream'], 16 * 1024)) {
                        //fwrite($writeStream, $data);
                        $head_chunk .= $data;
                        $limit_extract_block--;
                        if ($limit_extract_block <= 0) {
                            break;
                        }
                    }
                    //fclose($writeStream);
                    $result[$file_info['filename']] = ['headChunk' => $head_chunk];
                }
            }
            unset($reader);
        }
        return $result;
    }
    public function try_auto_configure($selected_provider = '')
    {
        $detected_providers = [];
        if (empty($this->job_provider) || $this->job_provider == 'auto') {
            $providers = new Providers();
            $possible_xml = $providers->get_available_providers('Import', function ($provider_key, $provider_info) use ($selected_provider) {
                if ((empty($selected_provider) || $selected_provider == $provider_key) && isset($provider_info['export']) && isset($provider_info['export']['allow_format'])) {
                    return count(preg_grep('/xml/i', $provider_info['export']['allow_format'])) > 0;
                }
                return false;
            });
            $full_auto_configure = null;
            $archived_file_columns = $this->get_archived_file_columns();
            $job_configure = ['containerFilesSetting' => []];
            $job_provider = '';
            $container_provider_type = [];
            if (isset($archived_file_columns['process_sequence.csv'])) {
                $reader = new Reader\ZIP(['filename' => $this->get_file_system_name()]);
                while ($file_info = $reader->read()) {
                    if ($file_info['filename'] == 'process_sequence.csv') {
                        $file_system_name = tempnam(sys_get_temp_dir(), 'ep_test_archived_feed');
                        $write_stream = fopen($file_system_name, 'wb');
                        while ($data = fread($file_info['stream'], 16 * 1024)) {
                            fwrite($write_stream, $data);
                        }
                        fclose($write_stream);
                        $nested_reader = new Reader\CSV(['filename' => $file_system_name]);
                        while ($feed_data = $nested_reader->read()) {
                            if (!empty($feed_data['Feed Type'])) {
                                $container_provider_type[$feed_data['Feed Process Queue']] = $feed_data['Feed Type'];
                            }
                        }
                        unset($nested_reader);
                        unlink($file_system_name);
                    }
                }
            }
            foreach ($archived_file_columns as $archived_file => $file_info) {
                if ($archived_file == 'process_sequence.csv') {
                    $job_provider = 'product\catalog';
                }
                if (preg_match('/\.(csv|txt)$/', $archived_file)) {
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
                } elseif (preg_match('/\.(xml)$/i', $archived_file)) {
                    $sh_cut = $file_info['headChunk'];
                    $header = false;
                    if ($sh_cut && ($h0 = stripos($sh_cut, '<header>')) !== false && ($h1 = stripos($sh_cut, '</header>')) !== false && $h1 > $h0) {
                        $xml_obj = new \Simple_Xml_Element(substr($sh_cut, $h0, $h1 - $h0 + 9));
                        $header = json_decode(json_encode($xml_obj), true);
                    }
                    if (is_array($header) && count($header) > 0) {
                        foreach ($possible_xml as $possible_provider_info) {
                            $provider_obj = $providers->get_provider_instance($possible_provider_info['key']);
                            if (!method_exists($provider_obj, 'exchangeXml')) {
                                continue;
                            }
                            $feed_settings = [];
                            foreach ($provider_obj->exchange_xml() as $version_info) {
                                if (isset($version_info['Header']) && $version_info['Header']['type'] == $header['type']) {
                                    $xml_reader = preg_grep('/xml/i', $possible_provider_info['export']['allow_format']);
                                    if (isset($header['projectCode'])) {
                                        $version_info['projectCode'] = $header['projectCode'];
                                    }
                                    $feed_settings['job_configure'] = [];
                                    $feed_settings['job_configure']['import'] = $version_info;
                                    $feed_settings['job_configure']['import']['format'] = current($xml_reader);
                                    $feed_settings['job_state'] = self::STATE_CONFIGURED;
                                    $feed_settings['job_provider'] = $possible_provider_info['key'];
                                    $detected_providers[] = $feed_settings['job_provider'];
                                    break;
                                } elseif (isset($version_info['header']) && $version_info['header'] == $header) {
                                    $xml_reader = preg_grep('/xml/i', $possible_provider_info['export']['allow_format']);
                                    $feed_settings['job_configure'] = [];
                                    $feed_settings['job_configure']['import'] = $version_info;
                                    $feed_settings['job_configure']['import']['format'] = current($xml_reader);
                                    $feed_settings['job_state'] = self::STATE_CONFIGURED;
                                    $feed_settings['job_provider'] = $possible_provider_info['key'];
                                    $detected_providers[] = $feed_settings['job_provider'];
                                    break;
                                }
                            }
                            if (count($feed_settings) > 0) {
                                $job_configure['containerFilesSetting'][$archived_file] = $feed_settings;
                            }
                        }
                    }
                    if (count($detected_providers) > 0) {
                        $job_provider = current($detected_providers);
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
        $this->run_zip($messages);
    }
    public function run_zip(Messages $messages)
    {
        $file = $this->get_file_system_name();
        $extract_dir = dirname($file) . '/' . pathinfo($this->file_name, PATHINFO_FILENAME) . '/';
        File_Helper::create_directory($extract_dir, 0777);
        $zip = new \Zip_Archive();
        $zip->open($file);
        for ($i = 0; $i < $zip->num_files; $i++) {
            $filename = $zip->get_name_index($i);
            $stream = $zip->get_stream($filename);
            $extract_filename = $extract_dir . $filename;
            if (!is_dir(dirname($extract_filename))) {
                File_Helper::create_directory(dirname($extract_filename), 0777, true);
            }
            if (preg_match('#[/|\\\\]$#', $filename)) {
                continue;
            }
            // skip directory
            $write_stream = fopen($extract_filename, 'wb');
            while ($data = fread($stream, 16 * 1024)) {
                fwrite($write_stream, $data);
            }
            fclose($stream);
            fclose($write_stream);
            chmod($extract_filename, 0666);
        }
        $zip->close();
        $this->get_directory()->synchronize_directories(false);
        if ($this->job_provider != '' && $this->job_provider != 'auto') {
            /**
             * @var $processSubDir Directory
             */
            $process_sub_dir = false;
            foreach ($this->get_directory()->get_subdirectories(false) as $sub_dir) {
                if ($sub_dir->directory == basename($extract_dir)) {
                    $process_sub_dir = $sub_dir;
                    break;
                }
            }
            $providers = new \backend\models\EP\Providers();
            if ($process_sub_dir) {
                $messages->set_ep_file_id($this->job_id);
                $messages->command('start_import');
                // {{ patch auto configured
                if (is_array($this->job_configure) && isset($this->job_configure['containerFilesSetting'])) {
                    foreach ($this->job_configure['containerFilesSetting'] as $subfilename => $file_configure) {
                        if (empty($file_configure['job_provider'])) {
                            continue;
                        }
                        $sub_job_record = $process_sub_dir->find_job_by_filename($subfilename);
                        if ($sub_job_record) {
                            $sub_job_record->job_provider = $file_configure['job_provider'];
                            if ($file_configure['remap_columns'] ?? null) {
                                $sub_job_record->job_configure['remap_columns'] = $file_configure['remap_columns'];
                            }
                            tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_provider='" . tep_db_input($sub_job_record->job_provider) . "', " . " job_configure='" . tep_db_input(json_encode($sub_job_record->job_configure)) . "' " . "WHERE job_id='" . $sub_job_record->job_id . "'");
                        }
                    }
                }
                // }} patch auto configured
                $provider_obj = $providers->get_provider_instance($this->job_provider);
                $job_record = $process_sub_dir->find_job_by_filename('process_sequence.csv');
                if ($job_record) {
                    $job_record->job_provider = 'product\catalog';
                    $messages->info('<b>Process "' . $job_record->file_name . '"</b>');
                    try {
                        $job_record->run($messages);
                    } catch (\Exception $ex) {
                        $messages->info($ex->get_message());
                        \Yii::error($ex->get_message() . (YII_DEBUG ? "\n" . $ex->get_trace_as_string() : ''));
                    }
                } else {
                    foreach ($process_sub_dir->get_jobs() as $directory_job) {
                        $messages->command('persist_messages', true);
                        /**
                         * @var $directoryJob Job
                         */
                        if ($directory_job->job_provider == '' || $directory_job->job_provider == 'auto') {
                            continue;
                        }
                        $messages->info('<b>Process "' . $directory_job->file_name . '"</b>');
                        try {
                            $directory_job->run($messages);
                        } catch (\Exception $ex) {
                            $messages->info($ex->get_message());
                            \Yii::error($ex->get_message() . (YII_DEBUG ? "\n" . $ex->get_trace_as_string() : ''));
                        }
                    }
                    $messages->command('persist_messages', false);
                }
                File_Helper::remove_directory($extract_dir);
                $this->move_to_processed();
                $this->get_directory()->synchronize_directories(false);
                return;
            }
        }
        Directory::get_all(true);
    }
}