<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class PlanSave extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'language' => 'required|string|in:' . implode(',', array_keys(Plan::SUPPORTED_LANGUAGES)),
            'content' => '',
            'group_id' => 'required',
            'transfer_enable' => 'required',
            'month_price' => 'nullable|integer',
            'quarter_price' => 'nullable|integer',
            'half_year_price' => 'nullable|integer',
            'year_price' => 'nullable|integer',
            'two_year_price' => 'nullable|integer',
            'three_year_price' => 'nullable|integer',
            'onetime_price' => 'nullable|integer',
            'reset_price' => 'nullable|integer',
            'reset_traffic_method' => 'nullable|integer|in:0,1,2,3,4',
            'capacity_limit' => 'nullable|integer',
            'speed_limit' => 'nullable|integer'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '名称不能为空',
            'language.required' => '语言不能为空',
            'language.in' => '不支持的语言',
            'group_id.required' => '分组不能为空',
            'transfer_enable.required' => '流量不能为空',
            'month_price.integer' => '月付价格格式有误',
            'quarter_price.integer' => '季付价格格式有误',
            'half_year_price.integer' => '半年付价格格式有误',
            'year_price.integer' => '年付价格格式有误',
            'two_year_price.integer' => '两年付价格格式有误',
            'three_year_price.integer' => '三年付价格格式有误',
            'onetime_price.integer' => '一次性价格格式有误',
            'reset_price.integer' => '重置流量价格格式有误',
            'reset_traffic_method.integer' => '重置流量方式格式有误',
            'reset_traffic_method.in' => '重置流量方式格式有误',
            'capacity_limit.integer' => '容量限制格式有误',
            'speed_limit.integer' => '速度限制格式有误'
        ];
    }
}
