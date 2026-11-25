<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $this->owns($user, $order);
    }

    public function create(User $user): bool
    {
        return !$user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    protected function owns(User $user, Order $order): bool
    {
        return (int) $order->user_id === (int) $user->id;
    }
}
