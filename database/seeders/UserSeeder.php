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

        // Create instructor user
        User::updateOrCreate(
            ['email' => 'instructor@example.com'],
            [
                'fullname' => 'Instructor User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => 2, // Assuming role_id 2 is for instructor
            ]
        );

        // Create student user
        User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'fullname' => 'Student User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => 3, // Assuming role_id 3 is for student
            ]
        );
    }
}
