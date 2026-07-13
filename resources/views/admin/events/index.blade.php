@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Events</h1>
    <a href="{{ route('admin.events.create') }}" class="btn btn-primary">Create Event</a>
</div>

<form class="filter-bar" method="GET">
    <input class="input" type="text" name="search" placeholder="Search by title..." value="{{ request('search') }}">
    <select class="select" name="status">
        <option value="">All Statuses</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
</form>

@if($events->isEmpty())
    <div class="empty-state">
        <h3>No events found</h3>
        <p>Create a new event or adjust your filters.</p>
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
                    <span>{{ $event->total_photos + $event->total_videos }} media</span>
                </div>
                <div class="event-card-actions">
                    <a href="{{ route('admin.events.show', $event) }}" class="btn btn-secondary" style="min-height:auto;padding:var(--space-2) var(--space-3)">View</a>
                    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Edit</a>
                    @if($event->status === 'active')
                        <form method="POST" action="{{ route('admin.events.close', $event) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Close</button>
                        </form>
                    @elseif($event->status === 'closed')
                        <form method="POST" action="{{ route('admin.events.reopen', $event) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Reopen</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    {{ $events->links() }}
@endif
@endsection
