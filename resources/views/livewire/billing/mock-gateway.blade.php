<?php

use App\Models\CreditOrder;
use App\Services\Billing\BillingService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public CreditOrder $order;

    public function mount(CreditOrder $order): void
    {
        abort_unless($order->user_id === auth()->id(), 403);

        if ($order->isPaid()) {
            $this->redirectRoute('billing.history', navigate: true);
        }

        $this->order = $order;
    }

    public function pay(BillingService $billing): void
    {
        $billing->completePurchase($this->order->fresh(), ['status' => 'success']);

        session()->flash('status', __(':n credits added to your account.', ['n' => $this->order->credits]));

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function failPayment(BillingService $billing): void
    {
        $billing->completePurchase($this->order->fresh(), ['status' => 'failed']);

        $this->redirectRoute('billing.pricing', navigate: true);
    }
}; ?>

<div class="py-16">
  <div class="mx-auto max-w-md px-4">
    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 shadow-sm">

        <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 p-3 text-center text-xs text-amber-700 dark:text-amber-300">
            {{ __('Mock checkout — no real :gw payment is taken.', ['gw' => config("billing.gateways.{$order->gateway}.label")]) }}
        </div>

        <h2 class="mt-6 text-center text-lg font-semibold text-gray-800 dark:text-gray-200">
            {{ __('Confirm your purchase') }}
        </h2>

        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">{{ __('Package') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200 capitalize">{{ $order->package_key }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">{{ __('Credits') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $order->credits }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">{{ __('Amount') }}</dt>
                <dd class="font-medium text-gray-800 dark:text-gray-200">{{ number_format($order->amount) }} {{ $order->currency }}</dd>
            </div>
        </dl>

        <button type="button" wire:click="pay"
                class="mt-6 w-full rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500">
            {{ __('Simulate successful payment') }}
        </button>
        <button type="button" wire:click="failPayment"
                class="mt-2 w-full rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
            {{ __('Simulate failure') }}
        </button>
    </div>
  </div>
</div>
