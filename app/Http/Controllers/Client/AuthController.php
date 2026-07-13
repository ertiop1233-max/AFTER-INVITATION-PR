<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientLoginRequest;
use App\Models\Client;
use App\Services\ClientPasswordService;

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
        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid credentials.');
        }

        try {
            $decryptedPassword = $this->clientPasswordService->decrypt($client->password_encrypted);

            if (!hash_equals($decryptedPassword, $request->password)) {
                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'Invalid credentials.');
            }
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Unable to verify credentials. Please contact the event organizer.');
        }

        session([
            'client_id' => $client->id,
            'event_id' => $client->event_id,
            'client_login_at' => now(),
        ]);

        return redirect()->route('client.dashboard');
    }

    public function logout()
    {
        session()->flush();
        return redirect()->route('client.login');
    }
}
