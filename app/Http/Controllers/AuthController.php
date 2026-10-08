<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'username' => User::usernameRules(),
            'password' => ['required', 'string'],
        ]);
        $credentials['username'] = User::normalizeUsername($credentials['username']);
        $key = 'login:'.hash('sha256', $credentials['username'].'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'محاولات كثيرة. حاول مجدداً بعد '.RateLimiter::availableIn($key).' ثانية.']);
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true, 'role' => [User::ROLE_ADMIN, User::ROLE_OPERATOR]])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'بيانات تسجيل الدخول غير صحيحة أو الحساب غير متاح.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('storage.index'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'max:72', 'different:current_password'],
        ]);
        $request->user()->forceFill(['password' => $validated['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->put('password_hash_web', $request->user()->getAuthPassword());
        $request->session()->regenerate();

        return back()->with('success', 'تم تغيير كلمة المرور.');
    }
}
