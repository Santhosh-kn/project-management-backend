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
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('hours', 8, 2); // Hours worked (e.g., 2.5 hours)
            $table->text('description')->nullable(); // Optional note about the work
            $table->date('log_date'); // Date when work was done
            $table->timestamp('started_at')->nullable(); // For timer: when started
            $table->timestamp('stopped_at')->nullable(); // For timer: when stopped
            $table->boolean('is_running')->default(false); // Is timer currently running
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('task_id');
            $table->index('user_id');
            $table->index('log_date');
            $table->index('is_running');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
