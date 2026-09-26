<?php

namespace App\Services\Permissions;

use App\Exceptions\PermissionDeletionException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    /** @var array<int, string> */
    public const SORTABLE_FIELDS = ['name', 'guard_name', 'created_at'];

    public const GUARD_NAME = 'web';

    /**
     * @return LengthAwarePaginator<int, Permission>
     */
    public function paginate(
        string $search,
        string $sortField,
        string $sortDirection,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $sortField = in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'name';
        $direction = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc';

        return Permission::query()
            ->withCount(['roles', 'users'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy($sortField, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function find(int $permissionId): Permission
    {
        return Permission::query()->findOrFail($permissionId);
    }

    public function findWithCounts(int $permissionId): Permission
    {
        return Permission::query()
            ->withCount(['roles', 'users'])
            ->findOrFail($permissionId);
    }

    /**
     * @param  array{name: string}  $data
     */
    public function create(array $data): Permission
    {
        return Permission::query()->create([
            'name' => $data['name'],
            'guard_name' => self::GUARD_NAME,
        ]);
    }

    /**
     * @param  array{name: string}  $data
     */
    public function update(int $permissionId, array $data): Permission
    {
        $permission = $this->find($permissionId);

        $permission->update([
            'name' => $data['name'],
            'guard_name' => self::GUARD_NAME,
        ]);

        return $permission;
    }

    /**
     * @return Collection<string, Collection<int, string>>
     */
    public function groupedNames(string $guardName = self::GUARD_NAME): Collection
    {
        return Permission::query()
            ->where('guard_name', $guardName)
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString())
            ->map(fn (Collection $permissions): Collection => $permissions
                ->map(fn (Permission $permission): string => $permission->name));
    }

    /**
     * @throws PermissionDeletionException
     */
    public function delete(int $permissionId): void
    {
        DB::transaction(function () use ($permissionId): void {
            $permission = Permission::query()
                ->lockForUpdate()
                ->findOrFail($permissionId);

            if ($permission->roles()->exists()) {
                throw PermissionDeletionException::assignedToRoles();
            }

            if ($permission->users()->exists()) {
                throw PermissionDeletionException::assignedToUsers();
            }

            $permission->delete();
        });
    }
}
