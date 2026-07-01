<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\EventLogger;
use App\Services\SystemObjectProvisioner;
use App\Support\UserRole;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function __construct(protected SystemObjectProvisioner $provisioner)
    {
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'workspace_name' => ['required', 'string', 'max:120'],
        ]);

        [$user, $token] = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['workspace_name'],
                'domain' => Str::slug($validated['workspace_name']).'-'.Str::random(4),
                'settings' => [],
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'tenant_id' => $tenant->id,
                'role' => UserRole::TENANT_ADMIN,
                'email_verified_at' => now(),
            ]);

            $this->provisioner->provision($tenant->id);

            $token = $user->createToken('spidernet-token')->plainTextToken;

            return [$user, $token];
        });

        EventLogger::log('auth.register', (string) $user->id, [
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
        ]);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

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

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        EventLogger::log('auth.password.forgot', '', [
            'email' => $request->input('email'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['error' => __($status)], 422);
        }

        EventLogger::log('auth.password.reset', '', ['email' => $request->input('email')]);

        return response()->json(['message' => 'Password has been reset. Please sign in.']);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $user->forceFill(['password' => $validated['password']])->save();
        EventLogger::log('auth.password.changed', (string) $user->id, ['email' => $user->email]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function sessions(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        return response()->json(
            $request->user()->tokens()
                ->orderByDesc('last_used_at')
                ->get(['id', 'name', 'last_used_at', 'created_at'])
                ->map(fn ($token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'current' => $token->id === $currentId,
                ])
        );
    }

    public function revokeSession(Request $request, int $tokenId)
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Session not found.'], 404);
        }

        return response()->json(['message' => 'Session revoked.']);
    }

    public function revokeOtherSessions(Request $request)
    {
        $currentId = $request->user()->currentAccessToken()?->id;
        $request->user()->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Other sessions revoked.']);
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
