<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $commonPassword = bcrypt('password');

        // 1. Owner
        User::updateOrCreate([
            'email' => 'owner@trustlab.com',
        ], [
            'first_name' => 'Owner',
            'last_name' => 'User',
            'password' => $commonPassword,
            'role' => User::ROLE_OWNER,
            'email_verified_at' => now(),
        ]);

        // 2. Admin
        User::updateOrCreate([
            'email' => 'admin@trustlab.com',
        ], [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'password' => $commonPassword,
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        // 3. Customer
        User::updateOrCreate([
            'email' => 'customer@trustlab.com',
        ], [
            'first_name' => 'Customer',
            'last_name' => 'User',
            'password' => $commonPassword,
            'role' => User::ROLE_CUSTOMER,
            'email_verified_at' => now(),
        ]);
    }
}
