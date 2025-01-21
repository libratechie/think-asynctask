<?php

namespace think\task\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Config;
use think\task\Daemon;

class Server extends Command
{
    protected array $config = [];

    protected function configure()
    {
        // 设置命令
        $this->setName('task')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|status", 'start')
            ->addOption('name', 'N', Option::VALUE_REQUIRED, 'swoole process name')
            ->addOption('pid', 'p', Option::VALUE_REQUIRED, 'pid file location')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the swoole server in daemon mode.')
            ->setDescription('Swoole Async Task Service for ThinkPHP');
    }

    protected function execute(Input $input, Output $output): bool
    {
        $action = $input->getArgument('action');

        if (!in_array($action, ['start', 'stop', 'restart', 'status'])) {
            $output->writeln('<error>Invalid argument action: $action, Expected start|stop|restart|status .</error>');
            return false;
        }
        if (!function_exists("getmypid")) {
            $output->writeln('<error>Please install getmypid extension.</error>');
            return false;
        }

        $this->config = Config::get('daemon');

        Daemon::$processName = $this->getProcessName();
        Daemon::$pidFile = $this->getPidFile();
        Daemon::$daemon = $this->getDaemon();
        Daemon::$taskNamespace = empty($this->config['namespace']) ? '\\app\\tasks' : $this->config['namespace'];
        Daemon::$tasks = empty($this->config['tasks']) ? [] : $this->config['tasks'];

        switch ($action) {
            case "start":
                $output->writeln('<info>Starting swoole async task service...</info>');
                Daemon::start();
                break;
            case "stop":
                Daemon::stop();
                break;
            case "restart":
                Daemon::restart();
                break;
            case "status":
                $this->getProcessStatus($output);
                break;
        }
        return true;
    }

    protected function getProcessName()
    {
        if ($this->input->hasOption('name')) {
            $name = $this->input->getOption('name');
        } else {
            $name = !empty($this->config['name']) ? $this->config['name'] : 'tp_daemon';
        }

        return $name;
    }

    protected function getPidFile()
    {
        if ($this->input->hasOption('pid')) {
            $pid_file = $this->input->getOption('pid');
        } else {
            $pid_file = !empty($this->config['pid']) ? $this->config['pid'] : '/tmp/' . Daemon::$processName . "_pid";
        }
        return $pid_file;
    }

    protected function getDaemon()
    {
        if ($this->input->hasOption('daemon')) {
            $name = $this->input->getOption('daemon');
        } else {
            $name = !empty($this->config['daemon']) ? $this->config['daemon'] : false;
        }

        return $name;
    }

    protected function getProcessStatus($output): bool
    {
        $status = Daemon::status();
        if ($status) {
            $output && $output->writeln('<info>Process is running.</info>');
        } else {
            $output && $output->writeln('Process is not running.');
        }

        // 检查 shell_exec 是否可用
        if (!function_exists('shell_exec')) {
            $output && $output->writeln('<warning>shell_exec is disabled in the current PHP configuration.</warning>');
        } else {
            // 构建命令并执行
            $command = "ps aux | grep " . escapeshellarg(Daemon::$processName) . " | grep -v grep";
            $command_output = shell_exec($command);
            if ($command_output) {
                $output && $output->writeln($command_output);
            }
        }
        return $status;
    }
}