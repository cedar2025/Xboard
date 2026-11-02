<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;

trait NormalizesEmail
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => User::normalizeEmail($email)]);
        }
    }
}
