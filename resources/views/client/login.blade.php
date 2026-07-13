@extends('layouts.base')

@section('content')
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:var(--space-6)">
    <div style="width:100%;max-width:400px">
        <h1 style="text-align:center;margin-bottom:var(--space-2)">Memory Vault</h1>
        <p style="text-align:center;color:var(--color-text-muted);margin-bottom:var(--space-8)">Client Sign In</p>

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('client.login') }}">
            @csrf
            <div class="form-group">
                <label class="label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="form-group">
                <label class="label" for="password">Password</label>
                <input class="input" type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Sign In</button>
        </form>
    </div>
</div>
@endsection
