<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $event = Event::with('client')->find(session('event_id'));

        if (!$event) {
            return redirect()->route('client.login');
        }

        $stats = [
            'total_submissions' => $event->total_submissions,
            'total_photos' => $event->total_photos,
            'total_videos' => $event->total_videos,
            'total_voice_recordings' => $event->total_voice_recordings,
            'total_messages' => $event->total_messages,
            'total_storage' => $event->total_storage_bytes,
        ];

        $recentSubmissions = $event->submissions()
            ->where('status', Submission::STATUS_COMPLETED)
            ->orderBy('submitted_at', 'desc')
            ->limit(5)
            ->get();

        return view('client.dashboard', compact('event', 'stats', 'recentSubmissions'));
    }

    public function gallery()
    {
        $event = Event::find(session('event_id'));

        if (!$event) {
            return redirect()->route('client.login');
        }

        $media = $event->media()
            ->where('status', Media::STATUS_UPLOADED)
            ->orderBy('created_at', 'desc')
            ->paginate(48);

        return view('client.gallery', compact('event', 'media'));
    }
}
