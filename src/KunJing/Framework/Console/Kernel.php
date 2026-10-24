<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：命令行内核（加载项目配置 + 注册内置命令）
 *
 * 用法（项目根目录执行）：
 *  php /path/to/kunjingphp/kunjing migrate
 */

namespace KunJing\Framework\Console;

use KunJing\Framework\Config\Repository;
use KunJing\Framework\Console\Commands\DbSeedCommand;
use KunJing\Framework\Console\Commands\EnvCheckCommand;
use KunJing\Framework\Console\Commands\MakeControllerCommand;
use KunJing\Framework\Console\Commands\MakeMiddlewareCommand;
use KunJing\Framework\Console\Commands\MakeMigrationCommand;
use KunJing\Framework\Console\Commands\MakeModelCommand;
use KunJing\Framework\Console\Commands\MakeRequestCommand;
use KunJing\Framework\Console\Commands\MakeSeederCommand;
use KunJing\Framework\Console\Commands\MigrateCommand;
use KunJing\Framework\Console\Commands\MigrateRollbackCommand;
use KunJing\Framework\Console\Commands\MigrateStatusCommand;
use KunJing\Framework\Console\Commands\RouteCacheCommand;
use KunJing\Framework\Console\Commands\RouteClearCommand;
use KunJing\Framework\Console\Commands\RouteListCommand;
use KunJing\Framework\Console\Commands\VersionCommand;
use KunJing\Framework\Database\ConnectionManager;
use KunJing\Framework\Database\Manager as DbManager;
use KunJing\Framework\Routing\Router;

class Kernel
{
    public const VERSION = '1.1.0';

    protected string $basePath;
    protected Repository $config;
    protected Router $router;
    protected ?DbManager $db = null;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? (getenv('KUNJING_BASE') ?: getcwd() ?: '.'), '/\\');
        $this->config = $this->loadConfig();
        $this->router = new Router();
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function config(): Repository
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    /**
     * 加载项目配置：优先 config/ 目录（每段一个文件），回退到 config.php。
     */
    protected function loadConfig(): Repository
    {
        $repository = new Repository();
        $dir = $this->basePath . '/config';
        if (is_dir($dir)) {
            $files = glob($dir . '/*.php') ?: [];
            foreach ($files as $file) {
                $key = basename($file, '.php');
                $data = require $file;
                if (is_array($data)) {
                    $repository->set($key, $data);
                }
            }
        } elseif (is_file($this->basePath . '/config.php')) {
            $data = require $this->basePath . '/config.php';
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $repository->set((string) $key, $value);
                }
            }
        }
        return $repository;
    }

    /**
     * 加载项目路由文件（routes.php），供 route:* 命令使用。
     */
    public function loadRoutes(): Router
    {
        $file = $this->config->get('app.route_file', $this->basePath . '/routes.php');
        if (is_string($file) && is_file($file)) {
            $router = $this->router;
            $result = require $file;
            if (is_callable($result)) {
                $result($router);
            }
        }
        return $this->router;
    }

    /**
     * 获取数据库管理器（懒连接）。
     */
    public function db(): DbManager
    {
        if ($this->db === null) {
            $manager = new ConnectionManager();
            $manager->setConfigs((array) $this->config->get('db.connections', []));
            $this->db = new DbManager($manager);
            $this->db->setDefaultConnection((string) $this->config->get('db.default', 'default'));
            DbManager::setInstance($this->db);
        }
        return $this->db;
    }

    /**
     * 注册内置命令。
     */
    public function registerCommands(Application $app): void
    {
        $base = $this->basePath;
        $config = $this->config;

        $app->add(new VersionCommand(self::VERSION));
        $app->add(new EnvCheckCommand());

        $app->add(new RouteListCommand($base, $config, $this));
        $app->add(new RouteCacheCommand($base, $config, $this));
        $app->add(new RouteClearCommand($base, $config, $this));

        $app->add(new MakeControllerCommand($base, $config));
        $app->add(new MakeModelCommand($base, $config));
        $app->add(new MakeMiddlewareCommand($base, $config));
        $app->add(new MakeRequestCommand($base, $config));
        $app->add(new MakeMigrationCommand($base, $config));
        $app->add(new MakeSeederCommand($base, $config));

        $app->add(new MigrateCommand($base, $config, $this));
        $app->add(new MigrateRollbackCommand($base, $config, $this));
        $app->add(new MigrateStatusCommand($base, $config, $this));
        $app->add(new DbSeedCommand($base, $config, $this));
    }

    /**
     * @param array<int,string> $argv
     */
    public function handle(array $argv): int
    {
        $app = new Application('堃景PHP KunJingPHP', self::VERSION);
        $this->registerCommands($app);

        // 项目自定义命令
        $file = $this->basePath . '/console_commands.php';
        if (is_file($file)) {
            $register = require $file;
            if (is_callable($register)) {
                $register($app);
            }
        }

        return $app->run($argv);
    }
}
