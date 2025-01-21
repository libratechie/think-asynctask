<?php

namespace think\task;

use think\facade\Log;
use think\Exception;
use Swoole\Process;

class DaemonWorker
{
    private array $taskInstance = [];   // 任务实例
    private float $startTime; // 进程启动时间
    private string $workerName = '';    // 进程名称
    private int $masterPid = 0; // 父进程PID
    private int $pid;  // 当前进程PID
    private int $warningTime = 0;  // 超时阈值
    private int $invokeCount = 500;   // 任务执行次数
    private float $taskAvgExecTime = 0;  // 任务平均执行时间
    private array $actionAvgExecTime = [];  // 任务方法平均执行时间
    private string $taskNamespace = '\\app\\tasks';   // 任务类命名空间
    private array $taskList = [];   // 任务列表

    public function __construct(array $workerData)
    {
        $this->startTime = microtime(true);     // 进程启动时间
        $this->pid = getmypid();   // 当前进程PID
        if (!empty($workerData['worker_name'])) {
            swoole_set_process_name($workerData['worker_name']);
            $this->workerName = $workerData['worker_name'];   // 进程名称
        }
        if (!empty($workerData['master_pid'])) {
            $this->masterPid = $workerData['master_pid'];   // 父进程PID
        }
        if (!empty($workerData['warning_time'])) {
            $this->warningTime = $workerData['warning_time'];   // 单次执行循环次数
        }
        if (!empty($workerData['invoke_count'])) {
            $this->invokeCount = $workerData['invoke_count'];   // 单次执行循环次数
        }
        if (!empty($workerData['task_namespace'])) {
            $this->taskNamespace = str_ends_with($workerData['task_namespace'], '\\') ? $workerData['task_namespace'] : $workerData['task_namespace'].'\\';   // 任务类命名空间
        }
        if (!empty($workerData['task_list'])) {
            $this->taskList = $workerData['task_list'];   // 任务列表
        }
    }

    public function run(): bool
    {
        if (empty($this->taskList)) {
            // 没有任何任务的进程，空进程
            return false;
        }
        // 循环执行任务类
        $execTimes = 1;
        while (true) {
            // 当前Unix 时间戳的微秒数
            $taskExecStartTime = microtime(true);
            $taskActionExecTime = [];
            foreach ($this->taskList as $taskName => $taskActionList) {
                if (!array_key_exists($taskName, $this->taskInstance)) {
                    $this->taskInstance[$taskName] = $this->getTask($taskName); // 初始化任务类
                }
                // 循环执行任务方法
                foreach ($taskActionList as $action) {
                    $this->checkExit(); // 检测是否应该退出进程
                    $actionStartTime = microtime(true);
                    try {
                        if (!is_callable([$this->taskInstance[$taskName], $action])) {
                            throw new Exception($taskName . '/' . $action . "无法调用");
                        }
                        $this->taskInstance[$taskName]->$action();
                    } catch (\Exception $e) {
                        Log::error('进程方法异常：' . $e->getMessage());
                    }
                    // 记录任务执行时间
                    $actionEndTime  = microtime(true);
                    $actionExecTime = $actionEndTime - $actionStartTime;
                    $logTime = [
                        'action_start_time' => $actionStartTime,
                        'action_end_time'   => $actionEndTime,
                        'action_exec_time'  => $actionExecTime,
                    ];
                    $taskActionExecTime[$taskName][$action] = $logTime;
                    //计算方法平均执行时间
                    $this->actionAvgExecTime[$taskName][$action] = (floatval($this->actionAvgExecTime[$taskName][$action]??0) * ($execTimes - 1) + $actionExecTime) / $execTimes;
                    sleep(1);
                }
            }

            // 执行时间
            $taskExecTime = microtime(true) - $taskExecStartTime;
            if (!empty($this->warningTime) && $this->warningTime < $taskExecTime) {
                // 记录当前工作任务日志标题
                $workerLogTitle = 'Process Name:' . $this->workerName . ';Process Pid:' . $this->pid . ';Parent ID:' . $this->masterPid . ';Num Of Process Exec:'.$execTimes.';Time Of Task Exec:'.$taskExecTime;
                Log::record($workerLogTitle);
                // 超时警告：单次执行时间超过指定阈值
                foreach ($taskActionExecTime as $taskName => $logTime) {
                    Log::record($taskName . 'Task Exec Time：' . json_encode($logTime));
                }
                Log::critical("OVER WARNING TIME");
                // 立马提交日志，并清空日志，避免内存溢出
                Log::save();
                Log::close();
            }

            // 记录任务平均执行时间
            $this->taskAvgExecTime = ($this->taskAvgExecTime * ($execTimes - 1) + $taskExecTime) / $execTimes;
            // 添加&扣除执行次数
            $execTimes++;
            $this->invokeCount--;
        }
    }

    private function getTask($taskName)
    {
        $className = $this->taskNamespace.$taskName;
        if (class_exists($className)) {
            // 创建类的实例
            return new $className();
        } else {
            return null;
        }
    }

    private function checkExit(): void
    {
        $processExit = false;
        $processExitMsg = '';
        if ($this->invokeCount <= 0) {
            $processExit = true;
            $processExitMsg = 'Process Exit Msg 执行次数完成，主动退出；';
        }
        if (!Process::kill($this->masterPid, 0)) {
            $processExit = true;
            $processExitMsg = 'Process Exit Msg 当前进程父进程ID #'.$this->masterPid.' 不存在，退出进程';
        }
        if ($processExit) {
            // 停止当前进程
            Log::record($processExitMsg);
            // 计算当前进程执行时间
            $daemonWorkerEndTime  = microtime(true);
            $daemonWorkerExecTime = $daemonWorkerEndTime - $this->startTime;
            Log::record('Process Exit Msg 进程开始时间：' . $this->startTime . ';结束时间:' . $daemonWorkerEndTime . ';运行总时长：' . $daemonWorkerExecTime);
            Log::record('Process Exit Msg 方法平均执行时间：' . json_encode($this->actionAvgExecTime));
            Log::record('Process Exit Msg 脚本平均执行时间：' . $this->taskAvgExecTime);
            Log::save();
            Log::close();
            exit;
        }
    }
}