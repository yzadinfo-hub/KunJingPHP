<?php
/**
 * Phase 6 测试夹具：鉴权中间件
 */

namespace P6;

use KunJing\Framework\Http\Response;

class AuthMiddleware
{
    public function handle($request, $next)
    {
        if ($request->header('X-Auth') !== '1') {
            return Response::json(['code' => 401, 'message' => '未授权'], 401);
        }
        return $next($request);
    }
}
