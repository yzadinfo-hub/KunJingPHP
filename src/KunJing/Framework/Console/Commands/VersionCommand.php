<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 内置命令：输出框架版本信息
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\Command;

class VersionCommand extends Command
{
    protected string $name = 'version';
    protected string $description = '显示框架版本';

    protected function handle(): int
    {
        $this->info('堃景PHP / KunJingPHP');
        $this->line('版本: ' . ($this->appVersion ?? '1.0.0'));
        return 0;
    }

    /** @var string|null 由 Application 注入 */
    protected ?string $appVersion = null;

    public function __construct(?string $version = null)
    {
        $this->appVersion = $version;
    }
}
