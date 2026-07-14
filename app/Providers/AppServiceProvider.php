<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(100)->by(
                $request->ip().'|'.$request->header('X-Upload-Token')
            );
        });

        RateLimiter::for('upload-chunks', function (Request $request) {
            return Limit::perHour(2000)->by($request->ip().'|'.$request->header('X-Upload-Nonce'));
        });

        RateLimiter::for('submissions', function (Request $request) {
            return Limit::perHour(10)->by(
                $request->ip().'|'.$request->header('X-Upload-Token')
            );
        });

        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
