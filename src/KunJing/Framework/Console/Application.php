<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：命令行应用（净室实现，能力对标 ThinkPHP console）
 *
 * 用法：
 *  $app = new Application('KunJingPHP', '1.0');
 *  $app->add(new MyCommand());
 *  exit($app->run($argv));
 */

namespace KunJing\Framework\Console;

class Application
{
    protected string $name = 'KunJingPHP';
    protected string $version = '1.0.0';
    /** @var array<string,Command> */
    protected array $commands = [];
    protected Output $output;

    public function __construct(string $name = 'KunJingPHP', string $version = '1.0.0', ?Output $output = null)
    {
        $this->name = $name;
        $this->version = $version;
        $this->output = $output ?? new Output();
    }

    public function output(): Output
    {
        return $this->output;
    }

    public function setOutput(Output $output): static
    {
        $this->output = $output;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function add(Command $command): static
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * @return array<string,Command>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    public function find(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    /**
     * @param array<int,string> $argv
     */
    public function run(array $argv = []): int
    {
        $input = new Input($argv);
        $commandName = $input->getCommand();

        if ($commandName === '' || $commandName === 'list' || $commandName === '--help' || $commandName === '-h') {
            $this->renderHelp();
            return 0;
        }

        $command = $this->find($commandName);
        if ($command === null) {
            $this->output->error("未找到命令 [{$commandName}]。");
            $this->renderCommandList();
            return 1;
        }

        try {
            return $command->run($input, $this->output);
        } catch (\Throwable $e) {
            $this->output->error('命令执行异常：' . $e->getMessage());
            $this->output->comment($e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }

    protected function renderHelp(): void
    {
        $this->output->title($this->name . ' ' . $this->version);
        $this->renderCommandList();
        $this->output->writeln('');
        $this->output->comment('用法：php kunjing <command> [arguments] [--options]');
    }

    protected function renderCommandList(): void
    {
        $rows = [];
        foreach ($this->commands as $name => $command) {
            $rows[] = ['命令' => $name, '说明' => $command->getDescription() ?: '-'];
        }
        $this->output->writeln('可用命令：');
        $this->output->table($rows);
    }
}
