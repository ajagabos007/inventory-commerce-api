<?php

namespace App\Http\Requests;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Staff::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'last_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:191', 'unique:users,phone_number'],
            'staff_no' => ['nullable', 'string', 'max:191', 'unique:staff,staff_no'],
            'store_id' => ['nullable', 'string', 'max:191', 'exists:stores,id'],
            'role_id' => ['nullable', 'string', 'max:191', 'exists:roles,id'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_id' => 'store',
        ];
    }
}
