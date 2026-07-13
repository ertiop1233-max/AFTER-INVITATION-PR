<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Media;
use App\Services\StorageService;
use App\Services\UploadService;

class MediaController extends Controller
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly UploadService $uploadService,
    ) {}

    public function view(Media $media)
    {
        $event = Event::find(session('event_id'));

        if ($media->event_id !== $event->id) {
            abort(404);
        }

        if (!$media->storage_id) {
            abort(404);
        }

        $stream = $this->storageService->getFileStream($media->storage_id);

        return response()->stream(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="' . $media->original_filename . '"',
        ]);
    }

    public function thumbnail(Media $media)
    {
        $event = Event::find(session('event_id'));

        if ($media->event_id !== $event->id) {
            abort(404);
        }

        if (!$media->thumbnail_storage_id) {
            abort(404);
        }

        $stream = $this->storageService->getFileStream($media->thumbnail_storage_id);

        return response()->stream(function () use ($stream) {
            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function destroy(Media $media)
    {
        $event = Event::find(session('event_id'));

        if ($media->event_id !== $event->id) {
            abort(404);
        }

        $this->uploadService->deleteMedia($media);

        return back()->with('success', 'Media deleted.');
    }
}
