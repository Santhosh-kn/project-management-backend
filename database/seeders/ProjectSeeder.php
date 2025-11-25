<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔹 Seeding projects...\n";
        
        $admin = User::where('email', 'admin@example.com')->first();
        $johnManager = User::where('email', 'john.manager@example.com')->first();
        $sarahManager = User::where('email', 'sarah.manager@example.com')->first();

        $projects = [
            [
                'name' => 'E-Commerce Platform',
                'description' => 'Building a modern e-commerce platform with advanced features including payment integration, inventory management, and customer analytics.',
                'status' => 'active', // ✅ Valid: draft, active, on_hold, completed, archived
                'priority' => 'high',
                'start_date' => now()->subDays(60),
                'end_date' => now()->addDays(90),
                'is_public' => false,
                'owner_id' => $admin->id,
                'created_at' => now()->subDays(60),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mobile App Development',
                'description' => 'Cross-platform mobile application for iOS and Android with real-time synchronization and offline capabilities.',
                'status' => 'active', // ✅ Valid enum value
                'priority' => 'critical',
                'start_date' => now()->subDays(45),
                'end_date' => now()->addDays(60),
                'is_public' => true,
                'owner_id' => $johnManager->id,
                'created_at' => now()->subDays(45),
                'updated_at' => now(),
            ],
            [
                'name' => 'API Integration Service',
                'description' => 'RESTful API service for third-party integrations with authentication, rate limiting, and comprehensive documentation.',
                'status' => 'active', // ✅ Valid enum value
                'priority' => 'high',
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(45),
                'is_public' => false,
                'owner_id' => $sarahManager->id,
                'created_at' => now()->subDays(30),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dashboard Redesign',
                'description' => 'Complete UI/UX overhaul of the admin dashboard with modern design principles and improved user experience.',
                'status' => 'draft', // ✅ Valid enum value
                'priority' => 'medium',
                'start_date' => now()->addDays(5),
                'end_date' => now()->addDays(75),
                'is_public' => true,
                'owner_id' => $admin->id,
                'created_at' => now()->subDays(15),
                'updated_at' => now(),
            ],
            [
                'name' => 'Security Audit',
                'description' => 'Comprehensive security audit and implementation of best practices including penetration testing and vulnerability assessment.',
                'status' => 'completed', // ✅ Valid enum value
                'priority' => 'critical',
                'start_date' => now()->subDays(90),
                'end_date' => now()->subDays(10),
                'is_public' => false,
                'owner_id' => $johnManager->id,
                'created_at' => now()->subDays(90),
                'updated_at' => now(),
            ],
        ];

        foreach ($projects as $projectData) {
            $project = Project::create($projectData);
            echo "  ✓ Created: {$project->name}\n";
            
            // Add project members
            $this->addProjectMembers($project);
        }

        echo "✅ Projects seeded successfully!\n";
    }

    private function addProjectMembers(Project $project)
    {
        $users = User::whereIn('role', ['manager', 'member'])->get();
        
        // Add 3-5 random members to each project
        $memberCount = rand(3, 5);
        $selectedUsers = $users->random(min($memberCount, $users->count()));

        foreach ($selectedUsers as $user) {
            DB::table('project_members')->insert([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => $user->role === 'manager' ? 'manager' : 'member',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
