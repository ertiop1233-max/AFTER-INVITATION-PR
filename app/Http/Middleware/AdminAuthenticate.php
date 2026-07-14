<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('admin_id')) {
            return redirect()->route('admin.login')
                ->with('error', 'Please sign in to continue.');
        }

        $loginAt = session('admin_login_at');

        if ($loginAt && $loginAt->diffInHours(now()) >= 8) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Your session has expired. Please sign in again.');
        }

        return $next($request);
    }
}
