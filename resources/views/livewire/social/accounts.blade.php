<?php

use App\Services\Social\SocialPublisher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function disconnect(int $id): void
    {
        Auth::user()->socialAccounts()->findOrFail($id)->delete();
    }

    public function with(SocialPublisher $publisher): array
    {
        return [
            'configured' => $publisher->isConfigured(),
            'accounts' => Auth::user()->socialAccounts()->latest()->get(),
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Connected accounts') }}</h2>

    @if (session('status')) <p class="rounded-md bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-700 dark:text-green-300">{{ session('status') }}</p> @endif
    @if (session('error')) <p class="rounded-md bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p> @endif

    <div class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow-sm">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ __('Link a Facebook Page (and its Instagram professional account) to publish finished videos with one click.') }}
        </p>

        @if ($configured)
            <a href="{{ route('social.connect') }}"
               class="mt-4 inline-flex items-center rounded-md bg-[#1877F2] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                {{ __('Connect Facebook') }}
            </a>
        @else
            <p class="mt-4 text-sm text-amber-600">{{ __('Meta app credentials are not configured (FACEBOOK_APP_ID / FACEBOOK_APP_SECRET).') }}</p>
        @endif
    </div>

    <div class="space-y-2">
        @forelse ($accounts as $account)
            <div wire:key="sa-{{ $account->id }}" class="flex items-center justify-between rounded-lg bg-white dark:bg-gray-800 p-4 shadow-sm">
                <div>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $account->label() }}</p>
                    <p class="text-xs text-gray-400">
                        {{ ucfirst(str_replace('_', ' ', $account->provider)) }}
                        @if ($account->token_expires_at) &middot; token expires {{ $account->token_expires_at->diffForHumans() }} @endif
                    </p>
                </div>
                <button type="button" wire:click="disconnect({{ $account->id }})"
                        wire:confirm="{{ __('Disconnect this account?') }}"
                        class="text-sm text-red-600 hover:text-red-500">{{ __('Disconnect') }}</button>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No accounts linked yet.') }}</p>
        @endforelse
    </div>

  </div>
</div>
