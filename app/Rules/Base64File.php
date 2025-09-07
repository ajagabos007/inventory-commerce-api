<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Base64File implements ValidationRule
{
    /**
     * @var array<int,string>
     */
    private array $allowed_mimetypes;

    /**
     * @var array<int,string>
     */
    private array $allowed_extensions;

    private int $max_size_in_kb;

    private string $message = 'The :attribute must be a valid base64-encoded file.';

    /**
     * Create a new rule instance.
     *
     * @param  array<int,string>  $allowed_mimetypes
     * @param  array<int,string>  $allowed_extensions
     * @return void
     */
    public function __construct(array $allowed_mimetypes = [], array $allowed_extensions = [], int $max_size_in_kb = 1024)
    {
        $this->allowed_mimetypes = $allowed_mimetypes;
        $this->allowed_extensions = $allowed_extensions;
        $this->max_size_in_kb = $max_size_in_kb;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if the value is a valid base64 string
        $parts = explode(';base64,', $value);
        if (count($parts) !== 2) {
            $fail('The :attribute must be a valid base64-encoded file.');

            return;
        }

        $mime_type = str_replace('data:', '', $parts[0]);
        $file_data = $parts[1];

        if (count($this->allowed_mimetypes)) {
            // Check MIME type
            if (! in_array($mime_type, $this->allowed_mimetypes)) {
                $fail('The :attribute type must be one of: '.implode(', ', $this->allowed_mimetypes).'.');

                return;
            }
        }

        if (count($this->allowed_extensions)) {
            $extension = explode('/', $mime_type)[1];
            // Check MIME type
            if (! in_array($extension, $this->allowed_extensions)) {
                $fail('The :attribute type must be one of: '.implode(', ', $this->allowed_extensions).'.');

                return;
            }
        }

        $decoded_file = base64_decode($file_data, true);
        if (! $decoded_file) {
            $fail('The :attribute contains invalid base64-encoded data.');

            return;
        }

        // Check file size
        if (strlen($decoded_file) / 1024 > $this->max_size_in_kb) {
            $fail('The :attribute may not be larger than '.($this->max_size_in_kb).' KB.');
        }
    }
}
