<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_photos' => $this->boolean('allow_photos'),
            'allow_videos' => $this->boolean('allow_videos'),
            'allow_voice' => $this->boolean('allow_voice'),
            'allow_messages' => $this->boolean('allow_messages'),
        ]);
    }

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
            'client_name' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'min:2', 'max:100'],
            'client_email' => [$this->isMethod('post') ? 'required' : 'sometimes', 'email', 'max:255'],
            'client_password' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'min:8', 'max:100'],
        ];
    }
}
