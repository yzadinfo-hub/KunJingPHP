<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：访问限流中间件（净室实现，基于缓存计数）
 *
 * 用法：
 *  路由级（实例方式，可自定义阈值）：
 *      $route->middleware(new ThrottleMiddleware(10, 1));   // 1 分钟 10 次
 *  全局/分组（类名方式，取默认配置 throttle.limit / throttle.minutes）：
 *      $router->group(['middleware' => [ThrottleMiddleware::class]], ...)
 */

namespace KunJing\Framework\Http\Middleware;

use KunJing\Framework\Config\Repository;
use KunJing\Framework\Container\Container;
use KunJing\Framework\Exception\HttpException;
use KunJing\Framework\Facade\Cache;
use KunJing\Framework\Http\Response;

class ThrottleMiddleware
{
    protected int $limit;
    protected int $minutes;

    /** 默认阈值（类名方式注册时使用） */
    protected static int $defaultLimit = 60;
    protected static int $defaultMinutes = 1;

    public function __construct(?int $limit = null, ?int $minutes = null)
    {
        $this->limit = $limit ?? self::$defaultLimit;
        $this->minutes = $minutes ?? self::$defaultMinutes;
        $this->loadDefaults();
    }

    protected function loadDefaults(): void
    {
        self::$defaultLimit = (int) $this->conf('limit', self::$defaultLimit);
        self::$defaultMinutes = (int) $this->conf('minutes', self::$defaultMinutes);
    }

    public static function configure(int $limit, int $minutes): void
    {
        self::$defaultLimit = $limit;
        self::$defaultMinutes = $minutes;
    }

    public function handle($request, $next)
    {
        $key = 'throttle:' . md5($request->ip() . '|' . $request->path());
        $ttl = max(1, $this->minutes) * 60;

        $current = (int) Cache::get($key, 0);
        if ($current >= $this->limit) {
            $response = $this->tooManyRequests();
            if ($response !== null) {
                return $response;
            }
            throw new HttpException(429, '请求过于频繁，请稍后再试。');
        }

        if ($current === 0) {
            Cache::set($key, 1, $ttl);
        } else {
            Cache::increment($key);
        }

        return $next($request);
    }

    protected function tooManyRequests(): ?Response
    {
        try {
            $repository = Container::getInstance()->make(Repository::class);
        } catch (\Throwable $e) {
            return null;
        }
        if ($repository->get('throttle.as_json', true)) {
            return Response::json([
                'code'    => 429,
                'message' => '请求过于频繁，请稍后再试。',
            ], 429)->setHeader('Retry-After', (string) ($this->minutes * 60));
        }
        return null;
    }

    protected function conf(string $key, $default = null)
    {
        try {
            $repository = Container::getInstance()->make(Repository::class);
            return $repository->get('throttle.' . $key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
