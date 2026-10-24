<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：命令行命令基类（净室实现，能力对标 ThinkPHP console\Command）
 *
 * 子类定义 $name / $description，实现 handle() 返回退出码。
 */

namespace KunJing\Framework\Console;

abstract class Command
{
    protected string $name = '';
    protected string $description = '';
    protected ?Input $input = null;
    protected ?Output $output = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setInput(Input $input): static
    {
        $this->input = $input;
        return $this;
    }

    public function setOutput(Output $output): static
    {
        $this->output = $output;
        return $this;
    }

    protected function input(): Input
    {
        return $this->input ?? new Input();
    }

    protected function output(): Output
    {
        return $this->output ?? new Output();
    }

    /**
     * 执行命令入口。
     */
    public function run(Input $input, Output $output): int
    {
        $this->input = $input;
        $this->output = $output;
        return (int) $this->handle();
    }

    /**
     * @return int 退出码，0 表示成功
     */
    abstract protected function handle(): int;

    /* ---------- 便捷输出 ---------- */

    protected function info(string $text): void
    {
        $this->output()->info($text);
    }

    protected function error(string $text): void
    {
        $this->output()->error($text);
    }

    protected function line(string $text = ''): void
    {
        $this->output()->writeln($text);
    }

    protected function argument(int $index, $default = null)
    {
        return $this->input()->getArgument($index, $default);
    }

    protected function option(string $key, $default = null)
    {
        return $this->input()->getOption($key, $default);
    }
}
