<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyUploadNonce
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $request->header('X-Upload-Nonce');
        $token = $request->header('X-Upload-Token');

        if (!$nonce || !$token) {
            return response()->json([
                'success' => false,
                'message' => 'Missing upload credentials.',
            ], 403);
        }

        $expectedNonce = $this->generateExpectedNonce($token);

        if (!hash_equals($expectedNonce, $nonce)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid upload credentials.',
            ], 403);
        }

        return $next($request);
    }

    public static function generateNonce(string $token): string
    {
        return hash_hmac('sha256', $token, config('app.key'));
    }

    private function generateExpectedNonce(string $token): string
    {
        return self::generateNonce($token);
    }
}
