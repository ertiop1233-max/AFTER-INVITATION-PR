@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Client Credentials</h1>
    <a href="{{ route('admin.events.show', $event) }}" class="btn btn-ghost">Back</a>
</div>

<div class="credential-box">
    <dl>
        <dt>Client Name:</dt>
        <dd>{{ $event->client->name }}</dd>
        <dt>Email:</dt>
        <dd>{{ $event->client->email }}</dd>
        <dt>Login URL:</dt>
        <dd>{{ route('client.login') }}</dd>
    </dl>
</div>

<div class="card">
    <h3 style="margin-bottom:var(--space-4)">Reset Password</h3>
    <p style="font-size:var(--font-size-sm);color:var(--color-text-muted);margin-bottom:var(--space-4)">Set a new password for the client. Share it securely with the client.</p>
    <form method="POST" action="{{ route('admin.events.credentials.reset', $event) }}">
        @csrf
        <div class="form-group">
            <label class="label" for="password">New Password</label>
            <input class="input" type="text" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Reset Password</button>
    </form>
</div>
@endsection
