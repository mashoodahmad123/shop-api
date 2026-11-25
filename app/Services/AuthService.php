<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = new User($data);
        $user->role = UserRole::CUSTOMER;
        $user->save();

        return [
            'user' => $user,
            'token' => $this->createToken($user),
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        return [
            'user' => $user,
            'token' => $this->createToken($user),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function createToken(User $user): string
    {
        return $user->createToken('api-token')->plainTextToken;
    }
}
