<?php

namespace App\Jobs;

use App\Models\VideoRender;
use App\Services\VideoRenderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class CheckRenderStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    private const POLL_INTERVAL_SECONDS = 30;
    private const MAX_WAIT_MINUTES = 10;

    public function __construct(
        public VideoRender $render,
        public ?string $deadline = null,
    ) {
        $this->deadline ??= now()->addMinutes(self::MAX_WAIT_MINUTES)->toISOString();
    }

    public function handle(VideoRenderService $service): void
    {
        $this->render->refresh();

        if ($this->render->isFinished()) {
            return;
        }

        $service->syncStatus($this->render);

        if ($this->render->fresh()->isFinished()) {
            return;
        }

        if (Carbon::parse($this->deadline)->isPast()) {
            $service->markTimedOut($this->render);

            return;
        }

        self::dispatch($this->render, $this->deadline)
            ->delay(now()->addSeconds(self::POLL_INTERVAL_SECONDS));
    }
}
