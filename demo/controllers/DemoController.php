<?php
/**
 * KunJingPHP demo 控制器（验证容器依赖注入 Request）
 */

namespace App\Controllers;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;

class DemoController
{
    public function index(Request $request): Response
    {
        return Response::html(
            '<h1>Demo Controller</h1><p>控制器 + 容器依赖注入验证通过。</p>'
        );
    }
}
