<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request)
    {
        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid credentials.');
        }

        session([
            'admin_id' => $admin->id,
            'admin_login_at' => now(),
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function logout()
    {
        session()->flush();
        return redirect()->route('admin.login');
    }
}
