@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Edit Event</h1>
    <a href="{{ route('admin.events.show', $event) }}" class="btn btn-ghost">Back</a>
</div>

@if($errors->any())
    <div class="alert alert-error">
        <ul style="margin:0;padding-left:var(--space-4)">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.events.update', $event) }}">
    @csrf
    @method('PUT')
    <div class="card" style="margin-bottom:var(--space-4)">
        <div class="form-group">
            <label class="label" for="title">Event Title</label>
            <input class="input" type="text" id="title" name="title" value="{{ old('title', $event->title) }}" required>
        </div>
        <div class="form-group">
            <label class="label" for="description">Description</label>
            <textarea class="textarea" id="description" name="description">{{ old('description', $event->description) }}</textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="label" for="event_type">Event Type</label>
                <input class="input" type="text" id="event_type" name="event_type" value="{{ old('event_type', $event->event_type) }}" required>
            </div>
            <div class="form-group">
                <label class="label" for="upload_deadline">Upload Deadline</label>
                <input class="input" type="datetime-local" id="upload_deadline" name="upload_deadline" value="{{ old('upload_deadline', $event->upload_deadline?->format('Y-m-d\TH:i')) }}">
            </div>
        </div>
        <div class="form-group">
            <label class="label">Allowed Media Types</label>
            <div class="checkbox-group">
                <input type="hidden" name="allow_photos" value="0">
                <label class="checkbox-label"><input type="checkbox" name="allow_photos" value="1" {{ old('allow_photos', $event->allow_photos) ? 'checked' : '' }}> Photos</label>
                <input type="hidden" name="allow_videos" value="0">
                <label class="checkbox-label"><input type="checkbox" name="allow_videos" value="1" {{ old('allow_videos', $event->allow_videos) ? 'checked' : '' }}> Videos</label>
                <input type="hidden" name="allow_voice" value="0">
                <label class="checkbox-label"><input type="checkbox" name="allow_voice" value="1" {{ old('allow_voice', $event->allow_voice) ? 'checked' : '' }}> Voice</label>
                <input type="hidden" name="allow_messages" value="0">
                <label class="checkbox-label"><input type="checkbox" name="allow_messages" value="1" {{ old('allow_messages', $event->allow_messages) ? 'checked' : '' }}> Messages</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
</form>
@endsection
