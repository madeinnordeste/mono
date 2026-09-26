<?php

use App\Exceptions\RoleDeletionException;
use App\Services\Permissions\PermissionService;
use App\Services\Roles\RoleService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Roles')] class extends Component {
    use WithPagination;

    protected RoleService $roleService;

    protected PermissionService $permissionService;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public ?int $roleId = null;
    public string $name = '';
    public string $guardName = 'web';
    public bool $showRoleModal = false;

    public bool $showDeleteModal = false;
    public ?int $deletingRoleId = null;
    public string $deletingRoleName = '';
    public int $deletingRoleUsersCount = 0;

    public bool $showPermissionsModal = false;
    public int $managingRoleId = 0;
    public string $managingRoleName = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function boot(RoleService $roleService, PermissionService $permissionService): void
    {
        $this->roleService = $roleService;
        $this->permissionService = $permissionService;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, RoleService::SORTABLE_FIELDS, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        return $this->roleService->paginate(
            $this->search,
            $this->sortField,
            $this->sortDirection,
        );
    }

    /**
     * Permissions grouped by the resource prefix of their name.
     *
     * @return Collection<string, Collection<int, string>>
     */
    #[Computed]
    public function permissionGroups(): Collection
    {
        return $this->permissionService->groupedNames();
    }

    #[Computed]
    public function isEditingAdminRole(): bool
    {
        return $this->roleService->isProtected($this->roleId);
    }

    public function createRole(): void
    {
        $this->reset('roleId', 'name');
        $this->guardName = 'web';
        $this->resetErrorBag();
        $this->showRoleModal = true;
    }

    public function editRole(int $id): void
    {
        $role = $this->roleService->find($id);

        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->guardName = $role->guard_name;
        $this->resetErrorBag();
        $this->showRoleModal = true;
    }

    public function saveRole(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', RoleService::GUARD_NAME))
                    ->ignore($this->roleId),
            ],
        ]);

        if ($this->roleId) {
            $this->roleService->update($this->roleId, $validated);
        } else {
            $this->roleService->create($validated);
        }

        Flux::toast(variant: 'success', text: __('Role saved successfully.'));

        $this->closeRoleModal();
    }

    public function managePermissions(int $id): void
    {
        $role = $this->roleService->findWithPermissions($id);

        $this->managingRoleId = $role->id;
        $this->managingRoleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
        $this->resetErrorBag();
        $this->showPermissionsModal = true;
    }

    public function syncPermissions(): void
    {
        $validated = $this->validate([
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => [
                'string',
                Rule::exists('permissions', 'name')
                    ->where('guard_name', RoleService::GUARD_NAME),
            ],
        ]);

        $this->roleService->syncPermissions($this->managingRoleId, $validated['selectedPermissions']);

        Flux::toast(variant: 'success', text: __('Permissions updated successfully.'));

        $this->closePermissionsModal();
    }

    public function confirmDelete(int $id): void
    {
        $role = $this->roleService->findWithUserCount($id);

        $this->deletingRoleId = $role->id;
        $this->deletingRoleName = $role->name;
        $this->deletingRoleUsersCount = $role->users_count;
        $this->showDeleteModal = true;
    }

    public function deleteRole(): void
    {
        if (! $this->deletingRoleId) {
            return;
        }

        try {
            $this->roleService->delete($this->deletingRoleId);
        } catch (RoleDeletionException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
            $this->closeDeleteModal();

            return;
        }

        Flux::toast(variant: 'success', text: __('Role deleted successfully.'));

        $this->closeDeleteModal();
    }

    public function closeRoleModal(): void
    {
        $this->showRoleModal = false;
        $this->reset('roleId', 'name', 'guardName');
        $this->resetErrorBag();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset('deletingRoleId', 'deletingRoleName', 'deletingRoleUsersCount');
    }

    public function closePermissionsModal(): void
    {
        $this->showPermissionsModal = false;
        $this->reset('managingRoleId', 'managingRoleName', 'selectedPermissions');
        $this->resetErrorBag();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Roles') }}</flux:heading>
            <flux:subheading>{{ __('Manage roles and the permissions granted to them.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="createRole">
            {{ __('New role') }}
        </flux:button>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search roles...') }}"
            class="max-w-xs"
            clearable
        />
    </div>

    <flux:table :paginate="$this->roles">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'guard_name'" :direction="$sortDirection" wire:click="sortBy('guard_name')">{{ __('Guard') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Users') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Permissions') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'created_at'" :direction="$sortDirection" wire:click="sortBy('created_at')">{{ __('Created at') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->roles as $role)
                <flux:table.row :key="$role->id">
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        <div class="flex items-center gap-2">
                            <span>{{ $role->name }}</span>
                            @if ($role->name === 'admin')
                                <flux:badge size="sm" color="red">{{ __('Protected') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $role->guard_name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $role->users_count }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $role->permissions_count }}</flux:table.cell>
                    <flux:table.cell>{{ $role->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-2">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil-square"
                                wire:click="editRole({{ $role->id }})"
                                aria-label="{{ __('Edit') }}"
                            />
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="key"
                                wire:click="managePermissions({{ $role->id }})"
                                aria-label="{{ __('Manage permissions') }}"
                            />
                            @if ($role->name !== 'admin')
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="confirmDelete({{ $role->id }})"
                                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                    aria-label="{{ __('Delete') }}"
                                />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500">
                        {{ __('No roles found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal
        name="role-modal"
        class="max-w-md md:min-w-md"
        @close="closeRoleModal"
        wire:model="showRoleModal"
    >
        <form wire:submit="saveRole" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $roleId ? __('Edit role') : __('New role') }}
                </flux:heading>
                <flux:subheading>
                    {{ $roleId ? __('Update the role details.') : __('Create a new role.') }}
                </flux:subheading>
            </div>

            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                autocomplete="off"
                :disabled="$this->isEditingAdminRole"
                :description="$this->isEditingAdminRole ? __('The admin role cannot be renamed.') : __('Use lowercase letters, numbers, dots, dashes or underscores.')"
            />

            <flux:input
                wire:model="guardName"
                :label="__('Guard')"
                type="text"
                disabled
                :description="__('The guard is fixed and cannot be changed.')"
            />

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="outline"
                    wire:click="closeRoleModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    type="submit"
                >
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        name="delete-role-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete role') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete the role ":name"? This action cannot be undone.', ['name' => $deletingRoleName]) }}
                </flux:text>
            </div>

            @if ($deletingRoleUsersCount > 0)
                <flux:callout variant="secondary" icon="exclamation-triangle">
                    {{ __('This role has :count user(s) assigned to it, so it cannot be deleted.', ['count' => $deletingRoleUsersCount]) }}
                </flux:callout>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="outline"
                    wire:click="closeDeleteModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteRole"
                >
                    {{ __('Delete role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="role-permissions-modal"
        class="max-w-2xl md:min-w-2xl"
        @close="closePermissionsModal"
        wire:model="showPermissionsModal"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Permissions for :role', ['role' => $managingRoleName]) }}</flux:heading>
                <flux:subheading>{{ __('Select the permissions granted to this role.') }}</flux:subheading>
            </div>

            <div class="max-h-96 space-y-6 overflow-y-auto pe-1">
                @forelse ($this->permissionGroups as $group => $permissions)
                    <div>
                        <flux:checkbox.group wire:model="selectedPermissions">
                            <flux:checkbox.all :label="str($group)->headline()" />
                            @foreach ($permissions as $permission)
                                <flux:checkbox :label="$permission" :value="$permission" />
                            @endforeach
                        </flux:checkbox.group>
                    </div>
                @empty
                    <flux:callout variant="secondary" icon="information-circle" heading="{{ __('No permissions available yet.') }}" />
                @endforelse
            </div>

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="outline"
                    wire:click="closePermissionsModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="syncPermissions"
                >
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
