<?php

use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Services\CreditService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $adjustingId = null;
    public int $adjustAmount = 0;
    public string $adjustReason = 'admin_adjustment';

    public function startAdjust(int $id): void
    {
        $this->adjustingId = $id;
        $this->adjustAmount = 0;
        $this->adjustReason = 'admin_adjustment';
        $this->resetErrorBag();
    }

    public function saveAdjust(CreditService $credits): void
    {
        $this->validate([
            'adjustAmount' => 'required|integer|not_in:0',
            'adjustReason' => 'required|string|max:100',
        ]);

        $user = User::findOrFail($this->adjustingId);

        try {
            $this->adjustAmount > 0
                ? $credits->grant($user, $this->adjustAmount, $this->adjustReason, ['by' => auth()->id()])
                : $credits->charge($user, $this->adjustAmount, $this->adjustReason, ['by' => auth()->id()]);
        } catch (InsufficientCreditsException) {
            $this->addError('adjustAmount', __('User does not have that many credits to remove.'));

            return;
        }

        $this->adjustingId = null;
        session()->flash('status', __('Credits updated for :name.', ['name' => $user->name]));
    }

    public function toggleAdmin(int $id): void
    {
        $user = User::findOrFail($id);
        abort_if($user->id === auth()->id(), 403, 'You cannot change your own role.');

        $user->isAdmin() ? $user->removeRole(User::ROLE_ADMIN) : $user->assignRole(User::ROLE_ADMIN);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")))
                ->withCount('projects')
                ->orderBy('id')
                ->paginate(15),
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Admin') }}</h2>
    <x-admin-nav />

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-700 dark:text-green-300">{{ session('status') }}</p>
    @endif

    <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name or email') }}"
           class="mb-4 w-full max-w-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm" />

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2">{{ __('User') }}</th>
                    <th class="px-4 py-2">{{ __('Projects') }}</th>
                    <th class="px-4 py-2">{{ __('Credits') }}</th>
                    <th class="px-4 py-2">{{ __('Role') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($users as $user)
                    <tr wire:key="u-{{ $user->id }}">
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-800 dark:text-gray-200">{{ $user->name }}</div>
                            <div class="text-xs text-gray-400">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-2">{{ $user->projects_count }}</td>
                        <td class="px-4 py-2 font-medium">{{ $user->credits }}</td>
                        <td class="px-4 py-2">
                            <button type="button" wire:click="toggleAdmin({{ $user->id }})"
                                    @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-indigo-100 text-indigo-700' => $user->isAdmin(),
                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $user->isAdmin(),
                                    ])>
                                {{ $user->isAdmin() ? __('admin') : __('user') }}
                            </button>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button type="button" wire:click="startAdjust({{ $user->id }})"
                                    class="text-indigo-600 hover:text-indigo-500">{{ __('Adjust credits') }}</button>
                        </td>
                    </tr>
                    @if ($adjustingId === $user->id)
                        <tr wire:key="adj-{{ $user->id }}" class="bg-indigo-50/50 dark:bg-indigo-900/10">
                            <td colspan="5" class="px-4 py-3">
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500">{{ __('Amount (+/-)') }}</label>
                                        <input type="number" wire:model="adjustAmount"
                                               class="mt-1 w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">{{ __('Reason') }}</label>
                                        <input type="text" wire:model="adjustReason"
                                               class="mt-1 w-56 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm" />
                                    </div>
                                    <button type="button" wire:click="saveAdjust"
                                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save') }}</button>
                                    <button type="button" wire:click="$set('adjustingId', null)"
                                            class="text-sm text-gray-500">{{ __('Cancel') }}</button>
                                </div>
                                @error('adjustAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('adjustReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
  </div>
</div>
