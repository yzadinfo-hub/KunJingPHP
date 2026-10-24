<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：分页器（净室实现）
 *
 * 用法：
 *  $result = Goods::query()->paginate(15, $page);
 *  $pager = Paginator::make($result);
 *  echo $pager->render();       // HTML 分页条
 *  echo $pager->items();        // 当前页数据
 */

namespace KunJing\Framework\Support;

class Paginator
{
    /** @var array<int,mixed> */
    protected array $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected string $path;
    /** @var array<string,mixed> */
    protected array $query;
    protected string $pageName = 'page';
    protected int $sidePages = 2;

    /**
     * @param array<int,mixed> $items
     * @param array<string,mixed> $query
     */
    public function __construct(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        string $path = '',
        array $query = []
    ) {
        $this->items = array_values($items);
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->path = $path !== '' ? $path : $this->detectPath();
        $this->query = $query !== [] ? $query : ($_GET ?? []);
    }

    /**
     * 由 Builder::paginate() 的返回结构直接构造。
     * @param array<string,mixed> $result
     * @param array<string,mixed> $query
     */
    public static function make(array $result, string $path = '', array $query = []): self
    {
        return new self(
            isset($result['data']) && is_array($result['data']) ? $result['data'] : [],
            (int) ($result['total'] ?? 0),
            (int) ($result['per_page'] ?? 15),
            (int) ($result['current_page'] ?? 1),
            $path,
            $query
        );
    }

    protected function detectPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    /* ---------- 基础信息 ---------- */

    /** @return array<int,mixed> */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return (int) max(1, (int) ceil($this->total / $this->perPage));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function hasPages(): bool
    {
        return $this->total > $this->perPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function firstItem(): int
    {
        return $this->total === 0 ? 0 : ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function lastItem(): int
    {
        return $this->total === 0 ? 0 : min($this->firstItem() + $this->count() - 1, $this->total);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage(),
            'from'         => $this->firstItem(),
            'to'           => $this->lastItem(),
            'data'         => array_map(function ($item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return $item->toArray();
                }
                return $item;
            }, $this->items),
        ];
    }

    /* ---------- 链接 ---------- */

    /** @param array<string,mixed> $query */
    public function appends(array $query): self
    {
        $this->query = array_merge($this->query, $query);
        return $this;
    }

    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function setPageName(string $name): self
    {
        $this->pageName = $name;
        return $this;
    }

    public function url(int $page): string
    {
        $params = array_merge($this->query, [$this->pageName => $page]);
        $query = http_build_query($params);
        return $this->path . ($query !== '' ? '?' . $query : '');
    }

    public function previousUrl(): ?string
    {
        return $this->onFirstPage() ? null : $this->url($this->currentPage - 1);
    }

    public function nextUrl(): ?string
    {
        return $this->hasMorePages() ? $this->url($this->currentPage + 1) : null;
    }

    /** @return array<int,int> 需要展示的页码 */
    public function pageList(): array
    {
        $last = $this->lastPage();
        $current = $this->currentPage;
        $start = max(1, $current - $this->sidePages);
        $end = min($last, $current + $this->sidePages);
        if ($end - $start < $this->sidePages * 2) {
            $start = max(1, $end - $this->sidePages * 2);
            $end = min($last, $start + $this->sidePages * 2);
        }
        return range($start, max($start, $end));
    }

    /* ---------- 渲染 ---------- */

    /**
     * 渲染分页条（自带样式类，可自行覆盖 CSS）。
     */
    public function render(): string
    {
        $last = $this->lastPage();
        if ($last <= 1) {
            return '';
        }

        $html = '<nav class="kj-pagination"><ul class="kj-pagination-list">';

        $prev = $this->previousUrl();
        $html .= $prev === null
            ? '<li class="kj-page kj-disabled"><span>上一页</span></li>'
            : '<li class="kj-page"><a href="' . $this->e($prev) . '" rel="prev">上一页</a></li>';

        foreach ($this->pageList() as $page) {
            if ($page === $this->currentPage) {
                $html .= '<li class="kj-page kj-active"><span>' . $page . '</span></li>';
            } else {
                $html .= '<li class="kj-page"><a href="' . $this->e($this->url($page)) . '">' . $page . '</a></li>';
            }
        }

        $next = $this->nextUrl();
        $html .= $next === null
            ? '<li class="kj-page kj-disabled"><span>下一页</span></li>'
            : '<li class="kj-page"><a href="' . $this->e($next) . '" rel="next">下一页</a></li>';

        $html .= '<li class="kj-page kj-total"><span>共 ' . $this->total . ' 条 / ' . $last . ' 页</span></li>';
        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * 极简模式：只输出上一页/下一页。
     */
    public function simple(): string
    {
        $html = '<nav class="kj-pagination kj-simple">';
        $prev = $this->previousUrl();
        $next = $this->nextUrl();
        $html .= $prev === null
            ? '<span class="kj-disabled">上一页</span>'
            : '<a href="' . $this->e($prev) . '">上一页</a>';
        $html .= '<span class="kj-current">' . $this->currentPage . ' / ' . $this->lastPage() . '</span>';
        $html .= $next === null
            ? '<span class="kj-disabled">下一页</span>'
            : '<a href="' . $this->e($next) . '">下一页</a>';
        $html .= '</nav>';
        return $html;
    }

    public function links(): string
    {
        return $this->render();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 默认分页条样式（可内联到页面 <style> 中）。
     */
    public static function styles(): string
    {
        return '.kj-pagination{margin:16px 0;font-size:14px}'
            . '.kj-pagination-list{list-style:none;margin:0;padding:0;display:flex;align-items:center;gap:6px}'
            . '.kj-page a,.kj-page span{display:inline-block;padding:6px 12px;border:1px solid #d9d9d9;'
            . 'border-radius:4px;color:#333;text-decoration:none;background:#fff}'
            . '.kj-page a:hover{border-color:#2f6fed;color:#2f6fed}'
            . '.kj-active span{border-color:#2f6fed;background:#2f6fed;color:#fff}'
            . '.kj-disabled span{color:#bbb;background:#fafafa;cursor:not-allowed}'
            . '.kj-total span{border:none;color:#888}';
    }
}
