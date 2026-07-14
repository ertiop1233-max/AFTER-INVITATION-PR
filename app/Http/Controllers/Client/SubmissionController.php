<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Submission;
use App\Services\StorageService;
use App\Services\SubmissionService;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionService $submissionService,
        private readonly StorageService $storageService,
    ) {}

    public function index(Request $request)
    {
        $event = Event::find(session('event_id'));

        if (! $event) {
            return redirect()->route('client.login');
        }

        $query = $event->submissions()
            ->where('status', Submission::STATUS_COMPLETED)
            ->orderBy('submitted_at', 'desc');

        if ($request->filled('search')) {
            $query->where('contributor_name', 'like', $request->search.'%');
        }

        $submissions = $query->cursorPaginate(20);

        return view('client.submissions', compact('event', 'submissions'));
    }

    public function show(Submission $submission)
    {
        $event = Event::find(session('event_id'));

        if (! $event || $submission->event_id !== $event->id || ! $submission->isCompleted()) {
            abort(404);
        }

        $submission->load(['media' => function ($query) {
            $query->where('status', 'uploaded')->orderBy('media_type');
        }]);

        return view('client.submission-detail', compact('event', 'submission'));
    }

    public function destroy(Submission $submission)
    {
        $event = Event::find(session('event_id'));

        if (! $event || $submission->event_id !== $event->id) {
            abort(404);
        }

        $this->submissionService->deleteSubmission($submission);

        return redirect()
            ->route('client.submissions')
            ->with('success', 'Submission deleted.');
    }

    public function voice(Submission $submission)
    {
        $event = Event::find(session('event_id'));

        if (! $event || $submission->event_id !== $event->id || ! $submission->isCompleted()) {
            abort(404);
        }

        if (! $submission->voice_storage_id) {
            abort(404);
        }

        $stream = $this->storageService->getFileStream($submission->voice_storage_id);

        $extension = pathinfo($submission->voice_storage_path, PATHINFO_EXTENSION);
        $mimeType = match ($extension) {
            'webm' => 'audio/webm',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/mp4',
            default => 'audio/mpeg',
        };

        return response()->stream(function () use ($stream) {
            while (! $stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="voice_recording.'.$extension.'"',
            'Cache-Control' => 'private, max-age=300, must-revalidate',
        ]);
    }

    public function search(Request $request)
    {
        $event = Event::find(session('event_id'));

        if (! $event) {
            return redirect()->route('client.login');
        }

        $query = $event->submissions()
            ->where('status', Submission::STATUS_COMPLETED)
            ->orderBy('submitted_at', 'desc');

        if ($request->filled('q')) {
            $query->where('contributor_name', 'like', $request->q.'%');
        }

        $submissions = $query->cursorPaginate(20);

        return view('client.submissions', compact('event', 'submissions'));
    }
}
