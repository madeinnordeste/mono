<?php

namespace App\Services\Users;

use App\Exceptions\LastAdminException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserService
{
    /** @var array<int, string> */
    public const SORTABLE_FIELDS = ['name', 'email', 'created_at'];

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(
        string $search,
        string $sortField,
        string $sortDirection,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $sortField = in_array($sortField, self::SORTABLE_FIELDS, true) ? $sortField : 'name';
        $direction = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'asc';

        return User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sortField, $direction)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function find(int $id): User
    {
        return User::query()
            ->with('roles')
            ->findOrFail($id);
    }

    /**
     * @return Collection<int, Role>
     */
    public function availableRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, email: string, password: string, selectedRoles?: array<int, string>}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $this->syncRoles($user, $data['selectedRoles'] ?? []);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, selectedRoles?: array<int, string>}  $data
     */
    public function update(int $userId, array $data): User
    {
        return DB::transaction(function () use ($userId, $data): User {
            $user = User::query()
                ->with('roles')
                ->findOrFail($userId);

            $roles = $data['selectedRoles'] ?? [];

            $this->ensureAdminRoleIsKept($user, $roles);

            $user->name = $data['name'];
            $user->email = $data['email'];

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            $this->syncRoles($user, $roles);

            return $user;
        });
    }

    public function delete(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $user = User::query()
                ->with('roles')
                ->lockForUpdate()
                ->findOrFail($userId);

            if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
                throw LastAdminException::cannotDeleteUser();
            }

            $user->delete();
        });
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function ensureAdminRoleIsKept(User $user, array $roles): void
    {
        if (in_array('admin', $roles, true)) {
            return;
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            throw LastAdminException::cannotRemoveRole();
        }
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function syncRoles(User $user, array $roles): void
    {
        $roles === []
            ? $user->assignRole('user')
            : $user->syncRoles($roles);
    }
}
