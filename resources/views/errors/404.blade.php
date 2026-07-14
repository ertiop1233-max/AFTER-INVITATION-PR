@extends('layouts.base')

@section('content')
<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:var(--space-6);text-align:center">
    <h1 style="font-size:var(--font-size-5xl);margin-bottom:var(--space-2)">404</h1>
    <p style="color:var(--color-text-secondary);max-width:400px;margin-bottom:var(--space-6)">
        The page you're looking for doesn't exist or has been moved.
    </p>
    <a href="/" class="btn btn-primary">Go Home</a>
</div>
@endsection