<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：CSRF 防护中间件（净室实现）
 *
 * 用法：
 *  路由：$router->post('/supplier', ...)->middleware(CsrfMiddleware::class);
 *  模板：<form method="post">{Csrf::field()} ...</form>  或 {$csrf_field|raw}
 *  AJAX：请求头带 X-Csrf-Token
 */

namespace KunJing\Framework\Http\Middleware;

use KunJing\Framework\Exception\HttpException;
use KunJing\Framework\Facade\Session;

class CsrfMiddleware
{
    public const TOKEN_KEY = '_csrf_token';

    /** @var array<int,string> 跳过校验的 URI（支持 * 通配） */
    protected array $except = [];

    /** @param array<int,string> $except */
    public function __construct(array $except = [])
    {
        $this->except = $except;
    }

    public function handle($request, $next)
    {
        $method = strtoupper($request->method());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        if ($this->shouldSkip($request->path())) {
            return $next($request);
        }

        $token = $request->input('_token');
        if ($token === null || $token === '') {
            $token = $request->header('X-Csrf-Token') ?? $request->header('X-CSRF-TOKEN');
        }

        $sessionToken = Session::get(self::TOKEN_KEY);
        if (!is_string($sessionToken) || $sessionToken === ''
            || !is_string($token) || !hash_equals($sessionToken, $token)) {
            throw new HttpException(403, 'CSRF 令牌校验失败，请刷新页面后重试。');
        }

        return $next($request);
    }

    protected function shouldSkip(string $path): bool
    {
        foreach ($this->except as $pattern) {
            if ($pattern === $path) {
                return true;
            }
            if (str_contains($pattern, '*') && fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取当前令牌（不存在则生成并写入会话）。
     */
    public static function token(): string
    {
        $token = Session::get(self::TOKEN_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(16));
            Session::set(self::TOKEN_KEY, $token);
        }
        return $token;
    }

    /**
     * 隐藏域 HTML。
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    /**
     * 便于模板直接输出：['csrf_token' => ..., 'csrf_field' => ...]
     * @return array<string,string>
     */
    public static function share(): array
    {
        return ['csrf_token' => self::token(), 'csrf_field' => self::field()];
    }
}
