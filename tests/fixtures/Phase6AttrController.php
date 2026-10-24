<?php
/**
 * Phase 6 测试夹具：注解路由控制器
 */

namespace P6;

use KunJing\Framework\Routing\Attributes\Get;
use KunJing\Framework\Routing\Attributes\Middleware;
use KunJing\Framework\Routing\Attributes\Post;
use KunJing\Framework\Routing\Attributes\Prefix;
use KunJing\Framework\Routing\Attributes\Route;
use KunJing\Framework\Routing\Attributes\Where;

#[Prefix('/api/v1')]
#[Middleware([AuthMiddleware::class])]
class AttrController
{
    #[Get('/users')]
    public function index()
    {
        return 'index';
    }

    #[Post('/users')]
    public function store()
    {
        return 'store';
    }

    #[Get('/users/{id}')]
    #[Where(['id' => '[0-9]+'])]
    public function show($id)
    {
        return 'show:' . $id;
    }

    #[Route('DELETE', '/users/{id}', name: 'p6.user.destroy')]
    public function destroy($id)
    {
        return 'destroy:' . $id;
    }
}
