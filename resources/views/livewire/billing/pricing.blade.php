<?php

use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $gateway = '';

    public function mount(): void
    {
        $this->gateway = (string) config('billing.default_gateway');
    }

    public function buy(string $package, BillingService $billing): void
    {
        $gateways = array_keys(config('billing.gateways'));
        abort_unless(in_array($this->gateway, $gateways, true), 422);

        $result = $billing->startPurchase(Auth::user(), $package, $this->gateway);

        $this->redirect($result['checkoutUrl']);
    }

    public function with(BillingService $billing): array
    {
        return [
            'packages' => $billing->packages(),
            'gateways' => config('billing.gateways'),
            'currency' => config('billing.currency', 'BDT'),
            'credits' => Auth::user()->credits,
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-8">

    <div class="text-center">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ __('Buy render credits') }}</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ __('Each rendered video costs 1 credit. You currently have') }}
            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $credits }}</span>.
        </p>
    </div>

    <div class="flex justify-center gap-6 text-sm">
        @foreach ($gateways as $key => $config)
            <label class="inline-flex items-center gap-2">
                <input type="radio" wire:model="gateway" value="{{ $key }}" class="text-indigo-600 focus:ring-indigo-500" />
                {{ $config['label'] }}
            </label>
        @endforeach
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($packages as $key => $package)
            <div @class([
                'relative rounded-2xl border bg-white dark:bg-gray-800 p-6 text-center shadow-sm',
                'border-indigo-500 ring-1 ring-indigo-500' => $package['popular'] ?? false,
                'border-gray-200 dark:border-gray-700' => ! ($package['popular'] ?? false),
            ])>
                @if ($package['popular'] ?? false)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">
                        {{ __('Most popular') }}
                    </span>
                @endif
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $package['name'] }}</h3>
                <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-gray-100">
                    {{ number_format($package['amount']) }}<span class="text-base font-normal text-gray-400"> {{ $currency }}</span>
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $package['credits'] }} {{ __('videos') }}</p>
                <button type="button" wire:click="buy('{{ $key }}')"
                        class="mt-6 w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    {{ __('Buy') }}
                </button>
            </div>
        @endforeach
    </div>

    <p class="text-center text-xs text-gray-400">
        {{ __('Payment gateways (bKash / SSLCommerz) are not connected yet — checkout runs in mock mode.') }}
    </p>

  </div>
</div>
