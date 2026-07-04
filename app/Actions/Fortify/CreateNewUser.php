<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => mb_strtolower(trim($input['email'])),
                'password' => Hash::make($input['password']),
                'ativo' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('frequentador');
            $user->perfil()->create([]);

            return $user;
        });
    }
}
