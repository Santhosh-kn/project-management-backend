<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();
        $users = User::all();
        $admin = User::where('email', 'admin@example.com')->first();

        $statuses = ['todo', 'in_progress', 'review', 'done'];
        $priorities = ['low', 'medium', 'high', 'critical'];

        $taskTemplates = [
            ['title' => 'Setup project repository', 'description' => 'Initialize git repository and setup branch protection rules'],
            ['title' => 'Design database schema', 'description' => 'Create ER diagrams and define relationships'],
            ['title' => 'Implement user authentication', 'description' => 'Setup JWT authentication with refresh tokens'],
            ['title' => 'Create API endpoints', 'description' => 'Build RESTful API with proper error handling'],
            ['title' => 'Setup CI/CD pipeline', 'description' => 'Configure automated testing and deployment'],
            ['title' => 'Write unit tests', 'description' => 'Achieve 80% code coverage with comprehensive tests'],
            ['title' => 'Design UI mockups', 'description' => 'Create wireframes and high-fidelity designs in Figma'],
            ['title' => 'Implement responsive layouts', 'description' => 'Build mobile-first responsive components'],
            ['title' => 'Setup error logging', 'description' => 'Integrate Sentry for error tracking and monitoring'],
            ['title' => 'Optimize database queries', 'description' => 'Add indexes and optimize slow queries'],
            ['title' => 'Security audit', 'description' => 'Perform security review and fix vulnerabilities'],
            ['title' => 'Write API documentation', 'description' => 'Document all endpoints with Swagger/OpenAPI'],
            ['title' => 'Setup staging environment', 'description' => 'Configure staging server with production parity'],
            ['title' => 'Implement caching strategy', 'description' => 'Setup Redis caching for improved performance'],
            ['title' => 'User acceptance testing', 'description' => 'Conduct UAT with stakeholders and gather feedback'],
            ['title' => 'Performance optimization', 'description' => 'Optimize bundle size and improve load times'],
            ['title' => 'Setup monitoring', 'description' => 'Configure application monitoring and alerts'],
            ['title' => 'Code review', 'description' => 'Review pull requests and ensure code quality'],
            ['title' => 'Deploy to production', 'description' => 'Final deployment with zero-downtime strategy'],
            ['title' => 'Post-deployment monitoring', 'description' => 'Monitor application health after release'],
        ];

        $taskId = 1;

        foreach ($projects as $project) {
            // Get project members
            $projectMembers = DB::table('project_members')
                ->where('project_id', $project->id)
                ->pluck('user_id')
                ->toArray();

            // Create 4-6 tasks per project
            $taskCount = rand(4, 6);
            
            for ($i = 0; $i < $taskCount; $i++) {
                $template = $taskTemplates[array_rand($taskTemplates)];
                $status = $statuses[array_rand($statuses)];
                $priority = $priorities[array_rand($priorities)];
                
                // Assign to random project member or leave unassigned
                $assignedTo = rand(0, 1) && !empty($projectMembers) 
                    ? $projectMembers[array_rand($projectMembers)] 
                    : null;

                $dueDate = null;
                if (rand(0, 1)) {
                    $daysOffset = rand(-30, 60);
                    $dueDate = now()->addDays($daysOffset)->format('Y-m-d');
                }

                DB::table('tasks')->insert([
                    'project_id' => $project->id,
                    'title' => $template['title'] . ' - ' . $project->name,
                    'description' => $template['description'],
                    'status' => $status,
                    'priority' => $priority,
                    'due_date' => $dueDate,
                    'estimated_hours' => rand(1, 40),
                    'actual_hours' => $status === 'done' ? rand(1, 50) : null,
                    'assigned_to' => $assignedTo,
                    'created_by' => $admin->id,
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(0, 5)),
                ]);

                // Add 1-3 tags to each task
                $this->addTaskTags($taskId, rand(1, 3));

                $taskId++;
            }
        }

        $this->command->info('✅ Tasks seeded successfully!');
    }

    private function addTaskTags($taskId, $count)
    {
        $tagIds = DB::table('tags')->pluck('id')->toArray();
        $selectedTags = array_rand(array_flip($tagIds), min($count, count($tagIds)));
        
        if (!is_array($selectedTags)) {
            $selectedTags = [$selectedTags];
        }

        foreach ($selectedTags as $tagId) {
            DB::table('taggables')->insert([
                'tag_id' => $tagId,
                'taggable_id' => $taskId,
                'taggable_type' => 'App\\Models\\Task',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
