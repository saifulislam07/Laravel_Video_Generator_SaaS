<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public int $pending = 0;
    public int $failed = 0;
    public ?string $oldestPendingAge = null;

    public function mount(): void
    {
        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $this->pending = DB::table('jobs')->count();
        $this->failed = DB::table('failed_jobs')->count();

        $oldest = DB::table('jobs')->min('created_at');
        $this->oldestPendingAge = $oldest
            ? Carbon::createFromTimestamp($oldest)->diffForHumans(short: true)
            : null;
    }
}; ?>

<div wire:poll.10s="refreshStats"
     class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
    <div class="flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ __('Queue health') }}</h3>
        <span class="text-xs text-gray-400">{{ __('database driver') }}</span>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-4">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 p-4">
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Pending jobs') }}</dt>
            <dd class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $pending }}</dd>
            @if ($oldestPendingAge)
                <dd class="text-xs text-gray-400">{{ __('oldest') }}: {{ $oldestPendingAge }}</dd>
            @endif
        </div>
        <div class="rounded-lg p-4 {{ $failed > 0 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-700/40' }}">
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Failed jobs') }}</dt>
            <dd class="mt-1 text-2xl font-bold {{ $failed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $failed }}</dd>
        </div>
    </dl>

    <p class="mt-3 text-xs text-gray-400">
        {{ __('Run the worker with') }} <code class="rounded bg-gray-100 dark:bg-gray-700 px-1">php artisan queue:work</code>.
        {{ __('Inspect failures with') }} <code class="rounded bg-gray-100 dark:bg-gray-700 px-1">php artisan queue:failed</code>.
    </p>
</div>
