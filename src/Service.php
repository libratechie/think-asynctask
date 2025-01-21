<?php

namespace think\task;

use think\Service as BaseService;

class Service extends BaseService
{
    public function register()
    {
        $this->commands([
            'task' => '\\think\\task\\command\\Server',
            'make:task' => '\\think\\task\\command\\MakeTask'
        ]);
    }
}