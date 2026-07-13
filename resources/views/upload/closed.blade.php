@extends('layouts.upload')

@section('upload-content')
<div class="closed-screen">
    <div class="closed-icon">&#128274;</div>
    <h1>Thank You</h1>
    <p style="color:var(--color-text-secondary);max-width:400px;margin:var(--space-3) auto">
        {{ $event->title }} is no longer accepting new submissions.
        Thank you to everyone who shared their memories.
    </p>
</div>
@endsection
