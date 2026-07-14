<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEventRequest;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(Request $request)
    {
        $query = Event::query();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.events.create');
    }

    public function store(CreateEventRequest $request)
    {
        try {
            $event = $this->eventService->createEvent($request->validated());

            $this->eventService->generateQrCode($event);

            return redirect()
                ->route('admin.events.show', $event)
                ->with('success', 'Event created successfully.');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Unable to create event. Please try again.');
        }
    }

    public function show(Event $event)
    {
        $event->load(['client', 'submissions' => function ($query) {
            $query->where('status', 'completed')->orderBy('submitted_at', 'desc')->limit(10);
        }]);

        $hasQr = $this->eventService->hasQrCode($event);

        return view('admin.events.show', compact('event', 'hasQr'));
    }

    public function edit(Event $event)
    {
        return view('admin.events.edit', compact('event'));
    }

    public function update(CreateEventRequest $request, Event $event)
    {
        $this->eventService->updateEvent($event, $request->validated());

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    public function confirmDelete(Event $event)
    {
        return view('admin.events.delete', compact('event'));
    }

    public function destroy(Request $request, Event $event)
    {
        $confirmed = $request->input('confirm_title');

        if ($confirmed !== $event->title) {
            return back()
                ->with('error', 'Confirmation failed. You must type the exact event title to delete.')
                ->withInput();
        }

        $this->eventService->deleteEvent($event);

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Event deleted permanently.');
    }

    public function close(Event $event)
    {
        $this->eventService->closeEvent($event);

        return back()->with('success', 'Event closed.');
    }

    public function reopen(Event $event)
    {
        $this->eventService->reopenEvent($event);

        return back()->with('success', 'Event reopened.');
    }

    public function credentials(Event $event)
    {
        $event->load('client');

        return view('admin.events.credentials', compact('event'));
    }

    public function resetPassword(Request $request, Event $event)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $this->eventService->resetClientPassword($event, $request->password);

        return back()->with('success', 'Client password reset successfully.');
    }

    public function downloadQr(Event $event)
    {
        $stream = $this->eventService->getQrCodeStream($event);

        if (! $stream) {
            return back()->with('error', 'QR code not found. Try regenerating it.');
        }

        return response()->stream(function () use ($stream) {
            while (! $stream->eof()) {
                echo $stream->read(8192);
                flush();
            }
        }, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr_'.$event->upload_slug.'.png"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function regenerateQr(Event $event)
    {
        $fileId = $this->eventService->generateQrCode($event);

        if ($fileId) {
            return back()->with('success', 'QR code regenerated successfully.');
        }

        return back()->with('error', 'QR code regeneration failed. Please try again.');
    }
}
