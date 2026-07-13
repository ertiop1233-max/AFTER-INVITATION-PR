<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_id' => ['required', 'integer'],
            'written_message' => ['nullable', 'string', 'max:5000'],
            'voice_data' => ['nullable', 'string'],
            'voice_mime_type' => ['nullable', 'string', 'in:audio/webm,audio/ogg,audio/mp4'],
            'voice_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }
}
