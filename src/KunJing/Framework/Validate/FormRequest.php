<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：表单请求（净室实现，能力对标 ThinkPHP 的验证器场景）
 *
 * 用法：
 *  class SupplierRequest extends FormRequest
 *  {
 *      public function rules(): array
 *      {
 *          return ['name' => 'required|min:2', 'phone' => 'required|regex:/^1[3-9]\d{9}$/'];
 *      }
 *  }
 *
 *  // 控制器方法直接声明类型即可自动校验
 *  public function store(SupplierRequest $request) { $data = $request->validated(); }
 */

namespace KunJing\Framework\Validate;

use KunJing\Framework\Exception\HttpException;
use KunJing\Framework\Exception\ValidateException;
use KunJing\Framework\Http\Request;
use KunJing\Framework\Http\UploadedFile;

abstract class FormRequest
{
    protected Request $request;
    protected ?Validator $validator = null;
    /** @var array<string,mixed> */
    protected array $validated = [];

    final public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 由 Request 构造并完成鉴权 + 校验。
     *
     * @throws HttpException      鉴权失败（403）
     * @throws ValidateException  校验失败（422）
     */
    public static function createFrom(Request $request, bool $autoValidate = true): static
    {
        $instance = new static($request);

        if (!$instance->authorize()) {
            throw new HttpException(403, '没有访问该资源的权限。');
        }

        $data = $instance->data();
        $validator = Validator::make($data, $instance->rules(), $instance->messages());
        $validator->setAliases($instance->attributes());
        $instance->validator = $validator;

        if ($autoValidate && $validator->fails()) {
            $exception = new ValidateException($validator->firstError() ?? '表单校验未通过', 422);
            $exception->setErrors($validator->flatErrors());
            throw $exception;
        }

        $instance->validated = $instance->extractValidated($data);
        return $instance;
    }

    /* ---------- 子类可重写 ---------- */

    /** @return array<string,string|array> */
    public function rules(): array
    {
        return [];
    }

    /** 自定义错误提示，键可为 "字段.规则" 或 "字段" */
    public function messages(): array
    {
        return [];
    }

    /** 字段中文别名，用于错误提示中的 :attribute */
    public function attributes(): array
    {
        return [];
    }

    /** 访问权限判定，返回 false 时抛 403 */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 待校验数据，默认取请求全部入参；可重写做预处理/默认值填充。
     * @return array<string,mixed>
     */
    public function data(): array
    {
        return $this->request->all();
    }

    /* ---------- 对外 API ---------- */

    public function request(): Request
    {
        return $this->request;
    }

    public function validator(): ?Validator
    {
        return $this->validator;
    }

    /** @return array<string,mixed> 通过校验的数据（仅含定义了规则的字段） */
    public function validated(): array
    {
        return $this->validated;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->request->all();
    }

    public function input(string $key, $default = null)
    {
        return $this->request->input($key, $default);
    }

    /** @param array<int,string> $keys */
    public function only(array $keys): array
    {
        return $this->request->only($keys);
    }

    /** @return UploadedFile|array<int,UploadedFile>|null */
    public function file(string $key)
    {
        return $this->request->file($key);
    }

    public function hasFile(string $key): bool
    {
        return $this->request->hasFile($key);
    }

    public function __get($name)
    {
        return $this->request->input($name);
    }

    public function __isset($name): bool
    {
        return $this->request->input($name) !== null;
    }

    /**
     * 只保留定义了规则的字段；无规则时保留全部入参。
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function extractValidated(array $data): array
    {
        $keys = array_keys($this->rules());
        if ($keys === []) {
            return $data;
        }
        $result = [];
        foreach ($keys as $key) {
            // 支持 "field.*" 通配（数组字段）
            if (str_ends_with($key, '.*')) {
                $base = substr($key, 0, -2);
                if (array_key_exists($base, $data)) {
                    $result[$base] = $data[$base];
                }
                continue;
            }
            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }
}
