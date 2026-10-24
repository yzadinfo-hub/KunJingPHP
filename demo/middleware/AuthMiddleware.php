<?php
/**
 * KunJingPHP demo 中间件（验证洋葱管道）
 * 请求头 X-Token=secret 才放行，否则返回 401。
 */

namespace App\Middleware;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;

class AuthMiddleware
{
    public function handle($request, $next)
    {
        if ($request instanceof Request && $request->header('X-Token') !== 'secret') {
            return new Response('未授权', 401, [], 'raw');
        }
        return $next($request);
    }
}
