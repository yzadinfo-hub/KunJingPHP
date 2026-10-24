<?php
/**
 * 组梦ERP 迁移样例 — 登录鉴权中间件
 * 未登录则跳转到登录页。
 */

namespace Erp\Middleware;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Facade\Session;

class AuthMiddleware
{
    public function handle($request, $next)
    {
        $user = Session::get('user');
        if ($user === null) {
            return Response::redirect('/login');
        }
        return $next($request);
    }
}
