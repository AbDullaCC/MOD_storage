<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {--name=Admin}';

    protected $description = 'Create an admin account with an interactively entered password';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name'),
            'email' => Str::lower(trim($this->argument('email'))),
            'password' => $this->secret('Password (at least 8 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8), 'max:72'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        unset($data['password_confirmation']);
        $user = new User;
        $user->forceFill([...$data, 'role' => User::ROLE_ADMIN, 'is_active' => true])->save();
        $this->info('Admin account created: '.$user->email);

        return self::SUCCESS;
    }
}
