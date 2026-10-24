<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：应用核心（引导启动 + 请求分发，净室实现）
 */

namespace KunJing\Framework;

use KunJing\Framework\Container\Container;
use KunJing\Framework\Config\Repository;
use KunJing\Framework\Routing\Router;
use KunJing\Framework\Routing\Route;
use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Http\Middleware\Pipeline;
use KunJing\Framework\Facade\Facade;
use KunJing\Framework\Exception\HttpException;
use KunJing\Framework\Exception\FrameworkException;
use KunJing\Framework\Exception\ValidateException;
use KunJing\Framework\Validate\FormRequest;
use KunJing\Framework\Database\ConnectionManager;
use KunJing\Framework\Database\Manager as DbManager;
use KunJing\Framework\Cache\CacheManager;
use KunJing\Framework\Log\Logger;
use KunJing\Framework\Validate\Validator;
use KunJing\Framework\Event\Dispatcher;
use KunJing\Framework\Session\SessionManager;
use KunJing\Framework\View\Engine;

class App
{
    protected Container $container;
    protected Router $router;
    protected Repository $config;
    protected string $basePath;
    protected bool $runDone = false;

    /** 是否已完成一次 run()（供多入口防重守卫使用） */
    public function isRunDone(): bool
    {
        return $this->runDone;
    }

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->container = Container::getInstance();
        $this->config = new Repository();
        $this->router = new Router();

        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Repository::class, $this->config);
        $this->container->instance(Router::class, $this->router);

        Facade::setContainer($this->container);
        $this->registerCoreServices();
    }

    /**
     * 注册数据层与服务组件到容器（懒加载单例）。
     */
    protected function registerCoreServices(): void
    {
        $config = $this->config;

        // 数据库
        $this->container->singleton(DbManager::class, function () use ($config) {
            $connections = new ConnectionManager();
            $connections->setConfigs($config->get('db.connections', []));
            $manager = new DbManager($connections);
            $manager->setDefaultConnection($config->get('db.default', 'default'));
            // 模型层通过静态单例访问，保持一致
            DbManager::setInstance($manager);
            return $manager;
        });

        // 缓存
        $this->container->singleton(CacheManager::class, function () use ($config) {
            return new CacheManager($config->get('cache', []));
        });

        // 日志
        $this->container->singleton(Logger::class, function () use ($config) {
            return new Logger($config->get('log', []));
        });

        // 验证器（实例中的 make/check 每次返回新 Validator）
        $this->container->singleton(Validator::class, function () {
            return new Validator();
        });

        // 事件
        $this->container->singleton(Dispatcher::class, function () {
            return new Dispatcher();
        });

        // 会话
        $this->container->singleton(SessionManager::class, function () use ($config) {
            return new SessionManager($config->get('session', []));
        });

        // 视图
        $this->container->singleton(Engine::class, function () use ($config) {
            $cfg = $config->get('view', []);
            $cfg['base_path'] = $cfg['base_path'] ?? $this->basePath;
            return new Engine($cfg);
        });
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Repository
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * 捕获请求 → 匹配路由 → 过中间件 → 调度动作 → 发送响应。
     */
    public function run(): void
    {
        if ($this->runDone) {
            return; // 守卫：同一 App 实例只允许 run 一次，避免多入口双重输出
        }
        $this->runDone = true;
        try {
            // 提前初始化数据库等核心服务，确保模型静态单例使用已注入配置的连接
            $this->container->make(DbManager::class);

            $request = Request::capture();
            $this->container->instance(Request::class, $request);

            // 启动会话（自定义文件驱动），确保登录态可在多次请求间保持
            $session = $this->container->make(SessionManager::class);
            $session->start();

            $route = $this->router->dispatch($request);
            if ($route === null) {
                throw new HttpException(404, '未找到匹配路由：' . $request->method() . ' ' . $request->path());
            }

            $core = function ($req) use ($route) {
                return $this->callAction($route, $req);
            };

            $middlewares = $route->getMiddleware();
            if (empty($middlewares)) {
                $response = $core($request);
            } else {
                $pipeline = new Pipeline();
                $response = $pipeline->send($request)->through($middlewares)->then($core);
            }

            if (!$response instanceof Response) {
                $response = Response::make((string) $response);
            }
            // 下发会话 Cookie 并持久化，保证登录态跨请求有效
            $session->issueCookie();
            $session->save();
            $response->send();
        } catch (ValidateException $e) {
            $this->renderValidateError($e, $request ?? null);
        } catch (HttpException $e) {
            $this->renderError($e->getStatusCode(), $e->getMessage(), $request ?? null);
        } catch (FrameworkException $e) {
            $this->renderError(500, $e->getMessage(), $request ?? null);
        } catch (\Throwable $e) {
            $message = $this->config->get('app.debug', false) ? $e->getMessage() : '服务器内部错误';
            $this->renderError(500, $message, $request ?? null);
        }
    }

    /**
     * 表单校验失败的响应：JSON 请求返回 422 JSON，否则渲染错误页。
     */
    protected function renderValidateError(ValidateException $e, ?Request $request): void
    {
        $wantsJson = $request !== null && $request->wantsJson();
        if ($wantsJson) {
            Response::json([
                'code'    => 422,
                'message' => $e->getMessage(),
                'errors'  => $e->getErrors(),
            ], 422)->send();
            return;
        }

        $items = '';
        foreach ($e->getErrors() as $field => $message) {
            $items .= '<li>' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '：'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        $html = '<h1>422 表单校验未通过</h1><p>'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><ul>' . $items . '</ul>';
        (new Response($html, 422, [], 'html'))->send();
    }

    /**
     * 执行路由动作（闭包 / [类, 方法] / 类@方法），返回 Response。
     */
    protected function callAction(Route $route, Request $request): Response
    {
        $action = $route->getAction();

        // 数组形式：[Controller::class, 'method']
        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            if (is_string($class)) {
                if (strpos($class, '\\') === false && ($ns = $route->getNamespace())) {
                    $class = rtrim($ns, '\\') . '\\' . $class;
                }
                if (!class_exists($class)) {
                    throw new HttpException(500, "控制器类 [{$class}] 不存在。");
                }
                $controller = $this->container->make($class);
                if (!method_exists($controller, $method)) {
                    throw new HttpException(500, "控制器方法 [{$class}::{$method}] 不存在。");
                }
                $params = $this->buildActionParameters([$controller, $method], $route, $request);
                $result = $this->container->call([$controller, $method], $params);
                return $this->toResponse($result);
            }
        }

        if (is_callable($action)) {
            $params = $this->buildActionParameters($action, $route, $request);
            $result = $this->container->call($action, $params);
            return $this->toResponse($result);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);
            if (($ns = $route->getNamespace())) {
                $class = rtrim($ns, '\\') . '\\' . $class;
            }
            if (!class_exists($class)) {
                throw new HttpException(500, "控制器类 [{$class}] 不存在。");
            }
            $controller = $this->container->make($class);
            if (!method_exists($controller, $method)) {
                throw new HttpException(500, "控制器方法 [{$class}::{$method}] 不存在。");
            }
            $params = $this->buildActionParameters([$controller, $method], $route, $request);
            $result = $this->container->call([$controller, $method], $params);
            return $this->toResponse($result);
        }

        throw new HttpException(500, '不支持的路由动作类型。');
    }

    /**
     * 构造动作参数：路由参数 + Request + 自动校验的 FormRequest。
     *
     * @param callable|array $callable
     * @return array<string,mixed>
     */
    protected function buildActionParameters($callable, Route $route, Request $request): array
    {
        // 路由参数（如 {id}）优先，其次 Request 实例
        $params = array_merge($route->getParams(), ['request' => $request]);

        try {
            if (is_array($callable) && count($callable) === 2) {
                $reflector = new \ReflectionMethod($callable[0], $callable[1]);
            } elseif (is_string($callable) || $callable instanceof \Closure) {
                $reflector = new \ReflectionFunction(\Closure::fromCallable($callable));
            } else {
                return $params;
            }
        } catch (\Throwable $e) {
            return $params;
        }

        foreach ($reflector->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $class = $type->getName();
            if (is_subclass_of($class, FormRequest::class)) {
                $params[$parameter->getName()] = $class::createFrom($request);
            }
        }

        return $params;
    }

    /**
     * @param mixed $result
     */
    protected function toResponse($result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }
        return Response::make((string) $result);
    }

    protected function renderError(int $status, string $message, ?Request $request = null): void
    {
        if ($request !== null && $request->wantsJson()) {
            Response::json([
                'code'    => $status,
                'message' => $this->config->get('app.debug', false) ? $message : $this->defaultErrorMessage($status),
            ], $status)->send();
            return;
        }

        if ($this->config->get('app.debug', false)) {
            $html = '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            $html = '<h1>' . $status . '</h1><p>' . $this->defaultErrorMessage($status) . '</p>';
        }
        (new Response($html, $status, [], 'html'))->send();
    }

    protected function defaultErrorMessage(int $status): string
    {
        return match ($status) {
            404     => '页面不存在',
            403     => '没有访问权限',
            422     => '表单校验未通过',
            429     => '请求过于频繁',
            default => '服务器错误',
        };
    }
}
