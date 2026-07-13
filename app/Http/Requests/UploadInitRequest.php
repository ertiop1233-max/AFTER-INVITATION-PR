<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadInitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'integer'],
            'original_filename' => ['required', 'string', 'max:500'],
            'mime_type' => ['required', 'string', 'max:100'],
            'extension' => ['required', 'string', 'max:20'],
            'file_size_bytes' => ['required', 'integer', 'min:1'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
