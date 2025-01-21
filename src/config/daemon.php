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