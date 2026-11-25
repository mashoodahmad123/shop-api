<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(protected UserService $userService)
    {
    }

    public function index(ListUsersRequest $request)
    {
        $filters = $request->validated();

        $users = $this->userService->list($filters, (int) ($filters['per_page'] ?? 10));

        return $this->success(
            UserResource::collection($users)->response()->getData(true)
        );
    }

    public function show(User $user)
    {
        return $this->success(new UserResource($user));
    }

    public function destroy(Request $request, User $user)
    {
        $this->userService->delete($user, $request->user());

        return $this->success(null, 'User deleted successfully');
    }
}
