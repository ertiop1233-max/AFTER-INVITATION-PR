@extends('layouts.upload')

@section('upload-content')
<div class="notfound-screen">
    <div class="notfound-icon">&#128533;</div>
    <h1>Not Found</h1>
    <p style="color:var(--color-text-secondary);max-width:400px;margin:var(--space-3) auto">
        The event you're looking for doesn't exist or the link is no longer valid.
        Please check your link and try again.
    </p>
</div>
@endsection
