<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class FileAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'size',
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'category_id',
        'version',
        'parent_id',
        'description',
    ];

    // Delete file from storage when model is deleted
    protected static function booted()
    {
        static::deleted(function ($file) {
            if (Storage::disk('public')->exists($file->file_path)) {
                Storage::disk('public')->delete($file->file_path);
            }
        });
    }
}
