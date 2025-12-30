<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UserApiController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $this->authorizeAdminOrOwner();
        $query = User::query();

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($request->input('per_page', 10));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeAdminOrOwner();
        
        if (auth()->user()->isOwner()) {
            $roles = [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_CUSTOMER];
        } else {
            // Admins can only create Customers
            $roles = [User::ROLE_CUSTOMER];
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in($roles)],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $this->authorizeAdminOrOwner();
        return $user;
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeAdminOrOwner();

        // Permission check: Admins cannot modify Admin/Owner accounts
        if (auth()->user()->isAdmin() && $user->role !== User::ROLE_CUSTOMER) {
             abort(403, 'Admins can only manage Customer accounts.');
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => [
                'sometimes', 
                'required', 
                auth()->user()->isOwner() ? Rule::in([User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_CUSTOMER]) : Rule::in([$user->role])
            ],
        ]);

        // Admins cannot change roles (already handled by Rule::in([$user->role]) above for safety, but let's be explicit)
        if (auth()->user()->isAdmin() && isset($validated['role']) && $validated['role'] !== $user->role) {
             abort(403, 'Admins cannot change user roles.');
        }


        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        $this->authorizeAdminOrOwner();

        // Permission check: Admins can only delete Customer accounts
        if (auth()->user()->isAdmin() && $user->role !== User::ROLE_CUSTOMER) {
             abort(403, 'Admins can only delete Customer accounts.');
        }
        // Prevent deleting yourself
        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    protected function authorizeAdminOrOwner()
    {
        if (!auth()->user()->isAdminOrOwner()) {
            abort(403, 'Unauthorized action.');
        }
    }
}
