<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Models\LegalPageRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LegalPageController extends Controller
{
    public function index()
    {
        $pages = LegalPage::with(['latestRevision' => function ($query) {
            $query->orderBy('major', 'desc')
                  ->orderBy('minor', 'desc')
                  ->orderBy('patch', 'desc');
        }])->get();
        return response()->json(['data' => $pages]);
    }

    public function show($id)
    {
        $legalPage = LegalPage::findOrFail($id);
        
        // Manual load latest revision
        $latestRevision = $legalPage->revisions()
            ->orderBy('major', 'desc')
            ->orderBy('minor', 'desc')
            ->orderBy('patch', 'desc')
            ->first();
        
        $legalPage->setRelation('latestRevision', $latestRevision);
        
        return response()->json(['data' => $legalPage]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|in:draft,published',
        ]);

        $slug = Str::slug($request->title);
        
        // Check if page exists
        $page = LegalPage::where('slug', $slug)->first();

        if ($page) {
            // Smart Versioning: If exists, increment Major version automatically for "Create" flow
            $maxMajor = $page->revisions()->max('major') ?? 0;
            $major = $maxMajor + 1;
            
            // Auto-Archive Logic: If new version is published, archive others
            if ($request->status === 'published') {
                $page->revisions()->where('status', 'published')->update(['status' => 'archived']);
            }

            $page->revisions()->create([
                'content' => $request->content,
                'major' => $major,
                'minor' => 0,
                'patch' => 0,
                'status' => $request->status,
                'published_at' => $request->status === 'published' ? now() : null,
                'change_log' => 'Created via New Page (Auto-increment Major)',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            return response()->json(['data' => $page, 'message' => 'New major version created for existing Legal Page'], 201);
        } else {
            // Create New
            $page = LegalPage::create([
                'title' => $request->title,
                'slug' => $slug,
                'is_active' => true,
            ]);

            // Initial create is always 1.0.0
            $page->revisions()->create([
                'content' => $request->content,
                'major' => 1,
                'minor' => 0,
                'patch' => 0,
                'status' => $request->status,
                'published_at' => $request->status === 'published' ? now() : null,
                'change_log' => 'Initial creation',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            return response()->json(['data' => $page, 'message' => 'Legal page created successfully'], 201);
        }
    }

    public function update(Request $request, LegalPage $legalPage)
    {
        $request->validate([
            'title' => 'string|max:255',
            'content' => 'required|string',
            'version_type' => 'required|in:major,minor,patch', // 'major', 'minor', 'patch'
            'parent_major' => 'nullable|integer',
            'parent_minor' => 'nullable|integer',
            'status' => 'required|in:draft,published',
            'change_log' => 'nullable|string',
        ]);

        if ($request->has('title')) {
             $legalPage->update(['title' => $request->title]);
        }

        // Calculate Version
        $major = 0; $minor = 0; $patch = 0;

        if ($request->version_type === 'major') {
            $maxMajor = $legalPage->revisions()->max('major') ?? 0;
            $major = $maxMajor + 1;
            $minor = 0;
            $patch = 0;
        } elseif ($request->version_type === 'minor') {
            if (!$request->parent_major) return response()->json(['message' => 'Parent Major required for Minor version'], 422);
            $maxMinor = $legalPage->revisions()
                ->where('major', $request->parent_major)
                ->max('minor') ?? -1;
            $major = $request->parent_major;
            $minor = $maxMinor + 1;
            $patch = 0;
        } elseif ($request->version_type === 'patch') {
             if (!$request->parent_major || is_null($request->parent_minor)) return response()->json(['message' => 'Parent Major and Minor required for Patch'], 422);
            $maxPatch = $legalPage->revisions()
                ->where('major', $request->parent_major)
                ->where('minor', $request->parent_minor)
                ->max('patch') ?? -1;
            $major = $request->parent_major;
            $minor = $request->parent_minor;
            $patch = $maxPatch + 1;
        }

        // Auto-Archive Logic: If new version is published, archive others
        if ($request->status === 'published') {
            $legalPage->revisions()->where('status', 'published')->update(['status' => 'archived']);
        }

        $legalPage->revisions()->create([
            'content' => $request->content,
            'major' => $major,
            'minor' => $minor,
            'patch' => $patch,
            'status' => $request->status,
            'published_at' => $request->status === 'published' ? now() : null,
            'change_log' => $request->change_log ?? 'Updated content',
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['data' => $legalPage, 'message' => 'Legal page updated with new revision']);
    }

    public function getHistory($id) {
         $legalPage = LegalPage::findOrFail($id);
         $revisions = $legalPage->revisions()
            ->orderBy('major', 'desc')
            ->orderBy('minor', 'desc')
            ->orderBy('patch', 'desc')
            ->get();
         return response()->json(['data' => $revisions]);
    }

    public function destroy(LegalPage $legalPage)
    {
        $legalPage->delete();
        return response()->json(['message' => 'Legal page deleted']);
    }
}
