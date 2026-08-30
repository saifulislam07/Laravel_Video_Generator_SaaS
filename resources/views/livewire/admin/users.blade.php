<?php

use App\Exceptions\InsufficientCreditsException;
use App\Models\User;
use App\Services\CreditService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin'), Title('Users')] class extends Component
{
    use WithPagination;

    public string $paginationTheme = 'bootstrap';

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

<div>
    <h1 class="mb-4">Users</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="form-control" style="max-width: 320px" placeholder="Search name or email">
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover">
                <thead>
                    <tr><th>User</th><th>Projects</th><th>Credits</th><th>Role</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr wire:key="u-{{ $user->id }}">
                            <td>
                                <strong>{{ $user->name }}</strong><br>
                                <small class="text-muted">{{ $user->email }}</small>
                            </td>
                            <td>{{ $user->projects_count }}</td>
                            <td><span class="badge badge-light">{{ $user->credits }}</span></td>
                            <td>
                                <button type="button" wire:click="toggleAdmin({{ $user->id }})"
                                        class="btn btn-xs {{ $user->isAdmin() ? 'btn-primary' : 'btn-default' }}">
                                    {{ $user->isAdmin() ? 'admin' : 'user' }}
                                </button>
                            </td>
                            <td class="text-right">
                                <button type="button" wire:click="startAdjust({{ $user->id }})"
                                        class="btn btn-xs btn-outline-primary">Adjust credits</button>
                            </td>
                        </tr>
                        @if ($adjustingId === $user->id)
                            <tr wire:key="adj-{{ $user->id }}" class="bg-light">
                                <td colspan="5">
                                    <div class="form-inline">
                                        <label class="mr-2">Amount (+/-)</label>
                                        <input type="number" wire:model="adjustAmount" class="form-control form-control-sm mr-3" style="width: 100px">
                                        <label class="mr-2">Reason</label>
                                        <input type="text" wire:model="adjustReason" class="form-control form-control-sm mr-3" style="width: 220px">
                                        <button type="button" wire:click="saveAdjust" class="btn btn-sm btn-primary mr-2">Save</button>
                                        <button type="button" wire:click="$set('adjustingId', null)" class="btn btn-sm btn-link">Cancel</button>
                                    </div>
                                    @error('adjustAmount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    @error('adjustReason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $users->links() }}</div>
    </div>
</div>
