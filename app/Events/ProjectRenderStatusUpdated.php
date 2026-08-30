<?php

namespace App\Events;

use App\Models\VideoRender;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectRenderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VideoRender $render) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('projects.'.$this->render->project_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'render.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->render->project_id,
            'project_status' => $this->render->project->status,
            'render_id' => $this->render->id,
            'render_status' => $this->render->status,
            'output_url' => $this->render->output_url,
            'error_message' => $this->render->error_message,
        ];
    }
}
