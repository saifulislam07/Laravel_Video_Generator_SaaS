<?php

namespace App\Jobs;

use App\Events\ProjectRenderStatusUpdated;
use App\Models\VideoRender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Copies a finished render off the Shotstack CDN onto a durable disk
 * (config: video.archive.disk) and repoints the render at the archived copy.
 * The original CDN url is kept in `source_url`.
 */
class DownloadRenderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(public VideoRender $render) {}

    public function handle(): void
    {
        $this->render->refresh();

        if ($this->render->archived_at || blank($this->render->output_url)) {
            return;
        }

        $disk = Storage::disk(config('video.archive.disk'));
        $path = trim(config('video.archive.directory'), '/')
            ."/{$this->render->project_id}/{$this->render->id}.mp4";

        $response = Http::timeout(120)->get($this->render->output_url);

        if ($response->failed()) {
            Log::warning('Render archive download failed', [
                'render' => $this->render->id, 'status' => $response->status(),
            ]);
            $this->fail("Could not download render {$this->render->id}.");

            return;
        }

        $disk->put($path, $response->body(), 'public');

        $this->render->forceFill([
            'source_url' => $this->render->output_url,
            'output_url' => $disk->url($path),
            'archived_at' => now(),
        ])->save();

        ProjectRenderStatusUpdated::dispatch($this->render->fresh('project'));
    }
}
