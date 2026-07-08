<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\SearchService;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService){
        $this->searchService = $searchService;
    }

    public function getSearch(Request $request): JsonResponse
    {
        $request->validate(['search' => 'required|string|min:2']);
        $search = $request->search;
        $response = $this->searchService->getGlobalSearch($search);

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => $response['projects'],
                'tasks' => $response['tasks']
            ]
        ]);
    }
}
