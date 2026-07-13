<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Submission;
use App\Services\DownloadService;

class DownloadController extends Controller
{
    public function __construct(
        private readonly DownloadService $downloadService,
    ) {}

    public function submission(Submission $submission)
    {
        $event = Event::find(session('event_id'));

        if ($submission->event_id !== $event->id) {
            abort(404);
        }

        if (!$submission->isCompleted()) {
            abort(404);
        }

        $this->downloadService->streamSubmissionZip($submission);
    }

    public function event()
    {
        $event = Event::with(['submissions' => function ($query) {
            $query->where('status', Submission::STATUS_COMPLETED);
        }])->find(session('event_id'));

        if (!$event) {
            abort(404);
        }

        if (!$this->downloadService->canDownloadEventZip($event)) {
            return back()->with('error', 'This event is too large for a single ZIP download. Please download individual submissions instead.');
        }

        $this->downloadService->streamEventZip($event);
    }
}
