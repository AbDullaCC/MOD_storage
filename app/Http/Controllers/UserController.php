<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User]);
    }

    public function store(Request $request)
    {
        $user = new User;
        $user->forceFill($this->validated($request))->save();

        return redirect()->route('users.index')->with('success', 'تم إنشاء الحساب.');
    }

    public function edit(User $user)
    {
        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($request->user()->is($user) && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])) {
            throw ValidationException::withMessages(['role' => 'لا يمكنك تعطيل حسابك أو إزالة صلاحية المدير من حسابك.']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['remember_token'] = Str::random(60);
        }
        $user->forceFill($data)->save();
        if ($request->user()->is($user) && isset($data['password'])) {
            $request->session()->put('password_hash_web', $user->getAuthPassword());
            $request->session()->regenerate();
        }

        return redirect()->route('users.index')->with('success', 'تم تحديث الحساب.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
            'is_active' => ['required', 'boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8), 'max:72'],
        ]);
    }
}
