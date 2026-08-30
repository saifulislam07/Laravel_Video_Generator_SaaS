<?php

use App\Models\VideoRender;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

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

<div class="py-10">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Admin') }}</h2>
    <x-admin-nav />

    @if (session('status'))
        <p class="mb-4 rounded-md bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-700 dark:text-green-300">{{ session('status') }}</p>
    @endif

    <select wire:model.live="status" class="mb-4 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm">
        <option value="">{{ __('All statuses') }}</option>
        @foreach ($statuses as $s)
            <option value="{{ $s }}">{{ ucfirst($s) }}</option>
        @endforeach
    </select>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2">{{ __('Project') }}</th>
                    <th class="px-4 py-2">{{ __('User') }}</th>
                    <th class="px-4 py-2">{{ __('Status') }}</th>
                    <th class="px-4 py-2">{{ __('Video') }}</th>
                    <th class="px-4 py-2">{{ __('Created') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($renders as $render)
                    <tr wire:key="r-{{ $render->id }}">
                        <td class="px-4 py-2">{{ $render->project?->title ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $render->project?->user?->email ?? '—' }}</td>
                        <td class="px-4 py-2 capitalize">{{ $render->status }}</td>
                        <td class="px-4 py-2">
                            @if ($render->output_url)
                                <a href="{{ $render->output_url }}" target="_blank" rel="noopener"
                                   class="text-indigo-600 hover:text-indigo-500">{{ __('open') }}</a>
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-400">{{ $render->created_at->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-right">
                            <button type="button" wire:click="delete({{ $render->id }})"
                                    wire:confirm="{{ __('Delete this render record?') }}"
                                    class="text-red-600 hover:text-red-500">{{ __('Delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('No renders.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $renders->links() }}</div>
  </div>
</div>
