<?php
/**
 * Phase 6 测试夹具：业务控制器（验证 FormRequest 注入与路由参数注入）
 */

namespace P6;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;

class SupplierController
{
    /** FormRequest 自动注入并校验 */
    public function store(SupplierRequest $request)
    {
        return Response::json(['code' => 0, 'data' => $request->validated()]);
    }

    /** 路由参数 {id} 自动注入 */
    public function show($id)
    {
        return Response::json(['code' => 0, 'id' => $id]);
    }
}
