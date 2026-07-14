@extends('layouts.base')

@section('content')
<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:var(--space-6);text-align:center">
    <h1 style="font-size:var(--font-size-4xl);margin-bottom:var(--space-2)">Session Expired</h1>
    <p style="color:var(--color-text-secondary);max-width:400px;margin-bottom:var(--space-6)">
        Your session has expired. Please try again.
    </p>
    <a href="{{ request()->is('client/*') ? route('client.login') : route('admin.login') }}" class="btn btn-primary">Sign In</a>
</div>
@endsection
