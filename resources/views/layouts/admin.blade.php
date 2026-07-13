@extends('layouts.base')

@section('content')
<div class="admin-layout">
    <nav class="navbar">
        <a href="{{ route('admin.dashboard') }}" class="navbar-brand">Memory Vault</a>
        <div class="navbar-links">
            <a href="{{ route('admin.dashboard') }}" class="navbar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.events.index') }}" class="navbar-link {{ request()->routeIs('admin.events.*') ? 'active' : '' }}">Events</a>
            <a href="{{ route('admin.storage') }}" class="navbar-link {{ request()->routeIs('admin.storage') ? 'active' : '' }}">Storage</a>
            <form method="POST" action="{{ route('admin.logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-ghost" style="min-height:auto;padding:var(--space-2) var(--space-3)">Logout</button>
            </form>
        </div>
    </nav>
    <main class="admin-content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @yield('admin-content')
    </main>
</div>
@endsection

@push('scripts')
@vite(['resources/css/admin.css', 'resources/js/admin.js'])
@endpush
