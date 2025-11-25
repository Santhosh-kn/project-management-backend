<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('read_at');
        });
        
        // Notification settings table
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(false);
            $table->boolean('task_assigned')->default(true);
            $table->boolean('task_completed')->default(true);
            $table->boolean('task_commented')->default(true);
            $table->boolean('task_mentioned')->default(true);
            $table->boolean('task_due_soon')->default(true);
            $table->boolean('project_updates')->default(true);
            $table->enum('digest_frequency', ['realtime', 'daily', 'weekly', 'never'])->default('daily');
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->default('22:00');
            $table->time('quiet_hours_end')->default('08:00');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notifications');
    }
};
