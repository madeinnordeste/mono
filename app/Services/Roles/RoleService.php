<?php

namespace App\Services\Roles;

use App\Exceptions\RoleDeletionException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    /** @var array<int, string> */
    public const SORTABLE_FIELDS = ['name', 'guard_name', 'created_at'];

    public const GUARD_NAME = 'web';

    public const PROTECTED_ROLE = 'admin';

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(
        string $search,
        string $sortField,
        string $sortDirection,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $sortField = in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'name';
        $direction = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc';

        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy($sortField, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function find(int $roleId): Role
    {
        return Role::query()->findOrFail($roleId);
    }

    public function findWithPermissions(int $roleId): Role
    {
        return Role::query()
            ->with('permissions')
            ->findOrFail($roleId);
    }

    public function findWithUserCount(int $roleId): Role
    {
        return Role::query()
            ->withCount('users')
            ->findOrFail($roleId);
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Role
    {
        return Role::query()->create([
            'name' => $data['name'],
            'guard_name' => self::GUARD_NAME,
        ]);
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(int $roleId, array $data): Role
    {
        $role = $this->find($roleId);

        $attributes = [
            'name' => $data['name'],
            'guard_name' => self::GUARD_NAME,
        ];

        if ($role->name === self::PROTECTED_ROLE) {
            unset($attributes['name']);
        }

        $role->update($attributes);

        return $role;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(int $roleId, array $permissions): void
    {
        $this->find($roleId)->syncPermissions($permissions);
    }

    public function isProtected(?int $roleId): bool
    {
        if ($roleId === null) {
            return false;
        }

        return Role::query()
            ->whereKey($roleId)
            ->where('name', self::PROTECTED_ROLE)
            ->exists();
    }

    /**
     * @throws RoleDeletionException
     */
    public function delete(int $roleId): void
    {
        DB::transaction(function () use ($roleId): void {
            $role = Role::query()
                ->lockForUpdate()
                ->findOrFail($roleId);

            if ($role->name === self::PROTECTED_ROLE) {
                throw RoleDeletionException::protectedRole();
            }

            if ($role->users()->exists()) {
                throw RoleDeletionException::roleHasUsers();
            }

            $role->delete();
        });
    }
}
