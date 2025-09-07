<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddScrapeToInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->scrape);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
