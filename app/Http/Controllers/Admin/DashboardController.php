<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;

class DashboardController extends Controller
{
    public function index()
    {
        $events = Event::orderBy('created_at', 'desc')->paginate(20);

        $stats = [
            'total_events' => Event::count(),
            'active_events' => Event::where('status', Event::STATUS_ACTIVE)->count(),
            'closed_events' => Event::where('status', Event::STATUS_CLOSED)->count(),
            'total_submissions' => Event::sum('total_submissions'),
            'total_photos' => Event::sum('total_photos'),
            'total_videos' => Event::sum('total_videos'),
        ];

        return view('admin.dashboard', compact('events', 'stats'));
    }
}
