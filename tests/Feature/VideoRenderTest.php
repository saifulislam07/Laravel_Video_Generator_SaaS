<?php

use App\Events\ProjectRenderStatusUpdated;
use App\Exceptions\RenderException;
use App\Jobs\CheckRenderStatusJob;
use App\Models\Project;
use App\Models\Scene;
use App\Models\User;
use App\Models\VideoRender;
use App\Services\VideoRenderService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    config()->set('services.shotstack.key', 'test-key');
    config()->set('services.shotstack.env', 'stage');
    config()->set('services.shotstack.webhook_secret', null);
});

function renderableProject(): Project
{
    $project = Project::factory()->create();
    Scene::factory()->for($project)->create([
        'order' => 1,
        'background_image_path' => 'backgrounds/1/a.jpg',
        'duration_seconds' => 5,
    ]);

    return $project;
}

it('submits a render, stores the id and schedules polling', function () {
    Bus::fake([CheckRenderStatusJob::class]);
    Event::fake([ProjectRenderStatusUpdated::class]);
    Http::fake([
        'api.shotstack.io/edit/stage/render' => Http::response([
            'success' => true,
            'response' => ['id' => 'abc-123', 'message' => 'Render Successfully Queued'],
        ], 201),
    ]);

    $project = renderableProject();
    $render = app(VideoRenderService::class)->submit($project);

    expect($render->shotstack_render_id)->toBe('abc-123')
        ->and($render->status)->toBe(VideoRender::STATUS_QUEUED)
        ->and($project->fresh()->status)->toBe(Project::STATUS_RENDERING);

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key')
        && $request->url() === 'https://api.shotstack.io/edit/stage/render');

    Bus::assertDispatched(CheckRenderStatusJob::class);
    Event::assertDispatched(ProjectRenderStatusUpdated::class);
});

it('adds a callback url when a webhook secret is configured', function () {
    config()->set('services.shotstack.webhook_secret', 's3cr3t');
    Bus::fake();
    Http::fake([
        '*' => Http::response(['response' => ['id' => 'abc']], 201),
    ]);

    app(VideoRenderService::class)->submit(renderableProject());

    Http::assertSent(fn ($request) => str_contains($request['callback'] ?? '', 'webhooks/shotstack')
        && str_contains($request['callback'], 's3cr3t'));
});

it('fails clearly when the api key is missing', function () {
    config()->set('services.shotstack.key', null);

    app(VideoRenderService::class)->submit(renderableProject());
})->throws(RenderException::class);

it('surfaces a shotstack api error', function () {
    Http::fake(['*' => Http::response('bad request', 400)]);

    app(VideoRenderService::class)->submit(renderableProject());
})->throws(RenderException::class);

it('marks the render done and completes the project on a done status', function () {
    Event::fake([ProjectRenderStatusUpdated::class]);
    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r1']);

    Http::fake([
        'api.shotstack.io/edit/stage/render/r1' => Http::response([
            'response' => ['status' => 'done', 'url' => 'https://cdn.shotstack.io/out.mp4'],
        ]),
    ]);

    app(VideoRenderService::class)->syncStatus($render);

    expect($render->fresh())
        ->status->toBe(VideoRender::STATUS_DONE)
        ->output_url->toBe('https://cdn.shotstack.io/out.mp4')
        ->and($render->project->fresh()->status)->toBe(Project::STATUS_COMPLETED);

    Event::assertDispatched(ProjectRenderStatusUpdated::class);
});

it('maps a failed status and stores the error', function () {
    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r2']);

    Http::fake([
        '*' => Http::response(['response' => ['status' => 'failed', 'error' => 'asset 404']]),
    ]);

    app(VideoRenderService::class)->syncStatus($render);

    expect($render->fresh())
        ->status->toBe(VideoRender::STATUS_FAILED)
        ->error_message->toBe('asset 404')
        ->and($render->project->fresh()->status)->toBe(Project::STATUS_FAILED);
});

it('re-dispatches the poll job while the render is still going', function () {
    Bus::fake([CheckRenderStatusJob::class]);
    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r3']);
    Http::fake(['*' => Http::response(['response' => ['status' => 'rendering']])]);

    (new CheckRenderStatusJob($render, now()->addMinutes(5)->toISOString()))->handle(app(VideoRenderService::class));

    Bus::assertDispatched(CheckRenderStatusJob::class);
});

it('stops polling once the render is finished', function () {
    Bus::fake([CheckRenderStatusJob::class]);
    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r4']);
    Http::fake(['*' => Http::response(['response' => ['status' => 'done', 'url' => 'https://cdn/x.mp4']])]);

    (new CheckRenderStatusJob($render, now()->addMinutes(5)->toISOString()))->handle(app(VideoRenderService::class));

    Bus::assertNotDispatched(CheckRenderStatusJob::class);
    expect($render->fresh()->status)->toBe(VideoRender::STATUS_DONE);
});

it('times out a render that never finishes', function () {
    Bus::fake([CheckRenderStatusJob::class]);
    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r5']);
    Http::fake(['*' => Http::response(['response' => ['status' => 'rendering']])]);

    (new CheckRenderStatusJob($render, now()->subMinute()->toISOString()))->handle(app(VideoRenderService::class));

    Bus::assertNotDispatched(CheckRenderStatusJob::class);
    expect($render->fresh())
        ->status->toBe(VideoRender::STATUS_FAILED)
        ->error_message->toContain('timed out');
});

describe('shotstack webhook', function () {
    it('updates a render from a valid webhook call', function () {
        config()->set('services.shotstack.webhook_secret', 'hook-secret');
        $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'w1']);

        postJson('/webhooks/shotstack?secret=hook-secret', [
            'type' => 'edit', 'action' => 'render', 'id' => 'w1',
            'status' => 'done', 'url' => 'https://cdn.shotstack.io/w1.mp4',
        ])->assertOk();

        expect($render->fresh())
            ->status->toBe(VideoRender::STATUS_DONE)
            ->output_url->toBe('https://cdn.shotstack.io/w1.mp4');
    });

    it('rejects a webhook with the wrong secret', function () {
        config()->set('services.shotstack.webhook_secret', 'hook-secret');

        postJson('/webhooks/shotstack?secret=nope', ['id' => 'x', 'status' => 'done'])
            ->assertForbidden();
    });

    it('accepts but ignores an unknown render id', function () {
        config()->set('services.shotstack.webhook_secret', null);

        postJson('/webhooks/shotstack', ['id' => 'ghost', 'status' => 'done'])
            ->assertAccepted();
    });
});

describe('render panel', function () {
    it('kicks off a render from the builder panel', function () {
        Bus::fake();
        Http::fake(['*' => Http::response(['response' => ['id' => 'panel-1']], 201)]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Scene::factory()->for($project)->create(['background_image_path' => 'backgrounds/1/a.jpg']);
        actingAs($user);

        Livewire\Volt\Volt::test('projects.render-panel', ['project' => $project])
            ->call('render_')
            ->assertSet('error', null);

        expect($project->videoRenders()->count())->toBe(1);
    });

    it('shows an error when the api key is missing', function () {
        config()->set('services.shotstack.key', null);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        Scene::factory()->for($project)->create(['background_image_path' => 'backgrounds/1/a.jpg']);
        actingAs($user);

        Livewire\Volt\Volt::test('projects.render-panel', ['project' => $project])
            ->call('render_')
            ->assertSetStrict('error', fn ($e) => str_contains((string) $e, 'SHOTSTACK_API_KEY'));
    });
});
