<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sevices\AuthService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;
    // protected AuthService $authService;

    // public function __construct(AuthService $authService)
    // {
    //     $this->authService = $authService;
    // }

    protected AuthService $authService;

    public function __construct(AuthService $authService){
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
            return $this->successResponse($response, 'Login successful', $response['code']);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                implode(' ', collect($e->errors())->flatten()->toArray()),
                200
            );
        } catch (\Exception $exception) {
            return $this->errorResponse(
                'An unexpected error occurred.',
                500,
                ['exception' => $exception->getMessage()]
            );
        }
    }
}
