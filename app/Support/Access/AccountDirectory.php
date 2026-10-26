<?php

namespace App\Support\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccountDirectory
{
    public const PLATFORM = 'platform';

    public const OPERATIONS = 'operations';

    /**
     * @return array<int, string>
     */
    public static function roleNames(string $directory): array
    {
        $roles = config('access.roles', []);

        return array_keys(array_filter(
            $roles,
            fn (array $role): bool => in_array(
                $role['workspace'] ?? '',
                self::workspacesFor($directory),
                true,
            ),
        ));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function applyScope(Builder $query, string $directory): Builder
    {
        $operationsRoles = self::roleNames(self::OPERATIONS);

        if ($directory === self::OPERATIONS) {
            return $query->whereHas(
                'roles',
                fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', $operationsRoles),
            );
        }

        $platformRoles = self::roleNames(self::PLATFORM);

        return $query
            ->whereHas(
                'roles',
                fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', $platformRoles),
            )
            ->whereDoesntHave(
                'roles',
                fn (Builder $roleQuery): Builder => $roleQuery->whereIn('name', $operationsRoles),
            );
    }

    public static function for(User $user): string
    {
        $operationsRoles = self::roleNames(self::OPERATIONS);

        if ($user->roles()->whereIn('name', $operationsRoles)->exists()) {
            return self::OPERATIONS;
        }

        return self::PLATFORM;
    }

    public static function indexRoute(string $directory): string
    {
        return $directory === self::OPERATIONS
            ? 'admin.users.operations.index'
            : 'admin.users.index';
    }

    public static function exportRoute(string $directory): string
    {
        return $directory === self::OPERATIONS
            ? 'admin.users.operations.export'
            : 'admin.users.export';
    }

    public static function heading(string $directory): string
    {
        return match ($directory) {
            self::OPERATIONS => 'Staff and administration',
            default => 'Partner accounts',
        };
    }

    public static function emptyMessage(string $directory): string
    {
        return match ($directory) {
            self::OPERATIONS => 'No staff or administration accounts are available to review.',
            default => 'No buyer, broker, or landowner accounts are available to review.',
        };
    }

    /**
     * @return array<int, string>
     */
    private static function workspacesFor(string $directory): array
    {
        return match ($directory) {
            self::OPERATIONS => ['staff', 'admin'],
            default => ['buyer', 'broker', 'seller'],
        };
    }
}
