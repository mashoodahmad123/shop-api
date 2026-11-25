<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => env('ADMIN_EMAIL', 'admin@example.com')]);
        $admin->name = 'Admin';
        $admin->password = env('ADMIN_PASSWORD', 'password');
        $admin->role = UserRole::ADMIN;
        $admin->save();
    }
}
