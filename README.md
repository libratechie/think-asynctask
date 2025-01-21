# ThinkPHP Async Task package

`think-asynctask` 是一个基于 Swoole 实现的用于处理异步任务的 Composer 包，通过它可以有效提升应用的性能，并具备强大的任务调度功能。

## 安装

```shell
$ composer require libratechie/think-asynctask
```

## 使用

### 生成 task 任务类

```shell
$ php think make:task FastTask
```

### 配置调整

```php
<?php

return [
    // 进程名称
    'name' => 'tp_daemon',
    // 运行模式
    'daemon' => true,
    // 任务类命名空间
    'namespace' => '\\app\\tasks',
    // 任务列表
    'tasks' => [
        // FastTask：进程名称
        'FastTask' => [
            'enable' => true,   // 是否开启
            'process_num' => 1, // 进程数量
            'warning_time' => 0,    // 超时阈值
            'invoke_count' => 500,  // 单次执行循环次数
            'task_list' => [
                'FastTask' => [ // 引入的具体类名称
                    'invoke',  // 具体任务
                ]
            ]
        ]
    ]
];
```

## 运行命令

### 启动任务

```shell
$ php think task
```

### 查看状态

```shell
$ php think task status
```

### 停止任务

```shell
$ php think task stop
```