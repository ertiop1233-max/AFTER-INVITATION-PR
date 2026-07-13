@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <div>
        <h1>{{ $event->title }}</h1>
        <p style="color:var(--color-text-muted)">{{ $event->event_type }}</p>
    </div>
    <div class="admin-actions">
        <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-secondary">Edit</a>
        <a href="{{ route('admin.events.credentials', $event) }}" class="btn btn-secondary">Credentials</a>
        @if($event->status === 'active')
            <form method="POST" action="{{ route('admin.events.close', $event) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Close Event</button>
            </form>
        @elseif($event->status === 'closed')
            <form method="POST" action="{{ route('admin.events.reopen', $event) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Reopen Event</button>
            </form>
        @endif
    </div>
</div>

<div class="stat-grid" style="margin-bottom:var(--space-6)">
    <div class="stat-card">
        <div class="stat-value">{{ $event->total_submissions }}</div>
        <div class="stat-label">Submissions</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $event->total_photos }}</div>
        <div class="stat-label">Photos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $event->total_videos }}</div>
        <div class="stat-label">Videos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $event->total_voice_recordings }}</div>
        <div class="stat-label">Voice</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ number_format($event->total_storage_bytes / 1073741824, 2) }} GB</div>
        <div class="stat-label">Storage</div>
    </div>
</div>

<div class="card" style="margin-bottom:var(--space-4)">
    <h3 style="margin-bottom:var(--space-3)">Upload Link</h3>
    <p style="font-size:var(--font-size-sm);color:var(--color-text-secondary);margin-bottom:var(--space-3)">Share this link with guests to collect memories:</p>
    <div style="display:flex;gap:var(--space-2);align-items:center">
        <input class="input" type="text" readonly value="{{ route('upload.page', ['slug' => $event->upload_slug, 'token' => $event->upload_token]) }}" id="uploadLink" onclick="this.select()">
        <button class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('uploadLink').value); this.textContent='Copied!'" type="button">Copy</button>
    </div>
</div>

<div class="card" style="margin-bottom:var(--space-4)">
    <h3 style="margin-bottom:var(--space-3)">QR Code</h3>
    @if($hasQr)
        <div style="display:flex;gap:var(--space-3);align-items:center;flex-wrap:wrap">
            <a href="{{ route('admin.events.qr', $event) }}" class="btn btn-primary">Download QR</a>
            <form method="POST" action="{{ route('admin.events.qr.regenerate', $event) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Regenerate QR</button>
            </form>
        </div>
    @else
        <p style="color:var(--color-text-muted);margin-bottom:var(--space-3)">No QR code has been generated yet.</p>
        <form method="POST" action="{{ route('admin.events.qr.regenerate', $event) }}">
            @csrf
            <button type="submit" class="btn btn-primary">Generate QR Code</button>
        </form>
    @endif
</div>

@if($event->description)
<div class="card" style="margin-bottom:var(--space-4)">
    <h3 style="margin-bottom:var(--space-2)">Description</h3>
    <p style="color:var(--color-text-secondary)">{{ $event->description }}</p>
</div>
@endif

<div class="card" style="margin-bottom:var(--space-4)">
    <h3 style="margin-bottom:var(--space-3)">Recent Submissions</h3>
    @if($event->submissions->isEmpty())
        <p style="color:var(--color-text-muted)">No submissions yet.</p>
    @else
        <div class="submission-list">
            @foreach($event->submissions as $submission)
                <div class="submission-item">
                    <div class="submission-info">
                        <span class="submission-name">{{ $submission->contributor_name }}</span>
                        <span class="submission-meta">{{ $submission->total_photos }} photos, {{ $submission->total_videos }} videos &middot; {{ $submission->submitted_at?->diffForHumans() }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div style="margin-top:var(--space-8);padding-top:var(--space-6);border-top:1px solid var(--color-border-default)">
    <a href="{{ route('admin.events.confirm-delete', $event) }}" class="btn btn-danger">Delete Event Permanently</a>
</div>
@endsection
