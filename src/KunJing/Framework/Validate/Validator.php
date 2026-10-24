<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：验证器（净室实现，能力对标 ThinkPHP Validate）
 *
 * 用法：
 *  $v = Validator::make($data, ['name' => 'required|min:2', 'email' => 'required|email']);
 *  if ($v->fails()) { $v->errors(); }
 *  Validator::check($data, $rules); // 失败抛 ValidateException
 */

namespace KunJing\Framework\Validate;

use KunJing\Framework\Database\Manager;
use KunJing\Framework\Exception\ValidateException;

class Validator
{
    /**
     * @var array<string,mixed>
     */
    protected array $data = [];
    /**
     * @var array<string,string|array>
     */
    protected array $rules = [];
    /**
     * @var array<string,string>
     */
    protected array $messages = [];
    /**
     * @var array<string,callable>
     */
    protected array $customRules = [];
    /**
     * @var array<string,array<int,string>>
     */
    protected array $errors = [];
    /**
     * @var array<string,string>
     */
    protected array $aliases = [];

    public function __construct(array $data = [], array $rules = [], array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string|array> $rules
     * @param array<string,string> $messages
     */
    public static function make(array $data, array $rules, array $messages = []): static
    {
        return new static($data, $rules, $messages);
    }

    /**
     * 校验失败直接抛异常。
     * @param array<string,mixed> $data
     * @param array<string,string|array> $rules
     * @return array<string,mixed> 通过则返回原始数据
     * @throws ValidateException
     */
    public static function check(array $data, array $rules, array $messages = []): array
    {
        $validator = new static($data, $rules, $messages);
        if ($validator->fails()) {
            $exception = new ValidateException($validator->firstError() ?? '校验未通过', 422);
            $exception->setErrors($validator->flatErrors());
            throw $exception;
        }
        return $data;
    }

    public function setAliases(array $aliases): static
    {
        $this->aliases = $aliases;
        return $this;
    }

    /**
     * 注册自定义规则闭包：返回 bool 表示通过。
     */
    public function extend(string $name, callable $callback): static
    {
        $this->customRules[$name] = $callback;
        return $this;
    }

    public function passes(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $rawRule) {
            $ruleList = is_array($rawRule) ? $rawRule : explode('|', (string) $rawRule);
            foreach ($ruleList as $rule) {
                [$name, $params] = $this->parseRule($rule);
                $this->applyRule($field, $name, $params);
            }
        }
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string,array<int,string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    /**
     * @return array<string,string>
     */
    public function flatErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            $flat[$field] = $messages[0] ?? '';
        }
        return $flat;
    }

    /* ---------- 内部实现 ---------- */

    /**
     * @return array{0:string,1:array<int,string>}
     */
    protected function parseRule(string $rule): array
    {
        $rule = trim($rule);
        if ($rule === '') {
            return ['', []];
        }
        if (str_contains($rule, ':')) {
            [$name, $params] = explode(':', $rule, 2);
            // 正则规则中的冒号需要还原
            return [trim($name), explode(',', $params)];
        }
        return [$rule, []];
    }

    protected function applyRule(string $field, string $name, array $params): void
    {
        if ($name === '') {
            return;
        }
        $value = $this->data[$field] ?? null;
        $present = array_key_exists($field, $this->data) && trim((string) $value) !== '';

        // required 特判：不存在必填时不跑后续规则（除 accepted 类简单规则外）
        if ($name === 'required') {
            if (!$present) {
                $this->addError($field, $name, '字段不能为空', $params);
            }
            return;
        }
        if (!$present) {
            return; // 非必填字段为空则跳过其他规则
        }

        $ok = true;
        $defaultMessage = '格式不正确';

        switch ($name) {
            case 'string':
                $ok = is_string($value);
                $defaultMessage = '必须是字符串';
                break;
            case 'integer':
            case 'int':
                $ok = filter_var($value, FILTER_VALIDATE_INT) !== false;
                $defaultMessage = '必须是整数';
                break;
            case 'numeric':
                $ok = is_numeric($value);
                $defaultMessage = '必须是数字';
                break;
            case 'array':
                $ok = is_array($value);
                $defaultMessage = '必须是数组';
                break;
            case 'email':
                $ok = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                $defaultMessage = '邮箱格式不正确';
                break;
            case 'url':
                $ok = filter_var($value, FILTER_VALIDATE_URL) !== false;
                $defaultMessage = 'URL 格式不正确';
                break;
            case 'date':
                $ok = strtotime((string) $value) !== false;
                $defaultMessage = '日期格式不正确';
                break;
            case 'alphaDash':
                $ok = (bool) preg_match('/^[A-Za-z0-9_\-]+$/', (string) $value);
                $defaultMessage = '只能包含字母、数字、下划线与连字符';
                break;
            case 'alphaNum':
                $ok = (bool) preg_match('/^[A-Za-z0-9]+$/', (string) $value);
                $defaultMessage = '只能包含字母与数字';
                break;
            case 'min':
                $limit = (float) ($params[0] ?? 0);
                $ok = is_numeric($value) ? (float) $value >= $limit : mb_strlen((string) $value) >= $limit;
                $defaultMessage = is_numeric($value)
                    ? "不能小于 {$limit}"
                    : "长度不能少于 {$limit} 个字符";
                break;
            case 'max':
                $limit = (float) ($params[0] ?? 0);
                $ok = is_numeric($value) ? (float) $value <= $limit : mb_strlen((string) $value) <= $limit;
                $defaultMessage = is_numeric($value)
                    ? "不能大于 {$limit}"
                    : "长度不能超过 {$limit} 个字符";
                break;
            case 'between':
                $min = (float) ($params[0] ?? 0);
                $max = (float) ($params[1] ?? 0);
                $len = is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);
                $ok = $len >= $min && $len <= $max;
                $defaultMessage = "必须在 {$min} 与 {$max} 之间";
                break;
            case 'in':
                $ok = in_array((string) $value, $params, true);
                $defaultMessage = '不在允许的取值范围内';
                break;
            case 'notIn':
                $ok = !in_array((string) $value, $params, true);
                $defaultMessage = '不允许使用该值';
                break;
            case 'regex':
                $pattern = implode(',', $params); // 还原被逗号拆分的正则
                $ok = (bool) @preg_match($pattern, (string) $value);
                $defaultMessage = '格式不符合要求';
                break;
            case 'confirmed':
                $confirmField = $params[0] ?? ($field . '_confirmation');
                $ok = array_key_exists($confirmField, $this->data) && $this->data[$confirmField] === $value;
                $defaultMessage = '两次输入不一致';
                break;
            case 'same':
                $other = $params[0] ?? '';
                $ok = ($this->data[$other] ?? null) === $value;
                $defaultMessage = "与 {$other} 不一致";
                break;
            case 'unique':
                $table  = $params[0] ?? '';
                $column = $params[1] ?? $field;
                $ok = $this->checkUnique($table, $column, $value);
                $defaultMessage = '该值已存在';
                break;
            case 'exists':
                $table  = $params[0] ?? '';
                $column = $params[1] ?? $field;
                $ok = !$this->checkUnique($table, $column, $value);
                $defaultMessage = '该值不存在';
                break;
            default:
                if (isset($this->customRules[$name])) {
                    $ok = (bool) ($this->customRules[$name])($value, $this->data, $params);
                    $defaultMessage = '未通过自定义规则校验';
                }
                break;
        }

        if (!$ok) {
            $this->addError($field, $name, $defaultMessage, $params);
        }
    }

    protected function checkUnique(string $table, string $column, $value): bool
    {
        if ($table === '') {
            return true;
        }
        try {
            $count = Manager::instance()->table($table)->where($column, '=', $value)->count();
            return $count === 0;
        } catch (\Throwable $e) {
            return true; // 数据库不可用时跳过该规则，避免误伤
        }
    }

    protected function addError(string $field, string $rule, string $defaultMessage, array $params = []): void
    {
        $key = $field . '.' . $rule;
        $message = $this->messages[$key] ?? $this->messages[$field] ?? $this->messages[$rule] ?? $defaultMessage;
        $label = $this->aliases[$field] ?? $field;
        $message = str_replace([':attribute', ':field'], $label, $message);
        $this->errors[$field][] = $message;
    }
}
