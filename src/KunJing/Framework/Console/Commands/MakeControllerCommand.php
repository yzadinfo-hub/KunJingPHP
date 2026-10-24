<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成控制器 make:controller
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;

class MakeControllerCommand extends BaseCommand
{
    protected string $name = 'make:controller';
    protected string $description = '生成控制器（--resource 生成 CRUD 骨架）';

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? '');
        if ($raw === '') {
            $this->error('请提供控制器名称：php kunjing make:controller Supplier');
            return 1;
        }

        $class = $this->studly($raw);
        if (!str_ends_with($class, 'Controller')) {
            $class .= 'Controller';
        }

        $dir = $this->pathFor('controllers', 'app/Controllers');
        $namespace = $this->namespace() . '\\Controllers';
        $file = $dir . '/' . $class . '.php';

        $content = $this->option('resource') === true || $this->option('resource') === 'true'
            ? $this->resourceStub($namespace, $class)
            : $this->plainStub($namespace, $class);

        return $this->writeFile($file, $content, $this->force()) ? 0 : 1;
    }

    protected function plainStub(string $namespace, string $class): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;

class {$class}
{
    public function index(Request \$request)
    {
        return Response::html('<h1>{$class}::index</h1>');
    }
}

PHP;
    }

    protected function resourceStub(string $namespace, string $class): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;

class {$class}
{
    public function index(Request \$request)
    {
        return Response::json(['code' => 0, 'message' => 'ok', 'data' => []]);
    }

    public function create(Request \$request)
    {
        return Response::html('<h1>新增</h1>');
    }

    public function store(Request \$request)
    {
        return Response::json(['code' => 0, 'message' => 'created']);
    }

    public function show(Request \$request, \$id)
    {
        return Response::json(['code' => 0, 'data' => ['id' => \$id]]);
    }

    public function edit(Request \$request, \$id)
    {
        return Response::html('<h1>编辑 ' . \$id . '</h1>');
    }

    public function update(Request \$request, \$id)
    {
        return Response::json(['code' => 0, 'message' => 'updated', 'id' => \$id]);
    }

    public function destroy(Request \$request, \$id)
    {
        return Response::json(['code' => 0, 'message' => 'deleted', 'id' => \$id]);
    }
}

PHP;
    }
}
