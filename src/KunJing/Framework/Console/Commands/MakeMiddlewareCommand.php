<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成中间件 make:middleware
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;

class MakeMiddlewareCommand extends BaseCommand
{
    protected string $name = 'make:middleware';
    protected string $description = '生成中间件';

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? '');
        if ($raw === '') {
            $this->error('请提供中间件名称：php kunjing make:middleware Auth');
            return 1;
        }

        $class = $this->studly($raw);
        if (!str_ends_with($class, 'Middleware')) {
            $class .= 'Middleware';
        }

        $dir = $this->pathFor('middleware', 'app/Middleware');
        $namespace = $this->namespace() . '\\Middleware';
        $file = $dir . '/' . $class . '.php';

        $content = <<<PHP
<?php

namespace {$namespace};

use KunJing\Framework\Http\Response;

class {$class}
{
    /**
     * @param \KunJing\Framework\Http\Request \$request
     * @param \Closure \$next
     */
    public function handle(\$request, \$next)
    {
        // 放行：return \$next(\$request);
        // 拦截：return Response::json(['code' => 403, 'message' => '禁止访问'], 403);

        return \$next(\$request);
    }
}

PHP;

        return $this->writeFile($file, $content, $this->force()) ? 0 : 1;
    }
}
