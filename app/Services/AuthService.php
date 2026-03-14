<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function register(array $data)
    {
        $user = User::create([
            'fullname' => $data['fullname'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'password' => Hash::make($data['password']),
            'is_active' => true
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    public function login(array $data)
    {
        if (!Auth::attempt($data)) {
            throw new \Exception('Invalid credentials');
        }

        $user = Auth::user();

        if (!$user->is_active) {
            throw new \Exception('User inactive');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    public function logout($user)
    {
        $user->currentAccessToken()->delete();
    }
}
