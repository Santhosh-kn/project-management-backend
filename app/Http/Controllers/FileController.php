<?php

namespace App\Http\Controllers;

use App\Models\FileAttachment;
use App\Models\FileCategory;
use App\Models\FileVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class FileController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('file_attachments')
            ->select(
                'file_attachments.*',
                'users.name as uploader_name',
                'users.email as uploader_email',
                'file_categories.name as category_name',
                'file_categories.icon as category_icon',
                'file_categories.color as category_color'
            )
            ->leftJoin('users', 'file_attachments.uploaded_by', '=', 'users.id')
            ->leftJoin('file_categories', 'file_attachments.category_id', '=', 'file_categories.id')
            ->whereNull('file_attachments.deleted_at')
            ->orderBy('file_attachments.created_at', 'desc');

        if ($request->has('attachable_type') && $request->has('attachable_id')) {
            $query->where('file_attachments.attachable_type', $request->attachable_type)
                  ->where('file_attachments.attachable_id', $request->attachable_id);
        }

        if ($request->has('category_id')) {
            $query->where('file_attachments.category_id', $request->category_id);
        }

        if ($request->has('type')) {
            $type = $request->type;
            if ($type === 'image') {
                $query->where('file_attachments.mime_type', 'like', 'image/%');
            } elseif ($type === 'video') {
                $query->where('file_attachments.mime_type', 'like', 'video/%');
            } elseif ($type === 'audio') {
                $query->where('file_attachments.mime_type', 'like', 'audio/%');
            } elseif ($type === 'document') {
                $query->where(function($q) {
                    $q->where('file_attachments.mime_type', 'like', '%pdf%')
                      ->orWhere('file_attachments.mime_type', 'like', '%document%')
                      ->orWhere('file_attachments.mime_type', 'like', '%word%');
                });
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('file_attachments.original_filename', 'like', "%{$search}%")
                  ->orWhere('file_attachments.filename', 'like', "%{$search}%");
            });
        }

        $files = $query->paginate($request->input('per_page', 50));

        $items = collect($files->items())->map(function ($file) {
            $file->file_url = Storage::url($file->file_path);
            $file->category = null;
            if ($file->category_name) {
                $file->category = (object)[
                    'id' => $file->category_id,
                    'name' => $file->category_name,
                    'icon' => $file->category_icon,
                    'color' => $file->category_color,
                ];
            }
            return $file;
        });

        return response()->json(['data' => $items]);
    }

    public function show($id)
    {
        $file = DB::table('file_attachments')
            ->select(
                'file_attachments.*',
                'users.name as uploader_name',
                'users.email as uploader_email',
                'file_categories.name as category_name',
                'file_categories.icon as category_icon',
                'file_categories.color as category_color'
            )
            ->leftJoin('users', 'file_attachments.uploaded_by', '=', 'users.id')
            ->leftJoin('file_categories', 'file_attachments.category_id', '=', 'file_categories.id')
            ->where('file_attachments.id', $id)
            ->whereNull('file_attachments.deleted_at')
            ->first();

        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $file->file_url = Storage::url($file->file_path);

        return response()->json(['data' => $file]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'attachable_type' => 'required|string',
            'attachable_id' => 'required|integer',
            'category_id' => 'nullable|exists:file_categories,id',
            'description' => 'nullable|string',
        ]);

        $uploadedFile = $request->file('file');
        
        $filename = time() . '_' . Str::random(10) . '.' . $uploadedFile->getClientOriginalExtension();
        
        $path = $uploadedFile->storeAs('files', $filename, 'public');

        $fileId = DB::table('file_attachments')->insertGetId([
            'filename' => $filename,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $uploadedFile->getMimeType(),
            'size' => $uploadedFile->getSize(),
            'attachable_type' => $request->attachable_type,
            'attachable_id' => $request->attachable_id,
            'uploaded_by' => Auth::id(),
            'category_id' => $request->category_id,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = DB::table('file_attachments')
            ->select(
                'file_attachments.*',
                'users.name as uploader_name',
                'file_categories.name as category_name',
                'file_categories.icon as category_icon',
                'file_categories.color as category_color'
            )
            ->leftJoin('users', 'file_attachments.uploaded_by', '=', 'users.id')
            ->leftJoin('file_categories', 'file_attachments.category_id', '=', 'file_categories.id')
            ->where('file_attachments.id', $fileId)
            ->first();

        $file->file_url = Storage::url($file->file_path);

        return response()->json(['data' => $file], 201);
    }

    public function update(Request $request, $id)
    {
        $file = FileAttachment::findOrFail($id);

        $request->validate([
            'original_filename' => 'sometimes|string',
            'category_id' => 'nullable|exists:file_categories,id',
            'description' => 'nullable|string',
        ]);

        DB::table('file_attachments')
            ->where('id', $id)
            ->update([
                'original_filename' => $request->input('original_filename', $file->original_filename),
                'category_id' => $request->input('category_id', $file->category_id),
                'description' => $request->input('description', $file->description),
                'updated_at' => now(),
            ]);

        $updatedFile = DB::table('file_attachments')
            ->select(
                'file_attachments.*',
                'users.name as uploader_name',
                'file_categories.name as category_name'
            )
            ->leftJoin('users', 'file_attachments.uploaded_by', '=', 'users.id')
            ->leftJoin('file_categories', 'file_attachments.category_id', '=', 'file_categories.id')
            ->where('file_attachments.id', $id)
            ->first();

        $updatedFile->file_url = Storage::url($updatedFile->file_path);

        return response()->json(['data' => $updatedFile]);
    }

    public function destroy($id)
    {
        $file = FileAttachment::findOrFail($id);
        
        DB::table('file_attachments')
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        if (Storage::disk('public')->exists($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        return response()->json(['message' => 'File deleted successfully']);
    }

    public function download($id)
    {
        $file = DB::table('file_attachments')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($file->file_path, $file->original_filename);
    }

    public function preview($id)
    {
        $file = DB::table('file_attachments')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $canPreview = in_array(explode('/', $file->mime_type)[0], ['image', 'video', 'audio', 'text']) 
                      || $file->mime_type === 'application/pdf';

        return response()->json([
            'data' => [
                'id' => $file->id,
                'filename' => $file->original_filename,
                'file_url' => Storage::url($file->file_path),
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'can_preview' => $canPreview,
            ]
        ]);
    }

    public function versions($fileId)
    {
        $versions = DB::table('file_versions')
            ->select(
                'file_versions.*',
                'users.name as uploader_name'
            )
            ->leftJoin('users', 'file_versions.uploaded_by', '=', 'users.id')
            ->where('file_versions.file_id', $fileId)
            ->orderBy('file_versions.version', 'desc')
            ->get();

        $versions = $versions->map(function ($version) {
            $version->file_url = Storage::url($version->file_path);
            return $version;
        });

        return response()->json(['data' => $versions]);
    }

    public function uploadVersion(Request $request, $fileId)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'changes_description' => 'nullable|string',
        ]);

        $originalFile = DB::table('file_attachments')
            ->where('id', $fileId)
            ->whereNull('deleted_at')
            ->first();

        if (!$originalFile) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $uploadedFile = $request->file('file');

        DB::table('file_versions')->insert([
            'file_id' => $originalFile->id,
            'version' => $originalFile->version,
            'filename' => $originalFile->filename,
            'file_path' => $originalFile->file_path,
            'size' => $originalFile->size,
            'uploaded_by' => $originalFile->uploaded_by,
            'changes_description' => $request->changes_description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filename = time() . '_' . Str::random(10) . '.' . $uploadedFile->getClientOriginalExtension();
        $path = $uploadedFile->storeAs('files', $filename, 'public');

        DB::table('file_attachments')
            ->where('id', $fileId)
            ->update([
                'filename' => $filename,
                'file_path' => $path,
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'version' => $originalFile->version + 1,
                'updated_at' => now(),
            ]);

        $file = DB::table('file_attachments')
            ->select(
                'file_attachments.*',
                'users.name as uploader_name',
                'file_categories.name as category_name'
            )
            ->leftJoin('users', 'file_attachments.uploaded_by', '=', 'users.id')
            ->leftJoin('file_categories', 'file_attachments.category_id', '=', 'file_categories.id')
            ->where('file_attachments.id', $fileId)
            ->first();

        $file->file_url = Storage::url($file->file_path);

        return response()->json(['data' => $file]);
    }

    public function restoreVersion($fileId, $versionId)
    {
        $file = DB::table('file_attachments')
            ->where('id', $fileId)
            ->whereNull('deleted_at')
            ->first();

        if (!$file) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $version = DB::table('file_versions')
            ->where('id', $versionId)
            ->where('file_id', $fileId)
            ->first();

        if (!$version) {
            return response()->json(['message' => 'Version not found'], 404);
        }

        DB::table('file_versions')->insert([
            'file_id' => $file->id,
            'version' => $file->version,
            'filename' => $file->filename,
            'file_path' => $file->file_path,
            'size' => $file->size,
            'uploaded_by' => $file->uploaded_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('file_attachments')
            ->where('id', $fileId)
            ->update([
                'filename' => $version->filename,
                'file_path' => $version->file_path,
                'size' => $version->size,
                'version' => $file->version + 1,
                'updated_at' => now(),
            ]);

        $restoredFile = DB::table('file_attachments')
            ->select(
                'file_attachments.*',
                'users.name as uploader_name',
                'file_categories.name as category_name'
            )
            ->leftJoin('users', 'file_attachments.uploaded_by', '=', 'users.id')
            ->leftJoin('file_categories', 'file_attachments.category_id', '=', 'file_categories.id')
            ->where('file_attachments.id', $fileId)
            ->first();

        $restoredFile->file_url = Storage::url($restoredFile->file_path);

        return response()->json(['data' => $restoredFile]);
    }

    public function deleteVersion($fileId, $versionId)
    {
        $version = DB::table('file_versions')
            ->where('id', $versionId)
            ->where('file_id', $fileId)
            ->first();

        if (!$version) {
            return response()->json(['message' => 'Version not found'], 404);
        }

        if (Storage::disk('public')->exists($version->file_path)) {
            Storage::disk('public')->delete($version->file_path);
        }

        DB::table('file_versions')->where('id', $versionId)->delete();

        return response()->json(['message' => 'Version deleted successfully']);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:file_attachments,id',
        ]);

        DB::table('file_attachments')
            ->whereIn('id', $request->file_ids)
            ->update(['deleted_at' => now()]);

        return response()->json(['message' => 'Files deleted successfully']);
    }

    public function bulkDownload(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:file_attachments,id',
        ]);

        $files = DB::table('file_attachments')
            ->whereIn('id', $request->file_ids)
            ->whereNull('deleted_at')
            ->get();

        $zip = new ZipArchive;
        $zipFileName = 'files_' . time() . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($files as $file) {
                $filePath = storage_path('app/public/' . $file->file_path);
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, $file->original_filename);
                }
            }
            $zip->close();
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function bulkCategorize(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:file_attachments,id',
            'category_id' => 'required|exists:file_categories,id',
        ]);

        DB::table('file_attachments')
            ->whereIn('id', $request->file_ids)
            ->update([
                'category_id' => $request->category_id,
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Files categorized successfully']);
    }

    public function getCategories()
    {
        $categories = DB::table('file_categories')
            ->select(
                'file_categories.*',
                DB::raw('COUNT(file_attachments.id) as files_count')
            )
            ->leftJoin('file_attachments', function($join) {
                $join->on('file_categories.id', '=', 'file_attachments.category_id')
                     ->whereNull('file_attachments.deleted_at');
            })
            ->groupBy(
                'file_categories.id',
                'file_categories.name',
                'file_categories.icon',
                'file_categories.color',
                'file_categories.description',
                'file_categories.created_at',
                'file_categories.updated_at'
            )
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        $categoryId = DB::table('file_categories')->insertGetId([
            'name' => $request->name,
            'icon' => $request->input('icon', '📁'),
            'color' => $request->input('color', '#3B82F6'),
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = DB::table('file_categories')->where('id', $categoryId)->first();

        return response()->json(['data' => $category], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
        ]);

        DB::table('file_categories')
            ->where('id', $id)
            ->update(array_merge($request->all(), ['updated_at' => now()]));

        $category = DB::table('file_categories')->where('id', $id)->first();

        return response()->json(['data' => $category]);
    }

    public function destroyCategory($id)
    {
        DB::table('file_attachments')
            ->where('category_id', $id)
            ->update(['category_id' => null, 'updated_at' => now()]);

        DB::table('file_categories')->where('id', $id)->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    public function stats(Request $request)
    {
        $query = DB::table('file_attachments')
            ->whereNull('deleted_at');

        if ($request->has('project_id')) {
            $query->where('attachable_type', 'App\\Models\\Project')
                  ->where('attachable_id', $request->project_id);
        }

        $totalFiles = $query->count();
        $totalSize = $query->sum('size');

        $byType = DB::table('file_attachments')
            ->select(
                DB::raw("CASE 
                    WHEN mime_type LIKE 'image/%' THEN 'image'
                    WHEN mime_type LIKE 'video/%' THEN 'video'
                    WHEN mime_type LIKE 'audio/%' THEN 'audio'
                    WHEN mime_type LIKE '%pdf%' THEN 'pdf'
                    ELSE 'other'
                END as type"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(size) as size')
            )
            ->whereNull('deleted_at')
            ->groupBy('type')
            ->get();

        return response()->json([
            'data' => [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
                'by_type' => $byType,
            ]
        ]);
    }
}
