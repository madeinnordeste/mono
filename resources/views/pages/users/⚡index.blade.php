<?php

use App\Exceptions\LastAdminException;
use App\Services\Users\UserService;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    protected UserService $userService;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $showUserModal = false;

    /** @var array<int, string> */
    public array $selectedRoles = [];

    public bool $showDeleteModal = false;
    public ?int $deletingUserId = null;
    public string $deletingUserName = '';

    public function boot(UserService $userService): void
    {
        $this->userService = $userService;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, UserService::SORTABLE_FIELDS, true)) {
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
    public function users(): LengthAwarePaginator
    {
        return $this->userService->paginate(
            $this->search,
            $this->sortField,
            $this->sortDirection,
        );
    }

    #[Computed]
    public function availableRoles(): Collection
    {
        return $this->userService->availableRoles();
    }

    public function createUser(): void
    {
        $this->reset('userId', 'name', 'email', 'password', 'password_confirmation', 'selectedRoles');
        $this->resetErrorBag();
        $this->showUserModal = true;
    }

    public function editUser(int $id): void
    {
        $user = $this->userService->find($id);

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->selectedRoles = $user->roles->pluck('name')->all();
        $this->resetErrorBag();
        $this->showUserModal = true;
    }

    public function saveUser(): void
    {
        $validated = $this->validate($this->userRules());

        try {
            if ($this->userId) {
                $this->userService->update($this->userId, $validated);
            } else {
                $this->userService->create($validated);
            }
        } catch (LastAdminException $exception) {
            $this->addError('selectedRoles', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('User saved successfully.'));

        $this->closeUserModal();
    }

    /**
     * @return array<string, mixed>
     */
    private function userRules(): array
    {
        $emailRules = ['required', 'string', 'email', 'max:255'];

        if ($this->userId) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($this->userId);
        } else {
            $emailRules[] = 'unique:users,email';
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'password' => [
                $this->userId ? 'nullable' : 'required',
                'string',
                Password::defaults(),
                'confirmed',
            ],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function confirmDelete(int $id): void
    {
        $user = $this->userService->find($id);

        $this->deletingUserId = $user->id;
        $this->deletingUserName = $user->name;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        if (! $this->deletingUserId) {
            return;
        }

        if (Auth::id() === $this->deletingUserId) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account.'));
            $this->closeDeleteModal();

            return;
        }

        try {
            $this->userService->delete($this->deletingUserId);
        } catch (LastAdminException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
            $this->closeDeleteModal();

            return;
        }

        Flux::toast(variant: 'success', text: __('User deleted successfully.'));

        $this->closeDeleteModal();
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->reset('userId', 'name', 'email', 'password', 'password_confirmation', 'selectedRoles');
        $this->resetErrorBag();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset('deletingUserId', 'deletingUserName');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('Manage registered users and their account details.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="createUser">
            {{ __('New user') }}
        </flux:button>
    </div>

    <div class="flex items-center justify-between gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search users...') }}"
            class="max-w-xs"
            clearable
        />
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'email'" :direction="$sortDirection" wire:click="sortBy('email')">{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Roles') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'created_at'" :direction="$sortDirection" wire:click="sortBy('created_at')">{{ __('Created at') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell class="flex items-center gap-3">
                        <flux:avatar :name="$user->name" :initials="$user->initials()" size="sm" />
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</span>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap items-center gap-1">
                            @forelse ($user->roles as $role)
                                <flux:badge size="sm" :color="$role->name === 'admin' ? 'red' : 'zinc'">{{ $role->name }}</flux:badge>
                            @empty
                                <flux:text size="sm" class="text-zinc-400">{{ __('None') }}</flux:text>
                            @endforelse
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-2">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil-square"
                                wire:click="editUser({{ $user->id }})"
                                aria-label="{{ __('Edit') }}"
                            />
                            @if (auth()->id() !== $user->id)
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="confirmDelete({{ $user->id }})"
                                    class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                    aria-label="{{ __('Delete') }}"
                                />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                        {{ __('No users found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal
        name="user-modal"
        class="max-w-md md:min-w-md"
        @close="closeUserModal"
        wire:model="showUserModal"
    >
        <form wire:submit="saveUser" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $userId ? __('Edit user') : __('New user') }}
                </flux:heading>
                <flux:subheading>
                    {{ $userId ? __('Update the user details.') : __('Create a new user account.') }}
                </flux:subheading>
            </div>

            <flux:input
                wire:model="name"
                :label="__('Name')"
                type="text"
                required
                autocomplete="name"
            />

            <flux:input
                wire:model="email"
                :label="__('Email')"
                type="email"
                required
                autocomplete="email"
            />

            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                :required="!$userId"
                autocomplete="new-password"
                viewable
                :description="$userId ? __('Leave blank to keep the current password.') : null"
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                :required="!$userId"
                autocomplete="new-password"
                viewable
            />

            <flux:checkbox.group
                wire:model="selectedRoles"
                :label="__('Roles')"
                class="max-h-64 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-3 pe-1 dark:border-white/10 dark:bg-white/10"
            >
                @forelse ($this->availableRoles as $role)
                    <flux:checkbox :label="$role->name" :value="$role->name" />
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No roles available yet.') }}</flux:text>
                @endforelse
            </flux:checkbox.group>

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="outline"
                    wire:click="closeUserModal"
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
        name="delete-user-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Delete user') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete the user ":name"? This action cannot be undone.', ['name' => $deletingUserName]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="outline"
                    wire:click="closeDeleteModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteUser"
                >
                    {{ __('Delete user') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
