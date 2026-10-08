<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    public static function usernameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:50', 'regex:/\A[\p{L}\p{N}][\p{L}\p{N}._-]*\z/u'];
    }

    public static function normalizeUsername(string $username): string
    {
        return Str::lower(trim($username));
    }

    public function isAdmin(): bool
    {
        return $this->is_active && $this->role === self::ROLE_ADMIN;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];
}
