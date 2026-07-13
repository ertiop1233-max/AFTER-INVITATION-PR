@extends('layouts.client')

@section('client-content')
<div class="client-header">
    <h1>Submissions</h1>
</div>

<form class="search-box" method="GET" action="{{ route('client.search') }}">
    <input class="input" type="text" name="q" placeholder="Search by contributor name..." value="{{ request('q') }}">
    <button type="submit" class="btn btn-secondary">Search</button>
</form>

@if($submissions->isEmpty())
    <div class="empty-state">
        <h3>No submissions found</h3>
        <p>Contributions will appear here once guests start submitting.</p>
    </div>
@else
    <div class="submission-list">
        @foreach($submissions as $submission)
            <div class="submission-item">
                <a href="{{ route('client.submissions.show', $submission) }}" style="text-decoration:none;color:inherit;flex:1">
                    <div class="submission-info">
                        <span class="submission-name">{{ $submission->contributor_name }}</span>
                        <span class="submission-meta">
                            {{ $submission->total_photos }} photos, {{ $submission->total_videos }} videos
                            @if($submission->hasVoice()) &middot; voice @endif
                            @if($submission->hasMessage()) &middot; message @endif
                            &middot; {{ $submission->submitted_at?->format('M j, Y') }}
                        </span>
                    </div>
                </a>
                <div style="display:flex;gap:var(--space-2)">
                    <a href="{{ route('client.download.submission', $submission) }}" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Download</a>
                    <form method="POST" action="{{ route('client.submissions.destroy', $submission) }}" onsubmit="return confirm('Delete this submission permanently?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="min-height:auto;padding:var(--space-2) var(--space-3)">Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    @if(method_exists($submissions, 'previousPageUrl'))
        <div class="pagination">
            @if($submissions->previousPageUrl())
                <a href="{{ $submissions->previousPageUrl() }}">&laquo; Previous</a>
            @endif
            @if($submissions->nextPageUrl())
                <a href="{{ $submissions->nextPageUrl() }}">Next &raquo;</a>
            @endif
        </div>
    @endif
@endif
@endsection
