@extends('layouts.base')

@section('content')
<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:var(--space-6);text-align:center">
    <h1 style="font-size:var(--font-size-4xl);margin-bottom:var(--space-2)">Something Went Wrong</h1>
    <p style="color:var(--color-text-secondary);max-width:400px;margin-bottom:var(--space-6)">
        We encountered an unexpected error. Please try again in a few moments.
    </p>
    <a href="/" class="btn btn-primary">Go Home</a>
</div>
@endsection