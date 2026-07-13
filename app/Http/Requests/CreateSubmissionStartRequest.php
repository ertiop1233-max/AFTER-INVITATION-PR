<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubmissionStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_token' => ['required', 'string', 'max:64'],
            'upload_session_key' => ['required', 'string', 'max:64'],
            'contributor_name' => ['required', 'min:4', 'max:100', 'regex:/^[\pL\pN\s]+$/u'],
            'nonce' => ['required', 'string'],
        ];
    }
}
