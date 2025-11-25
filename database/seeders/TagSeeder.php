<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            ['name' => 'Bug', 'color' => '#EF4444', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Feature', 'color' => '#3B82F6', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Enhancement', 'color' => '#10B981', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Documentation', 'color' => '#F59E0B', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Testing', 'color' => '#8B5CF6', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Performance', 'color' => '#EC4899', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Security', 'color' => '#DC2626', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'UI/UX', 'color' => '#06B6D4', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Backend', 'color' => '#6366F1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Frontend', 'color' => '#14B8A6', 'created_at' => now(), 'updated_at' => now()],
        ];

        DB::table('tags')->insert($tags);

        $this->command->info('✅ Tags seeded successfully!');
    }
}
