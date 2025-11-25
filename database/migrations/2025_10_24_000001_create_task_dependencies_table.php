<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('depends_on_task_id')->constrained('tasks')->onDelete('cascade');
            $table->enum('dependency_type', ['blocks', 'blocked_by', 'related_to'])->default('blocked_by');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Prevent duplicate dependencies
            $table->unique(['task_id', 'depends_on_task_id', 'dependency_type'], 'unique_task_dependency');

            // Indexes for better query performance
            $table->index('task_id');
            $table->index('depends_on_task_id');
            $table->index('dependency_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
