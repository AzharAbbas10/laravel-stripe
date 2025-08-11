<?php

namespace App\Sevices;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login($credentials): array
    {
        $email = $credentials['email'];
        $password = $credentials['password'];

        $user = User::where('email', $email)->first();

        if (!$user) {
            return [
                'message' => 'User not found',
                'code' => 200,
                'status' => false,
            ];
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            return [
                'message' => 'Invalid password',
                'code' => 200,
                'status' => false,
            ];
        }

        // Password is valid, create token
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'status' => true,
            'code' => 200,
        ];
    }

        public function register($credentials): array
    {
        $name = $credentials['name'];
        $email = $credentials['email'];
        $password = $credentials['password'];


        // Save user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
        ]);
        $user = $user->fresh();

        $token = $user->createToken('api-token')->plainTextToken;


        return [
            'message' => 'User Created Successfully',
            'user' => $user,
            'token' => $token,
            'status' => true,
            'code' => 200,
        ];
    }
}
