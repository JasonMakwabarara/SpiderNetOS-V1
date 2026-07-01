<?php

namespace App\Http\Controllers;

use App\Services\EventLogger;
use App\Support\UserRole;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            EventLogger::log('auth.login.failed', '', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('spidernet-token')->plainTextToken;

        EventLogger::log('auth.login.success', (string) $user->id, [
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        EventLogger::log('auth.logout', (string) $user->id, ['email' => $user->email]);
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'is_super_admin' => $user->isSuperAdmin(),
            'role' => $user->effectiveRole(),
            'permissions' => [
                'manage_workspace' => $user->hasMinimumRole(UserRole::TENANT_ADMIN),
                'manage_platform' => $user->isSuperAdmin(),
                'view_audit' => $user->hasMinimumRole(UserRole::TENANT_ADMIN),
            ],
        ];
    }
}
