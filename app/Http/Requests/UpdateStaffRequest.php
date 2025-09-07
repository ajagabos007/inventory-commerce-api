<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->staff);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:191'],
            'middle_name' => ['nullable', 'string', 'max:191'],
            'last_name' => ['sometimes', 'required', 'string', 'max:191'],
            'phone_number' => [
                'sometimes', 'required', 'string', 'max:191',
                Rule::unique('users', 'phone_number')
                    ->ignore($this->staff->user),
            ],
            'staff_no' => [
                'sometimes', 'required', 'string', 'max:191',
                Rule::unique('staff', 'staff_no')
                    ->ignore($this->staff),
            ],
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
