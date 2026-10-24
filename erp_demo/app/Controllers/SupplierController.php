<?php
/**
 * 组梦ERP 迁移样例 — 供应商控制器
 * 演示：ORM CRUD / 关联预加载 / 分页 / 缓存 / 日志 / 会话闪存 / 验证器
 */

namespace Erp\Controllers;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Facade\View;
use KunJing\Framework\Facade\Session;
use KunJing\Framework\Facade\Cache;
use KunJing\Framework\Facade\Log;
use KunJing\Framework\Validate\Validator;
use Erp\Models\Supplier;
use Erp\Models\Goods;

class SupplierController
{
    /**
     * 供应商列表（搜索 + 分页 + 预加载商品 + 缓存统计）
     */
    public function index(Request $request)
    {
        $page    = max(1, (int) ($request->get('page') ?? 1));
        $keyword = trim((string) ($request->get('keyword') ?? ''));

        $query = Supplier::query()->with(['goods']);
        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }
        $result = $query->orderBy('id', 'desc')->paginate(10, $page);

        // 演示缓存：总数缓存 30 秒
        $cachedTotal = Cache::remember('supplier_total', 30, function () {
            return Supplier::query()->count();
        });

        // 转数组并附带关联数量（模板可直接 {$s.goodsCount}）
        $rows = array_map(function ($supplier) {
            $row = $supplier->toArray();
            $row['goodsCount'] = count($row['goods'] ?? []);
            unset($row['goods']);
            return $row;
        }, $result['data']);

        Log::info('查看供应商列表', ['page' => $page, 'keyword' => $keyword]);

        return View::render('supplier.index', [
            'list'    => $rows,
            'total'   => $result['total'],
            'page'    => $result['current_page'],
            'lastPage'=> $result['last_page'],
            'keyword' => $keyword,
            'cachedTotal' => $cachedTotal,
            'msg'     => Session::getFlash('msg'),
            'user'    => Session::get('user'),
        ]);
    }

    public function create()
    {
        return View::render('supplier.form', [
            'supplier' => null,
            'errors'   => [],
        ]);
    }

    /**
     * 新增供应商（验证器 + 唯一性校验）
     */
    public function store(Request $request)
    {
        $data = $request->only(['name', 'contact', 'phone', 'address', 'status']);

        $validator = Validator::make($data, [
            'name'    => 'required|min:2|max:50|unique:supplier,name',
            'contact' => 'max:20',
            'phone'   => 'regex:/^[0-9+\-()]{6,25}$/',
            'address' => 'max:120',
        ], [
            'name.required' => '供应商名称不能为空',
            'name.min'      => '供应商名称至少 2 个字',
            'name.unique'   => '该供应商名称已存在',
            'phone.regex'   => '联系电话格式不正确',
        ]);

        if ($validator->fails()) {
            return View::render('supplier.form', [
                'supplier' => $data,
                'errors'   => $validator->flatErrors(),
            ]);
        }

        $data['status'] = (int) ($data['status'] ?? 0);
        $supplier = Supplier::create($data);

        Cache::delete('supplier_total');
        Log::info('新增供应商', ['id' => $supplier->getKey(), 'name' => $data['name']]);
        Session::flash('msg', '供应商【' . $data['name'] . '】已新增');

        return Response::redirect('/supplier');
    }

    public function edit(Request $request)
    {
        $id = (int) $request->param('id');
        $supplier = Supplier::find($id);
        if ($supplier === null) {
            Session::flash('msg', '供应商不存在');
            return Response::redirect('/supplier');
        }

        return View::render('supplier.form', [
            'supplier' => $supplier->toArray(),
            'errors'   => [],
        ]);
    }

    public function update(Request $request)
    {
        $id = (int) $request->param('id');
        $supplier = Supplier::find($id);
        if ($supplier === null) {
            return Response::redirect('/supplier');
        }

        $data = $request->only(['name', 'contact', 'phone', 'address', 'status']);

        $validator = Validator::make($data, [
            'name'    => 'required|min:2|max:50',
            'contact' => 'max:20',
            'phone'   => 'regex:/^[0-9+\-()]{6,25}$/',
        ], [
            'name.required' => '供应商名称不能为空',
            'phone.regex'   => '联系电话格式不正确',
        ]);

        if ($validator->fails()) {
            return View::render('supplier.form', [
                'supplier' => array_merge($supplier->toArray(), $data),
                'errors'   => $validator->flatErrors(),
            ]);
        }

        $data['status'] = (int) ($data['status'] ?? 0);
        $supplier->update($data);

        Cache::delete('supplier_total');
        Log::info('更新供应商', ['id' => $id]);
        Session::flash('msg', '供应商信息已更新');

        return Response::redirect('/supplier');
    }

    /**
     * 删除供应商（同时清理其下商品，演示事务）
     */
    public function destroy(Request $request)
    {
        $id = (int) $request->param('id');
        $supplier = Supplier::find($id);
        if ($supplier !== null) {
            try {
                \KunJing\Framework\Database\Manager::instance()->transaction(function () use ($id) {
                    Goods::query()->where('supplier_id', $id)->delete();
                    Supplier::query()->where('id', $id)->delete();
                });
                Cache::delete('supplier_total');
                Log::warning('删除供应商', ['id' => $id]);
                Session::flash('msg', '供应商已删除');
            } catch (\Throwable $e) {
                Log::error('删除供应商失败：' . $e->getMessage());
                Session::flash('msg', '删除失败：' . $e->getMessage());
            }
        }
        return Response::redirect('/supplier');
    }
}
