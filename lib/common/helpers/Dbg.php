<?php

declare (strict_types=1);
/**
 * This file is part of True Loaded.
 *
 * @link http://www.holbi.co.uk
 * @copyright Copyright (c) 2005 Holbi Group LTD
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */
namespace common\helpers;

use Yii;
class Dbg_Null
{
    public static function __callStatic($name, $arguments)
    {
    }
}
class Dbg
{
    public static function define_consts()
    {
        //$s= \common\models\Configuration::findOne(['configuration_key' => 'DEFINE_DBG_CONST'])->configuration_value ?? '';
        $str = defined('DEFINE_DBG_CONST') ? DEFINE_DBG_CONST : '';
        if (!empty($str)) {
            foreach (explode(',', $str) as $const_name) {
                $name = 'DBG_' . $const_name;
                defined($name) or define($name, true);
            }
        }
    }
    /**
     * @param $debugConst - part of debug constant
     * @return Dbg
     */
    public static function if_defined($debug_const)
    {
        if (!empty($debug_const) && defined('DBG_' . $debug_const) && constant('DBG_' . $debug_const) === true) {
            self::$last_dbg_const = $debug_const;
            return self::class;
        }
        return Dbg_Null::class;
    }
    private static function export($var)
    {
        if (is_object($var)) {
            try {
                return var_export($var, true);
            } catch (\Exception $e) {
                return \yii\helpers\Var_Dumper::export($var);
            }
        } else {
            return \yii\helpers\Var_Dumper::export($var);
        }
    }
    private static function export_var_array($vars)
    {
        $res = '';
        foreach ($vars as $var) {
            if (!empty($res)) {
                $res .= is_object($var) || is_array($var) ? "\n" : ',';
            }
            $res .= self::export($var);
        }
        return $res;
    }
    public static function log($msg)
    {
        if (class_exists('\Yii')) {
            $tmp = \Yii::$app->log->trace_level;
            \Yii::$app->log->trace_level = 0;
            \Yii::info(self::get_prefix() . $msg, empty(self::$log_entity) ? 'dbg/log' : end(self::$log_entity));
            \Yii::$app->log->trace_level = $tmp;
        }
    }
    public static function logf($msg)
    {
        self::log(\common\helpers\Php::vsprintf_safe($msg, array_slice(func_get_args(), 1)));
    }
    public static function log_var($var, $msg = 'var')
    {
        self::log($msg . '=' . self::export($var));
    }
    public static function log_vars()
    {
        self::log(self::export_var_array(func_get_args()));
    }
    public static function log_query($var, $msg = 'query')
    {
        if ($var instanceof \yii\db\query) {
            $var = $var->create_command()->get_raw_sql();
        }
        self::log_var($var, $msg);
        return $var;
    }
    public static function log_error($e, $prefix = null, $stack = false)
    {
        self::out_error($e, 'log', $prefix, $stack);
    }
    public static function echo($msg)
    {
        if (!class_exists('\common\helpers\System') || \common\helpers\System::is_development() || \common\helpers\System::is_console()) {
            echo '<pre>' . self::get_prefix() . $msg . '</pre>';
        }
        self::log($msg);
    }
    public static function out($msg, $dest)
    {
        if (method_exists(self::class, $dest)) {
            self::$dest($msg);
        }
    }
    public static function out_error($e, $dest = 'echo', $prefix = null, $stack = false)
    {
        $msg = empty($prefix) ? 'Error: ' : $prefix . ': ';
        if ($e instanceof \Throwable) {
            $msg .= $e->get_message();
            if ($stack) {
                $msg .= "\n" . $e->get_trace_as_string();
            }
        } else {
            $msg .= $e;
        }
        self::out($msg, $dest);
    }
    public static function echo_var($var, $msg = 'var')
    {
        self::echo($msg . '=' . self::export($var));
        self::log_var($var, $msg);
    }
    public static function echo_vars()
    {
        $vars = self::export_var_array(func_get_args());
        self::echo($vars);
        self::log_var($vars);
    }
    public static function echo_query($var, $msg = 'query')
    {
        if ($var instanceof \yii\db\query) {
            $var = $var->create_command()->get_raw_sql();
        }
        self::echo_var($var, $msg);
        return $var;
    }
    public static function echo_error($e, $prefix = null, $stack = false)
    {
        self::out_error($e, 'echo', $prefix, $stack);
    }
    private static function get_file_name($fn, $ext)
    {
        \common\helpers\Assert::match('/^[-\w]+$/', $fn, 'Invalid file name');
        $fn = Yii::get_alias('@app/runtime/logs/') . $fn;
        if (file_exists($fn . $ext)) {
            $fn .= date(' Y-m-d H-i-s ') . microtime();
        }
        return $fn . $ext;
    }
    public static function save_var($var, $fn = 'var')
    {
        $content = self::export($var);
        self::save_text($content, $fn, '.vardump');
    }
    public static function save_json($var, $fn = 'var')
    {
        $content = json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        self::save_text($content, $fn, '.json');
    }
    public static function save_text($txt, $fn = 'var', $ext = '.txt')
    {
        $fn = self::get_file_name($fn, $ext);
        if (false === file_put_contents($fn, $txt)) {
            \Yii::warning('Error in file_put_contents: ' . error_get_last(), 'debug/save');
        }
    }
    public static function load_text($fn, $ext)
    {
        $res = file_get_contents(Yii::get_alias('@app/runtime/logs/') . $fn . $ext);
        if (false === $res) {
            \Yii::warning('Error in file_get_contents: ' . (error_get_last()['message'] ?? 'Unknown'), 'debug/save');
        }
        return $res;
    }
    public static function load_json($fn, $associative = null)
    {
        $txt = self::load_text($fn, '.json');
        return json_decode($txt, $associative);
    }
    public static function log_stack($msg = 'Stack', $full = false)
    {
        self::log_var(self::get_stack($full), $msg);
    }
    public static function echo_stack($msg = 'Stack', $full = false)
    {
        self::echo_var(self::get_stack($full), $msg);
    }
    public static function get_stack($full = false)
    {
        ob_start();
        debug_print_backtrace($full ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS);
        $ret = ob_get_contents();
        ob_end_clean();
        return $ret;
    }
    public static $time_first = null;
    public static $time = null;
    private static function time_start($msg)
    {
        self::$time = self::$time_first = microtime(true);
        if (!empty($msg)) {
            self::log('Timer started' . $msg);
        }
    }
    public static function log_time($msg = '')
    {
        if (is_null(self::$time)) {
            self::time_start($msg);
            return 0;
        } else {
            $cur = microtime(true);
            $msg = empty($msg) ? '' : $msg . '. ';
            $elapsed = $cur - self::$time;
            self::log(sprintf('%sElapsed: %.3f (since start %.3f)', $msg, $elapsed, $cur - self::$time_first));
            self::$time = microtime(true);
            return $elapsed;
        }
    }
    public static $time_loop = [];
    private const LOOP_GLOBAL = '__loop-global__';
    public static function time_loop_start($loop_name = 'loop1')
    {
        self::$time_loop[$loop_name][self::LOOP_GLOBAL]['cur_time'] = microtime(true);
    }
    public static function time_loop($msg, $loop_name = 'loop1')
    {
        if (isset(self::$time_loop[$loop_name][self::LOOP_GLOBAL]['cur_time'])) {
            $cur_interval = microtime(true) - self::$time_loop[$loop_name][self::LOOP_GLOBAL]['cur_time'];
        } else {
            $cur_interval = 0;
            self::log("Interval for {$loop_name}/{$msg} can't be calculated. Use ::timeLoopStart() befoe ::timeLoop()");
        }
        self::$time_loop[$loop_name][$msg]['time'] = (self::$time_loop[$loop_name][$msg]['time'] ?? 0) + $cur_interval;
        self::$time_loop[$loop_name][$msg]['count'] = (self::$time_loop[$loop_name][$msg]['count'] ?? 0) + 1;
        self::$time_loop[$loop_name][self::LOOP_GLOBAL]['cur_time'] = microtime(true);
        return $cur_interval;
    }
    public static function time_loop_log($loop_name = 'loop1')
    {
        if (is_array(self::$time_loop[$loop_name] ?? null)) {
            $s = "Loop {$loop_name}:\n";
            foreach (self::$time_loop[$loop_name] as $name => $val) {
                if ($name != self::LOOP_GLOBAL) {
                    $s .= sprintf("%s=%.3f (%d times)\n", $name, $val['time'], $val['count']);
                }
            }
            self::log($s);
        } else {
            self::log("Loop {$loop_name} does not contains items");
        }
    }
    public static function time_loop_log_all()
    {
        foreach (self::$time_loop as $loop_name => $val) {
            self::time_loop_log($loop_name);
        }
    }
    private static $mem = null;
    private static $mem_real = null;
    private static $mem_count = 0;
    public static function out_mem($msg = '', $force = false, $dest = 'log')
    {
        $new_real = memory_get_peak_usage(false);
        $new = memory_get_peak_usage(true);
        if (is_null(self::$mem)) {
            self::$mem_real = $new_real;
            self::$mem = $new;
        } else if ($force || $new_real != self::$mem_real || $new != self::$mem) {
            if (!empty($msg)) {
                $msg .= ': ';
            }
            $real_str = $force ? '=' . memory_get_usage(true) : '';
            self::out(sprintf('%sMax=%d (+%d) Real%s+%d Count=%d', $msg, $new, $new - self::$mem, $real_str, $new_real - self::$mem_real, self::$mem_count), $dest);
            self::$mem_count = 0;
            self::$mem = $new;
            self::$mem_real = $new_real;
        } else {
            self::$mem_count++;
        }
    }
    public static function get_prefix_state()
    {
        return ['prefix' => count(self::$log_prefix), 'enity' => count(self::$log_entity)];
    }
    private static $last_dbg_const = null;
    private static $log_prefix = [];
    private static $log_entity = [];
    private static function add_entity($entity)
    {
        if (is_bool($entity)) {
            if ($entity && !empty(self::$last_dbg_const)) {
                self::$log_entity[] = 'dbg/' . self::$last_dbg_const;
            }
        } elseif (is_string($entity) && !empty($entity)) {
            self::$log_entity[] = "dbg/{$entity}";
        }
    }
    public static function add_prefix(string $prefix, $entity = null)
    {
        $state = self::get_prefix_state();
        if (!empty($entity)) {
            self::$log_entity[] = "dbg/{$entity}";
        }
        if (!empty($prefix)) {
            self::$log_prefix[] = $prefix;
        }
        return $state;
    }
    public static function del_prefix($state = null, $msg = 'Finished')
    {
        if (!empty($msg)) {
            self::log($msg);
        }
        if (is_null($state)) {
            array_pop(self::$log_prefix);
        } else {
            self::$log_prefix = array_slice(self::$log_prefix, 0, $state['prefix'] ?? count(self::$log_prefix));
            self::$log_entity = array_slice(self::$log_entity, 0, $state['enity'] ?? count(self::$log_entity));
        }
    }
    public static function log_prefix(string $prefix, $entity = true)
    {
        $state = self::get_prefix_state();
        self::add_entity($entity);
        self::log($prefix);
        self::add_prefix($prefix);
        return $state;
    }
    public static function get_prefix()
    {
        return empty(self::$log_prefix) ? '' : implode('::', self::$log_prefix) . ' ';
    }
}