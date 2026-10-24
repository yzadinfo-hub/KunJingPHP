<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：日志器（净室实现，能力对标 ThinkPHP Log）
 * 支持 single / daily 两种通道，按级别过滤写入。
 */

namespace KunJing\Framework\Log;

class Logger
{
    public const DEBUG   = 'debug';
    public const INFO    = 'info';
    public const NOTICE  = 'notice';
    public const WARNING = 'warning';
    public const ERROR   = 'error';

    /** 级别权重，用于阈值过滤 */
    protected const LEVELS = [
        self::DEBUG   => 100,
        self::INFO    => 200,
        self::NOTICE  => 250,
        self::WARNING => 300,
        self::ERROR   => 400,
    ];

    protected string $path;
    protected string $channel;
    protected int $threshold;
    protected string $dateFormat = 'Y-m-d H:i:s';

    public function __construct(array $config = [])
    {
        $this->path      = $config['path'] ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kunjing.log');
        $this->channel   = $config['channel'] ?? 'single';
        $this->threshold = self::LEVELS[$config['level'] ?? self::DEBUG] ?? 100;
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(self::INFO, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->write(self::NOTICE, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(self::ERROR, $message, $context);
    }

    public function write(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 100) < $this->threshold) {
            return;
        }
        $file = $this->channel === 'daily'
            ? preg_replace('/\.log$/', '', $this->path) . '-' . date('Y-m-d') . '.log'
            : $this->path;

        $line = sprintf(
            "[%s] %s.%s: %s%s\n",
            date($this->dateFormat),
            strtoupper($level),
            $this->channel,
            $this->interpolate($message, $context),
            $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($file, $line, FILE_APPEND);
    }

    /**
     * 简易 {placeholder} 插值：把 {key} 替换为 context[key]。
     */
    protected function interpolate(string $message, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $message = str_replace('{' . $key . '}', (string) $value, $message);
            }
        }
        return $message;
    }

    public function clear(): bool
    {
        return !is_file($this->path) || @file_put_contents($this->path, '') !== false;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
