<?php

namespace App\Http\Requests;

class LoginRequest extends \Laravel\Fortify\Http\Requests\LoginRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'required_without:login_id', 'string', 'max:255'],
            'login_id' => ['nullable', 'required_without:email', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
