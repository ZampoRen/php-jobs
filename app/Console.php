<?php

namespace Gino\Jobs;

use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Config;
use Gino\Jobs\Core\Process;

/**
 * 控制台
 * 
 * @author Gino Huang <binsuper@126.com>
 */
class Console {

    protected $_logger;
    private $__run_opts = [];
    private $__run_args = [];

    public function __construct(array $config) {
        //检测配置信息
        if (empty($config['log']) || empty($config['log']['log_dir'])) {
            die('config log.log_dir must be set' . PHP_EOL);
        }

        //初始化配置
        Config::setConfig($config);
        //初始化日志实例
        Logger::regist(Config::getConfig('log', 'log_dir'), Config::getConfig('log', 'log_file', 'application.log'));
        Logger::regist(Config::getConfig('log', 'log_dir'), Config::getConfig('process', 'process_log_file', 'process.log'), 'PROCESS');

        //初始化对象
        $this->_logger = Logger::getLogger();
    }

    /**
     *  运行控制台
     * 
     * @global array $argv
     */
    public function run() {

        if (!extension_loaded('swoole')) {
            //die('I need swoole(php-extension)！！！');
        }

        //解析参数
        global $argv;
        $command_args     = array_slice($argv, 1);
        $this->__run_opts = getopt('', ['no-delay']) ?: []; //启动配置项
        foreach ($command_args as $arg) {
            if ($arg[0] === '-') {
                continue;
            }
            $this->__run_args[] = $arg;
        }
        $act = $this->__run_args[0] ?? 'help';

        //动作
        switch ($act) {
            case 'help': //打印帮助信息
                $this->printHelpMessage();
                $this->_logger->flush();
                break;
            case 'start': //开始运行
                $this->start($this->__run_opts);
                break;
            case 'stop': //停止运行
                $this->stop();
                break;
            case 'restart': //重启
                $this->restart();
                break;
            case 'status': //重启
                $this->showStatus();
                break;
            case 'zombie': //杀死僵尸进程
                $this->killZombie();
                break;
            case 'check': //检查配置是否正确
                $this->checkConfig();
                break;
        }
    }

    /**
     * 打印帮助信息
     */
    public function printHelpMessage() {
        $txt = <<<HELP

{#y}Usage:
{##}  command [options] [arguments]

{#y}Options:
{#g}  --no-delay        {##}disable delay jobs

{#y}Available commands:
{#g}  help              {##}displays help message
{#g}  start             {##}start the program
{#g}  stop              {##}stop the program
{#g}  restart           {##}restart the program
{#g}  status            {##}show status
{#g}  zombie            {##}try killing the zombie process
{#g}  check             {##}check the configuration

HELP;
        $rep = [
            '{#y}' => "\033[0;33m", //黄色
            '{#g}' => "\033[0;32m", //绿色
            '{##}' => "\033[0m" // 清空颜色
        ];
        echo strtr($txt, $rep);
    }

    /**
     * 启动进程
     */
    public function start(array $run_opts = []) {
        $this->checkConfig();
        $master_process = new Process();
        if (isset($run_opts['no-delay'])) {
            $master_process->noDelay();
        }
        $master_process->start($run_opts);
    }

    /**
     * 停止进程, 平滑退出
     */
    public function stop($no_close = false) {
        try {
            $master_process = new Process();
            $pid            = $master_process->getMasterInfo('pid');
            if ($no_close) {
                if (!$pid) {
                    return true;
                }
                return \Swoole\Process::kill($pid, 0) ? false : true;
            } else {
                if ($pid) {
                    \Swoole\Process::kill($pid, SIGUSR1);
                    return true;
                }
            }
        } catch (\Exception $ex) {
            Core\Utils::catchError($this->_logger, $ex);
            echo 'stop error' . PHP_EOL;
        }
        return false;
    }

    /**
     * 重启进程
     */
    public function restart() {
        //获取关闭前的配置
        $master_process = new Process();
        $run_opt        = $master_process->getMasterInfo('options') ?: [];
        //重启
        if ($this->stop()) {
            while (!$this->stop(true)) {
                sleep(1);
            }
            $this->start($run_opt);
        }
    }

    /**
     * 检查配置
     * @throws \Exception
     */
    public function checkConfig() {
        try {
            //topic
            $topics_config = Config::getConfig('topics');
            if (empty($topics_config)) {
                throw new \Exception('config<topics> is empty');
            }
            if (!is_array($topics_config)) {
                throw new \Exception('config<topics> must be a array');
            }
            foreach ($topics_config as $topic_info) {
                if (empty($topic_info['name'])) {
                    throw new \Exception('topic\'s name must be a non-empty string');
                }
                if (empty($topic_info['action'])) {
                    throw new \Exception('topic\'s action must be a class name');
                }
            }
            //queue
            $config = Config::getConfig('queue');
            if (empty($config)) {
                throw new \Exception('config<queue> is empty');
            }
            $class = $config['class'];
            if (!class_implements($class)[Core\IFace\IQueueDriver::class]) {
                throw new \Exception("queue driver($class) must implements class(" . Core\IFace\IQueueDriver::class . ')');
            }
        } catch (\Exception $ex) {
            Core\Utils::catchError($this->_logger, $ex);
            echo 'the configuration syntax is error;' . PHP_EOL;
            echo 'error: ' . $ex->getMessage() . PHP_EOL;
            exit();
        } catch (\Throwable $ex) {
            Core\Utils::catchError($this->_logger, $ex);
            echo 'the configuration syntax is error;' . PHP_EOL;
            echo 'error: ' . $ex->getMessage() . PHP_EOL;
            exit();
        }
        echo 'the configuration syntax is OK;' . PHP_EOL;
    }

    /**
     * 杀死僵尸进程
     */
    public function killZombie() {
        $master_process = new Process();
        $master_process->waitWorkers();
    }

    /**
     * 展示状态信息
     */
    public function showStatus() {
        $master_process = new Process();
        $pid            = $master_process->getMasterInfo('pid');
        if (!$pid || !\Swoole\Process::kill($pid, 0)) {
            echo 'program is not running' . PHP_EOL;
            return;
        }
        if (@\Swoole\Process::kill($pid, SIGUSR2)) {
            $dir = Config::getConfig('process', 'data_dir');
            echo 'program status was updated; detail in the file ' . $dir . DIRECTORY_SEPARATOR . 'status.info' . PHP_EOL;
        }
    }

}
