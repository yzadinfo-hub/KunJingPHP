<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：生成表单请求 make:request
 */

namespace KunJing\Framework\Console\Commands;

use KunJing\Framework\Console\BaseCommand;

class MakeRequestCommand extends BaseCommand
{
    protected string $name = 'make:request';
    protected string $description = '生成表单请求（自动校验）';

    protected function handle(): int
    {
        $raw = (string) ($this->argument(0) ?? '');
        if ($raw === '') {
            $this->error('请提供名称：php kunjing make:request Supplier');
            return 1;
        }

        $class = $this->studly($raw);
        if (!str_ends_with($class, 'Request')) {
            $class .= 'Request';
        }

        $dir = $this->pathFor('requests', 'app/Requests');
        $namespace = $this->namespace() . '\\Requests';
        $file = $dir . '/' . $class . '.php';

        $content = <<<PHP
<?php

namespace {$namespace};

use KunJing\Framework\Validate\FormRequest;

class {$class} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,string|array> */
    public function rules(): array
    {
        return [
            // 'name' => 'required|min:2|max:60',
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            // 'name.required' => '名称不能为空',
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            // 'name' => '名称',
        ];
    }
}

PHP;

        return $this->writeFile($file, $content, $this->force()) ? 0 : 1;
    }
}
