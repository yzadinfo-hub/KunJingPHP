<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：模板引擎（净室实现，能力对标 ThinkTemplate，语法自研）
 *
 * 支持的语法：
 *   {$var}                     输出（默认 HTML 转义）
 *   {$var|raw}                 原样输出，不转义
 *   {$user.name}               点号访问数组/对象/模型属性
 *   {if $a > 1}...{else}...{/if}
 *   {foreach $list as $item}...{/foreach}
 *   {foreach $list as $k => $v}...{/foreach}
 *   {include 'header'}
 *   {extend 'layout'} + {block name}...{/block}   模板继承
 *
 * 编译产物为纯 PHP 缓存文件，二次请求直接包含，性能接近原生。
 */

namespace KunJing\Framework\View;

class Engine
{
    protected string $viewPath;
    protected string $cachePath;
    protected string $extension = '.html';
    /** @var array<string,string> 已编译过的模板映射 */
    protected array $compiled = [];

    public function __construct(array $config = [])
    {
        $base = $config['base_path'] ?? getcwd();
        $this->viewPath  = rtrim($config['view_path'] ?? ($base . DIRECTORY_SEPARATOR . 'views'), '/\\');
        $this->cachePath = rtrim($config['cache_path'] ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing_views'), '/\\');
        $this->extension = $config['extension'] ?? '.html';
        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0755, true);
        }
    }

    public function getViewPath(): string
    {
        return $this->viewPath;
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /* ---------- 对外接口 ---------- */

    /**
     * 渲染模板（返回字符串）。
     */
    public function render(string $template, array $vars = []): string
    {
        return $this->renderWith($template, $vars, []);
    }

    /**
     * 带已继承区块渲染（内部递归使用）。
     */
    public function renderWith(string $template, array $vars = [], array $blocks = []): string
    {
        $compiled = $this->compile($template);
        return $this->evaluate($compiled, $vars, $blocks);
    }

    public function exists(string $template): bool
    {
        return is_file($this->resolveSource($template));
    }

    protected function resolveSource(string $template): string
    {
        if (str_contains($template, DIRECTORY_SEPARATOR) || preg_match('#\.(html|php|tpl)$#', $template)) {
            return $this->viewPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $template), DIRECTORY_SEPARATOR);
        }
        return $this->viewPath . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $template) . $this->extension;
    }

    /* ---------- 编译 ---------- */

    /**
     * 返回编译后的 PHP 文件路径（带过期重编译）。
     */
    public function compile(string $template): string
    {
        $source = $this->resolveSource($template);
        if (!is_file($source)) {
            throw new \RuntimeException("模板文件不存在：{$template}（{$source}）");
        }
        $key = md5($source);
        $target = $this->cachePath . DIRECTORY_SEPARATOR . $key . '.php';
        if (!is_file($target) || filemtime($target) < filemtime($source)) {
            $php = $this->compileString(file_get_contents($source));
            @file_put_contents($target, $php);
        }
        $this->compiled[$template] = $target;
        return $target;
    }

    /**
     * 模板源码 → PHP 代码。
     */
    public function compileString(string $content): string
    {
        // 1) 模板继承：取出 {extend 'path'}
        $extend = null;
        $content = preg_replace_callback('/\{extend\s+[\'"]([^\'"]+)[\'"]\s*\}/', function ($m) use (&$extend) {
            $extend = $m[1];
            return '';
        }, $content);

        $isChild = $extend !== null;

        // 2) 区块：子模板收集，布局模板输出（可被覆盖）
        $content = preg_replace_callback(
            '/\{block\s+([A-Za-z_]\w*)\}((?:(?!\{\/block\}).)*)\{\/block\}/s',
            function ($m) use ($isChild) {
                $name = $m[1];
                $body = $m[2];
                if ($isChild) {
                    return "<?php ob_start(); ?>{$body}<?php \$__blocks['{$name}'] = ob_get_clean(); ?>";
                }
                // 布局：有覆盖则输出覆盖内容，否则输出默认内容
                return "<?php if (isset(\$__blocks['{$name}'])) "
                    . "{ echo \$__blocks['{$name}']; } else { ?>{$body}<?php } ?>";
            },
            $content
        );

        // 3) include
        $content = preg_replace_callback(
            '/\{include\s+[\'"]([^\'"]+)[\'"]\s*\}/',
            fn($m) => "<?php echo \$__engine->renderWith('{$m[1]}', get_defined_vars(), \$__blocks); ?>",
            $content
        );

        // 4) if / elseif / else / endif（并把 $a.b 转成安全取值表达式）
        $content = preg_replace_callback('/\{if\s+(.+?)\}/', fn($m) => '<?php if (' . $this->compileExpression($m[1]) . '): ?>', $content);
        $content = preg_replace_callback('/\{elseif\s+(.+?)\}/', fn($m) => '<?php elseif (' . $this->compileExpression($m[1]) . '): ?>', $content);
        $content = str_replace(['{else}', '{/if}'], ['<?php else: ?>', '<?php endif; ?>'], $content);

        // 5) foreach
        $content = preg_replace_callback(
            '/\{foreach\s+(\$[^\s]+)\s+as\s+(\$[A-Za-z_]\w*)\s*(?:=>\s*(\$[A-Za-z_]\w*))?\}/',
            fn($m) => ($m[3] ?? null) !== null
                ? "<?php foreach (" . $this->compileExpression($m[1]) . " as {$m[2]} => {$m[3]}): ?>"
                : "<?php foreach (" . $this->compileExpression($m[1]) . " as {$m[2]}): ?>",
            $content
        );
        $content = str_replace('{/foreach}', '<?php endforeach; ?>', $content);

        // 6) 变量输出
        $content = preg_replace_callback('/\{\$(.+?)\}/s', [$this, 'compileEcho'], $content);

        // 7) 子模板结尾渲染父布局
        if ($isChild) {
            $content .= "<?php echo \$__engine->renderWith('{$extend}', get_defined_vars(), \$__blocks); ?>";
        }

        return $content;
    }

    /**
     * 编译变量输出（默认转义，|raw 关闭转义）。
     */
    protected function compileEcho(array $matches): string
    {
        $expr = trim($matches[1]);
        $raw = false;
        if (str_ends_with($expr, '|raw')) {
            $raw = true;
            $expr = trim(substr($expr, 0, -4));
        }
        // 正则已吃掉前缀 \$，此处为「纯点号变量路径」补回 $（如 user.name → $user.name）
        if (!str_starts_with($expr, '$') && preg_match('/^[A-Za-z_]\w*(?:\.[A-Za-z_]\w*)*$/', $expr)) {
            $expr = '$' . $expr;
        }
        $php = $this->toPhpExpression($expr);
        return $raw
            ? "<?php echo {$php}; ?>"
            : "<?php echo htmlspecialchars((string)({$php} ?? ''), ENT_QUOTES, 'UTF-8'); ?>";
    }

    /**
     * 将模板变量表达式转换为 PHP 表达式。
     */
    protected function toPhpExpression(string $expr): string
    {
        // 已是 PHP 表达式（含运算符/函数调用）
        if (str_contains($expr, '(') || preg_match('/[\s\+\-\*\/%<>=\!\?]/', $expr)) {
            return $expr;
        }
        // $var 或 $var.a.b
        if (preg_match('/^\$([A-Za-z_]\w*)((?:\.[A-Za-z_]\w*)*)$/', $expr, $m)) {
            $var = '$' . $m[1];
            $path = trim($m[2] ?? '', '.');
            if ($path === '') {
                return $var;
            }
            return "\\KunJing\\Framework\\View\\Engine::get({$var}, '{$path}')";
        }
        return $expr;
    }

    /**
     * 指令表达式预处理：把 $a.b.c 转换为安全的 Engine::get($a,'b.c')，
     * 避免原生 PHP 把它解析成字符串拼接导致「Undefined constant」。
     */
    protected function compileExpression(string $expr): string
    {
        return (string) preg_replace_callback(
            '/\$([A-Za-z_]\w*)((?:\.[A-Za-z_]\w*)+)/',
            fn($m) => "\\KunJing\\Framework\\View\\Engine::get((\${$m[1]} ?? null), '" . trim($m[2], '.') . "')",
            $expr
        );
    }

    /**
     * 点号路径取值：同时支持数组、对象属性与模型 getAttribute()。
     * @param mixed $target
     * @return mixed
     */
    public static function get($target, string $path)
    {
        foreach (explode('.', $path) as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->$segment)) {
                $target = $target->$segment;
            } elseif (is_object($target) && method_exists($target, 'getAttribute')) {
                $target = $target->getAttribute($segment);
            } else {
                return null;
            }
        }
        return $target;
    }

    /**
     * 执行编译后的 PHP 文件并返回输出。
     */
    protected function evaluate(string $__file, array $__vars, array $__blocks): string
    {
        $__engine = $this;
        unset($__vars['__engine'], $__vars['__blocks'], $__vars['__file']);
        extract($__vars);
        ob_start();
        include $__file;
        return (string) ob_get_clean();
    }

    /**
     * 清空编译缓存。
     */
    public function clearCache(): bool
    {
        foreach (glob($this->cachePath . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            @unlink($file);
        }
        return true;
    }
}
