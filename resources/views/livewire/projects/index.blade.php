<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate('required|string|min:3|max:120')]
    public string $title = '';

    public function create(): void
    {
        $this->validate();

        $project = Auth::user()->projects()->create([
            'title' => $this->title,
            'status' => Project::STATUS_DRAFT,
        ]);

        $this->redirectRoute('projects.builder', $project, navigate: true);
    }

    public function delete(int $id): void
    {
        $project = Auth::user()->projects()->findOrFail($id);
        $this->authorize('delete', $project);
        $project->delete();
    }

    public function with(): array
    {
        return [
            'projects' => Auth::user()->projects()
                ->withCount('scenes')
                ->with('latestRender')
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-8">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Projects') }}</h2>
    </div>

    <form wire:submit="create" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('New project title') }}</label>
        <div class="mt-2 flex flex-wrap gap-3">
            <input id="title" type="text" wire:model="title" placeholder="{{ __('e.g. Monday motivation reel') }}"
                   class="flex-1 min-w-64 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <button type="submit"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                {{ __('Create & build') }}
            </button>
        </div>
        @error('title') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </form>

    <div class="space-y-3">
        @forelse ($projects as $project)
            <div wire:key="project-{{ $project->id }}"
                 class="flex items-center justify-between bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4">
                <div>
                    <a href="{{ route('projects.builder', $project) }}" wire:navigate
                       class="font-medium text-indigo-600 hover:text-indigo-500">{{ $project->title }}</a>
                    <p class="text-xs text-gray-400">
                        {{ trans_choice(':count scene|:count scenes', $project->scenes_count, ['count' => $project->scenes_count]) }}
                        &middot;
                        <span class="capitalize">{{ $project->status }}</span>
                        @if ($project->latestRender)
                            &middot; {{ __('last render') }}: {{ $project->latestRender->status }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('projects.timeline', $project) }}" wire:navigate
                       class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{{ __('Timeline') }}</a>
                    <button type="button" wire:click="delete({{ $project->id }})"
                            wire:confirm="{{ __('Delete this project and all its scenes?') }}"
                            class="text-red-600 hover:text-red-500">{{ __('Delete') }}</button>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No projects yet. Create one above.') }}</p>
        @endforelse
    </div>

  </div>
</div>
