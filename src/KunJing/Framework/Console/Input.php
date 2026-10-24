<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：命令行输入解析（净室实现）
 * 支持 --option=value、--flag、-a 短选项 与 位置参数。
 */

namespace KunJing\Framework\Console;

class Input
{
    protected string $script = 'kunjing';
    protected string $command = '';
    /** @var array<int,string> */
    protected array $arguments = [];
    /** @var array<string,mixed> */
    protected array $options = [];

    /**
     * @param array<int,string> $argv
     */
    public function __construct(array $argv = [])
    {
        $argv = $argv !== [] ? $argv : (array) ($_SERVER['argv'] ?? []);
        if ($argv === []) {
            return;
        }
        $this->script = array_shift($argv);
        // 第一个非选项 token 作为命令名
        foreach ($argv as $i => $token) {
            if (!str_starts_with($token, '-')) {
                $this->command = $token;
                unset($argv[$i]);
                break;
            }
        }
        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                $body = substr($token, 2);
                if (str_contains($body, '=')) {
                    [$key, $value] = explode('=', $body, 2);
                    $this->options[$key] = $value;
                } else {
                    $this->options[$body] = true;
                }
            } elseif (str_starts_with($token, '-') && strlen($token) > 1) {
                $key = substr($token, 1);
                $this->options[$key] = true;
            } else {
                $this->arguments[] = $token;
            }
        }
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getArgument(int $index, $default = null)
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * @return array<int,string>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * @return array<string,mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
