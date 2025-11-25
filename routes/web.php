<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/debug/projects', function () {
    $projects = DB::table('projects')
        ->whereNull('deleted_at')
        ->get(['id', 'name', 'owner_id', 'is_public', 'status']);
    
    $projectMembers = DB::table('project_members')
        ->select('project_id', DB::raw('GROUP_CONCAT(user_id) as members'))
        ->groupBy('project_id')
        ->pluck('members', 'project_id');
    
    return response()->json([
        'total_projects' => $projects->count(),
        'projects' => $projects->map(function ($p) use ($projectMembers) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'owner_id' => $p->owner_id,
                'is_public' => $p->is_public,
                'status' => $p->status,
                'members' => $projectMembers[$p->id] ?? 'none',
            ];
        }),
        'admin_user' => DB::table('users')->where('email', 'admin@example.com')->first(['id', 'name', 'email', 'role']),
    ]);
});
