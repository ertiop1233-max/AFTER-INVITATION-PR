<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadInitRequest extends FormRequest
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    protected function prepareForValidation(): void
    {
        $mimeType = (string) $this->input('mime_type');
        $filename = basename(str_replace('\\', '/', (string) $this->input('original_filename')));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?: 'upload';

        $this->merge([
            'original_filename' => $filename,
            'extension' => self::MIME_EXTENSIONS[$mimeType] ?? '',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'integer'],
            'original_filename' => ['required', 'string', 'max:500'],
            'mime_type' => ['required', 'string', Rule::in(array_keys(self::MIME_EXTENSIONS))],
            'extension' => ['required', Rule::in(array_values(self::MIME_EXTENSIONS))],
            'file_size_bytes' => ['required', 'integer', 'min:1'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
