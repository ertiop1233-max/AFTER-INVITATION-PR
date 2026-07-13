@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Delete Event</h1>
    <a href="{{ route('admin.events.show', $event) }}" class="btn btn-ghost">Cancel</a>
</div>

<div class="alert alert-error">
    <strong>Warning:</strong> This action is permanent and cannot be undone. All submissions, media, voice recordings, and messages will be permanently deleted.
</div>

<div class="card" style="margin-bottom:var(--space-4)">
    <h3 style="margin-bottom:var(--space-3)">Event to Delete</h3>
    <dl style="display:grid;grid-template-columns:max-content 1fr;gap:var(--space-2) var(--space-4)">
        <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Title</dt>
        <dd style="font-weight:var(--font-weight-medium)">{{ $event->title }}</dd>
        <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Type</dt>
        <dd>{{ $event->event_type }}</dd>
        <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Submissions</dt>
        <dd>{{ $event->total_submissions }}</dd>
        <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Media</dt>
        <dd>{{ $event->total_photos + $event->total_videos }} files</dd>
    </dl>
</div>

<div class="card">
    <h3 style="margin-bottom:var(--space-3)">Confirm Deletion</h3>
    <p style="font-size:var(--font-size-sm);color:var(--color-text-secondary);margin-bottom:var(--space-4)">
        To confirm, type the event title <code style="color:var(--color-accent-text);font-weight:var(--font-weight-semibold)">{{ $event->title }}</code> below:
    </p>

    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            <ul style="margin:0;padding-left:var(--space-4)">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.events.destroy', $event) }}">
        @csrf
        @method('DELETE')
        <div class="form-group">
            <label class="label" for="confirm_title">Type the event title to confirm</label>
            <input
                class="input"
                type="text"
                id="confirm_title"
                name="confirm_title"
                placeholder="{{ $event->title }}"
                autocomplete="off"
                required
                autofocus
            >
        </div>
        <button type="submit" class="btn btn-danger">Delete Permanently</button>
    </form>
</div>
@endsection
