<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');

        User::updateOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_approved' => true
            ]
        );
    }
}
