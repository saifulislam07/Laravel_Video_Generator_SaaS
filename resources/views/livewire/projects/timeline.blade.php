<?php

use App\Models\Project;
use App\Services\ShotstackPayloadBuilder;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Project $project;

    public bool $showPayload = false;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function with(ShotstackPayloadBuilder $builder): array
    {
        $scenes = $this->project->scenes()->with('sceneCharacter.characterPose')->get();

        $payloadJson = null;
        $payloadError = null;

        if ($this->showPayload) {
            try {
                $payloadJson = json_encode($builder->build($this->project), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } catch (\Throwable $e) {
                $payloadError = $e->getMessage();
            }
        }

        return [
            'scenes' => $scenes,
            'totalDuration' => $scenes->sum('duration_seconds'),
            'payloadJson' => $payloadJson,
            'payloadError' => $payloadError,
        ];
    }
}; ?>

<div class="py-8">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('projects.builder', $project) }}" wire:navigate class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Back to builder') }}</a>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $project->title }} — {{ __('Timeline') }}</h2>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ trans_choice(':count scene|:count scenes', $scenes->count(), ['count' => $scenes->count()]) }}
            &middot; {{ $totalDuration }}s {{ __('total') }}
        </p>
    </div>

    @if ($scenes->isEmpty())
        <p class="mt-8 text-sm text-gray-500 dark:text-gray-400">{{ __('This project has no scenes yet.') }}</p>
    @else
        <div class="mt-6 flex gap-4 overflow-x-auto pb-4">
            @foreach ($scenes as $scene)
                @php($link = $scene->sceneCharacter)
                <figure wire:key="tl-{{ $scene->id }}" class="w-40 shrink-0">
                    <div class="relative aspect-[9/16] overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                        @if ($scene->backgroundUrl())
                            <img src="{{ $scene->backgroundUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" />
                        @endif
                        @if ($link?->characterPose)
                            <img src="{{ $link->characterPose->url() }}" alt=""
                                 class="absolute w-1/2"
                                 style="left: {{ $link->position_x }}%; top: {{ $link->position_y }}%; transform: translate(-50%,-50%) scale({{ $link->scale }});" />
                        @endif
                        <span class="absolute left-1 top-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">{{ $loop->iteration }}</span>
                        <span class="absolute right-1 top-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">{{ $scene->duration_seconds }}s</span>
                        @if ($scene->dialogue_text)
                            <figcaption class="absolute inset-x-0 bottom-0 bg-black/55 p-1 text-center text-[10px] text-white">
                                {{ \Illuminate\Support\Str::limit($scene->dialogue_text, 60) }}
                            </figcaption>
                        @endif
                    </div>
                </figure>
            @endforeach
        </div>
    @endif

    <div class="mt-8">
        <button type="button" wire:click="$toggle('showPayload')"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
            {{ $showPayload ? __('Hide') : __('Show') }} {{ __('Shotstack render JSON') }}
        </button>
        @if ($showPayload)
            @if ($payloadError)
                <p class="mt-2 text-sm text-red-600">{{ $payloadError }}</p>
            @else
                <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">{{ $payloadJson }}</pre>
            @endif
        @endif
    </div>

  </div>
</div>
