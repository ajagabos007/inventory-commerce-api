<?php

namespace App\Http\Requests;

use App\Models\PaymentGateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentGatewayConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->payment_gateway_config);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<string,mixed>|string>
     */
    public function rules(): array
    {
        $gateway = PaymentGateway::find($this->input('payment_gateway_id'))
            ?? $this->input('payment_gateway_config')->gateway;

        $rules = [
            'payment_gateway_id' => ['sometimes', 'required', 'exists:payment_gateways,id'],
            'mode' => [
                'required',
                'in:test,live',
                Rule::unique('payment_gateway_configs')
                    ->where(fn ($query) => $query->where('payment_gateway_id', $this->payment_gateway_id))
                    ->ignore($this->route('payment_gateway_config')),
            ],
            'credentials' => ['required', 'array'],
            'settings' => ['nullable', 'array'],
        ];

        // Build dynamic credential validation rules
        if (! empty($gateway->credential_schema['fields'])) {
            foreach ($gateway->credential_schema['fields'] as $field) {
                $rules["credentials.{$field['key']}"] = $field['validation'] ?? 'nullable';
            }
        }

        // Build dynamic setting validation rules
        if (! empty($gateway->setting_schema['fields'])) {
            foreach ($gateway->setting_schema['fields'] as $field) {
                $rules["settings.{$field['key']}"] = $field['validation'] ?? 'nullable';
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'payment_gateway_id' => 'payment gateway.',
        ];
    }

    public function messages(): array
    {
        return [
            'mode.unique' => 'A configuration for this gateway and mode already exists.',
        ];
    }
}
