@extends('layouts.client')

@section('client-content')
<div class="client-header">
    <h1>Gallery</h1>
</div>

@if($media->isEmpty())
    <div class="empty-state">
        <h3>No media yet</h3>
        <p>Photos and videos will appear here once guests start contributing.</p>
    </div>
@else
    <div class="gallery-grid">
        @foreach($media as $item)
            <div class="gallery-item" onclick="mediaViewer.open([@json($media->map(fn($m) => ['type' => $m->isVideo() ? 'video' : 'image', 'url' => route('client.media.view', $m), 'name' => $m->original_filename])->values())], {{ $loop->index }})">
                @if($item->thumbnail_storage_id)
                    <img src="{{ route('client.media.thumbnail', $item) }}" alt="{{ $item->original_filename }}" loading="lazy">
                @else
                    <img src="{{ route('client.media.view', $item) }}" alt="{{ $item->original_filename }}" loading="lazy">
                @endif
                <div class="gallery-item-overlay">
                    {{ $item->isVideo() ? 'Video' : 'Photo' }}
                </div>
            </div>
        @endforeach
    </div>
    {{ $media->links() }}
@endif
@endsection
