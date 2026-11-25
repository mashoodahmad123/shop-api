<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());

        return $this->success(new AuthTokenResource($result), 'User registered successfully', 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->email, $request->password);

        return $this->success(new AuthTokenResource($result), 'Login successful');
    }

    public function profile(Request $request)
    {
        return $this->success(new UserResource($request->user()));
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully');
    }
}
