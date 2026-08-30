<?php

namespace App\Services;

use App\Events\ProjectRenderStatusUpdated;
use App\Exceptions\RenderException;
use App\Jobs\CheckRenderStatusJob;
use App\Models\Project;
use App\Models\VideoRender;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VideoRenderService
{
    /** Shotstack render states grouped into our four VideoRender statuses. */
    private const STATUS_MAP = [
        'queued' => VideoRender::STATUS_QUEUED,
        'fetching' => VideoRender::STATUS_QUEUED,
        'rendering' => VideoRender::STATUS_RENDERING,
        'saving' => VideoRender::STATUS_RENDERING,
        'done' => VideoRender::STATUS_DONE,
        'failed' => VideoRender::STATUS_FAILED,
    ];

    public function __construct(
        private readonly ShotstackPayloadBuilder $payloadBuilder,
        private readonly CreditService $credits,
    ) {}

    /**
     * Build the payload, submit it to Shotstack and start tracking the render.
     *
     * @throws \App\Exceptions\InsufficientCreditsException
     * @throws RenderException
     */
    public function submit(Project $project): VideoRender
    {
        $cost = (int) config('billing.cost.video_render', 1);
        $user = $project->user;

        if (! $user->hasCredits($cost)) {
            throw new \App\Exceptions\InsufficientCreditsException();
        }

        $payload = $this->payloadBuilder->build($project);

        if ($callback = $this->callbackUrl()) {
            $payload['callback'] = $callback;
        }

        $response = $this->client()->post("{$this->baseUrl()}/render", $payload);

        if ($response->failed()) {
            throw RenderException::apiError($response->status(), $response->body());
        }

        $renderId = $response->json('response.id');

        $render = $project->videoRenders()->create([
            'shotstack_render_id' => $renderId,
            'status' => VideoRender::STATUS_QUEUED,
        ]);

        $this->credits->charge($user, $cost, 'video_render', [
            'project_id' => $project->id,
            'video_render_id' => $render->id,
        ]);

        $project->update(['status' => Project::STATUS_RENDERING]);

        ProjectRenderStatusUpdated::dispatch($render->fresh('project'));

        CheckRenderStatusJob::dispatch($render)->delay(now()->addSeconds(30));

        return $render;
    }

    /**
     * Poll Shotstack for the current state of a render and persist any change.
     */
    public function syncStatus(VideoRender $render): VideoRender
    {
        if ($render->isFinished() || ! $render->shotstack_render_id) {
            return $render;
        }

        $response = $this->client()->get("{$this->baseUrl()}/render/{$render->shotstack_render_id}");

        if ($response->failed()) {
            throw RenderException::apiError($response->status(), $response->body());
        }

        $data = $response->json('response', []);
        $status = self::STATUS_MAP[$data['status'] ?? ''] ?? $render->status;

        $this->applyStatus($render, $status, [
            'output_url' => $data['url'] ?? null,
            'error_message' => $data['error'] ?? ($status === VideoRender::STATUS_FAILED ? 'Render failed at Shotstack.' : null),
        ]);

        return $render;
    }

    /**
     * Apply a status coming from a webhook payload.
     */
    public function applyWebhook(VideoRender $render, string $shotstackStatus, ?string $url = null, ?string $error = null): VideoRender
    {
        $status = self::STATUS_MAP[$shotstackStatus] ?? $render->status;

        $this->applyStatus($render, $status, [
            'output_url' => $url,
            'error_message' => $error ?? ($status === VideoRender::STATUS_FAILED ? 'Render failed at Shotstack.' : null),
        ]);

        return $render;
    }

    public function markTimedOut(VideoRender $render): void
    {
        if ($render->isFinished()) {
            return;
        }

        $this->applyStatus($render, VideoRender::STATUS_FAILED, [
            'error_message' => 'Render timed out — no result from Shotstack within the allowed window.',
        ]);
    }

    /**
     * @param  array{output_url?:?string,error_message?:?string}  $attributes
     */
    private function applyStatus(VideoRender $render, string $status, array $attributes = []): void
    {
        $original = $render->status;

        $render->fill(['status' => $status]);

        if (! empty($attributes['output_url'])) {
            $render->output_url = $attributes['output_url'];
        }
        if (! empty($attributes['error_message'])) {
            $render->error_message = $attributes['error_message'];
        }

        $dirty = $render->isDirty();
        $render->save();

        $transitioned = $status !== $original;

        if ($status === VideoRender::STATUS_DONE) {
            $render->project->update(['status' => Project::STATUS_COMPLETED]);

            if (config('video.archive.enabled') && ! $render->archived_at) {
                \App\Jobs\DownloadRenderJob::dispatch($render);
            }

            if ($transitioned) {
                $this->notify($render, new \App\Notifications\RenderCompleted($render));
            }
        } elseif ($status === VideoRender::STATUS_FAILED) {
            $render->project->update(['status' => Project::STATUS_FAILED]);

            if ($transitioned) {
                $this->refundCredit($render);
                $this->notify($render, new \App\Notifications\RenderFailed($render));
            }
        }

        if ($dirty || $transitioned) {
            ProjectRenderStatusUpdated::dispatch($render->fresh('project'));
        }
    }

    private function notify(VideoRender $render, \Illuminate\Notifications\Notification $notification): void
    {
        if (config('video.notify_by_email', true)) {
            $render->project->user->notify($notification);
        }
    }

    /** Return the credit spent on a render that ultimately failed. */
    private function refundCredit(VideoRender $render): void
    {
        $cost = (int) config('billing.cost.video_render', 1);

        $alreadyRefunded = $render->project->user->creditTransactions()
            ->where('reason', 'render_refund')
            ->where('meta->video_render_id', $render->id)
            ->exists();

        if ($cost > 0 && ! $alreadyRefunded) {
            $this->credits->grant($render->project->user, $cost, 'render_refund', [
                'project_id' => $render->project_id,
                'video_render_id' => $render->id,
            ]);
        }
    }

    public function baseUrl(): string
    {
        $env = config('services.shotstack.env', 'stage');

        // Shotstack Edit API: stage -> /edit/stage, production -> /edit/v1
        $segment = $env === 'production' ? 'v1' : 'stage';

        return "https://api.shotstack.io/edit/{$segment}";
    }

    private function client(): PendingRequest
    {
        $key = config('services.shotstack.key');

        if (blank($key)) {
            throw RenderException::missingApiKey();
        }

        return Http::withHeaders(['x-api-key' => $key])
            ->acceptJson()
            ->timeout(30);
    }

    private function callbackUrl(): ?string
    {
        if (blank(config('services.shotstack.webhook_secret'))) {
            return null;
        }

        return route('webhooks.shotstack', ['secret' => config('services.shotstack.webhook_secret')]);
    }
}
