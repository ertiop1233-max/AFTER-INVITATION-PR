<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_type' => ['required', 'string', 'max:50'],
            'upload_deadline' => ['nullable', 'date', 'after:now'],
            'max_submission_size_bytes' => ['nullable', 'integer', 'min:1048576', 'max:2147483648'],
            'allow_photos' => ['boolean'],
            'allow_videos' => ['boolean'],
            'allow_voice' => ['boolean'],
            'allow_messages' => ['boolean'],
            'client_name' => ['required', 'string', 'min:2', 'max:100'],
            'client_email' => ['required', 'email', 'max:255'],
            'client_password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
