<?php

namespace App\Http\Requests;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->store);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:191',
                Rule::unique('stores', 'name')
                    ->where('address', $this->input('address'))
                    ->ignore($this->store),
            ],
            'address' => [
                'nullable', 'string', 'max:191',
                Rule::unique('stores', 'address')
                    ->where('name', $this->input('name'))
                    ->ignore($this->store),

            ],

            'is_warehouse' => 'boolean',
            'manager_staff_id' => ['nullable', 'string', 'exists:staff,id'],
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
            'manager_staff_id' => 'manager',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {

                if ($this->store->manager_staff_id != $this->input('manager_staff_id')) {
                    if (! is_null($manager = Staff::find($this->input('manager_staff_id'))) && ! is_null($manager->managedStore)) {
                        $validator->errors()->add(
                            'manager_staff_id',
                            'The staff is already a store manager of '.$manager->managedStore->name.' at '.$manager->managedStore->address
                        );
                    }
                }
            },
        ];
    }
}
