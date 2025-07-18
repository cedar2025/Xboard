<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class GiftCardRedeemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'code' => 'required|string|min:8|max:32',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'code.required' => '请输入兑换码',
            'code.min' => '兑换码长度不能少于8位',
            'code.max' => '兑换码长度不能超过32位',
        ];
    }
}
