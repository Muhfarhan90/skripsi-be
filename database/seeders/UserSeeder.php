<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRoleId = Role::where('name', 'admin')->value('id');
        $instructorRoleId = Role::where('name', 'instructor')->value('id');
        $studentRoleId = Role::where('name', 'user')->value('id');

        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'fullname' => 'Admin User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $adminRoleId,
            ]
        );

        // Create instructor user
        User::updateOrCreate(
            ['email' => 'instructor@example.com'],
            [
                'fullname' => 'Lead Instructor',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $instructorRoleId,
                'bio' => 'Instruktur umum untuk kebutuhan demo dan fallback seed data.',
            ]
        );

        User::updateOrCreate(
            ['email' => 'web.instructor@example.com'],
            [
                'fullname' => 'Aditya Pratama',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $instructorRoleId,
                'bio' => 'Mengampu course Pemrograman Web dengan fokus pada HTML, CSS, JavaScript, dan fondasi pengembangan aplikasi web.',
            ]
        );

        User::updateOrCreate(
            ['email' => 'medical.instructor@example.com'],
            [
                'fullname' => 'dr. Nabila Putri',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $instructorRoleId,
                'bio' => 'Mengampu course Dasar Kedokteran Klinis dengan fokus pada anamnesis, pemeriksaan awal, dan komunikasi pasien.',
            ]
        );

        User::updateOrCreate(
            ['email' => 'agri.instructor@example.com'],
            [
                'fullname' => 'Bima Saputra',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $instructorRoleId,
                'bio' => 'Mengampu course Teknologi Pertanian Modern dengan fokus pada budidaya berbasis data dan teknologi pertanian terapan.',
            ]
        );

        // Create student user
        User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'fullname' => 'Student User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $studentRoleId,
            ]
        );

        User::updateOrCreate(
            ['email' => 'student.waiting@example.com'],
            [
                'fullname' => 'Student Waiting Start',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $studentRoleId,
            ]
        );

        User::updateOrCreate(
            ['email' => 'student.expired@example.com'],
            [
                'fullname' => 'Student Expired Access',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $studentRoleId,
            ]
        );

        User::updateOrCreate(
            ['email' => 'student.completed@example.com'],
            [
                'fullname' => 'Student Completed',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $studentRoleId,
            ]
        );
    }
}
