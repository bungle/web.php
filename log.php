<?php
// Logging
namespace log {
    function appenders() {
        static $appenders = array();
        if (func_num_args() == 0) return $appenders;
        $args = func_get_args();
        foreach ($args as $arg) $appenders[] = $arg;
    }
    function debug($message) { append($message, LOG_DEBUG, debug_backtrace()); }
    function info($message) { append($message, LOG_INFO, debug_backtrace()); }
    function warn($message) { append($message, LOG_WARNING, debug_backtrace()); }
    function error($message) { append($message, LOG_ERR, debug_backtrace()); }
    function append($message, $level, $trace) {
        $appenders = appenders();
        foreach ($appenders as $appender) $appender($message, $level, $trace);
    }
    function level($level) {
        if ($level > LOG_INFO) return 'DEBUG';
        if ($level > LOG_WARNING) return 'INFO';
        return $level > LOG_ERR ? 'WARNING' : 'ERROR';
    }
    function file($file, $log_level = LOG_DEBUG) {
        return function($message, $level, $trace) use ($file, $log_level) {
            if ($log_level < $level) return;
            static $messages = null;
            if ($messages == null) {
                register_shutdown_function(function($file) use (&$messages) {
                    file_put_contents($file, $messages, FILE_APPEND | LOCK_EX);
                }, $file);
            }
            list($usec, $sec) = explode(' ', microtime());
            if (is_array($message)) $message = print_r($message, true);
            $messages .= sprintf('%s %7s %-20s %s', date('Y-m-d H:i:s.', $sec) . substr($usec, 2, 3) , level($level), basename($trace[0]['file']) . ':' . $trace[0]['line'], trim($message) . PHP_EOL);
        };
    }
    function chromephp($log_level) {
        return function($message, $level, $trace) use ($log_level) {
            if ($log_level < $level) return;
            switch ($level) {
                case LOG_ERR: return \ChromePhp::error($message);
                case LOG_WARNING: return \ChromePhp::warn($message);
                case LOG_INFO: return \ChromePhp::info($message);
                default: return \ChromePhp::log($message);
            }
        };
    }
}