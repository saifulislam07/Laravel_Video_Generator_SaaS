<?php

use App\Models\Project;
use App\Models\User;
use App\Models\VideoRender;
use App\Notifications\RenderCompleted;
use App\Notifications\RenderFailed;
use App\Services\VideoRenderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config()->set('services.shotstack.key', 'test-key');
    config()->set('services.shotstack.env', 'stage');
    config()->set('video.archive.enabled', false);
    Notification::fake();
});

function renderFor(User $user, string $status = VideoRender::STATUS_RENDERING): VideoRender
{
    return VideoRender::factory()
        ->for(Project::factory()->for($user))
        ->create(['status' => $status, 'shotstack_render_id' => 'r']);
}

it('emails the owner when a render completes', function () {
    $user = User::factory()->create();
    $render = renderFor($user);
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'done', 'url' => 'https://cdn/x.mp4']])]);

    app(VideoRenderService::class)->syncStatus($render);

    Notification::assertSentTo($user, RenderCompleted::class);
});

it('emails the owner and refunds the credit when a render fails', function () {
    $user = User::factory()->credits(4)->create();
    $render = renderFor($user);
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'failed', 'error' => 'bad asset']])]);

    app(VideoRenderService::class)->syncStatus($render);

    Notification::assertSentTo($user, RenderFailed::class);
    expect($user->fresh()->credits)->toBe(5)
        ->and($user->creditTransactions()->where('reason', 'render_refund')->count())->toBe(1);
});

it('does not notify while a render is still in progress', function () {
    $render = renderFor(User::factory()->create());
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'rendering']])]);

    app(VideoRenderService::class)->syncStatus($render);

    Notification::assertNothingSent();
});

it('does not double-refund on a repeated failed webhook', function () {
    $user = User::factory()->credits(4)->create();
    $render = renderFor($user);
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'failed']])]);

    $service = app(VideoRenderService::class);
    $service->syncStatus($render);
    $service->applyWebhook($render->fresh(), 'failed');

    expect($user->fresh()->credits)->toBe(5)
        ->and($user->creditTransactions()->where('reason', 'render_refund')->count())->toBe(1);
});

it('honours the notify_by_email toggle', function () {
    config()->set('video.notify_by_email', false);
    $render = renderFor(User::factory()->create());
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'done', 'url' => 'https://cdn/x.mp4']])]);

    app(VideoRenderService::class)->syncStatus($render);

    Notification::assertNothingSent();
});
