<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use App\Models\Comment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔹 Seeding comments...\n";
        
        $tasks = Task::all();
        $users = User::all();

        if ($tasks->isEmpty() || $users->isEmpty()) {
            echo "⚠️  No tasks or users found. Skipping comments seeding.\n";
            return;
        }

        $commentTemplates = [
            'This looks good! Let\'s proceed with the implementation.',
            'I have some concerns about the approach. Can we discuss?',
            'Great work! Just a few minor suggestions.',
            'Please review the latest changes and let me know your thoughts.',
            'I\'ve completed my part. Moving this forward.',
            'We need to address the performance issues before proceeding.',
            'The tests are passing. Ready for review.',
            'Added the requested features. Please test.',
            'Found a bug in the implementation. Working on a fix.',
            'Documentation updated. Please review.',
            'This is blocked by another task. Will resume once unblocked.',
            'Excellent progress! Keep up the good work.',
            'Can someone help me with this issue?',
            'I\'ve reviewed the code and left some comments.',
            'Deployed to staging. Ready for testing.',
        ];

        $commentCount = 0;
        $mentionCount = 0;

        foreach ($tasks as $task) {
            // Add 2-4 comments per task
            $numComments = rand(2, 4);
            
            for ($i = 0; $i < $numComments; $i++) {
                $commentUser = $users->random();
                $commentText = $commentTemplates[array_rand($commentTemplates)];
                
                $createdAt = now()->subDays(rand(0, 20))->subHours(rand(0, 23));
                
                // Create comment using polymorphic relationship
                $comment = new Comment([
                    'user_id' => $commentUser->id,
                    'content' => $commentText,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(rand(0, 120)),
                ]);

                // Associate with task (polymorphic)
                $comment->commentable()->associate($task);
                $comment->save();
                $commentCount++;

                // Sometimes add a mention (30% chance)
                if (rand(1, 100) <= 30) {
                    // Get a different user to mention
                    $availableUsers = $users->where('id', '!=', $commentUser->id);
                    
                    if ($availableUsers->isNotEmpty()) {
                        $mentionedUser = $availableUsers->random();
                        
                        // Add @mention to the comment content
                        $comment->content = '@' . $mentionedUser->name . ' ' . $comment->content;
                        $comment->save();
                        
                        // Create mention record
                        DB::table('mentions')->insert([
                            'comment_id' => $comment->id,
                            'mentioned_user_id' => $mentionedUser->id,
                            'mentioned_by_user_id' => $commentUser->id,
                            'is_read' => rand(0, 1) == 1, // Randomly mark some as read
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]);
                        
                        $mentionCount++;
                    }
                }
            }
        }

        echo "  ✓ Created {$commentCount} comments\n";
        echo "  ✓ Created {$mentionCount} mentions\n";
        echo "✅ Comments seeded successfully!\n";
    }
}
