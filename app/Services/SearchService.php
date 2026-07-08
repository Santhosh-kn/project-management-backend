<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SearchService {
    public function getGlobalSearch(String $search): array
    {
        $projects = DB::table('projects')
        ->select("projects.id as projectId", "projects.name as projectName", "projects.status as projectStatus")
        ->join('project_members', 'projects.id', '=', 'project_members.project_id')
        ->where('project_members.user_id',Auth::id())
        ->where(function($query ) use ($search){
            $query->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
        })
        ->limit(3)
        ->get();

        $tasks = DB::table("tasks")
        ->select("tasks.id as taskId","tasks.title as tasksTitle", "tasks.project_id as taskProjectId", "tasks.status as taskStatus")
        ->join('project_members', 'tasks.project_id', '=', 'project_members.project_id')
        ->where('project_members.user_id',Auth::id())
        ->where(function($query) use ($search){
            $query->where('title', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
        })
        ->limit(3)
        ->get();

        return array('projects' => $projects, 'tasks' => $tasks) ;
    }
}