<?php

namespace App\Models;

use Database\Factories\VideoRenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'shotstack_render_id', 'status', 'output_url', 'source_url', 'error_message', 'archived_at'])]
class VideoRender extends Model
{
    /** @use HasFactory<VideoRenderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RENDERING = 'rendering';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_RENDERING,
        self::STATUS_DONE,
        self::STATUS_FAILED,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<SocialPublication> */
    public function publications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SocialPublication::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
