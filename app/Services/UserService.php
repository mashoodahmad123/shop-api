<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        return [
            'total' => User::count(),
            'customers' => User::where('role', UserRole::CUSTOMER)->count(),
            'admins' => User::where('role', UserRole::ADMIN)->count(),
            'this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
        ];
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->isAdmin() || $user->is($actor)) {
            throw new BusinessException('Admin accounts cannot be deleted from here.', 403);
        }

        if ($user->orders()->exists()) {
            throw new BusinessException('Users with orders cannot be deleted.', 409);
        }

        $user->tokens()->delete();
        $user->delete();
    }
}
