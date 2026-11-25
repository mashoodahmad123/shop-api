<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        if (app()->environment('local')) {
            User::firstOrCreate(
                ['email' => 'customer@example.com'],
                ['name' => 'Test Customer', 'password' => 'password']
            );

            User::factory(15)->create();

            $this->call(ProductSeeder::class);
            $this->call(OrderSeeder::class);
            $this->call(PaymentSeeder::class);
        }
    }
}
