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

use backend\models\EP\Provider\Datasource_Interface;
class Job_Datasource extends Job
{
    public function can_configure_export()
    {
        return false;
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
    public function can_configure_import()
    {
        $can = false;
        try {
            $job = $this->get_job_instance();
            if (method_exists($job, 'importOptions')) {
                $can = !empty($job->import_options());
            }
        } catch (\Exception $ex) {
            \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG');
        }
        return $can;
    }
    public function can_run()
    {
        $this->check_idle();
        if ($this->job_state == self::PROCESS_STATE_CONFIGURED || $this->job_state == self::PROCESS_STATE_IDLE) {
            return true;
        }
        return false;
    }
    /**
     * Timeout for job restart
     *
     * @return int
     */
    public function maximum_hang_time_minutes()
    {
        return 12 * 60;
    }
    /**
     * Check job too long running.
     * If last_cron_run time touched time more then maximumHangTimeMinutes and in running state
     *
     * @return bool
     */
    public function is_hang_job()
    {
        if ($activity_state = $this->job_activity_state()) {
            if (in_array($activity_state['job_state'], [Job::PROCESS_STATE_IN_PROGRESS, Job::PROCESS_STATE_IDLE])) {
                $db_time = strtotime($activity_state['db_time']);
                $last_ping_seconds = $db_time - strtotime($activity_state['last_cron_run']);
                if ($last_ping_seconds >= $this->maximum_hang_time_minutes() * 60) {
                    \Yii::info('Hang job #' . $this->job_id . ' ' . $this->file_name . " state: {$activity_state['job_state']};" . " last_ping: {$activity_state['last_cron_run']};" . ' allow minutes:' . $this->maximum_hang_time_minutes() . '  (' . date('Y-m-d H:i:s', strtotime('-' . $this->maximum_hang_time_minutes() . 'minutes', $db_time)) . '); ', 'datasource');
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Get job state from db
     *
     * @return array|false
     */
    protected function job_activity_state()
    {
        return tep_db_fetch_array(tep_db_query('select last_cron_run, now() as db_time, job_state from ' . TABLE_EP_JOB . ' ' . "WHERE job_id='" . intval($this->job_id) . "'"));
    }
    public function check_idle()
    {
        $idle = tep_db_fetch_array(tep_db_query('select (MINUTE(TIMEDIFF(last_cron_run, now()))) as minutes from ' . TABLE_EP_JOB . ' ' . "WHERE job_id='" . intval($this->job_id) . "' and job_state='in_progress'"));
        if (($idle['minutes'] ?? null) > 10) {
            $this->job_state = self::PROCESS_STATE_IDLE;
        }
        return;
    }
    public function can_run_in_browser()
    {
        $can = false;
        try {
            $job = $this->get_job_instance();
            if (method_exists($job, 'allowRunInPopup')) {
                $can = $job->allow_run_in_popup();
            }
        } catch (\Exception $ex) {
        }
        return $can;
    }
    public function run_asap()
    {
        $this->run_frequency = 1;
        tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . 'SET run_frequency=1 ' . "WHERE job_id='" . intval($this->job_id) . "'");
    }
    public function get_providers()
    {
        return new Providers();
    }
    protected function get_data_source_by_name(Directory $directory)
    {
        return Data_Sources::get_by_name($directory->directory);
    }
    protected function get_job_instance()
    {
        $directory = Directory::load_by_id($this->directory_id);
        if (!is_object($directory)) {
            throw new Exception('Not found job directory.');
        }
        $datasource = $this->get_data_source_by_name($directory);
        if (!is_object($datasource)) {
            throw new Exception('Not found job datasource object.');
        }
        $datasource_provider_config = $datasource->get_job_config();
        $datasource_provider_config['workingDirectory'] = $directory->files_root();
        $datasource_provider_config['directoryId'] = $this->directory_id;
        if (is_array($this->job_configure) && !empty($this->job_configure)) {
            $datasource_provider_config['job_configure'] = $this->job_configure;
        }
        $providers = $this->get_providers();
        return $providers->get_provider_instance($this->job_provider, $datasource_provider_config);
    }
    public function run(Messages $messages)
    {
        try {
            $provider_obj = $this->get_job_instance();
            if (property_exists($provider_obj, 'job_id')) {
                $provider_obj->job_id = $this->job_id;
            }
        } catch (Exception $ex) {
            $messages->info($ex->get_message() . ' Exit job.');
        }
        $messages->command('start');
        try {
            if ($provider_obj instanceof Datasource_Interface) {
                $messages->progress(0);
                $started = time();
                $idle_ping = $started;
                $row_counter = 0;
                $progress_row_inform = 100;
                $last_info_say_time = $started;
                $last_progress = 0;
                set_time_limit(0);
                $provider_obj->prepare_process($messages);
                while ($provider_obj->process_row($messages)) {
                    echo '.';
                    $row_counter++;
                    $current_time = time();
                    $percent_progress = $provider_obj->get_progress();
                    if ((int) $percent_progress - $last_progress > 1 || $row_counter % $progress_row_inform == 0 || $current_time - $last_info_say_time > 60) {
                        $last_progress = (int) $percent_progress;
                        if ($percent_progress == 0) {
                            $seconds_for_job = round(($current_time - $started) * 100 / 0.0001);
                        } else {
                            $seconds_for_job = round(($current_time - $started) * 100 / $percent_progress);
                        }
                        $time_left = 'Time left: ' . gmdate('H:i:s', max(0, $seconds_for_job - ($current_time - $started)));
                        if ($current_time != $started) {
                            $time_left .= ' ' . number_format($row_counter / ($current_time - $started), 1, '.', '') . ' Lines per second';
                        }
                        if ($this->is_alive() === false) {
                            // job removed;
                            // hmm.. postprocess or not?
                            echo "\nJob removed. Exit\n";
                            break;
                        }
                        $messages->progress($percent_progress, $time_left);
                        $idle_ping = $current_time;
                        set_time_limit(0);
                        $last_info_say_time = $current_time;
                    } elseif ($this->job_id && $current_time - $idle_ping > 60) {
                        // workaround for idle state
                        tep_db_perform(TABLE_EP_JOB, ['last_cron_run' => date('Y-m-d H:i:s', $current_time), 'job_state' => Job::PROCESS_STATE_IN_PROGRESS], 'update', "job_id='" . $this->job_id . "'");
                        $idle_ping = $current_time;
                    }
                }
                $messages->progress(100);
                $provider_obj->post_process($messages);
            }
        } catch (\Exception $ex) {
            //$messages->info($ex->getMessage());
            \Yii::error('Job exception: ' . $ex->get_message() . "\n" . $ex->get_trace_as_string(), 'datasource');
            throw $ex;
        }
    }
    public function job_finished()
    {
        parent::job_finished();
        $this->move_to_processed();
    }
    public function move_to_processed()
    {
        $new_job_directory_id = $this->directory_id;
        if (!parent::move_to_processed()) {
            \Yii::error('Move ' . $this->file_name . ' to processed failed - renew skip', 'datasource');
            return;
        }
        if (is_array($this->job_configure) && isset($this->job_configure['oneTimeJob']) && $this->job_configure['oneTimeJob'] === true) {
            // on time job
            return;
        }
        if (!$this->is_alive()) {
            \Yii::error('Move ' . $this->file_name . ' to processed failed - current job not in db', 'datasource');
            return;
        }
        $data_array = ['directory_id' => $new_job_directory_id, 'direction' => $this->direction, 'file_name' => $this->file_name, 'file_time' => 0, 'file_size' => 0, 'job_state' => 'configured', 'job_provider' => $this->job_provider, 'run_frequency' => $this->run_frequency, 'run_time' => $this->run_time, 'job_configure' => !empty($this->job_configure) ? json_encode($this->job_configure) : 'null', 'last_cron_run' => 'now()'];
        // {{ restore time if run by admin request - Immediately or and custom job time modification
        $directory = Directory::load_by_id($this->directory_id);
        if (is_object($directory) && $directory->directory_type == Directory::TYPE_PROCESSED) {
            $directory = $directory->get_parent();
        }
        if (is_object($directory)) {
            $job_config = $directory->find_config_by_file_name($this->file_name);
            if (is_array($job_config)) {
                if (array_key_exists('run_frequency', $job_config)) {
                    $data_array['run_frequency'] = $job_config['run_frequency'];
                }
                if (array_key_exists('run_time', $job_config)) {
                    $data_array['run_time'] = $job_config['run_time'];
                }
            }
        }
        // }} restore time
        \Yii::info("[EP_CRON] Dir {$data_array['directory_id']} re-add processed job {$data_array['file_name']}", 'datasource');
        $this_job_scheduled_count = \Yii::$app->get_db()->create_command('SELECT COUNT(*) ' . 'FROM ' . TABLE_EP_JOB . ' ' . 'WHERE directory_id=:dir_id AND file_name=:job_name', [':dir_id' => (int) $data_array['directory_id'], ':job_name' => (string) $data_array['file_name']])->query_scalar();
        if (is_numeric($this_job_scheduled_count) && $this_job_scheduled_count > 0) {
            \Yii::info("[EP_CRON] [CRITICAL] Job {$data_array['directory_id']} {$data_array['file_name']} already exist", 'datasource');
        } else {
            tep_db_perform(TABLE_EP_JOB, $data_array);
        }
    }
}