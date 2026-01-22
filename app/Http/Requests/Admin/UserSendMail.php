<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserSendMail extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'subject' => 'required',
            'content' => 'required',
            'chunk_size' => 'sometimes|integer|min:100|max:10000',
            'queue' => 'sometimes|string',
        ];
    }

    public function messages()
    {
        return [
            'subject.required' => '主题不能为空',
            'content.required' => '发送内容不能为空',
            'chunk_size.integer' => '批大小必须是整数',
            'chunk_size.min' => '批大小不能少于100',
            'chunk_size.max' => '批大小不能超过10000',
        ];
    }
}
