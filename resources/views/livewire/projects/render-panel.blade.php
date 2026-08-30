<?php

use App\Exceptions\RenderException;
use App\Models\Project;
use App\Models\VideoRender;
use App\Services\ShotstackPayloadBuilder;
use App\Services\VideoRenderService;
use Livewire\Volt\Component;

new class extends Component
{
    public Project $project;

    public ?string $error = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
    }

    public function render_(VideoRenderService $service): void
    {
        $this->authorize('update', $this->project);
        $this->error = null;

        try {
            $service->submit($this->project->fresh());
        } catch (RenderException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function canRender(ShotstackPayloadBuilder $builder): bool
    {
        if ($this->inProgress()) {
            return false;
        }

        try {
            $builder->build($this->project);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function inProgress(): bool
    {
        return in_array($this->latest()?->status, [
            VideoRender::STATUS_QUEUED,
            VideoRender::STATUS_RENDERING,
        ], true);
    }

    public function latest(): ?VideoRender
    {
        return $this->project->videoRenders()->latest()->first();
    }

    public function with(): array
    {
        return ['latest' => $this->latest()];
    }
}; ?>

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5"
     @if ($this->inProgress()) wire:poll.6s @endif
     x-data
     x-init="window.Echo && window.Echo.private('projects.{{ $project->id }}')
                .listen('.render.status', () => $wire.$refresh())">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Render') }}</h3>
            @if ($latest)
                <p class="text-xs text-gray-400">
                    {{ __('Status') }}:
                    <span @class([
                        'font-medium',
                        'text-amber-600' => in_array($latest->status, ['queued', 'rendering']),
                        'text-green-600' => $latest->status === 'done',
                        'text-red-600' => $latest->status === 'failed',
                    ])>{{ ucfirst($latest->status) }}</span>
                    &middot; {{ $latest->updated_at->diffForHumans() }}
                </p>
            @else
                <p class="text-xs text-gray-400">{{ __('Not rendered yet.') }}</p>
            @endif
        </div>

        <button type="button" wire:click="render_"
                @disabled(! $this->canRender(app(ShotstackPayloadBuilder::class)))
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
            @if ($this->inProgress())
                {{ __('Rendering…') }}
            @elseif ($latest)
                {{ __('Render again') }}
            @else
                {{ __('Render video') }}
            @endif
        </button>
    </div>

    @if ($error)
        <p class="mt-3 rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
    @endif

    @if ($latest?->status === 'rendering' || $latest?->status === 'queued')
        <div class="mt-4 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
            <svg class="h-4 w-4 animate-spin text-indigo-500" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            {{ __('Shotstack is rendering your video. This can take a minute or two.') }}
        </div>
    @endif

    @if ($latest?->status === 'done' && $latest->output_url)
        <div class="mt-4">
            <video src="{{ $latest->output_url }}" controls playsinline
                   class="mx-auto aspect-[9/16] w-64 rounded-lg bg-black"></video>
            <div class="mt-2 text-center">
                <a href="{{ $latest->output_url }}" target="_blank" rel="noopener"
                   class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Download / open MP4') }}</a>
            </div>
        </div>
    @endif

    @if ($latest?->status === 'failed')
        <p class="mt-3 rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300">
            {{ $latest->error_message ?: __('The render failed. Try again.') }}
        </p>
    @endif
</div>
