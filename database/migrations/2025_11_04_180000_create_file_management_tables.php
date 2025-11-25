<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // File categories
        Schema::create('file_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->default('📁');
            $table->string('color')->default('#3B82F6');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // File attachments
        Schema::create('file_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->bigInteger('size');
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->integer('version')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['attachable_type', 'attachable_id']);
            $table->index('category_id');
            $table->index('uploaded_by');
        });

        // File versions
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->integer('version');
            $table->string('filename');
            $table->string('file_path');
            $table->bigInteger('size');
            $table->unsignedBigInteger('uploaded_by');
            $table->text('changes_description')->nullable();
            $table->timestamps();
            
            $table->unique(['file_id', 'version']);
            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('file_attachments');
        Schema::dropIfExists('file_categories');
    }
};
