<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('client_id') || ! session()->has('event_id')) {
            return redirect()->route('client.login')
                ->with('error', 'Please sign in to continue.');
        }

        $client = Client::with('event')->find(session('client_id'));

        if (! $client
            || ! $client->event
            || $client->event_id !== (int) session('event_id')
            || $client->auth_version !== (int) session('client_auth_version')) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('client.login')
                ->with('error', 'Your session is no longer valid. Please sign in again.');
        }

        $loginAt = session('client_login_at');

        if ($loginAt && $loginAt->diffInDays(now()) >= 90) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('client.login')
                ->with('error', 'Your session has expired. Please sign in again.');
        }

        return $next($request);
    }
}
