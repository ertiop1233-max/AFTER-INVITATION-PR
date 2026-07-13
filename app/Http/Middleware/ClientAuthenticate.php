<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('client_id') || !session()->has('event_id')) {
            return redirect()->route('client.login')
                ->with('error', 'Please sign in to continue.');
        }

        $loginAt = session('client_login_at');

        if ($loginAt && $loginAt->diffInDays(now()) >= 90) {
            session()->flush();
            return redirect()->route('client.login')
                ->with('error', 'Your session has expired. Please sign in again.');
        }

        return $next($request);
    }
}
