<?php

namespace App\Http\Controllers;

use App\Models\CreditOrder;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;

class BillingCallbackController extends Controller
{
    /**
     * Return/IPN endpoint the payment gateways redirect (or POST) the customer
     * back to. Credits are granted here server-side, so it works even if the
     * browser session cookie is dropped on a cross-site POST.
     */
    public function __invoke(Request $request, string $gateway, BillingService $billing)
    {
        abort_unless(array_key_exists($gateway, config('billing.gateways')), 404);

        $order = CreditOrder::where('gateway', $gateway)
            ->findOrFail($request->integer('order'));

        $order = $billing->completePurchase($order, $request->all());

        if ($request->input('result') === 'ipn') {
            return response()->json(['status' => $order->status]);
        }

        return $order->isPaid()
            ? redirect()->route('dashboard')->with('status', __(':n credits added to your account.', ['n' => $order->credits]))
            : redirect()->route('billing.pricing')->with('error', __('Payment was not completed.'));
    }
}
