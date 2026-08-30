<?php

use App\Jobs\DownloadRenderJob;
use App\Models\VideoRender;
use App\Services\VideoRenderService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('services.shotstack.key', 'test-key');
    config()->set('services.shotstack.env', 'stage');
    config()->set('video.archive.disk', 'public');
});

it('does not archive when the feature is off', function () {
    config()->set('video.archive.enabled', false);
    Bus::fake();

    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r']);
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'done', 'url' => 'https://cdn.shotstack.io/x.mp4']])]);

    app(VideoRenderService::class)->syncStatus($render);

    Bus::assertNotDispatched(DownloadRenderJob::class);
});

it('dispatches the archive job when a render completes and the feature is on', function () {
    config()->set('video.archive.enabled', true);
    Bus::fake();

    $render = VideoRender::factory()->create(['status' => VideoRender::STATUS_RENDERING, 'shotstack_render_id' => 'r']);
    Http::fake(['*/render/r' => Http::response(['response' => ['status' => 'done', 'url' => 'https://cdn.shotstack.io/x.mp4']])]);

    app(VideoRenderService::class)->syncStatus($render);

    Bus::assertDispatched(DownloadRenderJob::class);
});

it('copies the video to the archive disk and repoints the render', function () {
    Storage::fake('public');
    config()->set('video.archive.enabled', true);

    $render = VideoRender::factory()->done()->create(['output_url' => 'https://cdn.shotstack.io/final.mp4']);
    Http::fake(['https://cdn.shotstack.io/final.mp4' => Http::response('BINARY-MP4-BYTES', 200)]);

    (new DownloadRenderJob($render))->handle();

    $render->refresh();
    $expectedPath = "renders/{$render->project_id}/{$render->id}.mp4";

    Storage::disk('public')->assertExists($expectedPath);
    expect($render->source_url)->toBe('https://cdn.shotstack.io/final.mp4')
        ->and($render->output_url)->toBe(Storage::disk('public')->url($expectedPath))
        ->and($render->archived_at)->not->toBeNull();
});

it('is a no-op if already archived', function () {
    Storage::fake('public');
    $render = VideoRender::factory()->done()->create(['archived_at' => now(), 'output_url' => 'https://mine/x.mp4']);
    Http::fake();

    (new DownloadRenderJob($render))->handle();

    Http::assertNothingSent();
});

it('fails the job (keeping the CDN url) when the download errors', function () {
    Storage::fake('public');
    $render = VideoRender::factory()->done()->create(['output_url' => 'https://cdn.shotstack.io/gone.mp4']);
    Http::fake(['*' => Http::response('', 404)]);

    $job = new DownloadRenderJob($render);
    $job->handle();

    expect($render->fresh())
        ->archived_at->toBeNull()
        ->output_url->toBe('https://cdn.shotstack.io/gone.mp4');
});
