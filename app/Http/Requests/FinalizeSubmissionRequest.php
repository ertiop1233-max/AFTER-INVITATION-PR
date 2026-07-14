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
            'voice_data' => ['nullable', 'required_with:voice_mime_type,voice_duration_seconds', 'string'],
            'voice_mime_type' => ['nullable', 'required_with:voice_data', 'string', 'in:audio/webm,audio/ogg,audio/mp4'],
            'voice_duration_seconds' => ['nullable', 'required_with:voice_data', 'integer', 'min:1', 'max:600'],
        ];
    }
}
