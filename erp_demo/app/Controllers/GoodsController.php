<?php
/**
 * 组梦ERP 迁移样例 — 商品控制器（演示 belongsTo 反向关联）
 */

namespace Erp\Controllers;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Facade\View;
use KunJing\Framework\Facade\Session;
use KunJing\Framework\Facade\Log;
use KunJing\Framework\Validate\Validator;
use Erp\Models\Goods;
use Erp\Models\Supplier;

class GoodsController
{
    public function index(Request $request)
    {
        $page = max(1, (int) ($request->get('page') ?? 1));
        $result = Goods::query()->with(['supplier'])->orderBy('id', 'desc')->paginate(10, $page);

        return View::render('goods.index', [
            'list'  => $result['data'],
            'page'  => $result['current_page'],
            'lastPage' => $result['last_page'],
            'total' => $result['total'],
            'suppliers' => Supplier::query()->orderBy('id', 'desc')->get(),
            'msg'   => Session::getFlash('msg'),
            'errors'=> [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->only(['supplier_id', 'name', 'sku', 'price', 'stock']);

        $validator = Validator::make($data, [
            'supplier_id' => 'required|integer',
            'name'        => 'required|min:2|max:60',
            'sku'         => 'required|unique:goods,sku',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
        ], [
            'supplier_id.required' => '请选择所属供应商',
            'sku.unique'           => '该 SKU 已存在',
            'price.numeric'        => '价格必须是数字',
            'stock.integer'        => '库存必须是整数',
        ]);

        if ($validator->fails()) {
            Session::flash('msg', $validator->firstError());
            return Response::redirect('/goods');
        }

        $data['supplier_id'] = (int) $data['supplier_id'];
        $data['stock']       = (int) $data['stock'];
        Goods::create($data);

        Log::info('新增商品', $data);
        Session::flash('msg', '商品【' . $data['name'] . '】已新增');

        return Response::redirect('/goods');
    }

    public function destroy(Request $request)
    {
        $id = (int) $request->param('id');
        Goods::destroy([$id]);
        Session::flash('msg', '商品已删除');
        return Response::redirect('/goods');
    }
}
