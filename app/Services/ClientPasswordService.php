<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClientPasswordService
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $key = config('memoryvault.client_key');

        if (! $key) {
            throw new RuntimeException('MEMORYVAULT_CLIENT_KEY is not configured');
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        $this->encrypter = new Encrypter($key, 'aes-256-cbc');
    }

    public function encrypt(string $password): string
    {
        return $this->encrypter->encrypt($password);
    }

    public function decrypt(string $encryptedPassword): string
    {
        try {
            return $this->encrypter->decrypt($encryptedPassword);
        } catch (\Throwable $e) {
            Log::error('Failed to decrypt client password', ['error' => $e->getMessage()]);
            throw new RuntimeException('Unable to decrypt client password');
        }
    }
}
