<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use Illuminate\Http\Request;

class LegalPageController extends Controller
{
    public function show($slug)
    {
        $page = LegalPage::where('slug', $slug)->where('is_active', true)->firstOrFail();

        // Robustly fetch the latest active revision that is published
        $latestRevision = $page->revisions()
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('major', 'desc')
            ->orderBy('minor', 'desc')
            ->orderBy('patch', 'desc')
            ->first();

        if (!$latestRevision) {
             return response()->json(['message' => 'Content not available'], 404);
        }

        // Manually attach for response structure if needed, or just build response
        return response()->json(['data' => [
            'title' => $page->title,
            'content' => $latestRevision->content,
            'updated_at' => $latestRevision->created_at,
            'version' => $latestRevision->version,
        ]]);
    }
    
    public function index()
    {
        $pages = LegalPage::where('is_active', true)->select('title', 'slug')->get();
        return response()->json(['data' => $pages]);
    }
}
