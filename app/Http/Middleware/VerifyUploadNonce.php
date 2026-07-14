<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyUploadNonce
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $request->header('X-Upload-Nonce');
        $token = $request->header('X-Upload-Token');

        if (! $nonce || ! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Missing upload credentials.',
            ], 403);
        }

        $event = Event::where('upload_token', $token)->first();

        if (! $event || ! self::isValidNonce($token, $nonce)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid upload credentials.',
            ], 403);
        }

        $request->attributes->set('upload_event', $event);

        return $next($request);
    }

    public static function generateNonce(string $token, ?int $expiresAt = null): string
    {
        $expiresAt ??= now()
            ->addHours(config('memoryvault.upload_nonce_ttl_hours', 24))
            ->getTimestamp();

        $signature = hash_hmac('sha256', $token.'|'.$expiresAt, config('app.key'));

        return $expiresAt.'.'.$signature;
    }

    public static function isValidNonce(string $token, string $nonce): bool
    {
        [$expiresAt, $signature] = array_pad(explode('.', $nonce, 2), 2, null);

        if ($expiresAt === null || $signature === null || ! ctype_digit($expiresAt)) {
            return false;
        }

        if ((int) $expiresAt < now()->getTimestamp()) {
            return false;
        }

        $expected = hash_hmac('sha256', $token.'|'.$expiresAt, config('app.key'));

        return hash_equals($expected, $signature);
    }
}
