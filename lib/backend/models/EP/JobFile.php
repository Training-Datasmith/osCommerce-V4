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
class Job_File extends Job
{
    public function delete()
    {
        $filename = $this->get_file_system_name();
        if (is_file($filename)) {
            @unlink($filename);
        }
        return parent::delete();
    }
    public function get_file_system_name()
    {
        if (strpos($this->file_name, 'php://') === 0) {
            return $this->file_name;
        }
        $directory = $this->get_directory();
        $ep_files_dir = $directory->files_root();
        return $ep_files_dir . (empty($this->file_name_internal) ? $this->file_name : $this->file_name_internal);
    }
    public function get_full_filename()
    {
        if (strpos($this->file_name, 'php://') === 0) {
            return $this->file_name;
        }
        $directory = $this->get_directory();
        $ep_files_dir = $directory->files_root();
        return $ep_files_dir . $this->file_name;
    }
    public function get_file_info()
    {
        $filename = $this->get_file_system_name();
        return ['pathFilename' => $this->get_full_filename(), 'fileSystemName' => $filename, 'filename' => $this->file_name, 'fileSize' => is_file($filename) ? filesize($filename) : false, 'fileTime' => is_file($filename) ? filemtime($filename) : 0];
    }
    public function can_remove()
    {
        if ($this->direction == 'export') {
            return true;
        }
        $job_filename = $this->get_file_system_name();
        return is_writeable(dirname($job_filename));
    }
    public function watch_file_changes()
    {
        clearstatcache();
        $file_info = $this->get_file_info();
        $touch_data = ['file_time' => $file_info['fileTime'], 'file_size' => $file_info['fileSize']];
        if ($touch_data['file_size'] == $this->file_size) {
            if ($this->job_state == self::STATE_UPLOAD_IN_PROGRESS) {
                $touch_data['job_state'] = self::STATE_UPLOADED;
                if (empty($this->file_name_internal)) {
                    $file_name_internal = md5(time() . '' . $this->file_name);
                    $directory = $this->get_directory();
                    if (rename($directory->files_root() . $this->file_name, $directory->files_root() . $file_name_internal)) {
                        $touch_data['file_name_internal'] = $file_name_internal;
                    }
                }
            }
        } else {
            $touch_data['job_state'] = self::STATE_UPLOAD_IN_PROGRESS;
        }
        if ($this->job_id) {
            tep_db_perform(TABLE_EP_JOB, $touch_data, 'update', "job_id='" . $this->job_id . "'");
        }
        foreach ($touch_data as $key => $val) {
            $this->{$key} = $val;
        }
    }
    public function check_upload_finish()
    {
        clearstatcache();
        $file_info = $this->get_file_info();
        $touch_data = ['file_time' => $file_info['fileTime'], 'file_size' => $file_info['fileSize']];
        if ($touch_data['file_size'] == $this->file_size) {
            $touch_data['job_state'] = self::STATE_UPLOADED;
            $file_name_internal = md5(time() . '' . $this->file_name);
            $directory = $this->get_directory();
            if (rename($directory->files_root() . $this->file_name, $directory->files_root() . $file_name_internal)) {
                $touch_data['file_name_internal'] = $file_name_internal;
            }
        }
        if ($this->job_id) {
            tep_db_perform(TABLE_EP_JOB, $touch_data, 'update', "job_id='" . $this->job_id . "'");
        }
        foreach ($touch_data as $key => $val) {
            $this->{$key} = $val;
        }
        return $this->job_state == self::STATE_UPLOADED;
    }
    protected function get_provider()
    {
        $job_construct_param = ['job_configure' => $this->job_configure];
        if (is_array($this->job_configure)) {
            $job_construct_param = $this->job_configure;
        }
        $directory = $this->get_directory();
        if (is_object($directory)) {
            $job_construct_param['directoryObj'] = $directory;
        }
        $providers = $this->get_providers();
        $provider_obj = $providers->get_provider_instance($this->job_provider, $job_construct_param);
        return $provider_obj;
    }
    public function try_auto_configure($selected_provider = '')
    {
        $possible_providers = [];
        if (!empty($selected_provider)) {
            $this->job_provider = $selected_provider;
            $this->job_configure = [];
            $this->save_configure_state();
        }
        if (empty($this->job_provider) || $this->job_provider == 'auto' || !empty($selected_provider)) {
            $providers = $this->get_providers();
            $file_system_name = $this->get_file_system_name();
            if (fnmatch('*.xml', $this->file_name)) {
                $possible = $providers->get_available_providers('Import', function ($provider_key, $provider_info) use ($selected_provider) {
                    if ((empty($selected_provider) || $selected_provider == $provider_key) && isset($provider_info['export']) && isset($provider_info['export']['allow_format'])) {
                        return count(preg_grep('/xml/i', $provider_info['export']['allow_format'])) > 0;
                    }
                    return false;
                });
                $f = fopen($file_system_name, 'r');
                $first_cut = 2048;
                $sh_cut = '';
                do {
                    $sh_cut .= fread($f, $first_cut);
                    if (stripos($sh_cut, '<header>') === false) {
                        break;
                    } else if (stripos($sh_cut, '</header>') !== false) {
                        break;
                    }
                } while (true);
                fclose($f);
                $header = false;
                if ($sh_cut && ($h0 = stripos($sh_cut, '<header>')) !== false && ($h1 = stripos($sh_cut, '</header>')) !== false && $h1 > $h0) {
                    $xml_obj = new \Simple_Xml_Element(substr($sh_cut, $h0, $h1 - $h0 + 9));
                    $header = json_decode(json_encode($xml_obj), true);
                }
                //                if (preg_match( '#<header>(.*)</header>#m',$shCut, $matchHeader) ) {
                //                    $xmlObj = new \SimpleXMLElement($matchHeader[0]);
                //                    $header = json_decode(json_encode($xmlObj),true);
                //                }
                if (is_array($header) && count($header) > 0) {
                    foreach ($possible as $possible_provider_info) {
                        $provider_obj = $providers->get_provider_instance($possible_provider_info['key']);
                        if (!method_exists($provider_obj, 'exchangeXml')) {
                            continue;
                        }
                        foreach ($provider_obj->exchange_xml() as $version_info) {
                            if (isset($version_info['Header']) && $version_info['Header']['type'] == $header['type']) {
                                $xml_reader = preg_grep('/xml/i', $possible_provider_info['export']['allow_format']);
                                if (isset($header['projectCode'])) {
                                    $version_info['projectCode'] = $header['projectCode'];
                                }
                                $this->job_configure['import'] = $version_info;
                                $this->job_configure['import']['format'] = current($xml_reader);
                                $this->job_state = self::STATE_CONFIGURED;
                                $this->job_provider = $possible_provider_info['key'];
                                $possible_providers[$this->job_provider] = 1;
                                break;
                            } elseif (isset($version_info['header']) && $version_info['header'] == $header) {
                                $xml_reader = preg_grep('/xml/i', $possible_provider_info['export']['allow_format']);
                                $this->job_configure['import'] = $version_info;
                                $this->job_configure['import']['format'] = current($xml_reader);
                                $this->job_state = self::STATE_CONFIGURED;
                                $this->job_provider = $possible_provider_info['key'];
                                $possible_providers[$this->job_provider] = 1;
                                break;
                            }
                        }
                    }
                }
                if (!is_array($header) && $this->job_state != self::STATE_CONFIGURED && strpos($sh_cut, '<Orders>') !== false) {
                    $this->job_configure['import']['format'] = 'XML_orders_new';
                    $this->job_state = self::STATE_CONFIGURED;
                    $this->job_provider = 'orders\orders';
                    $possible_providers[$this->job_provider] = 1;
                }
                if ($this->job_id && $this->job_state == self::STATE_CONFIGURED) {
                    $this->save_configure_state();
                }
                return $possible_providers;
            }
            $n = strrpos($file_system_name, '.');
            if ($n !== false) {
                $ext = substr($file_system_name, $n + 1);
                $ns = '\backend\models\EP\Reader\\';
                $classname = '';
                if (class_exists($ns . strtoupper($ext))) {
                    $classname = $ns . strtoupper($ext);
                } elseif (class_exists($ns . ucfirst($ext))) {
                    $classname = $ns . ucfirst($ext);
                }
                if ($classname != '') {
                    $reader = new $classname(['filename' => $file_system_name]);
                    $reader->filename = $file_system_name;
                }
            }
            if (!is_object($reader)) {
                $reader = new Reader\CSV(['filename' => $file_system_name]);
            }
            $file_columns = $reader->read_columns();
            if (is_array($file_columns) && count($file_columns) > 0) {
                $possible_providers = $providers->best_match($file_columns);
                if (!empty($selected_provider)) {
                    if (isset($possible_providers[$selected_provider])) {
                        $possible_providers = [$selected_provider => $possible_providers[$selected_provider]];
                    } else {
                        $possible_providers = [];
                    }
                }
                reset($possible_providers);
                if (current($possible_providers) == 1 || !empty($selected_provider) && isset($possible_providers[$selected_provider])) {
                    $file_provider = current(array_keys($possible_providers));
                    $this->job_state = self::STATE_CONFIGURED;
                    $this->job_provider = $file_provider;
                    if (!empty($this->job_id) && $this->direction == 'import' && $this->get_directory()->cron_enabled) {
                        $q = tep_db_query('select job_configure, run_frequency, job_provider FROM ' . TABLE_EP_JOB . ' ' . ' WHERE ' . " direction='" . $this->direction . "' and job_state='processed' and " . " file_name='" . tep_db_input($this->file_name) . "' and run_frequency>0" . ' ORDER BY job_id desc LIMIT 1');
                        if ($_details = tep_db_fetch_array($q)) {
                            //from saveConfigureState + run_frequency.
                            $this->run_frequency = $_details['run_frequency'];
                            $this->job_configure = json_decode($_details['job_configure'], true);
                            tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='" . tep_db_input($this->job_state) . "', job_provider='" . tep_db_input($this->job_provider) . "', " . " job_configure='" . tep_db_input(json_encode($this->job_configure)) . "' " . ", run_frequency='" . (int) $this->run_frequency . "' " . "WHERE job_id='" . $this->job_id . "' ");
                        }
                    }
                    $this->save_configure_state();
                } else if (!empty($this->job_id) && $this->direction == 'import' && $reader instanceof Reader\CSV) {
                    $q = tep_db_query('select job_configure, run_frequency, job_provider FROM ' . TABLE_EP_JOB . ' ' . ' WHERE ' . " direction='" . $this->direction . "' and job_state='processed' and " . " file_name='" . tep_db_input($this->file_name) . "' and run_frequency>0" . ' ORDER BY job_id desc LIMIT 1');
                    if ($_details = tep_db_fetch_array($q)) {
                        //from saveConfigureState + run_frequency.
                        $this->job_state = self::STATE_CONFIGURED;
                        $this->job_provider = $_details['job_provider'];
                        $this->run_frequency = $_details['run_frequency'];
                        $this->job_configure = json_decode($_details['job_configure'], true);
                        tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='" . tep_db_input($this->job_state) . "', job_provider='" . tep_db_input($this->job_provider) . "', " . " job_configure='" . tep_db_input(json_encode($this->job_configure)) . "' " . ", run_frequency='" . (int) $this->run_frequency . "' " . "WHERE job_id='" . $this->job_id . "' ");
                    }
                }
            }
        } else if ($this->job_state != self::STATE_CONFIGURED) {
            $this->job_state = self::STATE_CONFIGURED;
            $this->save_configure_state();
        }
        return $possible_providers;
    }
    public function run(Messages $messages)
    {
        if ($this->direction == 'import') {
            $this->run_import($messages);
        } elseif ($this->direction == 'export') {
            $this->run_export($messages);
        }
    }
    public function run_export($messages)
    {
        $selected_columns = false;
        $filter = [];
        if (isset($this->job_configure['export']) && is_array($this->job_configure['export'])) {
            if (isset($this->job_configure['export']['columns'])) {
                $selected_columns = $this->job_configure['export']['columns'];
            }
            if (isset($this->job_configure['export']['filter']) && is_array($this->job_configure['export']['filter'])) {
                $filter = $this->job_configure['export']['filter'];
            }
        }
        $export_provider_obj = $this->get_provider();
        if (!is_object($export_provider_obj)) {
            die;
        }
        $writer_configure = [];
        if (stripos($this->job_configure['export']['format'], 'XML') !== false && method_exists($export_provider_obj, 'exchangeXml')) {
            $exchange_convention = $export_provider_obj->exchange_xml();
            if (count($exchange_convention) > 0) {
                $exchange_convention = end($exchange_convention);
                if (is_array($exchange_convention)) {
                    $writer_configure = array_merge($writer_configure, $exchange_convention);
                }
            }
        } elseif (isset($this->job_configure['export']['write_config']) && is_array($this->job_configure['export']['write_config'])) {
            $writer_configure = array_merge($writer_configure, $this->job_configure['export']['write_config']);
        }
        if (isset($this->job_configure['export']['feed'])) {
            $writer_configure = array_merge(['class' => 'backend\models\EP\Writer\\' . $this->job_configure['export']['format'], 'filename' => $this->get_file_system_name(), 'feed' => isset($this->job_configure['export']['feed']) ? $this->job_configure['export']['feed'] : []], $writer_configure);
        } else {
            $writer_configure = array_merge(['class' => 'backend\models\EP\Writer\\' . $this->job_configure['export']['format'], 'filename' => $this->get_file_system_name()], $writer_configure);
        }
        $writer_configure['class'] = 'backend\models\EP\Writer\\' . $this->job_configure['export']['format'];
        $writer = Yii::create_object($writer_configure);
        $export_columns = $export_provider_obj->get_columns();
        if (is_array($selected_columns) && count($selected_columns) > 0) {
            $_selected = [];
            foreach ($selected_columns as $selected_column) {
                if (!isset($export_columns[$selected_column])) {
                    continue;
                }
                $_selected[$selected_column] = $export_columns[$selected_column];
            }
            $export_columns = $_selected;
        }
        //add import instruction to XLSX export files - set before setColumns (it writes out columns to file)
        if (method_exists($writer, 'setDescription') && method_exists($export_provider_obj, 'getExportDescription')) {
            $writer->set_description($export_provider_obj->get_export_description());
        }
        $writer->set_columns($export_columns);
        $export_provider_obj->set_columns($export_columns);
        $export_provider_obj->set_format($this->job_configure['export']['format']);
        $export_provider_obj->prepare_export(array_keys($export_columns), $filter);
        while (is_array($provider_data = $export_provider_obj->export_row())) {
            $writer->write($provider_data);
        }
        $writer->close();
    }
    public function run_import(Messages $messages)
    {
        if (empty($this->job_provider) || $this->job_provider == 'auto') {
            throw new Exception('Need select job type');
        }
        $provider_obj = $this->get_provider();
        //$messages->setEpFileId($this->job_id);
        //        $messages = new EP\Messages([
        //            'job_id' => $job_record->job_id,
        //        ]);
        $messages->command('start_import');
        //$dir = rtrim(\Yii::getAlias($this->ep_work_dir), '/');
        $filename = $this->get_file_system_name();
        try {
            $reader_class = 'CSV';
            if (preg_match('/\.zip$/i', $filename)) {
                $reader_class = 'ZIP';
            }
            if (isset($this->job_configure['import']) && !empty($this->job_configure['import']['format'])) {
                $reader_class = $this->job_configure['import']['format'];
            } elseif (preg_match('/\.xls$/i', $filename)) {
                $reader_class = 'XLS';
            } elseif (preg_match('/\.xlsx$/i', $filename)) {
                $reader_class = 'XLSX';
            }
            $reader_config = array_merge(['class' => 'backend\models\EP\Reader\\' . $reader_class, 'filename' => $filename], isset($this->job_configure['import']) && is_array($this->job_configure['import']) ? $this->job_configure['import'] : []);
            $reader = Yii::create_object($reader_config);
            $provider_obj->set_format($reader_class);
            $transform = new Transform();
            $transform->set_provider_columns($provider_obj->get_columns());
            if (isset($this->job_configure['remap_columns']) && is_array($this->job_configure['remap_columns'])) {
                $transform->set_transform_map($this->job_configure['remap_columns']);
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
            $this->job_finished();
        } catch (\Exception $ex) {
            //$messages->info($ex->getMessage());
            $this->job_finished();
            throw $ex;
        }
    }
    public function move_to_processed()
    {
        $directory = $this->get_directory();
        $processed_directory = $directory->get_processed_directory();
        if (!$processed_directory) {
            return false;
        }
        $processed_dir = $processed_directory->files_root();
        if ($this->file_name_internal) {
            $new_name = $this->file_name_internal;
            $new_file_name = $this->file_name;
        } else {
            $file_pathinfo = pathinfo($this->file_name);
            $new_name = $file_pathinfo['filename'] . '_' . date('YmdHis') . (isset($file_pathinfo['extension']) ? '.' . $file_pathinfo['extension'] : '');
            $new_file_name = $new_name;
        }
        if (rename($this->get_file_system_name(), $processed_dir . $new_name)) {
            @chmod($processed_dir . $new_name, 0666);
            $this->job_state = self::STATE_PROCESSED;
            $this->file_name = $new_file_name;
            $this->directory_id = $processed_directory->directory_id;
            tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='" . tep_db_input($this->job_state) . "', file_name='" . tep_db_input($this->file_name) . "', directory_id='" . intval($this->directory_id) . "' " . "WHERE job_id='" . intval($this->job_id) . "'");
        }
    }
}