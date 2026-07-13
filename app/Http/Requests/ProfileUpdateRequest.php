<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'loginname' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('username', 'loginname')->ignore($user->getKey(), $user->getKeyName()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'loginname.required' => 'Username is required.',
            'loginname.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'loginname.unique' => 'This username is already taken.',
        ];
    }
}