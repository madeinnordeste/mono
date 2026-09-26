<?php

use App\Exceptions\PermissionDeletionException;
use App\Services\Permissions\PermissionService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Permissions')] class extends Component {
    use WithPagination;

    protected PermissionService $permissionService;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public ?int $permissionId = null;
    public string $name = '';
    public string $guardName = 'web';
    public bool $showPermissionModal = false;

    public bool $showDeleteModal = false;
    public ?int $deletingPermissionId = null;
    public string $deletingPermissionName = '';
    public int $deletingPermissionRolesCount = 0;
    public int $deletingPermissionUsersCount = 0;

    public function boot(PermissionService $permissionService): void
    {
        $this->permissionService = $permissionService;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, PermissionService::SORTABLE_FIELDS, true)) {
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
    public function permissions(): LengthAwarePaginator
    {
        return $this->permissionService->paginate(
            $this->search,
            $this->sortField,
            $this->sortDirection,
        );
    }

    public function createPermission(): void
    {
        $this->reset('permissionId', 'name');
        $this->guardName = 'web';
        $this->resetErrorBag();
        $this->showPermissionModal = true;
    }

    public function editPermission(int $id): void
    {
        $permission = $this->permissionService->find($id);

        $this->permissionId = $permission->id;
        $this->name = $permission->name;
        $this->guardName = $permission->guard_name;
        $this->resetErrorBag();
        $this->showPermissionModal = true;
    }

    public function savePermission(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', PermissionService::GUARD_NAME))
                    ->ignore($this->permissionId),
            ],
        ]);

        if ($this->permissionId) {
            $this->permissionService->update($this->permissionId, $validated);
        } else {
            $this->permissionService->create($validated);
        }

        Flux::toast(variant: 'success', text: __('Permission saved successfully.'));

        $this->closePermissionModal();
    }

    public function confirmDelete(int $id): void
    {
        $permission = $this->permissionService->findWithCounts($id);

        $this->deletingPermissionId = $permission->id;
        $this->deletingPermissionName = $permission->name;
        $this->deletingPermissionRolesCount = $permission->roles_count;
        $this->deletingPermissionUsersCount = $permission->users_count;
        $this->showDeleteModal = true;
    }

    public function deletePermission(): void
    {
        if (! $this->deletingPermissionId) {
            return;
        }

        try {
            $this->permissionService->delete($this->deletingPermissionId);
        } catch (PermissionDeletionException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
            $this->closeDeleteModal();

            return;
        }

        Flux::toast(variant: 'success', text: __('Permission deleted successfully.'));

        $this->closeDeleteModal();
    }

    public function closePermissionModal(): void
    {
        $this->showPermissionModal = false;
        $this->reset('permissionId', 'name', 'guardName');
        $this->resetErrorBag();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset('deletingPermissionId', 'deletingPermissionName', 'deletingPermissionRolesCount', 'deletingPermissionUsersCount');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Permissions') }}</flux:heading>
            <flux:subheading>{{ __('Manage the permissions that can be granted to roles.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="createPermission">
            {{ __('New permission') }}
        </flux:button>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search permissions...') }}"
            class="max-w-xs"
            clearable
        />
    </div>

    <flux:table :paginate="$this->permissions">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'guard_name'" :direction="$sortDirection" wire:click="sortBy('guard_name')">{{ __('Guard') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Roles') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Users') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'created_at'" :direction="$sortDirection" wire:click="sortBy('created_at')">{{ __('Created at') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->permissions as $permission)
                <flux:table.row :key="$permission->id">
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        <div class="flex items-center gap-2">
                            <span>{{ $permission->name }}</span>
                            @if (str_contains($permission->name, '.'))
                                <flux:badge size="sm" color="zinc">{{ str($permission->name)->before('.') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $permission->guard_name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $permission->roles_count }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $permission->users_count }}</flux:table.cell>
                    <flux:table.cell>{{ $permission->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-2">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil-square"
                                wire:click="editPermission({{ $permission->id }})"
                                aria-label="{{ __('Edit') }}"
                            />
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                wire:click="confirmDelete({{ $permission->id }})"
                                class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                aria-label="{{ __('Delete') }}"
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500">
                        {{ __('No permissions found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal
        name="permission-modal"
        class="max-w-md md:min-w-md"
        @close="closePermissionModal"
        wire:model="showPermissionModal"
    >
        <form wire:submit="savePermission" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $permissionId ? __('Edit permission') : __('New permission') }}
                </flux:heading>
                <flux:subheading>
                    {{ $permissionId ? __('Update the permission details.') : __('Create a new permission.') }}
                </flux:subheading>
            </div>

            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                autocomplete="off"
                :description="__('Use the resource.action format, e.g. users.view.')"
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
                    wire:click="closePermissionModal"
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
        name="delete-permission-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete permission') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete the permission ":name"? This action cannot be undone.', ['name' => $deletingPermissionName]) }}
                </flux:text>
            </div>

            @if ($deletingPermissionRolesCount > 0 || $deletingPermissionUsersCount > 0)
                <flux:callout variant="secondary" icon="exclamation-triangle">
                    {{ __('This permission is currently granted to :roles role(s) and :users user(s), so it cannot be deleted.', ['roles' => $deletingPermissionRolesCount, 'users' => $deletingPermissionUsersCount]) }}
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
                    wire:click="deletePermission"
                >
                    {{ __('Delete permission') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
