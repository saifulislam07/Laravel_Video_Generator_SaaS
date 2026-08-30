<?php

namespace App\Jobs;

use App\Models\SocialPublication;
use App\Services\Social\SocialPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishRenderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public SocialPublication $publication,
        public string $caption,
    ) {}

    public function handle(SocialPublisher $publisher): void
    {
        $this->publication->refresh();

        if ($this->publication->status === SocialPublication::STATUS_PUBLISHED) {
            return;
        }

        $publisher->runPublish($this->publication, $this->caption);
    }
}
