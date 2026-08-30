<?php

use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditOrder;
use App\Models\CreditTransaction;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\CreditService;
use App\Services\VideoRenderService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

it('gives new users 5 credits by default', function () {
    expect(User::factory()->create()->credits)->toBe(5);
});

describe('CreditService', function () {
    it('charges credits and logs a negative transaction', function () {
        $user = User::factory()->credits(3)->create();

        $tx = app(CreditService::class)->charge($user, 1, 'video_render', ['x' => 1]);

        expect($user->fresh()->credits)->toBe(2)
            ->and($tx->amount)->toBe(-1)
            ->and($tx->balance_after)->toBe(2)
            ->and($tx->reason)->toBe('video_render')
            ->and($tx->meta)->toBe(['x' => 1]);
    });

    it('refuses to overspend', function () {
        $user = User::factory()->broke()->create();

        expect(fn () => app(CreditService::class)->charge($user, 1, 'video_render'))
            ->toThrow(InsufficientCreditsException::class);

        expect($user->fresh()->credits)->toBe(0)
            ->and(CreditTransaction::count())->toBe(0);
    });

    it('grants credits and logs a positive transaction', function () {
        $user = User::factory()->credits(2)->create();

        app(CreditService::class)->grant($user, 10, 'package_purchase');

        expect($user->fresh()->credits)->toBe(12)
            ->and(CreditTransaction::latest('id')->first()->amount)->toBe(10);
    });
});

describe('render credit gate', function () {
    beforeEach(fn () => config()->set('services.shotstack.key', 'test-key'));

    function project(int $credits): Project
    {
        $user = User::factory()->credits($credits)->create();
        $project = Project::factory()->for($user)->create();
        Scene::factory()->for($project)->create(['background_image_path' => 'backgrounds/1/a.jpg']);

        return $project;
    }

    it('deducts a credit and logs it when a render is submitted', function () {
        Bus::fake();
        Http::fake(['*' => Http::response(['response' => ['id' => 'r1']], 201)]);

        $project = project(5);
        app(VideoRenderService::class)->submit($project);

        expect($project->user->fresh()->credits)->toBe(4);

        $tx = CreditTransaction::sole();
        expect($tx->amount)->toBe(-1)
            ->and($tx->reason)->toBe('video_render')
            ->and($tx->meta['project_id'])->toBe($project->id);
    });

    it('blocks the render when the user is out of credits', function () {
        Http::fake();

        expect(fn () => app(VideoRenderService::class)->submit(project(0)))
            ->toThrow(InsufficientCreditsException::class);

        Http::assertNothingSent();
    });

    it('shows an Upgrade link on the render panel when out of credits', function () {
        $project = project(0);
        actingAs($project->user);

        Volt::test('projects.render-panel', ['project' => $project])
            ->call('render_')
            ->assertSet('outOfCredits', true)
            ->assertSee('Upgrade');
    });
});

describe('purchasing credits', function () {
    it('starts a purchase and points to the checkout url', function () {
        $user = User::factory()->create();

        $result = app(BillingService::class)->startPurchase($user, 'creator', 'bkash');

        expect($result['order'])
            ->credits->toBe(50)
            ->amount->toBe(800)
            ->status->toBe(CreditOrder::STATUS_PENDING)
            ->gateway->toBe('bkash')
            ->and($result['order']->gateway_ref)->toStartWith('BKASH-')
            ->and($result['checkoutUrl'])->toContain('/billing/checkout/');
    });

    it('tops up credits when a payment succeeds (idempotently)', function () {
        $user = User::factory()->credits(1)->create();
        $order = CreditOrder::factory()->for($user)->create(['credits' => 50, 'gateway' => 'bkash']);

        app(BillingService::class)->completePurchase($order, ['status' => 'success']);
        app(BillingService::class)->completePurchase($order->fresh(), ['status' => 'success']); // again

        expect($user->fresh()->credits)->toBe(51)
            ->and($order->fresh()->status)->toBe(CreditOrder::STATUS_PAID)
            ->and(CreditTransaction::where('reason', 'package_purchase')->count())->toBe(1);
    });

    it('marks the order failed when the payment fails', function () {
        $user = User::factory()->credits(1)->create();
        $order = CreditOrder::factory()->for($user)->create();

        app(BillingService::class)->completePurchase($order, ['status' => 'failed']);

        expect($order->fresh()->status)->toBe(CreditOrder::STATUS_FAILED)
            ->and($user->fresh()->credits)->toBe(1);
    });

    it('drives the flow through the Volt pages', function () {
        $user = User::factory()->credits(0)->create();
        actingAs($user);

        Volt::test('billing.pricing')
            ->set('gateway', 'sslcommerz')
            ->call('buy', 'studio')
            ->assertRedirect();

        $order = $user->creditOrders()->sole();
        expect($order->gateway)->toBe('sslcommerz')->and($order->credits)->toBe(100);

        Volt::test('billing.mock-gateway', ['order' => $order])
            ->call('pay')
            ->assertRedirect(route('dashboard'));

        expect($user->fresh()->credits)->toBe(100);
    });

    it('will not let a user pay for someone else\'s order', function () {
        $order = CreditOrder::factory()->create();
        actingAs(User::factory()->create());

        Volt::test('billing.mock-gateway', ['order' => $order])->assertForbidden();
    });
});
