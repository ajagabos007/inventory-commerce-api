<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => 'required',
            'email' => 'required|exists:users,email',
            'remember_me' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @overides \Illuminate\Foundation\Http\FormRequest fn messages()
     *
     * @return array
     */
    public function messages()
    {
        return [
            'password.required' => 'These credentials do not match our records.',
            'email.required' => 'These credentials do not match our records.',
            'email.exists' => 'These credentials do not match our records.',
        ];
    }
}
