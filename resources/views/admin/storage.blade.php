@extends('layouts.admin')

@section('admin-content')
<div class="admin-header">
    <h1>Storage & Health</h1>
</div>

<div class="storage-overview" style="margin-bottom:var(--space-6)">
    <div class="card">
        <h3 style="margin-bottom:var(--space-3)">Database</h3>
        <div class="health-indicator">
            <span class="health-dot {{ $health['database'] ? 'ok' : 'fail' }}"></span>
            {{ $health['database'] ? 'Connected' : 'Not Connected' }}
        </div>
    </div>
    <div class="card">
        <h3 style="margin-bottom:var(--space-3)">Google Drive</h3>
        <div class="health-indicator">
            <span class="health-dot {{ $health['drive'] ? 'ok' : 'fail' }}"></span>
            {{ $health['drive'] ? 'Connected' : 'Not Connected' }}
        </div>
    </div>
    <div class="card">
        <h3 style="margin-bottom:var(--space-3)">SMTP</h3>
        <div class="health-indicator">
            <span class="health-dot {{ $health['smtp'] ? 'ok' : 'warn' }}"></span>
            {{ $health['smtp'] ? 'Connected' : 'Not Connected' }}
        </div>
    </div>
    @if($quota)
    <div class="card">
        <h3 style="margin-bottom:var(--space-3)">Drive Storage</h3>
        @if(isset($quota['limit']) && $quota['limit'] > 0)
            @php $usedPercent = round(($quota['usage'] / $quota['limit']) * 100, 1); @endphp
            <p>Used: {{ number_format($quota['usage'] / 1073741824, 2) }} GB / {{ number_format($quota['limit'] / 1073741824, 2) }} GB ({{ $usedPercent }}%)</p>
            <div style="height:8px;background:var(--color-bg-elevated);border-radius:var(--radius-full);margin-top:var(--space-2);overflow:hidden">
                <div style="height:100%;width:{{ min(100, $usedPercent) }}%;background:{{ $usedPercent >= 80 ? 'var(--color-error)' : 'var(--color-accent)' }};border-radius:var(--radius-full)"></div>
            </div>
        @else
            <p style="color:var(--color-text-muted)">Quota information not available.</p>
        @endif
    </div>
    @endif
</div>

@if($quotaWarning ?? false)
    <div class="alert alert-warning">
        Storage usage has exceeded the warning threshold. Please review and clean up old events.
    </div>
@endif
@endsection
