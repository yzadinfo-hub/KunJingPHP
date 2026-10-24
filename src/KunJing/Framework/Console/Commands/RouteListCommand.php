<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：路由列表命令 route:list
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;
use KunJing\Framework\Console\Kernel;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Routing\Route;

class RouteListCommand extends BaseCommand
{
    protected string $name = 'route:list';
    protected string $description = '列出全部已注册路由';

    protected ?Kernel $kernel = null;

    public function __construct(string $basePath, Repository $config, ?Kernel $kernel = null)
    {
        parent::__construct($basePath, $config);
        $this->kernel = $kernel;
    }

    protected function handle(): int
    {
        $router = $this->kernel !== null ? $this->kernel->loadRoutes() : null;
        $routes = $router !== null ? $router->getRoutes() : [];

        if ($routes === []) {
            $this->warning('没有注册任何路由（检查 routes.php 是否存在）。');
            return 0;
        }

        $keyword = $this->argument(0);
        $rows = [];
        foreach ($routes as $route) {
            /** @var Route $route */
            $uri = $route->getUri();
            $action = $this->formatAction($route->getAction());
            if (is_string($keyword) && $keyword !== ''
                && !str_contains(strtolower($uri . ' ' . $action), strtolower($keyword))) {
                continue;
            }
            $rows[] = [
                '方法'   => $route->getMethod(),
                'URI'  => $uri,
                '动作'   => $action,
                '中间件'  => $this->formatMiddleware($route->getMiddleware()),
                '名称'   => (string) ($route->getName() ?? ''),
            ];
        }

        $this->output()->table($rows);
        $this->line('共 ' . count($rows) . ' 条路由。');
        return 0;
    }

    protected function formatAction($action): string
    {
        if (is_array($action) && count($action) === 2) {
            $class = is_string($action[0]) ? $action[0] : get_class($action[0]);
            return $class . '@' . $action[1];
        }
        if (is_string($action)) {
            return $action;
        }
        if ($action instanceof \Closure) {
            return 'Closure';
        }
        return is_object($action) ? get_class($action) : '(mixed)';
    }

    /** @param array<int,mixed> $middleware */
    protected function formatMiddleware(array $middleware): string
    {
        return implode(', ', array_map(
            fn($item) => is_string($item) ? $item : get_class($item),
            $middleware
        ));
    }
}
