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
        // Admin Users
        $admins = [
            ['email' => 'admin@trustlab.com', 'first_name' => 'Admin', 'last_name' => 'User'],
            ['email' => 'admin@example.com', 'first_name' => 'Admin', 'last_name' => 'Example'],
            ['email' => 'admin@gmail.com', 'first_name' => 'Admin', 'last_name' => 'Gmail'],
            ['email' => 'melon@buah.der.my.id', 'first_name' => 'Buah', 'last_name' => 'Melon'],
            ['email' => 'santulitam2024@gmail.com', 'first_name' => 'Santu', 'last_name' => 'Lita Tam'],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate([
                'email' => $admin['email'],
            ], [
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'password' => bcrypt('#PersibBandung1933'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);
        }

        // Customer Users
        $customers = [
            ['email' => 'customer@trustlab.com', 'first_name' => 'Customer', 'last_name' => 'User'],
            ['email' => 'customer@example.com', 'first_name' => 'Customer', 'last_name' => 'Example'],
            ['email' => 'customer@gmail.com', 'first_name' => 'Customer', 'last_name' => 'Gmail'],
            ['email' => 'customer@info.com', 'first_name' => 'Customer', 'last_name' => 'Info'],
        ];

        foreach ($customers as $customer) {
            User::updateOrCreate([
                'email' => $customer['email'],
            ], [
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'password' => bcrypt('#PersibBandung1933'),
                'role' => 'customer',
                'email_verified_at' => now(),
            ]);
        }
        
        // Additional Customer via Factory
        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'role' => 'customer',
        ]);
    }
}
