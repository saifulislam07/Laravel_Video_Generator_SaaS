<?php

use App\Models\VideoRender;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin'), Title('Renders')] class extends Component
{
    use WithPagination;

    public string $paginationTheme = 'bootstrap';

    public string $status = '';

    public function delete(int $id): void
    {
        VideoRender::findOrFail($id)->delete();
        session()->flash('status', __('Render removed.'));
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'renders' => VideoRender::query()
                ->with('project.user')
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->latest()
                ->paginate(20),
            'statuses' => VideoRender::STATUSES,
        ];
    }
}; ?>

<div>
    <h1 class="mb-4">Renders</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <select wire:model.live="status" class="form-control" style="max-width: 220px">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-striped">
                <thead>
                    <tr><th>Project</th><th>User</th><th>Status</th><th>Video</th><th>Created</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($renders as $render)
                        <tr wire:key="r-{{ $render->id }}">
                            <td>{{ $render->project?->title ?? '—' }}</td>
                            <td class="text-muted">{{ $render->project?->user?->email ?? '—' }}</td>
                            <td class="text-capitalize">{{ $render->status }}</td>
                            <td>
                                @if ($render->output_url)
                                    <a href="{{ $render->output_url }}" target="_blank" rel="noopener">open</a>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="text-muted">{{ $render->created_at->diffForHumans() }}</td>
                            <td class="text-right">
                                <button type="button" wire:click="delete({{ $render->id }})"
                                        wire:confirm="Delete this render record?"
                                        class="btn btn-xs btn-outline-danger">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No renders.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $renders->links() }}</div>
    </div>
</div>
