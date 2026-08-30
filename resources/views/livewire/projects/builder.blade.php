<?php

use App\Models\Project;
use App\Models\Scene;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Project $project;

    public ?int $selectedSceneId = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->selectedSceneId = $project->scenes()->value('id');
    }

    public function addScene(): void
    {
        $scene = $this->project->scenes()->create([
            'order' => (int) $this->project->scenes()->max('order') + 1,
            'duration_seconds' => config('video.scene.default_duration_seconds'),
        ]);

        $this->selectedSceneId = $scene->id;
    }

    public function selectScene(int $id): void
    {
        $this->selectedSceneId = $id;
    }

    public function deleteScene(int $id): void
    {
        $scene = $this->project->scenes()->findOrFail($id);
        $scene->delete();

        $this->reindex();

        if ($this->selectedSceneId === $id) {
            $this->selectedSceneId = $this->project->scenes()->value('id');
        }
    }

    /** Called by the Alpine sort handler: scene $id was dropped at 0-based $position. */
    public function moveScene(int $id, int $position): void
    {
        $ids = $this->project->scenes()->pluck('id')->all();
        $ids = array_values(array_filter($ids, fn ($x) => $x !== $id));
        array_splice($ids, max(0, $position), 0, [$id]);

        foreach ($ids as $index => $sceneId) {
            Scene::whereKey($sceneId)->update(['order' => $index + 1]);
        }
    }

    private function reindex(): void
    {
        foreach ($this->project->scenes()->get() as $index => $scene) {
            $scene->update(['order' => $index + 1]);
        }
    }

    #[On('scene-updated')]
    public function refreshList(): void
    {
        // just re-renders; scene rows read fresh data
    }

    public function with(): array
    {
        return [
            'scenes' => $this->project->scenes()->with('sceneCharacter.characterPose')->get(),
        ];
    }
}; ?>

<div class="py-8">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('projects.index') }}" wire:navigate class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('All projects') }}</a>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $project->title }}</h2>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('projects.timeline', $project) }}" wire:navigate
               class="rounded-md border border-gray-300 dark:border-gray-600 px-3 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                {{ __('Preview timeline') }}
            </a>
        </div>
    </div>

    <div class="mt-6">
        <livewire:projects.render-panel :project="$project" :wire:key="'render-panel-'.$project->id" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[280px_1fr]">

        {{-- Scene list --}}
        <div>
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Scenes') }}</h3>
                <button type="button" wire:click="addScene"
                        class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-500">
                    + {{ __('Add') }}
                </button>
            </div>

            @if ($scenes->isEmpty())
                <p class="mt-3 text-sm text-gray-400">{{ __('No scenes yet.') }}</p>
            @else
                <ul class="mt-3 space-y-2"
                    x-data
                    x-sort="$wire.moveScene($item, $position)"
                    x-sort:config="{ animation: 150, handle: '[data-drag]' }">
                    @foreach ($scenes as $scene)
                        <li x-sort:item="{{ $scene->id }}" wire:key="scene-row-{{ $scene->id }}"
                            @class([
                                'flex items-center gap-2 rounded-lg border p-2 text-sm',
                                'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' => $selectedSceneId === $scene->id,
                                'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' => $selectedSceneId !== $scene->id,
                            ])>
                            <span data-drag class="cursor-grab select-none text-gray-400" title="{{ __('Drag to reorder') }}">⠿</span>
                            <button type="button" wire:click="selectScene({{ $scene->id }})" class="flex-1 text-left">
                                <span class="font-medium">{{ __('Scene') }} {{ $loop->iteration }}</span>
                                <span class="block text-xs text-gray-400 truncate">
                                    {{ $scene->duration_seconds }}s
                                    @if ($scene->sceneCharacter?->characterPose)
                                        &middot; {{ $scene->sceneCharacter->characterPose->pose_name }}
                                    @endif
                                    @if ($scene->dialogue_text)
                                        &middot; “{{ \Illuminate\Support\Str::limit($scene->dialogue_text, 20) }}”
                                    @endif
                                </span>
                            </button>
                            <button type="button" wire:click="deleteScene({{ $scene->id }})"
                                    wire:confirm="{{ __('Delete this scene?') }}"
                                    class="text-gray-300 hover:text-red-500">&times;</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Scene editor --}}
        <div>
            @if ($selectedSceneId)
                <livewire:projects.scene-editor :scene-id="$selectedSceneId" :wire:key="'scene-editor-'.$selectedSceneId" />
            @else
                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-12 text-center text-sm text-gray-400">
                    {{ __('Add a scene to start building.') }}
                </div>
            @endif
        </div>

    </div>
  </div>
</div>
