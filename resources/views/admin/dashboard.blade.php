@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Dashboard</h1>
    <a href="{{ route('admin.events.create') }}" class="btn btn-primary">Create Event</a>
</div>

<div class="stat-grid" style="margin-bottom:var(--space-8)">
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_events'] }}</div>
        <div class="stat-label">Total Events</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['active_events'] }}</div>
        <div class="stat-label">Active</div>
    </div>
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
</div>

<h2 style="margin-bottom:var(--space-4)">Recent Events</h2>
@if($events->isEmpty())
    <div class="empty-state">
        <h3>No events yet</h3>
        <p>Create your first event to get started.</p>
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:var(--space-3)">
        @foreach($events as $event)
            <div class="event-card">
                <div class="event-card-title">{{ $event->title }}</div>
                <div class="event-card-meta">
                    <span>{{ $event->event_type }}</span>
                    <span class="badge badge-{{ $event->status === 'active' ? 'success' : ($event->status === 'closed' ? 'muted' : 'warning') }}">{{ ucfirst($event->status) }}</span>
                    <span>{{ $event->total_submissions }} submissions</span>
                </div>
                <div class="event-card-actions">
                    <a href="{{ route('admin.events.show', $event) }}" class="btn btn-secondary" style="min-height:auto;padding:var(--space-2) var(--space-3)">View</a>
                    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Edit</a>
                </div>
            </div>
        @endforeach
    </div>
    {{ $events->links() }}
@endif
@endsection
