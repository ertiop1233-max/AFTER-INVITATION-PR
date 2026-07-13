<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerifyUploadNonce;
use App\Models\Event;
use Illuminate\Http\Request;

class UploadPageController extends Controller
{
    public function show(Request $request, string $slug, string $token)
    {
        $event = Event::where('upload_token', $token)->first();

        if (!$event) {
            return view('upload.not-found');
        }

        if ($event->upload_slug !== $slug) {
            return redirect()->route('upload.page', [
                'slug' => $event->upload_slug,
                'token' => $event->upload_token,
            ]);
        }

        if ($event->isClosed() || $event->isUploadDeadlinePassed()) {
            return view('upload.closed', ['event' => $event]);
        }

        $nonce = VerifyUploadNonce::generateNonce($event->upload_token);

        return view('upload.page', [
            'event' => $event,
            'uploadToken' => $event->upload_token,
            'uploadNonce' => $nonce,
            'uploadStrategy' => config('memoryvault.upload_strategy'),
            'maxSubmissionBytes' => $event->max_submission_size_bytes,
            'maxConcurrent' => config('memoryvault.upload_max_concurrent', 3),
            'maxRetries' => config('memoryvault.upload_max_retries', 3),
            'chunkSize' => config('memoryvault.upload_chunk_size', 8388608),
            'voiceMaxDuration' => config('memoryvault.voice.max_duration_seconds', 600),
            'voiceMaxSize' => config('memoryvault.voice.max_file_size_bytes', 15728640),
        ]);
    }
}
