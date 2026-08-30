<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        return [
            'transactions' => Auth::user()->creditTransactions()->paginate(15),
            'orders' => Auth::user()->creditOrders()->take(10)->get(),
            'credits' => Auth::user()->credits,
        ];
    }
}; ?>

<div class="py-10">
  <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-8">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Billing') }}</h2>
        <a href="{{ route('billing.pricing') }}" wire:navigate
           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Buy credits') }}</a>
    </div>

    <div class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Current balance') }}</p>
        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $credits }} <span class="text-base font-normal text-gray-400">{{ __('credits') }}</span></p>
    </div>

    <section>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Orders') }}</h3>
        @if ($orders->isEmpty())
            <p class="mt-2 text-sm text-gray-400">{{ __('No orders yet.') }}</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($orders as $order)
                            <tr wire:key="order-{{ $order->id }}">
                                <td class="px-4 py-2 capitalize">{{ $order->package_key }}</td>
                                <td class="px-4 py-2">{{ $order->credits }} {{ __('credits') }}</td>
                                <td class="px-4 py-2">{{ number_format($order->amount) }} {{ $order->currency }}</td>
                                <td class="px-4 py-2 uppercase text-xs text-gray-400">{{ $order->gateway }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                        'bg-green-100 text-green-700' => $order->status === 'paid',
                                        'bg-amber-100 text-amber-700' => $order->status === 'pending',
                                        'bg-red-100 text-red-700' => in_array($order->status, ['failed', 'cancelled']),
                                    ])>{{ $order->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-400">{{ $order->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Credit history') }}</h3>
        @if ($transactions->isEmpty())
            <p class="mt-2 text-sm text-gray-400">{{ __('Nothing yet.') }}</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($transactions as $tx)
                            <tr wire:key="tx-{{ $tx->id }}">
                                <td class="px-4 py-2">{{ str($tx->reason)->headline() }}</td>
                                <td class="px-4 py-2 font-medium {{ $tx->isCredit() ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $tx->amount > 0 ? '+' : '' }}{{ $tx->amount }}
                                </td>
                                <td class="px-4 py-2 text-gray-400">{{ __('balance') }} {{ $tx->balance_after }}</td>
                                <td class="px-4 py-2 text-xs text-gray-400">{{ $tx->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $transactions->links() }}</div>
        @endif
    </section>

  </div>
</div>
