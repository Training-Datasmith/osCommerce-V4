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

class Messages extends \yii\base\Base_Object implements \backend\models\Notification_Interface
{
    public $job_id;
    public $output = 'www';
    public $log_message_id;
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->set_ep_file_id($this->job_id);
    }
    public function set_ep_file_id($id)
    {
        $this->job_id = $id;
        if ($this->job_id) {
            tep_db_query('DELETE FROM ' . TABLE_EP_LOG_MESSAGES . " WHERE job_id='" . (int) $this->job_id . "'");
        }
    }
    public function info($text)
    {
        if ($this->output == 'null') {
            return;
        }
        if ($this->output == 'www') {
            echo '<script>window.parent.uploader(\'message\', ' . json_encode($text) . ')</script>';
            echo str_repeat(' ', 2048);
            echo "\n";
            ob_flush();
            flush();
        } elseif ($this->output == 'console') {
            echo "{$text}\n";
        }
        if ($this->job_id) {
            tep_db_perform(TABLE_EP_LOG_MESSAGES, ['job_id' => $this->job_id, 'message_time' => 'now()', 'message_text' => $text]);
            $this->log_message_id = tep_db_insert_id();
            tep_db_perform(TABLE_EP_JOB, ['last_cron_run' => date('Y-m-d H:i:s', strtotime('now'))], 'update', "job_id='" . $this->job_id . "' AND job_state = '" . Job::PROCESS_STATE_IN_PROGRESS . "'");
        }
    }
    public function progress($percent_done, $time_string = '')
    {
        if ($this->output == 'null') {
            return;
        }
        if ($this->output == 'www') {
            if (empty($time_string)) {
                echo '<script>window.parent.uploader(\'progress\', ' . json_encode(round($percent_done)) . ')</script>';
            } else {
                echo '<script>window.parent.uploader(\'progress\', ' . json_encode(round($percent_done)) . ', ' . json_encode($time_string) . ')</script>';
            }
            echo str_repeat(' ', 1024 * 8);
            echo "\n";
            ob_flush();
            flush();
        } elseif ($this->output == 'console') {
            if (empty($time_string)) {
                echo ' =>' . round($percent_done) . "%\n";
            } else {
                echo ' =>' . round($percent_done) . "% {$time_string}\n";
            }
        }
        if ($this->job_id) {
            tep_db_perform(TABLE_EP_JOB, ['process_progress' => round($percent_done), 'last_cron_run' => date('Y-m-d H:i:s', strtotime('now')), 'job_state' => Job::PROCESS_STATE_IN_PROGRESS], 'update', "job_id='" . $this->job_id . "'");
        }
    }
    public function command($command)
    {
        if ($this->output == 'www') {
            if (func_num_args() > 1) {
                $arg = func_get_args();
                array_shift($arg);
                echo '<script>window.parent.uploader(\'' . $command . '\',' . json_encode($arg) . ')</script>';
            } else {
                echo '<script>window.parent.uploader(\'' . $command . '\')</script>';
            }
            echo str_repeat(' ', 2048);
            echo "\n";
            ob_flush();
            flush();
        }
    }
    public function get_messages()
    {
        $messages = [];
        if ($this->job_id) {
            $get_messages_r = tep_db_query('SELECT `message_text` ' . 'FROM ' . TABLE_EP_LOG_MESSAGES . ' ' . "WHERE job_id='" . $this->job_id . "' " . 'ORDER BY ep_log_message_id');
            if (tep_db_num_rows($get_messages_r) > 0) {
                while ($get_message = tep_db_fetch_array($get_messages_r)) {
                    $messages[] = $get_message['message_text'];
                }
            }
        }
        return $messages;
    }
    public function prepare_admin_message($message = null)
    {
        return base64_encode(serialize(['ep_log_message_id' => $this->log_message_id]));
    }
    public function get_admin_message($message = null)
    {
        $_message = unserialize(base64_decode($message));
        if (is_array($_message)) {
            if (isset($_message['ep_log_message_id'])) {
                $log_message = tep_db_fetch_array(tep_db_query('SELECT `message_text`, job_id ' . 'FROM ' . TABLE_EP_LOG_MESSAGES . ' ' . "WHERE ep_log_message_id='" . (int) $_message['ep_log_message_id'] . "' "));
                if ($log_message) {
                    $job = Job::load_by_id($log_message['job_id']);
                    return $job->job_provider . ", {$log_message['message_text']}";
                }
            }
        }
        return '';
    }
}