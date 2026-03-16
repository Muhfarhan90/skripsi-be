<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'fullname' => 'Admin User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => 1, // Assuming role_id 1 is for admin
            ]
        );
    }
}
