@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Create Event</h1>
    <a href="{{ route('admin.events.index') }}" class="btn btn-ghost">Cancel</a>
</div>

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

<form method="POST" action="{{ route('admin.events.store') }}">
    @csrf
    <div class="card" style="margin-bottom:var(--space-4)">
        <h3 style="margin-bottom:var(--space-4)">Event Details</h3>
        <div class="form-group">
            <label class="label" for="title">Event Title</label>
            <input class="input" type="text" id="title" name="title" value="{{ old('title') }}" required>
        </div>
        <div class="form-group">
            <label class="label" for="description">Description (optional)</label>
            <textarea class="textarea" id="description" name="description">{{ old('description') }}</textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="label" for="event_type">Event Type</label>
                <input class="input" type="text" id="event_type" name="event_type" value="{{ old('event_type') }}" placeholder="Wedding, Birthday, etc." required>
            </div>
            <div class="form-group">
                <label class="label" for="upload_deadline">Upload Deadline (optional)</label>
                <input class="input" type="datetime-local" id="upload_deadline" name="upload_deadline" value="{{ old('upload_deadline') }}">
            </div>
        </div>
        <div class="form-group">
            <label class="label">Allowed Media Types</label>
            <div class="checkbox-group">
                <label class="checkbox-label"><input type="checkbox" name="allow_photos" value="1" {{ old('allow_photos', true) ? 'checked' : '' }}> Photos</label>
                <label class="checkbox-label"><input type="checkbox" name="allow_videos" value="1" {{ old('allow_videos', true) ? 'checked' : '' }}> Videos</label>
                <label class="checkbox-label"><input type="checkbox" name="allow_voice" value="1" {{ old('allow_voice', true) ? 'checked' : '' }}> Voice</label>
                <label class="checkbox-label"><input type="checkbox" name="allow_messages" value="1" {{ old('allow_messages', true) ? 'checked' : '' }}> Messages</label>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-4)">
        <h3 style="margin-bottom:var(--space-4)">Client Account</h3>
        <div class="form-row">
            <div class="form-group">
                <label class="label" for="client_name">Client Name</label>
                <input class="input" type="text" id="client_name" name="client_name" value="{{ old('client_name') }}" required>
            </div>
            <div class="form-group">
                <label class="label" for="client_email">Client Email</label>
                <input class="input" type="email" id="client_email" name="client_email" value="{{ old('client_email') }}" required>
            </div>
        </div>
        <div class="form-group">
            <label class="label" for="client_password">Client Password</label>
            <input class="input" type="text" id="client_password" name="client_password" value="{{ old('client_password') }}" required>
            <p style="font-size:var(--font-size-xs);color:var(--color-text-muted);margin-top:var(--space-1)">This password will be shared with the client to access their dashboard.</p>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Create Event</button>
</form>
@endsection
