<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Token auth for a future mobile app / separate frontend. The admin panel itself uses session cookies. */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! $user->hasPassword() || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('Your account is disabled. Please contact the gym.')]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('auth.api_login', $user);

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'user' => new UserResource($user->load('roles', 'visitor')),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'personal_id' => ['nullable', 'string', 'max:32', 'regex:/^\d{11}$/', 'unique:users,personal_id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::create($data);
        $user->assignRole(config('permissions.default_role'));
        event(new Registered($user));

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'user' => new UserResource($user->load('roles', 'visitor')),
        ], 201);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles', 'visitor.category'));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        // Session-authenticated (cookie) requests have a transient token without delete().
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => __('Logged out.')]);
    }
}
