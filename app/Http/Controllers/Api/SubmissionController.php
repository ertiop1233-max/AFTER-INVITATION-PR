<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSubmissionStartRequest;
use App\Http\Requests\FinalizeSubmissionRequest;
use App\Models\Event;
use App\Services\SubmissionService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionService $submissionService,
        private readonly UploadService $uploadService,
    ) {}

    public function start(CreateSubmissionStartRequest $request): JsonResponse
    {
        $event = Event::where('upload_token', $request->event_token)->first();

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        if ($event->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'This event is no longer accepting submissions.',
                'closed' => true,
            ], 410);
        }

        if ($event->isUploadDeadlinePassed()) {
            return response()->json([
                'success' => false,
                'message' => 'The upload deadline has passed.',
                'closed' => true,
            ], 410);
        }

        $result = $this->submissionService->findOrCreateDraft(
            $event,
            $request->upload_session_key,
            $request->contributor_name
        );

        if ($result['status'] === 'completed') {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'message' => 'This submission has already been completed.',
                'submission_id' => $result['submission_id'],
            ]);
        }

        if ($result['status'] === 'invalid') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session. Please start a new submission.',
            ], 410);
        }

        return response()->json([
            'success' => true,
            'status' => 'draft',
            'submission_id' => $result['submission_id'],
        ]);
    }

    public function finalize(FinalizeSubmissionRequest $request): JsonResponse
    {
        $submission = $this->submissionService->findSubmissionById($request->submission_id);

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found. Please start a new submission.',
            ], 404);
        }

        if ($submission->isCompleted()) {
            return response()->json([
                'success' => true,
                'submission_id' => $submission->id,
                'message' => 'Submission already finalized.',
            ]);
        }

        $voiceData = null;

        if ($request->filled('voice_data') && $request->filled('voice_mime_type')) {
            $voiceContent = base64_decode($request->voice_data, true);

            if ($voiceContent === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid voice data.',
                ], 422);
            }

            $voiceSize = strlen($voiceContent);
            $maxVoiceSize = config('memoryvault.voice.max_file_size_bytes', 15728640);

            if ($voiceSize > $maxVoiceSize) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voice recording exceeds maximum size.',
                ], 422);
            }

            $this->uploadService->uploadVoice(
                $submission,
                $voiceContent,
                $request->voice_mime_type,
                $request->voice_duration_seconds ?? 0
            );

            $submission = $submission->fresh();

            $voiceData = [
                'voice_storage_id' => $submission->voice_storage_id,
                'voice_storage_path' => $submission->voice_storage_path,
                'voice_duration_seconds' => $submission->voice_duration_seconds,
                'voice_size_bytes' => $submission->voice_size_bytes,
            ];
        }

        $submission = $this->submissionService->finalize($submission, $request->written_message, $voiceData);

        return response()->json([
            'success' => true,
            'submission_id' => $submission->id,
        ]);
    }
}
