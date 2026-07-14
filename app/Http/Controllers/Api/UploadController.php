<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadCompleteRequest;
use App\Http\Requests\UploadInitRequest;
use App\Models\Event;
use App\Models\Media;
use App\Services\UploadService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private readonly UploadService $uploadService,
    ) {}

    public function eventInfo(string $token): JsonResponse
    {
        $event = Event::where('upload_token', $token)->first();

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'title' => $event->title,
            'event_type' => $event->event_type,
            'status' => $event->status,
            'allow_photos' => $event->allow_photos,
            'allow_videos' => $event->allow_videos,
            'allow_voice' => $event->allow_voice,
            'allow_messages' => $event->allow_messages,
            'upload_deadline' => $event->upload_deadline?->toISOString(),
            'max_submission_size_bytes' => $event->max_submission_size_bytes,
            'upload_strategy' => config('memoryvault.upload_strategy'),
        ]);
    }

    public function init(UploadInitRequest $request): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('upload_event');
        $submission = $event->submissions()->find($request->submission_id);

        if (! $submission || ! $submission->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found or already completed.',
            ], 404);
        }

        if ($event->isClosed() || $event->isUploadDeadlinePassed()) {
            return $this->eventClosedResponse();
        }

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif',
            'video/mp4', 'video/quicktime', 'video/webm',
        ];

        if (! in_array($request->mime_type, $allowedMimes)) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported file type.',
            ], 422);
        }

        if (str_starts_with($request->mime_type, 'image/') && ! $event->allow_photos) {
            return response()->json([
                'success' => false,
                'message' => 'Photos are not allowed for this event.',
            ], 422);
        }

        if (str_starts_with($request->mime_type, 'video/') && ! $event->allow_videos) {
            return response()->json([
                'success' => false,
                'message' => 'Videos are not allowed for this event.',
            ], 422);
        }

        try {
            $result = $this->uploadService->initUpload($submission, $request->validated());

            $response = [
                'success' => true,
                'media_id' => $result['media']->id,
            ];

            if ($result['upload_uri'] !== null) {
                $response['upload_uri'] = $result['upload_uri'];
            }

            return response()->json($response);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to initialize upload. Please try again.',
            ], 500);
        }
    }

    public function complete(UploadCompleteRequest $request): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('upload_event');
        if ($event->isClosed() || $event->isUploadDeadlinePassed()) {
            return $this->eventClosedResponse();
        }

        $media = $event->media()->find($request->media_id);

        if (! $media || ! $media->submission?->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Media not found.',
            ], 404);
        }

        if ($media->isUploaded()) {
            return response()->json(['success' => true]);
        }

        try {
            $this->uploadService->completeUploadByLookup($media);

            return response()->json(['success' => true]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload verification failed. Please try again.',
            ], 500);
        }
    }

    public function chunk(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'offset' => ['required', 'integer', 'min:0'],
            'total_size' => ['required', 'integer', 'min:1'],
        ]);

        /** @var Event $event */
        $event = $request->attributes->get('upload_event');
        if ($event->isClosed() || $event->isUploadDeadlinePassed()) {
            return $this->eventClosedResponse();
        }

        $media = $event->media()->find($request->media_id);

        if (! $media || ! $media->submission?->isDraft() || $media->status !== Media::STATUS_UPLOADING) {
            return response()->json([
                'success' => false,
                'message' => 'Media not found.',
            ], 404);
        }

        $chunkData = $request->getContent();
        $offset = (int) $request->offset;
        $totalSize = (int) $request->total_size;
        $chunkLength = strlen($chunkData);
        $maxChunkSize = config('memoryvault.upload_chunk_size', 8388608);

        if ($chunkLength < 1
            || $chunkLength > $maxChunkSize
            || $totalSize !== $media->file_size_bytes
            || $offset >= $totalSize
            || $offset + $chunkLength > $totalSize) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid upload chunk size or byte range.',
            ], 422);
        }

        try {
            $result = $this->uploadService->processChunk($media, $chunkData, $offset, $totalSize);

            return response()->json([
                'success' => true,
                'completed' => $result['completed'],
                'uploaded_bytes' => $result['uploaded_bytes'] ?? 0,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chunk upload failed. Please try again.',
            ], 500);
        }
    }

    public function thumbnail(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'thumbnail_data' => ['required', 'string', 'max:2097152'],
        ]);

        /** @var Event $event */
        $event = $request->attributes->get('upload_event');
        if ($event->isClosed() || $event->isUploadDeadlinePassed()) {
            return $this->eventClosedResponse();
        }

        $media = $event->media()->find($request->media_id);

        if (! $media || ! $media->submission?->isDraft() || ! $media->isUploaded()) {
            return response()->json([
                'success' => false,
                'message' => 'Media not found.',
            ], 404);
        }

        $thumbnailData = base64_decode($request->thumbnail_data, true);

        if ($thumbnailData === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid thumbnail data.',
            ], 422);
        }

        if (strlen($thumbnailData) > 1572864) {
            return response()->json([
                'success' => false,
                'message' => 'Thumbnail data is too large.',
            ], 422);
        }

        $this->uploadService->uploadThumbnail($media, $thumbnailData);

        return response()->json(['success' => true]);
    }

    public function voice(Request $request): JsonResponse
    {
        $request->validate([
            'submission_id' => ['required', 'integer'],
            'voice_data' => ['required', 'string'],
            'mime_type' => ['required', 'string', 'in:audio/webm,audio/ogg,audio/mp4'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:600'],
        ]);

        /** @var Event $event */
        $event = $request->attributes->get('upload_event');
        if ($event->isClosed() || $event->isUploadDeadlinePassed()) {
            return $this->eventClosedResponse();
        }

        if (! $event->allow_voice) {
            return response()->json([
                'success' => false,
                'message' => 'Voice messages are not allowed for this event.',
            ], 422);
        }

        $submission = $event->submissions()->find($request->submission_id);

        if (! $submission || ! $submission->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'Submission not found.',
            ], 404);
        }

        $voiceContent = base64_decode($request->voice_data, true);

        if ($voiceContent === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid voice data.',
            ], 422);
        }

        $maxSize = config('memoryvault.voice.max_file_size_bytes', 15728640);

        if (strlen($voiceContent) > $maxSize) {
            return response()->json([
                'success' => false,
                'message' => 'Voice recording exceeds maximum size.',
            ], 422);
        }

        try {
            $this->uploadService->uploadVoice(
                $submission,
                $voiceContent,
                $request->mime_type,
                $request->duration_seconds
            );

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voice upload failed. Please try again.',
            ], 500);
        }
    }

    private function eventClosedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This event is no longer accepting submissions.',
            'closed' => true,
        ], 410);
    }
}
