<?php

namespace think\task;

use think\facade\Log;
use Swoole\Process;
use Swoole\Process\Manager;

class Daemon
{
    static public string $processName;  // 进程名称
    static public string $pidFile;    //pid文件位置
    static public bool $daemon = false;  // 运行模式
    static public int $pid; //pid
    static public string $taskNamespace;    // 任务类命名空间
    static public array $tasks = [];    // 任务列表

    public static function start(): void
    {
        if (file_exists(self::$pidFile)) {
            die('Pid file ' . self::$pidFile . ' already exists!' . "\n");
        }
        if (self::$daemon) {
            // 使当前进程蜕变为一个守护进程
            Process::daemon();
        }
        // 设置父进程名称
        swoole_set_process_name(self::$processName);
        self::startTasks();
    }

    public static function stop(): void
    {
        $pid = @file_get_contents(self::$pidFile);
        if ($pid) {
            @unlink(self::$pidFile);
            if (Process::kill($pid, 0)) {
                Process::kill($pid, SIGTERM);
                Log::record('进程已结束，PID#' . $pid);
            } else {
                Log::record('进程不存在，PID#' . $pid);
            }
        } else {
            Log::record("需要停止的进程未启动");
        }
    }

    public static function restart(): void
    {
        self::stop();
        sleep(5);
        self::start();
    }

    public static function status(): bool
    {
        $pid = @file_get_contents(self::$pidFile);
        if ($pid && posix_kill($pid, 0)) {
            return true; // 进程存在
        } else {
            return false; // 进程不存在
        }
    }

    public static function startTasks(): void
    {
        // 当前进程的pid
        self::getPid();
        // 写入当前进程的pid到pid文件
        self::writePid();
        // 记录日志
        Log::record("启动成功，PID#" . self::$pid);
        // 启动工作进程
        $pm = new Manager();
        foreach (self::$tasks as $taskName => $conf) {
            // 判断进程数量和是否开启
            if (!isset($conf['process_num']) || !$conf['enable']) {
                continue;
            }
            // 组装进程信息，将信息写入到进程中
            $data = array(
                'worker_name' => self::$processName . "_worker_" . $taskName,
                'task_namespace' => self::$taskNamespace,
                'task_list' => $conf['task_list'],
                'master_pid' => self::$pid,
                'warning_time' => $conf['warning_time'] ?? 0,
                'invoke_count' => $conf['invoke_count'] ?? 0,
            );
            $pm->addBatch($conf['process_num'], function () use ($data) {
                // 运行进程
                (new DaemonWorker($data))->run();
            });
        }
        $pm->start();
    }

    /**
     * 当前进程的pid
     * @return void
     */
    private static function getPid(): void
    {
        if (!function_exists("getmypid")) {
            die('Please install getmypid extension.');
        }
        // 得到当前 Worker 进程的操作系统进程 ID
        self::$pid = getmypid();
    }

    /**
     * 写入当前进程的pid到pid文件
     * @return void
     */
    private static function writePid(): void
    {
        file_put_contents(self::$pidFile, self::$pid);
    }
}