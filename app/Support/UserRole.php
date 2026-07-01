<?php

namespace App\Support;

/**
 * Workspace RBAC roles. Super-admins manage the platform; tenant-admins manage
 * workspace configuration; members use day-to-day features.
 */
final class UserRole
{
    public const MEMBER = 'member';

    public const TENANT_ADMIN = 'tenant_admin';

    public const SUPER_ADMIN = 'super_admin';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::MEMBER, self::TENANT_ADMIN, self::SUPER_ADMIN];
    }

    public static function rank(string $role): int
    {
        return match ($role) {
            self::SUPER_ADMIN => 3,
            self::TENANT_ADMIN => 2,
            default => 1,
        };
    }
}
