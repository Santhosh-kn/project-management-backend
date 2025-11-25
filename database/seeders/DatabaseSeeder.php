<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        $this->command->newLine();

        $this->call([
            UserSeeder::class,
            ProjectSeeder::class,
            TagSeeder::class,
            TaskSeeder::class,
            CommentSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->newLine();
        
        $this->command->info('📊 Seeded Summary:');
        $this->command->info('• 10 Users (1 admin, 3 managers, 6 members)');
        $this->command->info('• 5 Projects with members');
        $this->command->info('• 10 Tags');
        $this->command->info('• 20-30 Tasks with tags');
        $this->command->info('• 30-60 Comments with mentions');
        $this->command->newLine();
        
        $this->command->info('🔐 Test Credentials:');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Manager: john.manager@example.com / password');
        $this->command->info('Member: alice.dev@example.com / password');
    }
}
