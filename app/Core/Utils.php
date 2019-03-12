<?php

namespace Gino\Jobs\Core;

/**
 * 工具
 * 
 * @author Gino Huang <binsuper@126.com>
 */
class Utils {

    /**
     * 创建目录
     * @param string $dir
     * @return bool
     */
    public static function mkdir(string $dir): bool {
        return is_dir($dir) || (static::mkdir(dirname($dir)) && @mkdir($dir, 0755));
    }

    /**
     * 捕捉错误和异常
     * @param \Gino\Jobs\Core\Logger $logger
     * @param Exception|Error $ex
     */
    public static function catchError(\Gino\Jobs\Core\Logger $logger, $ex) {
        $error = 'Error Type：' . get_class($ex) . PHP_EOL;
        $error .= 'Error Code：' . $ex->getCode() . PHP_EOL;
        $error .= 'Error Msg：' . $ex->getMessage() . PHP_EOL;
        $error .= 'Error Strace：' . $ex->getTraceAsString() . PHP_EOL;
        $logger->log($error, \Gino\Jobs\Core\Logger::LEVEL_ERROR, 'error', true);
    }

    /**
     * 获取负载情况
     *
     * @return string
     */
    public static function getSysLoadAvg() {
        $loadavg = function_exists('sys_getloadavg') ? array_map('round', sys_getloadavg(), [2]) : ['-', '-', '-'];
        return 'load average: ' . implode(', ', $loadavg);
    }

    /**
     * 获取内存使用量
     *
     * @return string
     */
    public static function getMemoryUsage() {
        return round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB';
    }

    /**
     * 
     * @param array $columns
     * @return string
     */
    public static function formatTablePrint(array $columns) {
        $str  = '';
        $rule = [10, 20, 10, 10, 10, 10, 10, 10, 15];
        foreach ($columns as $i => $col) {
            $str .= str_pad($col, ($rule[$i] ?? 0) < strlen($col) ? strlen($col) + 2 : ($rule[$i] ?? 0));
        }
        return $str;
    }

}
