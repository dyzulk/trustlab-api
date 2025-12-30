<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyController extends Controller
{
    /**
     * Display a listing of personal access tokens.
     */
    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()->apiKeys()->orderBy('created_at', 'desc')->get()
        ]);
    }

    /**
     * Create a new personal access token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $key = ApiKey::generate();

        $apiKey = $request->user()->apiKeys()->create([
            'name' => $request->name,
            'key' => $key,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'API Key created successfully',
            'token' => $key,
            'key' => $apiKey
        ], 201);
    }

    /**
     * Revoke a personal access token.
     */
    public function destroy($id)
    {
        $apiKey = Auth::user()->apiKeys()->where('id', $id)->first();

        if (!$apiKey) {
            return response()->json(['message' => 'API Key not found'], 404);
        }

        $apiKey->delete();

        return response()->json(['message' => 'API Key revoked successfully']);
    }

    /**
     * Toggle the active status of an API Key.
     */
    public function toggle($id)
    {
        $apiKey = Auth::user()->apiKeys()->where('id', $id)->first();

        if (!$apiKey) {
            return response()->json(['message' => 'API Key not found'], 404);
        }

        $apiKey->update([
            'is_active' => !$apiKey->is_active
        ]);

        return response()->json([
            'message' => 'API Key status updated successfully',
            'is_active' => $apiKey->is_active
        ]);
    }

    /**
     * Regenerate the contents of an API Key.
     */
    public function regenerate($id)
    {
        $apiKey = Auth::user()->apiKeys()->where('id', $id)->first();

        if (!$apiKey) {
            return response()->json(['message' => 'API Key not found'], 404);
        }

        $newKey = ApiKey::generate();

        $apiKey->update([
            'key' => $newKey,
            'last_used_at' => null,
        ]);

        return response()->json([
            'message' => 'API Key regenerated successfully',
            'token' => $newKey
        ]);
    }
}
