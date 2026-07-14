@extends('layouts.client')

@section('client-content')
<div class="client-header">
    <div>
        <h1>{{ $submission->contributor_name }}</h1>
        <p style="color:var(--color-text-muted)">{{ $submission->submitted_at?->format('F j, Y \a\t g:i A') }}</p>
    </div>
    <div class="admin-actions">
        <a href="{{ route('client.download.submission', $submission) }}" class="btn btn-secondary">Download ZIP</a>
        <form method="POST" action="{{ route('client.submissions.destroy', $submission) }}" onsubmit="return confirm('Delete this submission permanently?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>

<div class="submission-detail">
    <div class="submission-sidebar">
        @if($submission->hasMessage())
        <div class="card">
            <h3 style="margin-bottom:var(--space-3)">Message</h3>
            <div class="message-box">{{ $submission->written_message }}</div>
        </div>
        @endif

        @if($submission->hasVoice())
        <div class="card">
            <h3 style="margin-bottom:var(--space-3)">Voice Recording</h3>
            <div class="voice-player">
                <audio controls style="width:100%">
                    <source src="{{ route('client.submissions.voice', $submission) }}" type="audio/webm">
                </audio>
            </div>
            <p style="font-size:var(--font-size-xs);color:var(--color-text-muted);margin-top:var(--space-2)">Duration: {{ gmdate('i:s', $submission->voice_duration_seconds) }}</p>
        </div>
        @endif

        <div class="card">
            <h3 style="margin-bottom:var(--space-3)">Details</h3>
            <dl style="display:grid;grid-template-columns:max-content 1fr;gap:var(--space-2) var(--space-4)">
                <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Photos</dt>
                <dd>{{ $submission->total_photos }}</dd>
                <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Videos</dt>
                <dd>{{ $submission->total_videos }}</dd>
                <dt style="color:var(--color-text-muted);font-size:var(--font-size-sm)">Size</dt>
                <dd>{{ number_format($submission->total_size_bytes / 1048576, 2) }} MB</dd>
            </dl>
        </div>
    </div>

    <div>
        <h3 style="margin-bottom:var(--space-4)">Media</h3>
        @if($submission->media->isEmpty())
            <div class="empty-state">
                <h3>No media</h3>
            </div>
        @else
            @php
                $mediaItems = $submission->media->map(fn($m) => [
                    'type' => $m->isVideo() ? 'video' : 'image',
                    'url' => route('client.media.view', $m),
                    'thumbnail_url' => $m->thumbnail_storage_id ? route('client.media.thumbnail', $m) : null,
                    'name' => $m->original_filename,
                    'mime_type' => $m->mime_type,
                    'download_url' => route('client.media.view', $m),
                ])->values()->all();
            @endphp
            <div class="gallery-grid">
                @foreach($submission->media as $media)
                    <div
                        class="gallery-item"
                        onclick="mediaViewer.open(@json($mediaItems), {{ $loop->index }})"
                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();mediaViewer.open(@json($mediaItems), {{ $loop->index }})}"
                        tabindex="0"
                        role="button"
                        aria-label="View {{ $media->original_filename }}"
                    >
                        @if($media->thumbnail_storage_id)
                            <img src="{{ route('client.media.thumbnail', $media) }}" alt="{{ $media->original_filename }}" loading="lazy">
                        @else
                            <img src="{{ route('client.media.view', $media) }}" alt="{{ $media->original_filename }}" loading="lazy">
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection