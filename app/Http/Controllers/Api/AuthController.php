<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'member', // Default role
        ]);

        $token = auth('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Login user and return JWT token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'INVALID_CREDENTIALS',
                ],
            ], 401);
        }

        $user = auth('api')->user();

        // Check if user is active
        if (!$user->is_active) {
            auth('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'ACCOUNT_INACTIVE',
                ],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the authenticated user
     */
    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource(auth('api')->user()),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 422);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => new UserResource($user->fresh()),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 422);
        }

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update user settings/preferences
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'language' => ['nullable', 'string', 'in:en,es,fr,de'],
            'timezone' => ['nullable', 'string'],
            'date_format' => ['nullable', 'string'],
            'email_notifications' => ['nullable', 'boolean'],
            'task_assignments' => ['nullable', 'boolean'],
            'task_due_dates' => ['nullable', 'boolean'],
            'comment_mentions' => ['nullable', 'boolean'],
            'weekly_digest' => ['nullable', 'boolean'],
            'theme' => ['nullable', 'string', 'in:light,dark,auto'],
            'compact_mode' => ['nullable', 'boolean'],
            'online_status' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 422);
        }

        // Get current settings or create empty array
        $settings = $user->settings ?? [];

        // Merge with new settings
        $updatedSettings = array_merge($settings, $request->only([
            'language',
            'timezone',
            'date_format',
            'email_notifications',
            'task_assignments',
            'task_due_dates',
            'comment_mentions',
            'weekly_digest',
            'theme',
            'compact_mode',
            'online_status',
        ]));

        // Update user settings
        $user->update([
            'settings' => $updatedSettings,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => [
                'settings' => $updatedSettings,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Logout user (invalidate token)
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Refresh JWT token
     */
    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
