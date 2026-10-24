<?php
/**
 * 组梦ERP 迁移样例 — 登录控制器（演示 Session + Validate + View）
 */

namespace Erp\Controllers;

use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use KunJing\Framework\Facade\Session;
use KunJing\Framework\Facade\Log;
use KunJing\Framework\Facade\View;
use KunJing\Framework\Validate\Validator;

class AuthController
{
    /** 演示账号（真实项目应查库校验） */
    private const ADMIN  = 'admin';
    private const PASSWD = '123456';

    public function showLogin()
    {
        return View::render('auth.login', [
            'error'  => Session::getFlash('error'),
            'isPost' => false,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->only(['username', 'password']);

        $validator = Validator::make($data, [
            'username' => 'required|min:2|max:20',
            'password' => 'required|min:6',
        ], [
            'username.required' => '请输入用户名',
            'password.required' => '请输入密码',
            'password.min'      => '密码至少 6 位',
        ]);

        if ($validator->fails()) {
            Session::flash('error', $validator->firstError());
            return Response::redirect('/login');
        }

        if ($data['username'] !== self::ADMIN || $data['password'] !== self::PASSWD) {
            Log::warning('登录失败', ['username' => $data['username']]);
            Session::flash('error', '用户名或密码不正确');
            return Response::redirect('/login');
        }

        Session::set('user', [
            'id'       => 1,
            'username' => $data['username'],
            'name'     => '系统管理员',
        ]);
        Log::info('用户登录成功', ['username' => $data['username']]);

        return Response::redirect('/supplier');
    }

    public function logout()
    {
        Session::forget('user');
        return Response::redirect('/login');
    }
}
