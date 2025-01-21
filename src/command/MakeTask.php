<?php

namespace think\task\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class MakeTask extends Command
{
    protected function configure()
    {
        // 设置命令的名称
        $this->setName('make:task')
            ->setDescription('Create a new task class in app/tasks')
            ->addArgument('name', Argument::REQUIRED, 'Task class name');
    }

    protected function execute(Input $input, Output $output)
    {
        // 获取传入的类名
        $taskName = $input->getArgument('name');

        if (empty($taskName)) {
            $output->writeln('<error>The task class name is required!</error>');
            return;
        }

        // 设置任务类的文件路径
        $taskFilePath = app_path('tasks') . DIRECTORY_SEPARATOR . $taskName . '.php';
        $taskDirPath = app_path('tasks'); // 获取任务文件夹路径

        // 检查文件夹是否存在，不存在则创建
        if (!is_dir($taskDirPath)) {
            // 尝试创建目录，设置递归模式和权限
            if (!mkdir($taskDirPath, 0777, true)) {
                $output->writeln("<error>Failed to create the directory '$taskDirPath'.</error>");
                return;
            }
        }

        // 检查文件是否已经存在
        if (file_exists($taskFilePath)) {
            $output->writeln("<error>The task class '$taskName' already exists!</error>");
            return;
        }

        // 创建任务类的模板内容
        $taskClassContent = "<?php\n\nnamespace app\\tasks;\n\nclass $taskName\n{\n    public function invoke()\n    {\n        // TODO: Implement the task logic here.\n    }\n}\n";

        // 将内容写入文件
        if (file_put_contents($taskFilePath, $taskClassContent) !== false) {
            $output->writeln("<info>Task class '$taskName' has been created successfully in app/tasks!</info>");
        } else {
            $output->writeln("<error>Failed to create task class '$taskName'.</error>");
        }
    }
}