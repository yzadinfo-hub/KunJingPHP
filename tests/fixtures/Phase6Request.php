<?php
/**
 * Phase 6 测试夹具：表单请求
 */

namespace P6;

use KunJing\Framework\Validate\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        // token=deny 时用于测试 403
        return $this->request()->input('token') !== 'deny';
    }

    /** @return array<string,string|array> */
    public function rules(): array
    {
        return [
            'name'  => 'required|min:2|max:60',
            'phone' => 'required',
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return ['name' => '供应商名称', 'phone' => '联系电话'];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['phone.required' => ':attribute不能为空，请填写后再提交'];
    }
}
