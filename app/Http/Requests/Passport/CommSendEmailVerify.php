<?php

namespace App\Http\Requests\Passport;

use Illuminate\Foundation\Http\FormRequest;

class CommSendEmailVerify extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|string|email:strict|max:64|regex:/\A[\x21-\x7E]+\z/'
        ];
    }

    public function messages()
    {
        return [
            'email.required' => __('Email can not be empty'),
            'email.string' => __('Email format is incorrect'),
            'email.email' => __('Email format is incorrect'),
            'email.max' => __('Email format is incorrect'),
            'email.regex' => __('Email format is incorrect')
        ];
    }
}
