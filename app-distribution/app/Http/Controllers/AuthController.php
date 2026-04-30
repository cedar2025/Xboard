<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditLogger $auditLogger)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => '账号或密码错误'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $auditLogger->log('admin.login', $request->user(), [], $request);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuditLogger $auditLogger)
    {
        $admin = $request->user();
        $auditLogger->log('admin.logout', $admin, [], $request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function updatePassword(Request $request, AuditLogger $auditLogger)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => '当前密码不正确']);
        }

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $auditLogger->log('admin.password.update', $request->user(), [], $request);

        return back()->with('status', '密码已更新');
    }
}
