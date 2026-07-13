@extends('layouts.base')

@section('content')
<div class="client-layout">
    <nav class="navbar">
        <a href="{{ route('client.dashboard') }}" class="navbar-brand">Memory Vault</a>
        <div class="navbar-links">
            <a href="{{ route('client.dashboard') }}" class="navbar-link {{ request()->routeIs('client.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('client.gallery') }}" class="navbar-link {{ request()->routeIs('client.gallery') ? 'active' : '' }}">Gallery</a>
            <a href="{{ route('client.submissions') }}" class="navbar-link {{ request()->routeIs('client.submissions*') ? 'active' : '' }}">Submissions</a>
            <form method="POST" action="{{ route('client.logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Logout</button>
            </form>
        </div>
    </nav>
    <main class="client-content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @yield('client-content')
    </main>
</div>
@endsection

@push('scripts')
@vite(['resources/css/client.css'])
@vite(['resources/js/client.js'])
@endpush
