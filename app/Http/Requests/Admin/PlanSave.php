<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PlanSave extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'content' => 'nullable|string',
            'reset_traffic_method' => 'integer|nullable',
            'transfer_enable' => 'integer|required|min:1',
            'prices' => 'nullable|array',
            'prices.*' => 'nullable|numeric|min:0',
            'group_id' => 'integer|nullable',
            'speed_limit' => 'integer|nullable|min:0',
            'device_limit' => 'integer|nullable|min:0',
            'capacity_limit' => 'integer|nullable|min:0',
            'tags' => 'array|nullable',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validatePrices($validator);
        });
    }

    /**
     * 验证价格配置
     */
    protected function validatePrices(Validator $validator): void
    {
        $prices = $this->input('prices', []);
        
        if (empty($prices)) {
            return;
        }

        // 获取所有有效的周期
        $validPeriods = array_keys(Plan::getAvailablePeriods());
        
        foreach ($prices as $period => $price) {
            // 验证周期是否有效
            if (!in_array($period, $validPeriods)) {
                $validator->errors()->add(
                    "prices.{$period}", 
                    "不支持的订阅周期: {$period}"
                );
                continue;
            }

            // 价格可以为 null、空字符串或大于 0 的数字
            if ($price !== null && $price !== '') {
                // 转换为数字进行验证
                $numericPrice = is_numeric($price) ? (float) $price : null;
                
                if ($numericPrice === null) {
                    $validator->errors()->add(
                        "prices.{$period}", 
                        "价格必须是数字格式"
                    );
                } elseif ($numericPrice <= 0) {
                    $validator->errors()->add(
                        "prices.{$period}", 
                        "价格必须大于 0（如不需要此周期请留空或设为 null）"
                    );
                }
            }
        }
    }

    /**
     * 处理验证后的数据
     */
    protected function passedValidation(): void
    {
        // 清理和格式化价格数据
        $prices = $this->input('prices', []);
        $cleanedPrices = [];

        foreach ($prices as $period => $price) {
            // 只保留有效的正数价格
            if ($price !== null && $price !== '' && is_numeric($price)) {
                $numericPrice = (float) $price;
                if ($numericPrice > 0) {
                    // 转换为浮点数并保留两位小数
                    $cleanedPrices[$period] = round($numericPrice, 2);
                }
            }
        }

        // 更新请求中的价格数据
        $this->merge(['prices' => $cleanedPrices]);
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => '套餐名称不能为空',
            'name.max' => '套餐名称不能超过 255 个字符',
            'transfer_enable.required' => '流量配额不能为空',
            'transfer_enable.integer' => '流量配额必须是整数',
            'transfer_enable.min' => '流量配额必须大于 0',
            'prices.array' => '价格配置格式错误',
            'prices.*.numeric' => '价格必须是数字',
            'prices.*.min' => '价格不能为负数',
            'group_id.integer' => '权限组ID必须是整数',
            'speed_limit.integer' => '速度限制必须是整数',
            'speed_limit.min' => '速度限制不能为负数',
            'device_limit.integer' => '设备限制必须是整数',
            'device_limit.min' => '设备限制不能为负数',
            'capacity_limit.integer' => '容量限制必须是整数',
            'capacity_limit.min' => '容量限制不能为负数',
            'tags.array' => '标签格式必须是数组',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'data' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray()
            ], 422)
        );
    }
}
