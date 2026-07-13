@extends('layouts.base')

@section('content')
<div class="upload-page">
    @isset($event)
    <header class="upload-header">
        <h1>{{ $event->title }}</h1>
        <p>{{ $event->event_type }}</p>
    </header>
    @endisset
    <main class="upload-main">
        @yield('upload-content')
    </main>
</div>
@endsection

@push('scripts')
@vite(['resources/css/upload.css'])
@vite(['resources/js/upload-page.js'])
@endpush
