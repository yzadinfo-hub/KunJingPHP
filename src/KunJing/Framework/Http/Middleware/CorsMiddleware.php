<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：跨域中间件（净室实现）
 *
 * 配置（config/cors.php 或配置仓库的 cors 段）：
 *  'cors' => [
 *      'allow_origin'      => '*',
 *      'allow_methods'     => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
 *      'allow_headers'     => 'Content-Type,Authorization,X-Requested-With,X-Csrf-Token',
 *      'allow_credentials' => false,
 *      'max_age'           => 86400,
 *  ]
 */

namespace KunJing\Framework\Http\Middleware;

use KunJing\Framework\Config\Repository;
use KunJing\Framework\Container\Container;
use KunJing\Framework\Http\Response;

class CorsMiddleware
{
    /** @var array<string,mixed> */
    protected array $config = [];

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function handle($request, $next)
    {
        $headers = $this->headers();

        // 预检请求直接返回 204，不进入业务
        if ($request->method() === 'OPTIONS') {
            return Response::make('', 204, $headers);
        }

        $response = $next($request);
        if ($response instanceof Response) {
            foreach ($headers as $key => $value) {
                $response->setHeader($key, $value);
            }
        }
        return $response;
    }

    /** @return array<string,string> */
    protected function headers(): array
    {
        $origin = $this->conf('allow_origin', '*');
        if (is_array($origin)) {
            $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $origin = in_array($requestOrigin, $origin, true) ? $requestOrigin : 'null';
        }
        $headers = [
            'Access-Control-Allow-Origin'  => (string) $origin,
            'Access-Control-Allow-Methods' => (string) $this->conf('allow_methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
            'Access-Control-Allow-Headers' => (string) $this->conf('allow_headers', 'Content-Type,Authorization,X-Requested-With,X-Csrf-Token'),
            'Access-Control-Max-Age'       => (string) $this->conf('max_age', 86400),
        ];
        if ($this->conf('allow_credentials', false)) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }
        return $headers;
    }

    protected function conf(string $key, $default = null)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        try {
            $repository = Container::getInstance()->make(Repository::class);
            return $repository->get('cors.' . $key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
