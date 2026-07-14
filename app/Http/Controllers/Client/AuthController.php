<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientLoginRequest;
use App\Models\Client;
use App\Services\ClientPasswordService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly ClientPasswordService $clientPasswordService,
    ) {}

    public function showLogin()
    {
        return view('client.login');
    }

    public function login(ClientLoginRequest $request)
    {
        $clients = Client::where('email', $request->email)->get();

        if ($clients->isEmpty()) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid credentials.');
        }

        $matches = $clients->filter(function (Client $client) use ($request): bool {
            try {
                return hash_equals(
                    $this->clientPasswordService->decrypt($client->password_encrypted),
                    $request->password
                );
            } catch (\Throwable) {
                return false;
            }
        })->values();

        if ($matches->count() !== 1) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', $matches->isEmpty()
                    ? 'Invalid credentials.'
                    : 'These credentials match multiple events. Please contact the event organizer.');
        }

        $client = $matches->first();

        session([
            'client_id' => $client->id,
            'event_id' => $client->event_id,
            'client_auth_version' => $client->auth_version,
            'client_login_at' => now(),
        ]);
        $request->session()->regenerate();

        return redirect()->route('client.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.login');
    }
}
