<?php
/**
 * KunJingPHP demo 端到端模拟（CLI 内模拟 HTTP 请求，验证完整链路）
 */
require __DIR__ . '/../bootstrap.php';

use KunJing\Framework\App;
use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\Response;
use App\Controllers\DemoController;
use App\Middleware\AuthMiddleware;

function runUri(string $method, string $uri, array $headers = []): string
{
    $_GET = [];
    $_POST = [];
    $_SERVER = array_merge($_SERVER, [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI'    => $uri,
        'HTTP_HOST'      => 'localhost',
    ], $headers);

    $app = new App(__DIR__ . '/..');
    $app->config()->set('app.debug', true);
    $app->router()->get('/', fn() => '<h1>home</h1>');
    $app->router()->get('/hello/{name}', fn(Request $r) => Response::json(['hello' => $r->param('name')]));
    $app->router()->get('/secure', fn() => 'ok')->middleware(AuthMiddleware::class);
    $app->router()->get('/demo', [DemoController::class, 'index']);

    ob_start();
    $app->run();
    return (string) ob_get_clean();
}

echo "HOME        : " . runUri('GET', '/') . "\n";
echo "HELLO       : " . runUri('GET', '/hello/kunjing') . "\n";
echo "SECURE_NOAUTH: " . runUri('GET', '/secure') . "\n";
echo "SECURE_AUTH : " . runUri('GET', '/secure', ['HTTP_X_TOKEN' => 'secret']) . "\n";
echo "DEMO        : " . runUri('GET', '/demo') . "\n";
echo "NOTFOUND    : " . runUri('GET', '/nope') . "\n";
