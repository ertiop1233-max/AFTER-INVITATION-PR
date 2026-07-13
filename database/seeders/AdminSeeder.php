<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('memoryvault.admin.email');
        $password = config('memoryvault.admin.password');

        if (!$email || !$password) {
            throw new RuntimeException(
                'ADMIN_EMAIL and ADMIN_PASSWORD must be configured in .env before seeding.'
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'ADMIN_PASSWORD must be at least 12 characters long.'
            );
        }

        Admin::updateOrCreate(
            ['email' => $email],
            ['password' => Hash::make($password)]
        );
    }
}
