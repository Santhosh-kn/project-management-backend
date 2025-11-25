<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileVersion extends Model
{
    protected $fillable = [
        'file_id',
        'version',
        'filename',
        'file_path',
        'size',
        'uploaded_by',
        'changes_description',
    ];
}
