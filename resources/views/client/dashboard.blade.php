@extends('layouts.client')

@section('client-content')
<div class="client-header">
    <div>
        <h1>{{ $event->title }}</h1>
        <p style="color:var(--color-text-muted)">{{ $event->event_type }}</p>
    </div>
    <div class="admin-actions">
        <a href="{{ route('client.gallery') }}" class="btn btn-secondary">Gallery</a>
        <a href="{{ route('client.download.event') }}" class="btn btn-secondary">Download All</a>
    </div>
</div>

<div class="stat-grid" style="margin-bottom:var(--space-8)">
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_submissions'] }}</div>
        <div class="stat-label">Submissions</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_photos'] }}</div>
        <div class="stat-label">Photos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_videos'] }}</div>
        <div class="stat-label">Videos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_voice_recordings'] }}</div>
        <div class="stat-label">Voice</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_messages'] }}</div>
        <div class="stat-label">Messages</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ number_format($stats['total_storage'] / 1073741824, 2) }} GB</div>
        <div class="stat-label">Storage</div>
    </div>
</div>

<h2 style="margin-bottom:var(--space-4)">Recent Submissions</h2>
@if($recentSubmissions->isEmpty())
    <div class="empty-state">
        <h3>No submissions yet</h3>
        <p>Memories will appear here once guests start contributing.</p>
    </div>
@else
    <div class="submission-list">
        @foreach($recentSubmissions as $submission)
            <a href="{{ route('client.submissions.show', $submission) }}" class="submission-item" style="text-decoration:none;color:inherit">
                <div class="submission-info">
                    <span class="submission-name">{{ $submission->contributor_name }}</span>
                    <span class="submission-meta">{{ $submission->total_photos }} photos, {{ $submission->total_videos }} videos &middot; {{ $submission->submitted_at?->diffForHumans() }}</span>
                </div>
            </a>
        @endforeach
    </div>
    <div style="margin-top:var(--space-4)">
        <a href="{{ route('client.submissions') }}" class="btn btn-secondary">View All</a>
    </div>
@endif
@endsection
