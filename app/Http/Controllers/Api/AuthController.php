<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sevices\AuthService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:8'
            ]);

            $response = $this->authService->login($credentials);

            return response()->json($response, $response['code']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors(),
                'status' => false,
                'code' => 422
            ], 422);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $exception->getMessage(),
                'status' => false,
            ], 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8'
            ]);

            $response = $this->authService->register($credentials);
            return response()->json($response, $response['code']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors(),
                'status' => false,
                'code' => 422
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $exception->getMessage(),
                'status' => false,
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logged out successfully.',
                'status' => true,
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error' => $exception->getMessage(),
                'status' => false,
            ], 500);
        }
    }
}
