<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\UserService;
use App\Traits\ApiResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(protected UserService $userService)
    {
    }

    public function index()
    {
        return $this->success(new DashboardResource($this->userService->stats()));
    }
}
