<?php

use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\RenderException;
use App\Models\Project;
use App\Models\VideoRender;
use App\Services\Social\SocialPublisher;
use App\Services\VideoRenderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $flash = null;
    public ?string $error = null;

    public function retry(int $projectId, VideoRenderService $service): void
    {
        $this->reset('flash', 'error');

        $project = Auth::user()->projects()->findOrFail($projectId);
        $this->authorize('update', $project);

        try {
            $service->submit($project);
            $this->flash = __('Render restarted for “:title”.', ['title' => $project->title]);
        } catch (InsufficientCreditsException|RenderException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function publish(int $renderId, int $accountId, SocialPublisher $publisher): void
    {
        $this->reset('flash', 'error');

        $render = VideoRender::whereHas('project', fn ($q) => $q->where('user_id', Auth::id()))
            ->findOrFail($renderId);
        $account = Auth::user()->socialAccounts()->findOrFail($accountId);

        try {
            $publisher->publish($render, $account);
            $this->flash = __('Publishing to :name — it will appear once processing finishes.', ['name' => $account->label()]);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** @return \Illuminate\Support\Collection<int, Project> */
    public function projects()
    {
        return Auth::user()->projects()
            ->withCount('scenes')
            ->with('latestRender.publications.account')
            ->latest()
            ->get();
    }

    public function with(): array
    {
        $projects = $this->projects();

        return [
            'projects' => $projects,
            'socialAccounts' => Auth::user()->socialAccounts()->get(),
            'renderingIds' => $projects->filter(
                fn (Project $p) => in_array($p->latestRender?->status, [VideoRender::STATUS_QUEUED, VideoRender::STATUS_RENDERING], true)
            )->pluck('id')->values(),
            'projectCount' => $projects->count(),
            'renderedCount' => $projects->filter(fn (Project $p) => $p->latestRender?->status === VideoRender::STATUS_DONE)->count(),
        ];
    }
}; ?>

<div class="py-10"
     @if ($renderingIds->isNotEmpty()) wire:poll.6s @endif
     x-data
     x-init="
        @foreach ($projects as $p)
            window.Echo && window.Echo.private('projects.{{ $p->id }}').listen('.render.status', () => $wire.$refresh());
        @endforeach
     ">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-6">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Dashboard') }}</h2>
        <a href="{{ route('projects.index') }}" wire:navigate
           class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
            {{ __('New project') }}
        </a>
    </div>

    @if ($flash)
        <p class="rounded-md bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-700 dark:text-green-300">{{ $flash }}</p>
    @endif
    @if ($error)
        <p class="rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
    @endif

    <div class="grid gap-6 sm:grid-cols-3">
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Projects') }}</p>
            <p class="mt-1 text-2xl font-bold">{{ $projectCount }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Videos rendered') }}</p>
            <p class="mt-1 text-2xl font-bold">{{ $renderedCount }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Credits left') }}</p>
            <p class="mt-1 text-2xl font-bold">{{ auth()->user()->credits }}</p>
            <a href="{{ route('billing.pricing') }}" wire:navigate class="text-xs font-medium text-indigo-600 hover:text-indigo-500">{{ __('Buy more') }}</a>
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($projects as $project)
            @php($render = $project->latestRender)
            <div wire:key="dash-project-{{ $project->id }}"
                 class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('projects.builder', $project) }}" wire:navigate
                           class="font-medium text-indigo-600 hover:text-indigo-500">{{ $project->title }}</a>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ trans_choice(':count scene|:count scenes', $project->scenes_count, ['count' => $project->scenes_count]) }}
                            &middot; {{ $project->created_at->diffForHumans() }}
                        </p>
                    </div>

                    @php($status = $render?->status ?? $project->status)
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-medium capitalize',
                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => in_array($status, ['draft']),
                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => in_array($status, ['queued', 'rendering']),
                        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' => in_array($status, ['done', 'completed']),
                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' => in_array($status, ['failed']),
                    ])>{{ $status }}</span>
                </div>

                {{-- rendering --}}
                @if (in_array($render?->status, ['queued', 'rendering']))
                    <div class="mt-4 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="h-4 w-4 animate-spin text-indigo-500" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        {{ __('Rendering… updates live.') }}
                    </div>
                @endif

                {{-- completed --}}
                @if ($render?->status === 'done' && $render->output_url)
                    <div class="mt-4 flex flex-wrap items-start gap-4">
                        <video src="{{ $render->output_url }}" controls playsinline
                               class="aspect-[9/16] w-44 rounded-lg bg-black"></video>
                        <div class="flex flex-col gap-2 text-sm">
                            <a href="{{ $render->output_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 font-semibold text-white hover:bg-indigo-500">
                                {{ __('Download MP4') }}
                            </a>
                            <button type="button" wire:click="retry({{ $project->id }})"
                                    class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{{ __('Render again') }}</button>
                        </div>

                        @if ($socialAccounts->isNotEmpty())
                            <div class="w-full">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Publish to') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($socialAccounts as $account)
                                        @php($pub = $render->publications->firstWhere('social_account_id', $account->id))
                                        <button type="button"
                                                wire:click="publish({{ $render->id }}, {{ $account->id }})"
                                                @disabled($pub && in_array($pub->status, ['pending', 'published']))
                                                class="rounded-md border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-60">
                                            {{ $account->label() }}
                                            @if ($pub) &middot; {{ $pub->status }} @endif
                                        </button>
                                    @endforeach
                                </div>
                                <a href="{{ route('social.index') }}" wire:navigate class="mt-1 inline-block text-xs text-indigo-600 hover:text-indigo-500">{{ __('Manage accounts') }}</a>
                            </div>
                        @else
                            <a href="{{ route('social.index') }}" wire:navigate class="w-full text-xs text-indigo-600 hover:text-indigo-500">{{ __('Connect Facebook / Instagram to publish →') }}</a>
                        @endif
                    </div>
                @endif

                {{-- failed --}}
                @if ($render?->status === 'failed')
                    <div class="mt-4">
                        <p class="rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300">
                            {{ $render->error_message ?: __('The render failed.') }}
                        </p>
                        <button type="button" wire:click="retry({{ $project->id }})"
                                class="mt-2 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            {{ __('Try again') }}
                        </button>
                    </div>
                @endif

                {{-- draft / never rendered --}}
                @if (! $render)
                    <div class="mt-4">
                        <a href="{{ route('projects.builder', $project) }}" wire:navigate
                           class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Open builder to render →') }}</a>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No projects yet.') }}</p>
                <a href="{{ route('projects.index') }}" wire:navigate
                   class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ __('Create your first project') }}</a>
            </div>
        @endforelse
    </div>

    <livewire:queue-health />

  </div>
</div>
