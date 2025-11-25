<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            // Admin user
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
            
            // Manager users
            [
                'name' => 'John Manager',
                'email' => 'john.manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Sarah Manager',
                'email' => 'sarah.manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Mike Director',
                'email' => 'mike.director@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'email_verified_at' => now(),
            ],
            
            // Member users
            [
                'name' => 'Alice Developer',
                'email' => 'alice.dev@example.com',
                'password' => Hash::make('password'),
                'role' => 'member',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Bob Designer',
                'email' => 'bob.designer@example.com',
                'password' => Hash::make('password'),
                'role' => 'member',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Carol Tester',
                'email' => 'carol.tester@example.com',
                'password' => Hash::make('password'),
                'role' => 'member',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'David Frontend',
                'email' => 'david.frontend@example.com',
                'password' => Hash::make('password'),
                'role' => 'member',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Emma Backend',
                'email' => 'emma.backend@example.com',
                'password' => Hash::make('password'),
                'role' => 'member',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Frank DevOps',
                'email' => 'frank.devops@example.com',
                'password' => Hash::make('password'),
                'role' => 'member',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }

        $this->command->info('✅ Users seeded successfully!');
    }
}
