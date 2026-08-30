<?php

use App\Models\CreditOrder;
use App\Models\Project;
use App\Models\User;
use App\Models\VideoRender;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        return [
            'stats' => [
                __('Users') => User::count(),
                __('Projects') => Project::count(),
                __('Total renders') => VideoRender::count(),
                __('Renders today') => VideoRender::whereDate('created_at', today())->count(),
                __('Completed renders') => VideoRender::where('status', VideoRender::STATUS_DONE)->count(),
                __('Failed renders') => VideoRender::where('status', VideoRender::STATUS_FAILED)->count(),
                __('Paid orders') => CreditOrder::where('status', CreditOrder::STATUS_PAID)->count(),
                __('Revenue (BDT)') => number_format(CreditOrder::where('status', CreditOrder::STATUS_PAID)->sum('amount')),
            ],
            'recentRenders' => VideoRender::with('project.user')->latest()->take(8)->get(),
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Admin') }}</h2>
    <x-admin-nav />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="rounded-lg bg-white dark:bg-gray-800 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <h3 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Latest renders') }}</h3>
    <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($recentRenders as $render)
                    <tr wire:key="ar-{{ $render->id }}">
                        <td class="px-4 py-2">{{ $render->project?->title ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $render->project?->user?->email }}</td>
                        <td class="px-4 py-2 capitalize">{{ $render->status }}</td>
                        <td class="px-4 py-2 text-xs text-gray-400">{{ $render->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
  </div>
</div>
