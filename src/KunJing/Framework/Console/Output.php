<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：命令行输出（净室实现，含 ANSI 彩色）
 */

namespace KunJing\Framework\Console;

class Output
{
    public const COLOR_GREEN  = '32';
    public const COLOR_RED    = '31';
    public const COLOR_YELLOW = '33';
    public const COLOR_BLUE   = '34';
    public const COLOR_GRAY   = '90';

    protected bool $quiet = false;

    public function __construct(bool $quiet = false)
    {
        $this->quiet = $quiet;
    }

    public function write(string $text): void
    {
        if ($this->quiet) {
            return;
        }
        echo $text;
    }

    public function writeln(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    protected function color(string $text, string $color): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && getenv('ANSICON') === false && getenv('WT_SESSION') === false) {
            return $text; // Windows 旧控制台不支持 ANSI
        }
        return "\033[{$color}m{$text}\033[0m";
    }

    public function info(string $text): void
    {
        $this->writeln($this->color('[信息] ', self::COLOR_GREEN) . $text);
    }

    public function error(string $text): void
    {
        $this->writeln($this->color('[错误] ', self::COLOR_RED) . $text);
    }

    public function warning(string $text): void
    {
        $this->writeln($this->color('[警告] ', self::COLOR_YELLOW) . $text);
    }

    public function comment(string $text): void
    {
        $this->writeln($this->color($text, self::COLOR_GRAY));
    }

    public function title(string $text): void
    {
        $this->writeln('');
        $this->writeln($this->color('== ' . $text . ' ==', self::COLOR_BLUE));
    }

    /**
     * 简单表格输出。
     * @param array<int,array<string,mixed>> $rows
     */
    public function table(array $rows): void
    {
        if ($rows === []) {
            $this->comment('(空)');
            return;
        }
        $headers = array_keys($rows[0]);
        $widths = [];
        foreach ($headers as $h) {
            $widths[$h] = mb_strlen((string) $h);
        }
        foreach ($rows as $row) {
            foreach ($headers as $h) {
                $widths[$h] = max($widths[$h], mb_strlen((string) ($row[$h] ?? '')));
            }
        }
        $formatRow = function (array $cells) use ($headers, $widths): string {
            $parts = [];
            foreach ($headers as $h) {
                $parts[] = mb_str_pad_local((string) ($cells[$h] ?? ''), $widths[$h]);
            }
            return implode(' | ', $parts);
        };
        $this->writeln($formatRow(array_combine($headers, $headers)));
        $this->writeln(implode('-+-', array_map(fn($h) => str_repeat('-', $widths[$h]), $headers)));
        foreach ($rows as $row) {
            $this->writeln($formatRow($row));
        }
    }
}

if (!function_exists('mb_str_pad_local')) {
    /**
     * 简易多字节填充辅助函数。
     */
    function mb_str_pad_local(string $input, int $length): string
    {
        $pad = $length - mb_strlen($input);
        return $pad > 0 ? $input . str_repeat(' ', $pad) : $input;
    }
}
