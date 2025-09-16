<?php

namespace App\Http\Requests;

use App\Rules\Base64File;
use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
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
            'image' => ['required', 'string',
                new Base64File($allowed_mimetypes = [
                    'image/jpeg',
                    'image/png',
                    'image/svg+xml',
                    'image/webp',
                ], $allowed_extensions = [], $max_size_kb = 2048),
            ],
        ];
    }
}
